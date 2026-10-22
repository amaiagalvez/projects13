<?php

namespace Projects13\Tests\Feature\Epics;

use App\Models\User;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Basics13\Queries\ListQueryBase;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectSelectOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_epic_list_offers_project_form_when_no_projects_exist(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertViewHas('hasProjects', false)
            ->assertSee(__('No active projects are available. Open the project form to create one.'))
            ->assertSee(__('Open project form'))
            ->assertSee('href="'.route('projects.index', ['create' => 1]).'"', false)
            ->assertSee('epic-no-project-form-button');
    }

    public function test_epic_list_offers_project_form_when_only_archived_projects_exist(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->archived()->create();

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertViewHas('hasProjects', false)
            ->assertSee('epic-no-project-form-button');
    }

    public function test_epic_list_offers_project_form_when_only_deleted_projects_exist(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->trashed()->create();

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertViewHas('hasProjects', false)
            ->assertSee('epic-no-project-form-button');
    }

    public function test_epic_list_hides_project_form_notice_when_an_active_project_exists(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->create();

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertViewHas('hasProjects', true)
            ->assertDontSee('epic-no-project-form-button');
    }

    public function test_epic_form_does_not_preload_every_project(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $total = ListQueryBase::PER_PAGE + 1;

        for ($index = 1; $index <= $total; $index++) {
            Project::factory()->create([
                'name' => sprintf('Option-only project %02d', $index),
            ]);
        }

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertDontSee('Option-only project 21');
    }

    public function test_project_options_search_by_project_and_are_limited_to_the_shared_page_size(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $total = ListQueryBase::PER_PAGE + 1;

        for ($index = 1; $index <= $total; $index++) {
            Project::factory()->create([
                'name' => sprintf('Project result %02d', $index),
            ]);
        }

        $response = $this->getJson(route('projects.options', ['q' => 'Project result']));

        $response->assertOk()
            ->assertJsonCount(ListQueryBase::PER_PAGE, 'results')
            ->assertJsonPath('results.0.text', 'Project result 01')
            ->assertJsonMissing(['text' => sprintf('Project result %02d', $total)]);

        $this->getJson(route('projects.options', ['q' => sprintf('Project result %02d', $total)]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', sprintf('Project result %02d', $total));
    }

    public function test_project_options_only_include_active_projects(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Project::factory()->create(['name' => 'Active select project']);
        Project::factory()->archived()->create(['name' => 'Inactive select project']);
        Project::factory()->trashed()->create(['name' => 'Deleted select project']);

        $this->getJson(route('projects.options', ['q' => 'select project']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', 'Active select project');
    }

    public function test_project_options_reject_search_terms_over_the_configured_maximum(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $term = str_repeat('a', MaxLength::string() + 1);

        $this->getJson(route('projects.options', ['q' => $term]))
            ->assertJsonValidationErrors('q');
    }

    public function test_guests_are_redirected_when_requesting_project_options(): void
    {
        $this->get(route('projects.options'))
            ->assertRedirect(route('login'));
    }
}
