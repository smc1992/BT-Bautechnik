<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 leading-tight flex items-center gap-2">
            <span>📈 Finanz- & Analytics Steuerzentrale</span>
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="ui-page-content">
            @livewire('analytics-manager')
        </div>
    </div>
</x-app-layout>
