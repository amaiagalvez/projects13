<?php

namespace Projects13\Tests\Unit\Projects;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;

class ProjectTest extends TestCase
{
    public function test_project_fields_can_be_mass_assigned_without_a_database(): void
    {
        $project = new Project;

        $project->fill([
            'name' => 'Website renewal',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'customer_id' => 7,
        ]);

        $this->assertSame('Website renewal', $project->getAttributes()['name']);
        $this->assertSame('2026-10-01 00:00:00', $project->getAttributes()['start_date']);
        $this->assertSame(7, $project->getAttributes()['customer_id']);
    }

    public function test_only_declared_project_attributes_are_mass_assignable(): void
    {
        $project = new Project;

        $project->fill([
            'name' => 'Website renewal',
            'start_date' => '2026-10-01',
            'customer_id' => 7,
            'id' => 42,
            'active' => false,
        ]);

        $this->assertSame('Website renewal', $project->getAttributes()['name']);
        $this->assertTrue($project->active);
        $this->assertFalse($project->wasRecentlyCreated);
        $this->assertArrayNotHasKey('id', $project->getAttributes());
    }
}
