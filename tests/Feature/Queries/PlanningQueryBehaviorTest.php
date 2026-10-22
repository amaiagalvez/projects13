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

        $this->assertCount(PlanningQuery::PER_PAGE, $result['paginator']->items());
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
