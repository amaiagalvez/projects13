{{--
    One tile of the planning summary: the number, what it counts and, when the number is a
    drill-down, a link to the list it came from. The whole card is the link and its visible text is
    the accessible name, so nothing has to be announced twice.
--}}
@props(['icon', 'label', 'count', 'test', 'url' => null, 'tone' => 'default'])

@php
    $tone = match ($tone) {
        'overdue' => 'text-amber-700 dark:text-amber-300',
        default => 'text-zinc-900 dark:text-zinc-50',
    };
@endphp

<{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" wire:navigate @endif
    data-test="{{ $test }}"
    class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900 {{ $url ? 'transition-colors hover:border-zinc-300 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60' : '' }}">
    <span
        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
        <flux:icon :name="$icon" class="size-5" aria-hidden="true" />
    </span>
    <span class="min-w-0">
        <span class="block text-xl leading-tight font-semibold tabular-nums {{ $tone }}">{{ $count }}</span>
        <span class="block truncate text-sm text-zinc-600 dark:text-zinc-400">{{ $label }}</span>
    </span>
</{{ $url ? 'a' : 'div' }}>
