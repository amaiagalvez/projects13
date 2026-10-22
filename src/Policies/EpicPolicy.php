<?php

namespace Projects13\Policies;

use Projects13\Models\Epic;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Epic abilities, each one granted by its `epics.{action}` permission. Commenting is part of
 * working on the epic, so it asks for `epics.edit`.
 */
class EpicPolicy
{
    use ChecksPermissions;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'epics.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->permits($user, 'epics.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Epic $epic): bool
    {
        return $this->permits($user, 'epics.edit');
    }

    /**
     * Determine whether the user can comment on the model.
     */
    public function comment(User $user, Epic $epic): bool
    {
        return $this->permits($user, 'epics.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Epic $epic): bool
    {
        return $this->permits($user, 'epics.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Epic $epic): bool
    {
        return $this->permits($user, 'epics.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Epic $epic): bool
    {
        return $this->permits($user, 'epics.force_delete');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Epic $epic): bool
    {
        return $this->permits($user, 'epics.archive');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Epic $epic): bool
    {
        return $this->permits($user, 'epics.archive');
    }
}
