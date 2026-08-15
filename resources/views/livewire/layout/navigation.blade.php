<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $isBaustellenGroup = request()->routeIs('dashboard', 'planning', 'work-schedule', 'daily-logs', 'defects', 'supplements', 'measurements', 'project-plans', 'equipment');
    $isFinanzenActive = request()->routeIs('invoices', 'subcontractor-invoices', 'analytics', 'materials', 'time-tracking');
    $isCrmActive = request()->routeIs('contacts', 'company-settings');
    $isKiActive = request()->routeIs('ai-agent', 'knowledge-base');
@endphp

<nav x-data="{ open: false }" wire:key="topbar-navigation-header" class="bg-[#091224] text-white border-b border-slate-800/80 sticky top-0 z-40 transition-colors duration-200">
    <!-- Primary Navigation Menu -->
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            
            <!-- Left: Logo & Main Navigation -->
            <div class="flex items-center gap-3">
                <!-- Logo Badge -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center group btn-press">
                        <div class="bg-white px-3 py-1 rounded-xl shadow-xs border border-white/20 flex items-center justify-center h-9 group-hover:bg-slate-50 transition">
                            <x-application-logo class="h-6 w-auto object-contain" />
                        </div>
                    </a>
                </div>

                <!-- Structured Desktop Navigation Links -->
                <div class="hidden md:flex md:items-center md:gap-1.5">
                    <!-- 1. Dashboard -->
                    <a wire:key="nav-btn-dashboard" href="{{ route('dashboard') }}" wire:navigate 
                       class="h-9 px-3 text-xs font-bold rounded-xl transition btn-press flex items-center gap-1.5 border {{ request()->routeIs('dashboard') ? 'bg-white/15 text-amber-400 border-amber-500/40 shadow-2xs' : 'text-slate-300 border-transparent hover:text-white hover:bg-white/10' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span>{{ __('Cockpit') }}</span>
                    </a>

                    <!-- 2. Baustellen Dropdown -->
                    <x-dropdown align="left" width="72" wire:key="nav-dropdown-baustellen">
                        <x-slot name="trigger">
                            <button class="h-9 inline-flex items-center gap-1.5 px-3 text-xs font-bold rounded-xl transition btn-press cursor-pointer border {{ $isBaustellenGroup && !request()->routeIs('dashboard') ? 'bg-white/15 text-amber-400 border-amber-500/40 shadow-2xs' : 'text-slate-300 border-transparent hover:text-white hover:bg-white/10' }}">
                                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>Baustellen</span>
                                <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('work-schedule')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Einsatzplaner (Gewerke)</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('planning')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Bauzeitenplaner</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('daily-logs')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Bautagebuch & Berichte</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('defects')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Mängel-Verwaltung</span>
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <!-- 3. Finanzen Dropdown -->
                    <x-dropdown align="left" width="72" wire:key="nav-dropdown-finanzen">
                        <x-slot name="trigger">
                            <button class="h-9 inline-flex items-center gap-1.5 px-3 text-xs font-bold rounded-xl transition btn-press cursor-pointer border {{ $isFinanzenActive ? 'bg-white/15 text-amber-400 border-amber-500/40 shadow-2xs' : 'text-slate-300 border-transparent hover:text-white hover:bg-white/10' }}">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Finanzen</span>
                                <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('invoices')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Rechnungen & Angebote</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('subcontractor-invoices')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Baukosten & Subunternehmer</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('materials')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Material- & Baustoffkatalog</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('analytics')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Finanz- & Umsatz-Analytics</span>
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <!-- 4. CRM & Verwaltung Dropdown -->
                    <x-dropdown align="left" width="72" wire:key="nav-dropdown-crm">
                        <x-slot name="trigger">
                            <button class="h-9 inline-flex items-center gap-1.5 px-3 text-xs font-bold rounded-xl transition btn-press cursor-pointer border {{ $isCrmActive ? 'bg-white/15 text-amber-400 border-amber-500/40 shadow-2xs' : 'text-slate-300 border-transparent hover:text-white hover:bg-white/10' }}">
                                <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>CRM & Firma</span>
                                <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('contacts')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Kunden & Partner</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('company-settings')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-slate-800 hover:text-amber-700">
                                <span>Firmeneinstellungen</span>
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <!-- 5. Wissen (RAG) -->
                    <a wire:key="nav-btn-wissen" href="{{ route('knowledge-base') }}" wire:navigate 
                       class="h-9 px-3 text-xs font-bold rounded-xl transition btn-press flex items-center gap-1.5 border {{ request()->routeIs('knowledge-base') ? 'bg-white/15 text-amber-400 border-amber-500/40 shadow-2xs' : 'text-slate-300 border-transparent hover:text-white hover:bg-white/10' }}">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>{{ __('Wissen') }}</span>
                    </a>

                    <!-- 6. Featured KI-Agent Button Badge -->
                    <a wire:key="nav-btn-ki" href="{{ route('ai-agent') }}" wire:navigate 
                       class="h-9 px-3.5 rounded-xl font-black text-xs text-white bg-slate-900 hover:bg-slate-800 border border-amber-500/40 shadow-md transition btn-press flex items-center gap-1.5 cursor-pointer">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        <span class="text-amber-300">KI-Bauleiter</span>
                        <span class="px-1.5 py-0.2 rounded-md text-[8.5px] font-black uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">PRO</span>
                    </a>
                </div>
            </div>

            <!-- Right: Schnellsuche & Profile Settings Dropdown -->
            <div class="hidden md:flex md:items-center md:gap-2">
                <!-- Global Command Palette Trigger (`Cmd + K`) -->
                <button @click="$dispatch('open-cmd-palette')" 
                        class="h-9 hidden xl:flex items-center gap-2 px-3 bg-white/10 hover:bg-white/20 text-slate-200 hover:text-white rounded-xl border border-white/15 text-xs font-semibold transition btn-press cursor-pointer shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span>Schnellsuche...</span>
                    <kbd class="px-1.5 py-0.5 text-[10px] font-black font-mono text-slate-200 bg-white/15 rounded border border-white/20 shadow-2xs">⌘K</kbd>
                </button>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="h-9 inline-flex items-center px-3.5 border border-white/20 text-xs leading-4 font-bold rounded-xl text-white bg-white/10 hover:bg-white/20 focus:outline-none transition ease-in-out duration-150 btn-press cursor-pointer">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1.5">
                                <svg class="fill-current h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate class="flex items-center gap-2 font-bold text-xs text-slate-800 hover:text-amber-700">
                            <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>{{ __('Mein Profil') }}</span>
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link class="flex items-center gap-2 font-bold text-xs text-rose-600 hover:text-rose-700">
                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span>{{ __('Abmelden') }}</span>
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger Mobile Menu Button -->
            <div class="-me-2 flex items-center md:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 focus:outline-none transition duration-150 ease-in-out cursor-pointer">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'inline-flex': open, 'hidden': ! open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Mobile Navigation Drawer -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         :class="{'block': open, 'hidden': ! open}" 
         class="hidden md:hidden bg-slate-950 border-b border-slate-800 shadow-2xl">
        
        <div class="pt-3 pb-3 px-3 space-y-1.5">
            <!-- Featured KI-Agent Link in Mobile Menu -->
            <x-responsive-nav-link :href="route('ai-agent')" :active="request()->routeIs('ai-agent')" wire:navigate 
                                  class="rounded-xl font-bold text-amber-300 bg-slate-900 border-l-4 border-amber-500 flex items-center justify-between">
                <span class="flex items-center gap-2.5">
                    <span>KI-Bauleiter Zentrale</span>
                </span>
                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300">PRO</span>
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('knowledge-base')" :active="request()->routeIs('knowledge-base')" wire:navigate 
                                  class="rounded-xl font-bold text-slate-200 bg-slate-900/60 border-l-4 border-cyan-500 flex items-center justify-between">
                <span>Wissensdatenbank (RAG)</span>
            </x-responsive-nav-link>

            <div class="my-2 border-t border-slate-800"></div>

            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate class="rounded-xl font-bold text-slate-200">
                <span>Cockpit Dashboard</span>
            </x-responsive-nav-link>

            <!-- Baustellen Category -->
            <div class="pt-2 text-[10px] font-black uppercase tracking-wider text-amber-500 px-3">Baustellen & Ausführung</div>
            <x-responsive-nav-link :href="route('work-schedule')" :active="request()->routeIs('work-schedule')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Einsatzplaner</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('planning')" :active="request()->routeIs('planning')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Bauzeitenplaner</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('daily-logs')" :active="request()->routeIs('daily-logs')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Bautagebuch</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('defects')" :active="request()->routeIs('defects')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Mängel-Verwaltung</span>
            </x-responsive-nav-link>

            <!-- Finanzen Category -->
            <div class="pt-2 text-[10px] font-black uppercase tracking-wider text-amber-500 px-3">Finanzen & Controlling</div>
            <x-responsive-nav-link :href="route('invoices')" :active="request()->routeIs('invoices')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Rechnungen & Angebote</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('subcontractor-invoices')" :active="request()->routeIs('subcontractor-invoices')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Subunternehmer-Kosten</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('materials')" :active="request()->routeIs('materials')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Materialkatalog</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('analytics')" :active="request()->routeIs('analytics')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Finanz-Analytics</span>
            </x-responsive-nav-link>

            <!-- CRM Category -->
            <div class="pt-2 text-[10px] font-black uppercase tracking-wider text-amber-500 px-3">CRM & Verwaltung</div>
            <x-responsive-nav-link :href="route('contacts')" :active="request()->routeIs('contacts')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Kunden & Partner</span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('company-settings')" :active="request()->routeIs('company-settings')" wire:navigate class="rounded-xl text-slate-300 font-semibold">
                <span>Firmeneinstellungen</span>
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-4 px-4 border-t border-slate-800 bg-slate-900/60">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 text-amber-400 flex items-center justify-center font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <div class="font-bold text-sm text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="font-medium text-xs text-slate-400">{{ auth()->user()->email }}</div>
                </div>
            </div>

            <div class="mt-4 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate class="rounded-xl text-slate-300">
                    <span>{{ __('Mein Profil') }}</span>
                </x-responsive-nav-link>

                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link class="rounded-xl text-rose-400 font-bold">
                        <span>{{ __('Abmelden') }}</span>
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
