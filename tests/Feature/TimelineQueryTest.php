<?php

namespace Projects13\Tests\Feature;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Customers13\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use Projects13\Queries\Timeline\TimelineQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TimelineQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_deleted_between_id_selection_and_hydration_is_skipped(): void
    {
        $customer = Customer::factory()->create();
        $deleted = Project::factory()->for($customer)->create(['name' => 'Disappearing project']);
        $remaining = Project::factory()->for($customer)->create(['name' => 'Remaining project']);
        $deletedAfterSelection = false;
        DB::listen(static function (QueryExecuted $query) use ($deleted, &$deletedAfterSelection): void {
            if ($deletedAfterSelection || ! str_starts_with($query->sql, 'select "PRO_projects"."id" from')) {
                return;
            }

            $deletedAfterSelection = true;
            $deleted->delete();
        });

        $overview = (new TimelineQuery)->overview('');

        $this->assertTrue($deletedAfterSelection);
        $this->assertSoftDeleted($deleted);
        $this->assertSame(2, $overview['paginator']->total());
        $this->assertSame([$remaining->id], $overview['entries']->pluck('projectId')->all());
        $this->assertSame([$remaining->name], $overview['entries']->pluck('row.name')->all());
    }

    public function test_project_rows_control_active_epics_in_date_order(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay());
        $customer = Customer::factory()->archived()->create();
        $project = Project::factory()->for($customer)->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        $undated = Epic::factory()->for($project)->withoutDates()->create(['name' => 'Undated']);
        $later = Epic::factory()->for($project)->create(['name' => 'Later', 'start_date' => '2026-10-12', 'end_date' => '2026-10-20']);
        $earlier = Epic::factory()->for($project)->create(['name' => 'Earlier', 'start_date' => '2026-10-01', 'end_date' => '2026-10-10']);
        Epic::factory()->for($project)->archived()->create();
        Epic::factory()->for($project)->trashed()->create();
        Project::factory()->for($customer)->archived()->create();
        Project::factory()->for($customer)->trashed()->create();

        $overview = (new TimelineQuery)->overview('');
        $entries = $overview['entries']->all();

        $this->assertSame(['project', 'epic', 'epic', 'epic'], array_column($entries, 'kind'));
        $this->assertSame([$project->id, $earlier->id, $later->id, $undated->id], array_column(array_column($entries, 'row'), 'id'));
        $this->assertSame(['timeline-epic-'.$earlier->id, 'timeline-epic-'.$later->id, 'timeline-epic-'.$undated->id], $entries[0]['controls']);
        $this->assertSame(['ontrack', 'overdue', 'ontrack', 'unscheduled'], array_column(array_column($entries, 'row'), 'status'));
        $this->assertSame(['offset' => 0.0, 'width' => 100.0], $entries[0]['row']['bar']);
        $this->assertSame(['offset' => 0.0, 'width' => 32.2581], $entries[1]['row']['bar']);
        $this->assertNull($entries[3]['row']['bar']);
        $this->assertSame(1, $overview['paginator']->total());
    }

    public function test_epic_only_search_narrows_children_but_parent_search_keeps_them(): void
    {
        $customer = Customer::factory()->create(['name' => 'North %_! customer']);
        $project = Project::factory()->for($customer)->create(['name' => 'Website renewal']);
        $matching = Epic::factory()->for($project)->withoutDates()->create(['name' => 'Special delivery']);
        Epic::factory()->for($project)->withoutDates()->create(['name' => 'Unrelated delivery']);
        Epic::factory()->for($project)->archived()->create(['name' => 'Archived special']);
        $otherCustomer = Customer::factory()->create(['name' => 'South customer']);
        Project::factory()->for($otherCustomer)->create();
        $query = new TimelineQuery;

        $overview = $query->overview('Special delivery');

        $this->assertSame([$project->id, $matching->id], array_column($overview['entries']->pluck('row')->all(), 'id'));
        $this->assertStringContainsString('search=Special%20delivery', $overview['paginator']->url(2));
        $this->assertCount(3, $query->overview('Website renewal')['entries']);
        $this->assertCount(3, $query->overview('%_!')['entries']);
        $this->assertCount(0, $query->overview('Archived special')['entries']);
        $this->assertCount(0, $query->overview('No match')['entries']);
    }

    public function test_pagination_counts_projects_not_nested_rows(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->count(25)->sequence(
            fn ($sequence): array => ['name' => 'Project '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)],
        )->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $last = Project::factory()->for($customer)->create(['name' => 'Last project', 'start_date' => '2026-02-01', 'end_date' => '2026-12-31']);
        Epic::factory()->for($last)->withoutDates()->create();
        LengthAwarePaginator::currentPageResolver(static fn (): int => 2);

        $overview = (new TimelineQuery)->overview('');

        $this->assertSame(26, $overview['paginator']->total());
        $this->assertSame(2, $overview['paginator']->currentPage());
        $this->assertCount(2, $overview['entries']);
        $this->assertSame($last->id, $overview['entries']->all()[0]['projectId']);
    }

    public function test_single_date_milestones_and_long_window_are_clamped(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay());
        $project = Project::factory()->create(['start_date' => '2020-01-01', 'end_date' => '2026-10-31']);
        Epic::factory()->for($project)->create(['name' => 'End milestone', 'start_date' => null, 'end_date' => '2026-10-11']);
        Epic::factory()->for($project)->create(['name' => 'Start milestone', 'start_date' => '2026-10-11', 'end_date' => null]);

        $overview = (new TimelineQuery)->overview('');
        $entries = $overview['entries']->all();

        $this->assertSame('2024-10-01', $overview['timeline']['from']->toDateString());
        $this->assertSame('2026-10-31', $overview['timeline']['to']->toDateString());
        $this->assertSame(['offset' => 0.0, 'width' => 100.0], $entries[0]['row']['bar']);
        $this->assertSame($entries[1]['row']['bar'], $entries[2]['row']['bar']);
        $bar = $entries[1]['row']['bar'];
        $this->assertNotNull($bar);
        $this->assertGreaterThan(0.0, $bar['width']);
    }
}
