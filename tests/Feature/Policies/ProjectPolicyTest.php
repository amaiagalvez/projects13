<?php

namespace Projects13\Tests\Feature\Policies;

use App\Models\User;
use Basics13\Models\Role;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Illuminate\Auth\Access\Response;
use Projects13\Policies\ProjectPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, ProjectRole}>
     */
    public static function recordAbilities(): array
    {
        return [
            'update' => ['update', 'projects.edit', ProjectRole::Editor],
            'delete' => ['delete', 'projects.delete', ProjectRole::Owner],
            'restore' => ['restore', 'projects.restore', ProjectRole::Owner],
            'forceDelete' => ['forceDelete', 'projects.force_delete', ProjectRole::Owner],
            'archive' => ['archive', 'projects.archive', ProjectRole::Owner],
            'activate' => ['activate', 'projects.archive', ProjectRole::Owner],
        ];
    }

    public function test_listing_and_creating_need_their_permission(): void
    {
        $policy = new ProjectPolicy;

        $this->assertTrue($policy->viewAny($this->holding(['projects.view'])));
        $this->assertFalse($policy->viewAny($this->holding([])));
        $this->assertTrue($policy->create($this->holding(['projects.create'])));
        $this->assertFalse($policy->create($this->holding(['projects.view'])));
    }

    #[DataProvider('recordAbilities')]
    public function test_each_ability_on_a_record_needs_its_permission(string $ability, string $permission, ProjectRole $requiredRole): void
    {
        $policy = new ProjectPolicy;

        $user = $this->holding([$permission]);
        $userWithoutPermission = $this->holding(['projects.view']);
        $manager = $this->holding(['projects.manage']);

        // Create project with all three users as members with the required role
        $project = Project::factory()
            ->hasMember($user, $requiredRole)
            ->hasMember($userWithoutPermission, $requiredRole)
            ->hasMember($manager, $requiredRole)
            ->create();

        // User with permission should be allowed
        $this->assertTrue($policy->{$ability}($user, $project));
        // User without permission but still a member should be denied (false, not 404)
        $this->assertFalse($policy->{$ability}($userWithoutPermission, $project));
        // projects.manage should grant access regardless of role
        $this->assertTrue($policy->{$ability}($manager, $project));
    }

    public function test_manage_members_only_owner_or_projects_manage(): void
    {
        $policy = new ProjectPolicy;

        $owner = $this->holding(['projects.view']);
        $editor = $this->holding(['projects.view']);
        $viewer = $this->holding(['projects.view']);
        $outsider = $this->holding(['projects.view']);
        $manager = $this->holding(['projects.manage']);

        $project = Project::factory()
            ->hasMember($owner, ProjectRole::Owner)
            ->hasMember($editor, ProjectRole::Editor)
            ->hasMember($viewer, ProjectRole::Viewer)
            ->hasMember($manager, ProjectRole::Editor)
            ->create();

        // Owner can manage members
        $this->assertTrue($policy->manageMembers($owner, $project));
        // Editor cannot manage members
        $this->assertFalse($policy->manageMembers($editor, $project));
        // Viewer cannot manage members
        $this->assertFalse($policy->manageMembers($viewer, $project));
        // Non-member gets not found
        $response = $policy->manageMembers($outsider, $project);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->asNotFound()->allowed() === false);
        // projects.manage can manage members
        $this->assertTrue($policy->manageMembers($manager, $project));
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
