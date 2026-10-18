<?php

namespace Projects13\Queries\Timeline;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Projects13\Contracts\ProjectDisplayIntegration;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The timeline screen: the active projects that belong on the board, in the order they start,
 * each one carrying the active epics drawn underneath it, all of it ready to be painted on one
 * shared month axis.
 *
 * A project belongs on the board when a search term matches its own name, the name of anything that
 * hangs from it, or any of its epics. Matching the project brings every one of its epics along,
 * because the row stands for the whole project; matching only an epic leaves the project as the
 * context that holds it and keeps its other epics off the board, which is how narrow a search
 * stays on the flat planning screen.
 *
 * The board is a page of projects rather than a page of rows, so the ordering that picks that
 * page spans two tables: the ids are read first and only the records that page names are then
 * loaded whole, with their epics.
 *
 * @extends ListQueryBase<Project>
 *
 * @phpstan-type Bar array{offset: float, width: float}
 * @phpstan-type Row array{
 *     id: int,
 *     test: string,
 *     name: string,
 *     trail: list<string>,
 *     url: string,
 *     start: CarbonImmutable|null,
 *     end: CarbonImmutable|null,
 *     status: string,
 *     bar: Bar|null
 * }
 * @phpstan-type Entry array{
 *     kind: 'project'|'epic',
 *     projectId: int,
 *     controls: list<string>,
 *     row: Row
 * }
 * @phpstan-type Window array{
 *     from: CarbonImmutable,
 *     to: CarbonImmutable,
 *     days: int,
 *     today: float,
 *     months: list<array{label: string, width: float}>
 * }
 */
final class TimelineQuery extends ListQueryBase
{
    /**
     * Longest stretch the axis draws before it slides the window forward. A page of projects that
     * spans five years would otherwise squeeze every month into a couple of unreadable pixels.
     */
    private const MAX_MONTHS = 24;

    public function __construct(
        private readonly ProjectQueryIntegration $queryIntegration,
        private readonly ProjectDisplayIntegration $displayIntegration,
    ) {}

    /**
     * @return class-string<Project>
     */
    protected function getModelClass(): string
    {
        return Project::class;
    }

    protected function integration(): ProjectQueryIntegration
    {
        return $this->queryIntegration;
    }

    /**
     * @return array{
     *     entries: Collection<int, Entry>,
     *     timeline: Window,
     *     paginator: LengthAwarePaginator<int, Entry>
     * }
     */
    public function overview(string $search): array
    {
        $today = CarbonImmutable::today();

        $ids = $this->projectIds($search);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageIds = array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        // The page is read in page order rather than loaded order, so the start-date ordering
        // that picked it survives into the board.
        $projects = $this->projects($pageIds, $search);
        $entries = [];

        foreach ($pageIds as $id) {
            $project = $projects->get($id);

            if ($project === null) {
                continue;
            }

            $entries = [...$entries, ...$this->entriesOf($project, $today)];
        }

        $timeline = $this->timeline($entries, $today);

        foreach ($entries as $index => $entry) {
            $entries[$index]['row']['bar'] = $this->bar($entry['row'], $timeline);
        }

        $paginator = new LengthAwarePaginator(
            items: $entries,
            total: count($ids),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => route('timeline')],
        );

        if ($search !== '') {
            $paginator->appends(['search' => $search]);
        }

        // @phpstan-ignore-next-line
        return [
            'entries' => collect($entries),
            'timeline' => $timeline,
            'paginator' => $paginator,
        ];
    }

    /**
     * The ids of every project that belongs on the board, already in the order the board reads:
     * by the day the project starts, then by name, which is unique among active projects. Only
     * ids are read here, so drawing one page does not hydrate the whole portfolio.
     *
     * @return list<int>
     */
    private function projectIds(string $search): array
    {
        $query = $this->matchingProjects($search)
            // Only ids are read on this side of the page, and selecting them explicitly keeps an
            // integration's join from adding columns this pluck would then have to step over.
            ->select('PRO_projects.id');

        $ids = $query
            ->orderBy('PRO_projects.start_date')
            ->orderBy('PRO_projects.name')
            ->pluck('PRO_projects.id')
            ->all();

        /** @var list<int> */
        return array_map('intval', $ids);
    }

    /**
     * The active projects a search term puts on the board. A term that matches nothing leaves the
     * query untouched, so an empty search is every active project.
     *
     * @return Builder<Project>
     */
    private function matchingProjects(string $search): Builder
    {
        $query = Project::query()->where('PRO_projects.active', true);

        $this->queryIntegration->scope($query, 'timeline', 'timeline_entities');

        $nameColumns = $this->searchColumns('timeline', ['PRO_projects.name']);

        return $query->where(function (Builder $query) use ($search, $nameColumns): void {
            /** @phpstan-ignore argument.type */
            $this->whereMatches($query, $search, $nameColumns);

            if ($search !== '') {
                $query->orWhereHas('epics', function (Builder $epics) use ($search): void {
                    $epics->where('PRO_epics.active', true);
                    $this->whereMatches($epics, $search, ['PRO_epics.name']);
                });
            }
        });
    }

    /**
     * The projects that matched by their own name or by the name of whatever hangs from them (NOT
     * by epic name). These projects bring ALL their epics along.
     *
     * @return Builder<Project>
     */
    private function projectsMatchingByNameOrEntity(string $search): Builder
    {
        $query = Project::query()->where('PRO_projects.active', true);

        $this->queryIntegration->scope($query, 'timeline', 'timeline_entities');

        // Select only PRO_projects columns to avoid issues with pluck() after joins
        $query->select('PRO_projects.*');

        return $query->where(function (Builder $query) use ($search): void {
            /** @phpstan-ignore argument.type */
            $this->whereMatches($query, $search, $this->searchColumns('timeline', ['PRO_projects.name']));
        });
    }

    /**
     * The projects of the page with the epics the board draws under them, keyed by id and in the
     * order the page named them. A search that reached the board through an epic keeps only that
     * epic under a project that did not match itself.
     *
     * @param  list<int>  $pageIds
     * @return EloquentCollection<int, Project>
     */
    private function projects(array $pageIds, string $search): EloquentCollection
    {
        $projects = Project::query()
            ->whereIn('PRO_projects.id', $pageIds)
            ->with([
                'epics' => static function (HasMany $epics): HasMany {
                    return $epics->where('PRO_epics.active', true);
                },
            ])
            ->get()
            ->keyBy('id');

        if ($search !== '') {
            $this->narrowToMatchingEpics($projects, $pageIds, $search);
        }

        return $projects;
    }

    /**
     * @param  EloquentCollection<int, Project>  $projects
     * @param  list<int>  $pageIds
     */
    private function narrowToMatchingEpics(EloquentCollection $projects, array $pageIds, string $search): void
    {
        /** @var list<int> */
        $matchedProjectsByName = $this->projectsMatchingByNameOrEntity($search)
            ->whereIn('PRO_projects.id', $pageIds)
            ->pluck('PRO_projects.id')
            ->map('intval')
            ->all();

        /** @var list<int> */
        $matchedEpics = $this->whereMatches(
            Epic::query()->where('PRO_epics.active', true)->whereIn('PRO_epics.project_id', $pageIds),
            $search,
            ['PRO_epics.name'],
        )
            ->pluck('PRO_epics.id')
            ->map('intval')
            ->all();

        foreach ($projects as $project) {
            if (in_array($project->id, $matchedProjectsByName, true)) {
                continue;
            }

            $project->setRelation('epics', $project->epics
                ->filter(static fn(Epic $epic): bool => in_array($epic->id, $matchedEpics, true))
                ->values());
        }
    }

    /**
     * One project row followed by its epics, each epic naming the row that controls it so the
     * view can tie the two together.
     *
     * @return list<Entry>
     */
    private function entriesOf(Project $project, CarbonImmutable $today): array
    {
        $epics = $project->epics->all();
        usort($epics, fn(Epic $left, Epic $right): int => $this->byStartDate(
            [$left->start_date, $left->name],
            [$right->start_date, $right->name],
        ));

        $entries = [[
            'kind' => 'project',
            'projectId' => $project->id,
            'controls' => array_map(
                static fn(Epic $epic): string => 'timeline-epic-' . $epic->id,
                $epics,
            ),
            'row' => $this->projectRow($project, $today),
        ]];

        foreach ($epics as $epic) {
            $entries[] = [
                'kind' => 'epic',
                'projectId' => $project->id,
                'controls' => [],
                'row' => $this->epicRow($epic, $today),
            ];
        }

        return $entries;
    }

    /**
     * The order the board reads in: by the day the record starts, records with no date last,
     * and by name to keep a day stable. The project is ordered this way against the other
     * projects of the page, an epic against the epics of its project.
     *
     * @param  array{0: CarbonImmutable|null, 1: string}  $left
     * @param  array{0: CarbonImmutable|null, 1: string}  $right
     */
    private function byStartDate(array $left, array $right): int
    {
        return [
            $left[0]?->getTimestamp() ?? PHP_INT_MAX,
            $left[1],
        ] <=> [
            $right[0]?->getTimestamp() ?? PHP_INT_MAX,
            $right[1],
        ];
    }

    /**
     * @return Row
     */
    private function projectRow(Project $project, CarbonImmutable $today): array
    {
        return [
            'id' => $project->id,
            'test' => 'timeline-project-' . $project->id,
            'name' => $project->name,
            'trail' => $this->displayIntegration->trail($project),
            'url' => route('projects.index', ['search' => $project->name]),
            'start' => $project->start_date,
            'end' => $project->end_date,
            'status' => $this->status($project->end_date, $today),
            'bar' => null,
        ];
    }

    /**
     * @return Row
     */
    private function epicRow(Epic $epic, CarbonImmutable $today): array
    {
        return [
            'id' => $epic->id,
            'test' => 'timeline-epic-' . $epic->id,
            'name' => $epic->name,
            // The epic is indented under its project rather than carried by a visible trail, so
            // the trail only has to be there for assistive technology to place the row.
            'trail' => [$epic->project->name],
            'url' => route('epics.index', ['search' => $epic->name]),
            'start' => $epic->start_date,
            'end' => $epic->end_date,
            'status' => $this->status($epic->end_date, $today),
            'bar' => null,
        ];
    }

    /**
     * Where a record stands against today. A record with no end date is not late, it is unscheduled,
     * and the row says so instead of calling it on track.
     */
    private function status(?CarbonImmutable $end, CarbonImmutable $today): string
    {
        return match (true) {
            $end === null => 'unscheduled',
            $end->lt($today) => 'overdue',
            default => 'ontrack',
        };
    }

    /**
     * The window the axis is drawn on. It always holds today, opens and closes on a month
     * boundary so no month label is cut in half, and is never wider than {@see self::MAX_MONTHS}:
     * past that it slides forward, which is what a page of records spanning years needs.
     *
     * @param  list<Entry>  $entries
     * @return Window
     */
    private function timeline(array $entries, CarbonImmutable $today): array
    {
        $from = $today->startOfMonth();
        $to = $today->endOfMonth();

        foreach ($entries as $entry) {
            foreach ([$entry['row']['start'], $entry['row']['end']] as $mark) {
                if ($mark !== null) {
                    $from = $from->min($mark->startOfMonth());
                    $to = $to->max($mark->endOfMonth());
                }
            }
        }

        if ($from->lessThan($to->subMonthsNoOverflow(self::MAX_MONTHS)->startOfMonth())) {
            $from = $to->subMonthsNoOverflow(self::MAX_MONTHS)->startOfMonth();
        }

        $days = $this->days($from, $to->startOfDay()->addDay());
        $months = [];

        for ($month = $from; $month->lessThan($to); $month = $month->addMonth()) {
            $months[] = [
                'label' => $month->format('Y-m'),
                'width' => $this->share($this->days($month, $month->addMonth()), $days),
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'today' => $this->share($this->days($from, $today), $days),
            'months' => $months,
        ];
    }

    /**
     * Where one bar starts and how wide it is, as a share of the window. A record with a single
     * date is a milestone: one day wide, and the view keeps it visible with a minimum width. A
     * record with no date at all has no bar.
     *
     * @param  Row  $row
     * @param  Window  $timeline
     * @return Bar|null
     */
    private function bar(array $row, array $timeline): ?array
    {
        // A record with only an end date is drawn from that date onwards, so the branch is spelled
        // out instead of relying on `$row['start'] ?? $row['end']` to narrow the types for us.
        if ($row['start'] !== null) {
            $start = $row['start'];
            $end = $row['end'] ?? $row['start'];
        } elseif ($row['end'] !== null) {
            $start = $row['end'];
            $end = $row['end'];
        } else {
            return null;
        }

        return [
            'offset' => $this->share($this->days($timeline['from'], $start), $timeline['days']),
            'width' => $this->share($this->days($start, $end->addDay()), $timeline['days']),
        ];
    }

    /**
     * Whole days between two dates. The end is exclusive, which is what makes a bar finishing on
     * the last day of the window still reach its right edge.
     */
    private function days(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round($from->diffInDays($to));
    }

    /**
     * A stretch of the axis as a percentage of the window. Clamped to the window on both sides, so
     * a record that starts before it opens or ends after it closes is clipped by the cell instead
     * of spilling out of the row.
     */
    private function share(int $days, int $total): float
    {
        return round(max(min($days, $total), 0) / $total * 100, 4);
    }
}
