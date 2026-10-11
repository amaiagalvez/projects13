<?php

namespace Projects13\Tests\Feature;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_project(): void
    {
        $this->post(route('projects.store'), [])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('PRO_projects', 0);
    }

    public function test_creation_requires_authorization(): void
    {
        $this->signIn();
        Gate::before(static fn (): bool => false);

        $this->post(route('projects.store'), [])->assertForbidden();

        $this->assertDatabaseCount('PRO_projects', 0);
    }

    public function test_create_trims_name_and_persists_only_validated_fields(): void
    {
        $user = $this->signIn();

        $this->post(route('projects.store'), [
            'name' => '  Website renewal  ',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'notes' => 'Project notes',
            'active' => false,
        ])->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));

        $this->assertDatabaseHas('PRO_projects', [
            'name' => 'Website renewal',
            'notes' => 'Project notes',
            'active' => true,
            'created_by' => $user->id,
        ]);
    }

    public function test_live_duplicate_name_is_rejected(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Website renewal']);

        $this->post(route('projects.store'), [
            'name' => $project->name,
            'start_date' => '2026-01-01',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('PRO_projects', 1);
    }

    public function test_deleted_name_conflict_does_not_create_until_reuse_is_confirmed(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Website renewal']);
        $project->delete();
        $data = [
            'name' => $project->name,
            'start_date' => '2026-01-01',
        ];

        $this->post(route('projects.store'), $data)
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('deleted_project_conflict', ['id' => $project->id, 'name' => $project->name]);
        $this->assertDatabaseCount('PRO_projects', 1);

        $this->post(route('projects.store'), [...$data, 'reuse_deleted_name' => true])
            ->assertSessionHas('status', __('basics13::messages.created'));

        $this->assertDatabaseCount('PRO_projects', 2);
        $this->assertSoftDeleted($project);
    }

    public function test_deleted_name_json_conflict_returns_409(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $project->delete();
        $message = __('basics13::messages.deleted_name_conflict', ['name' => $project->name]);

        $this->postJson(route('projects.store'), [
            'name' => $project->name,
            'start_date' => '2026-01-01',
        ])->assertConflict()->assertExactJson([
            'message' => $message,
            'errors' => ['name' => [$message]],
        ]);

        $this->assertDatabaseCount('PRO_projects', 1);
    }

    public function test_delete_restore_and_permanent_delete(): void
    {
        $this->signIn();
        $project = Project::factory()->create();

        $this->delete(route('projects.destroy', $project))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));
        $this->assertSoftDeleted($project);

        $this->patch(route('projects.trash.restore', $project), ['resolve_name_conflict' => true])
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.restored_no_new_record'));
        $this->assertNotSoftDeleted($project);

        $this->delete(route('projects.destroy', $project));
        $this->delete(route('projects.trash.destroy', $project))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertModelMissing($project);
    }

    public function test_trashed_children_block_soft_and_permanent_deletion(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();
        $epic->delete();

        $this->delete(route('projects.destroy', $project))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));
        $this->assertNotSoftDeleted($project);

        Project::query()->whereKey($project->id)->update(['deleted_at' => now()]);
        $this->delete(route('projects.trash.destroy', $project))
            ->assertSessionHas('error', __('Cannot be permanently deleted while it has related records.'));
        $this->assertSoftDeleted($project);
    }

    public function test_restore_refuses_taken_name_and_missing_record(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Website renewal']);
        $project->delete();
        Project::factory()->create(['name' => $project->name]);

        $this->patch(route('projects.trash.restore', $project))
            ->assertSessionHas('error', __('basics13::messages.cannot_restore_name_taken'));
        $this->assertSoftDeleted($project);

        $this->patch(route('projects.trash.restore', 999999))->assertNotFound();
    }

    public function test_archive_and_activate_preserve_children(): void
    {
        $this->signIn();
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $this->patch(route('projects.archive', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.archived'));
        $this->assertFalse($project->fresh()->active);
        $this->assertTrue($epic->fresh()->active);

        $this->patch(route('projects.archived.activate', $project))
            ->assertRedirect(route('projects.archived.index'))
            ->assertSessionHas('status', __('basics13::messages.activated'));
        $this->assertTrue($project->fresh()->active);
    }
}
