<?php

namespace Projects13\Tests\Feature;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_forbidden_user_cannot_create(): void
    {
        $this->post(route('epics.store'), [])->assertRedirect(route('login'));
        $this->signIn();
        Gate::before(static fn (): bool => false);

        $this->post(route('epics.store'), [])->assertForbidden();
        $this->assertDatabaseCount('PRO_epics', 0);
    }

    public function test_create_and_update_trim_names_and_allow_missing_dates(): void
    {
        $user = $this->signIn();
        $project = Project::factory()->create();

        $this->post(route('epics.store'), [
            'name' => '  Initial delivery  ',
            'project_id' => $project->id,
            'active' => false,
        ])->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));
        $epic = Epic::query()->sole();
        $this->assertSame('Initial delivery', $epic->name);
        $this->assertNull($epic->start_date);
        $this->assertNull($epic->end_date);
        $this->assertTrue($epic->active);
        $this->assertSame($user->id, $epic->created_by);

        $project->forceFill(['active' => false])->save();
        $this->put(route('epics.update', $epic), [
            'name' => $epic->name,
            'project_id' => $project->id,
            'notes' => 'Revised scope',
        ])->assertSessionHas('status', __('basics13::messages.updated'));
        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id, 'notes' => 'Revised scope']);
    }

    public function test_dates_must_be_inside_project_and_end_after_start(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $this->post(route('epics.store'), [
            'name' => 'Initial delivery',
            'project_id' => $project->id,
            'start_date' => '2025-12-31',
            'end_date' => '2027-01-01',
        ])->assertSessionHasErrors(['start_date', 'end_date']);
        $this->post(route('epics.store'), [
            'name' => 'Initial delivery',
            'project_id' => $project->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
        ])->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('PRO_epics', 0);
    }

    public function test_name_uniqueness_is_scoped_to_project(): void
    {
        $this->signIn();
        $epic = Epic::factory()->withoutDates()->create(['name' => 'Initial delivery']);
        $other = Project::factory()->create();

        $this->post(route('epics.store'), ['name' => $epic->name, 'project_id' => $epic->project_id])
            ->assertSessionHasErrors('name');
        $this->post(route('epics.store'), ['name' => $epic->name, 'project_id' => $other->id])
            ->assertSessionHas('status', __('basics13::messages.created'));
        $this->assertDatabaseCount('PRO_epics', 2);
    }

    public function test_archived_project_cannot_be_selected_for_new_epic(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['active' => false]);

        $this->post(route('epics.store'), ['name' => 'Initial delivery', 'project_id' => $project->id])
            ->assertSessionHasErrors('project_id');
        $this->assertDatabaseCount('PRO_epics', 0);
    }

    public function test_deleted_name_conflict_and_explicit_reuse(): void
    {
        $this->signIn();
        $epic = Epic::factory()->withoutDates()->create(['name' => 'Initial delivery']);
        $epic->delete();
        $data = ['name' => $epic->name, 'project_id' => $epic->project_id];

        $this->post(route('epics.store'), $data)
            ->assertSessionHas('deleted_epic_conflict', ['id' => $epic->id, 'name' => $epic->name]);
        $this->assertDatabaseCount('PRO_epics', 1);
        $this->post(route('epics.store'), [...$data, 'reuse_deleted_name' => true])
            ->assertSessionHas('status', __('basics13::messages.created'));
        $this->assertDatabaseCount('PRO_epics', 2);
        $this->assertSoftDeleted($epic);
    }

    public function test_delete_restore_archive_activate_and_force_delete(): void
    {
        $this->signIn();
        $epic = Epic::factory()->create();

        $this->patch(route('epics.archive', $epic))
            ->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('basics13::messages.archived'));
        $this->assertFalse($epic->fresh()->active);
        $this->patch(route('epics.archived.activate', $epic))
            ->assertRedirect(route('epics.archived.index'));
        $this->assertTrue($epic->fresh()->active);

        $this->delete(route('epics.destroy', $epic))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));
        $this->assertSoftDeleted($epic);
        $this->patch(route('epics.trash.restore', $epic))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.restored'));
        $this->assertNotSoftDeleted($epic);
        $this->delete(route('epics.destroy', $epic));
        $this->delete(route('epics.trash.destroy', $epic))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertModelMissing($epic);
    }

    public function test_comments_block_soft_and_permanent_deletion(): void
    {
        $user = $this->signIn();
        $epic = Epic::factory()->create();
        EpicComment::factory()->for($epic)->for($user)->create();

        $this->delete(route('epics.destroy', $epic))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));
        $this->assertNotSoftDeleted($epic);
        Epic::query()->whereKey($epic->id)->update(['deleted_at' => now()]);

        $this->delete(route('epics.trash.destroy', $epic))
            ->assertSessionHas('error', __('Cannot be permanently deleted while it has related records.'));
        $this->assertSoftDeleted($epic);
    }

    public function test_restore_conflict_is_scoped_to_project(): void
    {
        $this->signIn();
        $epic = Epic::factory()->withoutDates()->create(['name' => 'Initial delivery']);
        $epic->delete();
        Epic::factory()->withoutDates()->create(['name' => $epic->name, 'project_id' => $epic->project_id]);

        $this->patch(route('epics.trash.restore', $epic))
            ->assertSessionHas('error', __('basics13::messages.cannot_restore_name_taken'));
        $this->assertSoftDeleted($epic);
        $this->delete(route('epics.trash.destroy', $epic));

        $otherProject = Project::factory()->create();
        $other = Epic::factory()->for($otherProject)->withoutDates()->create(['name' => 'Initial delivery']);
        $other->delete();
        $this->patch(route('epics.trash.restore', $other))
            ->assertSessionHas('status', __('basics13::messages.restored'));
        $this->assertNotSoftDeleted($other);
        $this->delete(route('epics.trash.destroy', 999999))->assertNotFound();
    }
}
