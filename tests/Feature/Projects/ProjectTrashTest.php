<?php

namespace Projects13\Tests\Feature\Projects;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectTrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_trash_preserves_index_columns_without_deletion_date(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-02 12:00:00');
        $project = Project::factory()->create([
            'name' => 'Project with dates',
            'start_date' => '2026-01-03',
            'end_date' => '2026-08-04',
        ]);

        $activeResponse = $this->get(route('projects.index'));
        $project->delete();

        $response = $this->get(route('projects.trash.index'));

        $response->assertSeeInOrder([
            '<thead',
            __('Name'),
            __('Start date'),
            __('End date'),
            __('Epics'),
            __('Comments'),
            __('Actions'),
        ], false)->assertSeeInOrder([
            'Project with dates',
            '2026-01-03',
            '2026-08-04',
        ]);
        $response->assertSee('data-test="project-comments-count-'.$project->id.'"></span>', false);
    }

    public function test_trash_orders_by_deletion_timestamp_descending_then_id_ascending(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-02 12:00:00');
        $olderProject = Project::factory()->trashed()->create(['name' => 'Older deleted project']);
        $newerProject = Project::factory()->trashed()->create(['name' => 'Newer deleted project']);
        $tiedProject = Project::factory()->trashed()->create(['name' => 'Tied deleted project']);
        $olderProject->forceFill(['deleted_at' => now()->subHour()])->saveQuietly();

        $response = $this->get(route('projects.trash.index'));

        $response->assertSeeInOrder([
            $newerProject->name,
            $tiedProject->name,
            $olderProject->name,
        ]);
    }

    public function test_trash_can_be_searched_by_project_name(): void
    {
        $this->actingAs(User::factory()->create());
        $matchingProject = Project::factory()->trashed()->create(['name' => 'Archived project match']);
        $otherProject = Project::factory()->trashed()->create(['name' => 'Unrelated archived project']);

        $this->get(route('projects.trash.index', ['search' => 'project match']))
            ->assertOk()
            ->assertSee($matchingProject->name)
            ->assertDontSee($otherProject->name);
    }

    public function test_deleted_projects_can_be_restored_or_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $activeProject = Project::factory()->create(['name' => 'Active project']);
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Deleted project']);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Active project')
            ->assertDontSee('Deleted project');

        $this->get(route('projects.trash.index'))
            ->assertOk()
            ->assertSee('Deleted project')
            ->assertDontSee('Active project');

        $this->patch(route('projects.trash.restore', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.restored'));
        $this->assertNotSoftDeleted($deletedProject);

        $this->delete(route('projects.destroy', $activeProject))->assertRedirect(route('projects.index'));
        $this->delete(route('projects.trash.destroy', $activeProject->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertDatabaseMissing('PRO_projects', ['id' => $activeProject->id]);
    }

    public function test_active_projects_are_not_found_through_trash_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->patch(route('projects.trash.restore', $project->id))->assertNotFound();
        $this->delete(route('projects.trash.destroy', $project->id))->assertNotFound();

        $this->assertModelExists($project);
    }

    public function test_permanently_deleting_a_project_with_epics_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->trashed()->create();
        $epic = Epic::factory()->for($project)->create();

        $this->get(route('projects.trash.index'))
            ->assertSee('data-test="project-force-delete-blocked-'.$project->id.'"', false)
            ->assertSee(__('basics13::messages.cannot_force_delete_related'));

        $this->delete(route('projects.trash.destroy', $project->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_force_delete_related'));

        $this->assertModelExists($project);
        $this->assertModelExists($epic);
    }

    public function test_project_can_be_permanently_deleted_without_epics(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->assertModelExists($project);
        $this->assertFalse($project->trashed());

        $this->delete(route('projects.destroy', $project->id))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($project);
    }

    public function test_project_cannot_be_restored_when_an_active_project_uses_its_name(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Repeated project']);

        Project::factory()->create(['name' => 'Repeated project']);

        $this->patch(route('projects.trash.restore', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas(
                'error',
                __('basics13::messages.cannot_restore_name_taken'),
            );

        $this->assertSoftDeleted($deletedProject);
    }

    public function test_project_cannot_be_restored_when_an_archived_project_uses_its_name(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Repeated project']);
        $archivedProject = Project::factory()->archived()->create(['name' => 'Repeated project']);

        $this->patch(route('projects.trash.restore', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas(
                'error',
                __('basics13::messages.cannot_restore_name_taken'),
            );

        $this->assertSoftDeleted($deletedProject);
        $this->assertModelExists($archivedProject);
        $this->assertFalse($archivedProject->active);
    }

    public function test_project_can_be_restored_when_its_name_is_used_only_in_another_project(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Repeated project']);
        Project::factory()->create(['name' => 'Repeated project']);

        $this->patch(route('projects.trash.restore', $deletedProject->id))
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas(
                'error',
                __('basics13::messages.cannot_restore_name_taken'),
            );

        $this->assertSoftDeleted($deletedProject);
    }

    public function test_confirmed_restore_does_not_create_an_additional_project(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Restored project']);

        $this->patch(route('projects.trash.restore', $deletedProject->id), [
            'resolve_name_conflict' => '1',
        ])
            ->assertRedirect(route('projects.trash.index'))
            ->assertSessionHas(
                'status',
                __('basics13::messages.restored_no_new_record'),
            );

        $this->assertDatabaseCount('PRO_projects', 1);
        $this->assertNotSoftDeleted($deletedProject);
    }
}
