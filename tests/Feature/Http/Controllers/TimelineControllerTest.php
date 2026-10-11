<?php

namespace Projects13\Tests\Feature\Http\Controllers;

use Illuminate\View\View;
use Projects13\Tests\TestCase;
use Projects13\Queries\Timeline\TimelineQuery;
use Projects13\Contracts\NullProjectIntegration;
use Projects13\Http\Requests\TimelineListRequest;
use Projects13\Http\Controllers\TimelineController;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class TimelineControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_view(): void
    {
        $controller = new TimelineController;
        $request = TimelineListRequest::create('/timeline', 'GET');

        $result = $controller->index($request, new TimelineQuery(new NullProjectIntegration));

        $this->assertInstanceOf(View::class, $result);
        $this->assertSame('projects13::timeline.index', $result->name());
        $this->assertSame('', $result->getData()['search']['value']);
        $this->assertTrue($result->getData()['timeline']['entries']->isEmpty());
    }
}
