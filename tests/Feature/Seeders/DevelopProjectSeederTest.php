<?php

namespace Projects13\Tests\Feature\Seeders;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Customers13\Models\Customer;
use Projects13\Models\EpicComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Database\Seeders\DevelopProjectSeeder;

class DevelopProjectSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_projects_epics_and_comments_for_seeded_customers(): void
    {
        Customer::factory()->create(['name' => 'Perf Customer 00001']);
        User::query()->create([
            'name' => 'Seed author',
            'email' => 'seed-author@example.test',
            'password' => 'unused',
        ]);

        app(DevelopProjectSeeder::class)->run(1);

        $this->assertDatabaseCount('PRO_projects', 3);
        $this->assertDatabaseCount('PRO_epics', 12);
        $this->assertDatabaseCount('PRO_epic_comments', 36);
        $this->assertSame(1, Project::query()->where('customer_id', Customer::query()->value('id'))->distinct()->count('customer_id'));
        $this->assertSame(12, Epic::query()->whereHas('project', fn ($query) => $query->where('name', 'like', 'Perf Project %'))->count());
        $this->assertSame(36, EpicComment::query()->count());
    }
}
