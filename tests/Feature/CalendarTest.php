<?php

namespace Projects13\Tests\Feature;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Basics13\Models\PersonalEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The calendar screen: project and epic dates turned into events next to the user's own ones. It
 * only reads the domain, keeps its whole state in the URL, and draws the same events in its four
 * views, each one bounded by the period it names.
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-12');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('calendar'))->assertRedirect(route('login'));
    }

    public function test_the_month_of_today_shows_by_default_with_every_calendar(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('calendar'))
            ->assertOk()
            ->assertSee('data-test="calendar-heading"', false)
            ->assertSee('data-test="calendar-month"', false)
            ->assertSee('data-test="calendar-day-2026-06-30"', false)
            ->assertDontSee('data-test="calendar-day-2026-07-01"', false)
            ->assertSee('data-test="calendar-create-event"', false)
            ->assertSeeInOrder(['aria-checked="true"', 'data-test="calendar-toggle-projects"'], false);
    }

    public function test_only_active_dated_records_of_active_projects_become_events(): void
    {
        $project = Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        $archived = Project::factory()->archived()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        $trashed = Project::factory()->trashed()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        $epicOfArchived = Epic::factory()->for($archived)->create(['start_date' => '2026-06-05', 'end_date' => '2026-06-06']);
        $undated = Epic::factory()->for($project)->withoutDates()->create();
        $milestone = Epic::factory()->for($project)->create(['start_date' => '2026-06-10', 'end_date' => null]);
        $archivedEpic = Epic::factory()->for($project)->archived()->create(['start_date' => '2026-06-10', 'end_date' => '2026-06-11']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('calendar'))->assertOk();

        $response->assertSee('data-test="calendar-event-project-'.$project->id.'"', false);
        foreach (['project-'.$archived->id, 'project-'.$trashed->id, 'epic-'.$epicOfArchived->id, 'epic-'.$undated->id, 'epic-'.$archivedEpic->id] as $hidden) {
            $response->assertDontSee('data-test="calendar-event-'.$hidden.'"', false);
        }

        // A single date is a one-day event: it shows on its day and on no other.
        $day = $this->actingAs(User::factory()->admin()->create());
        $day->get(route('calendar', ['view' => 'day', 'date' => '2026-06-10']))
            ->assertSee('data-test="calendar-event-epic-'.$milestone->id.'"', false);
        $day->get(route('calendar', ['view' => 'day', 'date' => '2026-06-11']))
            ->assertDontSee('data-test="calendar-event-epic-'.$milestone->id.'"', false);
    }

    public function test_an_account_without_a_role_cannot_open_the_calendar(): void
    {
        $project = Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        $epic = Epic::factory()->for($project)->create(['start_date' => '2026-06-05', 'end_date' => '2026-06-06']);
        $user = User::factory()->create();
        $own = PersonalEvent::factory()->on('2026-06-12')->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('calendar'))
            ->assertForbidden()
            ->assertDontSee('data-test="calendar-event-personal-'.$own->id.'"', false)
            ->assertDontSee('data-test="calendar-event-project-'.$project->id.'"', false)
            ->assertDontSee('data-test="calendar-event-epic-'.$epic->id.'"', false);
    }

    public function test_hiding_one_calendar_leaves_the_others_and_showing_it_again_brings_it_back(): void
    {
        $user = User::factory()->admin()->create();
        $project = Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        $epic = Epic::factory()->for($project)->create(['start_date' => '2026-06-05', 'end_date' => '2026-06-06']);
        $own = PersonalEvent::factory()->on('2026-06-12')->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->get(route('calendar'))
            ->assertSee('href="'.e(route('calendar', ['date' => '2026-06-12', 'calendars' => 'projects,personal'])).'"', false);

        $this->get(route('calendar', ['calendars' => 'projects,personal']))
            ->assertDontSee('data-test="calendar-event-epic-'.$epic->id.'"', false)
            ->assertSee('data-test="calendar-event-project-'.$project->id.'"', false)
            ->assertSee('data-test="calendar-event-personal-'.$own->id.'"', false)
            ->assertSee('href="'.e(route('calendar', ['date' => '2026-06-12'])).'"', false);

        $this->get(route('calendar', ['calendars' => 'none']))
            ->assertDontSee('data-test="calendar-event-', false);
    }

    public function test_navigation_keeps_the_view_the_period_and_the_hidden_calendars(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('calendar', ['view' => 'week', 'date' => '2026-06-12', 'calendars' => 'epics']))
            ->assertOk()
            ->assertSee('data-test="calendar-week"', false)
            ->assertSee('data-test="calendar-day-2026-06-08"', false)
            ->assertSee('data-test="calendar-day-2026-06-14"', false)
            ->assertSee(e(route('calendar', ['view' => 'week', 'date' => '2026-06-05', 'calendars' => 'epics'])), false)
            ->assertSee(e(route('calendar', ['view' => 'week', 'date' => '2026-06-19', 'calendars' => 'epics'])), false)
            ->assertSee(e(route('calendar', ['view' => 'week', 'calendars' => 'epics'])), false)
            ->assertSee(e(route('calendar', ['view' => 'year', 'date' => '2026-06-12', 'calendars' => 'epics'])), false);
    }

    public function test_a_stale_state_in_the_url_falls_back_to_the_defaults(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('calendar', ['view' => 'decade', 'date' => '2026-02-30', 'calendars' => 'bogus']))
            ->assertOk()
            ->assertSee('data-test="calendar-month"', false)
            ->assertSee('data-test="calendar-day-2026-06-12"', false);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function views(): array
    {
        return [
            'month' => ['month', '2026-06-01', '2026-05-31'],
            'week' => ['week', '2026-06-08', '2026-06-01'],
            'day' => ['day', '2026-06-12', '2026-06-11'],
            'year' => ['year', '2026-01-01', '2025-12-31'],
        ];
    }

    #[DataProvider('views')]
    public function test_every_view_draws_the_event_of_its_period_and_nothing_outside(string $view, string $inside, string $outside): void
    {
        $user = User::factory()->admin()->create();
        $event = PersonalEvent::factory()->on('2026-06-12')->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->get(route('calendar', ['view' => $view, 'date' => $inside]))
            ->assertSee('data-test="calendar-event-personal-'.$event->id.'"', false);
        $this->get(route('calendar', ['view' => $view, 'date' => $outside]))
            ->assertDontSee('data-test="calendar-event-personal-'.$event->id.'"', false);
    }

    public function test_an_event_across_two_weeks_shows_in_both(): void
    {
        $user = User::factory()->admin()->create();
        $event = PersonalEvent::factory()->on('2026-06-13', '2026-06-16')->create(['user_id' => $user->id]);
        $this->actingAs($user);

        foreach (['2026-06-12', '2026-06-15'] as $date) {
            $this->get(route('calendar', ['view' => 'week', 'date' => $date]))
                ->assertSee('data-test="calendar-event-personal-'.$event->id.'"', false);
        }
    }

    public function test_personal_events_are_written_from_the_calendar_and_domain_ones_are_not(): void
    {
        $user = User::factory()->admin()->create();
        $project = Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        $this->actingAs($user)->from(route('calendar'));

        $this->post(route('personal-events.store'), ['title' => 'Dentista', 'start_date' => '2026-06-12'])
            ->assertRedirect(route('calendar'));
        $event = PersonalEvent::query()->sole();
        $this->get(route('calendar'))->assertSee('data-test="calendar-event-personal-'.$event->id.'"', false);

        $foreign = PersonalEvent::factory()->on('2026-06-12')->create(['user_id' => User::factory()->admin()->create()->id]);
        $this->put(route('personal-events.update', $foreign), ['title' => 'X', 'start_date' => '2026-06-12'])->assertForbidden();
        $this->delete(route('personal-events.destroy', $foreign))->assertForbidden();

        // A project is not a personal event: its id does not resolve to one.
        $this->delete('/personal-events/project-'.$project->id)->assertNotFound();
        $this->assertModelExists($project);
    }

    public function test_a_rejected_form_comes_back_open_with_what_was_typed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('calendar'))
            ->followingRedirects()
            ->post(route('personal-events.store'), ['_personal_event_form' => 1, 'title' => 'Viaje', 'start_date' => '2026-06-12', 'end_date' => '2026-06-01'])
            ->assertOk()
            ->assertSee('x-init="$nextTick(() => edit(', false)
            ->assertSee('Viaje');
    }

    public function test_a_full_day_cell_shows_the_first_events_and_links_the_rest_to_the_day(): void
    {
        $user = User::factory()->admin()->create();
        $events = PersonalEvent::factory()->count(5)->on('2026-06-12')->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->get(route('calendar'))
            ->assertSee(__('+:count more', ['count' => 2]))
            ->assertSee('href="'.e(route('calendar', ['view' => 'day', 'date' => '2026-06-12'])).'"', false);

        $day = $this->get(route('calendar', ['view' => 'day', 'date' => '2026-06-12']))
            ->assertDontSee(__('+:count more', ['count' => 2]));
        foreach ($events as $event) {
            $day->assertSee('data-test="calendar-event-personal-'.$event->id.'"', false);
        }
    }

    public function test_a_full_month_of_the_year_links_the_rest_to_the_month(): void
    {
        $user = User::factory()->admin()->create();
        PersonalEvent::factory()->count(7)->on('2026-06-12')->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->get(route('calendar', ['view' => 'year', 'date' => '2026-06-12']))
            ->assertSee('data-test="calendar-month-2026-06-more"', false)
            ->assertSee(__('+:count more', ['count' => 2]))
            ->assertSee('href="'.e(route('calendar', ['date' => '2026-06-01'])).'"', false);
    }

    public function test_the_side_panel_counts_every_calendar_even_a_hidden_one(): void
    {
        $user = User::factory()->admin()->create();
        $project = Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        Epic::factory()->for($project)->count(2)->create(['start_date' => '2026-06-05', 'end_date' => '2026-06-06']);
        $this->actingAs($user);

        $this->get(route('calendar', ['calendars' => 'projects,personal']))
            ->assertSeeInOrder(['data-test="calendar-count-projects"', '>1</span>'], false)
            ->assertSeeInOrder(['aria-checked="false"', 'data-test="calendar-toggle-epics"', 'data-test="calendar-count-epics"', '>2</span>'], false)
            ->assertSeeInOrder(['data-test="calendar-count-personal"', '>0</span>'], false);
    }

    public function test_opening_the_screen_writes_nothing(): void
    {
        Project::factory()->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-20']);
        $before = [Project::query()->count(), Epic::query()->count(), PersonalEvent::query()->count()];

        foreach (['month', 'week', 'day', 'year'] as $view) {
            $this->actingAs(User::factory()->admin()->create())->get(route('calendar', ['view' => $view]))->assertOk();
        }

        $this->assertSame($before, [Project::query()->count(), Epic::query()->count(), PersonalEvent::query()->count()]);
    }
}
