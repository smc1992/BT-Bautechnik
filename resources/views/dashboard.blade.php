<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-xl text-slate-800 dark:text-white tracking-tight leading-tight">
                {{ __('Baustellen-Cockpit & Controlling') }}
            </h2>
            <span class="text-xs font-bold text-cyan-400 bg-cyan-500/10 border border-cyan-500/20 px-3 py-1 rounded-full uppercase tracking-wider">
                BT Bautechnik UG
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:dashboard />
        </div>
    </div>
</x-app-layout>
