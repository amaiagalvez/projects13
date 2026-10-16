<?php

namespace Projects13\Tests\Feature\Epics;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Projects13\Models\EpicComment;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\QueryException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Transformers\EpicListTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_authenticated_users_can_create_update_and_delete_epics(): void
    {
        $this->actingAs(User::factory()->create());
        // The epic dates below are fixed, so the projects they are validated against need a window
        // that always covers them: the factory would otherwise randomise them away.
        $project = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $otherProject = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->post(route('epics.store'), [
            'name' => '  Checkout flow  ',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'project_id' => $project->id,
        ])->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('Record created successfully.'));

        $epic = Epic::query()->firstOrFail();
        $this->assertSame('Checkout flow', $epic->name);

        $this->assertTrue($epic->project->is($project));

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('epic-create-button')
            ->assertSee('aria-labelledby="epic-form-heading"', false)
            ->assertSee('id="epic-form-heading"', false)
            ->assertSee('aria-labelledby="epic-confirm-heading"', false)
            ->assertSee('id="epic-confirm-heading"', false)
            ->assertSee('epic-edit-'.$epic->id)
            ->assertSee('Checkout flow')
            ->assertSee('title="Checkout flow"', false)
            ->assertSee($project->name);

        $this->put(route('epics.update', $epic), [
            'name' => 'Payment flow',
            'start_date' => '',
            'end_date' => '',
            'project_id' => $otherProject->id,
        ])->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('Record updated successfully.'));

        $this->assertDatabaseHas('PRO_epics', [
            'id' => $epic->id,
            'name' => 'Payment flow',
            'start_date' => null,
            'end_date' => null,
            'project_id' => $otherProject->id,
        ]);

        $this->delete(route('epics.destroy', $epic))
            ->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('Record moved to trash.'));

        $this->assertSoftDeleted($epic);
    }

    public function test_epic_store_persists_only_validated_attributes(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->post(route('epics.store'), [
            'name' => 'Validated epic',
            'project_id' => $project->id,
            'id' => 999999,
            'created_at' => '2000-01-01 00:00:00',
        ])->assertRedirect(route('epics.index'));

        $this->assertDatabaseHas('PRO_epics', [
            'name' => 'Validated epic',
            'project_id' => $project->id,
        ]);
        $this->assertDatabaseMissing('PRO_epics', [
            'name' => 'Validated epic',
            'id' => 999999,
        ]);
        $this->assertDatabaseMissing('PRO_epics', [
            'name' => 'Validated epic',
            'created_at' => '2000-01-01 00:00:00',
        ]);
    }

    public function test_json_creation_returns_the_created_record(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->postJson(route('epics.store'), [
            'name' => '  Trimmed epic  ',
            'project_id' => $project->id,
        ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'name'])
            ->assertJsonPath('name', 'Trimmed epic');

        $this->assertDatabaseHas('PRO_epics', ['name' => 'Trimmed epic', 'project_id' => $project->id]);
    }

    public function test_json_update_returns_the_updated_record(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create(['name' => 'Original epic']);

        $this->putJson(route('epics.update', $epic), [
            'name' => '  Renamed epic  ',
            'project_id' => $project->id,
        ])
            ->assertOk()
            ->assertJsonStructure(['id', 'name'])
            ->assertJsonPath('name', 'Renamed epic');

        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id, 'name' => 'Renamed epic']);
    }

    public function test_deleted_name_conflict_does_not_create_until_reuse_is_confirmed(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create(['name' => 'Checkout flow']);
        $epic->delete();
        $data = [
            'name' => $epic->name,
            'project_id' => $project->id,
        ];

        $this->post(route('epics.store'), $data)
            ->assertRedirect(route('epics.index'))
            ->assertSessionHas('deleted_epic_conflict', ['id' => $epic->id, 'name' => $epic->name]);
        $this->assertDatabaseCount('PRO_epics', 1);

        $this->post(route('epics.store'), [...$data, 'reuse_deleted_name' => true])
            ->assertSessionHas('status', __('Record created successfully.'));

        $this->assertDatabaseCount('PRO_epics', 2);
        $this->assertSoftDeleted($epic);
    }

    public function test_deleted_name_json_conflict_returns_409(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();
        $epic->delete();
        $message = __('basics13::messages.deleted_name_conflict', ['name' => $epic->name]);

        $this->postJson(route('epics.store'), [
            'name' => $epic->name,
            'project_id' => $project->id,
        ])->assertConflict()->assertExactJson([
            'message' => $message,
            'errors' => ['name' => [$message]],
        ]);

        $this->assertDatabaseCount('PRO_epics', 1);
    }

    public function test_a_project_can_have_multiple_epics(): void
    {
        $project = Project::factory()->create();

        Epic::factory()->count(2)->for($project)->create();

        $this->assertCount(2, $project->epics);
    }

    public function test_epic_list_transformer_renders_the_parents_it_is_bound_to(): void
    {
        $project = Project::factory()->create(['name' => 'Bound project']);
        $epic = Epic::factory()->for($project)->create();

        $list = app(EpicListTransformer::class)->active(
            new LengthAwarePaginator([$epic], 1, 15),
            '',
        );

        $this->assertSame('Bound project', data_get($list, 'rows.0.project'));
        $this->assertSame(
            'Bound project',
            data_get($list, 'rows.0.actions.0.payload.project_label'),
        );
    }

    /**
     * An epic cannot exist without a project: the column is NOT NULL, the foreign key restricts
     * deletion and the relation resolves soft-deleted projects, so the row always renders a name.
     */
    public function test_a_project_with_epics_cannot_be_permanently_deleted(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $project->forceDelete();

        $this->assertModelExists($epic);
        $this->assertDatabaseHas('PRO_epics', [
            'id' => $epic->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_empty_epic_list_uses_the_shared_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('epics.index'))
            ->assertOk()
            ->assertSee('data-test="list-empty-state"', false)
            ->assertSee(__('No epics yet.'));
    }

    public function test_database_rejects_duplicate_epic_names_in_the_same_project(): void
    {
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create(['name' => 'Shared epic']);

        $this->expectException(QueryException::class);

        Epic::factory()->for($project)->create(['name' => 'Shared epic']);
    }

    public function test_database_allows_the_same_epic_name_in_different_projects_and_after_soft_delete(): void
    {
        $project = Project::factory()->create();
        $deletedEpic = Epic::factory()->for($project)->trashed()->create(['name' => 'Shared epic']);
        $activeEpic = Epic::factory()->for($project)->create(['name' => 'Shared epic']);
        // The third epic needs a project of its own: the factory adopts whichever project it
        // finds first, which would make the name collide inside the project above.
        $otherProject = Project::factory()->create(['name' => 'Other project']);
        $otherProjectEpic = Epic::factory()->for($otherProject)->create(['name' => 'Shared epic']);

        $this->assertSoftDeleted($deletedEpic);
        $this->assertModelExists($activeEpic);
        $this->assertModelExists($otherProjectEpic);
    }

    public function test_store_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $name = 'Concurrent epic';
        $this->createDuplicateNameAfterValidation($name, $project);

        $this->postJson(route('epics.store'), ['name' => $name, 'project_id' => $project->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('PRO_epics', 1);
        $this->assertDatabaseHas('PRO_epics', ['name' => $name, 'project_id' => $project->id]);
    }

    public function test_update_converts_a_concurrent_duplicate_name_into_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create(['name' => 'Original epic']);
        $name = 'Concurrent epic';
        $this->createDuplicateNameAfterValidation($name, $project);

        $this->putJson(route('epics.update', $epic), ['name' => $name, 'project_id' => $project->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id, 'name' => 'Original epic']);
        $this->assertDatabaseHas('PRO_epics', ['name' => $name, 'project_id' => $project->id]);
        $this->assertDatabaseCount('PRO_epics', 2);
    }

    /**
     * The soft delete has to happen inside a transaction even when no rule asks for one, because a
     * guard registered by an optional integration package takes its row lock in a transaction of its
     * own and would otherwise let go of it before the delete is written.
     */
    public function test_direct_model_delete_runs_the_soft_delete_inside_a_transaction(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();
        $transactionLevel = null;

        DB::listen(static function (QueryExecuted $query) use (&$transactionLevel): void {
            if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'PRO_epics')) {
                $transactionLevel = DB::transactionLevel();
            }
        });

        $initialTransactionLevel = DB::transactionLevel();

        $epic->delete();

        $this->assertSame($initialTransactionLevel + 1, $transactionLevel);
        $this->assertSoftDeleted($epic);
    }

    public function test_invalid_epic_names_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        $this->postJson(route('epics.store'), ['name' => 'Ab', 'project_id' => $project->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('PRO_epics', 0);
    }

    public function test_epics_are_ordered_by_start_date_end_date_and_project(): void
    {
        $this->actingAs(User::factory()->create());
        $alphaProject = Project::factory()->create(['name' => 'Alpha project']);
        $zuluProjectA = Project::factory()->create(['name' => 'Zulu project A']);
        $zuluProjectZ = Project::factory()->create(['name' => 'Zulu project Z']);

        $dates = ['start_date' => '2026-10-01', 'end_date' => '2026-10-03'];
        Epic::factory()->for($alphaProject)->create(['name' => 'Epic later start', 'start_date' => '2026-10-02', 'end_date' => '2026-10-03']);
        Epic::factory()->for($alphaProject)->create(['name' => 'Epic later end', 'start_date' => '2026-10-01', 'end_date' => '2026-10-04']);
        Epic::factory()->for($zuluProjectZ)->create(['name' => 'Epic zulu project Z', ...$dates]);
        Epic::factory()->for($zuluProjectA)->create(['name' => 'Epic zulu project A', ...$dates]);
        Epic::factory()->for($alphaProject)->create(['name' => 'Epic alpha project', ...$dates]);

        // Fill the first page so the undated epic, which always sorts last, lands on the second one.
        for ($index = 6; $index <= ListQueryBase::PER_PAGE; $index++) {
            Epic::factory()->for($alphaProject)->create([
                'name' => "Epic filler {$index}",
                ...$dates,
            ]);
        }

        Epic::factory()->withoutDates()->for($alphaProject)->create(['name' => 'Epic without dates']);

        $this->get(route('epics.index'))
            ->assertSeeInOrder([
                'Epic alpha project',
                'Epic zulu project A',
                'Epic zulu project Z',
                'Epic later end',
                'Epic later start',
            ]);

        $this->get(route('epics.index', ['page' => 2]))
            ->assertSee('Epic without dates');
    }

    public function test_epics_can_be_searched_by_project_name(): void
    {
        $this->actingAs(User::factory()->create());
        Epic::factory()->for(Project::factory()->state(['name' => 'Website']))->create(['name' => 'Matching epic']);
        // Both parents are pinned: the factories adopt whichever record they find first, which
        // would hang the other epic off Website as well.
        Epic::factory()->for(Project::factory()->state(['name' => 'Other project']))->create(['name' => 'Other epic']);

        $this->get(route('epics.index', ['search' => 'Website']))
            ->assertOk()
            ->assertSee('Matching epic')
            ->assertDontSee('Other epic');
    }

    public function test_epics_can_be_searched_by_their_name(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create(['name' => 'Searchable epic']);
        Epic::factory()->for($project)->create(['name' => 'Unrelated epic']);

        $this->get(route('epics.index', ['search' => 'Searchable epic']))
            ->assertOk()
            ->assertSee('Searchable epic')
            ->assertDontSee('Unrelated epic');
    }

    public function test_epic_name_with_non_latin_characters_can_be_stored_and_searched(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $name = 'Ñandú プロジェクト';

        $this->post(route('epics.store'), [
            'name' => $name,
            'project_id' => $project->id,
        ])->assertRedirect(route('epics.index'));

        $this->assertDatabaseHas('PRO_epics', ['name' => $name, 'project_id' => $project->id]);

        $this->get(route('epics.index', ['search' => $name]))
            ->assertOk()
            ->assertSee($name);
    }

    public function test_epic_search_is_kept_in_pagination_links(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();

        foreach (range(1, 6) as $index) {
            Epic::factory()->for($project)->create(['name' => "Repeat epic {$index}"]);
        }

        $this->get(route('epics.index', ['search' => 'Repeat']))
            ->assertOk()
            ->assertSee('search=Repeat', false);
    }

    public function test_epics_can_be_searched_by_start_and_end_date(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create([
            'name' => 'Epic starts on date',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);
        Epic::factory()->for($project)->create([
            'name' => 'Epic ends on date',
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-01',
        ]);
        Epic::factory()->for($project)->create([
            'name' => 'Epic on other dates',
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-03',
        ]);

        $this->get(route('epics.index', ['search' => '2026-10-01']))
            ->assertOk()
            ->assertSee('Epic starts on date')
            ->assertSee('Epic ends on date')
            ->assertDontSee('Epic on other dates');
    }

    public function test_epic_list_shows_pagination_when_more_than_one_page_exists(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        for ($index = 1; $index <= ListQueryBase::PER_PAGE + 1; $index++) {
            Epic::factory()->for($project)->create();
        }

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('epic-pagination', false);
    }

    public function test_epic_list_shows_the_number_of_comments(): void
    {
        $this->actingAs(User::factory()->create());
        $epic = Epic::factory()->create();
        EpicComment::factory()->count(3)->for($epic)->create();

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSeeInOrder(['epic-comments-count-'.$epic->id, '3']);
    }

    public function test_epic_list_header_offers_the_state_tabs_with_the_record_count_of_each_one(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        Epic::factory()->count(2)->for($project)->create();
        Epic::factory()->for($project)->archived()->create();
        Epic::factory()->count(3)->for($project)->trashed()->create();

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('data-test="epic-breadcrumbs"', false)
            ->assertSeeInOrder([
                'href="'.route('dashboard').'"',
                __('Dashboard'),
                __('Epics'),
            ], false)
            ->assertSee('data-test="epic-heading"', false)
            ->assertSeeInOrder([
                'data-test="epic-active-link"',
                'aria-current="page"',
                'data-test="epic-archived-link"',
                'data-test="epic-trash-link"',
            ], false)
            ->assertSeeInOrder(['data-test="epic-active-link"', '>2</span>'], false)
            ->assertSeeInOrder(['data-test="epic-archived-link"', '>1</span>'], false)
            ->assertSeeInOrder(['data-test="epic-trash-link"', '>3</span>'], false);

        $this->get(route('epics.archived.index'))
            ->assertOk()
            ->assertSeeInOrder([__('Dashboard'), __('Epics'), __('Archived')], false);

        $this->get(route('epics.trash.index'))
            ->assertOk()
            ->assertSeeInOrder([__('Dashboard'), __('Epics'), __('Trash')], false);
    }

    public function test_epic_row_edits_from_the_name(): void
    {
        $this->actingAs(User::factory()->create());
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create(['name' => 'Editable from the row']);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-test="epic-name-'.$epic->id.'"',
                'editEpic(JSON.parse(',
            ], false)
            ->assertSee('data-test="epic-comments-count-'.$epic->id.'"></span>', false)
            ->assertSee('data-test="epic-delete-'.$epic->id.'"', false);
    }

    public function test_guests_are_redirected_to_login_from_the_epics_list(): void
    {
        $this->get(route('epics.index'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_create_update_or_delete_epics(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create(['name' => 'Existing epic']);

        $this->post(route('epics.store'), [
            'name' => 'Guest epic',
            'project_id' => $project->id,
        ])->assertRedirect(route('login'));

        $this->put(route('epics.update', $epic), [
            'name' => 'Updated epic',
            'project_id' => $project->id,
        ])->assertRedirect(route('login'));

        $this->delete(route('epics.destroy', $epic))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('PRO_epics', 1);
        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id, 'name' => 'Existing epic']);
        $this->assertNotSoftDeleted($epic);
    }

    private function createDuplicateNameAfterValidation(string $name, Project $project): void
    {
        $table = (new Epic)->getTable();
        $competitorCreated = false;

        DB::listen(static function (QueryExecuted $query) use ($name, $table, $project, &$competitorCreated): void {
            $sql = strtolower(ltrim($query->sql));
            $bindings = $query->bindings;

            if (
                $competitorCreated
                || ! str_starts_with($sql, 'select')
                || ! str_contains($sql, strtolower($table))
                || ! in_array($name, $bindings, true)
                // project_id in query bindings is a string, so compare as string
                || ! in_array((string) $project->id, $bindings, true)
            ) {
                return;
            }

            $competitorCreated = true;
            Epic::factory()->for($project)->create(['name' => $name]);
        });
    }
}
