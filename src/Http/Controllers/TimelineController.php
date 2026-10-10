<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Http\Controllers\Controller;
use Projects13\Queries\Timeline\TimelineQuery;
use Projects13\Http\Requests\TimelineListRequest;

class TimelineController extends Controller
{
    /**
     * The active projects of one page with their active epics nested underneath, ready to be
     * drawn as bars on a single month axis. `?search=` narrows both levels: a project that
     * matches brings its epics along, a project that does not match only brings the epics that do.
     */
    public function index(TimelineListRequest $request, TimelineQuery $query): View
    {
        $search = $request->search();

        return view('projects13::timeline.index', [
            'search' => [
                'action' => route('timeline'),
                'value' => $search,
                'placeholder' => __('Search projects and epics'),
            ],
            'timeline' => $query->overview($search),
        ]);
    }
}
