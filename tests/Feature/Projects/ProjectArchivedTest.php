<?php

namespace Projects13\Tests\Feature\Projects;

use App\Models\User;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class ProjectArchivedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_archived_projects_are_not_shown_in_the_active_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Project::factory()->archived()->create(['name' => 'Dormant project']);
        Project::factory()->create(['name' => 'Active project']);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Dormant project');
    }

    public function test_archived_projects_are_searchable_by_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->travelTo('2026-10-02 12:30:00');
        $updatedProject = Project::factory()->archived()->create(['name' => 'Alpha Dormant']);
        Project::factory()->archived()->create(['name' => 'Beta Dormant']);
        Project::factory()->create(['name' => 'Alpha Active']);
        $this->travelTo('2026-07-15 12:30:00');
        $updatedProject->touch();

        $this->get(route('projects.archived.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Dormant')
            ->assertDontSee('Beta Dormant')
            ->assertDontSee('Alpha Active');
    }

    public function test_project_can_be_archived_and_reactivated(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patch(route('projects.archive', $project))
            ->assertRedirect(route('projects.index'));
        $this->assertFalse($project->refresh()->active);

        $this->patch(route('projects.archived.activate', $project))
            ->assertRedirect(route('projects.archived.index'));
        $this->assertTrue($project->refresh()->active);
    }

    public function test_restoring_a_trashed_archived_project_preserves_its_archived_state(): void
    {
        $project = Project::factory()->archived()->trashed()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patch(route('projects.trash.restore', $project->id))
            ->assertRedirect(route('projects.trash.index'));

        $this->assertNotSoftDeleted($project);
        $this->assertFalse($project->refresh()->active);
    }
}
