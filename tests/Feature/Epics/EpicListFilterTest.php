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
}
