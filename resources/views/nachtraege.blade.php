<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Nachtragsmanagement (VOB/B § 2)') }}
        </h2>
    </x-slot>

    <div class="py-2">
        <livewire:supplement-manager />
    </div>
</x-app-layout>
