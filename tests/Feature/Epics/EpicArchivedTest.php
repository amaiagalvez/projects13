<?php

namespace Projects13\Tests\Feature\Epics;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class EpicArchivedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_archived_epics_are_not_shown_in_the_active_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Epic::factory()->archived()->create(['name' => 'Dormant epic']);
        Epic::factory()->create(['name' => 'Active epic']);

        $this->get(route('epics.index'))
            ->assertOk()
            ->assertDontSee('Dormant epic');
    }

    public function test_archived_epics_are_searchable_by_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Epic::factory()->archived()->create(['name' => 'Alpha Dormant']);
        Epic::factory()->archived()->create(['name' => 'Beta Dormant']);
        Epic::factory()->create(['name' => 'Alpha Active']);

        $this->get(route('epics.archived.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Dormant')
            ->assertDontSee('Beta Dormant')
            ->assertDontSee('Alpha Active');
    }

    public function test_epics_can_be_archived_and_reactivated(): void
    {
        $epic = Epic::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patch(route('epics.archive', $epic))
            ->assertRedirect(route('epics.index'));
        $this->assertFalse($epic->refresh()->active);

        $this->patch(route('epics.archived.activate', $epic))
            ->assertRedirect(route('epics.archived.index'));
        $this->assertTrue($epic->refresh()->active);
    }

    public function test_restoring_a_trashed_archived_epic_preserves_its_archived_state(): void
    {
        $epic = Epic::factory()->archived()->trashed()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patch(route('epics.trash.restore', $epic->id))
            ->assertRedirect(route('epics.trash.index'));

        $this->assertNotSoftDeleted($epic);
        $this->assertFalse($epic->refresh()->active);
    }
}
