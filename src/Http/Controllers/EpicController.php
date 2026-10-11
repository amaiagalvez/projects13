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
use Projects13\Contracts\ProjectIntegration;
use Projects13\Http\Requests\EpicListRequest;
use Projects13\Transformers\EpicListTransformer;
use Basics13\Support\Database\UniqueConstraintViolation;

class EpicController extends Controller
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
        ProjectIntegration $integration,
    ): View|string {
        $search = $request->search();
        $epics = $query->active($search);
        $projectId = old('project_id');
        $selectedProject = is_numeric($projectId)
            ? Project::query()->find((int) $projectId, ['id', 'name'])
            : null;

        return $this->listView($request, 'projects13::epics.list', [
            'epics' => $epics,
            'hasProjects' => Project::query()->where('active', true)->exists(),
            'selectedProjectOption' => $selectedProject === null ? null : [
                'id' => $selectedProject->id,
                'text' => $integration->label($selectedProject),
            ],
            'drawerEpic' => $this->drawerEpic($request, $transformer),
            'list' => $transformer->active(
                $epics,
                $search,
                $query->stateCounts(activeTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
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

        try {
            DB::transaction(static function () use ($request): void {
                Project::query()->lockForUpdate()->findOrFail($request->integer('project_id'));
                Epic::create($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('epics.index')->with('status', __('basics13::messages.created'));
    }

    public function update(EpicRequest $request, Epic $epic): RedirectResponse
    {
        try {
            DB::transaction(static function () use ($request, $epic): void {
                Project::query()->lockForUpdate()->findOrFail($request->integer('project_id'));
                $epic->update($request->validated());
            });
        } catch (QueryException $exception) {
            UniqueConstraintViolation::rethrowAsValidationError($exception);
        }

        return to_route('epics.index')->with('status', __('basics13::messages.updated'));
    }

    public function destroy(Epic $epic): RedirectResponse
    {
        $this->authorize('delete', $epic);

        // The deleting hook locks the epic and refuses deletion while comments exist.
        $deleted = DB::transaction(static fn (): bool => $epic->delete() !== false);

        if (! $deleted) {
            return to_route('epics.index')
                ->with('error', __('Cannot be deleted while it has related records.'));
        }

        return to_route('epics.index')->with('status', __('basics13::messages.moved_to_trash'));
    }
}
