<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Basics13\Http\Controllers\Controller;
use Projects13\Http\Requests\ProjectRequest;
use Projects13\Http\Requests\ProjectListRequest;
use Projects13\Queries\Projects\ProjectListQuery;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Transformers\ProjectListTransformer;
use Projects13\Contracts\ProjectValidationIntegration;
use Basics13\Support\Database\UniqueConstraintViolation;
use Projects13\Http\Requests\ProjectSelectOptionsRequest;
use Projects13\Queries\Projects\ProjectSelectOptionsQuery;

class ProjectController extends Controller
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
        ProjectDisplayIntegration $integration,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $projects = $query->active($search, $filters);

        return $this->listView($request, 'projects13::projects.list', [
            'projects' => $projects,
            'hasEntities' => $integration->hasActiveRelatedEntities(),
            'noRelatedEntitiesBanner' => $integration->noRelatedEntitiesBanner(),
            'list' => $transformer->active(
                $projects,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $projects->total() : null),
            ),
            'searchableFields' => $query->searchableFields('active'),
            'filters' => $filters,
            'scope' => 'active',
        ]);
    }

    public function selectOptions(
        ProjectSelectOptionsRequest $request,
        ProjectSelectOptionsQuery $query,
    ): JsonResponse {
        return response()->json([
            'results' => $query->search($request->search()),
        ]);
    }

    public function store(
        ProjectRequest $request,
        ProjectListQuery $query,
        ProjectValidationIntegration $integration,
    ): RedirectResponse|JsonResponse {
        $name = $request->string('name')->toString();
        $deletedProject = $query->findTrashedByName($name);

        if ($deletedProject && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $deletedProject, 'projects.index', 'deleted_project_conflict');
        }

        $project = null;
        try {
            DB::transaction(static function () use ($request, $integration, &$project): void {
                // The integration contributes attributes that are not part of the base model, so
                // they are force-filled instead of mass-assigned and only after it validated them.
                $project = new Project($request->validated());
                $project->forceFill($integration->saving($request, null));
                $project->save();
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($project->only(['id', 'name']), 201);
        }

        return to_route('projects.index')->with('status', __('basics13::messages.created'));
    }

    public function update(
        ProjectRequest $request,
        Project $project,
        ProjectValidationIntegration $integration,
    ): RedirectResponse|JsonResponse {
        try {
            DB::transaction(static function () use ($request, $project, $integration): void {
                $project->fill($request->validated());
                $project->forceFill($integration->saving($request, $project));
                $project->save();
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($project->only(['id', 'name']));
        }

        return to_route('projects.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        // The deleting hook holds the row lock and refuses the delete while children exist, so the
        // controller only has to run it inside a transaction and report the outcome.
        $deleted = DB::transaction(static fn (): bool => $project->delete() !== false);

        if (! $deleted) {
            return to_route('projects.index')
                ->with('error', __('Cannot be deleted while it has related records.'));
        }

        return to_route('projects.index')->with('status', __('basics13::messages.moved_to_trash'));
    }
}
