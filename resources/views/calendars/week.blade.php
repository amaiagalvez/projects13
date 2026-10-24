{{-- Seven columns from Monday to Sunday; on narrow screens the days stack. --}}
<div class="grid overflow-hidden rounded-xl border border-zinc-200 md:grid-cols-7 dark:border-zinc-700" data-test="calendar-week">
    @foreach ($days as $cell)
        @php($isToday = $cell['date']->isToday())
        <section @class([
            'flex min-h-48 flex-col gap-1 border-b border-zinc-200 p-2 md:border-e md:border-b-0 md:last:border-e-0 dark:border-zinc-700',
            'bg-brand-50/60 dark:bg-brand-900/20' => $isToday,
        ]) data-test="calendar-day-{{ $cell['date']->toDateString() }}"
            aria-label="{{ $cell['date']->isoFormat('dddd, LL') }}">
            <a href="{{ $dayUrl($cell['date']) }}" wire:navigate class="mb-1 flex items-baseline gap-2"
                @if ($isToday) aria-current="date" @endif>
                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ $cell['date']->isoFormat('ddd') }}</span>
                <span @class([
                    'inline-flex size-7 items-center justify-center rounded-full text-sm tabular-nums',
                    'bg-brand-700 font-semibold text-white dark:bg-brand-400 dark:text-zinc-950' => $isToday,
                    'text-zinc-800 dark:text-zinc-200' => ! $isToday,
                ])>{{ $cell['date']->day }}</span>
            </a>
            <x-projects13::calendar.events :events="$cell['events']" :limit="$limit"
                :more-url="$dayUrl($cell['date'])" test="calendar-day-{{ $cell['date']->toDateString() }}" />
        </section>
    @endforeach
</div>
