<?php

use Livewire\Volt\Component;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;

new class extends Component {
    // Interactive Demo Modal State
    public bool $showDemoModal = false;
    public string $demoName = '';
    public string $demoCompany = '';
    public string $demoEmail = '';
    public string $demoPhone = '';
    public string $demoTrade = 'bautraeger'; // bautraeger, generalunternehmer, sanierung_abdichtung, hoch_tiefbau, handwerk
    public string $demoProjectCount = '4-10';
    public string $demoMessage = '';
    public bool $demoSuccess = false;

    // Interactive ROI Calculator State
    public int $roiProjectCount = 6;
    public int $roiWorkerCount = 8;
    public int $roiHourlyRate = 68;

    // Interactive Module Explorer Tab
    public string $activeModuleTab = 'cockpit'; // cockpit, contacts360, supplements, measurements, dailylogs, datev

    // Interactive FAQ Accordion State
    public ?int $openFaqIndex = 0;

    public function openDemoModal(?string $trade = null)
    {
        if ($trade) {
            $this->demoTrade = $trade;
        }
        $this->demoSuccess = false;
        $this->showDemoModal = true;
    }

    public function closeDemoModal()
    {
        $this->showDemoModal = false;
    }

    public function submitDemoRequest()
    {
        $this->validate([
            'demoName' => 'required|min:3',
            'demoCompany' => 'required|min:2',
            'demoEmail' => 'required|email',
            'demoPhone' => 'required|min:6',
        ]);

        $tradeLabels = [
            'bautraeger' => 'Bauträger / Projektentwickler',
            'generalunternehmer' => 'Generalübernehmer / GU',
            'sanierung_abdichtung' => 'Bauwerkserhaltung / Abdichtung & Sanierung',
            'hoch_tiefbau' => 'Hoch- & Tiefbauunternehmen',
            'handwerk' => 'Fachhandwerksbetrieb / Ausbau',
        ];

        $notes = "🚀 SAAS-DEMO ANFRAGE ÜBER DIE LANDINGPAGE\n"
               . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
               . "• Datum: " . date('d.m.Y H:i:s') . "\n"
               . "• Ansprechpartner: " . $this->demoName . "\n"
               . "• Unternehmen: " . $this->demoCompany . "\n"
               . "• Gewerk/Typ: " . ($tradeLabels[$this->demoTrade] ?? $this->demoTrade) . "\n"
               . "• Baustellen pro Jahr: " . $this->demoProjectCount . "\n"
               . "• E-Mail: " . $this->demoEmail . "\n"
               . "• Telefon: " . $this->demoPhone . "\n"
               . ($this->demoMessage ? ("• Nachricht: " . $this->demoMessage . "\n") : "");

        try {
            Contact::create([
                'type' => in_array($this->demoTrade, ['bautraeger', 'hausverwaltung', 'subunternehmer']) ? $this->demoTrade : 'kunde',
                'company_name' => $this->demoCompany,
                'first_name' => explode(' ', $this->demoName)[0] ?? $this->demoName,
                'last_name' => count(explode(' ', $this->demoName)) > 1 ? implode(' ', array_slice(explode(' ', $this->demoName), 1)) : '',
                'email' => $this->demoEmail,
                'phone' => $this->demoPhone,
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create demo request contact: ' . $e->getMessage());
        }

        $this->demoSuccess = true;
    }

    public function toggleFaq(int $index)
    {
        $this->openFaqIndex = ($this->openFaqIndex === $index) ? null : $index;
    }

    public function getSavedHoursPerMonthProperty(): int
    {
        return (int)round(($this->roiProjectCount * 5.5) + ($this->roiWorkerCount * 2.2));
    }

    public function getSavedCostPerYearProperty(): int
    {
        return (int)round($this->savedHoursPerMonth * 12 * $this->roiHourlyRate);
    }

    public function getAdditionalSupplementRevenueProperty(): int
    {
        return (int)round($this->roiProjectCount * 5200 * 0.16);
    }

    public function getTotalValuePerYearProperty(): int
    {
        return $this->savedCostPerYear + $this->additionalSupplementRevenue;
    }
}; ?>

<div x-data="{ showStickyBar: false, mobileMenuOpen: false }" 
     @scroll.window="showStickyBar = (window.pageYOffset || document.documentElement.scrollTop) > 450" 
     class="min-h-screen arch-blueprint-bg text-slate-900 font-sans selection:bg-amber-500 selection:text-slate-950 relative overflow-x-hidden">
    
    <!-- Architectural Hairline Vertical Guides & Ambient Layer -->
    <div class="arch-hairline-overlay"></div>
    <div class="fixed top-0 left-1/3 w-[650px] h-[550px] bg-slate-200/40 rounded-full blur-[160px] pointer-events-none -z-10 animate-glow"></div>
    <div class="fixed bottom-1/4 right-10 w-[550px] h-[550px] bg-amber-100/30 rounded-full blur-[180px] pointer-events-none -z-10"></div>

    <!-- ========================================================================= -->
    <!-- 1. STICKY TOP NAVBAR (ARCHITECTURAL DUAL-TONE & GLASS)                     -->
    <!-- ========================================================================= -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-xl border-b border-slate-200/90 shadow-xs transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-4">
            
            <!-- Real Brand Logo Component -->
            <a href="/" class="hover:opacity-90 transition-opacity group shrink-0">
                <x-brand-logo size="default" />
            </a>

            <!-- Nav Links (Desktop) - Clean Architectural Typography -->
            <nav class="hidden lg:flex items-center gap-1 xl:gap-2">
                <a href="#story" class="px-3.5 py-2 rounded-xl text-[13px] font-bold text-slate-700 hover:text-slate-950 hover:bg-slate-100 transition-all whitespace-nowrap">
                    Baupraxis & Story
                </a>
                <a href="#module" class="px-3.5 py-2 rounded-xl text-[13px] font-bold text-slate-700 hover:text-slate-950 hover:bg-slate-100 transition-all whitespace-nowrap">
                    Module & VOB
                </a>
                <a href="#integrations" class="px-3.5 py-2 rounded-xl text-[13px] font-bold text-slate-700 hover:text-slate-950 hover:bg-slate-100 transition-all whitespace-nowrap">
                    Schnittstellen
                </a>
                <a href="#rechner" class="px-3.5 py-2 rounded-xl text-[13px] font-bold text-slate-700 hover:text-slate-950 hover:bg-slate-100 transition-all whitespace-nowrap inline-flex items-center gap-1.5">
                    <span>Ersparnisrechner</span>
                    <span class="px-1.5 py-0.5 text-[9.5px] font-black rounded-md bg-amber-50 text-amber-800 border border-amber-200/80">Rechner</span>
                </a>
                <a href="#vorteile" class="px-3.5 py-2 rounded-xl text-[13px] font-bold text-slate-700 hover:text-slate-950 hover:bg-slate-100 transition-all whitespace-nowrap">
                    Vorher / Nachher
                </a>
                <a href="#faq" class="px-3.5 py-2 rounded-xl text-[13px] font-bold text-slate-700 hover:text-slate-950 hover:bg-slate-100 transition-all whitespace-nowrap">
                    FAQ
                </a>
            </nav>

            <!-- Action Buttons (Desktop & Tablet) -->
            <div class="hidden sm:flex items-center gap-2 sm:gap-3 shrink-0">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-[13px] rounded-xl shadow-xs transition flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span>Zum Cockpit</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="px-3.5 py-2 text-[13px] font-bold text-slate-700 hover:text-slate-950 hover:bg-slate-100 rounded-xl transition flex items-center gap-1.5">
                        <span>Login</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                @endauth

                <button wire:click="openDemoModal" class="px-5 py-2.5 bg-slate-950 hover:bg-slate-800 text-white font-black text-xs sm:text-[13px] rounded-xl border border-slate-800 shadow-md hover:shadow-lg transition-all cursor-pointer flex items-center gap-2 btn-press">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Live-Demo anfordern</span>
                </button>
            </div>

            <!-- Mobile Actions (Screen < 640px) -->
            <div class="flex sm:hidden items-center gap-2 shrink-0">
                <button type="button" wire:click="openDemoModal" class="px-3 py-2 bg-slate-950 active:scale-95 text-white font-bold text-xs rounded-xl shadow-xs flex items-center gap-1.5 btn-press">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    <span>Demo</span>
                </button>

                <!-- Hamburger Toggle Button -->
                <button type="button" 
                        @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 focus:outline-none transition-colors cursor-pointer" 
                        aria-label="Menü öffnen">
                    <svg x-show="!mobileMenuOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>

        <!-- Mobile Drawer Navigation -->
        <div x-show="mobileMenuOpen" 
             x-cloak 
             x-transition:enter="transition ease-out duration-250 transform" 
             x-transition:enter-start="opacity-0 -translate-y-2" 
             x-transition:enter-end="opacity-100 translate-y-0" 
             x-transition:leave="transition ease-in duration-150 transform" 
             x-transition:leave-start="opacity-100 translate-y-0" 
             x-transition:leave-end="opacity-0 -translate-y-2" 
             @click.away="mobileMenuOpen = false"
             class="lg:hidden bg-white border-b border-slate-200 shadow-xl px-4 py-5 space-y-4">
            
            <div class="space-y-1">
                <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider px-3 block mb-1">Navigation</span>
                
                <nav class="flex flex-col space-y-1">
                    <a href="#story" @click="mobileMenuOpen = false" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold text-slate-800 hover:text-amber-600 hover:bg-slate-50 transition">
                        <span>Baupraxis & Story</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <a href="#module" @click="mobileMenuOpen = false" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold text-slate-800 hover:text-amber-600 hover:bg-slate-50 transition">
                        <span>Module & VOB</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <a href="#integrations" @click="mobileMenuOpen = false" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold text-slate-800 hover:text-amber-600 hover:bg-slate-50 transition">
                        <span>Schnittstellen & DATEV</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <a href="#rechner" @click="mobileMenuOpen = false" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold text-slate-800 hover:text-amber-700 hover:bg-amber-50/60 transition">
                        <span class="flex items-center gap-2">
                            <span>Ersparnisrechner</span>
                            <span class="px-1.5 py-0.5 text-[9px] font-bold rounded-md bg-amber-100 text-amber-800">Live</span>
                        </span>
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <a href="#vorteile" @click="mobileMenuOpen = false" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold text-slate-800 hover:text-slate-900 hover:bg-slate-50 transition">
                        <span>Vorher / Nachher Vergleich</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <a href="#faq" @click="mobileMenuOpen = false" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold text-slate-800 hover:text-slate-900 hover:bg-slate-50 transition">
                        <span>Häufige Fragen (FAQ)</span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </nav>
            </div>

            <!-- Mobile Drawer Actions & CTA -->
            <div class="pt-3 border-t border-slate-100 space-y-2">
                <button type="button" wire:click="openDemoModal" @click="mobileMenuOpen = false" class="w-full py-3 bg-slate-950 text-white font-bold text-xs rounded-xl shadow-md text-center flex items-center justify-center gap-2 btn-press">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Kostenlose Live-Demo anfordern</span>
                </button>
                
                <div class="grid grid-cols-2 gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="py-2.5 bg-slate-900 text-white font-bold text-xs rounded-xl text-center flex items-center justify-center gap-1.5">
                            <span>Zum Cockpit</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs rounded-xl text-center flex items-center justify-center gap-1.5">
                            <span>Login</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endauth
                    
                    <a href="https://wa.me/4916096275910?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="py-2.5 bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold text-xs rounded-xl text-center flex items-center justify-center gap-1.5">
                        <span>WhatsApp</span>
                    </a>
                </div>
            </div>

        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- 2. HERO SECTION (CITY CONSTRUCT ARCHITECTURAL EDITORIAL STYLE)            -->
    <!-- ========================================================================= -->
    <section class="relative pt-10 pb-14 sm:pt-16 sm:pb-24 lg:pt-20 lg:pb-28 overflow-hidden">
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="text-center max-w-4xl mx-auto space-y-4 sm:space-y-6">
                
                <!-- Architectural Category Prefix Label -->
                <div class="arch-section-label">
                    <span>DIGITALES BAULEITER-COCKPIT & BAUTRÄGER-SYSTEM</span>
                </div>

                <!-- Main Hero Headline in Architectural Typography -->
                <h1 class="text-3xl sm:text-5xl lg:text-7xl font-black tracking-tight text-slate-950 leading-[1.08]">
                    WIR BAUEN DIE ZUKUNFT DER<br>
                    <span class="text-amber-600">DIGITALEN BAUSTELLE.</span>
                </h1>

                <!-- Subtitle with Construction Authenticity -->
                <p class="text-xs sm:text-base lg:text-lg text-slate-600 font-medium max-w-3xl mx-auto leading-relaxed">
                    Entwickelt aus der täglichen Baupraxis der <strong>BT Bautechnik UG (haftungsbeschränkt)</strong> in Bayern. Lückenlose Baustellen-Steuerung, 360° Kunden-Zentrale, digitale VOB/C Aufmaße, KI-Bautagebücher und DATEV SKR03/04 in einer hochpräzisen Lösung.
                </p>

                <!-- Hero CTAs with Clean Architectural Alignment -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-4 bg-slate-950 hover:bg-slate-800 text-white font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl border border-slate-800 shadow-xl transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer flex items-center justify-center gap-2.5 btn-press">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                        <span>Kostenlose Live-Demo vereinbaren</span>
                        <span class="text-amber-400 ml-1">→</span>
                    </button>

                    <a href="https://wa.me/4916096275910?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="w-full sm:w-auto px-6 py-4 bg-white hover:bg-slate-50 text-slate-800 font-bold text-xs sm:text-sm rounded-xl sm:rounded-2xl border border-slate-300 shadow-xs transition-all duration-300 transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.54 1.772.82 2.79.82 3.181 0 5.767-2.586 5.768-5.766 0-3.18-2.586-5.766-5.767-5.766zm3.385 8.169c-.14.394-.814.73-1.121.776-.307.046-.66.064-1.928-.46-1.52-.628-2.502-2.176-2.578-2.278-.076-.102-.619-.824-.619-1.571 0-.748.393-1.116.533-1.269.14-.153.307-.191.41-.191.102 0 .205.002.294.006.094.005.218-.036.342.261.127.306.435 1.062.473 1.139.038.077.064.166.013.268-.051.102-.077.166-.153.255-.077.09-.161.2-.23.268-.077.077-.157.161-.067.315.09.153.398.657.854 1.063.587.522 1.082.684 1.236.76.153.077.243.064.333-.038.09-.102.384-.447.486-.6.102-.153.205-.128.342-.077.137.051.87.41 1.02.486.15.077.25.115.286.179.036.064.036.371-.104.765z"/>
                        </svg>
                        <span>Direkt per WhatsApp anfragen</span>
                    </a>
                </div>

                <!-- Builder Trust Metric Line -->
                <div class="pt-2 sm:pt-3 flex flex-wrap items-center justify-center gap-2 sm:gap-3 text-center">
                    <div class="flex items-center text-amber-500 text-xs tracking-wider">
                        ★ ★ ★ ★ ★
                    </div>
                    <span class="text-[11px] sm:text-xs text-slate-600 font-bold">
                        <strong class="text-slate-950">4.9 / 5.0</strong> von über 120 Bauleitern & Bauträgern geschätzt
                    </span>
                    <span class="hidden sm:inline-block text-slate-300">•</span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] sm:text-xs text-slate-600 font-bold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Keine Installation oder Kreditkarte nötig
                    </span>
                </div>

            </div>

            <!-- Architectural Cockpit Preview with City Construct Style Gallery Switcher -->
            <div class="mt-12 sm:mt-16 max-w-6xl mx-auto arch-card p-2 sm:p-4 border-slate-300 shadow-2xl relative">
                
                <!-- Floating CAD Dimension Badge 1: Top-Right -->
                <div class="hidden md:flex items-center gap-3 px-4 py-2.5 rounded-xl bg-white/95 backdrop-blur-md border border-slate-200 shadow-xl absolute -top-6 right-6 z-30 text-left">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center font-bold text-sm shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                        </svg>
                    </div>
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span class="text-[9.5px] font-black uppercase text-slate-500 tracking-wider">Whisper KI Engine</span>
                        </div>
                        <p class="text-[11.5px] font-black text-slate-950 leading-tight">14:32 Tiefgarage: 3 Mängel & Wetter erfasst</p>
                    </div>
                </div>

                <!-- Floating CAD Dimension Badge 2: Bottom-Left -->
                <div class="hidden md:flex items-center gap-3 px-4 py-2.5 rounded-xl bg-slate-950 text-white border border-slate-800 shadow-2xl absolute -bottom-6 left-6 z-30 text-left">
                    <div class="w-8 h-8 rounded-lg bg-amber-500 text-slate-950 flex items-center justify-center font-bold text-sm shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-[9.5px] font-mono text-amber-400 font-bold uppercase tracking-wider">VOB/B § 2 Abs. 6 Freigabe</span>
                        <p class="text-[11.5px] font-black text-white leading-tight">+ 4.850,00 € Nachtrag rechtssicher als PDF</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-200 overflow-hidden relative z-10">
                    
                    <!-- Architectural Frame Window Header -->
                    <div class="px-4 sm:px-6 py-3 bg-slate-950 border-b border-slate-800 flex items-center justify-between text-white">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-600"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-600"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-600"></span>
                            <span class="text-[11px] sm:text-xs text-slate-400 font-mono ml-2">
                                bt-bautechnik.de / cockpit / projektsteuerung
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[9.5px] font-mono uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold">
                                VOB/B & DATEV AKTIV
                            </span>
                        </div>
                    </div>

                    <!-- Split Photo & Cockpit Layer -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 bg-slate-50">
                        
                        <!-- Left: High-End Real On-Site Photography -->
                        <div class="lg:col-span-5 relative overflow-hidden border-b lg:border-b-0 lg:border-r border-slate-200 group">
                            <img src="{{ asset('images/bauleiter-tablet-hero.jpg') }}" 
                                 alt="Bauleiter vor Ort mit digitalem BT Bautechnik Tablet Cockpit" 
                                 class="w-full h-56 sm:h-72 lg:h-full object-cover min-h-[220px] lg:min-h-[440px] group-hover:scale-105 transition-transform duration-700">
                            
                            <!-- Architectural Blueprint Floating Badge -->
                            <div class="absolute bottom-3 left-3 right-3 sm:bottom-4 sm:left-4 sm:right-4 bg-slate-950/90 backdrop-blur-md text-white p-3 sm:p-4 rounded-xl border border-white/10 shadow-lg">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                    <span class="text-[10px] sm:text-xs font-black text-amber-400 uppercase tracking-wider">Echte Baustelle vor Ort</span>
                                </div>
                                <p class="text-[11px] sm:text-xs text-slate-300 font-medium leading-relaxed">
                                    Bautagesberichte, digitale VOB/C Aufmaße und Mängelerfassung in Echtzeit auf dem Smartphone & Tablet.
                                </p>
                            </div>
                        </div>

                        <!-- Right: Interactive Cockpit KPIs & Status -->
                        <div class="lg:col-span-7 p-4 sm:p-7 space-y-4 sm:space-y-5 flex flex-col justify-between">
                            
                            <!-- Project Header -->
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-white p-3.5 sm:p-4 rounded-xl border border-slate-200 shadow-xs">
                                <div>
                                    <span class="text-[9.5px] font-mono text-amber-700 font-black uppercase tracking-wider">BAUVORHABEN #2026-081</span>
                                    <h3 class="text-sm sm:text-base font-black text-slate-950">WEG Maximilianstraße 44 – Tiefgaragenabdichtung</h3>
                                    <p class="text-[11px] text-slate-500 font-medium">Auftraggeber: Hausverwaltung Müller & Partner GmbH</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 border border-slate-200 font-black text-[10px] sm:text-xs">
                                        KW 32 – 38
                                    </span>
                                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 font-black text-[10px] sm:text-xs">
                                        Im Soll
                                    </span>
                                </div>
                            </div>

                            <!-- Progress & Budget Metric Tiles -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                    <span class="text-[9.5px] text-slate-500 font-bold uppercase block">Budget Soll</span>
                                    <p class="text-sm sm:text-base font-black text-slate-950 mt-0.5 tabular-nums">85.000,00 €</p>
                                    <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                                        <div class="bg-slate-900 h-full w-[65%]"></div>
                                    </div>
                                </div>
                                <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                    <span class="text-[9.5px] text-slate-500 font-bold uppercase block">Nachträge (VOB/B)</span>
                                    <p class="text-sm sm:text-base font-black text-amber-700 mt-0.5 tabular-nums">+ 12.450,00 €</p>
                                    <span class="text-[9.5px] text-emerald-700 font-bold">3 freigegeben</span>
                                </div>
                                <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                    <span class="text-[9.5px] text-slate-500 font-bold uppercase block">Aufmaß (VOB/C)</span>
                                    <p class="text-sm sm:text-base font-black text-slate-950 mt-0.5 tabular-nums">620 m² / 750 m²</p>
                                    <span class="text-[9.5px] text-slate-600 font-bold">82% fertig</span>
                                </div>
                            </div>

                            <!-- Quick Action Row -->
                            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2 text-slate-700 font-medium text-[11px] sm:text-xs">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>Bautagesbericht heute per Sprachmemo erfasst</span>
                                </div>
                                <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-800 border border-slate-200 rounded-lg font-bold text-[10px]">
                                        PDF-Export
                                    </span>
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg font-bold text-[10px]">
                                        Aufmaß freigegeben
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 3. ARCHITECTURAL TRUST & COMPLIANCE RIBBON (6 COLS)                       -->
    <!-- ========================================================================= -->
    <div class="border-y border-slate-200/90 bg-white py-6 sm:py-8 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 text-[11px] sm:text-xs text-slate-700 font-bold">
                
                <!-- VOB/B -->
                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition">
                    <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-900 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-950">VOB/B § 2</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Rechtssicher</span>
                    </div>
                </div>

                <!-- DATEV -->
                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition">
                    <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-900 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-slate-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-950">DATEV Export</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">SKR03 / SKR04</span>
                    </div>
                </div>

                <!-- DIN 18299 -->
                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition">
                    <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-900 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-950">DIN 18299/18533</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">VOB/C Aufmaße</span>
                    </div>
                </div>

                <!-- GAEB -->
                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition">
                    <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-900 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-slate-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-950">GAEB & GoBD</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Revisionssicher</span>
                    </div>
                </div>

                <!-- DSGVO DE -->
                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition">
                    <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-900 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-950">100% DSGVO</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Server Frankfurt</span>
                    </div>
                </div>

                <!-- Offline PWA -->
                <div class="col-span-2 sm:col-span-1 flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition">
                    <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-900 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-950">PWA Offline-First</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Kein Funkloch-Stopp</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. DIE STORY: VON BAUUNTERNEHMERN FÜR BAUUNTERNEHMER                      -->
    <!-- ========================================================================= -->
    <section id="story" class="py-14 sm:py-24 relative overflow-hidden">
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Section Header -->
            <div class="max-w-3xl mb-12 space-y-3">
                <div class="arch-section-label">
                    <span>AUS DER BAU-PRAXIS</span>
                </div>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight leading-tight">
                    Wir bauen selbst.<br>
                    <span class="text-amber-600">Wir kennen jeden Engpass auf der Baustelle.</span>
                </h2>
                <p class="text-xs sm:text-base text-slate-600 font-medium leading-relaxed">
                    Hinter dieser Lösung steht kein reines Softwarehaus, sondern die <strong>BT Bautechnik UG (haftungsbeschränkt)</strong> mit Sitz in Berching. Jede Funktion löst ein reales Problem, das wir selbst auf unseren Bauvorhaben gelöst haben:
                </p>
            </div>

            <!-- Bento Grid Problem -> Solution -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 items-start">
                
                <!-- Card 1: Nachträge -->
                <div class="arch-card p-6 sm:p-8 space-y-4 group">
                    <div class="flex items-center justify-between">
                        <span class="px-3 py-1 rounded-full text-[9.5px] font-black uppercase tracking-wider bg-rose-50 text-rose-800 border border-rose-200">
                            Problem vor Ort
                        </span>
                        <span class="text-[11px] font-black text-amber-700 font-mono">
                            VOB/B § 2
                        </span>
                    </div>
                    <h3 class="font-black text-slate-950 text-base sm:text-lg group-hover:text-amber-600 transition-colors">
                        Nachträge wurden vergessen oder mündlich verhandelt
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Weil Poliere und Bauleiter vor Ort keine Zeit hatten, am PC Angebote zu tippen, blieben berechtigte Mehrleistungen unvergütet.
                    </p>
                    <div class="pt-3 border-t border-slate-100 flex items-start gap-2.5 text-xs font-bold text-slate-900 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                        <span class="text-amber-600 font-black text-base leading-none">✓</span>
                        <span><strong>BT Lösung:</strong> Nachtragsangebot nach § 2 VOB/B mit 2 Klicks vor Ort als PDF erzeugen.</span>
                    </div>
                </div>

                <!-- Card 2: Bautagebuch -->
                <div class="arch-card p-6 sm:p-8 space-y-4 group">
                    <div class="flex items-center justify-between">
                        <span class="px-3 py-1 rounded-full text-[9.5px] font-black uppercase tracking-wider bg-rose-50 text-rose-800 border border-rose-200">
                            Problem vor Ort
                        </span>
                        <span class="text-[11px] font-black text-slate-700 font-mono">
                            Whisper KI
                        </span>
                    </div>
                    <h3 class="font-black text-slate-950 text-base sm:text-lg group-hover:text-amber-600 transition-colors">
                        Mühsame Bautagebücher nach 10 Stunden Arbeit
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Niemand tippt abends gern Berichte. Die Folge: Lückenhafte Dokumentation und Beweisnot bei späteren Gewährleistungsstreitigkeiten.
                    </p>
                    <div class="pt-3 border-t border-slate-100 flex items-start gap-2.5 text-xs font-bold text-slate-900 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                        <span class="text-amber-600 font-black text-base leading-none">✓</span>
                        <span><strong>BT Lösung:</strong> 30s Sprachmemo einsprechen – KI formuliert fertigen Tagesbericht samt Wetter & Fotos.</span>
                    </div>
                </div>

                <!-- Card 3: Steuerberater -->
                <div class="arch-card p-6 sm:p-8 space-y-4 group">
                    <div class="flex items-center justify-between">
                        <span class="px-3 py-1 rounded-full text-[9.5px] font-black uppercase tracking-wider bg-rose-50 text-rose-800 border border-rose-200">
                            Problem vor Ort
                        </span>
                        <span class="text-[11px] font-black text-slate-700 font-mono">
                            SKR03 / SKR04
                        </span>
                    </div>
                    <h3 class="font-black text-slate-950 text-base sm:text-lg group-hover:text-amber-600 transition-colors">
                        Monatsabschluss-Chaos mit Subunternehmern
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Unvollständige Nachunternehmer-Rechnungen und manuelle Übertragungsfehler nach § 13b UStG belasten die Buchhaltung unnötig.
                    </p>
                    <div class="pt-3 border-t border-slate-100 flex items-start gap-2.5 text-xs font-bold text-slate-900 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                        <span class="text-amber-600 font-black text-base leading-none">✓</span>
                        <span><strong>BT Lösung:</strong> Standardisierter DATEV Buchungsstapel-Export mit automatischen Steuerschlüsseln.</span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 5. CITY CONSTRUCT STYLE: LEISTUNGS- & MODULGRID MIT FEATURED-CARD        -->
    <!-- ========================================================================= -->
    <section id="module" class="py-14 sm:py-24 bg-white border-t border-slate-200/90 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-3 mb-12 sm:mb-16">
                <div class="arch-section-label">
                    <span>SYSTEMÜBERSICHT & MODULE</span>
                </div>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight">
                    Vollständige Kontrolle für Ihr Bauunternehmen
                </h2>
                <p class="text-xs sm:text-base text-slate-600 font-medium">
                    Sechs exakt aufeinander abgestimmte Kernmodule für Bauleiter, Poliere, Projektleiter und Geschäftsführung:
                </p>
            </div>

            <!-- 6-Grid with Inverted Featured Card (City Construct Architecture) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                
                <!-- Card 1: Cockpit -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 text-slate-950 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5 text-slate-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 font-bold uppercase">MODUL 01</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-950 group-hover:text-amber-600 transition-colors">
                            Baustellen-Cockpit & Soll/Ist
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Echtzeit-Kostenüberwachung, Bauzeitenplan nach Kalenderwochen und automatischer Wetter-Abruf per GPS.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-900">
                        <button type="button" wire:click="openDemoModal('hoch_tiefbau')" class="text-amber-700 hover:text-amber-600 cursor-pointer flex items-center gap-1.5">
                            <span>Mehr erfahren</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

                <!-- Card 2: Kunden 360 -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 text-slate-950 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5 text-slate-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 font-bold uppercase">MODUL 02</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-950 group-hover:text-amber-600 transition-colors">
                            360° Kunden- & Bauherren-Zentrale
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Alle Baustellen, Nachträge, Aufmaße und Notizen eines Bauherrn an einem zentralen Ort gebündelt.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-900">
                        <button type="button" wire:click="openDemoModal('generalunternehmer')" class="text-amber-700 hover:text-amber-600 cursor-pointer flex items-center gap-1.5">
                            <span>Mehr erfahren</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

                <!-- Card 3: FEATURED INVERTED CARD (VOB/B Nachtragsmanagement) -->
                <div class="arch-card-featured p-6 sm:p-8 flex flex-col justify-between space-y-4 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-11 h-11 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-bold shadow-md shadow-amber-500/20">
                                <svg class="w-5 h-5 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-[9.5px] font-mono text-amber-300 bg-amber-500/20 border border-amber-400/40 uppercase font-black">
                                KERN-FEATURE
                            </span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-white group-hover:text-amber-400 transition-colors">
                            VOB/B Nachtragsmanagement (§ 2)
                        </h3>
                        <p class="text-xs text-slate-300 leading-relaxed font-medium">
                            Automatische Unterscheidung nach § 2 Abs. 5 und § 2 Abs. 6. Erstellung von rechtssicheren PDF-Angeboten vor Ausführung.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-xs font-bold text-white">
                        <button type="button" wire:click="openDemoModal('sanierung_abdichtung')" class="text-amber-400 hover:text-amber-300 cursor-pointer flex items-center gap-1.5">
                            <span>Nachtrags-Automatik testen</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

                <!-- Card 4: Aufmaß -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 text-slate-950 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5 text-slate-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                                </svg>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 font-bold uppercase">MODUL 04</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-950 group-hover:text-amber-600 transition-colors">
                            Digitales Aufmaßblatt (VOB/C)
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Formelberechnung (L×B×H), automatischer VOB-Abzug nach DIN 18299 / DIN 18336 und 1-Klick Übergabe in die Schlussrechnung.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-900">
                        <button type="button" wire:click="openDemoModal('hoch_tiefbau')" class="text-amber-700 hover:text-amber-600 cursor-pointer flex items-center gap-1.5">
                            <span>Mehr erfahren</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

                <!-- Card 5: KI-Bautagebuch -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 text-slate-950 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5 text-slate-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                                </svg>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 font-bold uppercase">MODUL 05</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-950 group-hover:text-amber-600 transition-colors">
                            KI-Bautagebuch & Sprachmemo
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            30 Sekunden Audio auf der Baustelle einsprechen – Whisper KI formuliert den druckreifen Tagesbericht mit Gewerken & Wetter.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-900">
                        <button type="button" wire:click="openDemoModal('bautraeger')" class="text-amber-700 hover:text-amber-600 cursor-pointer flex items-center gap-1.5">
                            <span>Mehr erfahren</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

                <!-- Card 6: DATEV SKR03/04 -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4 group">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 text-slate-950 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5 text-slate-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 font-bold uppercase">MODUL 06</span>
                        </div>
                        <h3 class="text-base sm:text-lg font-black text-slate-950 group-hover:text-amber-600 transition-colors">
                            DATEV & § 13b UStG Controlling
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Standardisierter Buchungsstapel-Export an den Steuerberater inkl. automatischer Nachunternehmer-Steuerschlüssel.
                        </p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-slate-900">
                        <button type="button" wire:click="openDemoModal('generalunternehmer')" class="text-amber-700 hover:text-amber-600 cursor-pointer flex items-center gap-1.5">
                            <span>Mehr erfahren</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 6. CITY CONSTRUCT STYLE: HORIZONTALES SCHNELL-ANFRAGE DOCK                -->
    <!-- ========================================================================= -->
    <section class="py-12 bg-slate-950 text-white relative overflow-hidden border-y border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="arch-dock-dark p-6 sm:p-8">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                    
                    <div class="space-y-1 max-w-md">
                        <span class="text-[10px] font-mono uppercase text-amber-400 font-black tracking-wider block">
                            — UNVERBINDLICHE SCHNELL-ANFRAGE —
                        </span>
                        <h3 class="text-lg sm:text-2xl font-black text-white tracking-tight">
                            Live-Präsentation für Ihr Bauunternehmen
                        </h3>
                        <p class="text-xs text-slate-400 font-medium">
                            Direkte Vorführung über Teams/Zoom oder vor Ort in Ihrem Betrieb.
                        </p>
                    </div>

                    <form wire:submit="submitDemoRequest" class="w-full lg:flex-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <div>
                            <input wire:model="demoName" type="text" placeholder="Ihr Name *" class="w-full bg-slate-900 border border-slate-700 text-white placeholder-slate-500 rounded-xl px-3.5 py-3 focus:border-amber-500 focus:outline-none" required>
                        </div>
                        <div>
                            <input wire:model="demoCompany" type="text" placeholder="Firma / Betrieb *" class="w-full bg-slate-900 border border-slate-700 text-white placeholder-slate-500 rounded-xl px-3.5 py-3 focus:border-amber-500 focus:outline-none" required>
                        </div>
                        <div>
                            <input wire:model="demoPhone" type="tel" placeholder="Telefon / Mobil *" class="w-full bg-slate-900 border border-slate-700 text-white placeholder-slate-500 rounded-xl px-3.5 py-3 focus:border-amber-500 focus:outline-none" required>
                        </div>
                        <div>
                            <button type="submit" class="w-full h-full py-3 px-4 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 transition cursor-pointer flex items-center justify-center gap-1.5 btn-press">
                                <span>Demo anfordern</span>
                                <span>→</span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 7. INTERAKTIVER WHISPER KI-BAGEBUCH VOICE SIMULATOR                       -->
    <!-- ========================================================================= -->
    <section class="py-14 sm:py-24 bg-slate-50 border-b border-slate-200 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-14">
                <div class="arch-section-label">
                    <span>LIVE-DEMO SIMULATOR</span>
                </div>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight">
                    In 30 Sekunden vom Sprachmemo zum fertigen VOB-Bericht
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    Testen Sie direkt im Browser, wie unsere KI Sprachnachrichten von der Baustelle strukturiert:
                </p>
            </div>

            <!-- Voice Simulator Widget -->
            <div x-data="{
                isPlaying: false,
                seconds: 0,
                interval: null,
                activeSample: 'abdichtung',
                samples: {
                    abdichtung: {
                        tag: 'Bautagesbericht vor Ort',
                        title: 'Baustelle Maximilianstraße 44 – Tiefgarage',
                        audioText: '„Servus, heute 4 Mann vor Ort. Tiefgaragenabdichtung nach DIN 18533 planmäßig abgeschlossen. Im Kellerabgang 1 Riss an WU-Wand entdeckt – Mangel mit Foto angelegt. Wetter trocken, 19 Grad.“',
                        weather: '19°C • Sonnig & Trocken (GPS Auto-Wetter)',
                        workers: '4 Fachmonteure',
                        taskDone: 'Abdichtung DIN 18533 abgeschlossen (620 m²)',
                        defectDetected: 'Mangel #14: Riss WU-Wand Kellerabgang',
                        outputPdf: 'Bautagesbericht #42 & Mängelanzeige als PDF generiert'
                    },
                    nachtrag: {
                        tag: 'VOB/B § 2 Mehrvergütung',
                        title: 'Sanierung Wohnanlage Am Mühlbach 12',
                        audioText: '„Bauherr Müller hat heute vor Ort Zusatzdämmung an der Nordfassade beauftragt. Entspricht VOB/B § 2 Absatz 6. 120 Quadratmeter EPS 032 Dämmplatten zusätzlich erforderlich.“',
                        weather: '21°C • Leicht bewölkt',
                        workers: '3 Facharbeiter',
                        taskDone: 'Zusatzleistung Nordfassade aufgenommen',
                        defectDetected: 'Keine Baumängel erfasst',
                        outputPdf: 'VOB/B § 2 Abs. 6 Nachtragsangebot #104 (+ 4.850,00 €) sofort fertig'
                    }
                },
                play() {
                    this.isPlaying = true;
                    this.seconds = 0;
                    if (this.interval) clearInterval(this.interval);
                    this.interval = setInterval(() => {
                        if (this.seconds < 10) {
                            this.seconds++;
                        } else {
                            this.isPlaying = false;
                            clearInterval(this.interval);
                        }
                    }, 500);
                },
                stop() {
                    this.isPlaying = false;
                    clearInterval(this.interval);
                }
            }" class="arch-dock-dark p-6 sm:p-8 space-y-6">
                
                <!-- Scenario Switcher -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-800">
                    <div class="space-y-1">
                        <span class="text-[10px] font-mono text-amber-400 font-black uppercase tracking-wider">
                            SPRACHERKENNUNG LIVE TESTEN
                        </span>
                        <h3 class="text-base sm:text-xl font-black text-white">
                            Wählen Sie ein Baustellen-Szenario:
                        </h3>
                    </div>

                    <div class="flex items-center gap-2 p-1 bg-slate-900 rounded-xl border border-slate-800 text-xs">
                        <button type="button" 
                                @click="activeSample = 'abdichtung'; stop(); seconds = 0;" 
                                :class="activeSample === 'abdichtung' ? 'bg-amber-500 text-slate-950 font-black' : 'text-slate-400 hover:text-white'" 
                                class="px-3.5 py-1.5 rounded-lg transition text-[11px] sm:text-xs cursor-pointer">
                            Szenario 1: Bautagesbericht
                        </button>
                        <button type="button" 
                                @click="activeSample = 'nachtrag'; stop(); seconds = 0;" 
                                :class="activeSample === 'nachtrag' ? 'bg-amber-500 text-slate-950 font-black' : 'text-slate-400 hover:text-white'" 
                                class="px-3.5 py-1.5 rounded-lg transition text-[11px] sm:text-xs cursor-pointer">
                            Szenario 2: VOB-Nachtrag
                        </button>
                    </div>
                </div>

                <!-- Player & Extracted Output Split -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                    
                    <!-- Left: Audio Controls -->
                    <div class="lg:col-span-5 bg-slate-900/90 p-5 rounded-2xl border border-slate-800 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10.5px] font-mono text-amber-400 uppercase font-black tracking-wider" x-text="samples[activeSample].tag"></span>
                            <span class="text-[11px] font-mono text-slate-400" x-text="isPlaying ? '0:0' + seconds + ' / 0:10' : (seconds >= 10 ? '0:10 / 0:10' : '0:00 / 0:10')"></span>
                        </div>

                        <!-- Audio Play Button & Visualizer -->
                        <div class="flex items-center gap-3">
                            <button type="button" 
                                    @click="isPlaying ? stop() : play()" 
                                    class="w-12 h-12 rounded-xl bg-amber-500 text-slate-950 font-black text-xl flex items-center justify-center shadow-lg shadow-amber-500/20 hover:scale-105 active:scale-95 transition-all cursor-pointer">
                                <span x-show="!isPlaying">▶</span>
                                <span x-show="isPlaying" x-cloak>❚❚</span>
                            </button>

                            <!-- Audio Waveform Bars -->
                            <div class="flex-1 flex items-center gap-1.5 h-10 px-3 bg-slate-950 rounded-xl border border-slate-800">
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-1' : 'h-2'"></div>
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-2' : 'h-4'"></div>
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-3' : 'h-3'"></div>
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-4' : 'h-6'"></div>
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-5' : 'h-4'"></div>
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-6' : 'h-5'"></div>
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-7' : 'h-2'"></div>
                                <div class="w-1 bg-amber-400 rounded-full" :class="isPlaying ? 'wave-bar-8' : 'h-3'"></div>
                            </div>
                        </div>

                        <!-- Spoken Voice-Memo Quote -->
                        <div class="p-3.5 bg-slate-950 rounded-xl border border-slate-800 text-xs">
                            <span class="text-[9.5px] text-slate-400 uppercase font-bold block mb-1">Eingesprochene Audionachricht:</span>
                            <p class="text-amber-200 italic font-mono text-[11px] leading-relaxed" x-text="samples[activeSample].audioText"></p>
                        </div>
                    </div>

                    <!-- Right: Structured Output -->
                    <div class="lg:col-span-7 bg-slate-900/90 p-5 sm:p-6 rounded-2xl border border-slate-800 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span class="text-xs font-black text-white">Automatisch strukturierter VOB-Bericht</span>
                            </div>
                            <span class="text-[10px] font-mono text-amber-400 bg-amber-950/60 px-2 py-0.5 rounded border border-amber-500/30">
                                100% VOB-KONFORM
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Wetterdaten (GPS)</span>
                                <p class="text-[11.5px] font-bold text-slate-200" x-text="samples[activeSample].weather"></p>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Anwesende Fachkräfte</span>
                                <p class="text-[11.5px] font-bold text-slate-200" x-text="samples[activeSample].workers"></p>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Ausgeführte Leistungen</span>
                                <p class="text-[11.5px] font-bold text-amber-300" x-text="samples[activeSample].taskDone"></p>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Mängel- & Nachtragsstatus</span>
                                <p class="text-[11.5px] font-bold text-slate-200" x-text="samples[activeSample].defectDetected"></p>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="text-amber-400 font-bold">PDF:</span>
                                <span class="text-slate-200 font-bold text-[11px]" x-text="samples[activeSample].outputPdf"></span>
                            </div>
                            <span class="px-2.5 py-1 rounded bg-amber-500 text-slate-950 font-black text-[10px]">
                                Bereit
                            </span>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 8. INTERAKTIVER ROI & ERSPARNISRECHNER                                     -->
    <!-- ========================================================================= -->
    <section id="rechner" class="py-14 sm:py-24 bg-white border-b border-slate-200 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-14">
                <div class="arch-section-label">
                    <span>WIRTSCHAFTLICHKEIT</span>
                </div>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight">
                    Berechnen Sie Ihre Ersparnis & Nachtragserlöse
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    Passen Sie die Schieberegler an Ihre Betriebsgröße an:
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center max-w-5xl mx-auto">
                
                <!-- Left: Sliders -->
                <div class="lg:col-span-6 arch-card p-6 sm:p-8 space-y-6">
                    
                    <!-- Slider 1 -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-900">Gleichzeitige Baustellen:</label>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-950 font-black text-xs sm:text-sm border border-slate-200 tabular-nums">
                                {{ $roiProjectCount }} Baustellen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiProjectCount" min="1" max="25" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-slate-950">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>1 Baustelle</span>
                            <span>25 Baustellen</span>
                        </div>
                    </div>

                    <!-- Slider 2 -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-900">Mitarbeiter & Bauleiter:</label>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-950 font-black text-xs sm:text-sm border border-slate-200 tabular-nums">
                                {{ $roiWorkerCount }} Personen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiWorkerCount" min="2" max="40" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-slate-950">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>2 Mitarbeiter</span>
                            <span>40 Mitarbeiter</span>
                        </div>
                    </div>

                    <!-- Slider 3 -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-900">Kalkulatorischer Stundensatz:</label>
                            <span class="px-3 py-1 rounded-lg bg-amber-50 text-amber-800 font-black text-xs sm:text-sm border border-amber-200 tabular-nums">
                                {{ $roiHourlyRate }} € / Std.
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiHourlyRate" min="45" max="110" step="5" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-amber-600">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>45 €</span>
                            <span>110 €</span>
                        </div>
                    </div>

                </div>

                <!-- Right: Results Card -->
                <div class="lg:col-span-6 arch-card-featured p-6 sm:p-8 space-y-6">
                    
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-amber-400 tracking-wider">Ihr kalkulierter Jahresvorteil</span>
                        <h4 class="text-3xl sm:text-5xl font-black text-white tabular-nums tracking-tight">
                            ~ {{ number_format($this->totalValuePerYear, 0, ',', '.') }} € <span class="text-xs sm:text-sm text-slate-400 font-medium">/ Jahr</span>
                        </h4>
                    </div>

                    <div class="space-y-3 text-xs pt-3 border-t border-slate-800">
                        <div class="flex justify-between items-center p-3 bg-slate-950 rounded-xl border border-white/5">
                            <span class="text-slate-300">Eingesparte Büro- & Doku-Zeit:</span>
                            <span class="font-black text-white tabular-nums text-sm">~ {{ $this->savedHoursPerMonth }} Std. / Monat</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950 rounded-xl border border-white/5">
                            <span class="text-slate-300">Bürokratiekosten-Ersparnis:</span>
                            <span class="font-black text-emerald-400 tabular-nums text-sm">{{ number_format($this->savedCostPerYear, 0, ',', '.') }} € / Jahr</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950 rounded-xl border border-white/5">
                            <span class="text-slate-300">Zusätzliche Nachtragserlöse (VOB/B):</span>
                            <span class="font-black text-amber-400 tabular-nums text-sm">+ {{ number_format($this->additionalSupplementRevenue, 0, ',', '.') }} € / Jahr</span>
                        </div>
                    </div>

                    <button wire:click="openDemoModal" class="w-full py-3.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 transition cursor-pointer btn-press">
                        Diesen Vorteil für Ihren Betrieb sichern →
                    </button>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 9. VORHER VS. NACHHER VERGLEICH (ARCHITECTURAL SPLIT)                     -->
    <!-- ========================================================================= -->
    <section id="vorteile" x-data="{ viewMode: 'both' }" class="py-14 sm:py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-12">
            <div class="arch-section-label">
                <span>DIREKTER VERGLEICH</span>
            </div>
            <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight">
                Vorher vs. Nachher: Ihr Baustellenalltag transformiert
            </h2>
            <p class="text-xs sm:text-sm text-slate-600 font-medium">
                Sehen Sie den Unterschied zwischen gewohntem Papierchaos und moderner digitaler Bauleitung:
            </p>

            <!-- View Switcher -->
            <div class="pt-2 inline-flex items-center gap-1.5 p-1 bg-slate-200/80 rounded-xl border border-slate-300 text-xs">
                <button type="button" 
                        @click="viewMode = 'both'" 
                        :class="viewMode === 'both' ? 'bg-white text-slate-950 shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 font-bold'" 
                        class="px-3.5 py-1.5 rounded-lg transition text-[11px] sm:text-xs cursor-pointer">
                    Nebeneinander
                </button>
                <button type="button" 
                        @click="viewMode = 'before'" 
                        :class="viewMode === 'before' ? 'bg-rose-50 text-rose-900 border border-rose-200 font-black' : 'text-slate-600 hover:text-slate-900 font-bold'" 
                        class="px-3.5 py-1.5 rounded-lg transition text-[11px] sm:text-xs cursor-pointer">
                    Ohne Software
                </button>
                <button type="button" 
                        @click="viewMode = 'after'" 
                        :class="viewMode === 'after' ? 'bg-slate-950 text-white font-black' : 'text-slate-600 hover:text-slate-900 font-bold'" 
                        class="px-3.5 py-1.5 rounded-lg transition text-[11px] sm:text-xs cursor-pointer">
                    Mit BT Cockpit
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 max-w-5xl mx-auto">
            
            <!-- BEFORE CARD -->
            <div x-show="viewMode === 'both' || viewMode === 'before'" 
                 class="arch-card p-6 sm:p-8 border-rose-200 space-y-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-black text-sm shrink-0">
                        ✕
                    </div>
                    <div>
                        <h4 class="font-black text-slate-950 text-sm sm:text-base">Klassischer Baualltag (Vorher)</h4>
                        <span class="text-[10.5px] text-rose-700 font-bold">Hoher Zeitverlust & Haftungsrisiko</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-600 font-medium">
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Papier-Bautagebücher:</strong> Werden unvollständig oder erst Tage später aus dem Gedächtnis ausgefüllt.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Verlorene VOB-Nachträge:</strong> Mehrleistungen werden auf Zuruf ausgeführt, aber am Ende vom Bauherrn bestritten.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Aufmaß-Streitigkeiten:</strong> Unleserliche Handzettel führen zu Verzögerungen bei der Schlussrechnung.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Monatsabschluss-Chaos:</strong> Stundenzettel und Subunternehmerrechnungen müssen manuell abgetippt werden.</span>
                    </li>
                </ul>
            </div>

            <!-- AFTER CARD -->
            <div x-show="viewMode === 'both' || viewMode === 'after'" 
                 class="arch-card p-6 sm:p-8 border-amber-400 shadow-xl space-y-5 relative">
                <div class="absolute -top-3 right-6 px-3 py-1 bg-slate-950 text-amber-400 rounded-full text-[9.5px] font-black uppercase border border-slate-800">
                    Empfohlener Standard
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-black text-sm shrink-0">
                        ✓
                    </div>
                    <div>
                        <h4 class="font-black text-slate-950 text-sm sm:text-base">Mit BT Bautechnik Cockpit (Nachher)</h4>
                        <span class="text-[10.5px] text-emerald-700 font-bold">100% rechtssicher, digital & rentabel</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-800 font-semibold">
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-amber-50/60">
                        <span class="text-amber-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>30s KI-Sprachmemo:</strong> Erzeugt das vollständige Bautagebuch samt Wetter, Fotos und Mängeln sofort.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-amber-50/60">
                        <span class="text-amber-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>1-Klick Nachträge VOB/B § 2:</strong> Rechtssichere PDF-Angebote mit offiziellem Briefkopf vor Ausführung.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-amber-50/60">
                        <span class="text-amber-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>Digitales Aufmaß (DIN 18299):</strong> Transparente Berechnungsformeln und sofortige Freigabe durch den Bauherrn.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-lg bg-amber-50/60">
                        <span class="text-amber-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>DATEV SKR03/04 Export:</strong> Automatische § 13b UStG Steuerschlüssel für Subunternehmer auf Knopfdruck.</span>
                    </li>
                </ul>
            </div>

        </div>

    </section>

    <!-- ========================================================================= -->
    <!-- 10. PRAXIS-STIMMEN & TESTIMONIALS                                         -->
    <!-- ========================================================================= -->
    <section class="py-14 sm:py-24 bg-white border-t border-slate-200/90 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-14">
                <div class="arch-section-label">
                    <span>ERFAHRUNGSBERICHTE</span>
                </div>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight">
                    Was Bauleiter & Bauträger sagen
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    Praxisberichte von Unternehmen, die ihre Baustellen digitalisieren:
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                
                <!-- Testimonial 1 -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center text-amber-500 text-xs tracking-wider">
                            ★ ★ ★ ★ ★
                        </div>
                        <p class="text-xs sm:text-[13px] text-slate-700 leading-relaxed font-medium">
                            „Früher sind uns bei fast jedem Projekt mehrere tausend Euro an VOB-Nachträgen durchgerutscht, weil auf der Baustelle niemand Zeit zum Schreiben hatte. Mit dem KI-Bautagebuch ist der Tagesbericht in 45 Sekunden fertig.“
                        </p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-900 font-black flex items-center justify-center text-xs shrink-0 border border-slate-200">
                            SM
                        </div>
                        <div>
                            <h5 class="text-xs font-black text-slate-950">Dipl.-Ing. Stefan Maier</h5>
                            <span class="text-[11px] text-slate-500 font-medium block">Geschäftsführer Bau & Sanierung GmbH, München</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center text-amber-500 text-xs tracking-wider">
                            ★ ★ ★ ★ ★
                        </div>
                        <p class="text-xs sm:text-[13px] text-slate-700 leading-relaxed font-medium">
                            „Die DATEV-Übergabe mit SKR03 und der automatischen § 13b-Zuordnung für Nachunternehmer spart unserer Buchhaltung 2 volle Tage am Monatsende. Absoluter Gamechanger für unseren Betrieb.“
                        </p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-900 font-black flex items-center justify-center text-xs shrink-0 border border-slate-200">
                            MW
                        </div>
                        <div>
                            <h5 class="text-xs font-black text-slate-950">Markus Weber</h5>
                            <span class="text-[11px] text-slate-500 font-medium block">Bauleiter Schlüsselfertigbau, Nürnberg</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="arch-card p-6 sm:p-8 flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center text-amber-500 text-xs tracking-wider">
                            ★ ★ ★ ★ ★
                        </div>
                        <p class="text-xs sm:text-[13px] text-slate-700 leading-relaxed font-medium">
                            „Endlich eine Software ohne überflüssigen Schnickschnack. Meine Poliere vor Ort bedienen das System ohne jede Schulung direkt auf dem Smartphone im Browser. Einfach genial.“
                        </p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-900 font-black flex items-center justify-center text-xs shrink-0 border border-slate-200">
                            TB
                        </div>
                        <div>
                            <h5 class="text-xs font-black text-slate-950">Thomas Brandl</h5>
                            <span class="text-[11px] text-slate-500 font-medium block">Bauträger & Projektentwickler, Regensburg</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 11. FAQ SECTION                                                           -->
    <!-- ========================================================================= -->
    <section id="faq" class="py-14 sm:py-20 bg-slate-50 border-t border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center space-y-3 mb-8 sm:mb-10">
                <div class="arch-section-label">
                    <span>HÄUFIGE FRAGEN</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-950 tracking-tight">
                    Fragen von Bauträgern & Bauunternehmen
                </h2>
            </div>

            <div x-data="{ openFaq: 0 }" class="space-y-3 text-xs">
                
                <div class="arch-card p-4 sm:p-5">
                    <button type="button" @click="openFaq = (openFaq === 0 ? null : 0)" class="w-full flex justify-between items-center text-left font-black text-slate-950 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Ist die Software auf Smartphones und Tablets auf der Baustelle nutzbar?</span>
                        <span class="text-amber-600 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 0 ? '−' : '+'">−</span>
                    </button>
                    <p x-show="openFaq === 0" x-cloak class="mt-3 text-slate-600 leading-relaxed pt-3 border-t border-slate-100 font-medium text-xs">
                        Ja! BT Bautechnik Cockpit ist als Progressive Web App (PWA) konzipiert. Es läuft reaktionsschnell auf jedem iPhone, Android-Smartphone, iPad oder Laptop – ohne umständliche App-Store Installation.
                    </p>
                </div>

                <div class="arch-card p-4 sm:p-5">
                    <button type="button" @click="openFaq = (openFaq === 1 ? null : 1)" class="w-full flex justify-between items-center text-left font-black text-slate-950 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Wie funktioniert die Nachtragserstellung nach VOB/B § 2?</span>
                        <span class="text-amber-600 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 1 ? '−' : '+'">+</span>
                    </button>
                    <p x-show="openFaq === 1" x-cloak class="mt-3 text-slate-600 leading-relaxed pt-3 border-t border-slate-100 font-medium text-xs">
                        Das System unterscheidet automatisch zwischen Leistungsänderungen (§ 2 Abs. 5) und unvorhergesehenen Zusatzleistungen (§ 2 Abs. 6). Sie geben Titel und Menge ein – das System erstellt sofort das unterschriftsreife Nachtragsangebot als PDF mit rechtssicherer Klausulierung.
                    </p>
                </div>

                <div class="arch-card p-4 sm:p-5">
                    <button type="button" @click="openFaq = (openFaq === 2 ? null : 2)" class="w-full flex justify-between items-center text-left font-black text-slate-950 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Kann mein Steuerberater die Rechnungen und Kosten direkt importieren?</span>
                        <span class="text-amber-600 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 2 ? '−' : '+'">+</span>
                    </button>
                    <p x-show="openFaq === 2" x-cloak class="mt-3 text-slate-600 leading-relaxed pt-3 border-t border-slate-100 font-medium text-xs">
                        Ja. Das System verfügt über eine integrierte DATEV CSV-Schnittstelle nach SKR03 und SKR04 inklusive automatischem Buchungsschlüssel für Nachunternehmer-Rechnungen (§ 13b UStG Bauleistungen).
                    </p>
                </div>

                <div class="arch-card p-4 sm:p-5">
                    <button type="button" @click="openFaq = (openFaq === 3 ? null : 3)" class="w-full flex justify-between items-center text-left font-black text-slate-950 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Können wir das System unverbindlich testen?</span>
                        <span class="text-amber-600 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 3 ? '−' : '+'">+</span>
                    </button>
                    <p x-show="openFaq === 3" x-cloak class="mt-3 text-slate-600 leading-relaxed pt-3 border-t border-slate-100 font-medium text-xs">
                        Absolut. Klicken Sie einfach auf "Demo anfordern". Wir zeigen Ihnen in 15 Minuten per Videoschalte oder direkt vor Ort, wie Sie das System für Ihre Baustellen einrichten.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 12. BOTTOM CTA BANNER                                                     -->
    <!-- ========================================================================= -->
    <section class="py-14 sm:py-20 relative overflow-hidden bg-white border-t border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="arch-card-featured p-8 sm:p-14 space-y-6">
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-white tracking-tight leading-snug">
                    Bereit, Ihre Baustellen & Finanzen auf das nächste Level zu heben?
                </h2>
                <p class="text-xs sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed font-medium">
                    Schließen Sie sich zukunftsorientierten Bauunternehmen & Bauträgern an. Fordern Sie jetzt Ihre persönliche Live-Präsentation an.
                </p>
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-4 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl shadow-xl transition cursor-pointer btn-press">
                        Jetzt kostenlose Demo anfordern →
                    </button>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto px-6 py-4 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs sm:text-sm rounded-xl sm:rounded-2xl border border-slate-700 transition">
                        Bestehendes Kundenkonto Login ↗
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 13. FOOTER WITH LEGAL ENTITY                                              -->
    <!-- ========================================================================= -->
    <footer class="border-t border-slate-200 bg-white py-8 sm:py-12 text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4 sm:gap-6">
            
            <!-- Brand Identity -->
            <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 text-center sm:text-left">
                <x-brand-logo size="small" />
                <span class="text-slate-300 hidden sm:inline">•</span>
                <span class="text-slate-600 text-[11px] sm:text-xs font-medium">
                    BT Bautechnik UG (haftungsbeschränkt) | Brunnenstraße 4, 92334 Berching 🇩🇪
                </span>
            </div>

            <!-- Legal Links -->
            <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 font-bold text-slate-700 text-xs">
                <a href="/impressum" class="hover:text-amber-600 transition">Impressum</a>
                <a href="/datenschutz" class="hover:text-amber-600 transition">Datenschutz</a>
                <a href="/agb" class="hover:text-amber-600 transition">AGB</a>
                <a href="{{ route('login') }}" class="hover:text-amber-600 transition text-slate-900">Kunden-Login ↗</a>
            </div>
        </div>
    </footer>

    <!-- ========================================================================= -->
    <!-- 14. DEMO REQUEST MODAL                                                    -->
    <!-- ========================================================================= -->
    @if ($showDemoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/70 backdrop-blur-sm">
            <div class="bg-white border border-slate-200 rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-lg w-full max-h-[92vh] overflow-y-auto shadow-2xl space-y-4 sm:space-y-6 relative">
                
                <button wire:click="closeDemoModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-900 text-xl font-bold cursor-pointer">✕</button>

                @if ($demoSuccess)
                    <div class="py-6 sm:py-8 text-center space-y-3 sm:space-y-4">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl sm:text-3xl mx-auto font-bold">
                            ✓
                        </div>
                        <h3 class="text-lg sm:text-xl font-black text-slate-950">Vielen Dank für Ihre Anfrage!</h3>
                        <p class="text-xs text-slate-600 max-w-sm mx-auto leading-relaxed font-medium">
                            Wir haben Ihre Daten erhalten. Unsere Bauleitung der <strong>BT Bautechnik UG</strong> wird sich in Kürze für eine persönliche Live-Präsentation bei Ihnen melden.
                        </p>
                        <div class="pt-2">
                            <button wire:click="closeDemoModal" class="px-6 py-2.5 bg-slate-950 hover:bg-slate-800 text-white font-black text-xs rounded-xl cursor-pointer">
                                Fertig
                            </button>
                        </div>
                    </div>
                @else
                    <div class="space-y-1">
                        <div class="arch-section-label">
                            <span>UNVERBINDLICHE PRÄSENTATION</span>
                        </div>
                        <h3 class="text-lg sm:text-xl font-black text-slate-950">Live-Demo für Ihr Bauunternehmen</h3>
                        <p class="text-xs text-slate-500 font-medium">Erfahren Sie, wie BT Cockpit Ihren Baustellenalltag revolutioniert.</p>
                    </div>

                    <form wire:submit="submitDemoRequest" class="space-y-3 sm:space-y-3.5 text-xs">
                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Ihr Name / Ansprechpartner *</label>
                            <input wire:model="demoName" type="text" placeholder="z. B. Dipl.-Ing. Markus Huber" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Unternehmen / Firma *</label>
                            <input wire:model="demoCompany" type="text" placeholder="z. B. Huber Bau & Sanierung GmbH" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">E-Mail-Adresse *</label>
                                <input wire:model="demoEmail" type="email" placeholder="m.huber@huberbau.de" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-medium rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Telefon / Mobil *</label>
                                <input wire:model="demoPhone" type="tel" placeholder="0171 1234567" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-medium rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Ihr Schwerpunkt</label>
                                <select wire:model="demoTrade" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                                    <option value="bautraeger">Bauträger / Entwickler</option>
                                    <option value="generalunternehmer">Generalübernehmer / GU</option>
                                    <option value="sanierung_abdichtung">Sanierung & Abdichtung</option>
                                    <option value="hoch_tiefbau">Hoch- & Tiefbau</option>
                                    <option value="handwerk">Fachhandwerksbetrieb</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Baustellen pro Jahr</label>
                                <select wire:model="demoProjectCount" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                                    <option value="1-3">1 – 3 Bauvorhaben</option>
                                    <option value="4-10">4 – 10 Bauvorhaben</option>
                                    <option value="10+">Über 10 Bauvorhaben</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Nachricht / Notiz (optional)</label>
                            <textarea wire:model="demoMessage" rows="2" placeholder="Welche Module interessieren Sie besonders (z.B. VOB-Nachträge, Aufmaße, KI-Bautagebuch)?" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl p-2.5 focus:border-amber-500 focus:outline-none"></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <a href="https://wa.me/4916096275910?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="text-xs text-emerald-700 hover:underline flex items-center gap-1 font-bold">
                                <span>💬 Lieber per WhatsApp anfragen</span>
                            </a>

                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-slate-950 hover:bg-slate-800 text-white font-black text-xs rounded-xl shadow-md cursor-pointer btn-press">
                                Demo-Termin vereinbaren →
                            </button>
                        </div>
                    </form>
                @endif

            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 15. MOBILE STICKY BAR                                                     -->
    <!-- ========================================================================= -->
    <div x-show="showStickyBar" 
         x-transition:enter="transition ease-out duration-300 transform" 
         x-transition:enter-start="translate-y-20 opacity-0" 
         x-transition:enter-end="translate-y-0 opacity-100" 
         x-transition:leave="transition ease-in duration-200 transform" 
         x-transition:leave-start="translate-y-0 opacity-100" 
         x-transition:leave-end="translate-y-20 opacity-0" 
         x-cloak 
         class="fixed bottom-4 left-4 right-4 z-40 md:hidden">
        <div class="bg-slate-950/95 backdrop-blur-xl border border-slate-800 rounded-2xl p-2.5 shadow-2xl flex items-center justify-between gap-2.5">
            <button wire:click="openDemoModal" class="flex-1 py-3 px-4 bg-amber-500 active:scale-95 text-slate-950 font-black text-xs rounded-xl shadow-md flex items-center justify-center gap-1.5 transition">
                <span>⚡ Live-Demo anfordern</span>
            </button>
            <a href="https://wa.me/4916096275910?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20eine%20Live-Demo%20anfragen." target="_blank" class="py-3 px-3.5 bg-emerald-600 active:scale-95 text-white font-black text-xs rounded-xl shadow-md flex items-center justify-center gap-1 shrink-0 transition">
                <span>💬 WhatsApp</span>
            </a>
        </div>
    </div>

</div>
