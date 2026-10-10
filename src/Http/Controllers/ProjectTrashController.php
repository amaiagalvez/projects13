<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Controllers\TrashController;
use Projects13\Http\Requests\ProjectListRequest;
use Projects13\Queries\Projects\ProjectListQuery;
use Projects13\Http\Requests\ProjectDestroyRequest;
use Projects13\Http\Requests\ProjectRestoreRequest;
use Projects13\Transformers\ProjectListTransformer;

/**
 * @extends TrashController<Project>
 */
class ProjectTrashController extends TrashController
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $projects = $query->trashed($search);

        return $this->listView($request, 'projects13::projects.list', [
            'projects' => $projects,
            'list' => $transformer->trash(
                $projects,
                $search,
                $query->stateCounts(trashedTotal: $search === '' ? $projects->total() : null),
            ),
        ]);
    }

    public function restore(ProjectRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(ProjectDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * @param  Project  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        $record->restore();
    }

    /**
     * @param  Project  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(Project::query(), $record->name);
    }

    protected function recordClass(): string
    {
        return Project::class;
    }

    protected function trashRoute(): string
    {
        return 'projects.trash.index';
    }
}
