<?php

namespace Projects13\Tests\Feature\Database\Factories;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class ProjectFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_project_factory_creates_valid_project(): void
    {
        $project = Project::factory()->createOne();

        $this->assertInstanceOf(Project::class, $project);
        $this->assertNotEmpty($project->name);
        $this->assertNotEmpty($project->start_date);
        $this->assertNotEmpty($project->end_date);
        $this->assertTrue($project->active);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id]);
    }

    public function test_project_factory_archived_state_sets_active_to_false(): void
    {
        $project = Project::factory()->archived()->createOne();

        $this->assertFalse($project->active);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'active' => false]);
    }

    public function test_project_factory_trashed_state_soft_deletes_the_record(): void
    {
        $project = Project::factory()->trashed()->createOne();

        $this->assertSoftDeleted($project);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'deleted_at' => $project->deleted_at]);
    }
}
