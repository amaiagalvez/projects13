<?php

namespace Projects13\Tests\Unit\Epics;

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
        $this->assertSame('2026-10-01 00:00:00', $epic->getAttributes()['start_date']);
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
}
