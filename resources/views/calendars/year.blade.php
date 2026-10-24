{{-- Twelve months, each with the events that touch it, listed once. --}}
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-test="calendar-year">
    @foreach ($months as $month)
        <section class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
            data-test="calendar-month-{{ $month['date']->format('Y-m') }}">
            <h3 class="mb-1 text-sm font-semibold first-letter:uppercase">
                <a href="{{ $monthUrl($month['date']) }}" wire:navigate class="hover:underline">{{ $month['date']->isoFormat('MMMM') }}</a>
            </h3>
            @if ($month['events'] === [])
                <flux:text class="text-xs">{{ __('No events.') }}</flux:text>
            @else
                <x-projects13::calendar.events :events="$month['events']" :limit="$limit"
                    :more-url="$monthUrl($month['date'])" test="calendar-month-{{ $month['date']->format('Y-m') }}" />
            @endif
        </section>
    @endforeach
</div>
