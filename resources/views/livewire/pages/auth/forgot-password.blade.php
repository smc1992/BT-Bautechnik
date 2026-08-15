<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset('email');
        session()->flash('status', __($status));
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-1">
        <div class="arch-section-label mb-1">
            <span>PASSWORT-RESET</span>
        </div>
        <h2 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight">
            Passwort vergessen?
        </h2>
        <p class="text-xs text-slate-600 font-medium leading-relaxed">
            Geben Sie Ihre E-Mail-Adresse ein. Wir senden Ihnen einen sicheren Link zum Zurücksetzen Ihres Passworts.
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-4">
        <!-- Email Address -->
        <div class="space-y-1.5">
            <label for="email" class="block text-xs font-bold text-slate-800">
                E-Mail-Adresse
            </label>
            <div class="relative">
                <input wire:model="email" id="email" 
                       type="email" name="email" 
                       required autofocus autocomplete="username" 
                       placeholder="name@bt-bautechnik.de"
                       class="w-full bg-slate-50 border border-slate-300 text-slate-950 placeholder-slate-400 focus:bg-white focus:border-slate-950 focus:ring-2 focus:ring-amber-500/20 rounded-xl pl-10 pr-4 py-2.5 text-xs font-medium transition shadow-2xs">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </span>
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="text-xs font-bold text-slate-600 hover:text-slate-950 flex items-center gap-1" href="{{ route('login') }}" wire:navigate>
                <span>← Zurück zum Login</span>
            </a>

            <button type="submit" class="px-5 py-2.5 bg-slate-950 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-md border border-slate-800 transition cursor-pointer btn-press">
                Link anfordern
            </button>
        </div>
    </form>
</div>
