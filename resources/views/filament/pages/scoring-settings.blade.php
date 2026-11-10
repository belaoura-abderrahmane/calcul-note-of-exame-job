<x-filament-panels::page>
    <form wire:submit="save" class="fi-sc-form" style="display:flex;flex-direction:column;gap:1.5rem">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-check" size="lg">
                حفظ وإعادة حساب النقاط
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
