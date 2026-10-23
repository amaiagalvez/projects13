<x-layouts::app :title="__('Work dashboard')">
    <div class="flex flex-col gap-6">
        <flux:heading size="xl" level="1" class="font-display text-3xl! font-semibold! tracking-tight">
            {{ __('Work dashboard') }}
        </flux:heading>

        <x-projects13::dashboard.summary :summary="$summary" />
    </div>
</x-layouts::app>
