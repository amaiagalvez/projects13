<?php

namespace Projects13\Tests\Feature\Epics;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicTrashTest extends TestCase
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
        $epic = Epic::factory()->create([
            'name' => 'Epic with dates',
            'start_date' => '2026-01-03',
            'end_date' => '2026-08-04',
        ]);
        $project = $epic->project;

        $activeResponse = $this->get(route('epics.index'));
        $epic->delete();

        $response = $this->get(route('epics.trash.index'));

        $response->assertSeeInOrder([
            '<thead',
            __('Name'),
            __('Project'),
            __('Start date'),
            __('End date'),
            __('Comments'),
            __('Actions'),
        ], false)->assertSeeInOrder([
            'Epic with dates',
            $project->name,
            '2026-01-03',
            '2026-08-04',
        ]);
        $response->assertSee('data-test="epic-comments-count-'.$epic->id.'"></span>', false);
    }

    public function test_trash_orders_by_deletion_timestamp_descending_then_id_ascending(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-10-02 12:00:00');
        $olderEpic = Epic::factory()->trashed()->create(['name' => 'Older deleted epic']);
        $newerEpic = Epic::factory()->trashed()->create(['name' => 'Newer deleted epic']);
        $tiedEpic = Epic::factory()->trashed()->create(['name' => 'Tied deleted epic']);
        $olderEpic->forceFill(['deleted_at' => now()->subHour()])->saveQuietly();

        $response = $this->get(route('epics.trash.index'));

        $response->assertSeeInOrder([
            $newerEpic->name,
            $tiedEpic->name,
            $olderEpic->name,
        ]);
    }

    public function test_trash_can_be_searched_by_epic_name(): void
    {
        $this->actingAs(User::factory()->create());
        $matchingEpic = Epic::factory()->trashed()->create(['name' => 'Archived epic match']);
        $otherEpic = Epic::factory()->trashed()->create(['name' => 'Unrelated archived epic']);

        $this->get(route('epics.trash.index', ['search' => 'epic match']))
            ->assertOk()
            ->assertSee($matchingEpic->name)
            ->assertDontSee($otherEpic->name);
    }

    /**
     * The trash query searches the project name too, so a trashed epic is reachable from its parent.
     */
    public function test_trash_can_be_searched_by_project_name(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create(['name' => 'Archived epic project']);
        $matchingEpic = Epic::factory()->for($project)->trashed()->create(['name' => 'First archived epic']);
        $otherProject = Project::factory()->create(['name' => 'Other project']);
        $otherEpic = Epic::factory()->for($otherProject)->trashed()->create(['name' => 'Second archived epic']);

        $this->get(route('epics.trash.index', ['search' => 'Archived epic project']))
            ->assertOk()
            ->assertSee($matchingEpic->name)
            ->assertDontSee($otherEpic->name);
    }

    public function test_deleted_epics_can_be_restored_or_permanently_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $activeEpic = Epic::factory()->create(['name' => 'Active epic']);
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Deleted epic']);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('Active epic')
            ->assertDontSee('Deleted epic');

        $this->get(route('epics.trash.index'))
            ->assertOk()
            ->assertSee('Deleted epic')
            ->assertDontSee('Active epic');

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.restored'));
        $this->assertNotSoftDeleted($deletedEpic);

        $this->delete(route('epics.destroy', $activeEpic))->assertRedirect(route('epics.index'));
        $this->delete(route('epics.trash.destroy', $activeEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas('status', __('basics13::messages.permanently_deleted'));
        $this->assertDatabaseMissing('PRO_epics', ['id' => $activeEpic->id]);
    }

    public function test_active_epics_are_not_found_through_trash_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();

        $this->patch(route('epics.trash.restore', $epic->id))->assertNotFound();
        $this->delete(route('epics.trash.destroy', $epic->id))->assertNotFound();

        $this->assertModelExists($epic);
    }

    public function test_permanently_deleting_an_epic_with_comments_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->trashed()->create();
        $comment = EpicComment::factory()->for($epic)->create();

        $this->get(route('epics.trash.index'))
            ->assertSee('data-test="epic-force-delete-blocked-'.$epic->id.'"', false)
            ->assertSee(__('basics13::messages.cannot_force_delete_related'));

        $this->delete(route('epics.trash.destroy', $epic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas('error', __('basics13::messages.cannot_force_delete_related'));

        $this->assertModelExists($epic);
        $this->assertModelExists($comment);
    }

    public function test_epic_cannot_be_restored_when_an_active_epic_in_the_same_project_uses_its_name(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Repeated epic']);
        $deletedProject = $deletedEpic->project;

        Epic::factory()->for($deletedProject)->create(['name' => 'Repeated epic']);

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas(
                'error',
                __('basics13::messages.cannot_restore_name_taken'),
            );

        $this->assertSoftDeleted($deletedEpic);
    }

    public function test_epic_cannot_be_restored_when_an_archived_epic_in_the_same_project_uses_its_name(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $deletedEpic = Epic::factory()->for($project)->trashed()->create(['name' => 'Repeated epic']);
        $archivedEpic = Epic::factory()->for($project)->archived()->create(['name' => 'Repeated epic']);

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas(
                'error',
                __('basics13::messages.cannot_restore_name_taken'),
            );

        $this->assertSoftDeleted($deletedEpic);
        $this->assertModelExists($archivedEpic);
        $this->assertFalse($archivedEpic->active);
    }

    public function test_epic_can_be_restored_when_its_name_is_used_only_in_another_project(): void
    {
        $this->actingAs(User::factory()->create());
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Repeated epic', 'project_id' => $projectA->id]);
        Epic::factory()->create(['name' => 'Repeated epic', 'project_id' => $projectB->id]);

        $this->patch(route('epics.trash.restore', $deletedEpic->id))
            ->assertSessionHas('status', __('basics13::messages.restored'));

        $this->assertNotSoftDeleted($deletedEpic);
    }

    public function test_confirmed_restore_does_not_create_an_additional_epic(): void
    {
        $this->actingAs(User::factory()->create());
        $deletedEpic = Epic::factory()->trashed()->create(['name' => 'Restored epic']);

        $this->patch(route('epics.trash.restore', $deletedEpic->id), [
            'resolve_name_conflict' => '1',
        ])
            ->assertRedirect(route('epics.trash.index'))
            ->assertSessionHas(
                'status',
                __('basics13::messages.restored_no_new_record'),
            );

        $this->assertDatabaseCount('PRO_epics', 1);
        $this->assertNotSoftDeleted($deletedEpic);
    }
}
