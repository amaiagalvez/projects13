<?php

namespace Projects13\Tests\Unit\Queries;

use ReflectionMethod;
use Projects13\Models\Project;
use PHPUnit\Framework\TestCase;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Queries\Projects\ProjectSelectOptionsQuery;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

/**
 * The select-options query searches by itself and never counts a state tab, so the two hooks the
 * base declares never come up on its own path. They are still its contract: the base asks this
 * query for its model and for its integration, and both answers have to be the ones the query was
 * built with.
 */
class ProjectSelectOptionsQueryTest extends TestCase
{
    public function test_the_query_answers_the_base_with_the_project_model(): void
    {
        $method = new ReflectionMethod(ProjectSelectOptionsQuery::class, 'getModelClass');

        $this->assertSame(
            Project::class,
            $method->invoke($this->query()),
        );
    }

    public function test_the_query_answers_the_base_with_the_integration_it_was_given(): void
    {
        $integration = new class implements ProjectQueryIntegration
        {
            use ProjectQueryIntegrationDefaults;
        };
        $method = new ReflectionMethod(ProjectSelectOptionsQuery::class, 'integration');

        $this->assertSame($integration, $method->invoke($this->query($integration)));
    }

    private function query(?ProjectQueryIntegration $queryIntegration = null): ProjectSelectOptionsQuery
    {
        $displayIntegration = new class implements ProjectDisplayIntegration
        {
            use ProjectDisplayIntegrationDefaults;
        };

        return new ProjectSelectOptionsQuery(
            $queryIntegration ?? new class implements ProjectQueryIntegration
            {
                use ProjectQueryIntegrationDefaults;
            },
            $displayIntegration,
        );
    }
}
