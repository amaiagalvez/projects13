<?php

namespace Projects13\Tests\Feature\Http\Controllers;

use Illuminate\View\View;
use Projects13\Tests\TestCase;
use Projects13\Queries\Planning\PlanningQuery;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Http\Requests\PlanningListRequest;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Http\Controllers\PlanningController;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

class PlanningControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_view(): void
    {
        $controller = new PlanningController;
        $request = PlanningListRequest::create('/planning', 'GET');

        $result = $controller->index($request, new PlanningQuery($this->nullQueryIntegration(), $this->nullDisplayIntegration()));

        $this->assertInstanceOf(View::class, $result);
        $this->assertSame('projects13::planning.index', $result->name());
        $this->assertFalse($result->getData()['timeline']);
        $this->assertSame('', $result->getData()['search']['value']);
        $this->assertSame([], $result->getData()['planning']['groups']);
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
