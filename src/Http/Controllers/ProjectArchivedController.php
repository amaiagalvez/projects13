<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Project;
use Illuminate\Http\RedirectResponse;
use Projects13\Http\Requests\ProjectListRequest;
use Basics13\Http\Controllers\ArchivedController;
use Projects13\Queries\Projects\ProjectListQuery;
use Projects13\Transformers\ProjectListTransformer;

class ProjectArchivedController extends ArchivedController
{
    public function index(
        ProjectListRequest $request,
        ProjectListQuery $query,
        ProjectListTransformer $transformer,
    ): View|string {
        $search = $request->search();
        $filters = $request->filters() ?? [];
        $projects = $query->archived($search, $filters);

        return $this->listView($request, 'projects13::projects.list', [
            'projects' => $projects,
            'list' => $transformer->archived(
                $projects,
                $search,
                $query->stateCounts(archivedTotal: $search === '' ? $projects->total() : null),
            ),
            'searchableFields' => $query->searchableFields('archived'),
            'filters' => $filters,
            'scope' => 'archived',
        ]);
    }

    public function archive(Project $project): RedirectResponse
    {
        return $this->archiveRecord($project);
    }

    public function activate(Project $project): RedirectResponse
    {
        return $this->activateRecord($project);
    }

    protected function activeRoute(): string
    {
        return 'projects.index';
    }

    protected function archivedRoute(): string
    {
        return 'projects.archived.index';
    }
}
