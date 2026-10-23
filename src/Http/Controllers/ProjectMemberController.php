<?php

namespace Projects13\Http\Controllers;

use Projects13\Models\Project;
use Illuminate\Http\JsonResponse;
use Projects13\Enums\ProjectRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Projects13\Models\ProjectMember;
use Illuminate\Http\RedirectResponse;
use Basics13\Http\Controllers\Controller;
use Projects13\Http\Requests\ProjectMemberRequest;
use Projects13\Http\Requests\ProjectMemberListRequest;

class ProjectMemberController extends Controller
{
    public function index(ProjectMemberListRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        /** @var Collection<int, ProjectMember> $members */
        $members = $project->members()
            ->with('user:id,name,email')
            ->latest('id')
            ->get();

        /** @var Collection<int, array<string, mixed>> $data */
        $data = $members->map(function (ProjectMember $member): array {
            $user = $member->user;

            return [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $user !== null ? $user->getAttribute('name') : __('Deleted user'),
                'email' => $user !== null ? $user->getAttribute('email') : '',
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'added_by' => $member->added_by,
                'created_at' => $member->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json(['members' => $data]);
    }

    public function store(ProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $added = DB::transaction(static function () use ($request, $project): bool {
            $lockedProject = Project::withTrashed()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProject->trashed()) {
                return false;
            }

            // Check if user is already a member
            if ($lockedProject->members()->where('user_id', $request->integer('user_id'))->exists()) {
                return false;
            }

            $member = $lockedProject->members()->make([
                'user_id' => $request->integer('user_id'),
                'role' => $request->enum('role', ProjectRole::class),
                'added_by' => $request->user()->getAuthIdentifier(),
            ]);
            $member->save();

            return true;
        });

        if (! $added) {
            return back(fallback: route('projects.index'))
                ->with('error', __('Cannot add member to a deleted project or user is already a member.'));
        }

        return back(fallback: route('projects.index'))
            ->with('status', __('Member added successfully.'));
    }

    public function update(ProjectMemberRequest $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        // Ensure the member belongs to this project
        if ($member->project_id !== $project->getKey()) {
            abort(404);
        }

        // Prevent changing the owner's role if they're the only owner
        if ($member->role === ProjectRole::Owner && $request->enum('role', ProjectRole::class) !== ProjectRole::Owner) {
            $ownerCount = $project->members()->where('role', ProjectRole::Owner)->count();
            if ($ownerCount <= 1) {
                return back(fallback: route('projects.index'))
                    ->with('error', __('A project must have at least one owner.'));
            }
        }

        $member->role = $request->enum('role', ProjectRole::class);
        $member->save();

        return back(fallback: route('projects.index'))
            ->with('status', __('Member role updated successfully.'));
    }

    public function destroy(Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        // Ensure the member belongs to this project
        if ($member->project_id !== $project->getKey()) {
            abort(404);
        }

        // Prevent removing the last owner
        if ($member->role === ProjectRole::Owner) {
            $ownerCount = $project->members()->where('role', ProjectRole::Owner)->count();
            if ($ownerCount <= 1) {
                return back(fallback: route('projects.index'))
                    ->with('error', __('A project must have at least one owner.'));
            }
        }

        $member->delete();

        return back(fallback: route('projects.index'))
            ->with('status', __('Member removed successfully.'));
    }
}
