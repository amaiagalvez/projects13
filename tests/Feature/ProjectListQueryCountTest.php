<?php

namespace Projects13\Tests\Feature;

use App\Models\User;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectListQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_project_list_makes_the_same_queries_whatever_the_number_of_rows(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->seedProjects(3);
        $this->get(route('projects.index'))->assertOk();

        $few = $this->queriesFor(route('projects.index'));
        $this->seedProjects(12);
        $many = $this->queriesFor(route('projects.index'));

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many, 'The project list queries grow with its rows.');
    }

    private function seedProjects(int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            Project::factory()->hasMember(auth()->user())->create();
        }
    }

    private function queriesFor(string $url): int
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $this->get($url)->assertOk();

        return $queries;
    }
}
