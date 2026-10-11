<?php

namespace Projects13\Tests\Feature;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Customers13\Models\Customer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectEpicPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_migrations_and_factories_create_the_resource_hierarchy(): void
    {
        $customer = Customer::factory()->create();
        $project = Project::factory()->for($customer)->create();
        $epic = Epic::factory()->for($project)->create();

        $this->assertSame($customer->id, $project->customer_id);
        $this->assertSame($project->id, $epic->project_id);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id]);
        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id]);
        $this->assertTrue(Schema::hasTable('PRO_epic_comments'));
        $this->assertFalse(Schema::hasTable('projects'));
        $this->assertFalse(Schema::hasTable('epics'));
        $this->assertFalse(Schema::hasTable('epic_comments'));
    }

    public function test_undated_epic_factory_handles_same_day_project_window(): void
    {
        $project = Project::factory()->create([
            'start_date' => '2026-10-11',
            'end_date' => '2026-10-11',
        ]);

        $epic = Epic::factory()->for($project)->withoutDates()->create();

        $this->assertModelExists($epic);
        $this->assertSame($project->id, $epic->project_id);
        $this->assertNull($epic->start_date);
        $this->assertNull($epic->end_date);
    }

    public function test_package_migrations_can_be_rolled_back_and_reapplied(): void
    {
        $rollback = $this->artisan('migrate:rollback', ['--step' => 3, '--no-interaction' => true]);
        $this->assertInstanceOf(PendingCommand::class, $rollback);
        $this->assertSame(0, $rollback->run());

        $this->assertFalse(Schema::hasTable('PRO_epic_comments'));
        $this->assertFalse(Schema::hasTable('PRO_epics'));
        $this->assertFalse(Schema::hasTable('PRO_projects'));

        $migration = $this->artisan('migrate', ['--no-interaction' => true]);
        $this->assertInstanceOf(PendingCommand::class, $migration);
        $this->assertSame(0, $migration->run());

        $this->assertTrue(Schema::hasTable('PRO_projects'));
        $this->assertTrue(Schema::hasTable('PRO_epics'));
        $this->assertTrue(Schema::hasTable('PRO_epic_comments'));
    }
}
