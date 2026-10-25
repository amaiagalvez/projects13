<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Projects13\Models\Project;
use Basics13\Http\Controllers\Controller;
use Projects13\Queries\Dashboard\DashboardSummaryQuery;

class DashboardController extends Controller
{
    /**
     * The work dashboard: the state of the projects and epics the user can see.
     */
    public function __invoke(DashboardSummaryQuery $query): View
    {
        $this->authorize('viewAny', Project::class);

        return view('projects13::dashboard.index', [
            'summary' => $query->summary(),
        ]);
    }
}
