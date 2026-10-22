<?php

namespace Projects13\Tests\Unit\Queries;

use ReflectionMethod;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Queries\Timeline\TimelineQuery;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

/**
 * The timeline reads its own tables and never counts a state tab, so the base hook that names the
 * model never comes up on its own path. It is still its contract with the base.
 */
class TimelineQueryTest extends TestCase
{
    public function test_the_query_answers_the_base_with_the_project_model(): void
    {
        $method = new ReflectionMethod(TimelineQuery::class, 'getModelClass');

        $this->assertSame(Project::class, $method->invoke($this->timelineQuery()));
    }

    public function test_the_board_offers_no_advanced_filters(): void
    {
        $this->assertSame([], $this->timelineQuery()->searchableFields('active'));
    }

    /**
     * An id the id query returns but the row query cannot load - a project trashed between the two
     * reads - is skipped instead of failing the whole board. The integration is what keeps the
     * trashed id in the id query, so the gap is reached deterministically.
     */
    public function test_a_page_id_without_a_loadable_project_is_skipped(): void
    {
        Project::factory()->create(['name' => 'Visible project']);
        Project::factory()->create(['name' => 'Trashed project'])->delete();

        $overview = $this->timelineQuery()->overview('');

        $names = $overview['entries']->map(static fn (array $entry): string => $entry['row']['name'])->all();

        $this->assertContains('Visible project', $names);
        $this->assertNotContains('Trashed project', $names);
    }

    private function timelineQuery(): TimelineQuery
    {
        return new TimelineQuery($this->nullQueryIntegration(), $this->nullDisplayIntegration());
    }

    /**
     * The trashed project stays in the id query, so the row query is the one that drops it.
     */
    private function nullQueryIntegration(): ProjectQueryIntegration
    {
        return new class implements ProjectQueryIntegration
        {
            use ProjectQueryIntegrationDefaults;

            public function scope(Builder $query, string $scope, string $alias): void
            {
                $query->withoutGlobalScope(SoftDeletingScope::class);
            }

            /**
             * @param  list<string>  $columns
             * @return list<string>
             */
            public function searchColumns(string $scope, array $columns): array
            {
                return $columns;
            }

            /**
             * @return list<string>
             */
            public function orderings(string $scope, string $alias): array
            {
                return [];
            }
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
