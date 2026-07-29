<?php

use Livewire\Volt\Component;

new class extends Component {
    // Sidebar Component
}; ?>

@php
    $isBaustellen = request()->routeIs('dashboard', 'planning', 'work-schedule', 'daily-logs', 'defects');
    $isFinanzen = request()->routeIs('invoices', 'subcontractor-invoices', 'analytics', 'materials');
    $isCrm = request()->routeIs('contacts', 'company-settings');

    $hasSidebar = $isBaustellen || $isFinanzen || $isCrm;
@endphp

<div>
    @if ($hasSidebar)
        <aside class="w-64 shrink-0 hidden md:block space-y-6 bg-white border-r border-slate-200/80 min-h-[calc(100vh-4rem)] p-4 relative transition-all duration-300">
            
            @if ($isBaustellen)
                <!-- Baustellen & Ausführung Sidebar -->
                <div class="space-y-4">
                    <div class="px-3 py-2 bg-blue-50/80 border border-blue-200/60 rounded-xl flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-blue-900">Baustellen</span>
                        </div>
                        <span class="text-[10px] font-extrabold text-blue-700 bg-white px-2 py-0.5 rounded-md border border-blue-200 shadow-2xs">HUB</span>
                    </div>

                    <nav class="space-y-1 text-xs">
                        <a href="/dashboard" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-blue-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">🏢</span> <span>Baustellenübersicht</span>
                            </span>
                            <span class="text-[10px] opacity-80 font-mono">{{ \App\Models\Project::where('status', 'active')->count() }}</span>
                        </a>

                        <a href="/einsatzplan" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('work-schedule') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-blue-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">👷</span> <span>Einsatzplaner</span>
                            </span>
                        </a>

                        <a href="/planung" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('planning') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-blue-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">📅</span> <span>Bauzeitenplaner</span>
                            </span>
                        </a>

                        <a href="/bautagebuch" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('daily-logs') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-blue-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">🎙️</span> <span>Bautagebuch</span>
                            </span>
                        </a>

                        <a href="/maengel" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('defects') ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-blue-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">⚠️</span> <span>Mängel-Verwaltung</span>
                            </span>
                        </a>
                    </nav>
                </div>

            @elseif ($isFinanzen)
                <!-- Finanzen & Controlling Sidebar -->
                <div class="space-y-4">
                    <div class="px-3 py-2 bg-emerald-50/80 border border-emerald-200/60 rounded-xl flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 animate-pulse"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-900">Finanzen</span>
                        </div>
                        <span class="text-[10px] font-extrabold text-emerald-700 bg-white px-2 py-0.5 rounded-md border border-emerald-200 shadow-2xs">HUB</span>
                    </div>

                    <nav class="space-y-1 text-xs">
                        <a href="/rechnungen" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('invoices') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-emerald-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">📄</span> <span>Rechnungen & Angebote</span>
                            </span>
                        </a>

                        <a href="/baukosten" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('subcontractor-invoices') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-emerald-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">🏗️</span> <span>Subunternehmer-Kosten</span>
                            </span>
                        </a>

                        <a href="/materialien" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('materials') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-emerald-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">📦</span> <span>Materialkatalog</span>
                            </span>
                        </a>

                        <a href="/analytics" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('analytics') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-emerald-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">📈</span> <span>Finanz-Analytics</span>
                            </span>
                        </a>
                    </nav>
                </div>

            @elseif ($isCrm)
                <!-- CRM & Firmen-Verwaltung Sidebar -->
                <div class="space-y-4">
                    <div class="px-3 py-2 bg-purple-50/80 border border-purple-200/60 rounded-xl flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-purple-600 animate-pulse"></span>
                            <span class="text-xs font-black uppercase tracking-wider text-purple-900">CRM & Firma</span>
                        </div>
                        <span class="text-[10px] font-extrabold text-purple-700 bg-white px-2 py-0.5 rounded-md border border-purple-200 shadow-2xs">HUB</span>
                    </div>

                    <nav class="space-y-1 text-xs">
                        <a href="/kontakte" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('contacts') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-purple-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">👥</span> <span>Kunden & Partner</span>
                            </span>
                            <span class="text-[10px] opacity-80 font-mono">{{ \App\Models\Contact::count() }}</span>
                        </a>

                        <a href="/firmeneinstellungen" wire:navigate class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-bold transition {{ request()->routeIs('company-settings') ? 'bg-purple-600 text-white shadow-md shadow-purple-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-purple-600' }}">
                            <span class="flex items-center gap-2.5">
                                <span class="text-base">⚙️</span> <span>Firmeneinstellungen</span>
                            </span>
                        </a>
                    </nav>
                </div>
            @endif

            <!-- Schnell-Hilfe Card in Sidebar Bottom -->
            <div class="pt-6 border-t border-slate-200/80 space-y-3">
                <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-3.5 rounded-2xl space-y-2 shadow-sm border border-indigo-500/20">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🤖</span>
                        <span class="text-xs font-black">BT KI-Assistent</span>
                    </div>
                    <p class="text-[10px] text-slate-300 leading-relaxed">Drücken Sie <kbd class="px-1 py-0.5 bg-white/20 rounded font-mono text-[9px] text-white">⌘K</kbd> für die Schnellsuche.</p>
                </div>
            </div>
        </aside>
    @endif
</div>
