<?php

namespace Projects13\Queries\Calendar;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Basics13\Models\PersonalEvent;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Queries\ProjectVisibility;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The calendar screen: the dates of the active projects and epics turned into events in memory,
 * next to the signed-in user's own events. Nothing is written: projects and epics keep no calendar
 * rows, and a record with a single date becomes a one-day event. Each domain calendar only draws
 * what the user may list (`viewAny` on its policy) and only the projects they reach.
 *
 * @phpstan-type Event array{
 *     key: string,
 *     calendar: string,
 *     title: string,
 *     start: CarbonImmutable,
 *     end: CarbonImmutable,
 *     url: string|null,
 *     personal: array{id: int, title: string, notes: string, start_date: string, end_date: string}|null
 * }
 * @phpstan-type Period array{from: CarbonImmutable, to: CarbonImmutable}
 */
final class CalendarQuery
{
    /**
     * The days a view covers: a calendar month, a week from Monday to Sunday, one day or a year.
     *
     * @return Period
     */
    public function period(string $view, CarbonImmutable $day): array
    {
        return match ($view) {
            'week' => ['from' => $day->startOfWeek(CarbonImmutable::MONDAY), 'to' => $day->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()],
            'day' => ['from' => $day->startOfDay(), 'to' => $day->startOfDay()],
            'year' => ['from' => $day->startOfYear(), 'to' => $day->endOfYear()->startOfDay()],
            default => ['from' => $day->startOfMonth(), 'to' => $day->endOfMonth()->startOfDay()],
        };
    }

    /**
     * Every event of the visible calendars that touches the period, its last day included, in date
     * order.
     *
     * @param  list<string>  $calendars
     * @return list<Event>
     */
    public function events(Authenticatable $user, CarbonImmutable $from, CarbonImmutable $to, array $calendars): array
    {
        $events = [];

        if (in_array('projects', $calendars, true) && $user->can('viewAny', Project::class)) {
            $events = [...$events, ...$this->projectEvents($from, $to)];
        }

        if (in_array('epics', $calendars, true) && $user->can('viewAny', Epic::class)) {
            $events = [...$events, ...$this->epicEvents($from, $to)];
        }

        if (in_array('personal', $calendars, true)) {
            $events = [...$events, ...$this->personalEvents($user, $from, $to)];
        }

        usort($events, static fn (array $left, array $right): int => [$left['start'], $left['end'], $left['title']]
            <=> [$right['start'], $right['end'], $right['title']]);

        return $events;
    }

    /**
     * The events that touch one day.
     *
     * @param  list<Event>  $events
     * @return list<Event>
     */
    public function on(array $events, CarbonImmutable $day): array
    {
        return array_values(array_filter(
            $events,
            static fn (array $event): bool => $event['start']->lte($day) && $event['end']->gte($day),
        ));
    }

    /**
     * @return list<Event>
     */
    private function projectEvents(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = $this->dated(Project::query()->where('PRO_projects.active', true), 'PRO_projects', $from, $to);
        (new ProjectVisibility)->restrict($query, 'PRO_projects.id');

        return array_values($query->get()->map(fn (Project $project): array => $this->domainEvent(
            'projects', 'project-'.$project->id, $project->name, $project->start_date, $project->end_date,
            route('projects.index', ['search' => $project->name]),
        ))->all());
    }

    /**
     * Epics of active projects only: an archived project hides its epics even when they are active.
     *
     * @return list<Event>
     */
    private function epicEvents(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = $this->dated(Epic::query()->where('PRO_epics.active', true), 'PRO_epics', $from, $to)
            ->whereHas('project', static fn (Builder $project): Builder => $project->where('active', true));
        (new ProjectVisibility)->restrict($query, 'PRO_epics.project_id');

        return array_values($query->get()->map(fn (Epic $epic): array => $this->domainEvent(
            'epics', 'epic-'.$epic->id, $epic->name, $epic->start_date, $epic->end_date,
            route('epics.index', ['search' => $epic->name]),
        ))->all());
    }

    /**
     * Only the signed-in user's events, whatever the URL says.
     *
     * @return list<Event>
     */
    private function personalEvents(Authenticatable $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(PersonalEvent::query()
            ->ownedWithin($user->getAuthIdentifier(), $from, $to)
            ->get()
            ->map(static fn (PersonalEvent $event): array => [
                'key' => 'personal-'.$event->id,
                'calendar' => 'personal',
                'title' => $event->title,
                'start' => $event->start_date,
                'end' => $event->lastDay(),
                'url' => null,
                'personal' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'notes' => $event->notes ?? '',
                    'start_date' => $event->start_date->toDateString(),
                    'end_date' => $event->end_date?->toDateString() ?? '',
                ],
            ])
            ->all());
    }

    /**
     * Records with at least one date whose days touch the period. A single date stands for both
     * ends, and the upper bound is "before the next day" so a date with a midnight time still
     * matches on engines that keep one.
     *
     * @template TModel of Project|Epic
     *
     * @param  Builder<TModel>  $query
     * @param  'PRO_projects'|'PRO_epics'  $table
     * @return Builder<TModel>
     */
    private function dated(Builder $query, string $table, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query
            ->where(static fn (Builder $query): Builder => $query
                ->whereNotNull($table.'.start_date')
                ->orWhereNotNull($table.'.end_date'))
            ->whereRaw("COALESCE({$table}.start_date, {$table}.end_date) < ?", [$to->addDay()->toDateString()])
            ->whereRaw("COALESCE({$table}.end_date, {$table}.start_date) >= ?", [$from->toDateString()]);
    }

    /**
     * @return Event
     */
    private function domainEvent(string $calendar, string $key, string $title, ?CarbonImmutable $start, ?CarbonImmutable $end, string $url): array
    {
        // dated() leaves out records with no date, so at least one side is set here.
        $first = $start ?? $end ?? CarbonImmutable::today();

        return [
            'key' => $key,
            'calendar' => $calendar,
            'title' => $title,
            'start' => $first,
            'end' => $end ?? $first,
            'url' => $url,
            'personal' => null,
        ];
    }
}
