<?php

namespace Projects13\Tests\Feature\Members;

use App\Models\User;
use Basics13\Models\Role;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Projects13\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_index_returns_members_for_authorized_user(): void
    {
        $owner = $this->holding(['projects.view']);
        $editor = $this->holding(['projects.view']);
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($editor, ProjectRole::Editor)
            ->create();

        $this->actingAs($owner);

        $response = $this->getJson(route('projects.members.index', $project));

        $response->assertOk()
            ->assertJsonStructure(['members' => [
                ['id', 'user_id', 'name', 'email', 'role', 'role_label', 'added_by', 'created_at'],
            ]]);
        $this->assertCount(2, $response->json('members'));
    }

    public function test_index_returns_empty_for_project_with_no_members(): void
    {
        $owner = $this->holding(['projects.view']);
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();

        $this->actingAs($owner);

        $response = $this->getJson(route('projects.members.index', $project));

        $response->assertOk()
            ->assertJson(['members' => []]);
    }

    public function test_index_denies_access_to_editor(): void
    {
        $owner = $this->holding(['projects.view']);
        $editor = $this->holding(['projects.view']);
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($editor, ProjectRole::Editor)
            ->create();

        $this->actingAs($editor);

        $response = $this->getJson(route('projects.members.index', $project));

        $response->assertForbidden();
    }

    public function test_index_denies_access_to_viewer(): void
    {
        $owner = $this->holding(['projects.view']);
        $viewer = $this->holding(['projects.view']);
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($viewer, ProjectRole::Viewer)
            ->create();

        $this->actingAs($viewer);

        $response = $this->getJson(route('projects.members.index', $project));

        $response->assertForbidden();
    }

    public function test_index_allows_projects_manage(): void
    {
        $owner = $this->holding(['projects.view']);
        $manager = $this->holding(['projects.manage']);
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($manager, ProjectRole::Editor)
            ->create();

        $this->actingAs($manager);

        $response = $this->getJson(route('projects.members.index', $project));

        $response->assertOk();
    }

    public function test_index_returns_404_for_non_member(): void
    {
        $owner = $this->holding(['projects.view']);
        $outsider = $this->holding(['projects.view']);
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();

        $this->actingAs($outsider);

        $response = $this->getJson(route('projects.members.index', $project));

        $response->assertNotFound();
    }

    public function test_store_adds_member_for_owner(): void
    {
        $owner = $this->signIn(); // Creates admin user
        $newMember = User::factory()->create();
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();

        $response = $this->from(route('projects.index'))->post(route('projects.members.store', $project), [
            'user_id' => $newMember->id,
            'role' => 'editor',
        ]);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Member added successfully.'));

        $this->assertDatabaseHas('PRO_project_members', [
            'project_id' => $project->id,
            'user_id' => $newMember->id,
            'role' => 'editor',
        ]);
    }

    public function test_store_adds_member_via_json(): void
    {
        $owner = $this->holding(['projects.view']);
        $newMember = User::factory()->create();
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();

        $this->actingAs($owner);

        $response = $this->postJson(route('projects.members.store', $project), [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ]);

        $response->assertOk()->assertExactJson(['message' => __('Member added successfully.')]);

        $this->assertDatabaseHas('PRO_project_members', [
            'project_id' => $project->id,
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ]);
    }

    public function test_store_prevents_duplicate_member(): void
    {
        $owner = $this->holding(['projects.view']);
        $existingMember = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($existingMember, ProjectRole::Editor)
            ->create();

        $this->actingAs($owner);

        $response = $this->postJson(route('projects.members.store', $project), [
            'user_id' => $existingMember->id,
            'role' => 'viewer',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id' => __('This user is already a member of the project.')]);
    }

    public function test_store_prevents_adding_to_trashed_project(): void
    {
        $owner = $this->holding(['projects.view']);
        $newMember = User::factory()->create();
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->trashed()->create();

        $this->actingAs($owner);

        $response = $this->from(route('projects.index'))->post(route('projects.members.store', $project), [
            'user_id' => $newMember->id,
            'role' => 'editor',
        ]);

        $response->assertNotFound();
    }

    public function test_store_denies_access_to_editor(): void
    {
        $owner = $this->holding(['projects.view']);
        $editor = $this->holding(['projects.view']);
        $newMember = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($editor, ProjectRole::Editor)
            ->create();

        $this->actingAs($editor);

        $response = $this->post(route('projects.members.store', $project), [
            'user_id' => $newMember->id,
            'role' => 'viewer',
        ]);

        $response->assertForbidden();
    }

    public function test_update_changes_member_role(): void
    {
        $owner = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($member, ProjectRole::Editor)
            ->create();

        $projectMember = $project->members()->where('user_id', $member->id)->sole();

        $this->actingAs($owner);

        $response = $this->from(route('projects.index'))->patch(route('projects.members.update', [$project, $projectMember]), [
            'user_id' => $member->id,
            'role' => 'viewer',
        ]);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Member role updated successfully.'));

        $this->assertDatabaseHas('PRO_project_members', [
            'id' => $projectMember->id,
            'role' => 'viewer',
        ]);
    }

    public function test_update_prevents_removing_last_owner(): void
    {
        $owner = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($member, ProjectRole::Editor)
            ->create();

        $ownerMember = $project->members()->where('user_id', $owner->id)->sole();

        $this->actingAs($owner);

        $response = $this->from(route('projects.index'))->patch(route('projects.members.update', [$project, $ownerMember]), [
            'user_id' => $owner->id,
            'role' => 'editor',
        ]);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('A project must have at least one owner.'));

        $this->assertDatabaseHas('PRO_project_members', [
            'id' => $ownerMember->id,
            'role' => 'owner',
        ]);
    }

    public function test_update_allows_changing_owner_when_multiple_owners(): void
    {
        $owner1 = $this->holding(['projects.view']);
        $owner2 = $this->holding(['projects.view']);
        $project = Project::factory()
            ->hasMember($owner1, ProjectRole::Owner)
            ->hasMember($owner2, ProjectRole::Owner)
            ->create();

        $ownerMember1 = $project->members()->where('user_id', $owner1->id)->sole();

        $this->actingAs($owner1);

        $response = $this->from(route('projects.index'))->patch(route('projects.members.update', [$project, $ownerMember1]), [
            'user_id' => $owner1->id,
            'role' => 'editor',
        ]);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Member role updated successfully.'));

        $this->assertDatabaseHas('PRO_project_members', [
            'id' => $ownerMember1->id,
            'role' => 'editor',
        ]);
    }

    public function test_update_denies_access_to_editor(): void
    {
        $owner = $this->holding(['projects.view']);
        $editor = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($editor, ProjectRole::Editor)
            ->hasMember($member, ProjectRole::Viewer)
            ->create();

        $projectMember = $project->members()->where('user_id', $member->id)->sole();

        $this->actingAs($editor);

        $response = $this->patch(route('projects.members.update', [$project, $projectMember]), [
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        $response->assertForbidden();
    }

    public function test_update_returns_404_for_wrong_project(): void
    {
        $owner = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project1 = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();
        $project2 = Project::factory()->create();
        $projectMember = ProjectMember::factory()->for($project2)->state(['user_id' => $member->id])->create();

        $this->actingAs($owner);

        $response = $this->patch(route('projects.members.update', [$project1, $projectMember]), [
            'user_id' => $member->id,
            'role' => 'editor',
        ]);

        $response->assertNotFound();
    }

    public function test_destroy_removes_member(): void
    {
        $owner = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($member, ProjectRole::Editor)
            ->create();

        $projectMember = $project->members()->where('user_id', $member->id)->sole();

        $this->actingAs($owner);

        $response = $this->from(route('projects.index'))->delete(route('projects.members.destroy', [$project, $projectMember]));

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Member removed successfully.'));

        $this->assertDatabaseMissing('PRO_project_members', ['id' => $projectMember->id]);
    }

    public function test_destroy_prevents_removing_last_owner(): void
    {
        $owner = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($member, ProjectRole::Editor)
            ->create();

        $ownerMember = $project->members()->where('user_id', $owner->id)->sole();

        $this->actingAs($owner);

        $response = $this->from(route('projects.index'))->delete(route('projects.members.destroy', [$project, $ownerMember]));

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('error', __('A project must have at least one owner.'));

        $this->assertDatabaseHas('PRO_project_members', ['id' => $ownerMember->id]);
    }

    public function test_destroy_allows_removing_owner_when_multiple_owners(): void
    {
        $owner1 = $this->holding(['projects.view']);
        $owner2 = $this->holding(['projects.view']);
        $project = Project::factory()
            ->hasMember($owner1, ProjectRole::Owner)
            ->hasMember($owner2, ProjectRole::Owner)
            ->create();

        $ownerMember1 = $project->members()->where('user_id', $owner1->id)->sole();

        $this->actingAs($owner1);

        $response = $this->from(route('projects.index'))->delete(route('projects.members.destroy', [$project, $ownerMember1]));

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('Member removed successfully.'));

        $this->assertDatabaseMissing('PRO_project_members', ['id' => $ownerMember1->id]);
    }

    public function test_destroy_denies_access_to_editor(): void
    {
        $owner = $this->holding(['projects.view']);
        $editor = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($editor, ProjectRole::Editor)
            ->hasMember($member, ProjectRole::Viewer)
            ->create();

        $projectMember = $project->members()->where('user_id', $member->id)->sole();

        $this->actingAs($editor);

        $response = $this->delete(route('projects.members.destroy', [$project, $projectMember]));

        $response->assertForbidden();
    }

    public function test_destroy_returns_404_for_wrong_project(): void
    {
        $owner = $this->holding(['projects.view']);
        $member = User::factory()->create();
        $project1 = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();
        $project2 = Project::factory()->create();
        $projectMember = ProjectMember::factory()->for($project2)->state(['user_id' => $member->id])->create();

        $this->actingAs($owner);

        $response = $this->delete(route('projects.members.destroy', [$project1, $projectMember]));

        $response->assertNotFound();
    }

    public function test_guests_are_redirected(): void
    {
        $project = Project::factory()->create();

        $this->getJson(route('projects.members.index', $project))->assertUnauthorized();
        $this->postJson(route('projects.members.store', $project))->assertUnauthorized();
        $this->patchJson(route('projects.members.update', [$project, 1]))->assertUnauthorized();
        $this->deleteJson(route('projects.members.destroy', [$project, 1]))->assertUnauthorized();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function holding(array $permissions): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::factory()->withPermissions($permissions)->create());

        return $user;
    }
}
