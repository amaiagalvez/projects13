<?php

namespace Projects13\Tests\Feature\Queries;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Queries\Projects\ProjectListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Contracts\ProjectQueryIntegrationDefaults;

class ProjectListQueryBehaviorTest extends TestCase
{
    use RefreshDatabase;

    private ProjectListQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
        $this->query = new ProjectListQuery($this->nullQueryIntegration());
    }

    public function test_active_returns_paginated_active_projects(): void
    {
        Project::factory()->count(3)->create(['active' => true]);
        Project::factory()->count(2)->archived()->create();
        Project::factory()->count(1)->trashed()->create();

        $result = $this->query->active('');

        $this->assertCount(3, $result->items());
        $this->assertSame(3, $result->total());
        foreach ($result->items() as $project) {
            $this->assertTrue($project->active);
        }
    }

    public function test_active_applies_search_filter(): void
    {
        Project::factory()->create(['name' => 'Website renewal', 'active' => true]);
        Project::factory()->create(['name' => 'Mobile app', 'active' => true]);
        Project::factory()->create(['name' => 'Website redesign', 'active' => true]);

        $result = $this->query->active('Website');

        $this->assertCount(2, $result->items());
        foreach ($result->items() as $project) {
            $this->assertStringContainsString('Website', $project->name);
        }
    }

    public function test_active_orders_by_start_date_then_end_date_then_name(): void
    {
        Project::factory()->create(['name' => 'Project C', 'start_date' => '2026-03-01', 'end_date' => '2026-06-01', 'active' => true]);
        Project::factory()->create(['name' => 'Project B2', 'start_date' => '2026-01-01', 'end_date' => '2026-06-01', 'active' => true]);
        Project::factory()->create(['name' => 'Project B', 'start_date' => '2026-01-01', 'end_date' => '2026-06-01', 'active' => true]);
        Project::factory()->create(['name' => 'Project A', 'start_date' => '2026-01-01', 'end_date' => '2026-03-01', 'active' => true]);

        $result = $this->query->active('');

        $names = array_map(fn ($p) => $p->name, $result->items());
        $this->assertSame(['Project A', 'Project B', 'Project B2', 'Project C'], $names);
    }

    public function test_active_includes_epics_exists_and_counts(): void
    {
        $projectWithEpics = Project::factory()->create(['name' => 'With Epics', 'active' => true]);
        $projectWithoutEpics = Project::factory()->create(['name' => 'Without Epics', 'active' => true]);
        Epic::factory()->for($projectWithEpics)->count(2)->create(['active' => true]);
        Epic::factory()->for($projectWithEpics)->count(1)->trashed()->create(); // trashed epics excluded
        Epic::factory()->for($projectWithEpics)->count(1)->create(['active' => false]); // archived epics excluded

        $result = $this->query->active('');

        $withEpics = $result->firstWhere('name', 'With Epics');
        $withoutEpics = $result->firstWhere('name', 'Without Epics');

        $this->assertTrue($withEpics->epics_exists);
        $this->assertSame(2, $withEpics->epics_count);
        $this->assertFalse($withoutEpics->epics_exists);
        $this->assertSame(0, $withoutEpics->epics_count);
    }

    public function test_archived_returns_paginated_archived_projects(): void
    {
        Project::factory()->count(2)->archived()->create();
        Project::factory()->count(3)->create(['active' => true]);
        Project::factory()->count(1)->trashed()->create();

        $result = $this->query->archived('');

        $this->assertCount(2, $result->items());
        $this->assertSame(2, $result->total());
        foreach ($result->items() as $project) {
            $this->assertFalse($project->active);
            $this->assertNull($project->deleted_at);
        }
    }

    public function test_archived_applies_search_filter(): void
    {
        Project::factory()->archived()->create(['name' => 'Old Website']);
        Project::factory()->archived()->create(['name' => 'Old Mobile']);

        $result = $this->query->archived('Website');

        $this->assertCount(1, $result->items());
        $this->assertSame('Old Website', $result->first()->name);
    }

    public function test_trashed_returns_paginated_trashed_projects(): void
    {
        Project::factory()->count(2)->trashed()->create();
        Project::factory()->count(3)->create(['active' => true]);

        $result = $this->query->trashed('');

        $this->assertCount(2, $result->items());
        $this->assertSame(2, $result->total());
        foreach ($result->items() as $project) {
            $this->assertNotNull($project->deleted_at);
        }
    }

    public function test_trashed_orders_by_deleted_at_desc_then_id_asc(): void
    {
        $this->travelTo('2026-10-02 12:00:00');
        $older = Project::factory()->trashed()->create(['name' => 'Older']);
        $older->forceFill(['deleted_at' => '2026-10-01 10:00:00'])->saveQuietly();
        $newer = Project::factory()->trashed()->create(['name' => 'Newer']);
        $newer->forceFill(['deleted_at' => '2026-10-02 10:00:00'])->saveQuietly();
        $tied = Project::factory()->trashed()->create(['name' => 'Tied']);
        $tied->forceFill(['deleted_at' => '2026-10-02 10:00:00'])->saveQuietly();

        $result = $this->query->trashed('');

        $names = array_map(fn ($p) => $p->name, $result->items());
        $this->assertSame(['Newer', 'Tied', 'Older'], $names);
    }

    public function test_state_counts_returns_correct_structure(): void
    {
        $result = $this->query->stateCounts(10, 5, 3);

        $this->assertSame([
            'active' => 10,
            'archived' => 5,
            'trashed' => 3,
        ], $result);
    }

    public function test_find_trashed_by_name_finds_trashed_project(): void
    {
        $trashed = Project::factory()->trashed()->create(['name' => 'Deleted project']);
        Project::factory()->create(['name' => 'Active project']);

        $result = $this->query->findTrashedByName('Deleted project');

        $this->assertNotNull($result);
        $this->assertSame($trashed->id, $result->id);
    }

    public function test_find_trashed_by_name_returns_null_for_active_project(): void
    {
        Project::factory()->create(['name' => 'Active project']);

        $result = $this->query->findTrashedByName('Active project');

        $this->assertNull($result);
    }

    public function test_find_trashed_by_name_returns_null_for_nonexistent(): void
    {
        $result = $this->query->findTrashedByName('Nonexistent');

        $this->assertNull($result);
    }

    private function nullQueryIntegration(): ProjectQueryIntegration
    {
        return new class implements ProjectQueryIntegration
        {
            use ProjectQueryIntegrationDefaults;
        };
    }
}
