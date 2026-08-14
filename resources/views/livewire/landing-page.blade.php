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
    public string $activeModuleTab = 'cockpit'; // cockpit, contacts360, supplements, measurements, dailylogs, datev, ai_agent

    // Interactive FAQ Accordion State
    public ?int $openFaqIndex = 0;

    // Open/Close Demo Modal
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

    // Computed ROI Properties
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

<div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-blue-600 selection:text-white">
    
    <!-- ========================================================================= -->
    <!-- 1. STICKY TOP NAVBAR                                                      -->
    <!-- ========================================================================= -->
    <header class="sticky top-0 z-40 bg-slate-950/85 backdrop-blur-md border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Logo Brand -->
            <a href="/" class="flex items-center gap-3 group">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 flex items-center justify-center font-black text-white text-xl shadow-lg shadow-blue-500/25 group-hover:scale-105 transition">
                    BT
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-black text-lg sm:text-xl text-white tracking-tight">BT BAUTECHNIK</span>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-blue-500/20 text-blue-400 border border-blue-500/30">ERP COCKPIT</span>
                    </div>
                    <span class="text-[10px] text-slate-400 block font-medium tracking-wide">Aus der Praxis für echte Bauprofis & Bauträger</span>
                </div>
            </a>

            <!-- Nav Links (Desktop) -->
            <nav class="hidden md:flex items-center gap-8 text-xs font-bold text-slate-300">
                <a href="#module" class="hover:text-white transition">Module & VOB</a>
                <a href="#vorteile" class="hover:text-white transition">Warum BT Cockpit?</a>
                <a href="#rechner" class="hover:text-white transition flex items-center gap-1.5 text-blue-400 font-black">
                    <span>🧮 Ersparnisrechner</span>
                </a>
                <a href="#ueber-uns" class="hover:text-white transition">Praxis-Vorteil</a>
                <a href="#faq" class="hover:text-white transition">FAQ</a>
            </nav>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl border border-slate-700 transition flex items-center gap-2">
                        <span>📊 Zum Cockpit</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="px-3.5 py-2 text-slate-300 hover:text-white font-bold text-xs transition">
                        Login ↗
                    </a>
                @endauth

                <button wire:click="openDemoModal" class="px-4 sm:px-5 py-2.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-500 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-500/30 hover:shadow-blue-500/50 transition cursor-pointer flex items-center gap-1.5 btn-press">
                    <span>✨ Demo anfordern</span>
                </button>
            </div>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- 2. HERO SECTION                                                           -->
    <!-- ========================================================================= -->
    <section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden">
        
        <!-- Glow effects in background -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[450px] bg-blue-600/20 rounded-full blur-[140px] pointer-events-none"></div>
        <div class="absolute top-1/3 -left-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute top-1/2 -right-32 w-96 h-96 bg-cyan-600/15 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="text-center max-w-4xl mx-auto space-y-6">
                
                <!-- Badge Pill -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-900/90 border border-blue-500/30 text-blue-300 text-xs font-bold shadow-inner">
                    <span class="flex h-2 w-2 rounded-full bg-blue-400 animate-ping"></span>
                    <span>🏗️ Die #1 Bau-ERP & Bauleiter-Software – Entwickelt im echten Baustellenalltag</span>
                </div>

                <!-- Main Hero Headline -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-tight sm:leading-none">
                    Schluss mit Zettelwirtschaft.<br>
                    <span class="bg-gradient-to-r from-blue-400 via-indigo-300 to-cyan-300 bg-clip-text text-transparent">
                        Volle Baustellen- & VOB-Kontrolle in einem Cockpit.
                    </span>
                </h1>

                <!-- Subtitle -->
                <p class="text-sm sm:text-lg text-slate-300 font-medium max-w-3xl mx-auto leading-relaxed">
                    Speziell für <strong>Bauträger, Bauunternehmen & Generalübernehmer</strong>: Steuern Sie Baustellen, Kunden, Nachträge nach VOB/B, digitale Aufmaße (VOB/C), KI-Bautagebücher und DATEV-Finanzen in Sekunden.
                </p>

                <!-- Hero CTAs -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-500 hover:from-blue-500 hover:to-cyan-400 text-white font-black text-sm rounded-2xl shadow-xl shadow-blue-500/30 hover:shadow-blue-500/60 transition cursor-pointer flex items-center justify-center gap-2.5 btn-press">
                        <span>🚀 Kostenlose Live-Demo anfordern</span>
                        <span>→</span>
                    </button>

                    <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20interessiere%20mich%20f%C3%BCr%20Ihr%20Bauleiter%20ERP%20Cockpit" target="_blank" class="w-full sm:w-auto px-6 py-4 bg-slate-900 hover:bg-slate-800 text-slate-200 hover:text-white font-bold text-sm rounded-2xl border border-slate-800 hover:border-slate-700 transition flex items-center justify-center gap-2">
                        <span>💬 Direkt per WhatsApp abstimmen</span>
                    </a>
                </div>

                <!-- Trust Badges Strip -->
                <div class="pt-8 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                    <div class="bg-slate-900/60 backdrop-blur-xs p-3 rounded-2xl border border-slate-800/80">
                        <span class="text-blue-400 font-bold block">⚖️ VOB/B § 2 & VOB/C</span>
                        <span class="text-slate-400 text-[11px]">100% rechtssicher</span>
                    </div>
                    <div class="bg-slate-900/60 backdrop-blur-xs p-3 rounded-2xl border border-slate-800/80">
                        <span class="text-indigo-400 font-bold block">🎙️ KI-Sprachmemo</span>
                        <span class="text-slate-400 text-[11px]">Bautagebuch in 30 Sek.</span>
                    </div>
                    <div class="bg-slate-900/60 backdrop-blur-xs p-3 rounded-2xl border border-slate-800/80">
                        <span class="text-emerald-400 font-bold block">📊 DATEV-Export</span>
                        <span class="text-slate-400 text-[11px]">SKR03 / SKR04 Schnittstelle</span>
                    </div>
                    <div class="bg-slate-900/60 backdrop-blur-xs p-3 rounded-2xl border border-slate-800/80">
                        <span class="text-cyan-400 font-bold block">📱 Mobile PWA</span>
                        <span class="text-slate-400 text-[11px]">Für Smartphone & Tablet</span>
                    </div>
                </div>

            </div>

            <!-- Interactive Hero Mockup Card -->
            <div class="mt-14 max-w-5xl mx-auto rounded-3xl p-2 sm:p-3 bg-gradient-to-b from-blue-500/30 via-slate-800/30 to-slate-900/60 border border-blue-500/30 shadow-2xl shadow-blue-500/10">
                <div class="bg-slate-900/95 rounded-2xl border border-slate-800 overflow-hidden shadow-inner">
                    
                    <!-- Window bar -->
                    <div class="px-4 py-3 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-rose-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500/80"></span>
                            <span class="text-xs text-slate-400 font-mono ml-2">app.bautechnik-bt.de / cockpit / live-preview</span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            LIVE SYSTEM
                        </span>
                    </div>

                    <!-- Mockup Body -->
                    <div class="p-4 sm:p-6 space-y-6">
                        
                        <!-- Mini Header -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div>
                                <span class="text-[10px] font-mono text-blue-400 font-bold uppercase">PROJEKT #2026-081</span>
                                <h3 class="text-base sm:text-lg font-black text-white">WEG Maximilianstraße 44 – Tiefgaragenabdichtung & Sanierung</h3>
                                <p class="text-xs text-slate-400">Auftraggeber: Hausverwaltung Müller & Partner GmbH (Nürnberg)</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 rounded-xl bg-blue-600/20 text-blue-400 border border-blue-500/30 font-bold text-xs">
                                    KW 32 – KW 38
                                </span>
                                <span class="px-3 py-1 rounded-xl bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 font-bold text-xs">
                                    Aktiv im Plan
                                </span>
                            </div>
                        </div>

                        <!-- Progress & Budget -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Geplantes Budget</span>
                                <p class="text-base font-black text-white mt-0.5">85.000,00 €</p>
                                <div class="w-full bg-slate-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                    <div class="bg-blue-500 h-full w-[65%]"></div>
                                </div>
                            </div>
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Nachtragsvolumen (VOB/B)</span>
                                <p class="text-base font-black text-indigo-400 mt-0.5">+ 12.450,00 €</p>
                                <span class="text-[10px] text-emerald-400 font-semibold">3 freigegeben, 1 in Prüfung</span>
                            </div>
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800">
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Aufmaßstand (VOB/C)</span>
                                <p class="text-base font-black text-cyan-400 mt-0.5">620 m² / 750 m²</p>
                                <span class="text-[10px] text-slate-400 font-semibold">82% fertiggestellt</span>
                            </div>
                        </div>

                        <!-- Mini Sub Action Bar -->
                        <div class="p-3 bg-slate-950/80 rounded-xl border border-slate-800/80 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-2 text-slate-300">
                                <span>🎙️ Bautagesbericht heute per KI erfasst</span>
                                <span class="text-slate-500">•</span>
                                <span class="text-slate-400">Wetter: 22°C trocken</span>
                                <span class="text-slate-500">•</span>
                                <span class="text-slate-400">4 Monteure vor Ort</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 bg-blue-600/30 text-blue-300 rounded-lg font-bold text-[11px]">
                                    📑 Nachtrags-PDF erzeugt
                                </span>
                                <span class="px-2.5 py-1 bg-emerald-600/30 text-emerald-300 rounded-lg font-bold text-[11px]">
                                    📐 Aufmaß exportiert
                                </span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 3. DIE STORY: AUS DER BAU-PRAXIS FÜR DIE PRAXIS                            -->
    <!-- ========================================================================= -->
    <section id="ueber-uns" class="py-20 bg-slate-900/50 border-y border-slate-800/80 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <div class="lg:col-span-6 space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-black uppercase">
                        <span>🧱 Praxis-Vorteil</span>
                    </div>

                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
                        Warum herkömmliche Software auf der Baustelle scheitert – und warum wir es anders machen.
                    </h2>

                    <div class="space-y-4 text-sm text-slate-300 leading-relaxed font-medium">
                        <p>
                            Als aktive <strong>BT Bautechnik UG</strong> führen wir selbst anspruchsvolle Bau- und Sanierungsprojekte durch. Wir kennen die Realität:
                        </p>
                        <ul class="space-y-2.5 list-none pl-0">
                            <li class="flex items-start gap-2.5">
                                <span class="text-rose-400 font-bold">❌</span>
                                <span>Bauleiter haben keine Zeit, abends stundenlang komplizierte Desktop-Programme zu bedienen.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="text-rose-400 font-bold">❌</span>
                                <span>Nachträge nach VOB/B § 2 gehen verloren oder werden zu spät eingereicht, weil der Prozess zu zäh ist.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="text-rose-400 font-bold">❌</span>
                                <span>Aufmaße und Mängelfotos landen in unübersichtlichen WhatsApp-Gruppen statt im Projektordner.</span>
                            </li>
                        </ul>
                        <p class="pt-2 text-white font-bold">
                            💡 Das BT Bautechnik Cockpit löst all diese Probleme mit einem Klick direkt auf dem Smartphone oder Tablet vor Ort.
                        </p>
                    </div>

                    <div class="pt-2">
                        <button wire:click="openDemoModal" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-extrabold text-xs rounded-xl shadow-md shadow-blue-500/20 transition cursor-pointer btn-press">
                            Erfahren Sie, wie Ihr Betrieb profitieren kann →
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-950 p-6 rounded-3xl border border-slate-800 space-y-3 hover:border-blue-500/40 transition">
                        <div class="w-12 h-12 rounded-2xl bg-blue-600/20 text-blue-400 flex items-center justify-center text-2xl font-bold">
                            ⏱️
                        </div>
                        <h4 class="font-black text-white text-base">In 30 Sekunden erledigt</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Sprachmemo einsprechen – die KI erstellt das fertige Bautagebuch mit Wetter und Gewerken.
                        </p>
                    </div>

                    <div class="bg-slate-950 p-6 rounded-3xl border border-slate-800 space-y-3 hover:border-indigo-500/40 transition">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center text-2xl font-bold">
                            📑
                        </div>
                        <h4 class="font-black text-white text-base">VOB/B Nachtragsautomatik</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Nachträge nach § 2 Abs. 5/6 sofort mit fertigem Briefkopf und rechtssicherem PDF versenden.
                        </p>
                    </div>

                    <div class="bg-slate-950 p-6 rounded-3xl border border-slate-800 space-y-3 hover:border-cyan-500/40 transition">
                        <div class="w-12 h-12 rounded-2xl bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-2xl font-bold">
                            👥
                        </div>
                        <h4 class="font-black text-white text-base">360° Kunden-Zentrale</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Der Bauherr als Dreh- und Angelpunkt: Baustellen, Aufmaße und Rechnungen direkt steuern.
                        </p>
                    </div>

                    <div class="bg-slate-950 p-6 rounded-3xl border border-slate-800 space-y-3 hover:border-emerald-500/40 transition">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-2xl font-bold">
                            📊
                        </div>
                        <h4 class="font-black text-white text-base">DATEV Buchungsstapel</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            SKR03/SKR04 Export für Ihren Steuerberater ohne lästige Doppeleingaben.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 4. INTERAKTIVER MODULE EXPLORER                                           -->
    <!-- ========================================================================= -->
    <section id="module" class="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto space-y-3 mb-12">
            <span class="px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-black uppercase">
                🚀 Die All-in-One Suite
            </span>
            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                Entdecken Sie alle Module im interaktiven Simulator
            </h2>
            <p class="text-xs sm:text-sm text-slate-400">
                Klicken Sie auf ein Modul, um die Praxis-Funktionen und den Live-Workflow kennenzulernen:
            </p>
        </div>

        <!-- Module Selector Tabs -->
        <div class="flex flex-wrap items-center justify-center gap-2 mb-8">
            <button wire:click="$set('activeModuleTab', 'cockpit')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'cockpit' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                <span>🏗️ Baustellen-Cockpit</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'contacts360')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'contacts360' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                <span>👥 360° Kunden-Zentrale</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'supplements')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'supplements' ? 'bg-purple-600 text-white shadow-lg shadow-purple-500/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                <span>📑 VOB/B Nachträge</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'measurements')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'measurements' ? 'bg-cyan-600 text-white shadow-lg shadow-cyan-500/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                <span>📐 VOB/C Aufmaßblatt</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'dailylogs')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'dailylogs' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                <span>🎙️ KI-Bautagebuch</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'datev')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'datev' ? 'bg-amber-600 text-white shadow-lg shadow-amber-500/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                <span>📊 DATEV & Finanzen</span>
            </button>
        </div>

        <!-- Interactive Module Showcase Screen -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl">
            
            @if ($activeModuleTab === 'cockpit')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-blue-400 tracking-wider">Kernmodul 01</span>
                        <h3 class="text-2xl font-black text-white">Baustellen-Cockpit & Soll/Ist-Steuerung</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Behalten Sie jedes Bauvorhaben im Griff: Budgetüberwachung, Bauzeitenplan nach Kalenderwochen, automatische Wetteraufzeichnung und lückenloses Fotoprotokoll.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ Echtzeit-Budgetverbrauch mit Soll/Ist-Kosten</p>
                            <p class="flex items-center gap-2">✅ Kalenderwochen-Terminplan (Start-KW bis End-KW)</p>
                            <p class="flex items-center gap-2">✅ Wetter-API mit automatischer Temperatur & Niederschlag</p>
                        </div>
                        <button wire:click="openDemoModal('bautraeger')" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Cockpit live testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                            <span class="font-bold text-xs text-white">🏢 Projekt: Neubau Wohnanlage Regensburg</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-400">KW 28 – KW 42</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="bg-slate-900 p-2.5 rounded-xl border border-slate-800">
                                <span class="text-[9px] text-slate-400 block font-bold">BUDGET SOLL</span>
                                <span class="font-black text-white text-sm">120.000 €</span>
                            </div>
                            <div class="bg-slate-900 p-2.5 rounded-xl border border-slate-800">
                                <span class="text-[9px] text-slate-400 block font-bold">IST-KOSTEN</span>
                                <span class="font-black text-blue-400 text-sm">74.500 €</span>
                            </div>
                            <div class="bg-slate-900 p-2.5 rounded-xl border border-slate-800">
                                <span class="text-[9px] text-slate-400 block font-bold">GEWINNMARGE</span>
                                <span class="font-black text-emerald-400 text-sm">+ 37,9%</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeModuleTab === 'contacts360')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-indigo-400 tracking-wider">Kernmodul 02</span>
                        <h3 class="text-2xl font-black text-white">360° Kunden- & Bauherren-Zentrale</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Da der Kunde der Eigentümer der Baustellen ist, steuern Sie alles direkt aus dem Kunden heraus: Neue Baustellen anlegen, VOB-Nachträge erfassen, Aufmaße abrufen und Mängel überwachen.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ 1-Klick-Aktionen pro Kunde (Baustelle, Nachtrag, Aufmaß, Rechnung)</p>
                            <p class="flex items-center gap-2">✅ Zeitgestempeltes Telefon- & Notizjournal</p>
                            <p class="flex items-center gap-2">✅ KI-Chefbauleiter Dossier für jedes Kundenprofil</p>
                        </div>
                        <button wire:click="openDemoModal('generalunternehmer')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Kunden-Zentrale testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-xs text-white">👤 Kunde: Hausverwaltung Schmidt & Co.</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/20 text-indigo-400">4 Baustellen</span>
                        </div>
                        <div class="p-3 bg-slate-900 rounded-xl border border-slate-800 flex flex-wrap gap-2 text-xs">
                            <span class="px-2 py-1 bg-blue-600/30 text-blue-300 rounded-lg font-bold">🏗️ + Baustelle</span>
                            <span class="px-2 py-1 bg-indigo-600/30 text-indigo-300 rounded-lg font-bold">📑 + Nachtrag</span>
                            <span class="px-2 py-1 bg-cyan-600/30 text-cyan-300 rounded-lg font-bold">📐 + Aufmaß</span>
                            <span class="px-2 py-1 bg-emerald-600/30 text-emerald-300 rounded-lg font-bold">📄 + Rechnung</span>
                            <span class="px-2 py-1 bg-purple-600/30 text-purple-300 rounded-lg font-bold">🤖 KI-Dossier</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeModuleTab === 'supplements')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-purple-400 tracking-wider">Kernmodul 03</span>
                        <h3 class="text-2xl font-black text-white">VOB/B Nachtragsmanagement (§ 2)</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Schluss mit vergessenen oder abgewiesenen Nachträgen. Erfassen Sie Mehrleistungen nach § 2 Abs. 5 (Leistungsänderung) oder § 2 Abs. 6 (Zusätzliche Leistung) sofort mit rechtssicherem PDF-Export.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ Automatische VOB-Begründung & Fristüberwachung</p>
                            <p class="flex items-center gap-2">✅ PDF-Nachtragsangebot mit rechtssicherem VOB-Briefkopf</p>
                            <p class="flex items-center gap-2">✅ Status: Eingereicht, Geprüft, Beauftragt, Abgerechnet</p>
                        </div>
                        <button wire:click="openDemoModal('sanierung_abdichtung')" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Nachtragsmodul ansehen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                            <span class="font-bold text-xs text-white">📑 Nachtragsangebot NT-03 nach VOB/B § 2 Abs. 5</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-purple-500/20 text-purple-400">BEAUFTRAGT</span>
                        </div>
                        <p class="text-xs text-slate-300 font-medium">
                            Titel: Zusätzliche Hohlkehlenabdichtung & Bitumen-Dickbeschichtung Rampe UG 2
                        </p>
                        <div class="flex justify-between items-center p-3 bg-slate-900 rounded-xl border border-slate-800 text-xs">
                            <span class="text-slate-400">Nachtragssumme Netto:</span>
                            <span class="text-base font-black text-white">4.850,00 €</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeModuleTab === 'measurements')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-cyan-400 tracking-wider">Kernmodul 04</span>
                        <h3 class="text-2xl font-black text-white">Digitales Aufmaßblatt (VOB/C / DIN 18299)</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Erfassen Sie Mengen direkt vor Ort mit Raummaßen (Länge × Breite × Höhe / Faktor), automatischem VOB-Abzug für Öffnungen und sofortigem PDF-Prüfprotokoll für den Bauherrn.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ Flexible Formeln (z. B. 12.50 * 4.20 * 2)</p>
                            <p class="flex items-center gap-2">✅ Automatischer VOB-Abzug nach DIN 18299 / DIN 18336</p>
                            <p class="flex items-center gap-2">✅ 1-Klick Übergabe in die Schlussrechnung</p>
                        </div>
                        <button wire:click="openDemoModal('hoch_tiefbau')" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Aufmaß-Engine testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                            <span class="font-bold text-xs text-white">📐 Aufmaßblatt AM-2026-004 (Bodenplatte TG)</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-cyan-500/20 text-cyan-400">DIN 18299</span>
                        </div>
                        <div class="space-y-2 text-xs font-mono">
                            <div class="p-2.5 bg-slate-900 rounded-lg flex justify-between">
                                <span>Fläche Achse 1-4: 18.50 × 8.20</span>
                                <span class="font-bold text-cyan-300">= 151,70 m²</span>
                            </div>
                            <div class="p-2.5 bg-slate-900 rounded-lg flex justify-between text-rose-300">
                                <span>Abzug Stütze (VOB): - (0.80 × 0.80 × 6)</span>
                                <span class="font-bold">- 3,84 m²</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeModuleTab === 'dailylogs')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-emerald-400 tracking-wider">Kernmodul 05</span>
                        <h3 class="text-2xl font-black text-white">KI-Bautagebuch & Sprachmemo (Whisper)</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Kein Bauleiter tippt gern Berichte auf der Baustelle. Nehmen Sie einfach 30 Sekunden Sprachmemo auf – die KI formuliert einen druckreifen, rechtssicheren Bautagesbericht mit Wetter, Anwesenden und Gewerken.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ KI-Sprachaufnahme & automatische Text-Strukturierung</p>
                            <p class="flex items-center gap-2">✅ Integrierter Fotoupload mit Beschriftung</p>
                            <p class="flex items-center gap-2">✅ Digitaler Freigabe-Link & PDF-Versand an Bauherrn</p>
                        </div>
                        <button wire:click="openDemoModal('bautraeger')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Sprach-Bautagebuch testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-emerald-500/10 rounded-xl border border-emerald-500/20 text-xs text-emerald-300">
                            <span class="text-lg">🎙️</span>
                            <span class="font-medium font-sans">"Heute 4 Mann vor Ort, Abdichtung TG fertiggestellt, 2 Palmen Bitumen verbraucht, Wetter trocken 21 Grad."</span>
                        </div>
                        <div class="p-3.5 bg-slate-900 rounded-xl border border-slate-800 space-y-1.5 text-xs text-slate-300">
                            <span class="text-[10px] text-emerald-400 font-black uppercase">✨ KI-Generierter Bericht:</span>
                            <p class="leading-relaxed">
                                <strong>Ausgeführte Leistungen:</strong> Fertigstellung der Flächenabdichtung Tiefgaragenebene 1 gem. DIN 18533. <strong>Personal:</strong> 4 Fachkräfte vor Ort. <strong>Witterung:</strong> 21°C, heiter, optimale Verarbeitungsbedingungen.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeModuleTab === 'datev')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-amber-400 tracking-wider">Kernmodul 06</span>
                        <h3 class="text-2xl font-black text-white">DATEV-Export & Subunternehmer-Controlling</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Übertragen Sie alle Ausgangs- und Eingangsrechnungen, Nachunternehmer-Rechnungen (§ 13b UStG) und Projektkosten im standardisierten DATEV-Format direkt an Ihren Steuerberater.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ DATEV Buchungsstapel CSV (SKR03 & SKR04)</p>
                            <p class="flex items-center gap-2">✅ Automatische § 13b UStG Steuerschlüssel-Zuordnung</p>
                            <p class="flex items-center gap-2">✅ Rechnungsfreigabe-Workflow & Zahlungsüberwachung</p>
                        </div>
                        <button wire:click="openDemoModal('generalunternehmer')" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            DATEV-Workflow testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                            <span class="font-bold text-xs text-white">📊 DATEV SKR03 Buchungsstapel Export</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-400">STEUERBERATER READY</span>
                        </div>
                        <div class="p-3 bg-slate-900 rounded-xl border border-slate-800 font-mono text-[11px] text-slate-300 space-y-1">
                            <p>Umsatz;S/H;Konto;Gegenkonto;BU;Beleg1;Datum;Text</p>
                            <p class="text-emerald-400">14850.00;S;8400;10000;;RE-2026-041;1408;AR WEG Maxstr</p>
                            <p class="text-amber-400">4200.00;H;3100;70000;19;ER-88412;1408;Subunt. Abdichtung</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>

    </section>

    <!-- ========================================================================= -->
    <!-- 5. INTERAKTIVER ROI & ERSPARNISRECHNER                                    -->
    <!-- ========================================================================= -->
    <section id="rechner" class="py-24 bg-gradient-to-b from-slate-900 to-slate-950 border-t border-slate-800 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-3 mb-16">
                <span class="px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-black uppercase">
                    🧮 Wirtschaftlichkeitsrechner
                </span>
                <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    Berechnen Sie Ihre Ersparnis & Mehrumsatz mit BT Cockpit
                </h2>
                <p class="text-xs sm:text-sm text-slate-400">
                    Passen Sie die Schieberegler an Ihre Betriebsgröße an und sehen Sie den sofortigen Mehrwert:
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center max-w-5xl mx-auto">
                
                <!-- Left: Interactive Sliders (6 cols) -->
                <div class="lg:col-span-6 bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-6">
                    
                    <!-- Slider 1: Baustellen -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-300">Anzahl gleichzeitiger Baustellen:</label>
                            <span class="px-3 py-1 rounded-xl bg-blue-600/20 text-blue-400 font-black text-sm border border-blue-500/30">
                                {{ $roiProjectCount }} Baustellen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiProjectCount" min="1" max="25" step="1" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-500">
                        <div class="flex justify-between text-[10px] text-slate-500">
                            <span>1 Baustelle</span>
                            <span>25 Baustellen</span>
                        </div>
                    </div>

                    <!-- Slider 2: Mitarbeiter -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-300">Mitarbeiter & Bauleiter im Betrieb:</label>
                            <span class="px-3 py-1 rounded-xl bg-indigo-600/20 text-indigo-400 font-black text-sm border border-indigo-500/30">
                                {{ $roiWorkerCount }} Personen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiWorkerCount" min="2" max="40" step="1" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-indigo-500">
                        <div class="flex justify-between text-[10px] text-slate-500">
                            <span>2 Mitarbeiter</span>
                            <span>40 Mitarbeiter</span>
                        </div>
                    </div>

                    <!-- Slider 3: Stundensatz -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-300">Durchschnittlicher Stundensatz:</label>
                            <span class="px-3 py-1 rounded-xl bg-emerald-600/20 text-emerald-400 font-black text-sm border border-emerald-500/30">
                                {{ $roiHourlyRate }} € / Std.
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiHourlyRate" min="45" max="110" step="5" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                        <div class="flex justify-between text-[10px] text-slate-500">
                            <span>45 €</span>
                            <span>110 €</span>
                        </div>
                    </div>

                </div>

                <!-- Right: Calculated Results (6 cols) -->
                <div class="lg:col-span-6 bg-gradient-to-br from-slate-900 via-blue-950/40 to-slate-900 p-6 sm:p-8 rounded-3xl border border-blue-500/30 shadow-2xl space-y-6">
                    
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-blue-400 tracking-wider">Ihr kalkulierter Jahresvorteil</span>
                        <h4 class="text-3xl sm:text-4xl font-black text-white tabular-nums">
                            ~ {{ number_format($this->totalValuePerYear, 0, ',', '.') }} € <span class="text-xs text-slate-400 font-medium">/ Jahr</span>
                        </h4>
                    </div>

                    <div class="space-y-3 text-xs pt-2 border-t border-slate-800">
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-slate-800/80">
                            <span class="text-slate-300">⏱️ Eingesparte Büro- & Doku-Zeit:</span>
                            <span class="font-black text-blue-400 tabular-nums">~ {{ $this->savedHoursPerMonth }} Std. / Monat</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-slate-800/80">
                            <span class="text-slate-300">💶 Bürokratiekosten-Ersparnis:</span>
                            <span class="font-black text-emerald-400 tabular-nums">{{ number_format($this->savedCostPerYear, 0, ',', '.') }} € / Jahr</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-slate-800/80">
                            <span class="text-slate-300">📈 Zusätzliche Nachtragserlöse (VOB/B):</span>
                            <span class="font-black text-indigo-400 tabular-nums">+ {{ number_format($this->additionalSupplementRevenue, 0, ',', '.') }} € / Jahr</span>
                        </div>
                    </div>

                    <button wire:click="openDemoModal" class="w-full py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition cursor-pointer btn-press">
                        Diesen Vorteil jetzt für Ihren Betrieb sichern →
                    </button>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 6. VORHER VS. NACHHER VERGLEICH                                           -->
    <!-- ========================================================================= -->
    <section id="vorteile" class="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto space-y-3 mb-16">
            <span class="px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-black uppercase">
                ⚡ Der direkte Vergleich
            </span>
            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                Vorher vs. Nachher: Ihr Baustellenalltag transformiert
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
            
            <!-- BEFORE CARD -->
            <div class="bg-slate-900/60 p-6 sm:p-8 rounded-3xl border border-rose-500/20 space-y-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center font-black">
                        ✕
                    </div>
                    <div>
                        <h4 class="font-black text-white text-base">Klassischer Baualltag (Vorher)</h4>
                        <span class="text-[11px] text-rose-400">Hoher Zeitverlust & Haftungsrisiko</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-400 font-medium">
                    <li class="flex items-start gap-2">
                        <span class="text-rose-400 font-bold">✕</span>
                        <span>Papier-Bautagebücher werden unvollständig oder erst Tage später ausgefüllt.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-400 font-bold">✕</span>
                        <span>Nachträge nach VOB/B § 2 werden formlos per Mail oder Zuruf verhandelt und nicht vergütet.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-400 font-bold">✕</span>
                        <span>Handschriftliche Handaufmaße mit unklaren Formeln führen zu Streit bei der Abnahme.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-400 font-bold">✕</span>
                        <span>Stundenzettel müssen am Monatsende mühsam abgetippt und korrigiert werden.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-400 font-bold">✕</span>
                        <span>Steuerberater wartet auf Belege; keine DATEV-Schnittstelle.</span>
                    </li>
                </ul>
            </div>

            <!-- AFTER CARD -->
            <div class="bg-gradient-to-br from-slate-900 via-blue-950/40 to-slate-900 p-6 sm:p-8 rounded-3xl border border-blue-500/40 space-y-5 shadow-xl shadow-blue-500/10">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black">
                        ✓
                    </div>
                    <div>
                        <h4 class="font-black text-white text-base">Mit BT Bautechnik Cockpit (Nachher)</h4>
                        <span class="text-[11px] text-emerald-400">100% rechtssicher, digital & rentabel</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-300 font-medium">
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-400 font-bold">✓</span>
                        <span>30-Sekunden Sprachmemo erzeugt das fertige Bautagebuch samt Wetter & Fotos.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-400 font-bold">✓</span>
                        <span>1-Klick Nachtragsangebote mit rechtssicherem VOB/B-Bezug und fertigem PDF.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-400 font-bold">✓</span>
                        <span>Digitales Aufmaßblatt (VOB/C / DIN 18299) mit automatischem Raumabzug.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-400 font-bold">✓</span>
                        <span>Mobile Zeiterfassung (MiLoG-konform) direkt auf der Baustelle per Klick.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-400 font-bold">✓</span>
                        <span>DATEV SKR03/04 Export mit automatischer § 13b UStG Steuerschlüssel-Vergabe.</span>
                    </li>
                </ul>
            </div>

        </div>

    </section>

    <!-- ========================================================================= -->
    <!-- 7. FAQ SECTION                                                            -->
    <!-- ========================================================================= -->
    <section id="faq" class="py-24 bg-slate-900/40 border-t border-slate-800">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center space-y-3 mb-12">
                <span class="px-3 py-1 rounded-full bg-slate-800 border border-slate-700 text-slate-300 text-xs font-black uppercase">
                    💬 Häufige Fragen
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                    Fragen von Bauträgern & Bauunternehmen
                </h2>
            </div>

            <div class="space-y-3 text-xs">
                
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(0)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Ist die Software auf Smartphones und Tablets auf der Baustelle nutzbar?</span>
                        <span class="text-blue-400 text-base">{{ $openFaqIndex === 0 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 0)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-slate-800">
                            Ja! BT Bautechnik Cockpit ist als Progressive Web App (PWA) konzipiert. Es läuft reaktionsschnell auf jedem iPhone, Android-Smartphone, iPad oder Laptop – ohne umständliche App-Store Installation.
                        </p>
                    @endif
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(1)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Wie funktioniert die Nachtragserstellung nach VOB/B § 2?</span>
                        <span class="text-blue-400 text-base">{{ $openFaqIndex === 1 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 1)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-slate-800">
                            Das System unterscheidet automatisch zwischen Leistungsänderungen (§ 2 Abs. 5) und unvorhergesehenen Zusatzleistungen (§ 2 Abs. 6). Sie geben Titel und Menge ein – das System erstellt sofort das unterschriftsreife Nachtragsangebot als PDF mit rechtssicherer Klausulierung.
                        </p>
                    @endif
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(2)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Kann mein Steuerberater die Rechnungen und Kosten direkt importieren?</span>
                        <span class="text-blue-400 text-base">{{ $openFaqIndex === 2 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 2)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-slate-800">
                            Ja. Das System verfügt über eine integrierte DATEV CSV-Schnittstelle nach SKR03 und SKR04 inklusive automatischem Buchungsschlüssel für Nachunternehmer-Rechnungen (§ 13b UStG Bauleistungen).
                        </p>
                    @endif
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(3)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Können wir das System unverbindlich testen?</span>
                        <span class="text-blue-400 text-base">{{ $openFaqIndex === 3 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 3)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-slate-800">
                            Absolut. Klicken Sie einfach auf "Demo anfordern". Wir zeigen Ihnen in 15 Minuten per Videoschalte oder direkt vor Ort, wie Sie das System für Ihre Baustellen einrichten.
                        </p>
                    @endif
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 8. BIG CTA BOTTOM BANNER                                                  -->
    <!-- ========================================================================= -->
    <section class="py-20 relative overflow-hidden">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 p-8 sm:p-12 rounded-3xl border border-blue-500/30 shadow-2xl space-y-6">
                <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    Bereit, Ihre Baustellen & Finanzen auf das nächste Level zu heben?
                </h2>
                <p class="text-xs sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
                    Schließen Sie sich zukunftsorientierten Bauunternehmen & Bauträgern an. Fordern Sie jetzt Ihre unverbindliche Präsentation an.
                </p>
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-3.5 bg-white hover:bg-slate-100 text-slate-950 font-black text-xs rounded-xl shadow-xl transition cursor-pointer btn-press">
                        🚀 Jetzt kostenlose Demo anfordern
                    </button>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto px-6 py-3.5 bg-blue-950/60 hover:bg-blue-900/60 text-white font-bold text-xs rounded-xl border border-blue-500/30 transition">
                        Bestehendes Kundenkonto Login ↗
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 9. FOOTER                                                                 -->
    <!-- ========================================================================= -->
    <footer class="border-t border-slate-900 bg-slate-950 py-12 text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="font-black text-white">BT BAUTECHNIK UG</span>
                <span>• Bauleiter ERP & Digitales Baustellen-Cockpit</span>
            </div>
            <div class="flex items-center gap-6">
                <span>Made with pride in Bavaria 🇩🇪</span>
                <a href="{{ route('login') }}" class="hover:text-white transition">Login</a>
            </div>
        </div>
    </footer>

    <!-- ========================================================================= -->
    <!-- 10. INTERACTIVE LEAD CAPTURE & DEMO REQUEST MODAL                         -->
    <!-- ========================================================================= -->
    @if ($showDemoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-6 relative overflow-hidden">
                
                <button wire:click="closeDemoModal" class="absolute top-5 right-5 text-slate-400 hover:text-white text-xl font-bold cursor-pointer">✕</button>

                @if ($demoSuccess)
                    <div class="py-8 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto font-bold animate-bounce">
                            ✓
                        </div>
                        <h3 class="text-xl font-black text-white">Vielen Dank für Ihre Anfrage!</h3>
                        <p class="text-xs text-slate-300 max-w-sm mx-auto leading-relaxed">
                            Wir haben Ihre Daten erhalten. Unsere Bauleitung wird sich innerhalb kürzester Zeit bei Ihnen für eine persönliche Live-Präsentation melden.
                        </p>
                        <div class="pt-2">
                            <button wire:click="closeDemoModal" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl cursor-pointer">
                                Fertig
                            </button>
                        </div>
                    </div>
                @else
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-blue-400 tracking-wider">Unverbindliche Präsentation</span>
                        <h3 class="text-xl font-black text-white">Live-Demo für Ihr Bauunternehmen</h3>
                        <p class="text-xs text-slate-400">Erfahren Sie, wie BT Cockpit Ihren Baustellenalltag revolutioniert.</p>
                    </div>

                    <form wire:submit="submitDemoRequest" class="space-y-3.5 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Ihr Name / Ansprechpartner *</label>
                            <input wire:model="demoName" type="text" placeholder="z. B. Dipl.-Ing. Markus Huber" class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl p-2.5 focus:border-blue-500 focus:outline-none" required>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Unternehmen / Firma *</label>
                            <input wire:model="demoCompany" type="text" placeholder="z. B. Huber Bau & Sanierung GmbH" class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl p-2.5 focus:border-blue-500 focus:outline-none" required>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">E-Mail-Adresse *</label>
                                <input wire:model="demoEmail" type="email" placeholder="m.huber@huberbau.de" class="w-full bg-slate-950 border border-slate-800 text-white font-semibold rounded-xl p-2.5 focus:border-blue-500 focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Telefon / Mobil *</label>
                                <input wire:model="demoPhone" type="tel" placeholder="0171 1234567" class="w-full bg-slate-950 border border-slate-800 text-white font-semibold rounded-xl p-2.5 focus:border-blue-500 focus:outline-none" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Ihr Unternehmensschwerpunkt</label>
                                <select wire:model="demoTrade" class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl p-2.5 focus:border-blue-500 focus:outline-none">
                                    <option value="bautraeger">Bauträger / Entwickler</option>
                                    <option value="generalunternehmer">Generalübernehmer / GU</option>
                                    <option value="sanierung_abdichtung">Sanierung & Abdichtung</option>
                                    <option value="hoch_tiefbau">Hoch- & Tiefbau</option>
                                    <option value="handwerk">Fachhandwerksbetrieb</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Baustellen pro Jahr</label>
                                <select wire:model="demoProjectCount" class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl p-2.5 focus:border-blue-500 focus:outline-none">
                                    <option value="1-3">1 – 3 Bauvorhaben</option>
                                    <option value="4-10">4 – 10 Bauvorhaben</option>
                                    <option value="10+">Über 10 Bauvorhaben</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Nachricht / Schwerpunkte (optional)</label>
                            <textarea wire:model="demoMessage" rows="2" placeholder="Welche Module interessieren Sie besonders (z.B. VOB-Nachträge, Aufmaße, KI-Bautagebuch)?" class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl p-2.5 focus:border-blue-500 focus:outline-none"></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="text-xs text-emerald-400 hover:underline flex items-center gap-1 font-bold">
                                <span>💬 Lieber per WhatsApp anfragen</span>
                            </a>

                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-500 hover:from-blue-500 hover:to-cyan-400 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-500/20 cursor-pointer btn-press">
                                Demo-Termin vereinbaren →
                            </button>
                        </div>
                    </form>
                @endif

            </div>
        </div>
    @endif

</div>
