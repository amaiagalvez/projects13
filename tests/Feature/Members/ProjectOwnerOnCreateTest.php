<?php

namespace Projects13\Tests\Feature\Members;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectOwnerOnCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_author_of_a_new_project_becomes_its_owner(): void
    {
        $author = $this->signIn();

        $this->post(route('projects.store'), ['name' => 'Owned project', 'start_date' => '2026-10-01'])
            ->assertSessionHasNoErrors();

        $member = Project::query()->where('name', 'Owned project')->sole()->members()->sole();
        $this->assertSame($author->id, $member->user_id);
        $this->assertSame(ProjectRole::Owner, $member->role);
        $this->assertSame($author->id, $member->added_by);
    }
}
