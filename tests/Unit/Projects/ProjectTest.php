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
        $this->assertSame('2026-10-01', $project->start_date?->toDateString() /** @phpstan-ignore-line nullsafe.neverNull */);
        $this->assertSame('2026-12-31', $project->end_date?->toDateString());
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

    public function test_project_has_dates(): void
    {
        $project = new Project;

        $project->fill([
            'name' => 'Test project',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
        ]);

        $this->assertEquals('2023-01-01', $project->start_date?->toDateString() /** @phpstan-ignore-line nullsafe.neverNull */);
        $this->assertEquals('2023-12-31', $project->end_date?->toDateString());
    }

    public function test_project_is_active_by_default(): void
    {
        $project = new Project;

        $this->assertTrue($project->active);
    }

    public function test_project_can_be_created_with_required_fields(): void
    {
        $project = new Project;

        $project->fill([
            'name' => 'Minimal project',
        ]);

        $this->assertEquals('Minimal project', $project->name);
    }

    public function test_project_can_have_null_end_date(): void
    {
        $project = new Project;

        $project->fill([
            'name' => 'Ongoing project',
            'start_date' => '2023-01-01',
            'end_date' => null,
        ]);

        $this->assertEquals('2023-01-01', $project->start_date?->toDateString() /** @phpstan-ignore-line nullsafe.neverNull */);
        $this->assertNull($project->end_date);
    }
}
