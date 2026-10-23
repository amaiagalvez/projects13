<?php

namespace Projects13\Tests\Feature\Members;

use App\Models\User;
use Basics13\Models\Role;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Projects13\Models\EpicComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Queries\Dashboard\DashboardSummaryQuery;

/**
 * Without `projects.manage`, a user reaches only the projects they are a member of, and the epics
 * and comments under them, on every surface.
 */
class ProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Project $mine;

    private Project $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->holding(['projects.view', 'projects.edit', 'epics.manage']);
        $this->mine = Project::factory()->hasMember($this->member, ProjectRole::Editor)
            ->create(['name' => 'Mine project', 'start_date' => '2026-01-01']);
        $this->foreign = Project::factory()->create(['name' => 'Foreign project', 'start_date' => '2026-01-01']);
        Epic::factory()->for($this->mine)->create(['name' => 'Mine epic', 'end_date' => now()->addDays(3)->toDateString()]);
        Epic::factory()->for($this->foreign)->create(['name' => 'Foreign epic', 'end_date' => now()->addDays(3)->toDateString()]);
    }

    public function test_the_lists_and_their_tabs_show_only_member_projects(): void
    {
        Project::factory()->archived()->create(['name' => 'Foreign archived']);
        $this->actingAs($this->member);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Mine project')
            ->assertDontSee('Foreign project')
            ->assertViewHas('list', fn (array $list): bool => $list['tabs'][1]['count'] === 0);
        $this->get(route('epics.index'))->assertOk()->assertSee('Mine epic')->assertDontSee('Foreign epic');
    }

    public function test_planning_timeline_and_dashboard_follow_membership(): void
    {
        EpicComment::factory()->for(Epic::query()->where('name', 'Foreign epic')->sole())->create(['body' => 'Foreign comment']);
        $this->actingAs($this->member);

        $this->get(route('planning'))->assertOk()->assertSee('Mine project')->assertDontSee('Foreign project');
        $this->get(route('timeline'))->assertOk()->assertSee('Mine project')->assertDontSee('Foreign project');

        $summary = app(DashboardSummaryQuery::class)->summary();
        $this->assertSame(1, $summary['counts']['projects']);
        $this->assertSame(['Mine epic'], array_column($summary['deadlines'], 'name'));
        $this->assertSame([], $summary['comments']);
    }

    public function test_a_foreign_project_by_url_is_not_found(): void
    {
        $this->actingAs($this->member);

        $this->put(route('projects.update', $this->foreign), ['name' => 'Taken', 'start_date' => '2026-01-01'])
            ->assertNotFound();
        $this->getJson(route('epics.comments.index', Epic::query()->where('name', 'Foreign epic')->sole()))
            ->assertNotFound();

        $this->assertSame('Foreign project', $this->foreign->refresh()->name);
    }

    public function test_epics_go_only_into_projects_the_user_edits(): void
    {
        $viewerOf = Project::factory()->hasMember($this->member, ProjectRole::Viewer)->create(['name' => 'Watched project']);
        $this->actingAs($this->member);

        $this->getJson(route('projects.options'))
            ->assertOk()
            ->assertExactJson(['results' => [['id' => $this->mine->id, 'text' => 'Mine project']]]);

        foreach ([$this->foreign, $viewerOf] as $project) {
            $this->postJson(route('epics.store'), ['name' => 'Sneaked epic', 'project_id' => $project->id])
                ->assertJsonValidationErrors('project_id');
        }

        $this->assertDatabaseMissing('PRO_epics', ['name' => 'Sneaked epic']);
    }

    public function test_projects_manage_reaches_every_project(): void
    {
        $this->actingAs($this->holding(['projects.manage', 'epics.view']));

        $this->get(route('projects.index'))->assertSee('Mine project')->assertSee('Foreign project');
        $this->get(route('epics.index'))->assertSee('Foreign epic');
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
