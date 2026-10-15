<?php

namespace Projects13\Policies;

use Projects13\Models\EpicComment;
use Illuminate\Contracts\Auth\Authenticatable as User;

class EpicCommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EpicComment $comment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EpicComment $comment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EpicComment $comment): bool
    {
        return true;
    }
}
