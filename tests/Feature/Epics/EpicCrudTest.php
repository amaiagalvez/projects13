<?php

namespace Projects13\Tests\Feature\Epics;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Projects13\Http\Requests\EpicRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_can_view_epics_index(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('epics.index'));

        $response->assertOk()
            ->assertSee('Start date')
            ->assertDontSee('projects13::messages.', false);
    }

    public function test_empty_index_offers_a_create_button_that_resets_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('x-on:click="createEpic()" data-test="epic-empty-create"', false);
    }

    public function test_a_search_without_results_offers_no_create_shortcut(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Epic::factory()->create(['name' => 'Alpha']);

        $this->get(route('epics.index', ['search' => 'Nothing like it']))
            ->assertOk()
            ->assertSee(__('basics13::messages.no_matching_records'))
            ->assertDontSee('data-test="epic-empty-create"', false);
    }

    public function test_can_create_an_epic(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $project = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $payload = [
            'name' => 'New epic',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'project_id' => $project->id,
        ];

        $response = $this->post(route('epics.store'), $payload);

        $response->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('basics13::messages.created'));

        $this->assertDatabaseHas('PRO_epics', ['name' => 'New epic', 'project_id' => $project->id]);
    }

    public function test_json_request_creates_an_epic_and_returns_its_identity(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->postJson(route('epics.store'), [
            'name' => 'JSON epic',
            'project_id' => $project->id,
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'JSON epic');

        $this->assertDatabaseHas('PRO_epics', [
            'name' => 'JSON epic',
            'project_id' => $project->id,
        ]);
    }

    public function test_json_request_updates_an_epic_and_returns_its_identity(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $epic = Epic::factory()->for($project)->create(['name' => 'Original epic']);

        $this->putJson(route('epics.update', $epic), [
            'name' => 'Updated JSON epic',
            'project_id' => $project->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ])
            ->assertOk()
            ->assertJsonPath('id', $epic->id)
            ->assertJsonPath('name', 'Updated JSON epic');

        $this->assertDatabaseHas('PRO_epics', [
            'id' => $epic->id,
            'name' => 'Updated JSON epic',
        ]);
    }

    public function test_a_project_trashed_after_validation_is_reported_on_the_field(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = Project::factory()->create();

        // Runs once the request has passed validation, just before the controller locks the project.
        $this->app->afterResolving(EpicRequest::class, static function () use ($project): void {
            $project->delete();
        });

        $this->postJson(route('epics.store'), ['name' => 'Late epic', 'project_id' => $project->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->assertDatabaseMissing('PRO_epics', ['name' => 'Late epic']);
    }

    public function test_can_update_an_epic(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $project = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $epic = Epic::factory()->for($project)->create(['name' => 'Original']);

        $payload = [
            'name' => 'Updated',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'project_id' => $project->id,
        ];

        $response = $this->put(route('epics.update', $epic), $payload);

        $response->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('basics13::messages.updated'));

        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id, 'name' => 'Updated']);
    }

    public function test_can_delete_an_epic_moves_to_trash(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $epic = Epic::factory()->create();

        $response = $this->delete(route('epics.destroy', $epic));

        $response->assertRedirect(route('epics.index'))
            ->assertSessionHas('status', __('basics13::messages.moved_to_trash'));

        $this->assertSoftDeleted($epic);
    }

    public function test_cannot_move_an_epic_with_comments_to_trash(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $epic = Epic::factory()->create();
        $comment = EpicComment::factory()->for($epic)->create();

        $this->delete(route('epics.destroy', $epic))
            ->assertRedirect(route('epics.index'))
            ->assertSessionHas('error', __('Cannot be deleted while it has related records.'));

        $this->assertModelExists($epic);
        $this->assertModelExists($comment);
    }

    public function test_can_create_epic_without_dates(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $project = Project::factory()->create();

        $payload = [
            'name' => 'Undated epic',
            'project_id' => $project->id,
        ];

        $this->post(route('epics.store'), $payload)
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('PRO_epics', [
            'name' => 'Undated epic',
            'start_date' => null,
            'end_date' => null,
            'project_id' => $project->id,
        ]);
    }

    public function test_can_create_epic_with_name_repeating_another_project(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $projectA = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $projectB = Project::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        Epic::factory()->for($projectA)->create(['name' => 'Existing epic']);

        $payload = [
            'name' => 'Existing epic',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'project_id' => $projectB->id,
        ];

        $this->post(route('epics.store'), $payload)
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('PRO_epics', 2);
    }

    public function test_can_create_epic_with_null_end_date_when_project_has_null_end_date(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $project = Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => null]);

        $payload = [
            'name' => 'Epic in open-ended project',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-01',
            'project_id' => $project->id,
        ];

        $this->post(route('epics.store'), $payload)
            ->assertRedirect(route('epics.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '2027-06-01',
            Epic::query()->where('name', 'Epic in open-ended project')->sole()->end_date?->toDateString(),
        );
    }
}
