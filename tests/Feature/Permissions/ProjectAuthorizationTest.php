<?php

namespace Projects13\Tests\Feature\Permissions;

use App\Models\User;
use Basics13\Models\Role;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_planning_and_timeline_need_projects_view(): void
    {
        $this->actingAs($this->holding(['epics.manage']));

        $this->get(route('projects.index'))->assertForbidden();
        $this->get(route('projects.archived.index'))->assertForbidden();
        $this->get(route('planning'))->assertForbidden();
        $this->get(route('timeline'))->assertForbidden();
    }

    public function test_projects_view_opens_the_lists_planning_and_timeline(): void
    {
        $this->actingAs($this->holding(['projects.view']));

        $this->get(route('projects.index'))->assertOk();
        $this->get(route('planning'))->assertOk();
        $this->get(route('timeline'))->assertOk();
    }

    public function test_emptying_the_trash_needs_force_delete(): void
    {
        $project = Project::factory()->trashed()->create();
        $this->actingAs($this->holding(['projects.view', 'projects.delete']));

        $this->delete(route('projects.trash.destroy', $project->id))->assertForbidden();

        $this->assertSoftDeleted($project);
    }

    public function test_epics_need_epics_view(): void
    {
        $this->actingAs($this->holding(['projects.manage']));

        $this->get(route('epics.index'))->assertForbidden();
    }

    public function test_commenting_needs_epics_edit(): void
    {
        $epic = Epic::factory()->create();
        $this->actingAs($this->holding(['epics.view']));

        $this->post(route('epics.comments.store', $epic), ['body' => 'Not allowed'])->assertForbidden();

        $this->assertDatabaseCount('PRO_epic_comments', 0);
    }

    public function test_epics_edit_allows_commenting(): void
    {
        $epic = Epic::factory()->create();
        $this->actingAs($this->holding(['epics.view', 'epics.edit']));

        $this->from(route('epics.index'))
            ->post(route('epics.comments.store', $epic), ['body' => 'Allowed'])
            ->assertRedirect(route('epics.index'));

        $this->assertDatabaseCount('PRO_epic_comments', 1);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function holding(array $permissions): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::factory()->withPermissions($permissions)->create());

        return $user;
    }
}
