{{--
    Date status of a planning row. Text plus a colour, never colour on its own, so the state is
    readable without seeing it: an overdue record and an unplanned one only differ here.
--}}
@props(['status'])

@php
    [$label, $classes] = match ($status) {
        'overdue' => [__('Overdue'), 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200'],
        'unscheduled' => [__('No date'), 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'],
        default => [__('On track'), 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'],
    };
@endphp

<span data-status="{{ $status }}"
    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>
