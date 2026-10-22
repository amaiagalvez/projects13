<?php

namespace Projects13\Tests\Feature\Queries;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Projects13\Queries\Planning\PlanningQuery;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Queries\Dashboard\DashboardSummaryQuery;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

class DashboardSummaryQueryTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
        $this->today = CarbonImmutable::parse('2026-10-22');
        $this->project = Project::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2027-12-31']);
    }

    public function test_counts_epics_ending_this_month_and_lists_the_next_deadlines_in_order(): void
    {
        $later = $this->epic('Later', '2026-11-15');
        $soon = $this->epic('Soon', '2026-10-25');
        $this->epic('Ended', '2026-10-01');
        $this->epic('Archived', '2026-10-28')->forceFill(['active' => false])->save();

        $summary = $this->dashboard()->summary($this->today);

        $this->assertSame(1, $summary['dueThisMonth']);
        $this->assertSame([$soon->id, $later->id], array_column($summary['deadlines'], 'id'));
        $this->assertSame($this->project->name, $summary['deadlines'][0]['project']);
        $this->assertSame('2026-10-25', $summary['deadlines'][0]['endDate']);
        $this->assertSame(route('epics.index', ['search' => 'Soon']), $summary['deadlines'][0]['url']);
    }

    public function test_counts_this_week_comments_and_lists_the_latest_first(): void
    {
        $epic = $this->epic('Commented', '2026-11-30');
        $old = EpicComment::factory()->for($epic)->create(['created_at' => '2026-10-01 10:00:00']);
        $recent = EpicComment::factory()->for($epic)->create(['created_at' => '2026-10-21 10:00:00']);

        $summary = $this->dashboard()->summary($this->today);

        $this->assertSame(1, $summary['commentsThisWeek']);
        $this->assertSame([$recent->id, $old->id], array_column($summary['comments'], 'id'));
        $this->assertSame('Commented', $summary['comments'][0]['epic']);
        $this->assertNotSame('', $summary['comments'][0]['initials']);
    }

    public function test_reuses_the_planning_counts(): void
    {
        $this->epic('Late', '2026-10-01');

        $summary = $this->dashboard()->summary($this->today);

        $this->assertSame($this->planning()->counts($this->today), $summary['counts']);
        $this->assertSame(1, $summary['counts']['overdue']);
    }

    private function epic(string $name, string $endDate): Epic
    {
        return Epic::factory()->for($this->project)->create([
            'name' => $name,
            'start_date' => '2026-01-01',
            'end_date' => $endDate,
        ]);
    }

    private function dashboard(): DashboardSummaryQuery
    {
        return new DashboardSummaryQuery($this->planning());
    }

    private function planning(): PlanningQuery
    {
        return new PlanningQuery(
            new class implements ProjectQueryIntegration
            {
                use ProjectQueryIntegrationDefaults;
            },
            new class implements ProjectDisplayIntegration
            {
                use ProjectDisplayIntegrationDefaults;
            },
        );
    }
}
