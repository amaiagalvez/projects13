<?php

namespace Projects13\Tests\Feature\Projects;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectInputValidationTest extends TestCase
{
    use RefreshDatabase;

    private const START_DATE = '2026-10-01';

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_name_and_start_date_are_required(): void
    {
        $this->signIn();

        $this->from(route('projects.index'))
            ->post(route('projects.store'), [])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors(['name', 'start_date']);

        $this->assertDatabaseCount('PRO_projects', 0);
    }

    public function test_store_trims_project_name_before_unique_validation(): void
    {
        $this->signIn();
        Project::factory()->create(['name' => 'Existing project']);

        $this->from(route('projects.index'))
            ->post(route('projects.store'), [
                'name' => '  Existing project  ',
                'start_date' => self::START_DATE,
            ])->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('PRO_projects', 1);
    }

    public function test_project_name_can_be_kept_when_updating_the_same_project(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Existing project']);

        $this->put(route('projects.update', $project), [
            'name' => $project->name,
            'start_date' => self::START_DATE,
        ])->assertRedirect(route('projects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Existing project']);
    }

    public function test_update_trims_project_name_before_persisting(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Original project']);

        $this->put(route('projects.update', $project), [
            'name' => '  Trimmed project  ',
            'start_date' => self::START_DATE,
        ])->assertRedirect(route('projects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Trimmed project']);
    }

    public function test_project_name_cannot_be_changed_to_another_projects_name(): void
    {
        $this->signIn();
        $existing = Project::factory()->create(['name' => 'Existing project']);
        $project = Project::factory()->create(['name' => 'Project to update']);

        $this->from(route('projects.index'))
            ->put(route('projects.update', $project), [
                'name' => $existing->name,
                'start_date' => self::START_DATE,
            ])->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Project to update']);
    }

    public function test_project_name_can_be_reused_after_soft_delete(): void
    {
        $this->signIn();
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Deleted project']);
        $payload = ['name' => $deletedProject->name, 'start_date' => self::START_DATE];

        $this->post(route('projects.store'), $payload)
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('deleted_project_conflict', [
                'id' => $deletedProject->id,
                'name' => $deletedProject->name,
            ]);

        $this->assertDatabaseCount('PRO_projects', 1);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('project-name-conflict')
            ->assertSee('project-conflict-create-new')
            ->assertSee('project-conflict-restore')
            ->assertSee('aria-labelledby="project-name-conflict-heading"', false)
            ->assertSee('id="project-name-conflict-heading"', false)
            ->assertSee(__('A deleted record already uses the name :name.', ['name' => $deletedProject->name]));

        $this->post(route('projects.store'), [...$payload, 'reuse_deleted_name' => '1'])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('PRO_projects', ['name' => 'Deleted project', 'deleted_at' => null]);
        $this->assertDatabaseCount('PRO_projects', 2);
        $this->assertSoftDeleted($deletedProject);
    }

    public function test_project_can_be_updated_to_a_name_used_only_by_a_deleted_project(): void
    {
        $this->signIn();
        $project = Project::factory()->create(['name' => 'Existing project']);
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Deleted project']);

        $this->put(route('projects.update', $project), [
            'name' => $deletedProject->name,
            'start_date' => self::START_DATE,
        ])->assertRedirect(route('projects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('PRO_projects', [
            'id' => $project->id,
            'name' => 'Deleted project',
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted($deletedProject);
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function validEndDates(): array
    {
        return [
            'omitted' => [null, null],
            'empty' => ['', null],
            'same day' => [self::START_DATE, self::START_DATE],
        ];
    }

    #[DataProvider('validEndDates')]
    public function test_project_accepts_optional_end_dates_and_same_day_ranges(?string $endDate, ?string $expected): void
    {
        $this->signIn();
        $payload = ['name' => 'Date range project', 'start_date' => self::START_DATE];

        $this->post(route('projects.store'), $endDate === null ? $payload : [...$payload, 'end_date' => $endDate])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasNoErrors();

        $project = Project::query()->sole();
        $this->assertSame(self::START_DATE, $project->start_date->format('Y-m-d'));
        $this->assertSame($expected, $project->end_date?->format('Y-m-d'));

        $this->get(route('projects.index'))->assertOk()->assertSee('Date range project');
    }
}
