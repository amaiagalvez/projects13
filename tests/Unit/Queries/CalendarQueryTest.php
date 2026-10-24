<?php

namespace Projects13\Tests\Unit\Queries;

use Carbon\CarbonImmutable;
use Projects13\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Projects13\Queries\Calendar\CalendarQuery;

class CalendarQueryTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function periods(): array
    {
        return [
            'leap february' => ['month', '2028-02-29', '2028-02-01', '2028-02-29'],
            'week across a year' => ['week', '2026-12-31', '2026-12-28', '2027-01-03'],
            'day' => ['day', '2026-06-12', '2026-06-12', '2026-06-12'],
            'year' => ['year', '2026-06-12', '2026-01-01', '2026-12-31'],
        ];
    }

    #[DataProvider('periods')]
    public function test_each_view_covers_the_days_it_names(string $view, string $day, string $from, string $to): void
    {
        $period = (new CalendarQuery)->period($view, CarbonImmutable::parse($day));

        $this->assertSame([$from, $to], [$period['from']->toDateString(), $period['to']->toDateString()]);
    }

    public function test_an_event_touches_every_day_from_its_start_to_its_end_both_included(): void
    {
        $event = [
            'key' => 'personal-1', 'calendar' => 'personal', 'title' => 'Viaje', 'url' => null, 'personal' => null,
            'start' => CarbonImmutable::parse('2026-06-13'), 'end' => CarbonImmutable::parse('2026-06-15'),
        ];
        $query = new CalendarQuery;

        $this->assertSame([], $query->on([$event], CarbonImmutable::parse('2026-06-12')));
        $this->assertSame([$event], $query->on([$event], CarbonImmutable::parse('2026-06-13')));
        $this->assertSame([$event], $query->on([$event], CarbonImmutable::parse('2026-06-15')));
        $this->assertSame([], $query->on([$event], CarbonImmutable::parse('2026-06-16')));
    }
}
