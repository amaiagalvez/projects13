<?php

namespace Projects13\Tests\Unit\Queries;

use ReflectionMethod;
use Carbon\CarbonImmutable;
use Projects13\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Projects13\Queries\Planning\PlanningQuery;
use Projects13\Contracts\NullProjectIntegration;

class PlanningBarTest extends TestCase
{
    /**
     * These defensive inputs cannot reach overview(): projects.start_date is NOT NULL.
     *
     * @return array<string, array{0: string|null, 1: array{offset: float, width: float}|null}>
     */
    public static function missingStartDates(): array
    {
        return [
            'end-only milestone' => ['2026-10-11', ['offset' => 32.2581, 'width' => 3.2258]],
            'no dates' => [null, null],
        ];
    }

    /**
     * @param  array{offset: float, width: float}|null  $expected
     */
    #[DataProvider('missingStartDates')]
    public function test_missing_start_date_produces_milestone_or_no_bar(?string $end, ?array $expected): void
    {
        $row = [
            'test' => 'planning-project-1',
            'name' => 'Defensive date input',
            'trail' => ['Customer'],
            'url' => '/projects',
            'start' => null,
            'end' => $end === null ? null : CarbonImmutable::parse($end),
            'status' => $end === null ? 'unscheduled' : 'ontrack',
            'count' => ['icon' => 'flag', 'value' => 0, 'label' => 'Epics: 0', 'url' => null],
            'bar' => null,
        ];
        $timeline = [
            'from' => CarbonImmutable::parse('2026-10-01'),
            'to' => CarbonImmutable::parse('2026-10-31')->endOfDay(),
            'days' => 31,
            'today' => 32.2581,
            'months' => [['label' => '2026-10', 'width' => 100.0]],
        ];

        $bar = (new ReflectionMethod(PlanningQuery::class, 'bar'))->invoke(
            new PlanningQuery(new NullProjectIntegration, new NullProjectIntegration),
            $row,
            $timeline
        );

        $this->assertSame($expected, $bar);
    }
}
