@php
    $projects = \App\Models\Project::where('status', 'active')->orderBy('name', 'asc')->get(['id', 'name', 'city_street', 'zip']);
@endphp

<div x-data="{ 
        showCmdPalette: false, 
        cmdQuery: '',
        projects: {{ json_encode($projects) }},
        navItems: [
            { title: 'Baustellenübersicht & Pipeline', icon: '🏢', url: '/dashboard', cat: 'Baustellen' },
            { title: 'Nachträge (VOB/B § 2)', icon: '📑', url: '/nachtraege', cat: 'Baustellen' },
            { title: 'Digitales Aufmaßblatt (VOB/C)', icon: '📐', url: '/aufmass', cat: 'Baustellen' },
            { title: 'Baupläne & Revisionsstand', icon: '📁', url: '/bauplaene', cat: 'Baustellen' },
            { title: 'Geräte- & Fuhrpark (UVV)', icon: '🚜', url: '/geraetepark', cat: 'Baustellen' },
            { title: 'Bautagebuch & Berichte', icon: '🎙️', url: '/bautagebuch', cat: 'Baustellen' },
            { title: 'Mängel-Verwaltung', icon: '⚠️', url: '/maengel', cat: 'Baustellen' },
            { title: 'Einsatzplaner', icon: '👷', url: '/einsatzplan', cat: 'Baustellen' },
            { title: 'Bauzeitenplaner', icon: '📅', url: '/planung', cat: 'Baustellen' },
            { title: 'Rechnungen & Angebote', icon: '📄', url: '/rechnungen', cat: 'Finanzen' },
            { title: 'Subunternehmer-Kosten', icon: '🏗️', url: '/baukosten', cat: 'Finanzen' },
            { title: 'Zeiterfassung (MiLoG)', icon: '⏱️', url: '/zeiterfassung', cat: 'Finanzen' },
            { title: 'DATEV CSV-Export (SKR03)', icon: '📊', url: '/datev-export', cat: 'Finanzen' },
            { title: 'Finanz-Analytics', icon: '📈', url: '/analytics', cat: 'Finanzen' },
            { title: 'Materialkatalog', icon: '📦', url: '/materialien', cat: 'Finanzen' },
            { title: 'Kunden & Partner (CRM)', icon: '👥', url: '/kontakte', cat: 'CRM' },
            { title: 'Firmeneinstellungen', icon: '⚙️', url: '/firmeneinstellungen', cat: 'CRM' },
            { title: 'KI-Agent Steuerzentrale', icon: '🤖', url: '/ki-agent', cat: 'KI & Wissen' },
            { title: 'Wissensdatenbank (RAG)', icon: '📚', url: '/wissen', cat: 'KI & Wissen' }
        ],
        get filteredNav() {
            if (!this.cmdQuery.trim()) return this.navItems;
            const q = this.cmdQuery.toLowerCase();
            return this.navItems.filter(i => i.title.toLowerCase().includes(q) || i.cat.toLowerCase().includes(q));
        },
        get filteredProjects() {
            if (!this.cmdQuery.trim()) return this.projects.slice(0, 5);
            const q = this.cmdQuery.toLowerCase();
            return this.projects.filter(p => p.name.toLowerCase().includes(q) || (p.city_street && p.city_street.toLowerCase().includes(q)));
        }
     }" 
     x-on:keydown.window.cmd.k.prevent="showCmdPalette = true"
     x-on:keydown.window.ctrl.k.prevent="showCmdPalette = true"
     x-on:open-cmd-palette.window="showCmdPalette = true"
     x-show="showCmdPalette" 
     x-cloak
     style="display: none;"
     class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-start justify-center z-50 pt-16 sm:pt-20 p-4 transition-all">
    
    <div @click.away="showCmdPalette = false" 
         class="bg-white border border-slate-200 rounded-3xl w-full max-w-xl shadow-2xl overflow-hidden flex flex-col space-y-0">
        
        <!-- Search Input Header -->
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
            <span class="text-slate-400 text-lg">🔍</span>
            <input x-model="cmdQuery" 
                   x-ref="cmdInput"
                   x-effect="if (showCmdPalette) setTimeout(() => $refs.cmdInput.focus(), 50)"
                   type="text" 
                   class="w-full bg-transparent border-0 text-sm font-bold text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0" 
                   placeholder="Suchen nach Modulen, Baustellen, Plänen, Rechnungen... (z. B. Nachtrag, Aufmaß, Zeiterfassung)">
            <button @click="showCmdPalette = false" class="text-slate-400 hover:text-slate-700 text-xs font-bold px-2 py-1 bg-slate-200/60 rounded-lg cursor-pointer">ESC</button>
        </div>

        <div class="p-4 max-h-[60vh] overflow-y-auto space-y-4">
            
            <!-- Navigation Items Grid -->
            <div>
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 px-1 mb-2">Module & Schnell-Navigation</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <template x-for="item in filteredNav" :key="item.url">
                        <a :href="item.url" @click="showCmdPalette = false" wire:navigate
                           class="p-2.5 bg-slate-50 hover:bg-blue-50 text-left rounded-xl border border-slate-200 hover:border-blue-300 transition cursor-pointer flex items-center gap-2.5 btn-press">
                            <span class="text-lg" x-text="item.icon"></span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 truncate" x-text="item.title"></div>
                                <div class="text-[10px] text-slate-500" x-text="item.cat"></div>
                            </div>
                        </a>
                    </template>
                </div>
            </div>

            <!-- Active Projects Quick Jump -->
            <div x-show="filteredProjects.length > 0">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 px-1 mb-2">Aktive Baustellen</div>
                <div class="space-y-1.5">
                    <template x-for="p in filteredProjects" :key="p.id">
                        <a href="/dashboard" @click="showCmdPalette = false" wire:navigate
                           class="p-2.5 bg-slate-50 hover:bg-blue-50 text-left rounded-xl border border-slate-200 hover:border-blue-300 transition cursor-pointer flex items-center justify-between gap-2 btn-press">
                            <div class="flex items-center gap-2 truncate">
                                <span>📍</span>
                                <span class="text-xs font-bold text-slate-800 truncate" x-text="p.name"></span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium whitespace-nowrap" x-text="p.city_street || p.zip || 'Baustelle'"></span>
                        </a>
                    </template>
                </div>
            </div>

        </div>

        <div class="p-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
            <span>Tippen Sie zur Filterung</span>
            <span>Drücken Sie <kbd class="font-mono bg-white px-1 py-0.5 rounded border">ESC</kbd> zum Schließen</span>
        </div>
    </div>
</div>
