{{--
    The timeline board: the active projects of the page as rows, each one followed by the active
    epics drawn underneath it, every record a bar on a single month axis.

    One table for the whole board, so the axis is read once and every bar lines up under it: the
    chart column is the same width in every row, and the month blocks behind each bar are the same
    shares the axis labels are. The chart column and its bars carry no text of their own, so they
    are hidden from assistive technology and the dates and the status badge next to the name are
    always the real answer. The expander is a real button: it says whether the epics below are
    shown and which rows it controls.

    Column widths: dates and status hug their content (`w-px`) so the name is the only flexible
    column, with a 16rem floor on desktop, and the chart claims the other half of the width. On
    narrow screens the chart column collapses and the bar moves under the name, so nothing is
    lost by losing the column.
--}}
@props(['entries', 'timeline', 'paginator', 'message'])

<div class="flex flex-col gap-6">
    @if ($entries->isEmpty())
        <div
            class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white shadow-card px-4 py-12 text-center dark:border-zinc-800 dark:bg-zinc-900"
            data-test="timeline-empty-state">
            <flux:icon.calendar-days class="size-8 text-zinc-400 dark:text-zinc-500" aria-hidden="true" />
            <flux:text class="text-zinc-600 dark:text-zinc-400">{{ $message }}</flux:text>
            {{ $slot }}
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-card dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full border-collapse text-sm">
                <caption class="sr-only">{{ __('Timeline') }}</caption>
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th scope="col"
                            class="px-3 py-2 text-start text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            {{ __('Name') }}
                        </th>
                        <th scope="col"
                            class="w-px px-3 py-2 text-start text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            {{ __('Dates') }}
                        </th>
                        <th scope="col"
                            class="w-px px-3 py-2 text-start text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            {{ __('Status') }}
                        </th>
                        <th scope="col"
                            class="hidden w-1/2 min-w-[20rem] py-2 text-start text-xs font-medium text-zinc-600 lg:table-cell dark:text-zinc-400">
                            {{ __('Timeline') }}
                        </th>
                    </tr>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700" aria-hidden="true">
                        <td colspan="3" class="hidden lg:table-cell"></td>
                        <td class="relative hidden py-2 lg:table-cell" data-test="timeline-axis">
                            <div class="flex">
                                @foreach ($timeline['months'] as $month)
                                    <div class="truncate border-s border-zinc-200 px-1 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                                        style="width: {{ $month['width'] }}%">{{ $month['label'] }}</div>
                                @endforeach
                            </div>
                            <span data-test="timeline-today" title="{{ __('Today') }}"
                                class="absolute inset-y-0 w-px bg-amber-500 dark:bg-amber-400"
                                style="inset-inline-start: {{ $timeline['today'] }}%"></span>
                        </td>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($entries as $entry)
                        @php $row = $entry['row']; @endphp
                        <tr data-test="{{ $row['test'] }}"
                            @if ($entry['kind'] === 'epic')
                                x-show="!collapsed[{{ $entry['projectId'] }}]"
                                class="bg-zinc-50/50 dark:bg-zinc-800/30"
                            @endif
                            class="border-b border-zinc-100 last:border-0 hover:bg-zinc-50 dark:border-zinc-800/70 dark:hover:bg-zinc-800/40 transition-opacity duration-150">
                            <th scope="row" class="max-w-0 px-3 py-2.5 text-start font-normal lg:min-w-[16rem]">
                                @if ($entry['kind'] === 'project')
                                    <span class="flex items-start gap-1">
                                        @if ($entry['controls'] !== [])
                                            <button type="button" data-test="timeline-toggle-{{ $row['id'] }}"
                                                x-on:click="collapsed[{{ $row['id'] }}] = !collapsed[{{ $row['id'] }}]"
                                                aria-expanded="true"
                                                x-bind:aria-expanded="collapsed[{{ $row['id'] }}] ? 'false' : 'true'"
                                                aria-controls="{{ implode(' ', $entry['controls']) }}"
                                                aria-label="{{ __('Toggle epics of :project', ['project' => $row['name']]) }}"
                                                title="{{ __('Toggle epics of :project', ['project' => $row['name']]) }}"
                                                class="-ms-1 mt-0.5 shrink-0 rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 dark:focus-visible:ring-brand-400"
                                                x-bind:class="{ 'bg-brand-50 dark:bg-brand-900/20': !collapsed[{{ $row['id'] }}] }">
                                                <flux:icon.chevron-right class="size-4 transition-transform"
                                                    x-bind:class="collapsed[{{ $row['id'] }}] ? '' : 'rotate-90'" />
                                            </button>
                                        @else
                                            {{-- Spacer for alignment when no epics --}}
                                            <span class="-ms-1 mt-0.5 shrink-0 w-8" aria-hidden="true"></span>
                                        @endif

                                        <a href="{{ $row['url'] }}" wire:navigate title="{{ $row['name'] }}"
                                            class="block min-w-0 truncate rounded text-start font-medium text-zinc-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 dark:text-zinc-100 dark:focus-visible:ring-brand-400">{{ $row['name'] }}</a>
                                    </span>

                                    {{-- Nothing above the row name says who it belongs to unless an
                                         integration contributed a trail, so the label only appears
                                         when there is one. --}}
                                    @if (($row['trail'][0] ?? null) !== null)
                                        <span class="mt-0.5 block truncate text-xs text-zinc-500 dark:text-zinc-400"
                                            title="{{ $row['trail'][0] }}">{{ $row['trail'][0] }}</span>
                                    @endif
                                @else
                                    {{-- The epic is indented under its project rather than carrying a
                                         visible trail, so the project still reaches whoever is
                                         reading the table row by row. --}}
                                    <span class="flex items-start gap-1 ps-7 relative">
                                        {{-- Vertical connector line to project --}}
                                        <span class="absolute left-2 top-0 bottom-0 w-px bg-zinc-200 dark:bg-zinc-700"
                                            aria-hidden="true"></span>
                                        <span class="sr-only">{{ $row['trail'][0] ?? $row['name'] }}: </span>
                                        <a href="{{ $row['url'] }}" wire:navigate title="{{ $row['name'] }}"
                                            class="block min-w-0 truncate rounded text-start font-medium text-zinc-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 dark:text-zinc-100 dark:focus-visible:ring-brand-400 relative z-10">{{ $row['name'] }}</a>
                                    </span>
                                @endif

                                @if ($row['bar'])
                                    {{-- The bar, on the small layout where there is no chart column. --}}
                                    <span class="relative mt-1.5 block h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 lg:hidden"
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
                            </td>

                            <td class="relative hidden py-2 lg:table-cell">
                                {{-- The month blocks behind the bar: the same shares as the labels
                                     above, so every row reads against the same grid. --}}
                                <div class="absolute inset-0 flex" aria-hidden="true">
                                    @foreach ($timeline['months'] as $month)
                                        <div class="border-s border-zinc-100 dark:border-zinc-800"
                                            style="width: {{ $month['width'] }}%"></div>
                                    @endforeach
                                </div>

                                <div class="relative h-6" aria-hidden="true">
                                    @if ($row['bar'])
                                        <span data-test="timeline-bar-{{ $row['id'] }}"
                                            class="absolute inset-y-1 min-w-[0.375rem] rounded-full {{ $row['status'] === 'overdue' ? 'bg-amber-500' : 'bg-brand-600 dark:bg-brand-400' }}"
                                            style="inset-inline-start: {{ $row['bar']['offset'] }}%; width: {{ $row['bar']['width'] }}%"></span>
                                    @endif
                                </div>

                                <span class="absolute inset-y-0 w-px bg-amber-500/70 dark:bg-amber-400/70"
                                    aria-hidden="true"
                                    style="inset-inline-start: {{ $timeline['today'] }}%"></span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if ($paginator->hasPages())
    <nav aria-label="{{ __('Pagination') }}" data-test="timeline-pagination" class="mt-4">
        {{ $paginator->links() }}
    </nav>
@endif