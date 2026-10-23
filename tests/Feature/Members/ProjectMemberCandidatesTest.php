<?php

namespace Projects13\Tests\Feature\Members;

use App\Models\User;
use Basics13\Models\Role;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectMemberCandidatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_without_users_view_finds_the_accounts_to_add(): void
    {
        $owner = $this->holding(['projects.view'], ['name' => 'Ane owner']);
        $member = User::factory()->create(['name' => 'Ane member']);
        $candidate = User::factory()->create(['name' => 'Ane candidate']);
        User::factory()->create(['name' => 'Ane archived', 'active' => false]);
        User::factory()->create(['name' => 'Ane trashed'])->delete();
        $root = User::factory()->create(['name' => 'Ane root']);
        $root->forceFill(['is_super_admin' => true])->save();
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->hasMember($member)->create();

        $this->actingAs($owner)
            ->getJson(route('projects.members.candidates', ['project' => $project, 'q' => 'Ane']))
            ->assertOk()
            ->assertExactJson(['results' => [['id' => $candidate->id, 'text' => 'Ane candidate']]]);
    }

    public function test_an_editor_cannot_search_and_a_stranger_gets_not_found(): void
    {
        $editor = $this->holding(['projects.view']);
        $project = Project::factory()->hasMember($editor, ProjectRole::Editor)->create();

        $this->actingAs($editor)->getJson(route('projects.members.candidates', $project))->assertForbidden();
        $this->actingAs($this->holding(['projects.view']))->getJson(route('projects.members.candidates', $project))->assertNotFound();
    }

    public function test_an_archived_account_or_the_super_admin_cannot_be_added(): void
    {
        $owner = $this->holding(['projects.view']);
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();
        $archived = User::factory()->create(['active' => false]);
        $root = User::factory()->create();
        $root->forceFill(['is_super_admin' => true])->save();

        foreach ([$archived, $root] as $user) {
            $this->actingAs($owner)
                ->postJson(route('projects.members.store', $project), ['user_id' => $user->id, 'role' => 'viewer'])
                ->assertJsonValidationErrors('user_id');
        }

        $this->assertSame(1, $project->members()->count());
    }

    public function test_the_drawer_gets_json_outcomes_and_keeps_an_owner(): void
    {
        $owner = $this->holding(['projects.view']);
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();
        $ownership = $project->members()->sole();
        $this->actingAs($owner);

        $this->patchJson(route('projects.members.update', [$project, $ownership]), ['role' => 'viewer'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => __('A project must have at least one owner.')]);
        $this->deleteJson(route('projects.members.destroy', [$project, $ownership]))
            ->assertUnprocessable();

        $this->assertSame(ProjectRole::Owner, $ownership->refresh()->role);
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    private function holding(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::factory()->withPermissions($permissions)->create());

        return $user;
    }
}
