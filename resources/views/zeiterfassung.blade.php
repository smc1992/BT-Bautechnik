<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Mobile Zeiterfassung (MiLoG)') }}
        </h2>
    </x-slot>

    <div class="py-2">
        <livewire:time-tracking-manager />
    </div>
</x-app-layout>
