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
use Illuminate\Foundation\Http\FormRequest;
use Projects13\Http\Requests\ProjectMemberRequest;
use Projects13\Queries\Members\MemberCandidateQuery;
use Projects13\Http\Requests\ProjectMemberListRequest;
use Projects13\Http\Requests\ProjectMemberDestroyRequest;
use Projects13\Http\Requests\ProjectMemberCandidatesRequest;

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

    public function candidates(
        ProjectMemberCandidatesRequest $request,
        Project $project,
        MemberCandidateQuery $query,
    ): JsonResponse {
        return response()->json(['results' => $query->search($project, $request->search())]);
    }

    public function store(ProjectMemberRequest $request, Project $project): RedirectResponse|JsonResponse
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
            return $this->outcome($request, false, __('Cannot add member to a deleted project or user is already a member.'));
        }

        return $this->outcome($request, true, __('Member added successfully.'));
    }

    public function update(ProjectMemberRequest $request, Project $project, ProjectMember $member): RedirectResponse|JsonResponse
    {
        $this->authorize('manageMembers', $project);
        abort_unless($member->project_id === $project->getKey(), 404);

        $role = $request->enum('role', ProjectRole::class);
        $updated = DB::transaction(fn (): bool => $this->keepsAnOwner($project, $member, $role) && $member->update(['role' => $role]));

        if (! $updated) {
            return $this->outcome($request, false, __('A project must have at least one owner.'));
        }

        return $this->outcome($request, true, __('Member role updated successfully.'));
    }

    public function destroy(ProjectMemberDestroyRequest $request, Project $project, ProjectMember $member): RedirectResponse|JsonResponse
    {
        $this->authorize('manageMembers', $project);
        abort_unless($member->project_id === $project->getKey(), 404);

        $removed = DB::transaction(fn (): bool => $this->keepsAnOwner($project, $member, null) && $member->delete() !== false);

        if (! $removed) {
            return $this->outcome($request, false, __('A project must have at least one owner.'));
        }

        return $this->outcome($request, true, __('Member removed successfully.'));
    }

    /**
     * The drawer manages the members without leaving the page, so it gets JSON; a plain form post
     * goes back where it came from with the flash message.
     */
    private function outcome(FormRequest $request, bool $succeeded, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $succeeded ? 200 : 422);
        }

        return back(fallback: route('projects.index'))->with($succeeded ? 'status' : 'error', $message);
    }

    /**
     * Whether the project still has an owner once this member takes the new role, or leaves when it
     * is null. The project row is locked first, so two requests demoting the last two owners at once
     * cannot both see the other one still there. Callers run it inside their transaction.
     */
    private function keepsAnOwner(Project $project, ProjectMember $member, ?ProjectRole $role): bool
    {
        Project::withTrashed()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

        if ($member->role !== ProjectRole::Owner || $role === ProjectRole::Owner) {
            return true;
        }

        return $project->members()
            ->where('role', ProjectRole::Owner)
            ->whereKeyNot($member->getKey())
            ->exists();
    }
}
