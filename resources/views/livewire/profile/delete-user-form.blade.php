<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header>
        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">
            {{ __('Konto löschen') }}
        </h2>

        <p class="mt-1 text-xs font-medium text-slate-500">
            {{ __('Sobald Ihr Konto gelöscht ist, werden alle Ressourcen und Daten dauerhaft entfernt.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Konto unwiderruflich löschen') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-base font-extrabold text-slate-900">
                {{ __('Sind Sie sicher, dass Sie Ihr Konto löschen möchten?') }}
            </h2>

            <p class="mt-2 text-xs font-medium text-slate-500">
                {{ __('Geben Sie zur Bestätigung Ihr Passwort ein.') }}
            </p>

            <div class="mt-4">
                <x-input-label for="password" value="{{ __('Passwort') }}" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Passwort eingeben') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Abbrechen') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Ja, Konto löschen') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
