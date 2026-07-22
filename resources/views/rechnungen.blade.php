<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-xl text-slate-900 tracking-tight leading-tight">
                {{ __('Angebots- & Rechnungserstellung') }}
            </h2>
            <span class="text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">
                BT Bautechnik UG
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:invoice-creator />
        </div>
    </div>
</x-app-layout>
