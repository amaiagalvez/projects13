<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Epic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Model;
use Projects13\Queries\Epics\EpicListQuery;
use Projects13\Http\Requests\EpicListRequest;
use Basics13\Http\Controllers\TrashController;
use Projects13\Http\Requests\EpicDestroyRequest;
use Projects13\Http\Requests\EpicRestoreRequest;
use Projects13\Transformers\EpicListTransformer;

/**
 * @extends TrashController<Epic>
 */
class EpicTrashController extends TrashController
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $epics = $query->trashed($search, $filters);

        return $this->listView($request, 'projects13::epics.list', [
            'epics' => $epics,
            'list' => $transformer->trash(
                $epics,
                $search,
                $query->stateCounts(trashedTotal: $search === '' && $filters === [] ? $epics->total() : null),
            ),
            'searchableFields' => $query->searchableFields('trashed'),
            'filters' => $filters,
            'scope' => 'trashed',
        ]);
    }

    public function restore(EpicRestoreRequest $request): RedirectResponse
    {
        return $this->restoreTrashed($request);
    }

    public function destroy(EpicDestroyRequest $request): RedirectResponse
    {
        return $this->destroyTrashed($request);
    }

    /**
     * @param  Epic  $record
     */
    protected function restoreTrashedRecord(Model $record): void
    {
        $record->restore();
    }

    /**
     * Epic names are only unique inside their project, so the search is narrowed to it.
     *
     * @param  Epic  $record
     */
    protected function nameIsTaken(Model $record): bool
    {
        return $this->takenBy(
            Epic::query()->where('project_id', $record->project_id),
            $record->name,
        );
    }

    protected function recordClass(): string
    {
        return Epic::class;
    }

    protected function trashRoute(): string
    {
        return 'epics.trash.index';
    }
}
