<?php

namespace Projects13\Tests\Unit\Queries;

use ReflectionMethod;
use Carbon\CarbonImmutable;
use Projects13\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Projects13\Queries\Planning\PlanningQuery;
use Projects13\Queries\Timeline\TimelineQuery;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

/**
 * The date arithmetic the planning and timeline boards share, without the rows that feed it.
 * Both boards carry their own copy, so every case runs against each of them.
 */
class BoardRowCalculationsTest extends TestCase
{
    private const TODAY = '2026-10-06';

    /**
     * @return array<string, array{class-string<PlanningQuery|TimelineQuery>}>
     */
    public static function boards(): array
    {
        return [
            'planning' => [PlanningQuery::class],
            'timeline' => [TimelineQuery::class],
        ];
    }

    /**
     * @return array<string, array{class-string<PlanningQuery|TimelineQuery>, string|null, string}>
     */
    public static function statuses(): array
    {
        $cases = [];

        foreach (self::boards() as $board => [$class]) {
            $cases["{$board} without end date"] = [$class, null, 'unscheduled'];
            $cases["{$board} ended yesterday"] = [$class, '2026-10-05', 'overdue'];
            $cases["{$board} ends today"] = [$class, self::TODAY, 'ontrack'];
            $cases["{$board} ends later"] = [$class, '2027-01-01', 'ontrack'];
        }

        return $cases;
    }

    /**
     * @param  class-string<PlanningQuery|TimelineQuery>  $board
     */
    #[DataProvider('statuses')]
    public function test_status_compares_the_end_date_with_today(string $board, ?string $end, string $expected): void
    {
        $status = $this->invokeBoard($board, 'status', $end === null ? null : CarbonImmutable::parse($end), CarbonImmutable::parse(self::TODAY));

        $this->assertSame($expected, $status);
    }

    /**
     * @return array<string, array{class-string<PlanningQuery|TimelineQuery>, string|null, string|null, array{offset: float, width: float}|null}>
     */
    public static function bars(): array
    {
        $cases = [];

        foreach (self::boards() as $board => [$class]) {
            $cases["{$board} range from the window start"] = [$class, '2026-10-01', '2026-10-10', ['offset' => 0.0, 'width' => 32.2581]];
            $cases["{$board} start-only milestone"] = [$class, '2026-10-11', null, ['offset' => 32.2581, 'width' => 3.2258]];
            $cases["{$board} no dates"] = [$class, null, null, null];
        }

        return $cases;
    }

    /**
     * @param  class-string<PlanningQuery|TimelineQuery>  $board
     * @param  array{offset: float, width: float}|null  $expected
     */
    #[DataProvider('bars')]
    public function test_bar_is_placed_as_a_share_of_the_window(string $board, ?string $start, ?string $end, ?array $expected): void
    {
        $row = [
            'start' => $start === null ? null : CarbonImmutable::parse($start),
            'end' => $end === null ? null : CarbonImmutable::parse($end),
        ];

        $this->assertSame($expected, $this->invokeBoard($board, 'bar', $row, $this->octoberWindow()));
    }

    public function test_planning_groups_rows_by_the_quarter_they_end_in_with_undated_rows_last(): void
    {
        app()->setLocale('en');
        $rows = [
            ['name' => 'Q2', 'end' => CarbonImmutable::parse('2026-06-01')],
            ['name' => 'No date', 'end' => null],
            ['name' => 'Next year', 'end' => CarbonImmutable::parse('2027-02-01')],
            ['name' => 'Q1', 'end' => CarbonImmutable::parse('2026-03-01')],
            ['name' => 'Q2 again', 'end' => CarbonImmutable::parse('2026-04-15')],
        ];

        /** @var list<array{key: string, label: string, count: int, rows: list<array{name: string}>}> $groups */
        $groups = $this->invokeBoard(PlanningQuery::class, 'groups', $rows);

        $this->assertSame(['2026-1', '2026-2', '2027-1', 'no-date'], array_column($groups, 'key'));
        $this->assertSame(['Quarter 1, 2026', 'Quarter 2, 2026', 'Quarter 1, 2027', 'No date'], array_column($groups, 'label'));
        $this->assertSame([1, 2, 1, 1], array_column($groups, 'count'));
        $this->assertSame(['Q2', 'Q2 again'], array_column($groups[1]['rows'], 'name'));
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, days: int, today: float, months: list<array{label: string, width: float}>}
     */
    private function octoberWindow(): array
    {
        return [
            'from' => CarbonImmutable::parse('2026-10-01'),
            'to' => CarbonImmutable::parse('2026-10-31')->endOfDay(),
            'days' => 31,
            'today' => 16.129,
            'months' => [['label' => '2026-10', 'width' => 100.0]],
        ];
    }

    /**
     * @param  class-string<PlanningQuery|TimelineQuery>  $board
     */
    private function invokeBoard(string $board, string $method, mixed ...$arguments): mixed
    {
        $query = new $board($this->nullQueryIntegration(), $this->nullDisplayIntegration());

        return (new ReflectionMethod($query, $method))->invoke($query, ...$arguments);
    }

    private function nullQueryIntegration(): ProjectQueryIntegration
    {
        return new class implements ProjectQueryIntegration
        {
            use ProjectQueryIntegrationDefaults;
        };
    }

    private function nullDisplayIntegration(): ProjectDisplayIntegration
    {
        return new class implements ProjectDisplayIntegration
        {
            use ProjectDisplayIntegrationDefaults;
        };
    }
}
