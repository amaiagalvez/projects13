<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Epic;
use Illuminate\Http\RedirectResponse;
use Projects13\Queries\Epics\EpicListQuery;
use Projects13\Http\Requests\EpicListRequest;
use Projects13\Transformers\EpicListTransformer;
use Basics13\Http\Controllers\ArchivedController;

class EpicArchivedController extends ArchivedController
{
    public function index(
        EpicListRequest $request,
        EpicListQuery $query,
        EpicListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $epics = $query->archived($search);

        return $this->listView($request, 'projects13::epics.list', [
            'epics' => $epics,
            'list' => $transformer->archived(
                $epics,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $epics->total() : null),
            ),
        ]);
    }

    public function archive(Epic $epic): RedirectResponse
    {
        return $this->archiveRecord($epic);
    }

    public function activate(Epic $epic): RedirectResponse
    {
        return $this->activateRecord($epic);
    }

    protected function activeRoute(): string
    {
        return 'epics.index';
    }

    protected function archivedRoute(): string
    {
        return 'epics.archived.index';
    }
}
