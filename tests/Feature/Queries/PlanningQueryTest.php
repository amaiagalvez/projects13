<?php

namespace Projects13\Tests\Feature\Queries;

use ReflectionMethod;
use Carbon\CarbonImmutable;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Queries\Planning\PlanningQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

/**
 * The planning board reads its own tables and never counts a state tab, so the base hook that names
 * the model never comes up on its own path. It is still its contract with the base.
 */
class PlanningQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A page whose id no longer resolves to a project (the record was trashed between the id read
     * and the row read) is skipped instead of failing the whole board.
     */
    public function test_a_page_id_without_a_project_is_skipped(): void
    {
        $method = new ReflectionMethod(PlanningQuery::class, 'paginateRows');

        $paginator = $method->invoke($this->planningQuery(), [999999], '', CarbonImmutable::parse('2026-10-10'), 1);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame([], $paginator->items());
    }

    private function planningQuery(): PlanningQuery
    {
        return new PlanningQuery($this->nullQueryIntegration(), $this->nullDisplayIntegration());
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
