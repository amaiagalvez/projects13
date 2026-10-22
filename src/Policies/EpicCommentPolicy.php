<?php

namespace Projects13\Policies;

use Projects13\Models\EpicComment;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Comments belong to their epic: reading them asks for `epics.view` and writing them for
 * `epics.edit`, the same permission EpicPolicy::comment() checks.
 */
class EpicCommentPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'epics.view');
    }

    public function create(User $user): bool
    {
        return $this->permits($user, 'epics.edit');
    }

    public function update(User $user, EpicComment $comment): bool
    {
        return $this->permits($user, 'epics.edit');
    }

    public function delete(User $user, EpicComment $comment): bool
    {
        return $this->permits($user, 'epics.edit');
    }

    public function forceDelete(User $user, EpicComment $comment): bool
    {
        return $this->permits($user, 'epics.edit');
    }
}
