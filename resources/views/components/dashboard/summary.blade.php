{{--
    Dashboard body, fed by DashboardSummaryQuery: a welcome band with the state of the work and the
    two next steps, the one figure that matters this month, the supporting counts in a single card
    and the two "recent" lists. Every figure links to where it can be acted on.

    :summary  DashboardSummaryQuery::summary()
    :name     the user's name, for the greeting
--}}
@props(['summary', 'name'])

@php
    $counts = $summary['counts'];
    $metrics = [
        ['icon' => 'briefcase', 'label' => __('Active projects'), 'value' => $counts['projects'], 'url' => route('projects.index'), 'test' => 'dashboard-projects'],
        ['icon' => 'flag', 'label' => __('Active epics'), 'value' => $counts['epics'], 'url' => route('epics.index'), 'test' => 'dashboard-epics'],
        ['icon' => 'clock', 'label' => __('Overdue'), 'value' => $counts['overdue'], 'url' => route('planning'), 'test' => 'dashboard-overdue'],
        ['icon' => 'chat-bubble-left', 'label' => __('Comments this week'), 'value' => $summary['commentsThisWeek'], 'url' => route('epics.index'), 'test' => 'dashboard-comments'],
    ];
    $card = 'rounded-2xl border border-zinc-200 bg-white shadow-card dark:border-zinc-800 dark:bg-zinc-900';
@endphp

<div class="grid gap-6 xl:grid-cols-12">
    {{-- Welcome: where the work stands and what to do next. --}}
    <section class="{{ $card }} flex flex-col gap-4 p-6 xl:col-span-8" aria-labelledby="dashboard-welcome-heading"
        data-test="dashboard-welcome">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-brand-700 dark:text-brand-300">{{ __('Work overview') }}</p>
            <h2 id="dashboard-welcome-heading" class="font-display text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                {{ __('Hello, :name', ['name' => $name]) }}
            </h2>
            <p class="text-zinc-600 dark:text-zinc-400" data-test="dashboard-status">
                {{ trans_choice('{0} Nothing is overdue.|{1} One project or epic is overdue.|[2,*] :count projects and epics are overdue.', $counts['overdue'], ['count' => $counts['overdue']]) }}
            </p>
        </div>
        <div class="mt-auto flex flex-wrap gap-2">
            <flux:button variant="primary" icon="plus" :href="route('projects.index', ['create' => 1])" wire:navigate
                data-test="dashboard-create-project">
                {{ __('New project') }}
            </flux:button>
            <flux:button variant="outline" icon="calendar-days" :href="route('planning')" wire:navigate
                data-test="dashboard-planning">
                {{ __('View planning') }}
            </flux:button>
        </div>
    </section>

    {{-- The figure this page is about. --}}
    <a href="{{ route('planning') }}" wire:navigate data-test="dashboard-due-this-month"
        class="flex flex-col justify-between gap-6 rounded-2xl bg-linear-to-br from-brand-600 to-brand-800 p-6 text-white shadow-card transition-shadow hover:shadow-raised xl:col-span-4">
        <span class="text-sm font-medium text-white/90">{{ __('Epics ending this month') }}</span>
        <span class="font-display text-5xl font-semibold tabular-nums">{{ $summary['dueThisMonth'] }}</span>
        <span class="inline-flex items-center gap-1 text-sm font-medium text-white/90">
            {{ __('View planning') }}
            <flux:icon.arrow-right variant="micro" class="rtl:rotate-180" aria-hidden="true" />
        </span>
    </a>

    {{-- Supporting counts: one card, four cells. --}}
    <section class="{{ $card }} xl:col-span-12" aria-label="{{ __('Summary') }}">
        <div class="grid divide-y divide-zinc-200 sm:grid-cols-2 sm:divide-y-0 xl:grid-cols-4 xl:divide-x dark:divide-zinc-800">
            @foreach ($metrics as $metric)
                <a href="{{ $metric['url'] }}" wire:navigate data-test="{{ $metric['test'] }}"
                    class="flex items-center gap-4 p-5 transition-colors first:rounded-t-2xl last:rounded-b-2xl hover:bg-zinc-50 sm:first:rounded-tl-2xl xl:first:rounded-l-2xl xl:last:rounded-r-2xl dark:hover:bg-zinc-800/60">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-400/10 dark:text-brand-300">
                        <flux:icon :icon="$metric['icon']" variant="mini" aria-hidden="true" />
                    </span>
                    <span class="flex min-w-0 flex-col">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $metric['label'] }}</span>
                        <span class="font-display text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $metric['value'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Next deadlines. --}}
    <section class="{{ $card }} flex flex-col xl:col-span-7" aria-labelledby="dashboard-deadlines-heading"
        data-test="dashboard-deadlines">
        <h2 id="dashboard-deadlines-heading" class="border-b border-zinc-200 px-5 py-4 font-display text-lg font-semibold dark:border-zinc-800">
            {{ __('Next deadlines') }}
        </h2>
        @forelse ($summary['deadlines'] as $deadline)
            <a href="{{ $deadline['url'] }}" wire:navigate
                class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-3 transition-colors last:rounded-b-2xl last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60">
                <span class="flex min-w-0 flex-col">
                    <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $deadline['name'] }}</span>
                    <span class="truncate text-sm text-zinc-600 dark:text-zinc-400">{{ $deadline['project'] }}</span>
                </span>
                <x-basics13::list.local-time :datetime="$deadline['endDate']" format="date"
                    class="shrink-0 text-sm tabular-nums text-zinc-700 dark:text-zinc-300" />
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ __('No upcoming deadlines.') }}</p>
        @endforelse
    </section>

    {{-- Latest comments. --}}
    <section class="{{ $card }} flex flex-col xl:col-span-5" aria-labelledby="dashboard-comments-heading"
        data-test="dashboard-latest-comments">
        <h2 id="dashboard-comments-heading" class="border-b border-zinc-200 px-5 py-4 font-display text-lg font-semibold dark:border-zinc-800">
            {{ __('Latest comments') }}
        </h2>
        @forelse ($summary['comments'] as $comment)
            <a href="{{ $comment['url'] }}" wire:navigate
                class="flex gap-3 border-b border-zinc-200 px-5 py-3 transition-colors last:rounded-b-2xl last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60">
                <span aria-hidden="true"
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 dark:bg-brand-400/10 dark:text-brand-300">{{ $comment['initials'] }}</span>
                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span class="flex items-baseline justify-between gap-2 text-sm">
                        <span class="truncate font-medium text-zinc-900 dark:text-white">{{ $comment['author'] }}</span>
                        {{-- How long ago, worded by the browser; the exact moment stays in the title. --}}
                        <time datetime="{{ $comment['dateTime'] }}" x-data
                            x-init="$el.title = window.formatLocalDateTime($el.dateTime, 'datetime'); $el.textContent = window.formatRelativeTime($el.dateTime)"
                            class="shrink-0 text-xs text-zinc-600 dark:text-zinc-400"></time>
                    </span>
                    <span class="truncate text-xs text-zinc-600 dark:text-zinc-400">{{ $comment['epic'] }}</span>
                    <span class="line-clamp-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $comment['body'] }}</span>
                </span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ __('No comments yet.') }}</p>
        @endforelse
    </section>
</div>
