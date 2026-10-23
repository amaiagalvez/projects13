<?php

namespace Projects13\Tests\Feature\Members;

use App\Models\User;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Illuminate\Support\Facades\DB;
use Projects13\Models\ProjectMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Basics13\Support\Database\UniqueConstraintViolation;

class ProjectMemberSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_be_a_member_of_the_same_project_twice(): void
    {
        $member = ProjectMember::factory()->create();

        try {
            ProjectMember::factory()->create(['project_id' => $member->project_id, 'user_id' => $member->user_id]);
            $this->fail('The unique index must reject a second membership.');
        } catch (QueryException $exception) {
            $this->assertTrue(UniqueConstraintViolation::causedBy($exception));
        }

        $this->assertSame(1, ProjectMember::query()->count());
    }

    public function test_existing_projects_get_their_creator_as_owner(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create();
        $trashed = Project::factory()->trashed()->create();
        $anonymous = Project::factory()->create();
        DB::table('PRO_projects')->whereIn('id', [$project->id, $trashed->id])->update(['created_by' => $author->id]);
        DB::table('PRO_projects')->where('id', $anonymous->id)->update(['created_by' => null]);
        ProjectMember::factory()->for($project)->create(['user_id' => $other->id]);

        $this->runOwnersMigration();
        $this->runOwnersMigration();

        $this->assertSame(ProjectRole::Owner, $project->members()->where('user_id', $author->id)->sole()->role);
        $this->assertSame(1, $trashed->members()->where('user_id', $author->id)->count());
        $this->assertSame(0, $anonymous->members()->count());
        $this->assertSame(2, $project->members()->count());
    }

    private function runOwnersMigration(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_23_000002_seed_project_owners.php';

        if (! is_object($migration) || ! method_exists($migration, 'up')) {
            $this->fail('The owners migration must define up().');
        }

        $migration->up();
    }
}
