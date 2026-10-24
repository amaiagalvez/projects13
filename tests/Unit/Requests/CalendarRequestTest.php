<?php

namespace Projects13\Tests\Unit\Requests;

use Projects13\Tests\TestCase;
use Projects13\Http\Requests\CalendarRequest;

class CalendarRequestTest extends TestCase
{
    public function test_without_parameters_every_calendar_shows(): void
    {
        $this->assertSame(CalendarRequest::CALENDARS, (new CalendarRequest)->calendars());
    }

    public function test_the_visible_calendars_are_read_from_the_url_and_unknown_ones_dropped(): void
    {
        $this->assertSame(['epics', 'personal'], (new CalendarRequest(['calendars' => 'personal,bogus,epics']))->calendars());
        $this->assertSame([], (new CalendarRequest(['calendars' => 'none']))->calendars());
    }

    public function test_an_unknown_view_falls_back_to_the_month(): void
    {
        $this->assertSame('month', (new CalendarRequest(['view' => 'decade']))->view());
        $this->assertSame('year', (new CalendarRequest(['view' => 'year']))->view());
    }
}
