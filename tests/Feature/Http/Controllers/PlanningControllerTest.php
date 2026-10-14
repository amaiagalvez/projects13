<?php

namespace Projects13\Tests\Feature\Http\Controllers;

use Illuminate\View\View;
use Projects13\Tests\TestCase;
use Projects13\Queries\Planning\PlanningQuery;
use Projects13\Contracts\NullProjectIntegration;
use Projects13\Http\Requests\PlanningListRequest;
use Projects13\Http\Controllers\PlanningController;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class PlanningControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_view(): void
    {
        $controller = new PlanningController;
        $request = PlanningListRequest::create('/planning', 'GET');

        $result = $controller->index($request, new PlanningQuery(new NullProjectIntegration, new NullProjectIntegration));

        $this->assertInstanceOf(View::class, $result);
        $this->assertSame('projects13::planning.index', $result->name());
        $this->assertFalse($result->getData()['timeline']);
        $this->assertSame('', $result->getData()['search']['value']);
        $this->assertSame([], $result->getData()['planning']['groups']);
    }
}
