<div x-data="{ openQuickSheet: false }" class="md:hidden">
    
    <!-- Bottom Navigation Bar for Mobile Phones -->
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 px-2 py-1.5 flex items-center justify-around shadow-lg safe-area-bottom">
        
        <!-- Baustellen -->
        <a href="/dashboard" wire:navigate class="flex flex-col items-center justify-center p-1.5 rounded-xl text-center min-w-[56px] transition {{ request()->routeIs('dashboard') ? 'text-blue-600 font-black' : 'text-slate-500 font-semibold' }}">
            <span class="text-lg leading-none">🏢</span>
            <span class="text-[10px] mt-1">Baustellen</span>
        </a>

        <!-- Bautagebuch -->
        <a href="/bautagebuch" wire:navigate class="flex flex-col items-center justify-center p-1.5 rounded-xl text-center min-w-[56px] transition {{ request()->routeIs('daily-logs') ? 'text-blue-600 font-black' : 'text-slate-500 font-semibold' }}">
            <span class="text-lg leading-none">🎙️</span>
            <span class="text-[10px] mt-1">Tagebuch</span>
        </a>

        <!-- Center Raised Action Button -->
        <div class="relative -top-4 flex items-center justify-center">
            <button @click="openQuickSheet = true" 
                    type="button"
                    class="w-13 h-13 rounded-full bg-gradient-to-tr from-blue-600 via-indigo-600 to-amber-500 text-white shadow-xl shadow-blue-500/30 flex items-center justify-center text-xl font-bold border-4 border-white cursor-pointer active:scale-95 transition-transform">
                <span>⚡</span>
            </button>
        </div>

        <!-- Zeiterfassung -->
        <a href="/zeiterfassung" wire:navigate class="flex flex-col items-center justify-center p-1.5 rounded-xl text-center min-w-[56px] transition {{ request()->routeIs('time-tracking') ? 'text-blue-600 font-black' : 'text-slate-500 font-semibold' }}">
            <span class="text-lg leading-none">⏱️</span>
            <span class="text-[10px] mt-1">Zeiterfassung</span>
        </a>

        <!-- KI-Assistent -->
        <a href="/ki-agent" wire:navigate class="flex flex-col items-center justify-center p-1.5 rounded-xl text-center min-w-[56px] transition {{ request()->routeIs('ai-agent') ? 'text-blue-600 font-black' : 'text-slate-500 font-semibold' }}">
            <span class="text-lg leading-none">🤖</span>
            <span class="text-[10px] mt-1">KI-Agent</span>
        </a>
    </nav>

    <!-- Quick Action Bottom Sheet Modal -->
    <div x-show="openQuickSheet" 
         x-cloak
         style="display: none;"
         class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 backdrop-blur-xs transition-opacity"
         @click.self="openQuickSheet = false">
        
        <div x-show="openQuickSheet"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="bg-white rounded-t-3xl p-6 w-full max-w-lg shadow-2xl border-t border-slate-200 space-y-5 pb-8">
            
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse"></span>
                    <h3 class="text-base font-black text-slate-900">⚡ Baustellen-Schnellaktionen</h3>
                </div>
                <button @click="openQuickSheet = false" class="text-slate-400 hover:text-slate-600 p-1.5 bg-slate-100 rounded-full text-xs font-bold">✕</button>
            </div>

            <div class="grid grid-cols-2 gap-3">
                
                <!-- 1-Tap Stempeluhr -->
                <a href="/zeiterfassung" @click="openQuickSheet = false" wire:navigate class="p-3.5 bg-blue-50/80 hover:bg-blue-100 rounded-2xl border border-blue-200 flex flex-col justify-between space-y-2 btn-press">
                    <span class="text-2xl">⏱️</span>
                    <div>
                        <div class="font-extrabold text-xs text-blue-950">Stempeluhr</div>
                        <div class="text-[10px] text-blue-700">Kommen / Gehen erfassen</div>
                    </div>
                </a>

                <!-- Sprach-Bautagebuch -->
                <a href="/bautagebuch" @click="openQuickSheet = false" wire:navigate class="p-3.5 bg-indigo-50/80 hover:bg-indigo-100 rounded-2xl border border-indigo-200 flex flex-col justify-between space-y-2 btn-press">
                    <span class="text-2xl">🎙️</span>
                    <div>
                        <div class="font-extrabold text-xs text-indigo-950">Sprach-Tagebuch</div>
                        <div class="text-[10px] text-indigo-700">Tagesbericht einsprechen</div>
                    </div>
                </a>

                <!-- Nachtrag erfassen -->
                <a href="/nachtraege" @click="openQuickSheet = false" wire:navigate class="p-3.5 bg-purple-50/80 hover:bg-purple-100 rounded-2xl border border-purple-200 flex flex-col justify-between space-y-2 btn-press">
                    <span class="text-2xl">📑</span>
                    <div>
                        <div class="font-extrabold text-xs text-purple-950">VOB/B Nachtrag</div>
                        <div class="text-[10px] text-purple-700">Mehrkosten anzeigen</div>
                    </div>
                </a>

                <!-- Aufmaßblatt -->
                <a href="/aufmass" @click="openQuickSheet = false" wire:navigate class="p-3.5 bg-cyan-50/80 hover:bg-cyan-100 rounded-2xl border border-cyan-200 flex flex-col justify-between space-y-2 btn-press">
                    <span class="text-2xl">📐</span>
                    <div>
                        <div class="font-extrabold text-xs text-cyan-950">VOB/C Aufmaß</div>
                        <div class="text-[10px] text-cyan-700">Massenermittlung</div>
                    </div>
                </a>

                <!-- Baupläne -->
                <a href="/bauplaene" @click="openQuickSheet = false" wire:navigate class="p-3.5 bg-amber-50/80 hover:bg-amber-100 rounded-2xl border border-amber-200 flex flex-col justify-between space-y-2 btn-press">
                    <span class="text-2xl">📁</span>
                    <div>
                        <div class="font-extrabold text-xs text-amber-950">Baupläne</div>
                        <div class="text-[10px] text-amber-700">Pläne & Revisionen</div>
                    </div>
                </a>

                <!-- Geräte & UVV -->
                <a href="/geraetepark" @click="openQuickSheet = false" wire:navigate class="p-3.5 bg-orange-50/80 hover:bg-orange-100 rounded-2xl border border-orange-200 flex flex-col justify-between space-y-2 btn-press">
                    <span class="text-2xl">🚜</span>
                    <div>
                        <div class="font-extrabold text-xs text-orange-950">Gerätepark</div>
                        <div class="text-[10px] text-orange-700">Standorte & UVV-Prüfung</div>
                    </div>
                </a>
            </div>

            <div class="pt-2 text-center">
                <p class="text-[11px] text-slate-400">BT Bautechnik • Progressive Web App</p>
            </div>
        </div>
    </div>
</div>
