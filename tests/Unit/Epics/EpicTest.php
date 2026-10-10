<?php

namespace Projects13\Tests\Unit\Epics;

use Carbon\Carbon;
use Projects13\Models\Epic;
use Projects13\Tests\TestCase;

class EpicTest extends TestCase
{
    public function test_epic_fields_can_be_mass_assigned_without_a_database(): void
    {
        $epic = new Epic;

        $epic->fill([
            'name' => 'Checkout flow',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'project_id' => 7,
        ]);

        $this->assertSame('Checkout flow', $epic->getAttributes()['name']);
        $this->assertSame('2026-10-01', $epic->start_date->toDateString());
        $this->assertSame('2026-10-31', $epic->end_date->toDateString());
        $this->assertSame(7, $epic->getAttributes()['project_id']);
    }

    public function test_only_declared_epic_attributes_are_mass_assignable(): void
    {
        $epic = new Epic;

        $epic->fill([
            'name' => 'Checkout flow',
            'start_date' => '2026-10-01',
            'project_id' => 7,
            'id' => 42,
            'active' => false,
        ]);

        $this->assertSame('Checkout flow', $epic->getAttributes()['name']);
        $this->assertTrue($epic->active);
        $this->assertFalse($epic->wasRecentlyCreated);
        $this->assertArrayNotHasKey('id', $epic->getAttributes());
    }

    public function test_epic_has_dates(): void
    {
        $epic = new Epic;

        $epic->fill([
            'name' => 'Test epic',
            'start_date' => '2023-06-01',
            'end_date' => '2023-08-01',
        ]);

        $this->assertEquals('2023-06-01', $epic->start_date->toDateString());
        $this->assertEquals('2023-08-01', $epic->end_date->toDateString());
    }

    public function test_epic_is_active_by_default(): void
    {
        $epic = new Epic;

        $this->assertTrue($epic->active);
    }

    public function test_epic_can_be_created_with_required_fields(): void
    {
        $epic = new Epic;

        $epic->fill([
            'name' => 'Minimal epic',
        ]);

        $this->assertEquals('Minimal epic', $epic->name);
    }

    public function test_epic_can_have_null_end_date(): void
    {
        $epic = new Epic;

        $epic->fill([
            'name' => 'Ongoing epic',
            'start_date' => '2023-06-01',
            'end_date' => null,
        ]);

        $this->assertEquals('2023-06-01', $epic->start_date->toDateString());
        $this->assertNull($epic->end_date);
    }
}