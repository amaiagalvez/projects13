<?php

namespace Projects13\Tests\Feature\Epics;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicListFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_is_empty_keeps_the_epics_without_a_start_date(): void
    {
        Epic::factory()->withoutDates()->create(['name' => 'Undated epic']);
        Epic::factory()->create(['name' => 'Dated epic']);

        $this->get(route('epics.index', ['filters' => ['start_date' => ['operator' => 'is_empty']]]))
            ->assertOk()
            ->assertSee('Undated epic')
            ->assertDontSee('Dated epic');
    }

    public function test_the_project_name_filter_keeps_the_epics_of_matching_projects(): void
    {
        Epic::factory()->for(Project::factory()->create(['name' => 'Checkout revamp']))->create(['name' => 'Payment step']);
        Epic::factory()->for(Project::factory()->create(['name' => 'Search revamp']))->create(['name' => 'Ranking tweaks']);

        $this->get(route('epics.index', ['filters' => ['project_name' => ['operator' => 'contains', 'value' => 'Checkout']]]))
            ->assertOk()
            ->assertSee('Payment step')
            ->assertDontSee('Ranking tweaks');
    }

    public function test_epics_left_outside_their_project_dates_are_flagged(): void
    {
        $project = Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-12-31']);
        $inside = Epic::factory()->for($project)->create(['start_date' => '2026-07-01', 'end_date' => '2026-08-01']);
        $outside = Epic::factory()->for($project)->create(['start_date' => '2026-07-01', 'end_date' => '2026-08-01']);
        $undated = Epic::factory()->for($project)->withoutDates()->create();

        // The project moves; the epic edit is the only place that validated the dates.
        $outside->forceFill(['end_date' => '2027-02-01'])->saveQuietly();

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('data-test="epic-outside-project-dates-'.$outside->id.'"', false)
            ->assertDontSee('data-test="epic-outside-project-dates-'.$inside->id.'"', false)
            ->assertDontSee('data-test="epic-outside-project-dates-'.$undated->id.'"', false)
            ->assertSee(__('Outside the project dates'));
    }
}
