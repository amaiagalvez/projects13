<?php

namespace Projects13\Tests\Feature\Projects;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectTrashControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_index_returns_trashed_projects(): void
    {
        $this->signIn();
        $trashed1 = Project::factory()->trashed()->create(['name' => 'Deleted A', 'deleted_at' => '2026-10-01 10:00:00']);
        $trashed2 = Project::factory()->trashed()->create(['name' => 'Deleted B', 'deleted_at' => '2026-10-02 10:00:00']);
        Project::factory()->create(['name' => 'Active Project']);

        $response = $this->get(route('projects.trash.index'));

        $response->assertOk();
        $response->assertSee('Deleted A');
        $response->assertSee('Deleted B');
        $response->assertDontSee('Active Project');
    }

    public function test_index_orders_by_deleted_at_desc_then_id_asc(): void
    {
        $this->signIn();
        $this->travelTo('2026-10-02 12:00:00');
        $older = Project::factory()->trashed()->create(['name' => 'Older']);
        $older->forceFill(['deleted_at' => '2026-10-01 10:00:00'])->saveQuietly();
        $newer = Project::factory()->trashed()->create(['name' => 'Newer']);
        $newer->forceFill(['deleted_at' => '2026-10-02 10:00:00'])->saveQuietly();

        $response = $this->get(route('projects.trash.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Newer', 'Older']);
    }

    public function test_index_searches_by_name(): void
    {
        $this->signIn();
        Project::factory()->trashed()->create(['name' => 'Website project']);
        Project::factory()->trashed()->create(['name' => 'Mobile project']);

        $response = $this->get(route('projects.trash.index', ['search' => 'Website']));

        $response->assertOk();
        $response->assertSee('Website project');
        $response->assertDontSee('Mobile project');
    }

    public function test_index_preserves_original_dates(): void
    {
        $this->signIn();
        $project = Project::factory()->trashed()->create([
            'name' => 'Project with dates',
            'start_date' => '2026-01-03',
            'end_date' => '2026-08-04',
        ]);

        $response = $this->get(route('projects.trash.index'));

        $response->assertOk();
        $response->assertSee('2026-01-03');
        $response->assertSee('2026-08-04');
    }

    public function test_restore_restores_trashed_project(): void
    {
        $this->signIn();
        $project = Project::factory()->trashed()->create(['name' => 'Deleted project']);

        $response = $this->patch(route('projects.trash.restore', $project->id));

        $response->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.restored'));
        $this->assertNotSoftDeleted($project);
    }

    public function test_restore_with_active_name_conflict_shows_error(): void
    {
        $this->signIn();
        $trashed = Project::factory()->trashed()->create(['name' => 'Same name']);
        Project::factory()->create(['name' => 'Same name']);

        $response = $this->patch(route('projects.trash.restore', $trashed->id));

        $response->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_restore_name_taken'));
        $this->assertSoftDeleted($trashed);
    }

    public function test_restore_with_archived_name_conflict_shows_error(): void
    {
        $this->signIn();
        $trashed = Project::factory()->trashed()->create(['name' => 'Same name']);
        Project::factory()->archived()->create(['name' => 'Same name']);

        $response = $this->patch(route('projects.trash.restore', $trashed->id));

        $response->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_restore_name_taken'));
        $this->assertSoftDeleted($trashed);
    }

    public function test_restore_with_confirmed_flag_restores_without_new_record(): void
    {
        $this->signIn();
        $trashed = Project::factory()->trashed()->create(['name' => 'Restored project']);
        // No active project with the same name - just testing the flag changes the message

        $response = $this->patch(route('projects.trash.restore', $trashed->id), [
            'resolve_name_conflict' => '1',
        ]);

        $response->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.restored_no_new_record'));
        $this->assertDatabaseCount('PRO_projects', 1);
        $this->assertNotSoftDeleted($trashed);
    }

    public function test_destroy_permanently_deletes_trashed_project(): void
    {
        $this->signIn();
        $project = Project::factory()->trashed()->create();

        $response = $this->delete(route('projects.trash.destroy', $project->id));

        $response->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertDatabaseMissing('PRO_projects', ['id' => $project->id]);
    }

    public function test_destroy_refuses_when_project_has_epics(): void
    {
        $this->signIn();
        $project = Project::factory()->trashed()->create();
        $epic = Epic::factory()->for($project)->create();

        $response = $this->delete(route('projects.trash.destroy', $project->id));

        $response->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_force_delete_related'));
        $this->assertModelExists($project);
        $this->assertModelExists($epic);
    }

    public function test_destroy_refuses_when_project_has_trashed_epics(): void
    {
        $this->signIn();
        $project = Project::factory()->trashed()->create();
        $epic = Epic::factory()->for($project)->trashed()->create();

        $response = $this->delete(route('projects.trash.destroy', $project->id));

        $response->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_force_delete_related'));
        $this->assertModelExists($project);
        $this->assertSoftDeleted($epic);
    }

    public function test_active_projects_not_found_in_trash_actions(): void
    {
        $this->signIn();
        $project = Project::factory()->create();

        $this->patch(route('projects.trash.restore', $project->id))->assertNotFound();
        $this->delete(route('projects.trash.destroy', $project->id))->assertNotFound();

        $this->assertModelExists($project);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('projects.trash.index'))->assertRedirect(route('login'));
        $this->patch(route('projects.trash.restore', 1))->assertRedirect(route('login'));
        $this->delete(route('projects.trash.destroy', 1))->assertRedirect(route('login'));
    }
}
