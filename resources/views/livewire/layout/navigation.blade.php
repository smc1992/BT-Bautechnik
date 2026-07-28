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

<nav x-data="{ open: false }" class="bg-white border-b border-slate-200/80 shadow-sm relative z-30">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-4">
                <!-- Logo -->
                <div class="shrink-0 flex items-center pr-2">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-10 w-auto" />
                    </a>
                </div>

                <!-- Structured Desktop Navigation Links -->
                <div class="hidden md:flex md:items-center md:space-x-1">
                    <!-- 1. Dashboard -->
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate class="px-3 py-2 text-xs font-extrabold rounded-xl transition">
                        📊 {{ __('Dashboard') }}
                    </x-nav-link>

                    <!-- 2. Baustellen Dropdown -->
                    @php $isBaustellenActive = request()->routeIs('planning', 'daily-logs', 'defects'); @endphp
                    <x-dropdown align="left" width="72">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-1 px-3 py-2 text-xs font-extrabold rounded-xl transition cursor-pointer {{ $isBaustellenActive ? 'text-blue-700 bg-blue-50 border border-blue-200' : 'text-slate-700 hover:text-blue-600 hover:bg-slate-50' }}">
                                <span>🏗️ Baustellen</span>
                                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('planning')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>📅</span> {{ __('Bauzeitenplaner') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('daily-logs')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>🎙️</span> {{ __('Bautagebuch & Berichte') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('defects')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>⚠️</span> {{ __('Mängel-Verwaltung') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <!-- 3. Finanzen Dropdown -->
                    @php $isFinanzenActive = request()->routeIs('invoices', 'subcontractor-invoices', 'analytics', 'materials'); @endphp
                    <x-dropdown align="left" width="72">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-1 px-3 py-2 text-xs font-extrabold rounded-xl transition cursor-pointer {{ $isFinanzenActive ? 'text-blue-700 bg-blue-50 border border-blue-200' : 'text-slate-700 hover:text-blue-600 hover:bg-slate-50' }}">
                                <span>💶 Finanzen</span>
                                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('invoices')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>📄</span> {{ __('Rechnungen & Angebote') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('subcontractor-invoices')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>🏗️</span> {{ __('Baukosten & Subunternehmer') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('materials')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>📦</span> {{ __('Material- & Baustoffkatalog') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('analytics')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap text-blue-700">
                                <span>📈</span> {{ __('Finanz- & Umsatz-Analytics') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <!-- 4. CRM & Verwaltung Dropdown -->
                    @php $isCrmActive = request()->routeIs('contacts', 'company-settings'); @endphp
                    <x-dropdown align="left" width="72">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-1 px-3 py-2 text-xs font-extrabold rounded-xl transition cursor-pointer {{ $isCrmActive ? 'text-blue-700 bg-blue-50 border border-blue-200' : 'text-slate-700 hover:text-blue-600 hover:bg-slate-50' }}">
                                <span>👥 CRM & Firma</span>
                                <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('contacts')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>👥</span> {{ __('Kunden & Partner') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('company-settings')" wire:navigate class="flex items-center gap-2 font-bold text-xs py-2.5 whitespace-nowrap">
                                <span>⚙️</span> {{ __('Firmeneinstellungen') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <!-- 5. Wissen (RAG) -->
                    <x-nav-link :href="route('knowledge-base')" :active="request()->routeIs('knowledge-base')" wire:navigate class="px-3 py-2 text-xs font-extrabold text-indigo-700">
                        📚 {{ __('Wissen') }}
                    </x-nav-link>

                    <!-- 6. Featured KI-Agent Button Badge -->
                    <a href="{{ route('ai-agent') }}" wire:navigate 
                       class="px-3.5 py-1.5 rounded-xl font-black text-xs text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer ml-2">
                        <span>🤖 KI-Agent</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[9px] font-black uppercase bg-white/20 text-white backdrop-blur-xs">PRO</span>
                    </a>
                </div>
            </div>

            <!-- Right Profile Settings Dropdown -->
            <div class="hidden md:flex md:items-center md:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3.5 py-2 border border-slate-200 text-xs leading-4 font-extrabold rounded-xl text-slate-700 bg-slate-50 hover:bg-slate-100 hover:text-slate-900 focus:outline-none transition ease-in-out duration-150 cursor-pointer">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate class="flex items-center gap-2 font-bold text-xs">
                            <span>👤</span> {{ __('Mein Profil') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link class="flex items-center gap-2 font-bold text-xs text-rose-600">
                                <span>🚪</span> {{ __('Abmelden') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger Mobile Menu Button -->
            <div class="-me-2 flex items-center md:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-xl text-slate-400 hover:text-slate-500 hover:bg-slate-100 focus:outline-none transition duration-150 ease-in-out cursor-pointer">
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
         class="hidden md:hidden bg-white border-b border-slate-200/90 shadow-lg">
        
        <div class="pt-3 pb-3 px-3 space-y-1.5">
            <!-- Featured KI-Agent Link in Mobile Menu -->
            <x-responsive-nav-link :href="route('ai-agent')" :active="request()->routeIs('ai-agent')" wire:navigate 
                                  class="rounded-xl font-black text-blue-700 bg-blue-50/80 hover:bg-blue-100/80 border-l-4 border-blue-600 flex items-center justify-between shadow-2xs">
                <span class="flex items-center gap-2.5">
                    <span class="text-base">🤖</span>
                    <span>{{ __('KI-Agent Steuerzentrale') }}</span>
                </span>
                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-blue-200 text-blue-800">Autonom</span>
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('knowledge-base')" :active="request()->routeIs('knowledge-base')" wire:navigate 
                                  class="rounded-xl font-extrabold text-indigo-700 bg-indigo-50/80 hover:bg-indigo-100/80 border-l-4 border-indigo-600 flex items-center justify-between shadow-2xs">
                <span class="flex items-center gap-2.5">
                    <span class="text-base">📚</span>
                    <span>{{ __('Wissensdatenbank (RAG)') }}</span>
                </span>
                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-indigo-200 text-indigo-800">Vektoren</span>
            </x-responsive-nav-link>

            <div class="my-2 border-t border-slate-100"></div>

            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-bold">
                <span>📊</span> {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <!-- Baustellen Category -->
            <div class="pt-2 text-[10px] font-black uppercase tracking-wider text-slate-400 px-3">Baustellen & Ausführung</div>
            <x-responsive-nav-link :href="route('planning')" :active="request()->routeIs('planning')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>📅</span> {{ __('Bauzeitenplaner') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('daily-logs')" :active="request()->routeIs('daily-logs')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>🎙️</span> {{ __('Bautagebuch & Berichte') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('defects')" :active="request()->routeIs('defects')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>⚠️</span> {{ __('Mängel-Verwaltung') }}
            </x-responsive-nav-link>

            <!-- Finanzen Category -->
            <div class="pt-2 text-[10px] font-black uppercase tracking-wider text-slate-400 px-3">Finanzen & Controlling</div>
            <x-responsive-nav-link :href="route('invoices')" :active="request()->routeIs('invoices')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>📄</span> {{ __('Rechnungen & Angebote') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('subcontractor-invoices')" :active="request()->routeIs('subcontractor-invoices')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>🏗️</span> {{ __('Baukosten & Subunternehmer') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('materials')" :active="request()->routeIs('materials')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>📦</span> {{ __('Material- & Baustoffkatalog') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('analytics')" :active="request()->routeIs('analytics')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold text-blue-700">
                <span>📈</span> {{ __('Finanz-Analytics') }}
            </x-responsive-nav-link>

            <!-- CRM Category -->
            <div class="pt-2 text-[10px] font-black uppercase tracking-wider text-slate-400 px-3">CRM & Verwaltung</div>
            <x-responsive-nav-link :href="route('contacts')" :active="request()->routeIs('contacts')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>👥</span> {{ __('Kunden & Partner') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('company-settings')" :active="request()->routeIs('company-settings')" wire:navigate class="rounded-xl flex items-center gap-2.5 font-semibold">
                <span>⚙️</span> {{ __('Firmeneinstellungen') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-4 px-4 border-t border-slate-200 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    👤
                </div>
                <div>
                    <div class="font-bold text-sm text-slate-900" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="font-medium text-xs text-slate-500">{{ auth()->user()->email }}</div>
                </div>
            </div>

            <div class="mt-4 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate class="rounded-xl flex items-center gap-2">
                    <span>👤</span> {{ __('Profil') }}
                </x-responsive-nav-link>

                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link class="rounded-xl flex items-center gap-2 text-rose-600 font-bold">
                        <span>🚪</span> {{ __('Abmelden') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
