<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Digitales Aufmaßblatt (VOB/C / DIN 18299)') }}
        </h2>
    </x-slot>

    <div class="py-2">
        <livewire:measurement-manager />
    </div>
</x-app-layout>
