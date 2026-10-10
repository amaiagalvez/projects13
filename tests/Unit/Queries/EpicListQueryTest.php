<?php

namespace Projects13\Tests\Unit\Queries;

use Projects13\Tests\TestCase;
use Projects13\Queries\Epics\EpicListQuery;

class EpicListQueryTest extends TestCase
{
    private EpicListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new EpicListQuery;
    }

    public function test_recent_comments_limit_constant_is_20(): void
    {
        $this->assertSame(20, EpicListQuery::RECENT_COMMENTS_LIMIT);
    }

    public function test_search_columns_constant_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasConstant('SEARCH_COLUMNS'));
        $columns = $reflection->getConstant('SEARCH_COLUMNS');
        $this->assertIsArray($columns);
        $this->assertContains('PRO_epics.name', $columns);
        $this->assertContains('epic_projects.name', $columns);
        $this->assertContains('epic_customers.name', $columns);
        $this->assertContains('PRO_epics.start_date', $columns);
        $this->assertContains('PRO_epics.end_date', $columns);
    }

    public function test_active_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('active'));
    }

    public function test_archived_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('archived'));
    }

    public function test_trashed_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('trashed'));
    }

    public function test_find_trashed_by_name_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('findTrashedByName'));
    }

    public function test_state_counts_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('stateCounts'));
    }

    public function test_state_counts_returns_array_with_expected_keys(): void
    {
        $result = $this->query->stateCounts(10, 5, 3);

        $this->assertSame(
            ['active' => 10, 'archived' => 5, 'trashed' => 3],
            $result,
        );
    }

    public function test_find_trashed_by_name_signature_accepts_project_id(): void
    {
        $reflection = new \ReflectionMethod($this->query, 'findTrashedByName');
        $params = $reflection->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('name', $params[0]->getName());
        $this->assertSame('projectId', $params[1]->getName());
    }

    public function test_with_project_and_customer_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withProjectAndCustomer'));
        $this->assertTrue($reflection->getMethod('withProjectAndCustomer')->isPrivate());
    }
}
