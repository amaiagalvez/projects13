<?php

namespace Projects13\Tests\Feature\Seeders;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Database\Seeders\DevelopProjectSeeder;

class DevelopProjectSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_projects_epics_and_comments(): void
    {
        User::query()->create([
            'name' => 'Seed author',
            'email' => 'seed-author@example.test',
            'password' => 'unused',
        ]);

        app(DevelopProjectSeeder::class)->run(1);

        $this->assertDatabaseCount('PRO_projects', 7);
        $this->assertDatabaseCount('PRO_epics', 7);
        $this->assertDatabaseCount('PRO_epic_comments', 12);
        $this->assertDatabaseHas('PRO_projects', ['name' => 'Perf Project 00001']);
        $this->assertSame(3, Project::query()->where('name', 'like', 'Project without epics %')->doesntHave('epics')->count());
        $this->assertSame(3, Epic::query()->where('name', 'like', 'Epic without comments %')->doesntHave('comments')->count());
        $this->assertSame(4, Epic::query()->whereHas('project', fn ($query) => $query->where('name', 'like', 'Perf Project %'))->count());
        $this->assertSame(12, EpicComment::query()->count());
    }

    #[DataProvider('nonPositiveProjectCounts')]
    public function test_it_creates_nothing_when_asked_for_no_projects(int $projects): void
    {
        app(DevelopProjectSeeder::class)->run($projects);

        $this->assertDatabaseCount('PRO_projects', 0);
        $this->assertDatabaseCount('PRO_epics', 0);
        $this->assertDatabaseCount('PRO_epic_comments', 0);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveProjectCounts(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }
}
