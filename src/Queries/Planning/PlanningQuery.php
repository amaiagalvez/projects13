<?php

namespace Projects13\Queries\Planning;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Basics13\Queries\ListQueryBase;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Contracts\ProjectDisplayIntegration;

/**
 * The planning screen: the active projects, ordered by the day they are due and grouped by the
 * quarter they finish in, ready to be drawn either as a roadmap or on a timeline.
 *
 * Projects are the rows: their epics are the count on the row, and anything that hangs from a
 * project without carrying dates is the trail under a row rather than a row of its own. A row with
 * no end date has no bar and no quarter, and lands last.
 *
 * The ordering needs both tables at once, so the page is picked from a cheap id-only read of the two
 * and only the records that page names are then loaded whole. That keeps both the rows the database
 * hydrates and the records the browser receives to one page, which is the point of the screen.
 *
 * @extends ListQueryBase<Project>
 *
 * @phpstan-type Bar array{offset: float, width: float}
 * @phpstan-type Row array{
 *     test: string,
 *     name: string,
 *     trail: list<string>,
 *     url: string,
 *     start: CarbonImmutable|null,
 *     end: CarbonImmutable|null,
 *     status: string,
 *     count: array{icon: string, value: int, label: string, url: string|null},
 *     bar: Bar|null
 * }
 * @phpstan-type Group array{key: string, label: string, count: int, rows: list<Row>}
 * @phpstan-type Window array{
 *     from: CarbonImmutable,
 *     to: CarbonImmutable,
 *     days: int,
 *     today: float,
 *     months: list<array{label: string, width: float}>
 * }
 */
final class PlanningQuery extends ListQueryBase
{
    /**
     * Longest stretch the timeline draws before it slides the window forward. A page of records that
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
     *     counts: array{projects: int, epics: int, overdue: int},
     *     groups: list<Group>,
     *     timeline: Window,
     *     paginator: LengthAwarePaginator<int, Row>
     * }
     */
    public function overview(string $search, ?int $page = null): array
    {
        $today = CarbonImmutable::today();

        $paginator = $this->paginateRows($this->projectIds($search), $search, $today, $page);
        $rows = array_values($paginator->items());

        $timeline = $this->timeline($rows, $today);

        foreach ($rows as $index => $row) {
            $rows[$index]['bar'] = $this->bar($row, $timeline);
        }

        return [
            'counts' => $this->counts($today),
            'groups' => $this->groups($rows),
            'timeline' => $timeline,
            'paginator' => $paginator,
        ];
    }

    /**
     * How much of the portfolio is active, and how much of it is late. Counted on their own tables,
     * so the totals describe the whole active slice and do not shrink when a search term narrows
     * the list below them.
     *
     * @return array{projects: int, epics: int, overdue: int}
     */
    private function counts(CarbonImmutable $today): array
    {
        // A record is late when it has an end date and that date is behind today. The column is a
        // plain date, so the bound is compared as a string rather than wrapped in whereDate().
        $late = fn (Builder $query): Builder => $query
            ->where('active', true)
            ->where('end_date', '<', $today->toDateString());

        return [
            'projects' => Project::query()->where('active', true)->count(),
            'epics' => Epic::query()->where('active', true)->count(),
            'overdue' => $late(Project::query())->count() + $late(Epic::query())->count(),
        ];
    }

    /**
     * Active projects, whatever hangs from them: deactivation does not cascade, so the project of an
     * archived record is still active work. Ids come back in id order, which is what fixes the order
     * the pages are cut in.
     *
     * @return list<int>
     */
    private function projectIds(string $search): array
    {
        $query = Project::query()
            ->where('PRO_projects.active', true)
            // Only ids are read on this side of the page, and selecting them explicitly keeps an
            // integration's join from adding columns this pluck would then have to step over.
            ->select('PRO_projects.id');

        $this->queryIntegration->scope($query, 'planning', 'planning_entities');

        /** @var list<int|string> $ids */
        $ids = $this->whereMatches($query, $search,
            /** @phpstan-ignore argument.type */
            $this->searchColumns('planning', ['PRO_projects.name']))
            ->orderBy('PRO_projects.id')
            ->pluck('PRO_projects.id')
            ->all();

        return array_map(static fn (int|string $id): int => (int) $id, $ids);
    }

    /**
     * Load the projects of one page and wrap them in a paginator, so the view renders a page and
     * the links behave like the ones of the resource lists.
     *
     * @param  list<int>  $ids
     * @return LengthAwarePaginator<int, Row>
     */
    private function paginateRows(array $ids, string $search, CarbonImmutable $today, ?int $page): LengthAwarePaginator
    {
        // The page is cut out of the ids before anything is loaded whole: hydrating every match and
        // slicing afterwards would run whereIn() over the whole portfolio to render one page of it.
        $page ??= LengthAwarePaginator::resolveCurrentPage();
        $pageIds = array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $projects = Project::query()
            ->whereIn('PRO_projects.id', $pageIds)
            ->withCount(['epics' => fn (Builder $epics): Builder => $epics->where('PRO_epics.active', true)])
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($pageIds as $id) {
            $record = $projects->get($id);

            if ($record === null) {
                continue;
            }

            $rows[] = $this->projectRow($record, $today);
        }

        // The page arrives in id order from the query, and sorting it here as well keeps the
        // displayed order in PHP's own terms: the database breaks a date tie by its collation,
        // which is not the byte order this comparison uses.
        usort($rows, $this->byDeadline(...));

        return new LengthAwarePaginator(
            items: $rows,
            total: count($ids),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => Route::getRoutes()->getByName('planning')?->uri() ?? 'planning'],
        );
    }

    /**
     * The order the screen is read in: whatever is due soonest comes first, so the work that has
     * already slipped sits at the top without needing a filter for it. Work with no end date has no
     * deadline to be near, so it goes last, and the name keeps the order stable within a day.
     *
     * @param  Row  $left
     * @param  Row  $right
     */
    private function byDeadline(array $left, array $right): int
    {
        return [$this->deadline($left), $this->startsOn($left), $left['name']]
            <=> [$this->deadline($right), $this->startsOn($right), $right['name']];
    }

    /**
     * @param  Row  $row
     */
    private function deadline(array $row): int
    {
        return $row['end']?->getTimestamp() ?? PHP_INT_MAX;
    }

    /**
     * @param  Row  $row
     */
    private function startsOn(array $row): int
    {
        return ($row['start'] ?? $row['end'])?->getTimestamp() ?? PHP_INT_MAX;
    }

    /**
     * @return Row
     */
    private function projectRow(Project $project, CarbonImmutable $today): array
    {
        $epicsCount = (int) $project->epics_count;

        return [
            'test' => 'planning-project-'.$project->id,
            'name' => $project->name,
            'trail' => $this->displayIntegration->trail($project),
            'url' => route('projects.index', ['search' => $project->name]),
            'start' => $project->start_date,
            'end' => $project->end_date,
            'status' => $this->status($project->end_date, $today),
            'count' => [
                'icon' => 'flag',
                'value' => $epicsCount,
                'label' => __('Epics: :count', ['count' => $epicsCount]),
                'url' => $epicsCount > 0 ? route('epics.index', ['search' => $project->name]) : null,
            ],
            'bar' => null,
        ];
    }

    /**
     * Where a record stands against today. A record with no end date is not late, it is unplanned,
     * and the screen says so instead of calling it on track.
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
     * Rows bucketed by the quarter they finish in, quarters first and the undated ones last.
     *
     * @param  list<Row>  $rows
     * @return list<Group>
     */
    private function groups(array $rows): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $end = $row['end'];
            // "2026-3" sorts before "2027-1", and "no-date" sorts after both, so a plain sort of
            // the keys already gives the order the groups should be read in.
            $key = $end === null ? 'no-date' : $end->format('Y').'-'.$end->quarter;

            $buckets[$key] ??= [
                'key' => $key,
                'label' => $end === null
                    ? __('No date')
                    : __('Quarter :number, :year', ['number' => $end->quarter, 'year' => $end->year]),
                'rows' => [],
            ];

            $buckets[$key]['rows'][] = $row;
        }

        ksort($buckets);

        $groups = [];

        foreach ($buckets as $bucket) {
            $groups[] = [...$bucket, 'count' => count($bucket['rows'])];
        }

        return $groups;
    }

    /**
     * The window the timeline is drawn on. It always holds today, opens and closes on a month
     * boundary so no month label is cut in half, and is never wider than {@see self::MAX_MONTHS}:
     * past that it slides forward instead, which is what a page of records spanning years needs.
     *
     * @param  list<Row>  $rows
     * @return Window
     */
    private function timeline(array $rows, CarbonImmutable $today): array
    {
        $from = $today->startOfMonth();
        $to = $today->endOfMonth();

        foreach ($rows as $row) {
            foreach ([$row['start'], $row['end']] as $mark) {
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
     * Where one bar starts and how wide it is, as a share of the timeline window. A record with a
     * single date is a milestone: one day wide, and the view keeps it visible with a minimum width.
     * A record with no date at all has no bar.
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
     * A stretch of the timeline as a percentage of the window. Clamped to the window on both sides,
     * so a record that starts before it opens or ends after it closes is clipped by the cell instead
     * of spilling out of the row.
     */
    private function share(int $days, int $total): float
    {
        return round(max(min($days, $total), 0) / $total * 100, 4);
    }
}
