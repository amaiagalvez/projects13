<?php

namespace Projects13\Tests\Feature\Queries;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Support\Collection;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;
use Projects13\Queries\Projects\ProjectSelectOptionsQuery;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

class ProjectSelectOptionsQueryBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function test_search_returns_active_projects_with_id_and_text(): void
    {
        $query = $this->selectOptionsQuery();
        Project::factory()->count(3)->create(['active' => true]);
        Project::factory()->archived()->create();

        $result = $query->search('');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        foreach ($result as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('text', $item);
        }
    }

    public function test_search_excludes_archived_projects(): void
    {
        $query = $this->selectOptionsQuery();
        Project::factory()->create(['name' => 'Active project', 'active' => true]);
        Project::factory()->archived()->create(['name' => 'Archived project']);

        $result = $query->search('');

        $this->assertCount(1, $result);
        $this->assertSame('Active project', $result->first()['text']);
    }

    public function test_search_excludes_trashed_projects(): void
    {
        $query = $this->selectOptionsQuery();
        Project::factory()->create(['name' => 'Active project', 'active' => true]);
        Project::factory()->trashed()->create(['name' => 'Trashed project']);

        $result = $query->search('');

        $this->assertCount(1, $result);
        $this->assertSame('Active project', $result->first()['text']);
    }

    public function test_search_filters_by_name(): void
    {
        $query = $this->selectOptionsQuery();
        Project::factory()->create(['name' => 'Website project', 'active' => true]);
        Project::factory()->create(['name' => 'Mobile project', 'active' => true]);
        Project::factory()->create(['name' => 'Web API', 'active' => true]);

        $result = $query->search('Website');

        $this->assertCount(1, $result);
        $this->assertSame('Website project', $result->first()['text']);
    }

    public function test_search_uses_display_integration_for_label(): void
    {
        $displayIntegration = new class implements ProjectDisplayIntegration
        {
            use ProjectDisplayIntegrationDefaults;

            public function label(Project $project): string
            {
                return 'CUSTOM: '.$project->name;
            }
        };

        $query = new ProjectSelectOptionsQuery(
            new class implements ProjectQueryIntegration
            {
                use ProjectQueryIntegrationDefaults;
            },
            $displayIntegration
        );

        Project::factory()->create(['name' => 'Test project', 'active' => true]);

        $result = $query->search('');

        $this->assertCount(1, $result);
        $this->assertSame('CUSTOM: Test project', $result->first()['text']);
    }

    public function test_search_orders_by_name_then_id(): void
    {
        $query = $this->selectOptionsQuery();
        Project::factory()->create(['name' => 'C Project', 'active' => true]);
        Project::factory()->create(['name' => 'A Project', 'active' => true]);
        Project::factory()->create(['name' => 'B Project', 'active' => true]);

        $result = $query->search('');

        $names = $result->pluck('text')->all();
        $this->assertSame(['A Project', 'B Project', 'C Project'], $names);
    }

    public function test_search_limits_results_to_per_page(): void
    {
        $query = $this->selectOptionsQuery();
        foreach (range(1, 30) as $i) {
            Project::factory()->create(['name' => "Project $i", 'active' => true]);
        }

        $result = $query->search('');

        $this->assertCount(ProjectSelectOptionsQuery::PER_PAGE, $result);
    }

    private function selectOptionsQuery(): ProjectSelectOptionsQuery
    {
        return new ProjectSelectOptionsQuery(
            new class implements ProjectQueryIntegration
            {
                use ProjectQueryIntegrationDefaults;
            },
            new class implements ProjectDisplayIntegration
            {
                use ProjectDisplayIntegrationDefaults;
            }
        );
    }
}
