<?php

namespace Projects13\Tests\Unit\Policies;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Projects13\Policies\EpicPolicy;
use Illuminate\Auth\Access\Response;
use Projects13\Models\ProjectMember;
use Basics13\Support\Testing\PermissionsActor;
use PHPUnit\Framework\Attributes\DataProvider;

class EpicPolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, string, ProjectRole}>
     */
    public static function recordAbilities(): array
    {
        return [
            'update' => ['update', 'epics.edit', ProjectRole::Editor],
            'comment' => ['comment', 'epics.edit', ProjectRole::Editor],
            'delete' => ['delete', 'epics.delete', ProjectRole::Owner],
            'restore' => ['restore', 'epics.restore', ProjectRole::Owner],
            'forceDelete' => ['forceDelete', 'epics.force_delete', ProjectRole::Owner],
            'archive' => ['archive', 'epics.archive', ProjectRole::Owner],
            'activate' => ['activate', 'epics.archive', ProjectRole::Owner],
        ];
    }

    public function test_listing_and_creating_need_their_permission(): void
    {
        $policy = new EpicPolicy;

        $this->assertTrue($policy->viewAny(PermissionsActor::holding(['epics.view'])));
        $this->assertFalse($policy->viewAny(PermissionsActor::holding([])));
        $this->assertTrue($policy->create(PermissionsActor::holding(['epics.create'])));
        $this->assertFalse($policy->create(PermissionsActor::holding(['epics.view'])));
    }

    #[DataProvider('recordAbilities')]
    public function test_each_ability_on_a_record_needs_its_permission(string $ability, string $permission, ProjectRole $requiredRole): void
    {
        $policy = new EpicPolicy;

        // Create an epic with a project that has the user as a member with the required role
        $user = PermissionsActor::holding([$permission]);
        $member = new ProjectMember(['user_id' => $user->getAuthIdentifier(), 'role' => $requiredRole]);
        $project = new Project(['id' => 1]);
        $project->setRelation('members', collect([$member]));
        $epic = new Epic(['project_id' => 1]);
        $epic->setRelation('project', $project);

        $this->assertTrue($policy->{$ability}($user, $epic));

        // User without permission but still a member should be denied
        $userWithoutPermission = PermissionsActor::holding(['epics.view']);
        $memberWithoutPermission = new ProjectMember(['user_id' => $userWithoutPermission->getAuthIdentifier(), 'role' => $requiredRole]);
        $projectWithoutPermission = new Project(['id' => 1]);
        $projectWithoutPermission->setRelation('members', collect([$memberWithoutPermission]));
        $epicWithoutPermission = new Epic(['project_id' => 1]);
        $epicWithoutPermission->setRelation('project', $projectWithoutPermission);

        $this->assertFalse($policy->{$ability}($userWithoutPermission, $epicWithoutPermission));

        // projects.manage should grant access regardless of role
        $manager = PermissionsActor::holding(['projects.manage']);
        $memberManager = new ProjectMember(['user_id' => $manager->getAuthIdentifier(), 'role' => ProjectRole::Viewer]);
        $projectManager = new Project(['id' => 1]);
        $projectManager->setRelation('members', collect([$memberManager]));
        $epicManager = new Epic(['project_id' => 1]);
        $epicManager->setRelation('project', $projectManager);

        $this->assertTrue($policy->{$ability}($manager, $epicManager));

        // epics.manage should also grant access
        $epicManager = PermissionsActor::holding(['epics.manage']);
        $memberEpicManager = new ProjectMember(['user_id' => $epicManager->getAuthIdentifier(), 'role' => ProjectRole::Viewer]);
        $projectEpicManager = new Project(['id' => 1]);
        $projectEpicManager->setRelation('members', collect([$memberEpicManager]));
        $epicEpicManager = new Epic(['project_id' => 1]);
        $epicEpicManager->setRelation('project', $projectEpicManager);

        $this->assertTrue($policy->{$ability}($epicManager, $epicEpicManager));
    }

    public function test_non_member_gets_not_found(): void
    {
        $policy = new EpicPolicy;

        // Epic with project but user is not a member
        $user = PermissionsActor::holding(['epics.edit']);
        $project = new Project(['id' => 1]);
        $project->setRelation('members', collect());
        $epic = new Epic(['project_id' => 1]);
        $epic->setRelation('project', $project);

        $response = $policy->update($user, $epic);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->asNotFound()->allowed() === false);
    }
}
