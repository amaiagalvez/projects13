@php
    $calendarLabels = [
        'projects' => __('Projects'),
        'epics' => __('Epics'),
        'personal' => __('Personal'),
    ];
    $blankEvent = ['id' => null, 'title' => '', 'notes' => '', 'start_date' => $day->toDateString(), 'end_date' => ''];
    // A form that failed validation comes back open, with what the user typed.
    $returning = old('_personal_event_form') !== null
        ? ['id' => old('_personal_event_id') ?: null, 'title' => old('title', ''), 'notes' => old('notes', ''),
            'start_date' => old('start_date', ''), 'end_date' => old('end_date', '')]
        : null;
@endphp

<x-layouts::app :title="__('Calendar')">
    <div class="flex flex-col gap-6" data-calendar-root
        x-data="{
            form: @js($blankEvent),
            confirmingDelete: false,
            updateUrl: @js(route('personal-events.update', ['personal_event' => '__ID__'])),
            create() { this.confirmingDelete = false; this.form = @js($blankEvent); this.$flux.modal('personal-event').show() },
            edit(event) { this.confirmingDelete = false; this.form = { ...event }; this.$flux.modal('personal-event').show() },
            action() { return this.form.id ? this.updateUrl.replace('__ID__', this.form.id) : @js(route('personal-events.store')) },
        }"
        @if ($returning !== null) x-init="$nextTick(() => edit(@js($returning)))" @endif>
        <flux:heading level="1" size="xl" class="sr-only" data-test="calendar-heading">{{ __('Calendar') }}</flux:heading>

        <x-basics13::list.flash prefix="calendar" />

        <div class="grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
            {{-- Side panel: create, jump to a day, and which calendars show. --}}
            <aside class="flex flex-col gap-6 lg:border-e lg:border-zinc-200 lg:pe-6 dark:lg:border-zinc-700" aria-label="{{ __('Calendars') }}">
                <flux:button variant="primary" icon="calendar-days" x-on:click="create()" class="w-full" data-test="calendar-create-event">
                    {{ __('New event') }}
                </flux:button>

                <section aria-labelledby="calendar-mini-heading" data-test="calendar-mini">
                    <h2 id="calendar-mini-heading" class="mb-2 text-sm font-semibold first-letter:uppercase">{{ $day->isoFormat('MMMM YYYY') }}</h2>
                    <div class="grid grid-cols-7 gap-y-1 text-center text-xs">
                        @foreach (range(0, 6) as $weekday)
                            <span class="py-1 font-medium text-zinc-500 dark:text-zinc-400" aria-hidden="true">{{ $day->startOfWeek()->addDays($weekday)->isoFormat('dd') }}</span>
                        @endforeach
                        @for ($pad = 1; $pad < $miniMonth[0]['date']->dayOfWeekIso; $pad++)
                            <span></span>
                        @endfor
                        @foreach ($miniMonth as $cell)
                            @php($selected = $cell['date']->isSameDay($day))
                            <a href="{{ $dayUrl($cell['date']) }}" wire:navigate
                                aria-label="{{ $cell['date']->isoFormat('LL') }}"
                                @if ($cell['date']->isToday()) aria-current="date" @endif
                                @class([
                                    'mx-auto inline-flex size-8 items-center justify-center rounded-lg tabular-nums focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:outline-none',
                                    'bg-brand-700 font-semibold text-white dark:bg-brand-400 dark:text-zinc-950' => $cell['date']->isToday(),
                                    'bg-white font-medium shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700' => ! $cell['date']->isToday() && ($selected || $cell['events'] !== []),
                                    'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => ! $cell['date']->isToday() && ! $selected && $cell['events'] === [],
                                ])>{{ $cell['date']->day }}</a>
                        @endforeach
                    </div>
                </section>

                {{-- Each calendar is a link that hides or shows it, so the choice stays in the URL. --}}
                <nav aria-labelledby="calendar-toggles-heading" class="border-t border-zinc-200 pt-4 dark:border-zinc-700" data-test="calendar-toggles">
                    <h2 id="calendar-toggles-heading" class="mb-2 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Calendars') }}</h2>
                    <ul class="flex flex-col gap-1">
                        @foreach ($toggles as $toggle)
                            @php($dot = ['projects' => 'bg-emerald-600', 'epics' => 'bg-violet-500', 'personal' => 'bg-sky-500'][$toggle['calendar']])
                            <li>
                                <a href="{{ $toggle['url'] }}" wire:navigate role="checkbox" aria-checked="{{ $toggle['visible'] ? 'true' : 'false' }}"
                                    data-test="calendar-toggle-{{ $toggle['calendar'] }}"
                                    class="flex h-11 items-center gap-3 rounded-lg px-2 text-sm hover:bg-zinc-100 focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:outline-none sm:h-10 dark:hover:bg-zinc-800">
                                    <span @class([
                                        'inline-flex size-5 shrink-0 items-center justify-center rounded border',
                                        'border-brand-700 bg-brand-700 text-white dark:border-brand-400 dark:bg-brand-400 dark:text-zinc-950' => $toggle['visible'],
                                        'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-900' => ! $toggle['visible'],
                                    ])>
                                        @if ($toggle['visible'])
                                            <flux:icon name="check" variant="micro" />
                                        @endif
                                    </span>
                                    <span class="size-2 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
                                    <span class="flex-1 font-medium">{{ $calendarLabels[$toggle['calendar']] }}</span>
                                    <span class="text-xs text-zinc-500 tabular-nums dark:text-zinc-400"
                                        data-test="calendar-count-{{ $toggle['calendar'] }}">{{ $toggle['count'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                    <div class="flex items-center gap-2">
                        <flux:button :href="$navigation['previous']" wire:navigate variant="ghost" size="sm" square icon="chevron-left"
                            :aria-label="__('Previous')" title="{{ __('Previous') }}" data-test="calendar-previous" />
                        <flux:button :href="$navigation['today']" wire:navigate size="sm" data-test="calendar-today">
                            {{ __('Today') }}
                        </flux:button>
                        <flux:button :href="$navigation['next']" wire:navigate variant="ghost" size="sm" square icon="chevron-right"
                            :aria-label="__('Next')" title="{{ __('Next') }}" data-test="calendar-next" />
                        <h2 class="ms-2 text-2xl font-semibold first-letter:uppercase" data-test="calendar-period">{{ $title }}</h2>
                    </div>

                    <nav aria-label="{{ __('Layout') }}" data-test="calendar-tabs"
                        class="inline-flex rounded-xl border border-zinc-200 bg-zinc-100 p-1 dark:border-zinc-700 dark:bg-zinc-800">
                        @foreach ($views as $tab)
                            <a href="{{ $tab['url'] }}" wire:navigate data-test="{{ $tab['test'] }}"
                                @if ($tab['current']) aria-current="page" @endif
                                @class([
                                    'inline-flex h-9 items-center rounded-lg px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:outline-none',
                                    'bg-white text-brand-800 shadow-sm dark:bg-zinc-900 dark:text-brand-300' => $tab['current'],
                                    'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => ! $tab['current'],
                                ])>{{ $tab['label'] }}</a>
                        @endforeach
                    </nav>
                </div>

                @include('projects13::calendars.'.$view)
            </div>
        </div>

        <flux:modal name="personal-event" class="w-full max-w-lg" data-test="personal-event-modal">
            <form method="POST" x-bind:action="action()" class="flex flex-col gap-4" data-test="personal-event-form">
                @csrf
                <input type="hidden" name="_personal_event_form" value="1">
                <input type="hidden" name="_personal_event_id" x-bind:value="form.id ?? ''">
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>

                <flux:heading size="lg">
                    <span x-show="!form.id">{{ __('New event') }}</span>
                    <span x-show="form.id" x-cloak>{{ __('Edit event') }}</span>
                </flux:heading>

                <flux:field>
                    <flux:label for="personal-event-title">{{ __('Title') }}</flux:label>
                    <flux:input id="personal-event-title" name="title" x-model="form.title" required
                        maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" data-test="personal-event-title" />
                    <flux:error name="title" />
                </flux:field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label for="personal-event-start-date">{{ __('Start date') }}</flux:label>
                        <flux:input id="personal-event-start-date" type="date" name="start_date" x-model="form.start_date"
                            required data-test="personal-event-start-date" />
                        <flux:error name="start_date" />
                    </flux:field>
                    <flux:field>
                        <flux:label for="personal-event-end-date" badge="{{ __('Optional') }}">{{ __('End date') }}</flux:label>
                        <flux:input id="personal-event-end-date" type="date" name="end_date" x-model="form.end_date"
                            x-bind:min="form.start_date" data-test="personal-event-end-date" />
                        <flux:error name="end_date" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label for="personal-event-notes" badge="{{ __('Optional') }}">{{ __('Notes') }}</flux:label>
                    <flux:textarea id="personal-event-notes" name="notes" x-model="form.notes" rows="3"
                        data-test="personal-event-notes" />
                    <flux:error name="notes" />
                </flux:field>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" data-test="personal-event-save">{{ __('Save') }}</flux:button>
                </div>
            </form>

            <form method="POST" x-show="form.id" x-cloak x-bind:action="action()" class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700"
                data-test="personal-event-delete-form">
                @csrf
                @method('DELETE')
                <flux:button type="button" variant="danger" icon="trash" x-show="!confirmingDelete"
                    x-on:click="confirmingDelete = true" data-test="personal-event-delete">
                    {{ __('Delete event') }}
                </flux:button>
                <div x-show="confirmingDelete" x-cloak role="alertdialog" aria-labelledby="personal-event-delete-question"
                    class="flex flex-wrap items-center justify-between gap-3" data-test="personal-event-delete-confirmation">
                    <flux:text id="personal-event-delete-question" class="font-medium">{{ __('Delete this event? It cannot be undone.') }}</flux:text>
                    <div class="flex gap-2">
                        <flux:button type="button" variant="ghost" x-on:click="confirmingDelete = false"
                            data-test="personal-event-delete-cancel">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="danger" data-test="personal-event-delete-confirm">{{ __('Delete event') }}</flux:button>
                    </div>
                </div>
            </form>
        </flux:modal>
    </div>
</x-layouts::app>
