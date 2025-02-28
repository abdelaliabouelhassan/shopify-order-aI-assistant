<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::button wire:click="update" class="mt-4">
        Save Changes
    </x-filament::button>
</x-filament-panels::page>