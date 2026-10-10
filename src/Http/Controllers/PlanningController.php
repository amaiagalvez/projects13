<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Basics13\Http\Controllers\Controller;
use Projects13\Queries\Planning\PlanningQuery;
use Projects13\Http\Requests\PlanningListRequest;

class PlanningController extends Controller
{
    /**
     * The active projects and epics, ordered by deadline and grouped by quarter. `?view=` only
     * chooses the layout; the same rows are rendered either way.
     */
    public function index(PlanningListRequest $request, PlanningQuery $query): View
    {
        $search = $request->search();

        return view('projects13::planning.index', [
            'timeline' => $request->view() === 'timeline',
            'search' => [
                'action' => route('planning'),
                'value' => $search,
                'placeholder' => __('Search projects and epics'),
            ],
            'views' => [
                $this->tab(__('Roadmap'), 'roadmap', $search, $request->view()),
                $this->tab(__('Timeline'), 'timeline', $search, $request->view()),
            ],
            'planning' => $query->overview($search),
        ]);
    }

    /**
     * One of the two layouts, as a link rather than a panel: the choice stays in the URL, so it
     * survives a reload and can be shared. The search term travels with it.
     *
     * @return array{label: string, url: string, current: bool, count: null, test: string}
     */
    private function tab(string $label, string $mode, string $search, string $current): array
    {
        return [
            'label' => $label,
            'url' => route('planning', array_filter([
                'search' => $search,
                'view' => $mode === PlanningListRequest::VIEWS[0] ? null : $mode,
            ])),
            'current' => $mode === $current,
            'count' => null,
            'test' => 'planning-'.$mode.'-link',
        ];
    }
}
