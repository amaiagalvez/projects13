<?php

namespace Projects13\Tests\Feature;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicListQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_active_epic_list_makes_the_same_queries_whatever_the_number_of_rows(): void
    {
        $this->assertListQueriesDoNotGrow(route('epics.index'), ['active' => true]);
    }

    public function test_the_archived_epic_list_makes_the_same_queries_whatever_the_number_of_rows(): void
    {
        $this->assertListQueriesDoNotGrow(route('epics.archived.index'), ['active' => false]);
    }

    public function test_the_epic_trash_makes_the_same_queries_whatever_the_number_of_rows(): void
    {
        $this->assertListQueriesDoNotGrow(route('epics.trash.index'), ['active' => true], trashed: true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertListQueriesDoNotGrow(string $url, array $attributes, bool $trashed = false): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->seedEpics($attributes, trashed: $trashed);
        $this->get($url)->assertOk();

        $few = $this->queriesFor($url);
        $this->seedEpics($attributes, count: 12, trashed: $trashed);
        $many = $this->queriesFor($url);

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many, 'The epic list queries grow with its rows.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedEpics(array $attributes, int $count = 3, bool $trashed = false): void
    {
        for ($index = 0; $index < $count; $index++) {
            $project = Project::factory()->hasMember(auth()->user())->create();
            $epic = Epic::factory()->for($project)->create($attributes);

            if ($trashed) {
                $epic->delete();
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
