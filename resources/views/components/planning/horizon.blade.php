{{--
    How far ahead the planning board looks. Like the layout, it is a set of links rather than a
    control with state: the choice belongs in the URL so it can be shared and survives a reload.
--}}
@props(['horizons'])

<nav aria-label="{{ __('Horizon') }}" data-test="planning-horizons"
    class="flex flex-wrap items-center gap-1 text-sm">
    @foreach ($horizons as $horizon)
        <a href="{{ $horizon['url'] }}" wire:navigate data-test="{{ $horizon['test'] }}"
            @if ($horizon['current']) aria-current="page" @endif
            class="inline-flex h-9 items-center rounded-md px-2.5 transition-colors {{ $horizon['current'] ? 'bg-zinc-200 font-medium text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' }}">{{ $horizon['label'] }}</a>
    @endforeach
</nav>
