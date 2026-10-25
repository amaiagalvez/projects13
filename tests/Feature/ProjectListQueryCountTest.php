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

    public function test_the_archived_project_list_makes_the_same_queries_whatever_the_number_of_rows(): void
    {
        $this->assertListQueriesDoNotGrow(route('projects.archived.index'), ['active' => false]);
    }

    public function test_the_project_trash_makes_the_same_queries_whatever_the_number_of_rows(): void
    {
        $this->assertListQueriesDoNotGrow(route('projects.trash.index'), ['active' => true], trashed: true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertListQueriesDoNotGrow(string $url, array $attributes, bool $trashed = false): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->seedProjects(3, $attributes, $trashed);
        $this->get($url)->assertOk();

        $few = $this->queriesFor($url);
        $this->seedProjects(12, $attributes, $trashed);
        $many = $this->queriesFor($url);

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many, 'The project list queries grow with its rows.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedProjects(int $count, array $attributes = [], bool $trashed = false): void
    {
        for ($index = 0; $index < $count; $index++) {
            $project = Project::factory()->hasMember(auth()->user())->create($attributes);

            if ($trashed) {
                $project->delete();
            }
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
