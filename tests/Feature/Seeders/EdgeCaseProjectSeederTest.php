<?php

namespace Projects13\Tests\Feature\Seeders;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Database\Seeders\EdgeCaseProjectSeeder;

class EdgeCaseProjectSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_projects_without_epics_and_epics_without_comments(): void
    {
        $this->seed(EdgeCaseProjectSeeder::class);

        $this->assertSame(3, Project::query()->where('name', 'like', 'Project without epics %')->count());
        $this->assertSame(3, Project::query()->where('name', 'like', 'Project for epic without comments %')->count());
        $this->assertSame(3, Epic::query()->where('name', 'like', 'Epic without comments %')->count());
        $this->assertDatabaseCount('PRO_epic_comments', 0);
    }
}
