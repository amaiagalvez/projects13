<?php

namespace Projects13\Policies;

use Projects13\Models\Epic;
use Projects13\Enums\ProjectRole;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable as User;

/**
 * Epic abilities, each one granted by its `epics.{action}` permission. Commenting is part of
 * working on the epic, so it asks for `epics.edit`. Without `projects.manage`, the user also has to
 * be a member of the epic's project: an owner or editor edits and comments, only an owner deletes
 * or archives, and a viewer only reads.
 */
class EpicPolicy
{
    use ChecksProjectMembership;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->permits($user, 'epics.view');
    }

    /**
     * Determine whether the user can read the comments of the model.
     */
    public function viewComments(User $user, Epic $epic): bool|Response
    {
        return $this->decideOn($user, $epic->project, 'epics.view', static fn (): bool => true);
    }

    /**
     * Determine whether the user can create models. Which projects the epic may go in is checked by
     * the request, among the ones the user edits.
     */
    public function create(User $user): bool
    {
        return $this->permits($user, 'epics.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Epic $epic): bool|Response
    {
        return $this->editorMay($user, $epic, 'epics.edit');
    }

    /**
     * Determine whether the user can comment on the model.
     */
    public function comment(User $user, Epic $epic): bool|Response
    {
        return $this->editorMay($user, $epic, 'epics.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Epic $epic): bool|Response
    {
        return $this->ownerMay($user, $epic, 'epics.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Epic $epic): bool|Response
    {
        return $this->ownerMay($user, $epic, 'epics.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Epic $epic): bool|Response
    {
        return $this->ownerMay($user, $epic, 'epics.force_delete');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Epic $epic): bool|Response
    {
        return $this->ownerMay($user, $epic, 'epics.archive');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Epic $epic): bool|Response
    {
        return $this->ownerMay($user, $epic, 'epics.archive');
    }

    private function editorMay(User $user, Epic $epic, string $permission): bool|Response
    {
        return $this->decideOn($user, $epic->project, $permission, static fn (ProjectRole $role): bool => $role->edits());
    }

    private function ownerMay(User $user, Epic $epic, string $permission): bool|Response
    {
        return $this->decideOn($user, $epic->project, $permission, static fn (ProjectRole $role): bool => $role === ProjectRole::Owner);
    }
}
