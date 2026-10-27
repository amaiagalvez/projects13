<?php

namespace Projects13\Tests\Browser\Calendars;

use Laravel\Dusk\Browser;
use Illuminate\Support\Str;
use Basics13\Models\PersonalEvent;
use Projects13\Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * The personal event modal of the calendar is filled by Alpine: one modal serves create and edit,
 * and the form action, the method and the delete form follow the event it was opened for.
 */
class PersonalEventModalTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_an_event_is_created_edited_and_deleted_from_the_modal(): void
    {
        $user = $this->admin();
        $title = 'Dusk event '.Str::uuid()->toString();
        $renamed = $title.' (2)';
        $day = now()->startOfMonth()->addDays(9)->toDateString();

        $this->browse(function (Browser $browser) use ($user, $title, $renamed, $day): void {
            $browser->loginAs($user)
                ->visit('/calendar?view=day&date='.$day.'&calendars=personal')
                ->click('[data-test="calendar-create-event"]')
                ->waitFor('dialog[open] [data-test="personal-event-title"]')
                ->assertMissing('dialog[open] [data-test="personal-event-delete"]')
                ->assertInputValue('dialog[open] [data-test="personal-event-start-date"]', $day)
                ->type('dialog[open] [data-test="personal-event-title"]', $title)
                ->waitForReload(fn (Browser $browser) => $browser->click('dialog[open] [data-test="personal-event-save"]'))
                ->assertSee($title);

            $event = PersonalEvent::query()->where('title', $title)->sole();

            $browser->click('[data-test="calendar-event-personal-'.$event->id.'"]')
                ->waitFor('dialog[open] [data-test="personal-event-delete"]')
                ->assertInputValue('dialog[open] [data-test="personal-event-title"]', $title)
                ->clear('dialog[open] [data-test="personal-event-title"]')
                ->type('dialog[open] [data-test="personal-event-title"]', $renamed)
                ->waitForReload(fn (Browser $browser) => $browser->click('dialog[open] [data-test="personal-event-save"]'))
                ->assertSee($renamed);

            $this->assertSame(1, PersonalEvent::query()->count());

            $browser->click('[data-test="calendar-event-personal-'.$event->id.'"]')
                ->waitFor('dialog[open] [data-test="personal-event-delete"]')
                ->click('dialog[open] [data-test="personal-event-delete"]')
                ->waitFor('dialog[open] [data-test="personal-event-delete-confirmation"]')
                ->click('dialog[open] [data-test="personal-event-delete-cancel"]')
                ->assertVisible('dialog[open] [data-test="personal-event-delete"]')
                ->click('dialog[open] [data-test="personal-event-delete"]')
                ->waitForReload(fn (Browser $browser) => $browser->click('dialog[open] [data-test="personal-event-delete-confirm"]'))
                ->assertDontSee($renamed)
                ->assertSee(__('No events on this day.'));
        });

        $this->assertDatabaseCount('BAS_personal_events', 0);
    }

    public function test_a_rejected_form_reopens_with_what_was_typed(): void
    {
        $user = $this->admin();
        $day = now()->startOfMonth()->addDays(9);

        $this->browse(function (Browser $browser) use ($user, $day): void {
            $browser->loginAs($user)
                ->visit('/calendar?view=day&date='.$day->toDateString())
                ->click('[data-test="calendar-create-event"]')
                ->waitFor('dialog[open] [data-test="personal-event-title"]')
                ->type('dialog[open] [data-test="personal-event-title"]', 'Viaje')
                // The browser's own min would block the submit, so it is lifted to reach the server rule.
                ->script("document.querySelector('[data-test=\"personal-event-end-date\"]').removeAttribute('min')");

            $browser->script("const end = document.querySelector('[data-test=\"personal-event-end-date\"]'); end.value = '".$day->subDay()->toDateString()."'; end.dispatchEvent(new Event('input'))");

            $browser->waitForReload(fn (Browser $browser) => $browser->click('dialog[open] [data-test="personal-event-save"]'))
                ->waitFor('dialog[open] [data-test="personal-event-title"]')
                ->assertInputValue('dialog[open] [data-test="personal-event-title"]', 'Viaje')
                ->assertVisible('dialog[open] [data-flux-error]:not(.hidden)');
        });

        $this->assertDatabaseCount('BAS_personal_events', 0);
    }
}
