<?php

namespace Projects13\Policies;

use Projects13\Models\Project;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Project abilities, each one granted by its `projects.{action}` permission. Planning and timeline
 * are read through viewAny.
 */
class ProjectPolicy
{
    use ChecksPermissions;

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
    public function update(User $user, Project $project): bool
    {
        return $this->permits($user, 'projects.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->permits($user, 'projects.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return $this->permits($user, 'projects.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return $this->permits($user, 'projects.force_delete');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Project $project): bool
    {
        return $this->permits($user, 'projects.archive');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Project $project): bool
    {
        return $this->permits($user, 'projects.archive');
    }
}
