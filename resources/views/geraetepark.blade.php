<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Geräte-, Maschinen- & Fuhrparkverwaltung') }}
        </h2>
    </x-slot>

    <div class="py-2">
        <livewire:equipment-manager />
    </div>
</x-app-layout>
