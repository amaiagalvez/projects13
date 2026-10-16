<?php

namespace Projects13\Tests\Unit\Database\Factories;

use Mockery;
use Faker\Generator;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class ProjectFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_project_factory_creates_valid_project(): void
    {
        $project = Project::factory()->createOne();

        $this->assertInstanceOf(Project::class, $project);
        $this->assertNotEmpty($project->name);
        $this->assertNotEmpty($project->start_date);
        $this->assertNotEmpty($project->end_date);
        $this->assertTrue($project->active);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id]);
    }

    public function test_project_factory_archived_state_sets_active_to_false(): void
    {
        $project = Project::factory()->archived()->createOne();

        $this->assertFalse($project->active);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'active' => false]);
    }

    public function test_project_factory_trashed_state_soft_deletes_the_record(): void
    {
        $project = Project::factory()->trashed()->createOne();

        $this->assertSoftDeleted($project);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'deleted_at' => $project->deleted_at]);
    }

    public function test_project_factory_definition_returns_valid_dates(): void
    {
        $factory = new class extends ProjectFactory
        {
            /** @return array{start_date: string|null, end_date: string|null} */
            public function callGetDateRange(): array
            {
                return $this->getDatesInRange();
            }
        };

        $dates = $factory->callGetDateRange();

        $this->assertArrayHasKey('start_date', $dates);
        $this->assertArrayHasKey('end_date', $dates);
        $this->assertNotNull($dates['start_date']);
        $this->assertNotNull($dates['end_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dates['start_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dates['end_date']);
    }

    public function test_project_factory_get_date_range_can_return_null_when_allowed(): void
    {
        $factory = new class extends ProjectFactory
        {
            /** @return array{start_date: string|null, end_date: string|null} */
            public function callGetDateRange(bool $allowNull): array
            {
                return $this->getDatesInRange(-1, 1, $allowNull);
            }
        };

        $faker = Mockery::mock(Generator::class);
        $faker->shouldReceive('boolean')->once()->with(20)->andReturnTrue();
        $locale = config('app.faker_locale');
        $this->assertIsString($locale);
        app()->instance(Generator::class . ':' . $locale, $faker);

        $dates = $factory->callGetDateRange(true);

        $this->assertSame(['start_date' => null, 'end_date' => null], $dates);
    }
}
