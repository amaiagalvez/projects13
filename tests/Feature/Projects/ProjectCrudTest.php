<?php

namespace Projects13\Tests\Feature\Projects;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Projects13\Models\EpicComment;
use Basics13\Queries\ListQueryBase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\QueryException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_guests_are_redirected_from_project_list(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_create_projects(): void
    {
        $user = User::create(['name' => 'Test user', 'email' => 'test@example.test', 'password' => 'unused']);

        $this->actingAs($user)
            ->post(route('projects.store'), ['name' => 'Unverified project', 'start_date' => '2026-01-01'])
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('PRO_projects', 0);
    }

    public function test_authenticated_users_can_create_update_and_delete_projects(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('projects.store'), [
            'name' => '  Website renewal  ',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'notes' => 'Project notes',
        ])->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));

        $project = Project::query()->firstOrFail();
        $this->assertSame('Website renewal', $project->name);
        $this->assertSame('Project notes', $project->notes);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('project-create-button')
            ->assertSee('aria-labelledby="project-form-heading"', false)
            ->assertSee('id="project-form-heading"', false)
            ->assertSee('aria-labelledby="project-confirm-heading"', false)
            ->assertSee('id="project-confirm-heading"', false)
            ->assertSee('project-edit-'.$project->id)
            ->assertSee('Website renewal')
            ->assertSee('title="Website renewal"', false);

        $this->put(route('projects.update', $project), [
            'name' => 'Mobile app',
            'start_date' => '2026-02-01',
            'end_date' => '2026-11-30',
            'notes' => 'Updated notes',
        ])->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.updated'));

        $this->assertDatabaseHas('PRO_projects', [
            'id' => $project->id,
            'name' => 'Mobile app',
            'notes' => 'Updated notes',
        ]);

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));

        $this->assertSoftDeleted($project);
    }

    public function test_project_store_persists_only_validated_attributes(): void
    {
        $user = $this->actingAs(User::factory()->create());

        $this->post(route('projects.store'), [
            'name' => 'Validated project',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'id' => 999999,
            'created_at' => '2000-01-01 00:00:00',
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('PRO_projects', [
            'name' => 'Validated project',
        ]);
        $this->assertDatabaseMissing('PRO_projects', [
            'name' => 'Validated project',
            'id' => 999999,
        ]);
        $this->assertDatabaseMissing('PRO_projects', [
            'name' => 'Validated project',
            'created_at' => '2000-01-01 00:00:00',
        ]);
    }

    public function test_json_creation_trims_name_and_returns_created_record(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $this->postJson(route('projects.store'), ['name' => '  Trimmed project  ', 'start_date' => '2026-01-01'])
            ->assertCreated()
            ->assertJsonStructure(['id', 'name'])
            ->assertJsonPath('name', 'Trimmed project');

        $this->assertDatabaseHas('PRO_projects', ['name' => 'Trimmed project']);
    }

    public function test_live_duplicate_name_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create(['name' => 'Website renewal']);

        $this->post(route('projects.store'), [
            'name' => $project->name,
            'start_date' => '2026-01-01',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('PRO_projects', 1);
    }

    public function test_deleted_name_conflict_does_not_create_until_reuse_is_confirmed(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create(['name' => 'Website renewal']);
        $project->delete();
        $data = [
            'name' => $project->name,
            'start_date' => '2026-01-01',
        ];

        $this->post(route('projects.store'), $data)
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('deleted_project_conflict', ['id' => $project->id, 'name' => $project->name]);
        $this->assertDatabaseCount('PRO_projects', 1);

        $this->post(route('projects.store'), [...$data, 'reuse_deleted_name' => true])
            ->assertSessionHas('status', __('basics13::messages.created'));

        $this->assertDatabaseCount('PRO_projects', 2);
        $this->assertSoftDeleted($project);
    }

    public function test_deleted_name_json_conflict_returns_409(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $project->delete();
        $message = __('basics13::messages.deleted_name_conflict', ['name' => $project->name]);

        $this->postJson(route('projects.store'), [
            'name' => $project->name,
            'start_date' => '2026-01-01',
        ])->assertConflict()->assertExactJson([
            'message' => $message,
            'errors' => ['name' => [$message]],
        ]);

        $this->assertDatabaseCount('PRO_projects', 1);
    }

    public function test_store_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
        $name = 'Concurrent project';
        $this->createDuplicateNameAfterValidation($name);

        $this->postJson(route('projects.store'), ['name' => $name, 'start_date' => '2026-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('PRO_projects', 1);
        $this->assertDatabaseHas('PRO_projects', ['name' => $name]);
    }

    public function test_update_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
        $project = Project::factory()->create(['name' => 'Original project']);
        $name = 'Concurrent project';
        $this->createDuplicateNameAfterValidation($name);

        $this->putJson(route('projects.update', $project), ['name' => $name, 'start_date' => '2026-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Original project']);
        $this->assertDatabaseHas('PRO_projects', ['name' => $name]);
        $this->assertDatabaseCount('PRO_projects', 2);
    }

    /**
     * The soft delete has to happen inside a transaction even when no rule asks for one, because a
     * guard registered by an optional integration package takes its row lock in a transaction of its
     * own and would otherwise let go of it before the delete is written.
     */
    public function test_direct_model_delete_runs_the_soft_delete_inside_a_transaction(): void
    {
        $project = Project::factory()->create();
        $transactionLevel = null;

        DB::listen(static function (QueryExecuted $query) use (&$transactionLevel): void {
            if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'PRO_projects')) {
                $transactionLevel = DB::transactionLevel();
            }
        });

        $initialTransactionLevel = DB::transactionLevel();

        $project->delete();

        $this->assertSame($initialTransactionLevel + 1, $transactionLevel);
        $this->assertSoftDeleted($project);
    }

    public function test_invalid_project_names_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        $this->postJson(route('projects.store'), ['name' => 'Ab', 'start_date' => '2026-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('PRO_projects', 0);
    }

    public function test_archive_and_activate_preserve_children(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $this->patch(route('projects.archive', $project))
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('status', __('basics13::messages.archived'));
        $this->assertFalse($project->fresh()->active);
        $this->assertTrue($epic->fresh()->active);

        $this->patch(route('projects.archived.activate', $project))
            ->assertRedirect(route('projects.archived.index'))
            ->assertSessionHas('status', __('basics13::messages.activated'));
        $this->assertTrue($project->fresh()->active);
    }

    public function test_archived_project_is_not_in_active_list_but_is_in_archived_list(): void
    {
        $this->actingAs(User::factory()->create());
        Project::factory()->archived()->create(['name' => 'Dormant project']);
        Project::factory()->create(['name' => 'Active project']);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Dormant project')
            ->assertSee('Active project');

        $this->get(route('projects.archived.index'))
            ->assertOk()
            ->assertSee('Dormant project')
            ->assertDontSee('Active project');
    }

    public function test_trashed_project_is_not_in_active_or_archived_lists(): void
    {
        $this->actingAs(User::factory()->create());
        Project::factory()->trashed()->create(['name' => 'Deleted project']);
        Project::factory()->create(['name' => 'Active project']);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Deleted project')
            ->assertSee('Active project');

        $this->get(route('projects.archived.index'))
            ->assertOk()
            ->assertDontSee('Deleted project')
            ->assertDontSee('Active project');

        $this->get(route('projects.trash.index'))
            ->assertOk()
            ->assertSee('Deleted project')
            ->assertDontSee('Active project');
    }

    public function test_empty_project_list_uses_shared_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('data-test="list-empty-state"', false)
            ->assertSee(__('No projects yet.'));
    }

    public function test_database_rejects_duplicate_project_names(): void
    {
        Project::factory()->create(['name' => 'Shared project']);

        $this->expectException(QueryException::class);

        Project::factory()->create(['name' => 'Shared project']);
    }

    public function test_database_allows_same_name_after_soft_delete(): void
    {
        $deletedProject = Project::factory()->trashed()->create(['name' => 'Shared project']);
        $activeProject = Project::factory()->create(['name' => 'Shared project']);

        $this->assertSoftDeleted($deletedProject);
        $this->assertModelExists($activeProject);
    }

    public function test_projects_are_ordered_by_start_date_then_name(): void
    {
        $this->actingAs(User::factory()->create());
        $zuluProject = Project::factory()->create(['name' => 'Zulu project', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $alphaProject = Project::factory()->create(['name' => 'Alpha project', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $laterProject = Project::factory()->create(['name' => 'Later project', 'start_date' => '2026-02-01', 'end_date' => '2026-12-31']);

        $this->get(route('projects.index'))
            ->assertSeeInOrder([
                'Alpha project',
                'Zulu project',
                'Later project',
            ]);
    }

    public function test_projects_can_be_searched_by_name(): void
    {
        $this->actingAs(User::factory()->create());
        Project::factory()->create(['name' => 'Searchable project']);
        Project::factory()->create(['name' => 'Unrelated project']);

        $this->get(route('projects.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee('Searchable project')
            ->assertDontSee('Unrelated project');
    }

    public function test_project_name_with_non_latin_characters_can_be_stored_and_searched(): void
    {
        $this->actingAs(User::factory()->create());
        $name = 'Proyecto Ñandú プロジェクト';

        $this->post(route('projects.store'), [
            'name' => $name,
            'start_date' => '2026-01-01',
        ])->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('PRO_projects', ['name' => $name]);

        $this->get(route('projects.index', ['search' => $name]))
            ->assertOk()
            ->assertSee($name);
    }

    public function test_project_search_is_kept_in_pagination_links(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 30) as $index) {
            Project::factory()->create(['name' => "Repeat project {$index}"]);
        }

        $this->get(route('projects.index', ['search' => 'Repeat']))
            ->assertOk()
            ->assertSee('search=Repeat', false);
    }

    public function test_projects_can_be_searched_by_start_and_end_date(): void
    {
        $this->actingAs(User::factory()->create());
        Project::factory()->create([
            'name' => 'Project starts on date',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);
        Project::factory()->create([
            'name' => 'Project ends on date',
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-01',
        ]);
        Project::factory()->create([
            'name' => 'Project on other dates',
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-03',
        ]);

        $this->get(route('projects.index', ['search' => '2026-10-01']))
            ->assertOk()
            ->assertSee('Project starts on date')
            ->assertSee('Project ends on date')
            ->assertDontSee('Project on other dates');
    }

    public function test_project_list_shows_pagination_when_more_than_one_page_exists(): void
    {
        $this->actingAs(User::factory()->create());
        for ($index = 1; $index <= ListQueryBase::PER_PAGE + 1; $index++) {
            Project::factory()->create(['name' => "Project {$index}"]);
        }

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('project-pagination', false);
    }

    public function test_project_list_shows_epics_count_and_comments_count(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Epic::factory()->count(3)->for($project)->create();
        EpicComment::factory()->count(5)->for(Epic::factory()->for($project)->create())->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSeeInOrder(['project-epics-count-'.$project->id, '3'])
            ->assertSeeInOrder(['project-comments-count-'.$project->id, '5']);
    }

    public function test_project_list_header_offers_state_tabs_with_record_counts(): void
    {
        $this->actingAs(User::factory()->create());
        Project::factory()->count(2)->create();
        Project::factory()->archived()->create();
        Project::factory()->count(3)->trashed()->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('data-test="project-breadcrumbs"', false)
            ->assertSeeInOrder([
                'href="'.route('dashboard').'"',
                __('Dashboard'),
                __('Projects'),
            ], false)
            ->assertSee('data-test="project-heading"', false)
            ->assertSeeInOrder([
                'data-test="project-active-link"',
                'aria-current="page"',
                'data-test="project-archived-link"',
                'data-test="project-trash-link"',
            ], false)
            ->assertSeeInOrder(['data-test="project-active-link"', '>2</span>'], false)
            ->assertSeeInOrder(['data-test="project-archived-link"', '>1</span>'], false)
            ->assertSeeInOrder(['data-test="project-trash-link"', '>3</span>'], false);

        $this->get(route('projects.archived.index'))
            ->assertOk()
            ->assertSeeInOrder([__('Dashboard'), __('Projects'), __('Archived')], false);

        $this->get(route('projects.trash.index'))
            ->assertOk()
            ->assertSeeInOrder([__('Dashboard'), __('Projects'), __('Trash')], false);
    }

    public function test_project_row_edits_from_the_name(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create(['name' => 'Editable from the row']);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-test="project-name-'.$project->id.'"',
                'editProject(JSON.parse(',
            ], false)
            ->assertSee('data-test="project-comments-count-'.$project->id.'"></span>', false)
            ->assertSee('data-test="project-delete-'.$project->id.'"', false);
    }

    public function test_guests_cannot_create_update_or_delete_projects(): void
    {
        $project = Project::factory()->create(['name' => 'Existing project']);

        $this->post(route('projects.store'), [
            'name' => 'Guest project',
            'start_date' => '2026-01-01',
        ])->assertRedirect(route('login'));

        $this->put(route('projects.update', $project), [
            'name' => 'Updated project',
            'start_date' => '2026-01-01',
        ])->assertRedirect(route('login'));

        $this->delete(route('projects.destroy', $project))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('PRO_projects', 1);
        $this->assertDatabaseHas('PRO_projects', ['id' => $project->id, 'name' => 'Existing project']);
        $this->assertNotSoftDeleted($project);
    }

    public function test_creation_requires_authorization(): void
    {
        $user = User::create([
            'name' => 'Test user',
            'email' => 'test@example.test',
            'password' => 'unused',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        Gate::before(static fn (): bool => false);

        $this->post(route('projects.store'), [])->assertForbidden();

        $this->assertDatabaseCount('PRO_projects', 0);
    }

    private function createDuplicateNameAfterValidation(string $name): void
    {
        $table = (new Project)->getTable();
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($name, $table, &$competitorCreated): void {
            if (
                $competitorCreated
                || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')
                || ! str_contains(strtolower($query->sql), strtolower($table))
                || ! in_array($name, $query->bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Project::factory()->create(['name' => $name, 'start_date' => '2026-01-01']);
        });
    }
}
