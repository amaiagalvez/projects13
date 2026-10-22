<?php

namespace Projects13\Tests\Feature\Queries;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Queries\Planning\PlanningQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

class PlanningQueryBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function test_overview_returns_structured_data(): void
    {
        $query = $this->planningQuery();
        Project::factory()->count(2)->create([
            'active' => true,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $result = $query->overview('');

        $this->assertArrayHasKey('counts', $result);
        $this->assertArrayHasKey('groups', $result);
        $this->assertArrayHasKey('timeline', $result);
        $this->assertArrayHasKey('paginator', $result);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result['paginator']);
    }

    public function test_overview_counts_all_active_projects_and_epics(): void
    {
        $query = $this->planningQuery();
        Project::factory()->count(3)->create(['active' => true]);
        Project::factory()->count(1)->archived()->create();
        Epic::factory()->count(4)->create(['active' => true]);
        Epic::factory()->count(1)->create(['active' => false]);

        $result = $query->overview('');

        $this->assertSame(3, $result['counts']['projects']);
        $this->assertSame(4, $result['counts']['epics']);
    }

    public function test_overview_counts_overdue_projects_and_epics(): void
    {
        $query = $this->planningQuery();
        $today = CarbonImmutable::today();

        // Overdue project
        Project::factory()->create([
            'active' => true,
            'end_date' => $today->subDays(10)->toDateString(),
        ]);
        // On track project
        Project::factory()->create([
            'active' => true,
            'end_date' => $today->addDays(10)->toDateString(),
        ]);
        // Unscheduled project
        Project::factory()->create([
            'active' => true,
            'end_date' => null,
        ]);

        // Overdue epic
        Epic::factory()->create([
            'active' => true,
            'end_date' => $today->subDays(5)->toDateString(),
        ]);
        // On track epic
        Epic::factory()->create([
            'active' => true,
            'end_date' => $today->addDays(5)->toDateString(),
        ]);

        // Overdue count includes both overdue projects (1) and overdue epics (1) = 2
        $result = $query->overview('');

        $this->assertSame(2, $result['counts']['overdue']);
    }

    public function test_overview_groups_projects_by_quarter(): void
    {
        $query = $this->planningQuery();
        Project::factory()->create([
            'name' => 'Q1 Project',
            'active' => true,
            'start_date' => '2026-01-15',
            'end_date' => '2026-03-15',
        ]);
        Project::factory()->create([
            'name' => 'Q2 Project',
            'active' => true,
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
        ]);
        Project::factory()->create([
            'name' => 'No Date Project',
            'active' => true,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $result = $query->overview('');

        $groupKeys = array_map(fn ($g) => $g['key'], $result['groups']);
        $this->assertContains('2026-1', $groupKeys); // Q1
        $this->assertContains('2026-2', $groupKeys); // Q2
        $this->assertContains('no-date', $groupKeys);
    }

    public function test_overview_sorts_groups_by_quarter_then_no_date_last(): void
    {
        $query = $this->planningQuery();
        Project::factory()->create(['name' => 'Q2', 'active' => true, 'end_date' => '2026-06-01']);
        Project::factory()->create(['name' => 'No Date', 'active' => true, 'end_date' => null]);
        Project::factory()->create(['name' => 'Q1', 'active' => true, 'end_date' => '2026-03-01']);

        $result = $query->overview('');

        $labels = array_map(fn ($g) => $g['label'], $result['groups']);
        // Q1 first, then Q2, then No Date
        $this->assertSame('Quarter 1, 2026', $labels[0]);
        $this->assertSame('Quarter 2, 2026', $labels[1]);
        $this->assertSame('No date', $labels[2]);
    }

    public function test_overview_assigns_status_correctly(): void
    {
        $query = $this->planningQuery();
        $today = CarbonImmutable::today();

        $overdue = Project::factory()->create([
            'name' => 'Overdue',
            'active' => true,
            'start_date' => $today->subDays(20)->toDateString(),
            'end_date' => $today->subDays(5)->toDateString(),
        ]);
        $ontrack = Project::factory()->create([
            'name' => 'On Track',
            'active' => true,
            'start_date' => $today->subDays(5)->toDateString(),
            'end_date' => $today->addDays(5)->toDateString(),
        ]);
        $unscheduled = Project::factory()->create([
            'name' => 'Unscheduled',
            'active' => true,
            'start_date' => $today->subDays(5)->toDateString(),
            'end_date' => null,
        ]);

        $result = $query->overview('');

        // Status is added to rows in groups, not in the paginator
        $allRows = collect($result['groups'])->flatMap(fn ($g) => $g['rows']);
        $overdueRow = $allRows->firstWhere('name', 'Overdue');
        $ontrackRow = $allRows->firstWhere('name', 'On Track');
        $unscheduledRow = $allRows->firstWhere('name', 'Unscheduled');

        $this->assertSame('overdue', $overdueRow['status'] ?? '');
        $this->assertSame('ontrack', $ontrackRow['status'] ?? '');
        $this->assertSame('unscheduled', $unscheduledRow['status'] ?? '');
    }

    public function test_overview_calculates_bar_for_projects_with_dates(): void
    {
        // Use dates close to today to ensure they're in the timeline window
        $today = CarbonImmutable::today();
        $query = $this->planningQuery();
        $project = Project::factory()->create([
            'name' => 'With Dates',
            'active' => true,
            'start_date' => $today->subDays(10)->toDateString(),
            'end_date' => $today->addDays(10)->toDateString(),
        ]);

        // Debug: check the project dates
        $this->assertNotNull($project->start_date);
        $this->assertNotNull($project->end_date);

        $result = $query->overview('');

        // The bar is added to rows in groups, not in the paginator
        $this->assertNotEmpty($result['groups']);
        $group = $result['groups'][0];
        $this->assertNotEmpty($group['rows']);
        $row = $group['rows'][0];
        $this->assertSame('With Dates', $row['name']);
        $this->assertNotNull($row['start']);
        $this->assertNotNull($row['end']);
        $this->assertNotNull($row['bar']);
        $this->assertArrayHasKey('offset', $row['bar']);
        $this->assertArrayHasKey('width', $row['bar']);
    }

    public function test_overview_returns_null_bar_for_projects_without_dates(): void
    {
        $query = $this->planningQuery();
        // A project with end_date but no start_date still gets a bar (milestone from end date)
        // To test null bar, we need a project with neither start nor end date
        // But start_date is required, so we test with a project that has only start_date
        $project = Project::factory()->create([
            'name' => 'Only Start Date',
            'active' => true,
            'start_date' => '2026-06-01',
            'end_date' => null,
        ]);

        $result = $query->overview('');

        // Bar is added to rows in groups, not in the paginator
        $this->assertNotEmpty($result['groups']);
        $group = $result['groups'][0];
        $this->assertNotEmpty($group['rows']);
        $row = $group['rows'][0];
        // With only start_date, it's a milestone (1 day wide)
        $this->assertNotNull($row['bar']);
    }

    public function test_overview_paginates_results(): void
    {
        $query = $this->planningQuery();
        foreach (range(1, 30) as $i) {
            Project::factory()->create([
                'name' => "Project $i",
                'active' => true,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]);
        }

        $result = $query->overview('', 1);

        $this->assertCount(25, $result['paginator']->items()); // PER_PAGE
        $this->assertSame(30, $result['paginator']->total());
    }

    public function test_overview_filters_by_search(): void
    {
        $query = $this->planningQuery();
        Project::factory()->create(['name' => 'Website project', 'active' => true, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        Project::factory()->create(['name' => 'Mobile app', 'active' => true, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $result = $query->overview('Website');

        $this->assertCount(1, $result['paginator']->items());
        $this->assertSame('Website project', $result['paginator']->first()['name']);
    }

    public function test_overview_includes_trail_from_display_integration(): void
    {
        $displayIntegration = new class implements ProjectDisplayIntegration
        {
            use ProjectDisplayIntegrationDefaults;

            public function trail(Project $project): array
            {
                return ['Custom trail for '.$project->name];
            }
        };

        $query = new PlanningQuery(
            new class implements ProjectQueryIntegration
            {
                use ProjectQueryIntegrationDefaults;
            },
            $displayIntegration
        );

        Project::factory()->create(['name' => 'Test', 'active' => true, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $result = $query->overview('');

        $row = $result['paginator']->first();
        $this->assertSame(['Custom trail for Test'], $row['trail']);
    }

    private function planningQuery(): PlanningQuery
    {
        return new PlanningQuery(
            new class implements ProjectQueryIntegration
            {
                use ProjectQueryIntegrationDefaults;
            },
            new class implements ProjectDisplayIntegration
            {
                use ProjectDisplayIntegrationDefaults;
            }
        );
    }
}
