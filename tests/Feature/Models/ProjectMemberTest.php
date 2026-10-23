<?php

namespace Projects13\Tests\Feature\Models;

use App\Models\User;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Projects13\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_role_to_enum(): void
    {
        $member = ProjectMember::factory()->create(['role' => ProjectRole::Editor]);

        $this->assertInstanceOf(ProjectRole::class, $member->role);
        $this->assertSame(ProjectRole::Editor, $member->role);
    }

    public function test_belongs_to_project(): void
    {
        $project = Project::factory()->create();
        $member = ProjectMember::factory()->for($project)->create();

        $this->assertSame($project->id, $member->project->id);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $member = ProjectMember::factory()->state(['user_id' => $user->id])->create();

        $this->assertSame($user->id, $member->user->id);
    }

    public function test_project_relationship_with_trashed(): void
    {
        $project = Project::factory()->trashed()->create();
        $member = ProjectMember::factory()->for($project)->create();

        $this->assertSame($project->id, $member->project->id);
        $this->assertTrue($member->project->trashed());
    }

    public function test_fillable_attributes(): void
    {
        $member = new ProjectMember(['user_id' => 1, 'role' => ProjectRole::Viewer]);

        $this->assertSame(1, $member->user_id);
        $this->assertSame(ProjectRole::Viewer, $member->role);
    }

    public function test_factory_creates_valid_member(): void
    {
        $member = ProjectMember::factory()->create();

        $this->assertDatabaseHas('PRO_project_members', ['id' => $member->id]);
        $this->assertInstanceOf(ProjectRole::class, $member->role);
    }

    public function test_factory_role_state(): void
    {
        $member = ProjectMember::factory()->role(ProjectRole::Owner)->create();

        $this->assertSame(ProjectRole::Owner, $member->role);
    }
}
