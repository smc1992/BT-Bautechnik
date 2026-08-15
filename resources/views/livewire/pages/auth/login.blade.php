<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <!-- Header inside Card -->
    <div class="space-y-1">
        <div class="arch-section-label mb-1">
            <span>COCKPIT LOGIN</span>
        </div>
        <h2 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight">
            Mitarbeiter & Partner Login
        </h2>
        <p class="text-xs text-slate-500 font-medium">
            Melden Sie sich mit Ihren Zugangsdaten an, um fortzufahren.
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <!-- Email Address -->
        <div class="space-y-1.5">
            <label for="email" class="block text-xs font-bold text-slate-800">
                E-Mail-Adresse
            </label>
            <div class="relative">
                <input wire:model="form.email" id="email" 
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
            <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="space-y-1.5" x-data="{ showPassword: false }">
            <div class="flex items-center justify-between">
                <label for="password" class="block text-xs font-bold text-slate-800">
                    Passwort
                </label>
                @if (Route::has('password.request'))
                    <a class="text-[11px] font-bold text-amber-700 hover:text-amber-800 hover:underline" href="{{ route('password.request') }}" wire:navigate>
                        Passwort vergessen?
                    </a>
                @endif
            </div>

            <div class="relative">
                <input wire:model="form.password" id="password" 
                       :type="showPassword ? 'text' : 'password'"
                       name="password"
                       required autocomplete="current-password" 
                       placeholder="••••••••••••"
                       class="w-full bg-slate-50 border border-slate-300 text-slate-950 placeholder-slate-400 focus:bg-white focus:border-slate-950 focus:ring-2 focus:ring-amber-500/20 rounded-xl pl-10 pr-10 py-2.5 text-xs font-medium transition shadow-2xs">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </span>
                
                <button type="button" @click="showPassword = !showPassword" 
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                    <!-- Eye Open Icon -->
                    <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <!-- Eye Closed Icon -->
                    <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a9.967 9.967 0 013.682-.763c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m-2.585-2.585a3 3 0 11-4.243-4.243"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"/>
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember" class="inline-flex items-center cursor-pointer">
                <input wire:model="form.remember" id="remember" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-slate-950 focus:ring-amber-500 shadow-xs cursor-pointer accent-amber-500" name="remember">
                <span class="ms-2 text-xs font-semibold text-slate-700 select-none">Angemeldet bleiben</span>
            </label>
        </div>

        <!-- Primary Submit Button -->
        <div class="pt-2">
            <button type="submit" wire:loading.attr="disabled"
                    class="w-full py-3.5 px-4 bg-slate-950 hover:bg-slate-800 text-white font-black text-xs sm:text-sm rounded-xl border border-slate-800 shadow-xl transition-all flex items-center justify-center gap-2 cursor-pointer btn-press disabled:opacity-50">
                <span wire:loading.remove wire:target="login" class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Im Cockpit anmelden</span>
                    <span class="text-amber-400 ml-1">→</span>
                </span>
                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <span class="w-3.5 h-3.5 border-2 border-white/30 border-t-amber-400 rounded-full animate-spin"></span>
                    <span>Authentifiziere...</span>
                </span>
            </button>
        </div>
    </form>
</div>
