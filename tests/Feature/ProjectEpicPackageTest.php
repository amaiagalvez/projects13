<?php

namespace Projects13\Tests\Feature;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Customers13\Models\Customer;
use Illuminate\Support\Facades\Schema;
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
}
