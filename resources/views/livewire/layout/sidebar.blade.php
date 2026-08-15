<?php

use Livewire\Volt\Component;

new class extends Component {
    // Sidebar Component
}; ?>

@php
    $isBaustellen = request()->routeIs('dashboard', 'planning', 'work-schedule', 'daily-logs', 'defects', 'supplements', 'measurements', 'project-plans', 'equipment');
    $isFinanzen = request()->routeIs('invoices', 'subcontractor-invoices', 'analytics', 'materials', 'time-tracking');
    $isCrm = request()->routeIs('contacts', 'company-settings');

    $hasSidebar = $isBaustellen || $isFinanzen || $isCrm;
@endphp

<div wire:key="sidebar-layout-navigation">
    @if ($hasSidebar)
        <aside class="w-64 xl:w-72 shrink-0 hidden md:block space-y-6 bg-white border-r border-slate-200/90 min-h-[calc(100vh-4rem)] p-4 xl:p-5 relative transition-all duration-200">
            
            @if ($isBaustellen)
                <!-- Baustellen & Ausführung Sidebar -->
                <div class="space-y-4">
                    <div class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-950">Baustellen</span>
                        </div>
                        <span class="text-[9.5px] font-black text-amber-800 bg-amber-100/80 px-2 py-0.5 rounded-md border border-amber-200/80">HUB</span>
                    </div>

                    <nav class="space-y-1 text-xs xl:text-[13px]">
                        <a href="/dashboard" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('dashboard') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('dashboard') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                <span>Baustellenübersicht</span>
                            </span>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md {{ request()->routeIs('dashboard') ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">{{ \App\Models\Project::where('status', 'active')->count() }}</span>
                        </a>

                        <a href="/bautagebuch" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('daily-logs') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('daily-logs') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                                </svg>
                                <span>Bautagebuch (KI)</span>
                            </span>
                        </a>

                        <a href="/maengel" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('defects') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('defects') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Mängel-Verwaltung</span>
                            </span>
                        </a>

                        <a href="/nachtraege" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('supplements') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('supplements') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Nachträge (VOB/B)</span>
                            </span>
                        </a>

                        <a href="/aufmass" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('measurements') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('measurements') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                <span>Aufmaßblätter (VOB/C)</span>
                            </span>
                        </a>

                        <a href="/bauplaene" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('project-plans') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('project-plans') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                <span>Baupläne & Stände</span>
                            </span>
                        </a>

                        <a href="/geraetepark" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('equipment') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('equipment') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>Geräte- & Fuhrpark</span>
                            </span>
                        </a>

                        <a href="/einsatzplan" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('work-schedule') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('work-schedule') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>Einsatzplaner</span>
                            </span>
                        </a>

                        <a href="/planung" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('planning') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('planning') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>Bauzeitenplaner</span>
                            </span>
                        </a>
                    </nav>
                </div>

            @elseif ($isFinanzen)
                <!-- Finanzen & Controlling Sidebar -->
                <div class="space-y-4">
                    <div class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-950">Finanzen</span>
                        </div>
                        <span class="text-[9.5px] font-black text-emerald-800 bg-emerald-100/80 px-2 py-0.5 rounded-md border border-emerald-200/80">HUB</span>
                    </div>

                    <nav class="space-y-1 text-xs xl:text-[13px]">
                        <a href="/rechnungen" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('invoices') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('invoices') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Rechnungen & VOB/B</span>
                            </span>
                        </a>

                        <a href="/baukosten" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('subcontractor-invoices') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('subcontractor-invoices') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>Subunternehmer-Kosten</span>
                            </span>
                        </a>

                        <a href="/zeiterfassung" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('time-tracking') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('time-tracking') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Zeiterfassung (MiLoG)</span>
                            </span>
                        </a>

                        <a href="/materialien" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('materials') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('materials') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <span>Materialkatalog</span>
                            </span>
                        </a>

                        <a href="/analytics" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('analytics') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('analytics') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <span>Finanz-Analytics</span>
                            </span>
                        </a>

                        <!-- DATEV Export Button Link -->
                        <a href="/datev-export" target="_blank" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-200 btn-press">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>DATEV CSV Export</span>
                            </span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-900 border border-emerald-200">SKR03</span>
                        </a>
                    </nav>
                </div>

            @elseif ($isCrm)
                <!-- CRM & Firmen-Verwaltung Sidebar -->
                <div class="space-y-4">
                    <div class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-slate-950">CRM & Firma</span>
                        </div>
                        <span class="text-[9.5px] font-black text-purple-800 bg-purple-100/80 px-2 py-0.5 rounded-md border border-purple-200/80">HUB</span>
                    </div>

                    <nav class="space-y-1 text-xs xl:text-[13px]">
                        <a href="/kontakte" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('contacts') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('contacts') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>Kunden & Partner</span>
                            </span>
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md {{ request()->routeIs('contacts') ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">{{ \App\Models\Contact::count() }}</span>
                        </a>

                        <a href="/firmeneinstellungen" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition btn-press {{ request()->routeIs('company-settings') ? 'bg-slate-950 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 {{ request()->routeIs('company-settings') ? 'text-amber-400' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>Firmeneinstellungen</span>
                            </span>
                        </a>
                    </nav>
                </div>
            @endif

            <!-- Schnell-Hilfe Card in Sidebar Bottom -->
            <div class="pt-6 border-t border-slate-200 space-y-3">
                <div class="bg-slate-950 text-white p-4 rounded-2xl space-y-2 shadow-xs border border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        <span class="text-xs font-black text-white">Schnellzugriff</span>
                    </div>
                    <p class="text-[11px] text-slate-300 leading-relaxed font-medium">Drücken Sie <kbd class="px-1.5 py-0.5 bg-white/15 rounded font-mono text-[10px] text-white border border-white/20">⌘K</kbd> für die globale Schnellsuche.</p>
                </div>
            </div>
        </aside>
    @endif
</div>
