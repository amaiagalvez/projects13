<?php

namespace Projects13\Tests\Feature;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Customers13\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;
use PHPUnit\Framework\Attributes\DataProvider;
use Projects13\Queries\Planning\PlanningQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlanningQueryTest extends TestCase
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

        $overview = (new PlanningQuery)->overview('');

        $this->assertTrue($deletedAfterSelection);
        $this->assertSoftDeleted($deleted);
        $this->assertSame(2, $overview['paginator']->total());
        $this->assertSame([$remaining->name], array_column($overview['paginator']->items(), 'name'));
        $this->assertSame([$remaining->name], array_column($overview['groups'][0]['rows'], 'name'));
        $this->assertSame(1, $overview['counts']['projects']);
    }

    public function test_groups_sort_deadlines_and_count_only_active_work(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay());
        $customer = Customer::factory()->archived()->create(['name' => 'Archived customer']);
        $later = Project::factory()->for($customer)->create(['name' => 'Later project', 'start_date' => '2026-10-01', 'end_date' => '2027-01-31']);
        $undated = Project::factory()->for($customer)->create(['name' => 'No deadline', 'start_date' => '2026-10-01', 'end_date' => null]);
        $overdue = Project::factory()->for($customer)->create(['name' => 'Overdue project', 'start_date' => '2026-01-01', 'end_date' => '2026-09-30']);
        $today = Project::factory()->for($customer)->create(['name' => 'Due today', 'start_date' => '2026-10-01', 'end_date' => '2026-10-11']);
        Project::factory()->for($customer)->archived()->create();
        Project::factory()->for($customer)->trashed()->create();
        Epic::factory()->for($overdue)->create(['start_date' => '2026-02-01', 'end_date' => '2026-09-01']);
        Epic::factory()->for($overdue)->archived()->create();

        $overview = (new PlanningQuery)->overview('');

        $this->assertSame(['customers' => 0, 'projects' => 4, 'epics' => 1, 'overdue' => 2], $overview['counts']);
        $this->assertSame(['2026-3', '2026-4', '2027-1', 'no-date'], array_column($overview['groups'], 'key'));
        $rows = $overview['paginator']->items();
        $this->assertSame([$overdue->name, $today->name, $later->name, $undated->name], array_column($rows, 'name'));
        $this->assertSame(['overdue', 'ontrack', 'ontrack', 'unscheduled'], array_column($rows, 'status'));
        $this->assertSame(['Archived customer'], $rows[0]['trail']);
        $this->assertSame(1, $rows[0]['count']['value']);
        $this->assertSame(route('epics.index', ['search' => $overdue->name]), $rows[0]['count']['url']);
        $this->assertNull($rows[1]['count']['url']);
        $this->assertSame(4, $overview['paginator']->total());
    }

    public function test_search_filters_projects_not_epic_names_and_keeps_global_counts(): void
    {
        $customer = Customer::factory()->create(['name' => 'North %_! customer']);
        $project = Project::factory()->for($customer)->create(['name' => 'Website renewal']);
        Epic::factory()->for($project)->create(['name' => 'Only epic matches']);
        $other = Customer::factory()->create(['name' => 'South customer']);
        Project::factory()->for($other)->create();
        $query = new PlanningQuery;

        $overview = $query->overview('%_!');

        $this->assertSame([$project->name], array_column($overview['paginator']->items(), 'name'));
        $this->assertSame(2, $overview['counts']['projects']);
        $this->assertSame([], $query->overview('Only epic matches')['groups']);
    }

    public function test_page_is_cut_before_hydration_and_deadline_ordering(): void
    {
        $customer = Customer::factory()->create();
        Project::factory()->for($customer)->count(25)->sequence(
            fn ($sequence): array => ['name' => 'Project '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)],
        )->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $last = Project::factory()->for($customer)->create(['name' => 'Last project', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01']);

        $overview = (new PlanningQuery)->overview('', 2);

        $this->assertSame(26, $overview['paginator']->total());
        $this->assertSame(2, $overview['paginator']->currentPage());
        $this->assertSame([$last->name], array_column($overview['paginator']->items(), 'name'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: float}>
     */
    public static function months(): array
    {
        return [
            '31 days' => ['2026-10-01', '2026-10-31', 31, 32.2581],
            '30 days' => ['2026-04-01', '2026-04-30', 30, 33.3333],
            'February' => ['2026-02-01', '2026-02-28', 28, 35.7143],
            'leap February' => ['2024-02-01', '2024-02-29', 29, 34.4828],
        ];
    }

    #[DataProvider('months')]
    public function test_month_window_and_inclusive_bar_percentages(string $start, string $end, int $days, float $today): void
    {
        $this->travelTo($start.' 00:00:00');
        $this->travel(10)->days();
        Project::factory()->create(['start_date' => $start, 'end_date' => $end]);

        $overview = (new PlanningQuery)->overview('');

        $this->assertSame($start, $overview['timeline']['from']->toDateString());
        $this->assertSame($days, $overview['timeline']['days']);
        $this->assertSame([['label' => substr($start, 0, 7), 'width' => 100.0]], $overview['timeline']['months']);
        $this->assertSame($today, $overview['timeline']['today']);
        $this->assertSame(['offset' => 0.0, 'width' => 100.0], $overview['groups'][0]['rows'][0]['bar']);
    }

    public function test_long_window_slides_forward_and_clamps_bar_offset(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay());
        Project::factory()->create(['start_date' => '2020-01-01', 'end_date' => '2026-10-31']);

        $overview = (new PlanningQuery)->overview('');

        $this->assertSame('2024-10-01', $overview['timeline']['from']->toDateString());
        $this->assertSame('2026-10-31', $overview['timeline']['to']->toDateString());
        $this->assertSame(['offset' => 0.0, 'width' => 100.0], $overview['groups'][0]['rows'][0]['bar']);
    }
}
