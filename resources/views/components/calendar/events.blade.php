{{--
    The events of one cell, cut at $limit: the rest collapse into a "+N more" link to the view that
    lists them all. Without a limit every event is drawn.
--}}
@props(['events', 'limit' => null, 'moreUrl' => null, 'test'])

@php
    $shown = $limit === null ? $events : array_slice($events, 0, $limit);
    $hidden = count($events) - count($shown);
@endphp

@foreach ($shown as $event)
    <x-projects13::calendar.event :event="$event" />
@endforeach
@if ($hidden > 0)
    <a href="{{ $moreUrl }}" wire:navigate data-test="{{ $test }}-more"
        class="self-start rounded px-1 text-xs whitespace-nowrap font-medium text-zinc-600 hover:text-zinc-900 hover:underline focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:outline-none dark:text-zinc-400 dark:hover:text-white">
        {{ __('+:count more', ['count' => $hidden]) }}
    </a>
@endif
