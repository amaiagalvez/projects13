<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Project;
use Illuminate\Http\JsonResponse;
use Projects13\Enums\ProjectRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Basics13\Http\Controllers\Controller;
use Projects13\Http\Requests\ProjectRequest;
use Basics13\Support\Excel\ExcelExportService;
use Projects13\Http\Requests\ProjectListRequest;
use Projects13\Queries\Projects\ProjectListQuery;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Transformers\ProjectListTransformer;
use Projects13\Contracts\ProjectValidationIntegration;
use Basics13\Support\Database\UniqueConstraintViolation;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        $hasRelated = $integration->hasActiveRelated();

        return $this->listView($request, 'projects13::projects.list', [
            'projects' => $projects,
            'hasRelated' => $hasRelated,
            // The banner only explains an empty choice, so it is not asked for again when one exists.
            'noRelatedBanner' => $hasRelated ? null : $integration->noRelatedBanner(),
            'list' => $transformer->active(
                $projects,
                $search,
                $query->stateCounts(activeTotal: $search === '' && $filters === [] ? $projects->total() : null),
            ),
            'searchableFields' => $query->searchableFields('active'),
            'filters' => $filters,
            'scope' => 'active',
        ]);
    }

    public function export(
        ProjectListRequest $request,
        ProjectListQuery $query,
    ): BinaryFileResponse {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $scope = $request->scope();

        $queryBuilder = $query->filteredQuery($search, $filters, $scope);

        $columnDefinitions = [
            ['key' => 'id', 'label' => __('ID')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'notes', 'label' => __('Notes')],
            ['key' => 'start_date', 'label' => __('Start date')],
            ['key' => 'end_date', 'label' => __('End date')],
            ['key' => 'active', 'label' => __('Status'), 'transformer' => fn ($p) => $p->active ? __('basics13::messages.active_label') : __('basics13::messages.inactive_label')],
            ['key' => 'created_at', 'label' => __('Created at')],
        ];

        return (new ExcelExportService(
            query: $queryBuilder,
            title: 'Projects Export',
            columnDefinitions: $columnDefinitions,
            maxRows: 50000,
        ))->download(ExcelExportService::fileName(__('Projects'), $scope));
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

                // Whoever creates the project owns it, so it does not vanish from their own lists.
                $authorId = (int) $request->user()?->getAuthIdentifier();
                $project->members()->forceCreate([
                    'user_id' => $authorId,
                    'role' => ProjectRole::Owner,
                    'added_by' => $authorId,
                ]);
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($project->only(['id', 'name']), 201);
        }

        return $this->backToList('projects.index')->with('status', __('basics13::messages.created'));
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

        return $this->backToList('projects.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        // The deleting hook holds the row lock and refuses the delete while children exist, so the
        // controller only has to run it inside a transaction and report the outcome.
        $deleted = DB::transaction(static fn (): bool => $project->delete() !== false);

        if (! $deleted) {
            return $this->backToList('projects.index')
                ->with('error', __('Cannot be deleted while it has related records.'));
        }

        return $this->backToList('projects.index')->with('status', __('basics13::messages.moved_to_trash'));
    }
}
