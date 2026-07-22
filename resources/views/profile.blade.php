<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-xl text-slate-900 tracking-tight leading-tight">
                {{ __('Benutzerprofil & Sicherheit') }}
            </h2>
            <span class="text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 px-3 py-1 rounded-full uppercase tracking-wider shadow-xs">
                Account-Einstellungen
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 sm:p-8 bg-white border border-slate-200/80 shadow-xs rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-6 sm:p-8 bg-white border border-slate-200/80 shadow-xs rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-6 sm:p-8 bg-white border border-slate-200/80 shadow-xs rounded-2xl">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
