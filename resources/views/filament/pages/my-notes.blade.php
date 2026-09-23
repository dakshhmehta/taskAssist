<x-filament-panels::page>
    <div
        x-data
        x-on:keydown.window.ctrl.s.prevent.debounce.400ms="$wire.save()"
        x-on:keydown.window.meta.s.prevent.debounce.400ms="$wire.save()"
    >
        <form wire:submit="save" class="grid gap-y-6">
            {{ $this->form }}

            <div class="flex items-center gap-x-3">
                <x-filament::button type="submit">
                    Save
                </x-filament::button>

                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Tip: press Ctrl+S / &#8984;S to save
                </span>
            </div>
        </form>
    </div>
</x-filament-panels::page>
