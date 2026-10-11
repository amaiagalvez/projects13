<?php

namespace Projects13\Tests\Feature\Seeders;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
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

        $this->assertDatabaseCount('PRO_projects', 1);
        $this->assertDatabaseCount('PRO_epics', 4);
        $this->assertDatabaseCount('PRO_epic_comments', 12);
        $this->assertSame('Perf Project 00001', Project::query()->value('name'));
        $this->assertSame(4, Epic::query()->whereHas('project', fn ($query) => $query->where('name', 'like', 'Perf Project %'))->count());
        $this->assertSame(12, EpicComment::query()->count());
    }

    public function test_it_creates_nothing_when_asked_for_no_projects(): void
    {
        User::query()->create([
            'name' => 'Seed author',
            'email' => 'seed-author@example.test',
            'password' => 'unused',
        ]);

        app(DevelopProjectSeeder::class)->run(0);

        $this->assertDatabaseCount('PRO_projects', 0);
    }
}
