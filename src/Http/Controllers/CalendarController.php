<?php

namespace Projects13\Http\Controllers;

use Illuminate\View\View;
use Carbon\CarbonImmutable;
use Basics13\Http\Controllers\Controller;
use Projects13\Http\Requests\CalendarRequest;
use Projects13\Queries\Calendar\CalendarQuery;

/**
 * @phpstan-import-type Event from CalendarQuery
 */
class CalendarController extends Controller
{
    /**
     * How many events a day cell of the month and the week, and a month of the year, draw before
     * the rest collapse into a "+N more" link to the view that lists them all.
     *
     * @var array<string, int>
     */
    private const CELL_LIMITS = ['month' => 3, 'week' => 6, 'year' => 5];

    /**
     * The dates of projects and epics and the user's own events on one calendar. The view, the day
     * and the visible calendars all live in the URL, so a reload or a shared link shows the same.
     */
    public function index(CalendarRequest $request, CalendarQuery $query): View
    {
        $view = $request->view();
        $day = $request->day();
        $calendars = $request->calendars();
        $period = $query->period($view, $day);
        // Every calendar is read so the side panel can count the hidden ones too; only the visible
        // ones are drawn.
        $all = $query->events($request->user(), $period['from'], $period['to'], CalendarRequest::CALENDARS);
        $events = array_values(array_filter($all, static fn (array $event): bool => in_array($event['calendar'], $calendars, true)));
        $mini = $query->period('month', $day);
        $miniEvents = $view === 'month'
            ? $events
            : $query->events($request->user(), $mini['from'], $mini['to'], $calendars);

        return view('projects13::calendars.index', [
            'view' => $view,
            'day' => $day,
            'period' => $period,
            'events' => $events,
            'days' => $view === 'year' ? [] : $this->days($query, $events, $period['from'], $period['to']),
            'months' => $view === 'year' ? $this->months($query, $events, $day) : [],
            'title' => $this->title($view, $day, $period['from'], $period['to']),
            'views' => array_map(fn (string $mode): array => [
                'label' => __(ucfirst($mode)),
                'url' => $this->url($mode, $day, $calendars),
                'current' => $mode === $view,
                'count' => null,
                'test' => 'calendar-'.$mode.'-link',
            ], CalendarRequest::VIEWS),
            'limit' => self::CELL_LIMITS[$view] ?? null,
            'dayUrl' => fn (CarbonImmutable $date): string => $this->url('day', $date, $calendars),
            'monthUrl' => fn (CarbonImmutable $date): string => $this->url('month', $date, $calendars),
            'miniMonth' => $this->days($query, $miniEvents, $mini['from'], $mini['to']),
            'toggles' => array_map(fn (string $calendar): array => [
                'calendar' => $calendar,
                'count' => count(array_filter($all, static fn (array $event): bool => $event['calendar'] === $calendar)),
                'visible' => in_array($calendar, $calendars, true),
                'url' => $this->url($view, $day, in_array($calendar, $calendars, true)
                    ? array_values(array_diff($calendars, [$calendar]))
                    : array_values(array_intersect(CalendarRequest::CALENDARS, [...$calendars, $calendar]))),
            ], CalendarRequest::CALENDARS),
            'navigation' => [
                'previous' => $this->url($view, $this->step($view, $day, -1), $calendars),
                'next' => $this->url($view, $this->step($view, $day, 1), $calendars),
                'today' => $this->url($view, null, $calendars),
            ],
            'personalCalendarVisible' => in_array('personal', $calendars, true),
        ]);
    }

    /**
     * The URL of one state of the screen. Defaults are left out, so the bare route is the month of
     * today with every calendar showing.
     *
     * @param  list<string>  $calendars
     */
    private function url(string $view, ?CarbonImmutable $day, array $calendars): string
    {
        return route('calendar', array_filter([
            'view' => $view === CalendarRequest::VIEWS[0] ? null : $view,
            'date' => $day?->toDateString(),
            'calendars' => match (true) {
                $calendars === CalendarRequest::CALENDARS => null,
                $calendars === [] => 'none',
                default => implode(',', $calendars),
            },
        ]));
    }

    private function step(string $view, CarbonImmutable $day, int $direction): CarbonImmutable
    {
        return match ($view) {
            'week' => $day->addWeeks($direction),
            'day' => $day->addDays($direction),
            'year' => $day->addYearsNoOverflow($direction),
            default => $day->addMonthsNoOverflow($direction),
        };
    }

    private function title(string $view, CarbonImmutable $day, CarbonImmutable $from, CarbonImmutable $to): string
    {
        return match ($view) {
            'week' => $from->isoFormat('LL').' – '.$to->isoFormat('LL'),
            'day' => $day->isoFormat('dddd, LL'),
            'year' => (string) $day->year,
            default => $day->isoFormat('MMMM YYYY'),
        };
    }

    /**
     * The days of the period, each one with the events that touch it.
     *
     * @param  list<Event>  $events
     * @return list<array{date: CarbonImmutable, events: list<Event>}>
     */
    private function days(CalendarQuery $query, array $events, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $days[] = ['date' => $date, 'events' => $query->on($events, $date)];
        }

        return $days;
    }

    /**
     * The twelve months of the year, each one with the events that touch it, listed once.
     *
     * @param  list<Event>  $events
     * @return list<array{date: CarbonImmutable, events: list<Event>}>
     */
    private function months(CalendarQuery $query, array $events, CarbonImmutable $day): array
    {
        $months = [];

        for ($month = $day->startOfYear(); $month->year === $day->year; $month = $month->addMonth()) {
            $period = $query->period('month', $month);
            $months[] = [
                'date' => $month,
                'events' => array_values(array_filter(
                    $events,
                    static fn (array $event): bool => $event['start']->lte($period['to']) && $event['end']->gte($period['from']),
                )),
            ];
        }

        return $months;
    }
}
