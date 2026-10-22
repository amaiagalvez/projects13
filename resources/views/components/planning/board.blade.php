{{--
    The planning board: the active projects and epics grouped by the quarter they finish in, shown
    either as a roadmap or on a timeline.

    One table per group, so each group announces itself as its own table and the month axis repeats
    aligned with the rows below it. The bar column is decorative: it carries no text of its own, so
    it is hidden from assistive technology and the dates next to it are always the real answer. On
    narrow screens the table collapses to cards and the bar moves under the name, so nothing is lost
    by losing the column. The roadmap has no bar column at any width, so it keeps the bar under the
    name everywhere: only the timeline hides it behind the column that carries it.

    Column widths: dates and status hug their content (`w-px`) so the name is the only flexible
    column and takes the whole leftover width. The name keeps `max-w-0` so a long name or
    breadcrumb truncates instead of stretching the table, with a 16rem floor on desktop so it stays
    readable on the timeline, where the month axis claims nearly all of the width.
--}}
@props(['groups', 'timeline', 'months', 'today', 'paginator', 'message'])

<div class="flex flex-col gap-6">
    @forelse ($groups as $group)
        <section aria-labelledby="planning-group-{{ $group['key'] }}"
            data-test="planning-group-{{ $group['key'] }}">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <flux:heading id="planning-group-{{ $group['key'] }}" size="sm" level="2"
                    class="font-semibold">{{ $group['label'] }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 tabular-nums dark:text-zinc-400">
                    {{ __('Total: :count', ['count' => $group['count']]) }}
                </flux:text>
            </div>

            <div class="mt-2 overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-card dark:border-zinc-800 dark:bg-zinc-900">
                <table class="w-full border-collapse text-sm">
                    <caption class="sr-only">{{ $group['label'] }}</caption>
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th scope="col"
                                class="px-3 py-2 text-start text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                {{ __('Name') }}
                            </th>
                            <th scope="col"
                                class="px-3 py-2 text-start text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                {{ __('Dates') }}
                            </th>
                            <th scope="col"
                                class="px-3 py-2 text-start text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                {{ __('Status') }}
                            </th>
                            @if ($timeline)
                                <th scope="col"
                                    class="hidden w-1/2 min-w-[16rem] px-3 py-2 text-start text-xs font-medium text-zinc-600 lg:table-cell dark:text-zinc-400">
                                    {{ __('Timeline') }}
                                </th>
                            @endif
                        </tr>
                        @if ($timeline)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" aria-hidden="true">
                                <td colspan="3" class="hidden lg:table-cell"></td>
                                <td class="hidden lg:table-cell">
                                    <div class="flex">
                                        @foreach ($months as $month)
                                            <div
                                                class="truncate border-s border-zinc-200 px-1 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                                                style="width: {{ $month['width'] }}%">{{ $month['label'] }}</div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </thead>

                    <tbody>
                        @foreach ($group['rows'] as $row)
                            <tr data-test="{{ $row['test'] }}"
                                class="border-b border-zinc-100 last:border-0 hover:bg-zinc-50 dark:border-zinc-800/70 dark:hover:bg-zinc-800/40">
                                <th scope="row" class="max-w-0 lg:min-w-[16rem] px-3 py-2.5 text-start font-normal">
                                    <span class="block truncate font-medium text-zinc-900 dark:text-zinc-100"
                                        title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                                    @if ($row['trail'] !== [])
                                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400"
                                            title="{{ implode(' › ', $row['trail']) }}">
                                            @foreach ($row['trail'] as $index => $ancestor)
                                                @if ($index > 0)
                                                    <span aria-hidden="true">›</span>
                                                @endif
                                                {{ $ancestor }}@if (! $loop->last)
                                                    &nbsp;
                                                @endif
                                            @endforeach
                                        </span>
                                    @endif

                                    @if ($row['bar'])
                                        {{-- The bar where there is no column to carry it: every width of the
                                             roadmap, and the small layouts of the timeline. --}}
                                        <span
                                            class="relative mt-1.5 block h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 {{ $timeline ? 'lg:hidden' : '' }}"
                                            aria-hidden="true">
                                            <span
                                                class="absolute inset-y-0 rounded-full {{ $row['status'] === 'overdue' ? 'bg-amber-500' : 'bg-brand-600 dark:bg-brand-400' }}"
                                                style="inset-inline-start: {{ $row['bar']['offset'] }}%; width: {{ $row['bar']['width'] }}%"></span>
                                        </span>
                                    @endif
                                </th>
                                <td class="px-3 py-2.5 text-zinc-700 dark:text-zinc-300">
                                    @if ($row['start'] || $row['end'])
                                        <span class="whitespace-nowrap">
                                            @if ($row['start'])
                                                <x-basics13::list.local-time :datetime="$row['start']->toDateString()"
                                                    format="date" />
                                            @else
                                                <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                            @endif
                                            <span aria-hidden="true" class="mx-1 text-zinc-400">–</span>
                                            @if ($row['end'])
                                                <x-basics13::list.local-time :datetime="$row['end']->toDateString()"
                                                    format="date" />
                                            @else
                                                <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-zinc-400 dark:text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <x-projects13::planning.status-badge :status="$row['status']" />
                                    <x-basics13::list.count class="ms-2 align-middle" :icon="$row['count']['icon']"
                                        :count="$row['count']['value']" :url="$row['count']['url']"
                                        :label="$row['count']['label']" />
                                </td>
                                @if ($timeline)
                                    <td class="hidden lg:table-cell">
                                        @if ($row['bar'])
                                            <div class="relative h-6" aria-hidden="true">
                                                <span
                                                    class="absolute inset-y-1 min-w-[0.375rem] rounded-full {{ $row['status'] === 'overdue' ? 'bg-amber-500' : 'bg-brand-600 dark:bg-brand-400' }}"
                                                    style="inset-inline-start: {{ $row['bar']['offset'] }}%; width: {{ $row['bar']['width'] }}%"></span>
                                            </div>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div
            class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white shadow-card px-4 py-12 text-center dark:border-zinc-800 dark:bg-zinc-900"
            data-test="planning-empty-state">
            <flux:icon.calendar-days class="size-8 text-zinc-400 dark:text-zinc-500" aria-hidden="true" />
            <flux:text class="text-zinc-600 dark:text-zinc-400">{{ $message }}</flux:text>
            {{ $slot }}
        </div>
    @endforelse
</div>

    @if ($paginator && $paginator->hasPages())
        <nav aria-label="{{ __('Pagination') }}" data-test="planning-pagination" class="mt-4">
            {{ $paginator->links() }}
        </nav>
    @endif
