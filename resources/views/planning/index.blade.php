@php
    $counts = $planning['counts'];
    $emptyMessage = $search['value'] === ''
        ? __('There is nothing to plan yet.')
        : __('No record matches your search.');
@endphp

<x-layouts::app :title="__('Planning')">
    <div class="flex flex-col gap-6" data-planning-root>
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
            <div class="min-w-0">
                <flux:heading level="1" size="xl" data-test="planning-heading">{{ __('Planning') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Active projects and epics, ordered by deadline.') }}
                </flux:text>
            </div>

            <flux:button variant="primary" icon="plus" :href="route('projects.index', ['create' => 1])"
                wire:navigate data-test="planning-create-project">
                {{ __('New project') }}
            </flux:button>
        </div>

        {{-- Counts of the active slice. They describe the whole slice, not the search result, so
             they stay put while a term narrows the list below them. --}}
        <section aria-labelledby="planning-summary-heading">
            <flux:heading id="planning-summary-heading" size="sm" level="2" class="sr-only">
                {{ __('Summary') }}
            </flux:heading>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-test="planning-summary">
                <x-projects13::planning.summary-card icon="briefcase" :label="__('Active projects')" :count="$counts['projects']"
                    test="planning-summary-projects" :url="route('projects.index')" />
                <x-projects13::planning.summary-card icon="flag" :label="__('Active epics')" :count="$counts['epics']"
                    test="planning-summary-epics" :url="route('epics.index')" />
                <x-projects13::planning.summary-card icon="clock" :label="__('Overdue')" :count="$counts['overdue']"
                    test="planning-summary-overdue" tone="overdue" />
            </div>
        </section>

        {{-- Search on the left, the layout on the right. --}}
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
            <form method="GET" action="{{ $search['action'] }}" class="w-full sm:max-w-md">
                <div class="relative">
                    <flux:input name="search" :aria-label="__('Search')" :placeholder="$search['placeholder']"
                        :value="$search['value']" maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}"
                        icon="magnifying-glass" class="w-full" data-test="planning-search" />
                    @if ($search['value'] !== '')
                        <div class="absolute inset-e-1 top-1/2 z-10 -translate-y-1/2">
                            <flux:button :href="$search['action']" variant="ghost" size="sm" square icon="x-mark"
                                title="{{ __('Clear') }}" :aria-label="__('Clear')" data-test="planning-search-clear" />
                        </div>
                    @endif
                </div>
                <input type="hidden" name="view" value="{{ $timeline ? 'timeline' : 'roadmap' }}">
            </form>

            <x-basics13::list.tabs :tabs="$views" prefix="planning" :label="__('Layout')" />
        </div>

        <x-projects13::planning.board :groups="$planning['groups']" :timeline="$timeline"
            :months="$planning['timeline']['months']" :today="$planning['timeline']['today']"
            :paginator="$planning['paginator']" :message="$emptyMessage">
            <flux:button variant="primary" icon="plus" :href="route('projects.index', ['create' => 1])"
                wire:navigate data-test="planning-empty-create">
                {{ __('New project') }}
            </flux:button>
        </x-planning.board>
    </div>
</x-layouts::app>