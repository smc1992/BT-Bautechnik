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
     class="min-h-screen bg-slate-50 text-slate-900 font-sans selection:bg-blue-600 selection:text-white relative overflow-x-hidden">
    
    <!-- Modern Blueprint Geometric Background Pattern & Ambient Laser Glow -->
    <div class="absolute inset-0 blueprint-pattern pointer-events-none opacity-70"></div>
    <div class="fixed top-0 left-1/4 w-[650px] h-[550px] bg-blue-200/40 rounded-full blur-[150px] pointer-events-none -z-10 animate-glow"></div>
    <div class="fixed top-1/3 right-10 w-[550px] h-[550px] bg-amber-100/50 rounded-full blur-[160px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-10 left-10 w-[500px] h-[500px] bg-indigo-100/40 rounded-full blur-[160px] pointer-events-none -z-10"></div>

    <!-- ========================================================================= -->
    <!-- ========================================================================= -->
    <!-- 1. STICKY TOP NAVBAR (CLEAN WHITE / FROSTED GLASS & FULL MOBILE NAV)      -->
    <!-- ========================================================================= -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-xl border-b border-slate-200/90 shadow-xs transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-2">
            
            <!-- Real Brand Logo Component -->
            <a href="/" class="hover:opacity-95 transition group shrink-0">
                <x-brand-logo size="default" />
            </a>

            <!-- Nav Links (Desktop) -->
            <nav class="hidden lg:flex items-center gap-7 text-xs font-black text-slate-600">
                <a href="#story" class="hover:text-blue-700 transition flex items-center gap-1">
                    <span>🧱 Baupraxis & Story</span>
                </a>
                <a href="#module" class="hover:text-blue-700 transition">ERP-Module & VOB</a>
                <a href="#integrations" class="hover:text-blue-700 transition">Schnittstellen</a>
                <a href="#rechner" class="hover:text-emerald-700 transition flex items-center gap-1.5 font-black text-emerald-700">
                    <span>🧮 Ersparnisrechner</span>
                </a>
                <a href="#vorteile" class="hover:text-blue-700 transition">Vorher / Nachher</a>
                <a href="#faq" class="hover:text-slate-900 transition">FAQ</a>
            </nav>

            <!-- Action Buttons (Desktop & Tablet) -->
            <div class="hidden sm:flex items-center gap-2.5">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-xl shadow-xs transition flex items-center gap-2">
                        <span>📊 Zum Cockpit</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="px-3.5 py-2 text-slate-700 hover:text-slate-900 font-extrabold text-xs transition">
                        Login ↗
                    </a>
                @endauth

                <button wire:click="openDemoModal" class="px-4 sm:px-5 py-2.5 bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 hover:from-blue-600 hover:to-amber-500 text-white font-black text-xs rounded-xl shadow-md shadow-blue-500/20 transition cursor-pointer flex items-center gap-1.5 btn-press">
                    <span>✨ Live-Demo anfordern</span>
                </button>
            </div>

            <!-- Mobile Actions (Screen < 640px) -->
            <div class="flex sm:hidden items-center gap-1.5 shrink-0">
                <button type="button" wire:click="openDemoModal" class="px-2.5 py-2 bg-gradient-to-r from-blue-700 to-indigo-700 text-white font-black text-[11px] rounded-xl shadow-xs flex items-center gap-1 btn-press">
                    <span>✨ Demo</span>
                </button>

                <!-- Hamburger Toggle Button -->
                <button type="button" 
                        @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 focus:outline-none transition-colors cursor-pointer" 
                        aria-label="Menü öffnen">
                    <!-- Hamburger Icon when closed -->
                    <svg x-show="!mobileMenuOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <!-- Close Icon when open -->
                    <svg x-show="mobileMenuOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>

        <!-- Mobile Slide-Down Drawer Navigation (Alpine.js) -->
        <div x-show="mobileMenuOpen" 
             x-cloak 
             x-transition:enter="transition ease-out duration-250 transform" 
             x-transition:enter-start="opacity-0 -translate-y-2" 
             x-transition:enter-end="opacity-100 translate-y-0" 
             x-transition:leave="transition ease-in duration-150 transform" 
             x-transition:leave-start="opacity-100 translate-y-0" 
             x-transition:leave-end="opacity-0 -translate-y-2" 
             @click.away="mobileMenuOpen = false"
             class="lg:hidden bg-white border-b border-slate-200 shadow-2xl px-4 py-5 space-y-4">
            
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider px-3 block mb-1">Navigation</span>
                
                <nav class="flex flex-col space-y-1">
                    <a href="#story" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-black text-slate-800 hover:text-blue-700 hover:bg-blue-50 transition">
                        <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center text-sm shrink-0">🧱</span>
                        <span>Baupraxis & Story</span>
                    </a>
                    <a href="#module" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-black text-slate-800 hover:text-blue-700 hover:bg-blue-50 transition">
                        <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center text-sm shrink-0">🏗️</span>
                        <span>ERP-Module & Simulator</span>
                    </a>
                    <a href="#integrations" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-black text-slate-800 hover:text-blue-700 hover:bg-blue-50 transition">
                        <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-800 flex items-center justify-center text-sm shrink-0">🔌</span>
                        <span>Schnittstellen & DATEV</span>
                    </a>
                    <a href="#rechner" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-black text-slate-800 hover:text-emerald-700 hover:bg-emerald-50 transition">
                        <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-sm shrink-0">🧮</span>
                        <span>Ersparnisrechner</span>
                    </a>
                    <a href="#vorteile" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-black text-slate-800 hover:text-blue-700 hover:bg-blue-50 transition">
                        <span class="w-7 h-7 rounded-lg bg-cyan-100 text-cyan-800 flex items-center justify-center text-sm shrink-0">⚖️</span>
                        <span>Vorher / Nachher Vergleich</span>
                    </a>
                    <a href="#faq" @click="mobileMenuOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-black text-slate-800 hover:text-slate-900 hover:bg-slate-100 transition">
                        <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center text-sm shrink-0">💬</span>
                        <span>Häufige Fragen (FAQ)</span>
                    </a>
                </nav>
            </div>

            <!-- Mobile Drawer Actions & CTA -->
            <div class="pt-3 border-t border-slate-100 space-y-2">
                <button type="button" wire:click="openDemoModal" @click="mobileMenuOpen = false" class="w-full py-3 bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 text-white font-black text-xs rounded-xl shadow-md text-center flex items-center justify-center gap-2 btn-press">
                    <span>✨ Kostenlose Live-Demo anfordern</span>
                </button>
                
                <div class="grid grid-cols-2 gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="py-2.5 bg-slate-900 text-white font-black text-xs rounded-xl text-center flex items-center justify-center gap-1.5">
                            <span>📊 Zum Cockpit</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-black text-xs rounded-xl text-center flex items-center justify-center gap-1.5">
                            <span>🔑 Login ↗</span>
                        </a>
                    @endauth
                    
                    <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 font-black text-xs rounded-xl text-center flex items-center justify-center gap-1.5">
                        <span>💬 WhatsApp</span>
                    </a>
                </div>
            </div>

        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- ========================================================================= -->
    <!-- 2. HERO SECTION (MARTEX DEMO-2 SAAS STYLE MIT KPI STATS & COCKPIT)       -->
    <!-- ========================================================================= -->
    <section class="relative pt-8 pb-14 sm:pt-16 sm:pb-24 lg:pt-20 lg:pb-28 overflow-hidden">
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="text-center max-w-4xl mx-auto space-y-4 sm:space-y-6">
                
                <!-- Origin Badge with Micro-Animation Float -->
                <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1.5 rounded-full bg-white border border-amber-300 text-amber-900 text-[10.5px] sm:text-xs font-black shadow-xs animate-float text-left sm:text-center leading-snug">
                    <span class="flex h-2 w-2 rounded-full bg-amber-500 animate-ping shrink-0"></span>
                    <span>🏗️ Entwickelt von der BT Bautechnik UG – Aus der Baupraxis für Bauträger & Bauleiter</span>
                </div>

                <!-- Main Hero Headline -->
                <h1 class="text-2xl sm:text-4xl lg:text-6xl font-black tracking-tight text-slate-950 leading-tight sm:leading-[1.12]">
                    Die Bauleiter- & Bauträger-Software,<br>
                    <span class="bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 bg-clip-text text-transparent">
                        die direkt auf der Baustelle geboren wurde.
                    </span>
                </h1>

                <!-- Subtitle with Construction Authenticity -->
                <p class="text-xs sm:text-base lg:text-lg text-slate-600 font-medium max-w-3xl mx-auto leading-relaxed">
                    Wir sind selbst ein aktives Bauunternehmen in Bayern. Wir kennen den Zeitdruck, unübersichtliche Aufmaße und vergessene Nachträge nach VOB/B § 2. Das <strong>BT Bautechnik Cockpit</strong> vereint Baustellen-Steuerung, 360° Kunden-Zentrale, digitale VOB/C Aufmaße, KI-Bautagebücher und DATEV-Finanzen in einer blitzschnellen Lösung.
                </p>

                <!-- Hero CTAs with Glow and Hover Elevation -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-6 sm:px-8 py-3.5 sm:py-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 hover:from-blue-600 hover:to-amber-500 text-white font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl shadow-xl shadow-blue-600/20 hover:shadow-amber-500/25 transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer flex items-center justify-center gap-2 btn-press">
                        <span>🚀 Kostenlose Live-Demo vereinbaren</span>
                        <span class="inline-block transition-transform group-hover:translate-x-1">→</span>
                    </button>

                    <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="w-full sm:w-auto px-5 sm:px-6 py-3.5 sm:py-4 bg-white hover:bg-slate-50 text-slate-800 font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl border border-slate-300 shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <span>💬 Direkt per WhatsApp anfragen</span>
                    </a>
                </div>

                <!-- Lexend Eight Style 5-Star Builder Trust Badge -->
                <div class="pt-2 sm:pt-3 flex flex-wrap items-center justify-center gap-2 sm:gap-3 text-center">
                    <div class="flex items-center text-amber-400 text-xs">
                        <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
                    </div>
                    <span class="text-[11px] sm:text-xs text-slate-600 font-bold">
                        <strong class="text-slate-900">4.9 / 5.0</strong> von über 120 Bauleitern & Bauträgern geschätzt
                    </span>
                    <span class="hidden sm:inline-block text-slate-300">•</span>
                    <span class="inline-flex items-center gap-1 text-[11px] sm:text-xs text-emerald-700 font-bold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Keine Kreditkarte erforderlich
                    </span>
                </div>

                <!-- Martex Demo-2 KPI Stat Badges in Hero -->
                <div class="pt-4 sm:pt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 max-w-3xl mx-auto">
                    <div class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm card-lift text-center space-y-1">
                        <div class="text-2xl sm:text-4xl font-black text-blue-700 tracking-tight">85%</div>
                        <div class="text-xs font-black text-slate-900">Zeitersparnis Doku</div>
                        <div class="text-[10.5px] sm:text-[11px] text-slate-500 font-medium">Bautagebuch & Wetter vor Ort</div>
                    </div>
                    <div class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm card-lift text-center space-y-1">
                        <div class="text-2xl sm:text-4xl font-black text-amber-600 tracking-tight">+14.800 €</div>
                        <div class="text-xs font-black text-slate-900">Nachtragserlöse</div>
                        <div class="text-[10.5px] sm:text-[11px] text-slate-500 font-medium">Rechtssicher nach VOB/B § 2</div>
                    </div>
                    <div class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm card-lift text-center space-y-1">
                        <div class="text-2xl sm:text-4xl font-black text-emerald-600 tracking-tight">100%</div>
                        <div class="text-xs font-black text-slate-900">DATEV & DSGVO</div>
                        <div class="text-[10.5px] sm:text-[11px] text-slate-500 font-medium">SKR03/04 & § 13b UStG</div>
                    </div>
                </div>

            </div>

            <!-- Crisp Architectural Cockpit Preview Card (High Contrast with Real Construction Photography & Floating Live Badges) -->
            <div class="mt-10 sm:mt-16 max-w-6xl mx-auto rounded-2xl sm:rounded-3xl p-2.5 sm:p-4 bg-gradient-to-b from-blue-100/90 via-slate-200/80 to-amber-100/60 border border-slate-300 shadow-2xl reveal-on-scroll relative">
                
                <!-- Floating Live Badge 1: Top-Right (Whisper KI Voice Recognition) -->
                <div class="hidden md:flex items-center gap-3 px-4 py-2.5 rounded-2xl floating-badge absolute -top-5 -right-3 z-30 animate-float text-left">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 text-white flex items-center justify-center font-bold text-base shrink-0 shadow-md shadow-amber-500/20">
                        🎙️
                    </div>
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            <span class="text-[9.5px] font-black uppercase text-amber-700 tracking-wider">Whisper KI erkannt</span>
                        </div>
                        <p class="text-[11.5px] font-black text-slate-900 leading-tight">14:32 Tiefgarage: 3 Mängel & Wetter erfasst</p>
                    </div>
                </div>

                <!-- Floating Live Badge 2: Bottom-Left (VOB/B Nachtrag Freigabe) -->
                <div class="hidden md:flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-slate-950/95 backdrop-blur-xl text-white border border-slate-700/90 shadow-2xl absolute -bottom-5 -left-3 z-30 text-left">
                    <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-base shrink-0 shadow-md shadow-blue-500/30">
                        ⚖️
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-[9.5px] font-mono text-amber-400 font-bold uppercase tracking-wider">VOB/B § 2 Abs. 6 Freigabe</span>
                        <p class="text-[11.5px] font-black text-white leading-tight">+ 4.850,00 € Nachtrag rechtssicher als PDF</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl border border-slate-200 overflow-hidden shadow-inner relative z-10">
                    
                    <!-- Window bar -->
                    <div class="px-3.5 sm:px-5 py-2.5 sm:py-3.5 bg-slate-900 border-b border-slate-800 flex items-center justify-between text-white">
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <span class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-rose-500"></span>
                            <span class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-amber-500"></span>
                            <span class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-emerald-500"></span>
                            <span class="text-[11px] sm:text-xs text-slate-300 font-mono ml-1 sm:ml-2">
                                bautechnik-bt.de<span class="hidden sm:inline"> / bauleiter-cockpit / live</span>
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <span class="hidden xs:inline-block px-2 sm:px-2.5 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black bg-amber-500/30 text-amber-300 border border-amber-400/40">
                                BT BAUTECHNIK
                            </span>
                            <span class="px-2 sm:px-2.5 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black bg-emerald-500/30 text-emerald-300 border border-emerald-400/40 animate-pulse">
                                LIVE
                            </span>
                        </div>
                    </div>

                    <!-- Split Mockup & On-Site Action Photo -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 bg-slate-50">
                        
                        <!-- Left: Real Bauleiter On-Site Tablet Photography -->
                        <div class="lg:col-span-5 relative overflow-hidden border-b lg:border-b-0 lg:border-r border-slate-200 group">
                            <img src="{{ asset('images/bauleiter-tablet-hero.jpg') }}" 
                                 alt="Bauleiter vor Ort mit digitalem BT Bautechnik Tablet Cockpit" 
                                 class="w-full h-56 sm:h-72 lg:h-full object-cover min-h-[220px] lg:min-h-[440px] group-hover:scale-105 transition-transform duration-700">
                            
                            <!-- Floating Glass Badge over photo -->
                            <div class="absolute bottom-3 left-3 right-3 sm:bottom-4 sm:left-4 sm:right-4 bg-slate-950/85 backdrop-blur-md text-white p-3 sm:p-3.5 rounded-xl sm:rounded-2xl border border-white/20 shadow-lg">
                                <div class="flex items-center gap-2 mb-0.5 sm:mb-1">
                                    <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                                    <span class="text-[10px] sm:text-xs font-black text-amber-300 uppercase tracking-wider">Echte Baustelle vor Ort</span>
                                </div>
                                <p class="text-[11px] sm:text-xs text-slate-200 font-medium leading-relaxed">
                                    Bautagesberichte, digitale VOB/C Aufmaße und Mängelerfassung in Echtzeit auf dem Smartphone & Tablet.
                                </p>
                            </div>
                        </div>

                        <!-- Right: Interactive Cockpit KPIs & Status -->
                        <div class="lg:col-span-7 p-3.5 sm:p-7 space-y-3.5 sm:space-y-5 flex flex-col justify-between">
                            
                            <!-- Project Banner Header -->
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-3 bg-white p-3 sm:p-4 rounded-xl sm:rounded-2xl border border-slate-200 shadow-xs">
                                <div>
                                    <span class="text-[9px] sm:text-[10px] font-mono text-blue-700 font-black uppercase tracking-wider">BAUVORHABEN #2026-081</span>
                                    <h3 class="text-sm sm:text-base font-black text-slate-950">WEG Maximilianstraße 44 – Tiefgaragenabdichtung</h3>
                                    <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Auftraggeber / Bauherr: Hausverwaltung Müller & Partner GmbH</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-2.5 py-1 rounded-lg sm:rounded-xl bg-blue-50 text-blue-800 border border-blue-200 font-black text-[10px] sm:text-xs">
                                        KW 32 – 38
                                    </span>
                                    <span class="px-2.5 py-1 rounded-lg sm:rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 font-black text-[10px] sm:text-xs flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Im Plan
                                    </span>
                                </div>
                            </div>

                            <!-- Progress & Budget Cards (Layered) -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3">
                                <div class="layered-card p-3 sm:p-4 rounded-xl border border-slate-200 card-lift">
                                    <span class="text-[9px] sm:text-[10px] text-slate-500 font-bold uppercase">Geplantes Budget</span>
                                    <p class="text-sm sm:text-base font-black text-slate-900 mt-0.5 tabular-nums">85.000,00 €</p>
                                    <div class="w-full bg-slate-100 h-1.5 sm:h-2 rounded-full mt-2 overflow-hidden">
                                        <div class="bg-blue-600 h-full w-[65%]"></div>
                                    </div>
                                </div>
                                <div class="layered-card p-3 sm:p-4 rounded-xl border border-slate-200 card-lift">
                                    <span class="text-[9px] sm:text-[10px] text-slate-500 font-bold uppercase">Nachträge (VOB/B)</span>
                                    <p class="text-sm sm:text-base font-black text-amber-700 mt-0.5 tabular-nums">+ 12.450,00 €</p>
                                    <span class="text-[9.5px] sm:text-[10px] text-emerald-700 font-bold">3 freigegeben, 1 offen</span>
                                </div>
                                <div class="layered-card p-3 sm:p-4 rounded-xl border border-slate-200 card-lift">
                                    <span class="text-[9px] sm:text-[10px] text-slate-500 font-bold uppercase">Aufmaß (VOB/C)</span>
                                    <p class="text-sm sm:text-base font-black text-blue-700 mt-0.5 tabular-nums">620 m² / 750 m²</p>
                                    <span class="text-[9.5px] sm:text-[10px] text-slate-600 font-bold">82% fertiggestellt</span>
                                </div>
                            </div>

                            <!-- Mini Sub Action Bar -->
                            <div class="p-2.5 sm:p-3 bg-white rounded-xl border border-slate-200 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-1.5 text-slate-700 font-medium text-[11px] sm:text-xs">
                                    <span>🎙️ Bautagesbericht heute per Sprachmemo</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-slate-600">4 Monteure</span>
                                </div>
                                <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                                    <span class="px-2 py-0.5 sm:py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg font-bold text-[10px] sm:text-[11px]">
                                        📑 Nachtrags-PDF
                                    </span>
                                    <span class="px-2 py-0.5 sm:py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg font-bold text-[10px] sm:text-[11px]">
                                        📐 Aufmaß
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
    <!-- HIGH-TRUST COMPLIANCE & BAU-STANDARDS BAR (6 COLS)                        -->
    <!-- ========================================================================= -->
    <div class="border-y border-slate-200 bg-white py-6 sm:py-8 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 text-[11px] sm:text-xs text-slate-700 font-bold">
                <!-- VOB -->
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:border-blue-200 transition">
                    <span class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-sm font-black shrink-0">⚖️</span>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-900">VOB/B § 2 & VOB/C</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Rechtssicher</span>
                    </div>
                </div>
                <!-- DATEV -->
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:border-emerald-200 transition">
                    <span class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-black shrink-0">📊</span>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-900">DATEV SKR03/04</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Inkl. § 13b UStG</span>
                    </div>
                </div>
                <!-- DIN -->
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:border-indigo-200 transition">
                    <span class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-black shrink-0">📐</span>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-900">DIN 18299/18533</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Digitale Aufmaße</span>
                    </div>
                </div>
                <!-- GAEB / GoBD -->
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:border-cyan-200 transition">
                    <span class="w-8 h-8 rounded-lg bg-cyan-100 text-cyan-700 flex items-center justify-center text-sm font-black shrink-0">📑</span>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-900">GAEB & GoBD</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Revisionssicher</span>
                    </div>
                </div>
                <!-- DSGVO -->
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:border-amber-200 transition">
                    <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-black shrink-0">🇩🇪</span>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-900">100% DSGVO</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Server Frankfurt</span>
                    </div>
                </div>
                <!-- Offline PWA -->
                <div class="col-span-2 sm:col-span-1 flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-100 hover:border-violet-200 transition">
                    <span class="w-8 h-8 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-black shrink-0">📱</span>
                    <div class="leading-tight">
                        <span class="block font-black text-slate-900">PWA Offline-First</span>
                        <span class="text-[9.5px] text-slate-500 font-medium">Kein Funkloch-Stopp</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MARTEX DEMO-2 SIGNATURE 3-STEP PROCESS + INTERACTIVE VOICE MEMO SIMULATOR -->
    <!-- ========================================================================= -->
    <section class="py-14 sm:py-24 bg-slate-50 border-b border-slate-200 relative overflow-hidden reveal-on-scroll">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-2 sm:space-y-3 mb-10 sm:mb-16">
                <span class="px-3.5 py-1 rounded-full bg-blue-100 text-blue-800 text-[10.5px] sm:text-xs font-black uppercase border border-blue-200">
                    ⚡ Einfacher Workflow
                </span>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight">
                    In 3 Schritten zur vollständigen Baustellen-Kontrolle
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    Keine monatelange Einführung. Sofort startklar auf jedem Smartphone & Tablet.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 relative">
                
                <!-- Step 1 -->
                <div class="layered-card p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200 card-lift relative space-y-3.5 sm:space-y-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-blue-600 text-white font-black text-base sm:text-lg flex items-center justify-center shadow-md shadow-blue-500/30">
                        1
                    </div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900">
                        Kunde & Baustelle anlegen
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Erfassen Sie das Bauvorhaben mit Adresse, Bauherrn, KW-Bauzeitenplan und Budget in unter 30 Sekunden.
                    </p>
                    <div class="pt-1 sm:pt-2">
                        <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-[10px] font-bold border border-blue-200">
                            ⏱️ 30 Sekunden Aufwand
                        </span>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="layered-card p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200 card-lift relative space-y-3.5 sm:space-y-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-amber-500 text-white font-black text-base sm:text-lg flex items-center justify-center shadow-md shadow-amber-500/30">
                        2
                    </div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900">
                        Vor Ort per Sprachmemo erfassen
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Der Bauleiter spricht 30 Sekunden Tagesbericht ein. Die KI ergänzt automatisch Wetterdaten, Fotos und Gewerke.
                    </p>
                    <div class="pt-1 sm:pt-2">
                        <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 text-[10px] font-bold border border-amber-200">
                            🎙️ Whisper KI-Sprachmemo
                        </span>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="layered-card p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200 card-lift relative space-y-3.5 sm:space-y-4">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-emerald-600 text-white font-black text-base sm:text-lg flex items-center justify-center shadow-md shadow-emerald-500/30">
                        3
                    </div>
                    <h3 class="text-base sm:text-lg font-black text-slate-900">
                        1-Klick Nachtrag & DATEV Export
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        VOB/B Nachtragsangebote mit offiziellem Briefkopf als PDF versenden und Buchungsstapel direkt an den Steuerberater übergeben.
                    </p>
                    <div class="pt-1 sm:pt-2">
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 text-[10px] font-bold border border-emerald-200">
                            📊 DATEV SKR03/04 & VOB/B § 2
                        </span>
                    </div>
                </div>

            </div>

            <!-- ================================================================= -->
            <!-- 🎙️ INTERACTIVE LIVE KI-SPRACHMEMO SIMULATOR WIDGET (SHOW, DONT TELL) -->
            <!-- ================================================================= -->
            <div x-data="{
                isPlaying: false,
                seconds: 0,
                interval: null,
                activeSample: 'abdichtung',
                samples: {
                    abdichtung: {
                        tag: 'Tagesbericht & Mängelerfassung vor Ort',
                        title: 'Baustelle Maximilianstraße 44 – Tiefgarage',
                        audioText: '„Servus, heute 4 Mann vor Ort. Tiefgaragenabdichtung nach DIN 18533 planmäßig abgeschlossen. Im Kellerabgang 1 Riss an WU-Wand entdeckt – Mangel mit Foto angelegt. Wetter trocken, 19 Grad.“',
                        weather: '19°C • Sonnig & Trocken (GPS Auto-Wetter)',
                        workers: '4 Monteure (Firma BT Bautechnik)',
                        taskDone: 'Abdichtung DIN 18533 im Soll (620 m²)',
                        defectDetected: 'Mangel #14: Riss WU-Wand Kellerabgang',
                        outputPdf: 'Bautagesbericht #42 & Mängelanzeige als PDF generiert'
                    },
                    nachtrag: {
                        tag: 'VOB/B § 2 Mehrvergütung & Zusatzleistung',
                        title: 'Sanierung Wohnanlage Am Mühlbach 12',
                        audioText: '„Bauherr Müller hat heute vor Ort Zusatzdämmung an der Nordfassade beauftragt. Entspricht VOB/B § 2 Absatz 6. 120 Quadratmeter EPS 032 Dämmplatten zusätzlich erforderlich.“',
                        weather: '21°C • Leicht bewölkt',
                        workers: '3 Facharbeiter',
                        taskDone: 'Zusatzleistung Nordfassade aufgenommen',
                        defectDetected: 'Keine Baumängel erfasst',
                        outputPdf: 'VOB/B § 2 Abs. 6 Nachtragsangebot #104 (+ 4.850,00 €) sofort als PDF fertig'
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
            }" class="mt-12 sm:mt-16 bg-gradient-to-br from-slate-900 via-slate-950 to-blue-950 text-white rounded-2xl sm:rounded-3xl p-5 sm:p-8 border border-slate-800 shadow-2xl space-y-6">
                
                <!-- Simulator Header with Sample Switcher -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-slate-800">
                    <div class="space-y-1">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px] sm:text-xs font-black uppercase">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                            <span>Interaktiver Live-Simulator</span>
                        </div>
                        <h3 class="text-base sm:text-xl font-black text-white">
                            Testen Sie, wie die KI eine 10-Sekunden-Sprachnotiz in Sekunden strukturiert
                        </h3>
                    </div>

                    <!-- Scenario Selector -->
                    <div class="flex items-center gap-2 p-1 bg-slate-900/90 rounded-xl border border-slate-800 text-xs">
                        <button type="button" 
                                @click="activeSample = 'abdichtung'; stop(); seconds = 0;" 
                                :class="activeSample === 'abdichtung' ? 'bg-blue-600 text-white font-black' : 'text-slate-400 hover:text-white'" 
                                class="px-3 py-1.5 rounded-lg transition text-[11px] sm:text-xs">
                            Scenario 1: Bautagesbericht
                        </button>
                        <button type="button" 
                                @click="activeSample = 'nachtrag'; stop(); seconds = 0;" 
                                :class="activeSample === 'nachtrag' ? 'bg-blue-600 text-white font-black' : 'text-slate-400 hover:text-white'" 
                                class="px-3 py-1.5 rounded-lg transition text-[11px] sm:text-xs">
                            Scenario 2: VOB-Nachtrag
                        </button>
                    </div>
                </div>

                <!-- Interactive Player Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-center">
                    
                    <!-- Left: Tactile Audio Player Control (5 cols) -->
                    <div class="lg:col-span-5 bg-slate-900/80 p-5 rounded-2xl border border-slate-800/90 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10.5px] font-mono text-amber-400 uppercase font-black tracking-wider" x-text="samples[activeSample].tag"></span>
                            <span class="text-[11px] font-mono text-slate-400" x-text="isPlaying ? '0:0' + seconds + ' / 0:10' : (seconds >= 10 ? '0:10 / 0:10' : '0:00 / 0:10')"></span>
                        </div>

                        <!-- Audio Play Button & Visualizer -->
                        <div class="flex items-center gap-3">
                            <button type="button" 
                                    @click="isPlaying ? stop() : play()" 
                                    class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-600 text-slate-950 font-black text-xl flex items-center justify-center shadow-lg shadow-amber-500/30 hover:scale-105 active:scale-95 transition-all cursor-pointer">
                                <span x-show="!isPlaying">▶</span>
                                <span x-show="isPlaying" x-cloak>❚❚</span>
                            </button>

                            <!-- Audio Waveform Bars -->
                            <div class="flex-1 flex items-center gap-1.5 h-10 px-3 bg-slate-950/80 rounded-xl border border-slate-800">
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
                        <div class="p-3 bg-slate-950/90 rounded-xl border border-slate-800/80 text-xs">
                            <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Eingesprochene Audionachricht:</span>
                            <p class="text-amber-200/90 italic font-mono text-[11px] leading-relaxed" x-text="samples[activeSample].audioText"></p>
                        </div>
                    </div>

                    <!-- Right: Instant Real-time Extracted Data (7 cols) -->
                    <div class="lg:col-span-7 bg-slate-900/90 p-5 sm:p-6 rounded-2xl border border-blue-900/40 space-y-3.5 relative overflow-hidden">
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span class="text-xs font-black text-white">Automatisch strukturierter Bautagesbericht</span>
                            </div>
                            <span class="text-[10px] font-mono text-emerald-400 bg-emerald-950/60 px-2 py-0.5 rounded-lg border border-emerald-500/30">
                                100% VOB-konform
                            </span>
                        </div>

                        <!-- Structured Cards Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                            <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Wetterdaten (GPS)</span>
                                <p class="text-[11.5px] font-bold text-slate-200" x-text="samples[activeSample].weather"></p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Anwesende Fachkräfte</span>
                                <p class="text-[11.5px] font-bold text-slate-200" x-text="samples[activeSample].workers"></p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Ausgeführte Leistungen</span>
                                <p class="text-[11.5px] font-bold text-blue-300" x-text="samples[activeSample].taskDone"></p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 space-y-0.5">
                                <span class="text-[9.5px] font-bold text-slate-400 uppercase">Mängel- & Nachtragsstatus</span>
                                <p class="text-[11.5px] font-bold text-amber-300" x-text="samples[activeSample].defectDetected"></p>
                            </div>
                        </div>

                        <!-- PDF Ready Bar -->
                        <div class="p-3 bg-gradient-to-r from-blue-900/40 via-indigo-900/40 to-slate-900 rounded-xl border border-blue-500/30 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="text-base">📄</span>
                                <span class="text-slate-200 font-bold text-[11px]" x-text="samples[activeSample].outputPdf"></span>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-blue-600 text-white font-black text-[10px]">
                                Fertig
                            </span>
                        </div>

                    </div>

                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- ========================================================================= -->
    <!-- 3. DIE STORY: VON DER BRANCHE FÜR DIE BRANCHE                              -->
    <!-- ========================================================================= -->
    <section id="story" class="py-14 sm:py-24 bg-gradient-to-b from-white via-slate-50/50 to-white border-y border-slate-200/90 relative overflow-hidden">
        
        <!-- Background Ambient Glow -->
        <div class="absolute top-1/2 -left-40 w-96 h-96 bg-blue-100/40 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/3 -right-40 w-96 h-96 bg-amber-100/40 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Section Header -->
            <div class="max-w-3xl mb-10 sm:mb-14 space-y-3 reveal-on-scroll">
                <div class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-1.5 rounded-full bg-amber-50 border border-amber-300 text-amber-900 text-[10.5px] sm:text-xs font-black uppercase shadow-2xs">
                    <span class="text-sm">🧱</span>
                    <span>Aus der Praxis – Für Bauträger, GU & Bauleiter</span>
                </div>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight leading-tight">
                    Wir bauen selbst.<br>
                    <span class="bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 bg-clip-text text-transparent">
                        Wir kennen jeden Engpass auf der Baustelle.
                    </span>
                </h2>
                <p class="text-xs sm:text-base text-slate-600 font-medium leading-relaxed">
                    Hinter dieser Lösung steht kein reines Softwarehaus, sondern die <strong>BT Bautechnik UG (haftungsbeschränkt)</strong> mit Sitz in Berching (Bayern). Jede Funktion löst ein reales Problem, das wir selbst auf unseren Baustellen erlebt haben:
                </p>
            </div>

            <!-- Bento Grid Showcase: 3 Interactive Problem/Solution Cards (Left) & Photo + 4 Core Cards (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">
                
                <!-- Left Column: 3 Rich Problem -> Solution Cards (6 cols) -->
                <div class="lg:col-span-6 space-y-4 sm:space-y-5 reveal-on-scroll">
                    
                    <!-- Card 1: Nachträge -->
                    <div class="lexend-bento-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl space-y-3.5 sm:space-y-4 group">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-0.5 sm:py-1 rounded-full text-[9.5px] sm:text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                ⚠️ Das alte Problem
                            </span>
                            <span class="text-[11px] sm:text-xs font-black text-amber-600 flex items-center gap-1">
                                <span>VOB/B § 2 Abs. 5 & 6</span>
                            </span>
                        </div>
                        <h3 class="font-black text-slate-900 text-base sm:text-lg group-hover:text-amber-600 transition-colors">
                            Nachträge wurden vergessen oder mündlich verhandelt
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Weil Poliere und Bauleiter vor Ort keine Zeit hatten, am PC Angebote zu tippen, blieben berechtigte Mehrleistungen unvergütet.
                        </p>
                        <div class="pt-2 sm:pt-3 border-t border-slate-100 flex items-center gap-2.5 text-xs font-bold text-emerald-900 bg-emerald-50/80 p-3 sm:p-3.5 rounded-xl sm:rounded-2xl border border-emerald-200">
                            <span class="text-base sm:text-lg">✨</span>
                            <span><strong>BT Lösung:</strong> Nachtragsangebot nach § 2 VOB/B mit 2 Klicks vor Ort als PDF erzeugen.</span>
                        </div>
                        <div class="pt-1 flex justify-end">
                            <button wire:click="openDemoModal('bautraeger')" class="lexend-arrow-link text-amber-700 hover:text-amber-600 cursor-pointer">
                                <span>Nachtrags-Automatik testen</span>
                                <span class="arrow-icon">→</span>
                            </button>
                        </div>
                    </div>

                    <!-- Card 2: Bautagebuch -->
                    <div class="lexend-bento-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl space-y-3.5 sm:space-y-4 group">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-0.5 sm:py-1 rounded-full text-[9.5px] sm:text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                ⚠️ Das alte Problem
                            </span>
                            <span class="text-[11px] sm:text-xs font-black text-blue-600 flex items-center gap-1">
                                <span>KI-Sprachmemo (Whisper)</span>
                            </span>
                        </div>
                        <h3 class="font-black text-slate-900 text-base sm:text-lg group-hover:text-blue-700 transition-colors">
                            Mühsame Bautagebücher nach 10 Stunden Arbeit
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Niemand tippt abends gern Berichte. Die Folge: Lückenhafte Dokumentation und Beweisnot bei späteren Gewährleistungsstreitigkeiten.
                        </p>
                        <div class="pt-2 sm:pt-3 border-t border-slate-100 flex items-center gap-2.5 text-xs font-bold text-blue-900 bg-blue-50/80 p-3 sm:p-3.5 rounded-xl sm:rounded-2xl border border-blue-200">
                            <span class="text-base sm:text-lg">🎙️</span>
                            <span><strong>BT Lösung:</strong> 30-Sekunden Sprachmemo einsprechen – KI formuliert fertigen Tagesbericht samt Wetter & Fotos.</span>
                        </div>
                        <div class="pt-1 flex justify-end">
                            <button wire:click="openDemoModal('bautraeger')" class="lexend-arrow-link text-blue-700 hover:text-blue-600 cursor-pointer">
                                <span>Sprach-Bautagebuch ansehen</span>
                                <span class="arrow-icon">→</span>
                            </button>
                        </div>
                    </div>

                    <!-- Card 3: Steuerberater & Abrechnung -->
                    <div class="lexend-bento-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl space-y-3.5 sm:space-y-4 group">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-0.5 sm:py-1 rounded-full text-[9.5px] sm:text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                ⚠️ Das alte Problem
                            </span>
                            <span class="text-[11px] sm:text-xs font-black text-emerald-600 flex items-center gap-1">
                                <span>DATEV SKR03 / SKR04</span>
                            </span>
                        </div>
                        <h3 class="font-black text-slate-900 text-base sm:text-lg group-hover:text-emerald-700 transition-colors">
                            Abrechnungs-Chaos & manuelle Buchhaltungs-Übergabe
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Belege per Post, fehlende Zuordnung nach § 13b UStG für Nachunternehmer und Verzögerungen beim Monatsabschluss.
                        </p>
                        <div class="pt-2 sm:pt-3 border-t border-slate-100 flex items-center gap-2.5 text-xs font-bold text-emerald-900 bg-emerald-50/80 p-3 sm:p-3.5 rounded-xl sm:rounded-2xl border border-emerald-200">
                            <span class="text-base sm:text-lg">📊</span>
                            <span><strong>BT Lösung:</strong> Fertiger DATEV-Export auf Knopfdruck für Ihren Steuerberater ohne Doppeleingaben.</span>
                        </div>
                        <div class="pt-1 flex justify-end">
                            <button wire:click="openDemoModal('generalunternehmer')" class="lexend-arrow-link text-emerald-700 hover:text-emerald-600 cursor-pointer">
                                <span>DATEV-Export ansehen</span>
                                <span class="arrow-icon">→</span>
                            </button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button wire:click="openDemoModal" class="w-full py-3.5 sm:py-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 hover:from-blue-600 hover:to-amber-500 text-white font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl shadow-lg shadow-blue-600/20 transition cursor-pointer flex items-center justify-center gap-2 btn-press">
                            <span>Lernen Sie die BT Bauleiter-Suite unverbindlich kennen</span>
                            <span>→</span>
                        </button>
                    </div>

                </div>

                <!-- Right Column: Planning Office Photo + 4 High-Impact Value Cards (6 cols) -->
                <div class="lg:col-span-6 space-y-5 sm:space-y-6 reveal-on-scroll reveal-delay-200">
                    
                    <!-- Bauträger Planning Office Image with Floating Badges -->
                    <div class="relative rounded-2xl sm:rounded-3xl overflow-hidden border border-slate-200 shadow-xl group">
                        <img src="{{ asset('images/bautraeger-office-cockpit.jpg') }}" 
                             alt="BT Bautechnik Bauträger Planungsbüro & Projektmanagement" 
                             class="w-full h-56 sm:h-80 object-cover group-hover:scale-105 transition-transform duration-700">
                        
                        <!-- Overlay Gradient & Glass Badges -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/30 to-transparent flex flex-col justify-between p-4 sm:p-6">
                            <div class="flex justify-end">
                                <span class="px-2.5 sm:px-3 py-1 rounded-full text-[9.5px] sm:text-[10px] font-black uppercase bg-white/90 backdrop-blur-md text-slate-900 shadow-md border border-white">
                                    📍 Berching, Bayern
                                </span>
                            </div>
                            <div class="space-y-1 sm:space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                                    <span class="text-[10px] sm:text-xs font-black text-amber-300 uppercase tracking-wider">
                                        Praxiseinsatz vor Ort
                                    </span>
                                </div>
                                <h4 class="text-sm sm:text-lg font-black text-white leading-snug">
                                    Planungsbüro & Baustellen-Zentrale BT Bautechnik UG
                                </h4>
                                <p class="text-[11px] sm:text-xs text-slate-200 font-medium">
                                    Wir testen und optimieren jedes Release täglich auf unseren eigenen Bauvorhaben.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 4 Generously Padded, High-Contrast Lexend Bento Feature Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                        
                        <!-- Card 1 -->
                        <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group cursor-default">
                            <div class="flex items-center justify-between">
                                <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-amber-100 text-amber-800 text-xl sm:text-2xl font-bold flex items-center justify-center">
                                    🧱
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-amber-50 text-amber-800 border border-amber-200">
                                    100% Praxis
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-sm sm:text-base group-hover:text-amber-700 transition-colors">
                                    Echtes Bauunternehmen
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Keine theoretische Spielerei: Entwickelt von aktiven Bauleitern für den harten Baustellenalltag in Bayern.
                                </p>
                            </div>
                            <div class="pt-2 border-t border-slate-100">
                                <span class="lexend-arrow-link text-amber-800 text-xs">
                                    <span>Praxis-Erfahrung</span>
                                    <span class="arrow-icon">→</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group cursor-default">
                            <div class="flex items-center justify-between">
                                <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-blue-100 text-blue-800 text-xl sm:text-2xl font-bold flex items-center justify-center">
                                    📑
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-blue-50 text-blue-800 border border-blue-200">
                                    Rechtssicher
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-sm sm:text-base group-hover:text-blue-700 transition-colors">
                                    VOB/B § 2 Automatik
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Mehrvergütung sofort mit offiziellem Briefkopf, Begründung und rechtssicherem PDF-Angebot versenden.
                                </p>
                            </div>
                            <div class="pt-2 border-t border-slate-100">
                                <span class="lexend-arrow-link text-blue-800 text-xs">
                                    <span>VOB-Konform</span>
                                    <span class="arrow-icon">→</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group cursor-default">
                            <div class="flex items-center justify-between">
                                <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-indigo-100 text-indigo-800 text-xl sm:text-2xl font-bold flex items-center justify-center">
                                    👥
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-indigo-50 text-indigo-800 border border-indigo-200">
                                    Alles vernetzt
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-sm sm:text-base group-hover:text-indigo-700 transition-colors">
                                    360° Kunden-Zentrale
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Der Bauherr im Mittelpunkt: Baustellen, Aufmaße, Rechnungen und Telefonnotizen mit einem Klick steuern.
                                </p>
                            </div>
                            <div class="pt-2 border-t border-slate-100">
                                <span class="lexend-arrow-link text-indigo-800 text-xs">
                                    <span>360° Übersicht</span>
                                    <span class="arrow-icon">→</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card 4 -->
                        <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group cursor-default">
                            <div class="flex items-center justify-between">
                                <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-emerald-100 text-emerald-800 text-xl sm:text-2xl font-bold flex items-center justify-center">
                                    📊
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    DATEV SKR03/04
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-sm sm:text-base group-hover:text-emerald-700 transition-colors">
                                    Steuerberater Export
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Inklusive § 13b UStG Steuerschlüsseln für Subunternehmer. Kein mühsames Nachbuchen am Monatsende.
                                </p>
                            </div>
                            <div class="pt-2 border-t border-slate-100">
                                <span class="lexend-arrow-link text-emerald-800 text-xs">
                                    <span>DATEV-Ready</span>
                                    <span class="arrow-icon">→</span>
                                </span>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 4. INTERAKTIVER MODULE EXPLORER (LIGHT THEME TABS & PREVIEWS)             -->
    <!-- ========================================================================= -->
    <section id="module" class="py-14 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 reveal-on-scroll">
        
        <div class="text-center max-w-3xl mx-auto space-y-2 sm:space-y-3 mb-8 sm:mb-10">
            <span class="px-3.5 py-1 rounded-full bg-blue-100 border border-blue-200 text-blue-800 text-[10.5px] sm:text-xs font-black uppercase">
                🚀 Die All-in-One ERP Suite
            </span>
            <h2 class="text-2xl sm:text-4xl font-black text-slate-950 tracking-tight">
                Entdecken Sie alle Module im interaktiven Simulator
            </h2>
            <p class="text-xs sm:text-sm text-slate-600 font-medium">
                Wählen Sie ein Modul, um Funktionen und Workflows zu testen:
            </p>
        </div>

        <!-- Module Selector with Alpine.js (Instant 0ms tab switching without server requests) -->
        <div x-data="{ activeModuleTab: 'cockpit' }">
            
            <!-- Module Selector Tabs with Smooth Horizontal Scroll on Mobile -->
            <div class="flex items-center gap-1.5 sm:gap-2 mb-6 sm:mb-8 overflow-x-auto no-scrollbar pb-2 sm:flex-wrap sm:justify-center px-1">
                <button type="button" @click="activeModuleTab = 'cockpit'" 
                        :class="activeModuleTab === 'cockpit' ? 'bg-blue-700 text-white shadow-md shadow-blue-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200'"
                        class="whitespace-nowrap shrink-0 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-1.5 sm:gap-2">
                    <span>🏗️ Baustellen-Cockpit</span>
                </button>
                <button type="button" @click="activeModuleTab = 'contacts360'" 
                        :class="activeModuleTab === 'contacts360' ? 'bg-indigo-700 text-white shadow-md shadow-indigo-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200'"
                        class="whitespace-nowrap shrink-0 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-1.5 sm:gap-2">
                    <span>👥 360° Kunden-Zentrale</span>
                </button>
                <button type="button" @click="activeModuleTab = 'supplements'" 
                        :class="activeModuleTab === 'supplements' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200'"
                        class="whitespace-nowrap shrink-0 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-1.5 sm:gap-2">
                    <span>📑 VOB/B Nachträge</span>
                </button>
                <button type="button" @click="activeModuleTab = 'measurements'" 
                        :class="activeModuleTab === 'measurements' ? 'bg-cyan-700 text-white shadow-md shadow-cyan-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200'"
                        class="whitespace-nowrap shrink-0 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-1.5 sm:gap-2">
                    <span>📐 VOB/C Aufmaßblatt</span>
                </button>
                <button type="button" @click="activeModuleTab = 'dailylogs'" 
                        :class="activeModuleTab === 'dailylogs' ? 'bg-emerald-700 text-white shadow-md shadow-emerald-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200'"
                        class="whitespace-nowrap shrink-0 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-1.5 sm:gap-2">
                    <span>🎙️ KI-Bautagebuch</span>
                </button>
                <button type="button" @click="activeModuleTab = 'datev'" 
                        :class="activeModuleTab === 'datev' ? 'bg-purple-700 text-white shadow-md shadow-purple-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200'"
                        class="whitespace-nowrap shrink-0 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-1.5 sm:gap-2">
                    <span>📊 DATEV & Finanzen</span>
                </button>
            </div>

            <!-- Interactive Module Showcase Screen (Light High-Contrast) -->
            <div class="bg-white border border-slate-200 rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-xl min-h-[360px]">
                
                <!-- Tab 1: Cockpit -->
                <div x-show="activeModuleTab === 'cockpit'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center">
                    <div class="lg:col-span-5 space-y-3 sm:space-y-4">
                        <span class="text-[10px] sm:text-xs font-black uppercase text-blue-700 tracking-wider">Kernmodul 01</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-950">Baustellen-Cockpit & Soll/Ist-Steuerung</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Behalten Sie jedes Bauvorhaben im Griff: Budgetüberwachung, Bauzeitenplan nach Kalenderwochen, automatische Wetteraufzeichnung und lückenloses Fotoprotokoll.
                        </p>
                        <div class="space-y-1.5 sm:space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ Echtzeit-Budgetverbrauch mit Soll/Ist-Kosten</p>
                            <p class="flex items-center gap-2">✅ Kalenderwochen-Terminplan (Start-KW bis End-KW)</p>
                            <p class="flex items-center gap-2">✅ Wetter-API mit automatischer Temperatur & Niederschlag</p>
                        </div>
                        <button type="button" wire:click="openDemoModal('bautraeger')" class="px-4 py-2.5 bg-blue-700 hover:bg-blue-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Cockpit live testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3 sm:space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-1 sm:gap-2 pb-2.5 sm:pb-3 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">🏢 Projekt: Neubau Wohnanlage Regensburg</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">KW 28 – KW 42</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-center text-xs">
                            <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-xs">
                                <span class="text-[9px] text-slate-500 block font-bold">BUDGET SOLL</span>
                                <span class="font-black text-slate-900 text-sm">120.000 €</span>
                            </div>
                            <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-xs">
                                <span class="text-[9px] text-slate-500 block font-bold">IST-KOSTEN</span>
                                <span class="font-black text-blue-700 text-sm">74.500 €</span>
                            </div>
                            <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-xs">
                                <span class="text-[9px] text-slate-500 block font-bold">GEWINNMARGE</span>
                                <span class="font-black text-emerald-700 text-sm">+ 37,9%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Contacts 360 -->
                <div x-show="activeModuleTab === 'contacts360'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center">
                    <div class="lg:col-span-5 space-y-3 sm:space-y-4">
                        <span class="text-[10px] sm:text-xs font-black uppercase text-indigo-700 tracking-wider">Kernmodul 02</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-950">360° Kunden- & Bauherren-Zentrale</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Da der Kunde der Eigentümer der Baustellen ist, steuern Sie alles direkt aus dem Kunden heraus: Neue Baustellen anlegen, VOB-Nachträge erfassen, Aufmaße abrufen und Mängel überwachen.
                        </p>
                        <div class="space-y-1.5 sm:space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ 1-Klick-Aktionen pro Kunde (Baustelle, Nachtrag, Aufmaß, Rechnung)</p>
                            <p class="flex items-center gap-2">✅ Zeitgestempeltes Telefon- & Notizjournal</p>
                            <p class="flex items-center gap-2">✅ KI-Chefbauleiter Dossier für jedes Kundenprofil</p>
                        </div>
                        <button type="button" wire:click="openDemoModal('generalunternehmer')" class="px-4 py-2.5 bg-indigo-700 hover:bg-indigo-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Kunden-Zentrale testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="font-black text-xs text-slate-900">👤 Kunde: Hausverwaltung Schmidt & Co.</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-indigo-100 text-indigo-800">4 Baustellen</span>
                        </div>
                        <div class="p-3 bg-white rounded-xl border border-slate-200 flex flex-wrap gap-1.5 sm:gap-2 text-[10.5px] sm:text-xs">
                            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg font-bold">🏗️ + Baustelle</span>
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg font-bold">📑 + Nachtrag</span>
                            <span class="px-2.5 py-1 bg-cyan-50 text-cyan-700 border border-cyan-200 rounded-lg font-bold">📐 + Aufmaß</span>
                            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg font-bold">📄 + Rechnung</span>
                            <span class="px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg font-bold">🤖 KI-Dossier</span>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Supplements -->
                <div x-show="activeModuleTab === 'supplements'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center">
                    <div class="lg:col-span-5 space-y-3 sm:space-y-4">
                        <span class="text-[10px] sm:text-xs font-black uppercase text-amber-700 tracking-wider">Kernmodul 03</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-950">VOB/B Nachtragsmanagement (§ 2)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Schluss mit vergessenen oder abgewiesenen Nachträgen. Erfassen Sie Mehrleistungen nach § 2 Abs. 5 oder § 2 Abs. 6 sofort mit rechtssicherem PDF-Export.
                        </p>
                        <div class="space-y-1.5 sm:space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ Automatische VOB-Begründung & Fristüberwachung</p>
                            <p class="flex items-center gap-2">✅ PDF-Nachtragsangebot mit rechtssicherem VOB-Briefkopf</p>
                            <p class="flex items-center gap-2">✅ Status: Eingereicht, Geprüft, Beauftragt, Abgerechnet</p>
                        </div>
                        <button type="button" wire:click="openDemoModal('sanierung_abdichtung')" class="px-4 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Nachtragsmodul ansehen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-1 pb-2 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">📑 Nachtragsangebot NT-03 nach VOB/B § 2 Abs. 5</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800">BEAUFTRAGT</span>
                        </div>
                        <p class="text-xs text-slate-700 font-medium leading-relaxed">
                            Titel: Zusätzliche Hohlkehlenabdichtung & Bitumen-Dickbeschichtung Rampe UG 2
                        </p>
                        <div class="flex justify-between items-center p-3 bg-white rounded-xl border border-slate-200 text-xs">
                            <span class="text-slate-500 font-medium">Nachtragssumme Netto:</span>
                            <span class="text-sm sm:text-base font-black text-amber-700">4.850,00 €</span>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Measurements -->
                <div x-show="activeModuleTab === 'measurements'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center">
                    <div class="lg:col-span-5 space-y-3 sm:space-y-4">
                        <span class="text-[10px] sm:text-xs font-black uppercase text-cyan-700 tracking-wider">Kernmodul 04</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-950">Digitales Aufmaßblatt (VOB/C / DIN 18299)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Erfassen Sie Mengen direkt vor Ort mit Raummaßen (Länge × Breite × Höhe / Faktor), automatischem VOB-Abzug für Öffnungen und sofortigem PDF-Prüfprotokoll für den Bauherrn.
                        </p>
                        <div class="space-y-1.5 sm:space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ Flexible Formeln (z. B. 12.50 * 4.20 * 2)</p>
                            <p class="flex items-center gap-2">✅ Automatischer VOB-Abzug nach DIN 18299 / DIN 18336</p>
                            <p class="flex items-center gap-2">✅ 1-Klick Übergabe in die Schlussrechnung</p>
                        </div>
                        <button type="button" wire:click="openDemoModal('hoch_tiefbau')" class="px-4 py-2.5 bg-cyan-700 hover:bg-cyan-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Aufmaß-Engine testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">📐 Aufmaßblatt AM-2026-004 (Bodenplatte TG)</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-cyan-100 text-cyan-800">DIN 18299</span>
                        </div>
                        <div class="space-y-2 text-[11px] sm:text-xs font-mono">
                            <div class="p-2.5 bg-white rounded-lg flex justify-between border border-slate-200">
                                <span>Fläche Achse 1-4: 18.50 × 8.20</span>
                                <span class="font-bold text-blue-700">= 151,70 m²</span>
                            </div>
                            <div class="p-2.5 bg-white rounded-lg flex justify-between text-rose-700 border border-slate-200">
                                <span>Abzug Stütze (VOB): - (0.80 × 0.80 × 6)</span>
                                <span class="font-bold">- 3,84 m²</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 5: Daily Logs -->
                <div x-show="activeModuleTab === 'dailylogs'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center">
                    <div class="lg:col-span-5 space-y-3 sm:space-y-4">
                        <span class="text-[10px] sm:text-xs font-black uppercase text-emerald-700 tracking-wider">Kernmodul 05</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-950">KI-Bautagebuch & Sprachmemo (Whisper)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Kein Bauleiter tippt gern Berichte auf der Baustelle. Nehmen Sie einfach 30 Sekunden Sprachmemo auf – die KI formuliert einen druckreifen, rechtssicheren Bautagesbericht mit Wetter, Anwesenden und Gewerken.
                        </p>
                        <div class="space-y-1.5 sm:space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ KI-Sprachaufnahme & automatische Text-Strukturierung</p>
                            <p class="flex items-center gap-2">✅ Integrierter Fotoupload mit Beschriftung</p>
                            <p class="flex items-center gap-2">✅ Digitaler Freigabe-Link & PDF-Versand an Bauherrn</p>
                        </div>
                        <button type="button" wire:click="openDemoModal('bautraeger')" class="px-4 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Sprach-Bautagebuch testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center gap-2.5 p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900">
                            <span class="text-lg">🎙️</span>
                            <span class="font-medium font-sans">"Heute 4 Mann vor Ort, Abdichtung TG fertiggestellt, 2 Paletten Bitumen verbraucht, Wetter trocken 21 Grad."</span>
                        </div>
                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 space-y-1.5 text-xs text-slate-700">
                            <span class="text-[10px] text-emerald-700 font-black uppercase">✨ KI-Generierter Bericht:</span>
                            <p class="leading-relaxed text-[11px] sm:text-xs">
                                <strong>Ausgeführte Leistungen:</strong> Fertigstellung der Flächenabdichtung Tiefgaragenebene 1 gem. DIN 18533. <strong>Personal:</strong> 4 Fachkräfte vor Ort. <strong>Witterung:</strong> 21°C, heiter, optimale Verarbeitungsbedingungen.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tab 6: DATEV -->
                <div x-show="activeModuleTab === 'datev'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center">
                    <div class="lg:col-span-5 space-y-3 sm:space-y-4">
                        <span class="text-[10px] sm:text-xs font-black uppercase text-purple-700 tracking-wider">Kernmodul 06</span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-950">DATEV-Export & Subunternehmer-Controlling</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Übertragen Sie alle Ausgangs- und Eingangsrechnungen, Nachunternehmer-Rechnungen (§ 13b UStG) und Projektkosten im standardisierten DATEV-Format direkt an Ihren Steuerberater.
                        </p>
                        <div class="space-y-1.5 sm:space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ DATEV Buchungsstapel CSV (SKR03 & SKR04)</p>
                            <p class="flex items-center gap-2">✅ Automatische § 13b UStG Steuerschlüssel-Zuordnung</p>
                            <p class="flex items-center gap-2">✅ Rechnungsfreigabe-Workflow & Zahlungsüberwachung</p>
                        </div>
                        <button type="button" wire:click="openDemoModal('generalunternehmer')" class="px-4 py-2.5 bg-purple-700 hover:bg-purple-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            DATEV-Workflow testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">📊 DATEV SKR03 Buchungsstapel Export</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-purple-100 text-purple-800">STEUERBERATER READY</span>
                        </div>
                        <div class="p-3 bg-white rounded-xl border border-slate-200 font-mono text-[10px] sm:text-[11px] text-slate-700 space-y-1 overflow-x-auto">
                            <p class="whitespace-nowrap">Umsatz;S/H;Konto;Gegenkonto;BU;Beleg1;Datum;Text</p>
                            <p class="text-emerald-700 font-bold whitespace-nowrap">14850.00;S;8400;10000;;RE-2026-041;1408;AR WEG Maxstr</p>
                            <p class="text-amber-700 font-bold whitespace-nowrap">4200.00;H;3100;70000;19;ER-88412;1408;Subunt. Abdichtung</p>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </section>

    <!-- ========================================================================= -->
    <!-- 5. LEXEND EIGHT STYLE INTEGRATIONS & BAU-ÖKOSYSTEM CLOUD                  -->
    <!-- ========================================================================= -->
    <section id="integrations" class="py-14 sm:py-24 bg-white border-t border-slate-200 relative overflow-hidden reveal-on-scroll">
        
        <!-- Ambient background aura -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[350px] bg-gradient-to-r from-blue-100/40 via-amber-100/30 to-emerald-100/40 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="text-center max-w-3xl mx-auto space-y-2 sm:space-y-3 mb-10 sm:mb-16">
                <span class="inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-slate-800 text-[10.5px] sm:text-xs font-black uppercase shadow-2xs">
                    <span>🔌</span>
                    <span>Nahtlose Schnittstellen & Standards</span>
                </span>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight">
                    Maximale Konnektivität für Ihren <span class="bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 bg-clip-text text-transparent">Baualltag</span>
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium max-w-2xl mx-auto">
                    Verbinden Sie das BT Bautechnik Cockpit nahtlos mit Ihren bestehenden Buchhaltungs-, Kommunikations- und Baustellensystemen – ohne Medienbrüche.
                </p>
            </div>

            <!-- Integrations Bento Cards Grid (Lexend Style) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                
                <!-- Item 1: DATEV -->
                <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group">
                    <div class="flex items-center justify-between">
                        <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-emerald-100 text-emerald-800 text-xl font-black flex items-center justify-center">
                            📊
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-emerald-50 text-emerald-800 border border-emerald-200">
                            Nativ
                        </span>
                    </div>
                    <div>
                        <h4 class="font-black text-slate-900 text-sm sm:text-base group-hover:text-emerald-700 transition-colors">
                            DATEV SKR03 & SKR04
                        </h4>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                            Standardisierter Buchungsstapel-Export inkl. § 13b UStG direkt an Ihren Steuerberater.
                        </p>
                    </div>
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-500">
                        <span>CSV / ASCII Format</span>
                        <span class="lexend-arrow-link text-emerald-700">
                            <span class="arrow-icon">→</span>
                        </span>
                    </div>
                </div>

                <!-- Item 2: VOB/B & VOB/C -->
                <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group">
                    <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-blue-100 text-blue-800 text-xl font-black flex items-center justify-center">
                        ⚖️
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-blue-50 text-blue-800 border border-blue-200 w-fit">
                        Rechtskonform
                    </span>
                    <div>
                        <h4 class="font-black text-slate-900 text-sm sm:text-base group-hover:text-blue-700 transition-colors">
                            VOB/B § 2 & VOB/C
                        </h4>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                            Rechtssichere Nachtragsbegründung & DIN 18299 / DIN 18336 konforme Aufmaße.
                        </p>
                    </div>
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-500">
                        <span>Inkl. VOB-Vorlagen</span>
                        <span class="lexend-arrow-link text-blue-700">
                            <span class="arrow-icon">→</span>
                        </span>
                    </div>
                </div>

                <!-- Item 3: WhatsApp & Messenger -->
                <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group">
                    <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-emerald-100 text-emerald-800 text-xl font-black flex items-center justify-center">
                        💬
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-emerald-50 text-emerald-800 border border-emerald-200 w-fit">
                        Sofortkontakt
                    </span>
                    <div>
                        <h4 class="font-black text-slate-900 text-sm sm:text-base group-hover:text-emerald-700 transition-colors">
                            WhatsApp Business
                        </h4>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                            1-Klick Freigabelinks für Bautagebuch & Nachträge direkt an Bauherren und Poliere.
                        </p>
                    </div>
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-500">
                        <span>Kein Login nötig</span>
                        <span class="lexend-arrow-link text-emerald-700">
                            <span class="arrow-icon">→</span>
                        </span>
                    </div>
                </div>

                <!-- Item 4: Mobile PWA -->
                <div class="lexend-bento-card p-5 sm:p-6 rounded-2xl sm:rounded-3xl flex flex-col justify-between space-y-3.5 sm:space-y-4 group">
                    <div class="lexend-icon-box w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-amber-100 text-amber-800 text-xl font-black flex items-center justify-center">
                        📱
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] sm:text-[9.5px] font-black uppercase bg-amber-50 text-amber-800 border border-amber-200 w-fit">
                        iOS & Android
                    </span>
                    <div>
                        <h4 class="font-black text-slate-900 text-sm sm:text-base group-hover:text-amber-700 transition-colors">
                            PWA Baustellen-App
                        </h4>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                            Installierbar ohne App-Store auf Tablet & Smartphone. Vollständig offline-synchronisierbar.
                        </p>
                    </div>
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-500">
                        <span>Offline First</span>
                        <span class="lexend-arrow-link text-amber-700">
                            <span class="arrow-icon">→</span>
                        </span>
                    </div>
                </div>

            </div>

            <!-- Bottom Integrations Ribbon with Direct CTA -->
            <div class="mt-8 sm:mt-10 p-5 sm:p-6 rounded-2xl sm:rounded-3xl bg-slate-900 text-white flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 sm:gap-6 shadow-xl">
                <div class="flex items-center gap-3 sm:gap-4">
                    <span class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-white/10 flex items-center justify-center text-xl sm:text-2xl shrink-0">⚡</span>
                    <div>
                        <h4 class="font-black text-xs sm:text-base text-white">Sie nutzen individuelle Bauprogramme oder AVA-Software?</h4>
                        <p class="text-[11px] sm:text-xs text-slate-400 font-medium">Wir unterstützen flexible Excel-, CSV- und PDF-Importe für reibungslosen Datenfluss.</p>
                    </div>
                </div>
                <button wire:click="openDemoModal" class="w-full sm:w-auto px-5 sm:px-6 py-3 sm:py-3.5 bg-gradient-to-r from-blue-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-black text-xs rounded-xl shadow-md transition cursor-pointer shrink-0 btn-press">
                    Individuelle Schnittstellen anfragen →
                </button>
            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 6. INTERAKTIVER ROI & ERSPARNISRECHNER MIT DYNAMISCHER VISUALISIERUNG    -->
    <!-- ========================================================================= -->
    <section id="rechner" class="py-14 sm:py-24 bg-slate-100 border-t border-slate-200 relative reveal-on-scroll">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-2 sm:space-y-3 mb-10 sm:mb-14">
                <span class="px-3.5 py-1 rounded-full bg-emerald-100 border border-emerald-200 text-emerald-800 text-[10.5px] sm:text-xs font-black uppercase">
                    🧮 Wirtschaftlichkeitsrechner
                </span>
                <h2 class="text-2xl sm:text-4xl font-black text-slate-950 tracking-tight">
                    Berechnen Sie Ihre Ersparnis & Mehrumsatz mit BT Cockpit
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    Passen Sie die Schieberegler an Ihre Betriebsgröße an:
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center max-w-5xl mx-auto">
                
                <!-- Left: Interactive Sliders (6 cols) -->
                <div class="lg:col-span-6 layered-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200 space-y-5 sm:space-y-6">
                    
                    <!-- Slider 1: Baustellen -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-800">Gleichzeitige Baustellen:</label>
                            <span class="px-3 py-1 rounded-xl bg-blue-50 text-blue-700 font-black text-xs sm:text-sm border border-blue-200 tabular-nums">
                                {{ $roiProjectCount }} Baustellen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiProjectCount" min="1" max="25" step="1" class="w-full h-2.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>1 Baustelle</span>
                            <span>25 Baustellen</span>
                        </div>
                    </div>

                    <!-- Slider 2: Mitarbeiter -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-800">Mitarbeiter & Bauleiter:</label>
                            <span class="px-3 py-1 rounded-xl bg-indigo-50 text-indigo-700 font-black text-xs sm:text-sm border border-indigo-200 tabular-nums">
                                {{ $roiWorkerCount }} Personen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiWorkerCount" min="2" max="40" step="1" class="w-full h-2.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>2 Mitarbeiter</span>
                            <span>40 Mitarbeiter</span>
                        </div>
                    </div>

                    <!-- Slider 3: Stundensatz -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-800">Kalkulatorischer Stundensatz:</label>
                            <span class="px-3 py-1 rounded-xl bg-amber-50 text-amber-800 font-black text-xs sm:text-sm border border-amber-200 tabular-nums">
                                {{ $roiHourlyRate }} € / Std.
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiHourlyRate" min="45" max="110" step="5" class="w-full h-2.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-amber-600">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>45 €</span>
                            <span>110 €</span>
                        </div>
                    </div>

                </div>

                <!-- Right: Calculated Results (6 cols) -->
                <div class="lg:col-span-6 bg-gradient-to-br from-slate-900 via-slate-950 to-blue-950 text-white p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-800 shadow-2xl space-y-5 sm:space-y-6 card-lift">
                    
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-amber-400 tracking-wider">Ihr kalkulierter Jahresvorteil</span>
                        <h4 class="text-3xl sm:text-5xl font-black text-white tabular-nums tracking-tight">
                            ~ {{ number_format($this->totalValuePerYear, 0, ',', '.') }} € <span class="text-xs sm:text-sm text-slate-400 font-medium">/ Jahr</span>
                        </h4>
                    </div>

                    <div class="space-y-2.5 sm:space-y-3 text-xs pt-3 border-t border-slate-800">
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-white/5">
                            <span class="text-slate-300">⏱️ Eingesparte Büro- & Doku-Zeit:</span>
                            <span class="font-black text-blue-400 tabular-nums text-sm">~ {{ $this->savedHoursPerMonth }} Std. / Monat</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-white/5">
                            <span class="text-slate-300">💶 Bürokratiekosten-Ersparnis:</span>
                            <span class="font-black text-emerald-400 tabular-nums text-sm">{{ number_format($this->savedCostPerYear, 0, ',', '.') }} € / Jahr</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-white/5">
                            <span class="text-slate-300">📈 Zusätzliche Nachtragserlöse (VOB/B):</span>
                            <span class="font-black text-amber-400 tabular-nums text-sm">+ {{ number_format($this->additionalSupplementRevenue, 0, ',', '.') }} € / Jahr</span>
                        </div>
                    </div>

                    <button wire:click="openDemoModal" class="w-full py-3.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-500/20 transition cursor-pointer btn-press">
                        Diesen Vorteil jetzt für Ihren Betrieb sichern →
                    </button>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 7. VORHER VS. NACHHER VERGLEICH (INTERAKTIVER TAB- & SPLIT-VERGLEICH)     -->
    <!-- ========================================================================= -->
    <section id="vorteile" x-data="{ viewMode: 'both' }" class="py-14 sm:py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 reveal-on-scroll">
        
        <div class="text-center max-w-3xl mx-auto space-y-2 sm:space-y-3 mb-8 sm:mb-12">
            <span class="px-3.5 py-1 rounded-full bg-cyan-100 border border-cyan-200 text-cyan-800 text-[10.5px] sm:text-xs font-black uppercase">
                ⚡ Der direkte Vergleich
            </span>
            <h2 class="text-2xl sm:text-4xl font-black text-slate-950 tracking-tight">
                Vorher vs. Nachher: Ihr Baustellenalltag transformiert
            </h2>
            <p class="text-xs sm:text-sm text-slate-600 font-medium">
                Sehen Sie den Unterschied zwischen gewohntem Papierchaos und moderner digitaler Bauleitung:
            </p>

            <!-- Interactive View Filter Buttons -->
            <div class="pt-3 inline-flex items-center gap-1.5 p-1 bg-slate-100 rounded-2xl border border-slate-200 text-xs">
                <button type="button" 
                        @click="viewMode = 'both'" 
                        :class="viewMode === 'both' ? 'bg-white text-slate-900 shadow-xs font-black' : 'text-slate-500 hover:text-slate-900 font-bold'" 
                        class="px-3 sm:px-4 py-1.5 rounded-xl transition text-[11px] sm:text-xs">
                    ↔️ Nebeneinander
                </button>
                <button type="button" 
                        @click="viewMode = 'before'" 
                        :class="viewMode === 'before' ? 'bg-rose-50 text-rose-800 border border-rose-200 font-black' : 'text-slate-500 hover:text-slate-900 font-bold'" 
                        class="px-3 sm:px-4 py-1.5 rounded-xl transition text-[11px] sm:text-xs">
                    ❌ Ohne Software
                </button>
                <button type="button" 
                        @click="viewMode = 'after'" 
                        :class="viewMode === 'after' ? 'bg-blue-600 text-white font-black' : 'text-slate-500 hover:text-slate-900 font-bold'" 
                        class="px-3 sm:px-4 py-1.5 rounded-xl transition text-[11px] sm:text-xs">
                    ✨ Mit BT Cockpit
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 max-w-5xl mx-auto">
            
            <!-- BEFORE CARD -->
            <div x-show="viewMode === 'both' || viewMode === 'before'" 
                 x-transition:enter="transition ease-out duration-300 transform" 
                 x-transition:enter-start="opacity-0 scale-95" 
                 x-transition:enter-end="opacity-100 scale-100" 
                 class="layered-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl border border-rose-200 shadow-sm space-y-4 sm:space-y-5 card-lift">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-black text-base shrink-0">
                        ✕
                    </div>
                    <div>
                        <h4 class="font-black text-slate-900 text-sm sm:text-base">Klassischer Baualltag (Vorher)</h4>
                        <span class="text-[10.5px] sm:text-[11px] text-rose-700 font-bold">Hoher Zeitverlust & Haftungsrisiko</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-600 font-medium">
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Papier-Bautagebücher:</strong> Werden unvollständig oder erst Tage später aus dem Gedächtnis ausgefüllt.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Verlorene VOB-Nachträge:</strong> Mehrleistungen werden auf Zuruf ausgeführt, aber am Ende vom Bauherrn bestritten.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Aufmaß-Streitigkeiten:</strong> Unleserliche Handzettel führen zu Verzögerungen bei der Schlussrechnung.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-rose-50/50">
                        <span class="text-rose-600 font-bold text-sm leading-none shrink-0">✕</span>
                        <span><strong>Monatsabschluss-Chaos:</strong> Stundenzettel und Subunternehmerrechnungen müssen manuell abgetippt werden.</span>
                    </li>
                </ul>
            </div>

            <!-- AFTER CARD -->
            <div x-show="viewMode === 'both' || viewMode === 'after'" 
                 x-transition:enter="transition ease-out duration-300 transform" 
                 x-transition:enter-start="opacity-0 scale-95" 
                 x-transition:enter-end="opacity-100 scale-100" 
                 class="layered-card p-5 sm:p-8 rounded-2xl sm:rounded-3xl border-2 border-blue-600 shadow-xl space-y-4 sm:space-y-5 relative card-lift">
                <div class="absolute -top-3 right-4 sm:right-6 px-3 py-0.5 sm:py-1 bg-blue-600 text-white rounded-full text-[9px] sm:text-[10px] font-black tracking-wider uppercase shadow-xs">
                    Empfohlener Standard
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-black text-base shrink-0">
                        ✓
                    </div>
                    <div>
                        <h4 class="font-black text-slate-900 text-sm sm:text-base">Mit BT Bautechnik Cockpit (Nachher)</h4>
                        <span class="text-[10.5px] sm:text-[11px] text-emerald-700 font-bold">100% rechtssicher, digital & rentabel</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-700 font-semibold">
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-emerald-50/60">
                        <span class="text-emerald-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>30s KI-Sprachmemo:</strong> Erzeugt das vollständige Bautagebuch samt Wetter, Fotos und Mängeln sofort.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-emerald-50/60">
                        <span class="text-emerald-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>1-Klick Nachträge VOB/B § 2:</strong> Rechtssichere PDF-Angebote mit offiziellem Briefkopf vor Ausführung.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-emerald-50/60">
                        <span class="text-emerald-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>Digitales Aufmaß (DIN 18299):</strong> Transparente Berechnungsformeln und sofortige Freigabe durch den Bauherrn.</span>
                    </li>
                    <li class="flex items-start gap-2.5 p-2 rounded-xl bg-emerald-50/60">
                        <span class="text-emerald-600 font-bold text-sm leading-none shrink-0">✓</span>
                        <span><strong>DATEV SKR03/04 Export:</strong> Automatische § 13b UStG Steuerschlüssel für Subunternehmer auf Knopfdruck.</span>
                    </li>
                </ul>
            </div>

        </div>

    </section>

    <!-- ========================================================================= -->
    <!-- 8. BAUPRAXIS-STIMMEN & PRAXIS-TESTIMONIALS                                 -->
    <!-- ========================================================================= -->
    <section class="py-14 sm:py-24 bg-gradient-to-b from-white via-slate-50 to-white border-t border-slate-200 relative reveal-on-scroll">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-2 sm:space-y-3 mb-10 sm:mb-14">
                <span class="px-3.5 py-1 rounded-full bg-amber-100 border border-amber-200 text-amber-900 text-[10.5px] sm:text-xs font-black uppercase">
                    ⭐ Aus der Baupraxis
                </span>
                <h2 class="text-2xl sm:text-4xl font-black text-slate-950 tracking-tight">
                    Was Bauleiter & Bauträger über BT Cockpit sagen
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    Praxisberichte von Unternehmen, die ihre Baustellen digitalisieren:
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                
                <!-- Testimonial 1 -->
                <div class="layered-card p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200 flex flex-col justify-between space-y-4 card-lift">
                    <div class="space-y-3">
                        <div class="flex items-center text-amber-400 text-sm">
                            ★★★★★
                        </div>
                        <p class="text-xs sm:text-[13px] text-slate-700 leading-relaxed font-medium">
                            „Früher sind uns bei fast jedem Projekt mehrere tausend Euro an VOB-Nachträgen durchgerutscht, weil auf der Baustelle niemand Zeit zum Schreiben hatte. Mit dem KI-Bautagebuch ist der Tagesbericht in 45 Sekunden fertig.“
                        </p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-800 font-black flex items-center justify-center text-sm shrink-0">
                            SM
                        </div>
                        <div>
                            <h5 class="text-xs font-black text-slate-900">Dipl.-Ing. Stefan Maier</h5>
                            <span class="text-[11px] text-slate-500 font-medium block">Geschäftsführer Bau & Sanierung GmbH, München</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="layered-card p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200 flex flex-col justify-between space-y-4 card-lift">
                    <div class="space-y-3">
                        <div class="flex items-center text-amber-400 text-sm">
                            ★★★★★
                        </div>
                        <p class="text-xs sm:text-[13px] text-slate-700 leading-relaxed font-medium">
                            „Die DATEV-Übergabe mit SKR03 und der automatischen § 13b-Zuordnung für Nachunternehmer spart unserer Buchhaltung 2 volle Tage am Monatsende. Absoluter Gamechanger für unseren Betrieb.“
                        </p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-800 font-black flex items-center justify-center text-sm shrink-0">
                            MW
                        </div>
                        <div>
                            <h5 class="text-xs font-black text-slate-900">Markus Weber</h5>
                            <span class="text-[11px] text-slate-500 font-medium block">Bauleiter Schlüsselfertigbau, Nürnberg</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="layered-card p-6 sm:p-8 rounded-2xl sm:rounded-3xl border border-slate-200 flex flex-col justify-between space-y-4 card-lift">
                    <div class="space-y-3">
                        <div class="flex items-center text-amber-400 text-sm">
                            ★★★★★
                        </div>
                        <p class="text-xs sm:text-[13px] text-slate-700 leading-relaxed font-medium">
                            „Endlich eine Software ohne überflüssigen Schnickschnack. Meine Poliere vor Ort bedienen das System ohne jede Schulung direkt auf dem Smartphone im Browser. Einfach genial.“
                        </p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 font-black flex items-center justify-center text-sm shrink-0">
                            TB
                        </div>
                        <div>
                            <h5 class="text-xs font-black text-slate-900">Thomas Brandl</h5>
                            <span class="text-[11px] text-slate-500 font-medium block">Bauträger & Projektentwickler, Regensburg</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 7. FAQ SECTION                                                            -->
    <!-- ========================================================================= -->
    <section id="faq" class="py-14 sm:py-20 bg-slate-100 border-t border-slate-200 reveal-on-scroll">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center space-y-2 sm:space-y-3 mb-8 sm:mb-10">
                <span class="px-3.5 py-1 rounded-full bg-white border border-slate-200 text-slate-700 text-[10.5px] sm:text-xs font-black uppercase">
                    💬 Häufige Fragen
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-950 tracking-tight">
                    Fragen von Bauträgern & Bauunternehmen
                </h2>
            </div>

            <div x-data="{ openFaq: 0 }" class="space-y-2.5 sm:space-y-3 text-xs">
                
                <div class="bg-white border border-slate-200 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 transition shadow-xs card-lift">
                    <button type="button" @click="openFaq = (openFaq === 0 ? null : 0)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Ist die Software auf Smartphones und Tablets auf der Baustelle nutzbar?</span>
                        <span class="text-blue-700 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 0 ? '−' : '+'">−</span>
                    </button>
                    <p x-show="openFaq === 0" x-cloak class="mt-2.5 sm:mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium text-xs">
                        Ja! BT Bautechnik Cockpit ist als Progressive Web App (PWA) konzipiert. Es läuft reaktionsschnell auf jedem iPhone, Android-Smartphone, iPad oder Laptop – ohne umständliche App-Store Installation.
                    </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 transition shadow-xs card-lift">
                    <button type="button" @click="openFaq = (openFaq === 1 ? null : 1)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Wie funktioniert die Nachtragserstellung nach VOB/B § 2?</span>
                        <span class="text-blue-700 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 1 ? '−' : '+'">+</span>
                    </button>
                    <p x-show="openFaq === 1" x-cloak class="mt-2.5 sm:mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium text-xs">
                        Das System unterscheidet automatisch zwischen Leistungsänderungen (§ 2 Abs. 5) und unvorhergesehenen Zusatzleistungen (§ 2 Abs. 6). Sie geben Titel und Menge ein – das System erstellt sofort das unterschriftsreife Nachtragsangebot als PDF mit rechtssicherer Klausulierung.
                    </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 transition shadow-xs card-lift">
                    <button type="button" @click="openFaq = (openFaq === 2 ? null : 2)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Kann mein Steuerberater die Rechnungen und Kosten direkt importieren?</span>
                        <span class="text-blue-700 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 2 ? '−' : '+'">+</span>
                    </button>
                    <p x-show="openFaq === 2" x-cloak class="mt-2.5 sm:mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium text-xs">
                        Ja. Das System verfügt über eine integrierte DATEV CSV-Schnittstelle nach SKR03 und SKR04 inklusive automatischem Buchungsschlüssel für Nachunternehmer-Rechnungen (§ 13b UStG Bauleistungen).
                    </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 transition shadow-xs card-lift">
                    <button type="button" @click="openFaq = (openFaq === 3 ? null : 3)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-xs sm:text-sm cursor-pointer gap-2">
                        <span>Können wir das System unverbindlich testen?</span>
                        <span class="text-blue-700 text-sm sm:text-base font-bold shrink-0" x-text="openFaq === 3 ? '−' : '+'">+</span>
                    </button>
                    <p x-show="openFaq === 3" x-cloak class="mt-2.5 sm:mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium text-xs">
                        Absolut. Klicken Sie einfach auf "Demo anfordern". Wir zeigen Ihnen in 15 Minuten per Videoschalte oder direkt vor Ort, wie Sie das System für Ihre Baustellen einrichten.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 8. BIG CTA BOTTOM BANNER                                                  -->
    <!-- ========================================================================= -->
    <section class="py-14 sm:py-20 relative overflow-hidden bg-white border-t border-slate-200 reveal-on-scroll">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white p-6 sm:p-12 rounded-2xl sm:rounded-3xl shadow-2xl space-y-4 sm:space-y-6">
                <h2 class="text-xl sm:text-3xl lg:text-4xl font-black text-white tracking-tight leading-snug">
                    Bereit, Ihre Baustellen & Finanzen auf das nächste Level zu heben?
                </h2>
                <p class="text-xs sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed font-medium">
                    Schließen Sie sich zukunftsorientierten Bauunternehmen & Bauträgern an. Fordern Sie jetzt Ihre persönliche Live-Präsentation an.
                </p>
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-6 sm:px-8 py-3.5 sm:py-4 bg-gradient-to-r from-blue-500 via-indigo-500 to-amber-500 hover:from-blue-400 hover:to-amber-400 text-white font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl shadow-xl transition cursor-pointer btn-press">
                        🚀 Jetzt kostenlose Demo anfordern
                    </button>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto px-5 sm:px-6 py-3.5 sm:py-4 bg-white/10 hover:bg-white/20 text-white font-black text-xs sm:text-sm rounded-xl sm:rounded-2xl border border-white/20 transition">
                        Bestehendes Kundenkonto Login ↗
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 9. FOOTER WITH REAL LEGAL ENTITY & COMPLIANCE LINKS                       -->
    <!-- ========================================================================= -->
    <footer class="border-t border-slate-200 bg-white py-8 sm:py-12 text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4 sm:gap-6">
            
            <!-- Real Brand Identity in Footer -->
            <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3 text-center sm:text-left">
                <x-brand-logo size="small" />
                <span class="text-slate-300 hidden sm:inline">•</span>
                <span class="text-slate-500 text-[11px] sm:text-xs font-medium">
                    BT Bautechnik UG (haftungsbeschränkt) | Sollngriesbacher Str. 4, 92334 Berching 🇩🇪
                </span>
            </div>

            <!-- Legal Pages Links -->
            <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 font-bold text-slate-700 text-xs">
                <a href="/impressum" class="hover:text-blue-700 transition">Impressum</a>
                <a href="/datenschutz" class="hover:text-blue-700 transition">Datenschutz</a>
                <a href="/agb" class="hover:text-blue-700 transition">AGB</a>
                <a href="{{ route('login') }}" class="hover:text-blue-700 transition text-blue-700">Kunden-Login ↗</a>
            </div>
        </div>
    </footer>

    <!-- ========================================================================= -->
    <!-- 10. INTERACTIVE LEAD CAPTURE & DEMO REQUEST MODAL                         -->
    <!-- ========================================================================= -->
    @if ($showDemoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/70 backdrop-blur-sm">
            <div class="bg-white border border-slate-200 rounded-2xl sm:rounded-3xl p-5 sm:p-8 max-w-lg w-full max-h-[92vh] overflow-y-auto shadow-2xl space-y-4 sm:space-y-6 relative">
                
                <button wire:click="closeDemoModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-900 text-xl font-bold cursor-pointer">✕</button>

                @if ($demoSuccess)
                    <div class="py-6 sm:py-8 text-center space-y-3 sm:space-y-4">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-2xl sm:text-3xl mx-auto font-bold animate-bounce">
                            ✓
                        </div>
                        <h3 class="text-lg sm:text-xl font-black text-slate-900">Vielen Dank für Ihre Anfrage!</h3>
                        <p class="text-xs text-slate-600 max-w-sm mx-auto leading-relaxed font-medium">
                            Wir haben Ihre Daten erhalten. Unsere Bauleitung der <strong>BT Bautechnik UG</strong> wird sich in Kürze für eine persönliche Live-Präsentation bei Ihnen melden.
                        </p>
                        <div class="pt-2">
                            <button wire:click="closeDemoModal" class="px-6 py-2.5 bg-blue-700 hover:bg-blue-600 text-white font-black text-xs rounded-xl cursor-pointer">
                                Fertig
                            </button>
                        </div>
                    </div>
                @else
                    <div class="space-y-1">
                        <span class="text-[9.5px] sm:text-[10px] font-black uppercase text-amber-700 tracking-wider">Unverbindliche Präsentation</span>
                        <h3 class="text-lg sm:text-xl font-black text-slate-900">Live-Demo für Ihr Bauunternehmen</h3>
                        <p class="text-xs text-slate-500 font-medium">Erfahren Sie, wie BT Cockpit Ihren Baustellenalltag revolutioniert.</p>
                    </div>

                    <form wire:submit="submitDemoRequest" class="space-y-3 sm:space-y-3.5 text-xs">
                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Ihr Name / Ansprechpartner *</label>
                            <input wire:model="demoName" type="text" placeholder="z. B. Dipl.-Ing. Markus Huber" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-blue-600 focus:outline-none" required>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Unternehmen / Firma *</label>
                            <input wire:model="demoCompany" type="text" placeholder="z. B. Huber Bau & Sanierung GmbH" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-blue-600 focus:outline-none" required>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">E-Mail-Adresse *</label>
                                <input wire:model="demoEmail" type="email" placeholder="m.huber@huberbau.de" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-medium rounded-xl p-2.5 focus:border-blue-600 focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Telefon / Mobil *</label>
                                <input wire:model="demoPhone" type="tel" placeholder="0171 1234567" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-medium rounded-xl p-2.5 focus:border-blue-600 focus:outline-none" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Ihr Schwerpunkt</label>
                                <select wire:model="demoTrade" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-blue-600 focus:outline-none">
                                    <option value="bautraeger">Bauträger / Entwickler</option>
                                    <option value="generalunternehmer">Generalübernehmer / GU</option>
                                    <option value="sanierung_abdichtung">Sanierung & Abdichtung</option>
                                    <option value="hoch_tiefbau">Hoch- & Tiefbau</option>
                                    <option value="handwerk">Fachhandwerksbetrieb</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-800 mb-1">Baustellen pro Jahr</label>
                                <select wire:model="demoProjectCount" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-blue-600 focus:outline-none">
                                    <option value="1-3">1 – 3 Bauvorhaben</option>
                                    <option value="4-10">4 – 10 Bauvorhaben</option>
                                    <option value="10+">Über 10 Bauvorhaben</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Nachricht / Notiz (optional)</label>
                            <textarea wire:model="demoMessage" rows="2" placeholder="Welche Module interessieren Sie besonders (z.B. VOB-Nachträge, Aufmaße, KI-Bautagebuch)?" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl p-2.5 focus:border-blue-600 focus:outline-none"></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="text-xs text-emerald-700 hover:underline flex items-center gap-1 font-bold">
                                <span>💬 Lieber per WhatsApp anfragen</span>
                            </a>

                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 hover:from-blue-600 hover:to-amber-500 text-white font-black text-xs rounded-xl shadow-md shadow-blue-600/20 cursor-pointer btn-press">
                                Demo-Termin vereinbaren →
                            </button>
                        </div>
                    </form>
                @endif

            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 9. MOBILE STICKY QUICK-ACTION BAR (FLOAT AT BOTTOM ON SMARTPHONES)        -->
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
        <div class="bg-slate-950/95 backdrop-blur-xl border border-slate-700/80 rounded-2xl p-2.5 shadow-2xl flex items-center justify-between gap-2.5">
            <button wire:click="openDemoModal" class="flex-1 py-3 px-4 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-600 active:scale-95 text-white font-black text-xs rounded-xl shadow-md flex items-center justify-center gap-1.5 transition">
                <span>⚡ Live-Demo</span>
            </button>
            <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20eine%20Live-Demo%20anfragen." target="_blank" class="py-3 px-3.5 bg-emerald-600 active:scale-95 text-white font-black text-xs rounded-xl shadow-md flex items-center justify-center gap-1 shrink-0 transition">
                <span>💬 WhatsApp</span>
            </a>
        </div>
    </div>

</div>
