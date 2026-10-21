<?php

namespace Projects13\Tests\Feature\Projects;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_can_view_projects_index(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('projects.index'));

        $response->assertOk()
            ->assertSee('Start date')
            ->assertDontSee('projects13::messages.', false);
    }

    public function test_can_create_a_project(): void
    {
        $this->actingAs(User::factory()->create());

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

    public function test_json_request_creates_a_project_and_returns_its_identity(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('projects.store'), [
            'name' => 'JSON project',
            'start_date' => '2026-10-01',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'JSON project');

        $this->assertDatabaseHas('PRO_projects', ['name' => 'JSON project']);
    }

    public function test_json_request_updates_a_project_and_returns_its_identity(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create(['name' => 'Original project']);

        $this->putJson(route('projects.update', $project), [
            'name' => 'Updated JSON project',
            'start_date' => '2026-10-01',
        ])
            ->assertOk()
            ->assertJsonPath('id', $project->id)
            ->assertJsonPath('name', 'Updated JSON project');

        $this->assertDatabaseHas('PRO_projects', [
            'id' => $project->id,
            'name' => 'Updated JSON project',
        ]);
    }

    public function test_can_update_a_project(): void
    {
        $this->actingAs(User::factory()->create());

        $project = Project::factory()->create(['name' => 'Original']);

        $payload = [
            'name' => 'Updated',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
        ];

        $response = $this->put(route('projects.update', $project), $payload);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.updated'));

        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Updated']);
    }

    public function test_can_delete_a_project_moves_to_trash(): void
    {
        $this->actingAs(User::factory()->create());

        $project = Project::factory()->create();

        $response = $this->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));

        $this->assertSoftDeleted($project);
    }

    public function test_cannot_move_a_project_with_epics_to_trash(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));

        $this->assertModelExists($project);
        $this->assertModelExists($epic);
    }

    public function test_cannot_create_project_with_duplicate_name(): void
    {
        $this->actingAs(User::factory()->create());

        Project::factory()->create(['name' => 'Duplicate name']);

        $payload = [
            'name' => 'Duplicate name',
            'start_date' => '2026-10-01',
        ];

        $this->from(route('projects.index'))->post(route('projects.store'), $payload)
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('PRO_projects', 1);
    }

    public function test_cannot_create_project_with_name_too_short(): void
    {
        $this->actingAs(User::factory()->create());

        $payload = [
            'name' => 'Abc',
            'start_date' => '2026-10-01',
        ];

        $this->from(route('projects.index'))->post(route('projects.store'), $payload)
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('PRO_projects', 0);
    }

    public function test_cannot_create_project_without_start_date(): void
    {
        $this->actingAs(User::factory()->create());

        $payload = [
            'name' => 'No start date',
        ];

        $this->from(route('projects.index'))->post(route('projects.store'), $payload)
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors(['start_date']);

        $this->assertDatabaseCount('PRO_projects', 0);
    }

    public function test_can_create_project_with_null_end_date(): void
    {
        $this->actingAs(User::factory()->create());

        $payload = [
            'name' => 'Ongoing project',
            'start_date' => '2023-01-01',
            'end_date' => null,
        ];

        $this->post(route('projects.store'), $payload)
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('PRO_projects', [
            'name' => 'Ongoing project',
            'end_date' => null,
        ]);
    }
}
