<?php

namespace Projects13\Tests\Unit\Queries;

use ReflectionMethod;
use Projects13\Tests\TestCase;
use Projects13\Queries\Members\MemberCandidateQuery;

/**
 * The candidate search runs on its own path, so the hooks the base declares never come up there.
 * They are still its contract: the configured users model, no integration and no advanced filters.
 */
class MemberCandidateQueryTest extends TestCase
{
    public function test_the_query_answers_the_base_with_the_configured_users_model(): void
    {
        $method = new ReflectionMethod(MemberCandidateQuery::class, 'getModelClass');

        $this->assertSame(config('auth.providers.users.model'), $method->invoke(new MemberCandidateQuery));
    }

    public function test_the_integration_appends_no_search_column(): void
    {
        $method = new ReflectionMethod(MemberCandidateQuery::class, 'integration');

        $this->assertSame(['name'], $method->invoke(new MemberCandidateQuery)->searchColumns('active', ['name']));
    }

    public function test_there_are_no_advanced_filters(): void
    {
        $this->assertSame([], (new MemberCandidateQuery)->searchableFields('active'));
    }
}
