<x-filament-panels::page>
    <form wire:submit="save" class="fi-form space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Speichern
        </x-filament::button>
    </form>
</x-filament-panels::page>
