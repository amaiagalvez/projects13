<?php

namespace Projects13\Policies;

use Closure;
use Projects13\Models\Project;
use Projects13\Enums\ProjectRole;
use Illuminate\Auth\Access\Response;
use Projects13\Models\ProjectMember;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Membership rules shared by the project and epic policies. `projects.manage` reaches every
 * project. Anybody else acts only on the projects they are a member of: on any other one the
 * answer is "not found", so a URL does not reveal that the project exists; on their own ones the
 * permission and their role there both have to allow the action.
 */
trait ChecksProjectMembership
{
    use ChecksPermissions;

    /**
     * @param  Closure(ProjectRole): bool  $roleAllows
     */
    private function decideOn(User $user, Project $project, string $permission, Closure $roleAllows): bool|Response
    {
        // projects.manage grants access to everything project-related (including epics)
        if ($this->permits($user, 'projects.manage')) {
            return true;
        }

        // Module-specific manage permission (e.g., epics.manage) grants access within the module
        $module = explode('.', $permission, 2)[0];
        if ($this->permits($user, $module.'.manage')) {
            return true;
        }

        $role = $this->roleIn($project, $user);

        if ($role === null) {
            return Response::denyAsNotFound();
        }

        return $this->permits($user, $permission) && $roleAllows($role);
    }

    /**
     * The user's role on the project, or null when they are not a member. A loaded `members`
     * relation is used as it is, so a list that eager loads it asks nothing more.
     */
    private function roleIn(Project $project, User $user): ?ProjectRole
    {
        $userId = (int) $user->getAuthIdentifier();

        $member = $project->relationLoaded('members')
            ? $project->members->first(static fn (ProjectMember $member): bool => $member->user_id === $userId)
            : $project->members()->where('user_id', $userId)->first();

        return $member?->role;
    }
}
