<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Basics13\Http\Controllers\Controller;
use Projects13\Http\Requests\EpicRequest;
use Projects13\Queries\Epics\EpicListQuery;
use Projects13\Http\Requests\EpicListRequest;
use Basics13\Support\Excel\ExcelExportService;
use Illuminate\Validation\ValidationException;
use Projects13\Transformers\EpicListTransformer;
use Projects13\Contracts\ProjectDisplayIntegration;
use Basics13\Support\Database\UniqueConstraintViolation;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EpicController extends Controller
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
        ProjectDisplayIntegration $integration,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $epics = $query->active($search, $filters);
        $projectId = old('project_id');
        $selectedProject = is_numeric($projectId)
            ? Project::query()->find((int) $projectId, ['id', 'name', 'active'])
            : null;

        return $this->listView($request, 'projects13::epics.list', [
            'epics' => $epics,
            'hasProjects' => Project::query()->where('active', true)->exists(),
            'selectedProjectOption' => $selectedProject === null ? null : [
                'id' => $selectedProject->id,
                'text' => $integration->label($selectedProject),
                'active' => $selectedProject->active,
            ],
            'drawerEpic' => $this->drawerEpic($request, $transformer),
            'list' => $transformer->active(
                $epics,
                $search,
                $query->stateCounts(activeTotal: $search === '' && $filters === [] ? $epics->total() : null),
            ),
            'searchableFields' => $query->searchableFields('active'),
            'filters' => $filters,
            'scope' => 'active',
        ]);
    }

    public function export(
        EpicListRequest $request,
        EpicListQuery $query,
    ): BinaryFileResponse {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $scope = $request->scope();

        $queryBuilder = $query->filteredQuery($search, $filters, $scope);

        $columnDefinitions = [
            ['key' => 'id', 'label' => __('ID')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'project', 'label' => __('Project'), 'transformer' => fn ($e) => $e->project?->name],
            ['key' => 'notes', 'label' => __('Notes')],
            ['key' => 'start_date', 'label' => __('Start date')],
            ['key' => 'end_date', 'label' => __('End date')],
            ['key' => 'comments_count', 'label' => __('Comments')],
            ['key' => 'active', 'label' => __('Status'), 'transformer' => fn ($e) => $e->active ? __('basics13::messages.active_label') : __('basics13::messages.inactive_label')],
            ['key' => 'created_at', 'label' => __('Created at')],
        ];

        return (new ExcelExportService(
            query: $queryBuilder,
            title: 'Epics Export',
            columnDefinitions: $columnDefinitions,
            maxRows: 50000,
        ))->download(ExcelExportService::fileName(__('Epics'), $scope));
    }

    /**
     * The epic whose drawer has to be reopened after the request: the one just commented, or the one
     * whose edit failed. It is resolved by id instead of being looked up among the current page, so
     * a comment saved on a later page still reopens the right drawer.
     *
     * @return array<string, mixed>|null
     */
    private function drawerEpic(EpicListRequest $request, EpicListTransformer $transformer): ?array
    {
        $id = $request->session()->get('commented_epic_id')
            ?? $request->old('_epic_id');

        if (! is_numeric($id)) {
            return null;
        }

        $epic = Epic::query()->with('project')->withCount('comments')->find((int) $id);

        return $epic === null ? null : $transformer->payloadFor($epic);
    }

    public function store(EpicRequest $request, EpicListQuery $query): RedirectResponse|JsonResponse
    {
        $deletedEpic = $query->findTrashedByName(
            $request->string('name')->toString(),
            $request->integer('project_id'),
        );

        if ($deletedEpic && ! $request->boolean('reuse_deleted_name')) {
            return $this->deletedNameConflict($request, $deletedEpic, 'epics.index', 'deleted_epic_conflict');
        }

        $epic = null;
        try {
            DB::transaction(static function () use ($request, &$epic): void {
                self::lockProject($request->integer('project_id'));
                $epic = Epic::create($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($epic->only(['id', 'name']), 201);
        }

        return $this->backToList('epics.index')->with('status', __('basics13::messages.created'));
    }

    /**
     * Lock the epic's project until the write commits, so it cannot be trashed in between. One
     * trashed after validation is reported on the field, like any other project that is not there.
     */
    private static function lockProject(int $projectId): void
    {
        if (Project::query()->lockForUpdate()->find($projectId) === null) {
            throw ValidationException::withMessages([
                'project_id' => __('validation.exists', ['attribute' => __('Project')]),
            ]);
        }
    }

    public function update(EpicRequest $request, Epic $epic): RedirectResponse|JsonResponse
    {
        try {
            DB::transaction(static function () use ($request, $epic): void {
                self::lockProject($request->integer('project_id'));
                $epic->update($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        if ($request->expectsJson()) {
            return response()->json($epic->only(['id', 'name']));
        }

        return $this->backToList('epics.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(Epic $epic): RedirectResponse
    {
        $this->authorize('delete', $epic);

        // The deleting hook locks the epic and refuses deletion while comments exist.
        $deleted = DB::transaction(static fn (): bool => $epic->delete() !== false);

        if (! $deleted) {
            return $this->backToList('epics.index')
                ->with('error', __('Cannot be deleted while it has related records.'));
        }

        return $this->backToList('epics.index')->with('status', __('basics13::messages.moved_to_trash'));
    }
}
