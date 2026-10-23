<?php

namespace Projects13\Tests\Feature\Validation;

use App\Models\User;
use Basics13\Models\Role;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Projects13\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Http\Requests\ProjectMemberRequest;

class ProjectMemberRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_authorize_returns_false_for_guest(): void
    {
        $this->getJson(route('projects.members.store', Project::factory()->create()))
            ->assertUnauthorized();
    }

    public function test_authorize_returns_true_for_user_with_manage_permission(): void
    {
        $user = $this->holding(['projects.manage']);
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => User::factory()->create()->id,
                'role' => 'editor',
            ])
            ->assertOk();
    }

    public function test_authorize_returns_true_for_project_owner(): void
    {
        $owner = $this->holding(['projects.view']);
        $project = Project::factory()->hasMember($owner, ProjectRole::Owner)->create();
        $newMember = User::factory()->create();

        $this->actingAs($owner)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => $newMember->id,
                'role' => 'editor',
            ])
            ->assertOk();
    }

    public function test_authorize_returns_false_for_editor(): void
    {
        $owner = $this->holding(['projects.view']);
        $editor = $this->holding(['projects.view']);
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($editor, ProjectRole::Editor)
            ->create();
        $newMember = User::factory()->create();

        $this->actingAs($editor)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => $newMember->id,
                'role' => 'viewer',
            ])
            ->assertForbidden();
    }

    public function test_authorize_returns_forbidden_for_viewer(): void
    {
        $owner = $this->holding(['projects.view']);
        $viewer = $this->holding(['projects.view']);
        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($viewer, ProjectRole::Viewer)
            ->create();
        $newMember = User::factory()->create();

        $this->actingAs($viewer)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => $newMember->id,
                'role' => 'viewer',
            ])
            ->assertForbidden();
    }

    public function test_validation_requires_user_id(): void
    {
        $user = $this->holding(['projects.manage']);
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->postJson(route('projects.members.store', $project), [
                'role' => 'editor',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_validation_requires_role(): void
    {
        $user = $this->holding(['projects.manage']);
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => User::factory()->create()->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_validation_user_id_must_exist(): void
    {
        $user = $this->holding(['projects.manage']);
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => 99999,
                'role' => 'editor',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_validation_role_must_be_valid_enum(): void
    {
        $user = $this->holding(['projects.manage']);
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => User::factory()->create()->id,
                'role' => 'invalid_role',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_validation_prevents_duplicate_member_on_store(): void
    {
        $user = $this->holding(['projects.manage']);
        $project = Project::factory()->create();
        $existingMember = ProjectMember::factory()->for($project)->create();

        $this->actingAs($user)
            ->postJson(route('projects.members.store', $project), [
                'user_id' => $existingMember->user_id,
                'role' => 'editor',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_validation_allows_update_for_existing_member(): void
    {
        $user = $this->holding(['projects.manage']);
        $project = Project::factory()->create();
        $member = ProjectMember::factory()->for($project)->create();

        $this->actingAs($user)
            ->patchJson(route('projects.members.update', [$project, $member]), [
                'user_id' => $member->user_id,
                'role' => 'editor',
            ])
            ->assertOk();
    }

    public function test_attributes_returns_translated_names(): void
    {
        $request = new ProjectMemberRequest;
        $attributes = $request->attributes();

        $this->assertSame(__('User'), $attributes['user_id']);
        $this->assertSame(__('Role'), $attributes['role']);
    }

    public function test_error_bag_is_member(): void
    {
        $request = new ProjectMemberRequest;
        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('errorBag');
        $property->setAccessible(true);
        $this->assertSame('member', $property->getValue($request));
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
