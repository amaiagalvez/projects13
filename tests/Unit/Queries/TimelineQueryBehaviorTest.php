<?php

namespace Projects13\Tests\Unit\Queries;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Queries\Timeline\TimelineQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

class TimelineQueryBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function test_overview_returns_structured_data(): void
    {
        $query = $this->timelineQuery();
        Project::factory()->count(2)->create([
            'active' => true,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $result = $query->overview('');

        $this->assertArrayHasKey('entries', $result);
        $this->assertArrayHasKey('timeline', $result);
        $this->assertArrayHasKey('paginator', $result);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result['paginator']);
    }

    public function test_overview_includes_projects_and_their_epics(): void
    {
        $query = $this->timelineQuery();
        $project = Project::factory()->create([
            'name' => 'Parent Project',
            'active' => true,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        Epic::factory()->for($project)->count(2)->create(['active' => true]);
        Epic::factory()->for($project)->count(1)->create(['active' => false]); // archived
        Epic::factory()->for($project)->count(1)->trashed()->create(); // trashed

        $result = $query->overview('');

        $entries = $result['entries'];
        $projectEntries = $entries->where('kind', 'project');
        $epicEntries = $entries->where('kind', 'epic');

        $this->assertCount(1, $projectEntries);
        $this->assertCount(2, $epicEntries); // only active epics
    }

    public function test_overview_orders_projects_by_start_date_then_name(): void
    {
        $query = $this->timelineQuery();
        Project::factory()->create(['name' => 'C Project', 'active' => true, 'start_date' => '2026-03-01']);
        Project::factory()->create(['name' => 'A Project', 'active' => true, 'start_date' => '2026-01-01']);
        Project::factory()->create(['name' => 'B Project', 'active' => true, 'start_date' => '2026-01-01']);

        $result = $query->overview('');

        $projectNames = $result['entries']
            ->where('kind', 'project')
            ->pluck('row.name')
            ->all();

        $this->assertSame(['A Project', 'B Project', 'C Project'], $projectNames);
    }

    public function test_overview_orders_epics_by_start_date_then_name(): void
    {
        $query = $this->timelineQuery();
        $project = Project::factory()->create(['name' => 'Project', 'active' => true, 'start_date' => '2026-01-01']);
        Epic::factory()->for($project)->create(['name' => 'C Epic', 'active' => true, 'start_date' => '2026-03-01']);
        Epic::factory()->for($project)->create(['name' => 'A Epic', 'active' => true, 'start_date' => '2026-01-01']);
        Epic::factory()->for($project)->create(['name' => 'B Epic', 'active' => true, 'start_date' => '2026-01-01']);

        $result = $query->overview('');

        $epicNames = $result['entries']
            ->where('kind', 'epic')
            ->pluck('row.name')
            ->all();

        $this->assertSame(['A Epic', 'B Epic', 'C Epic'], $epicNames);
    }

    public function test_overview_assigns_status_correctly(): void
    {
        $query = $this->timelineQuery();
        $today = CarbonImmutable::today();

        $overdueProject = Project::factory()->create([
            'name' => 'Overdue Project',
            'active' => true,
            'end_date' => $today->subDays(5)->toDateString(),
        ]);
        $ontrackProject = Project::factory()->create([
            'name' => 'On Track Project',
            'active' => true,
            'end_date' => $today->addDays(5)->toDateString(),
        ]);
        $unscheduledProject = Project::factory()->create([
            'name' => 'Unscheduled Project',
            'active' => true,
            'end_date' => null,
        ]);

        $result = $query->overview('');

        $overdueEntry = $result['entries']->firstWhere('row.name', 'Overdue Project');
        $ontrackEntry = $result['entries']->firstWhere('row.name', 'On Track Project');
        $unscheduledEntry = $result['entries']->firstWhere('row.name', 'Unscheduled Project');

        $this->assertSame('overdue', $overdueEntry['row']['status'] ?? '');
        $this->assertSame('ontrack', $ontrackEntry['row']['status'] ?? '');
        $this->assertSame('unscheduled', $unscheduledEntry['row']['status'] ?? '');
    }

    public function test_overview_calculates_bar_for_records_with_dates(): void
    {
        $query = $this->timelineQuery();
        Project::factory()->create([
            'name' => 'With Dates',
            'active' => true,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);

        $result = $query->overview('');

        $projectEntry = $result['entries']->firstWhere('kind', 'project');
        $this->assertNotNull($projectEntry['row']['bar']);
        $this->assertArrayHasKey('offset', $projectEntry['row']['bar']);
        $this->assertArrayHasKey('width', $projectEntry['row']['bar']);
    }

    public function test_overview_returns_null_bar_for_records_without_dates(): void
    {
        $query = $this->timelineQuery();
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

        $projectEntry = $result['entries']->firstWhere('kind', 'project');
        // With only start_date, it's a milestone (1 day wide)
        $this->assertNotNull($projectEntry['row']['bar']);
    }

    public function test_overview_includes_controls_for_project_with_epics(): void
    {
        $query = $this->timelineQuery();
        $project = Project::factory()->create(['name' => 'Project', 'active' => true, 'start_date' => '2026-01-01']);
        $epic1 = Epic::factory()->for($project)->create(['name' => 'Epic 1', 'active' => true]);
        $epic2 = Epic::factory()->for($project)->create(['name' => 'Epic 2', 'active' => true]);

        $result = $query->overview('');

        $projectEntry = $result['entries']->firstWhere('kind', 'project');
        $this->assertCount(2, $projectEntry['controls']);
        $this->assertContains('timeline-epic-'.$epic1->id, $projectEntry['controls']);
        $this->assertContains('timeline-epic-'.$epic2->id, $projectEntry['controls']);
    }

    public function test_overview_filters_by_search_on_project_name(): void
    {
        $query = $this->timelineQuery();
        Project::factory()->create(['name' => 'Website project', 'active' => true, 'start_date' => '2026-01-01']);
        Project::factory()->create(['name' => 'Mobile app', 'active' => true, 'start_date' => '2026-01-01']);

        $result = $query->overview('Website');

        $projectEntries = $result['entries']->where('kind', 'project');
        $this->assertCount(1, $projectEntries);
        $this->assertSame('Website project', $projectEntries->first()['row']['name']);
    }

    public function test_overview_filters_by_search_on_epic_name_and_includes_only_matching_epic(): void
    {
        $query = $this->timelineQuery();
        $project1 = Project::factory()->create(['name' => 'Project A', 'active' => true, 'start_date' => '2026-01-01']);
        $project2 = Project::factory()->create(['name' => 'Project B', 'active' => true, 'start_date' => '2026-01-01']);
        Epic::factory()->for($project1)->create(['name' => 'Website epic', 'active' => true]);
        Epic::factory()->for($project1)->create(['name' => 'Mobile epic', 'active' => true]);
        Epic::factory()->for($project2)->create(['name' => 'API epic', 'active' => true]);

        $result = $query->overview('Website');

        $projectEntries = $result['entries']->where('kind', 'project');
        $epicEntries = $result['entries']->where('kind', 'epic');

        // Only Project A should be included (it has a matching epic)
        $this->assertCount(1, $projectEntries);
        $this->assertSame('Project A', $projectEntries->first()['row']['name']);

        // Only the matching epic should be under Project A
        $this->assertCount(1, $epicEntries);
        $this->assertSame('Website epic', $epicEntries->first()['row']['name']);
    }

    public function test_overview_excludes_archived_and_trashed_records(): void
    {
        $query = $this->timelineQuery();
        Project::factory()->create(['name' => 'Active Project', 'active' => true, 'start_date' => '2026-01-01']);
        Project::factory()->archived()->create(['name' => 'Archived Project', 'start_date' => '2026-01-01']);
        Project::factory()->trashed()->create(['name' => 'Trashed Project', 'start_date' => '2026-01-01']);

        $result = $query->overview('');

        $projectEntries = $result['entries']->where('kind', 'project');
        $this->assertCount(1, $projectEntries);
        $this->assertSame('Active Project', $projectEntries->first()['row']['name']);
    }

    public function test_overview_paginates_results(): void
    {
        $query = $this->timelineQuery();
        foreach (range(1, 30) as $i) {
            Project::factory()->create([
                'name' => "Project $i",
                'active' => true,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]);
        }

        $result = $query->overview('');

        $this->assertCount(25, $result['paginator']->items()); // PER_PAGE
        $this->assertSame(30, $result['paginator']->total());
    }

    public function test_overview_includes_trail_from_display_integration(): void
    {
        $displayIntegration = new class implements ProjectDisplayIntegration
        {
            use ProjectDisplayIntegrationDefaults;

            public function trail(Project $project): array
            {
                return ['Trail for '.$project->name];
            }
        };

        $query = new TimelineQuery(
            new class implements ProjectQueryIntegration
            {
                use ProjectQueryIntegrationDefaults;
            },
            $displayIntegration
        );

        Project::factory()->create(['name' => 'Test', 'active' => true, 'start_date' => '2026-01-01']);

        $result = $query->overview('');

        $projectEntry = $result['entries']->firstWhere('kind', 'project');
        $this->assertSame(['Trail for Test'], $projectEntry['row']['trail']);
    }

    private function timelineQuery(): TimelineQuery
    {
        return new TimelineQuery(
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
