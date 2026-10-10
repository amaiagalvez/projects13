<?php

namespace Projects13\Tests\Unit\Queries;

use Projects13\Tests\TestCase;
use Projects13\Queries\Projects\ProjectListQuery;

class ProjectListQueryTest extends TestCase
{
    private ProjectListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new ProjectListQuery;
    }

    public function test_search_columns_constant_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasConstant('SEARCH_COLUMNS'));
        $columns = $reflection->getConstant('SEARCH_COLUMNS');
        $this->assertIsArray($columns);
        $this->assertContains('PRO_projects.name', $columns);
        $this->assertContains('project_customers.name', $columns);
        $this->assertContains('PRO_projects.start_date', $columns);
        $this->assertContains('PRO_projects.end_date', $columns);
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

    public function test_with_active_counts_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withActiveCounts'));
        $this->assertTrue($reflection->getMethod('withActiveCounts')->isPrivate());
    }

    public function test_with_customer_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withCustomer'));
        $this->assertTrue($reflection->getMethod('withCustomer')->isPrivate());
    }
}
