<?php

namespace Projects13\Policies;

use Projects13\Models\Project;
use Projects13\Enums\ProjectRole;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Project abilities, each one granted by its `projects.{action}` permission. Planning and timeline
 * are read through viewAny. Without `projects.manage`, the user also has to be a member: an editor
 * edits, only an owner deletes, archives or manages the members.
 */
class ProjectPolicy
{
    use ChecksProjectMembership;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'projects.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->permits($user, 'projects.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool|Response
    {
        return $this->decideOn($user, $project, 'projects.edit', static fn (ProjectRole $role): bool => $role->edits());
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool|Response
    {
        return $this->ownerMay($user, $project, 'projects.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool|Response
    {
        return $this->ownerMay($user, $project, 'projects.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool|Response
    {
        return $this->ownerMay($user, $project, 'projects.force_delete');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Project $project): bool|Response
    {
        return $this->ownerMay($user, $project, 'projects.archive');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Project $project): bool|Response
    {
        return $this->ownerMay($user, $project, 'projects.archive');
    }

    /**
     * Determine whether the user can add, change or remove the members of the project. The owner
     * may do it without any further permission; so may whoever has `projects.manage`.
     */
    public function manageMembers(User $user, Project $project): bool|Response
    {
        if ($this->permits($user, 'projects.manage')) {
            return true;
        }

        $role = $this->roleIn($project, $user);

        return $role === null ? Response::denyAsNotFound() : $role === ProjectRole::Owner;
    }

    private function ownerMay(User $user, Project $project, string $permission): bool|Response
    {
        return $this->decideOn($user, $project, $permission, static fn (ProjectRole $role): bool => $role === ProjectRole::Owner);
    }
}
