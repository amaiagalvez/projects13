<?php

namespace Projects13\Tests\Feature\Projects;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_index_returns_active_projects(): void
    {
        $this->signIn();
        $project1 = Project::factory()->create(['name' => 'Project A', 'start_date' => '2026-01-01']);
        $project2 = Project::factory()->create(['name' => 'Project B', 'start_date' => '2026-02-01']);
        Project::factory()->archived()->create(['name' => 'Archived Project']);
        Project::factory()->trashed()->create(['name' => 'Trashed Project']);

        $response = $this->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('Project A');
        $response->assertSee('Project B');
        $response->assertDontSee('Archived Project');
        $response->assertDontSee('Trashed Project');
    }

    public function test_index_shows_projects_ordered_by_start_date(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Later Project', 'start_date' => '2026-03-01']);
        Project::factory()->create(['name' => 'Earlier Project', 'start_date' => '2026-01-01']);

        $response = $this->get(route('projects.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Earlier Project', 'Later Project']);
    }

    public function test_index_searches_by_name(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Website renewal']);
        Project::factory()->create(['name' => 'Mobile app']);

        $response = $this->get(route('projects.index', ['search' => 'Website']));

        $response->assertOk();
        $response->assertSee('Website renewal');
        $response->assertDontSee('Mobile app');
    }

    public function test_index_searches_by_start_date(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Project A', 'start_date' => '2026-01-15']);
        Project::factory()->create(['name' => 'Project B', 'start_date' => '2026-06-01']);

        $response = $this->get(route('projects.index', ['search' => '2026-01']));

        $response->assertOk();
        $response->assertSee('Project A');
        $response->assertDontSee('Project B');
    }

    public function test_store_creates_project_and_redirects(): void
    {
        $this->signIn();

        $payload = [
            'name' => 'New project',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
        ];

        $response = $this->post(route('projects.store'), $payload);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));
        $this->assertDatabaseHas('PRO_projects', ['name' => 'New project']);
    }

    public function test_store_returns_json_when_expected(): void
    {
        $this->signIn();

        $response = $this->postJson(route('projects.store'), [
            'name' => 'JSON project',
            'start_date' => '2026-10-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'JSON project')
            ->assertJsonStructure(['id', 'name']);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'JSON project']);
    }

    public function test_store_with_trashed_same_name_shows_conflict_without_reuse_flag(): void
    {
        $this->signIn();
        $trashedProject = Project::factory()->trashed()->create(['name' => 'Recycled project']);

        $response = $this->from(route('projects.index'))->post(route('projects.store'), [
            'name' => 'Recycled project',
            'start_date' => '2026-10-01',
        ]);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('deleted_project_conflict');
        $this->assertDatabaseCount('PRO_projects', 1); // Still only the trashed one
        $this->assertSoftDeleted($trashedProject);
    }

    public function test_store_with_trashed_same_name_and_reuse_flag_restores(): void
    {
        $this->signIn();
        $trashedProject = Project::factory()->trashed()->create(['name' => 'Recycled project']);

        $response = $this->from(route('projects.index'))->post(route('projects.store'), [
            'name' => 'Recycled project',
            'start_date' => '2026-10-01',
            'reuse_deleted_name' => true,
        ]);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));
        // A NEW project is created with the same name (trashed project remains in trash)
        $this->assertDatabaseCount('PRO_projects', 2);
        $this->assertSoftDeleted($trashedProject);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Recycled project', 'deleted_at' => null]);
    }

    public function test_update_changes_project_and_redirects(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Original name']);

        $payload = [
            'name' => 'Updated name',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
        ];

        $response = $this->put(route('projects.update', $project), $payload);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.updated'));
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Updated name']);
    }

    public function test_update_returns_json_when_expected(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Original']);

        $response = $this->putJson(route('projects.update', $project), [
            'name' => 'Updated JSON',
            'start_date' => '2026-10-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('id', $project->id)
            ->assertJsonPath('name', 'Updated JSON');
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Updated JSON']);
    }

    public function test_destroy_moves_project_to_trash(): void
    {
        $this->signIn();
        $project = Project::factory()->create();

        $response = $this->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));
        $this->assertSoftDeleted($project);
    }

    public function test_destroy_refuses_when_project_has_epics(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $response = $this->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));
        $this->assertModelExists($project);
        $this->assertModelExists($epic);
    }

    public function test_destroy_refuses_when_project_has_trashed_epics(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->trashed()->create();

        $response = $this->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));
        $this->assertModelExists($project);
        $this->assertSoftDeleted($epic);
    }

    public function test_select_options_returns_active_projects(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Active project']);
        Project::factory()->archived()->create(['name' => 'Archived project']);

        $response = $this->getJson(route('projects.options', ['search' => '']));

        $response->assertOk();
        $data = $response->json('results');
        $this->assertCount(1, $data);
        $this->assertSame('Active project', $data[0]['text']);
    }

    public function test_select_options_searches_by_name(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Website project unique test', 'active' => true]);
        Project::factory()->create(['name' => 'Mobile project unique test', 'active' => true]);

        $response = $this->getJson(route('projects.options', ['q' => 'Website project unique']));

        $response->assertOk();
        $data = $response->json('results');
        $this->assertCount(1, $data);
        $this->assertSame('Website project unique test', $data[0]['text']);
    }

    public function test_select_options_limits_results(): void
    {
        $this->signIn();
        foreach (range(1, 25) as $i) {
            Project::factory()->create(['name' => "Project $i"]);
        }

        $response = $this->getJson(route('projects.options', ['search' => '']));

        $response->assertOk();
        $data = $response->json('results');
        $this->assertCount(25, $data); // PER_PAGE from ListQueryBase is 25
    }

    public function test_select_options_rejects_long_search_terms(): void
    {
        $this->signIn();
        $longSearch = str_repeat('a', 256);

        $response = $this->getJson(route('projects.options', ['q' => $longSearch]));

        $response->assertUnprocessable();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->post(route('projects.store'))->assertRedirect(route('login'));
        $this->getJson(route('projects.options'))->assertUnauthorized();
    }
}
