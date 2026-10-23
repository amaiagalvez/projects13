<?php

namespace Projects13\Queries;

use Projects13\Enums\ProjectRole;
use Illuminate\Support\Facades\Auth;
use Projects13\Models\ProjectMember;
use Basics13\Concerns\ChecksPermissions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which projects the signed-in user reaches. `projects.manage` reaches all of them; anybody else
 * only the ones they are a member of. Every list, the planning, the timeline and the dashboard go
 * through here, so a project outside the user's memberships shows up nowhere. Without a signed-in
 * user (a console command, a seeder) nothing is narrowed.
 */
final class ProjectVisibility
{
    use ChecksPermissions;

    /**
     * Keep only the rows whose project the user is a member of.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function restrict(Builder $query, string $projectIdColumn): Builder
    {
        $memberships = $this->memberships();

        if ($memberships !== null) {
            $query->whereIn($projectIdColumn, $memberships->select('project_id'));
        }

        return $query;
    }

    /**
     * Keep only the rows whose project the user may put epics in: the ones they own or edit.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function restrictToEditable(Builder $query, string $projectIdColumn): Builder
    {
        $editable = $this->editableProjectIds();

        if ($editable !== null) {
            $query->whereIn($projectIdColumn, $editable);
        }

        return $query;
    }

    /**
     * The ids of the projects the user owns or edits, as a subquery, or null when every project
     * is open to them.
     *
     * @return Builder<ProjectMember>|null
     */
    public function editableProjectIds(): ?Builder
    {
        return $this->memberships()
            ?->whereIn('role', [ProjectRole::Owner->value, ProjectRole::Editor->value])
            ->select('project_id');
    }

    /**
     * The user's memberships, or null when nothing has to be narrowed.
     *
     * @return Builder<ProjectMember>|null
     */
    private function memberships(): ?Builder
    {
        $user = Auth::user();

        if ($user === null || $this->permits($user, 'projects.manage')) {
            return null;
        }

        return ProjectMember::query()->where('user_id', $user->getAuthIdentifier());
    }
}
