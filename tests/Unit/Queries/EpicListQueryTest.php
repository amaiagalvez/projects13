<?php

namespace Projects13\Tests\Unit\Queries;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Queries\Epics\EpicListQuery;
use Projects13\Contracts\ProjectIntegration;
use Projects13\Http\Requests\ProjectRequest;
use Projects13\Contracts\NullProjectIntegration;

class EpicListQueryTest extends TestCase
{
    private EpicListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new EpicListQuery(new NullProjectIntegration);
    }

    public function test_recent_comments_limit_constant_is_20(): void
    {
        $this->assertSame(20, EpicListQuery::RECENT_COMMENTS_LIMIT);
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

    public function test_with_project_method_exists(): void
    {
        $reflection = new \ReflectionClass($this->query);
        $this->assertTrue($reflection->hasMethod('withProject'));
        $this->assertTrue($reflection->getMethod('withProject')->isPrivate());
    }

    /**
     * The base lists search the epic's own columns and its project's name, and nothing else: any
     * other column only reaches a search because an integration contributed its join and then asked
     * for the column. The scope and the alias are what tell an integration which query is asking.
     */
    /**
     * The base lists search their own columns and nothing else: any other column only reaches a
     * search because an integration contributed its join and then asked for the column. The scope
     * and the alias are what tell an integration which query is asking.
     *
     * The helpers are called directly rather than through `active()` so the assertion is about the
     * seam and does not need a database.
     */
    public function test_the_integration_is_asked_for_the_epic_scope_and_the_epic_alias(): void
    {
        $integration = new RecordingProjectIntegration;
        $query = new EpicListQuery($integration);

        (new \ReflectionMethod($query, 'scoped'))->invoke($query, $this->queryWithoutRunningIt());

        $this->assertSame(['epic'], $integration->scopes);
        $this->assertSame(['epic_customers'], $integration->aliases);
    }

    public function test_the_epic_search_asks_the_integration_after_its_own_columns(): void
    {
        $integration = new RecordingProjectIntegration;
        $query = new EpicListQuery($integration);
        $base = ['PRO_epics.name'];

        $columns = (new \ReflectionMethod($query, 'searchColumns'))->invoke($query, $base);

        $this->assertSame(['epic'], $integration->searchScopes);
        $this->assertSame($base, $columns);
    }

    /**
     * Loading the customer of every project on the page was the other half of what made this query
     * know about customers; nothing in the base can use it any more.
     */
    public function test_the_customer_join_is_gone_from_the_base_query(): void
    {
        $reflection = new \ReflectionClass($this->query);

        $this->assertFalse($reflection->hasMethod('withProjectAndCustomer'));
    }

    /**
     * A builder the query never runs: these tests assert on what the query asked for, not on the
     * rows it would have returned.
     *
     * @return Builder<Epic>
     */
    private function queryWithoutRunningIt(): Builder
    {
        return Epic::query();
    }
}

/**
 * Records what the query asked of it instead of changing anything, so the test can assert on the
 * seam rather than on the SQL that ends up behind it.
 */
final class RecordingProjectIntegration implements ProjectIntegration
{
    /** @var list<string> */
    public array $scopes = [];

    /** @var list<string> */
    public array $aliases = [];

    /** @var list<string> */
    public array $searchScopes = [];

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

    /** @return array<string, array<int, mixed>> */
    public function rules(ProjectRequest $request): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function saving(ProjectRequest $request, ?Project $project): array
    {
        return [];
    }

    public function label(Project $project): string
    {
        return $project->name;
    }

    /** @return list<string> */
    public function trail(Project $project): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function row(Model $record): array
    {
        return [];
    }

    public function hasActiveRelatedEntities(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function orderings(string $scope, string $alias): array
    {
        return [];
    }

    public function noRelatedEntitiesBanner(): ?array
    {
        return null;
    }
}
