{{--
    One event on the calendar. The calendar it belongs to is said in words as well as in colour.
    A personal event opens the edit form; a project or epic only links to its list, because the
    calendar never edits domain records.
--}}
@props(['event', 'compact' => false])

@php
    $tone = match ($event['calendar']) {
        'projects' => 'border-emerald-600 bg-emerald-100/70 text-emerald-950 hover:bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-50',
        'epics' => 'border-violet-500 bg-violet-100/70 text-violet-950 hover:bg-violet-100 dark:bg-violet-900/40 dark:text-violet-50',
        default => 'border-sky-500 bg-sky-100/70 text-sky-950 hover:bg-sky-100 dark:bg-sky-900/40 dark:text-sky-50',
    };
    $calendarLabel = match ($event['calendar']) {
        'projects' => __('Project'),
        'epics' => __('Epic'),
        default => __('Personal'),
    };
    $classes = 'block w-full truncate rounded-md border-s-4 px-2 py-0.5 text-start text-xs font-medium focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:outline-none '.$tone;
@endphp

@if ($event['personal'] !== null)
    <button type="button" class="{{ $classes }}" data-test="calendar-event-{{ $event['key'] }}"
        x-on:click="edit(@js($event['personal']))" title="{{ $event['title'] }}">
        <span class="sr-only">{{ $calendarLabel }}:</span>{{ $event['title'] }}
    </button>
@else
    <a href="{{ $event['url'] }}" wire:navigate class="{{ $classes }}" data-test="calendar-event-{{ $event['key'] }}"
        title="{{ $calendarLabel }}: {{ $event['title'] }}">
        <span class="sr-only">{{ $calendarLabel }}:</span>{{ $event['title'] }}
    </a>
@endif
