{{-- One day: its events as a list. --}}
@php($cell = $days[0])

<section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="calendar-day-{{ $cell['date']->toDateString() }}">
    @if ($cell['events'] === [])
        <flux:text>{{ __('No events on this day.') }}</flux:text>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($cell['events'] as $event)
                <li><x-projects13::calendar.event :event="$event" /></li>
            @endforeach
        </ul>
    @endif
</section>
