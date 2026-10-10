@php
    $emptyMessage = $search['value'] === ''
        ? __('There is nothing to plan yet.')
        : __('No record matches your search.');
@endphp

{{-- One scope for the page: the expander state of the board lives here, so the toggle and the
     epic rows it hides are read from the same place without a second Alpine root. --}}
<x-layouts::app :title="__('Timeline')">
    <div class="flex flex-col gap-6" data-timeline-root x-data="{ collapsed: {} }">
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
            <div class="min-w-0">
                <flux:heading level="1" size="xl" data-test="timeline-heading">{{ __('Timeline') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Active projects and their epics, over time.') }}
                </flux:text>
            </div>

            <flux:button variant="primary" icon="plus" :href="route('projects.index', ['create' => 1])"
                wire:navigate data-test="timeline-create-project">
                {{ __('New project') }}
            </flux:button>
        </div>

        <x-basics13::list.search :search="$search" prefix="timeline" />

        <x-projects13::timeline.board :entries="$timeline['entries']" :timeline="$timeline['timeline']"
            :paginator="$timeline['paginator']" :message="$emptyMessage">
            <flux:button variant="primary" icon="plus" :href="route('projects.index', ['create' => 1])"
                wire:navigate data-test="timeline-empty-create">
                {{ __('New project') }}
            </flux:button>
        </x-timeline.board>
    </div>
</x-layouts::app>
