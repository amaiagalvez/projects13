{{--
    A month grid from Monday to Sunday. The days of the neighbouring months only pad the grid: their
    number shows greyed and they draw no events, because the view is bounded by its month.
--}}
@php
    $first = $days[0]['date'];
    $leading = array_map(static fn (int $offset) => $first->subDays($offset), range($first->dayOfWeekIso - 1, 1));
    $last = $days[count($days) - 1]['date'];
    $trailing = array_map(static fn (int $offset) => $last->addDays($offset), range(1, 7 - $last->dayOfWeekIso));
    $leading = $first->dayOfWeekIso === 1 ? [] : $leading;
    $trailing = $last->dayOfWeekIso === 7 ? [] : $trailing;
@endphp

<div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700" data-test="calendar-month">
    <div class="grid min-w-[42rem] grid-cols-7">
        @foreach (range(0, 6) as $weekday)
            <div class="border-b border-zinc-200 bg-zinc-50 py-2 text-center text-xs font-medium tracking-wide text-zinc-600 uppercase dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400">
                {{ $first->startOfWeek()->addDays($weekday)->isoFormat('ddd') }}
            </div>
        @endforeach
        @foreach ($leading as $padding)
            <div class="min-h-28 border-e border-b border-zinc-200 p-2 text-sm text-zinc-400 dark:border-zinc-700 dark:text-zinc-500" aria-hidden="true">{{ $padding->day }}</div>
        @endforeach
        @foreach ($days as $cell)
            @php($isToday = $cell['date']->isToday())
            <div @class([
                'flex min-h-28 flex-col gap-1 border-e border-b border-zinc-200 p-2 dark:border-zinc-700 [&:nth-child(7n)]:border-e-0',
                'bg-brand-50/60 dark:bg-brand-900/20' => $isToday,
            ]) data-test="calendar-day-{{ $cell['date']->toDateString() }}">
                <a href="{{ $dayUrl($cell['date']) }}" wire:navigate
                    @if ($isToday) aria-current="date" @endif
                    @class([
                        'inline-flex size-7 items-center justify-center rounded-full text-sm tabular-nums focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:outline-none',
                        'bg-brand-700 font-semibold text-white dark:bg-brand-400 dark:text-zinc-950' => $isToday,
                        'text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' => ! $isToday,
                    ])>{{ $cell['date']->day }}</a>
                <x-projects13::calendar.events :events="$cell['events']" :limit="$limit"
                    :more-url="$dayUrl($cell['date'])" test="calendar-day-{{ $cell['date']->toDateString() }}" />
            </div>
        @endforeach
        @foreach ($trailing as $padding)
            <div class="min-h-28 border-e border-b border-zinc-200 p-2 text-sm text-zinc-400 dark:border-zinc-700 dark:text-zinc-500" aria-hidden="true">{{ $padding->day }}</div>
        @endforeach
    </div>
</div>
