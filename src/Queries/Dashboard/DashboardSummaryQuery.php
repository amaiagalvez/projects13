<?php

namespace Projects13\Queries\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Projects13\Models\Epic;
use Projects13\Models\EpicComment;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Queries\ProjectVisibility;
use Projects13\Queries\Planning\PlanningQuery;

/**
 * What the dashboard says about the work: how much of it is active and late (the same counts as
 * the planning page), what closes this month, the next deadlines and the latest comments. Only
 * active, non-deleted epics count as upcoming work. Comments need no such filter: an epic with
 * comments cannot be trashed.
 */
final class DashboardSummaryQuery
{
    /**
     * Rows in each "recent" table.
     */
    public const RECENT_LIMIT = 5;

    /**
     * Weeks ahead in the trend of upcoming deadlines, this week included.
     */
    public const TREND_WEEKS = 8;

    public function __construct(private readonly PlanningQuery $planning) {}

    /**
     * @return array{
     *     counts: array{projects: int, epics: int, overdue: int},
     *     dueThisMonth: int,
     *     weeklyDue: list<int>,
     *     commentsThisWeek: int,
     *     deadlines: list<array{id: int, name: string, project: string, endDate: string, url: string}>,
     *     comments: list<array{id: int, author: string, initials: string, epic: string, dateTime: string|null, body: string, url: string}>
     * }
     */
    public function summary(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        return [
            'counts' => $this->planning->counts($today),
            'dueThisMonth' => $this->upcomingEpics($today)
                // Exclusive bound on the next day: the stored value may carry a midnight time.
                ->where('end_date', '<', $today->startOfMonth()->addMonth()->toDateString())
                ->count(),
            'weeklyDue' => $this->weeklyDue($today),
            'commentsThisWeek' => $this->visibleComments()
                ->where('created_at', '>=', $today->startOfWeek())
                ->count(),
            'deadlines' => $this->deadlines($today),
            'comments' => $this->comments(),
        ];
    }

    /**
     * Active epics that have not ended yet. The column is a plain date, so the bound is compared as
     * a string, as the planning counts do.
     *
     * @return Builder<Epic>
     */
    private function upcomingEpics(CarbonImmutable $today): Builder
    {
        return (new ProjectVisibility)->restrict(Epic::query(), 'project_id')
            ->where('active', true)
            ->where('end_date', '>=', $today->toDateString());
    }

    /**
     * How many active epics end in each of the coming weeks, this one first. Bucketed here rather
     * than grouped in SQL, where week functions differ between the database engines in use.
     *
     * @return list<int>
     */
    private function weeklyDue(CarbonImmutable $today): array
    {
        $start = $today->startOfWeek();
        $weeks = array_fill(0, self::TREND_WEEKS, 0);

        $endDates = (new ProjectVisibility)->restrict(Epic::query(), 'project_id')
            ->where('active', true)
            ->where('end_date', '>=', $start->toDateString())
            ->where('end_date', '<', $start->addWeeks(self::TREND_WEEKS)->toDateString())
            ->pluck('end_date');

        foreach ($endDates as $endDate) {
            $weeks[intdiv((int) $start->diffInDays($endDate), 7)]++;
        }

        return array_values($weeks);
    }

    /**
     * @return list<array{id: int, name: string, project: string, endDate: string, url: string}>
     */
    private function deadlines(CarbonImmutable $today): array
    {
        return array_values($this->upcomingEpics($today)
            ->with('project:id,name')
            ->orderBy('end_date')
            ->orderBy('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'name', 'project_id', 'end_date'])
            ->map(fn (Epic $epic): array => [
                'id' => $epic->id,
                'name' => $epic->name,
                'project' => $epic->project->name,
                'endDate' => (string) $epic->end_date?->format('Y-m-d'),
                'url' => route('epics.index', ['search' => $epic->name]),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, author: string, initials: string, epic: string, dateTime: string|null, body: string, url: string}>
     */
    private function comments(): array
    {
        $comments = $this->visibleComments()
            ->with(['user:id,name', 'epic:id,name'])
            ->latest()
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        $rows = [];

        foreach ($comments as $comment) {
            $author = $comment->user === null ? (string) __('Deleted user') : (string) $comment->user->getAttribute('name');

            $rows[] = [
                'id' => $comment->id,
                'author' => $author,
                'initials' => Str::of($author)->squish()->explode(' ')->take(2)
                    ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))->implode(''),
                'epic' => $comment->epic->name,
                'dateTime' => $comment->created_at?->toIso8601String(),
                'body' => Str::limit($comment->body, 120),
                'url' => route('epics.index', ['search' => $comment->epic->name]),
            ];
        }

        return $rows;
    }

    /**
     * The comments written on epics of the projects the user reaches.
     *
     * @return Builder<EpicComment>
     */
    private function visibleComments(): Builder
    {
        return EpicComment::query()->whereIn(
            'epic_id',
            (new ProjectVisibility)->restrict(Epic::withTrashed(), 'project_id')->select('id'),
        );
    }
}
