<?php

namespace Projects13\Tests\Unit\Queries;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Queries\Projects\ProjectListQuery;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;

class ProjectListQueryTest extends TestCase
{
    private ProjectListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new ProjectListQuery($this->nullQueryIntegration());
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

    /**
     * The base lists search the project's own columns and nothing else: any other column only
     * reaches a search because an integration contributed its join and then asked for the column.
     * The scope and the alias are what tell an integration which query is asking.
     */
    /**
     * The base lists search their own columns and nothing else: any other column only reaches a
     * search because an integration contributed its join and then asked for the column. The scope
     * and the alias are what tell an integration which query is asking.
     *
     * The helpers are called directly rather than through `active()` so the assertion is about the
     * seam and does not need a database.
     */
    public function test_the_integration_is_asked_for_the_project_scope_and_the_project_alias(): void
    {
        $integration = new RecordingProjectListQueryIntegration;
        $query = new ProjectListQuery($integration);

        (new \ReflectionMethod($query, 'scoped'))->invoke($query, $this->queryWithoutRunningIt());

        $this->assertSame(['project'], $integration->scopes);
        $this->assertSame(['project_customers'], $integration->aliases);
    }

    public function test_the_project_search_asks_the_integration_after_its_own_columns(): void
    {
        $integration = new RecordingProjectListQueryIntegration;
        $query = new ProjectListQuery($integration);
        $base = ['PRO_projects.name'];

        $columns = (new \ReflectionMethod($query, 'searchColumns'))->invoke($query, 'project', $base);

        $this->assertSame(['project'], $integration->searchScopes);
        $this->assertSame($base, $columns);
    }

    /**
     * An integration that contributes an ordering is asked for it under the same project scope and
     * alias as the search, and the column it names reaches the query after the base orderings.
     */
    public function test_an_ordering_contributed_by_the_integration_reaches_the_query(): void
    {
        $integration = new RecordingProjectListQueryIntegration(['project_customers.name']);
        $query = new ProjectListQuery($integration);
        $builder = $this->queryWithoutRunningIt();

        (new \ReflectionMethod($query, 'applyOrderings'))->invoke($query, $builder);

        $this->assertSame(['project'], $integration->orderingScopes);
        $this->assertSame(['project_customers'], $integration->orderingAliases);
        $this->assertContains('project_customers.name', array_column($builder->getQuery()->orders ?? [], 'column'));
    }

    /**
     * The join that used to live here was the only reason this query knew a customer, and nothing
     * in the base can use it any more.
     */
    public function test_the_customer_join_is_gone_from_the_base_query(): void
    {
        $reflection = new \ReflectionClass($this->query);

        $this->assertFalse($reflection->hasMethod('withCustomer'));
    }

    /**
     * A builder the query never runs: these tests assert on what the query asked for, not on the
     * rows it would have returned.
     *
     * @return Builder<Project>
     */
    private function queryWithoutRunningIt(): Builder
    {
        return Project::query();
    }

    private function nullQueryIntegration(): ProjectQueryIntegration
    {
        return new class implements ProjectQueryIntegration
        {
            use ProjectQueryIntegrationDefaults;
        };
    }
}

/**
 * Records what the query asked of it instead of changing anything, so the test can assert on the
 * seam rather than on the SQL that ends up behind it.
 */
final class RecordingProjectListQueryIntegration implements ProjectQueryIntegration
{
    /** @var list<string> */
    public array $scopes = [];

    /** @var list<string> */
    public array $aliases = [];

    /** @var list<string> */
    public array $searchScopes = [];

    /** @var list<string> */
    public array $orderingScopes = [];

    /** @var list<string> */
    public array $orderingAliases = [];

    /**
     * @param  list<string>  $orderings
     */
    public function __construct(private readonly array $orderings = []) {}

    public function scope(Builder $query, string $scope, string $alias): void
    {
        $this->scopes[] = $scope;
        $this->aliases[] = $alias;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function searchColumns(string $scope, array $columns): array
    {
        $this->searchScopes[] = $scope;

        return $columns;
    }

    /**
     * @return list<string>
     */
    public function orderings(string $scope, string $alias): array
    {
        $this->orderingScopes[] = $scope;
        $this->orderingAliases[] = $alias;

        return $this->orderings;
    }
}
