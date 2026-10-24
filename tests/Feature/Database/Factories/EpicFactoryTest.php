<?php

namespace Projects13\Tests\Feature\Database\Factories;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Database\Factories\EpicFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class EpicFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_epic_factory_creates_valid_epic(): void
    {
        $epic = Epic::factory()->createOne();

        $this->assertInstanceOf(Epic::class, $epic);
        $this->assertNotEmpty($epic->name);
        $this->assertNotEmpty($epic->start_date);
        $this->assertNotEmpty($epic->end_date);
        $this->assertGreaterThan(0, $epic->project_id);
        $this->assertTrue($epic->active);
        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id]);
    }

    public function test_epic_factory_archived_state_sets_active_to_false(): void
    {
        $epic = Epic::factory()->archived()->createOne();

        $this->assertFalse($epic->active);
        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id, 'active' => false]);
    }

    public function test_epic_factory_trashed_state_soft_deletes_the_record(): void
    {
        $epic = Epic::factory()->trashed()->createOne();

        $this->assertSoftDeleted($epic);
        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id, 'deleted_at' => $epic->deleted_at]);
    }

    public function test_epic_factory_without_dates_state(): void
    {
        $epic = Epic::factory()->withoutDates()->createOne();

        $this->assertNull($epic->start_date);
        $this->assertNull($epic->end_date);
    }

    public function test_epic_factory_dates_are_within_project_dates(): void
    {
        $project = Project::factory()->create([
            'name' => 'Test Project '.uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $project->refresh();

        $this->assertEquals('2026-01-01', $project->start_date->toDateString());
        $this->assertEquals('2026-12-31', $project->end_date?->toDateString());

        $epic = Epic::factory()->for($project)->createOne();

        $this->assertEquals($project->id, $epic->project_id);
        // epic start_date >= project start_date
        $this->assertGreaterThanOrEqual($project->start_date, $epic->start_date);
        // epic end_date <= project end_date
        $this->assertLessThanOrEqual($project->end_date, $epic->end_date);
        // epic start_date <= epic end_date
        $this->assertGreaterThanOrEqual($epic->start_date, $epic->end_date);
    }

    public function test_epic_factory_handles_project_without_end_date(): void
    {
        $project = Project::factory()->create([
            'name' => 'Test Project '.uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $project->refresh();

        $epic = Epic::factory()->for($project)->createOne();

        $this->assertEquals($project->id, $epic->project_id);
        // epic start_date >= project start_date
        $this->assertGreaterThanOrEqual($project->start_date, $epic->start_date);
        // epic start_date <= epic end_date
        $this->assertGreaterThanOrEqual($epic->start_date, $epic->end_date);
    }

    public function test_epic_factory_get_date_range_returns_valid_dates(): void
    {
        $project = Project::create([
            'name' => 'Test Project',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $factory = new class extends EpicFactory
        {
            /** @return array{start_date: string, end_date: string} */
            public function callDefinition(): array
            {
                /** @var array{start_date: string, end_date: string} $def */
                $def = $this->definition();

                return $def;
            }
        };

        $definition = $factory->callDefinition();

        $this->assertArrayHasKey('start_date', $definition);
        $this->assertArrayHasKey('end_date', $definition);
        $this->assertNotEmpty($definition['start_date']);
        $this->assertNotEmpty($definition['end_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $definition['start_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $definition['end_date']);
        $this->assertGreaterThanOrEqual($project->start_date, $definition['start_date']);
        $this->assertLessThanOrEqual($project->end_date, $definition['end_date']);
    }

    public function test_epic_factory_get_record_returns_existing_project_when_available(): void
    {
        $existing = Project::factory()->create();

        $project = EpicFactory::getRecordOrNew(Project::class);

        $this->assertSame($existing->id, $project->id);
    }

    public function test_epic_factory_get_record_creates_new_when_none_exists(): void
    {
        $project = EpicFactory::getRecordOrNew(Project::class);

        $this->assertInstanceOf(Project::class, $project);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id]);
    }
}
