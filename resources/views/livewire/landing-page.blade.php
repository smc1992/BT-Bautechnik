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

<div class="min-h-screen bg-[#060a12] text-slate-100 font-sans selection:bg-blue-600 selection:text-white relative overflow-x-hidden">
    
    <!-- Ambient Glass Background Orbs -->
    <div class="fixed top-0 left-1/4 w-[600px] h-[500px] bg-blue-600/10 rounded-full blur-[140px] pointer-events-none -z-10"></div>
    <div class="fixed top-1/3 right-10 w-[500px] h-[500px] bg-amber-500/8 rounded-full blur-[160px] pointer-events-none -z-10"></div>
    <div class="fixed bottom-10 left-10 w-[600px] h-[600px] bg-indigo-600/10 rounded-full blur-[160px] pointer-events-none -z-10"></div>

    <!-- ========================================================================= -->
    <!-- 1. STICKY TOP NAVBAR (FROSTED GLASS EFFECT)                               -->
    <!-- ========================================================================= -->
    <header class="sticky top-0 z-40 bg-[#060a12]/80 backdrop-blur-xl border-b border-white/10 shadow-xl transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Real Brand Logo Component -->
            <a href="/" class="hover:opacity-95 transition group">
                <x-brand-logo size="default" />
            </a>

            <!-- Nav Links (Desktop) -->
            <nav class="hidden lg:flex items-center gap-8 text-xs font-bold text-slate-300">
                <a href="#story" class="hover:text-amber-400 transition flex items-center gap-1">
                    <span>🧱 Praxis-Story</span>
                </a>
                <a href="#module" class="hover:text-blue-400 transition">Module & VOB</a>
                <a href="#rechner" class="hover:text-emerald-400 transition flex items-center gap-1.5 font-extrabold text-emerald-400">
                    <span>🧮 Ersparnisrechner</span>
                </a>
                <a href="#vorteile" class="hover:text-white transition">Vorher / Nachher</a>
                <a href="#faq" class="hover:text-white transition">FAQ</a>
            </nav>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-4 py-2.5 bg-slate-900/90 hover:bg-slate-800 text-white font-bold text-xs rounded-xl border border-white/15 backdrop-blur-md shadow-xs transition flex items-center gap-2">
                        <span>📊 Zum Cockpit</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="px-3.5 py-2 text-slate-300 hover:text-white font-bold text-xs transition">
                        Login ↗
                    </a>
                @endauth

                <button wire:click="openDemoModal" class="px-4 sm:px-5 py-2.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-amber-500/30 transition cursor-pointer flex items-center gap-1.5 btn-press border border-white/15">
                    <span>✨ Demo anfordern</span>
                </button>
            </div>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- 2. HERO SECTION WITH IMMERSIVE STORYTELLING & GLASSPHISM                  -->
    <!-- ========================================================================= -->
    <section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden">
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="text-center max-w-4xl mx-auto space-y-6">
                
                <!-- Storytelling Origin Pill -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-900/80 backdrop-blur-md border border-amber-500/30 text-amber-300 text-xs font-black shadow-inner">
                    <span class="flex h-2 w-2 rounded-full bg-amber-400 animate-ping"></span>
                    <span>🏗️ Entwickelt von der BT Bautechnik UG – Aus der Baupraxis für echte Bauprofis</span>
                </div>

                <!-- Main Hero Headline -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-tight sm:leading-tight">
                    Die Bauleiter-Software,<br>
                    <span class="bg-gradient-to-r from-blue-400 via-indigo-300 to-amber-300 bg-clip-text text-transparent">
                        die direkt auf der Baustelle geboren wurde.
                    </span>
                </h1>

                <!-- Subtitle with Authentic Builder Tone -->
                <p class="text-sm sm:text-lg text-slate-300 font-medium max-w-3xl mx-auto leading-relaxed">
                    Wir sind selbst Bauunternehmer in Bayern. Wir kennen den Regen, den Zeitdruck und verlorene Nachträge nach VOB/B § 2. Deshalb haben wir das <strong>BT Bautechnik Cockpit</strong> gebaut: Alle Baustellen, 360° Kunden-Zentrale, digitale Aufmaße (VOB/C), KI-Bautagebücher und DATEV-Finanzen in Sekunden steuern.
                </p>

                <!-- Hero CTAs -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-black text-sm rounded-2xl shadow-xl shadow-blue-500/25 hover:shadow-amber-500/40 transition cursor-pointer flex items-center justify-center gap-2.5 btn-press border border-white/20">
                        <span>🚀 Kostenlose Live-Demo anfordern</span>
                        <span>→</span>
                    </button>

                    <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="w-full sm:w-auto px-6 py-4 bg-slate-900/80 hover:bg-slate-800 text-slate-200 hover:text-white font-bold text-sm rounded-2xl border border-white/10 hover:border-amber-400/40 transition flex items-center justify-center gap-2 backdrop-blur-md">
                        <span>💬 Direkt per WhatsApp anfragen</span>
                    </a>
                </div>

                <!-- Glassmorphism Trust Badges Strip -->
                <div class="pt-8 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                    <div class="bg-slate-900/50 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 hover:border-blue-400/40 transition shadow-sm">
                        <span class="text-blue-400 font-black block">⚖️ VOB/B § 2 & VOB/C</span>
                        <span class="text-slate-400 text-[11px]">Rechtssichere Nachträge & Aufmaße</span>
                    </div>
                    <div class="bg-slate-900/50 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 hover:border-amber-400/40 transition shadow-sm">
                        <span class="text-amber-400 font-black block">🎙️ KI-Sprachmemo</span>
                        <span class="text-slate-400 text-[11px]">Bautagebuch in 30 Sek. vor Ort</span>
                    </div>
                    <div class="bg-slate-900/50 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 hover:border-emerald-400/40 transition shadow-sm">
                        <span class="text-emerald-400 font-black block">📊 DATEV SKR03/04</span>
                        <span class="text-slate-400 text-[11px]">Buchungsstapel für Steuerberater</span>
                    </div>
                    <div class="bg-slate-900/50 backdrop-blur-md p-3.5 rounded-2xl border border-white/10 hover:border-cyan-400/40 transition shadow-sm">
                        <span class="text-cyan-400 font-black block">📱 Mobile PWA</span>
                        <span class="text-slate-400 text-[11px]">Für Smartphone & Baustellen-Tablet</span>
                    </div>
                </div>

            </div>

            <!-- Interactive Hero Glassmorphism Cockpit Preview Card -->
            <div class="mt-14 max-w-5xl mx-auto rounded-3xl p-3 sm:p-4 bg-gradient-to-b from-blue-500/20 via-slate-800/40 to-amber-500/10 border border-white/15 shadow-2xl backdrop-blur-2xl">
                <div class="bg-slate-950/90 rounded-2xl border border-white/10 overflow-hidden shadow-inner">
                    
                    <!-- Window bar with real company identification -->
                    <div class="px-5 py-3.5 bg-slate-900/90 border-b border-white/10 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-rose-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500/80"></span>
                            <span class="text-xs text-slate-300 font-mono ml-2">app.bautechnik-bt.de / bt-bauleiter-cockpit / live</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                BT BAUTECHNIK UG
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                LIVE COCKPIT
                            </span>
                        </div>
                    </div>

                    <!-- Mockup Body -->
                    <div class="p-5 sm:p-7 space-y-6">
                        
                        <!-- Project Banner Header -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div>
                                <span class="text-[10px] font-mono text-amber-400 font-bold uppercase tracking-wider">BAUVORHABEN #2026-081</span>
                                <h3 class="text-base sm:text-lg font-black text-white">WEG Maximilianstraße 44 – Tiefgaragenabdichtung & Sanierung</h3>
                                <p class="text-xs text-slate-400">Auftraggeber / Bauherr: Hausverwaltung Müller & Partner GmbH (Nürnberg)</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 rounded-xl bg-blue-600/20 text-blue-400 border border-blue-500/30 font-bold text-xs">
                                    KW 32 – KW 38
                                </span>
                                <span class="px-3 py-1 rounded-xl bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 font-bold text-xs">
                                    Im Plan
                                </span>
                            </div>
                        </div>

                        <!-- Progress & Budget Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="bg-slate-900/60 backdrop-blur-md p-4 rounded-xl border border-white/10">
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Geplantes Budget</span>
                                <p class="text-base font-black text-white mt-0.5">85.000,00 €</p>
                                <div class="w-full bg-slate-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                    <div class="bg-blue-500 h-full w-[65%]"></div>
                                </div>
                            </div>
                            <div class="bg-slate-900/60 backdrop-blur-md p-4 rounded-xl border border-white/10">
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Nachtragsvolumen (VOB/B)</span>
                                <p class="text-base font-black text-amber-400 mt-0.5">+ 12.450,00 €</p>
                                <span class="text-[10px] text-emerald-400 font-semibold">3 freigegeben, 1 in Prüfung</span>
                            </div>
                            <div class="bg-slate-900/60 backdrop-blur-md p-4 rounded-xl border border-white/10">
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Aufmaßstand (VOB/C)</span>
                                <p class="text-base font-black text-cyan-400 mt-0.5">620 m² / 750 m²</p>
                                <span class="text-[10px] text-slate-400 font-semibold">82% fertiggestellt</span>
                            </div>
                        </div>

                        <!-- Mini Sub Action Bar -->
                        <div class="p-3 bg-slate-900/80 rounded-xl border border-white/10 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-2 text-slate-300">
                                <span>🎙️ Bautagesbericht heute per Sprachmemo erfasst</span>
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
    <!-- 3. DIE STORY: VON DER BRANCHE FÜR DIE BRANCHE                              -->
    <!-- ========================================================================= -->
    <section id="story" class="py-24 bg-slate-950/60 border-y border-white/10 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <div class="lg:col-span-6 space-y-6">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-black uppercase">
                        <span>🧱 Aus der Praxis für die Baupraxis</span>
                    </div>

                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight">
                        Wir bauen selbst. Wir kennen den Baustellenalltag bis ins Detail.
                    </h2>

                    <div class="space-y-4 text-sm text-slate-300 leading-relaxed font-medium">
                        <p>
                            Hinter dieser Software steht kein anonymer IT-Konzern, sondern die <strong>BT Bautechnik UG (haftungsbeschränkt)</strong> – ein aktives deutsches Bauunternehmen mit Sitz in Berching (Bayern).
                        </p>
                        <p>
                            Jede Funktion in diesem Cockpit wurde aus einem konkreten Baustellen-Problem entwickelt:
                        </p>
                        <ul class="space-y-3 list-none pl-0">
                            <li class="flex items-start gap-3 p-3 bg-slate-900/50 rounded-xl border border-white/5">
                                <span class="text-amber-400 font-bold text-base">⚠️</span>
                                <span><strong>VOB-Nachträge gingen verloren:</strong> Weil Poliere vor Ort keine Zeit hatten, Angebote am Schreibtisch zu schreiben. Heute erstellen wir Nachtragsangebote nach § 2 VOB/B mit 2 Klicks vor Ort.</span>
                            </li>
                            <li class="flex items-start gap-3 p-3 bg-slate-900/50 rounded-xl border border-white/5">
                                <span class="text-blue-400 font-bold text-base">🎙️</span>
                                <span><strong>Zähe Bautagebücher:</strong> Nach einem 10-Stunden-Tag tippt kein Bauleiter Berichte. Ein 30-Sekunden Sprachmemo genügt und die KI formuliert den fertigen Bericht inklusive Wetter & Fotos.</span>
                            </li>
                            <li class="flex items-start gap-3 p-3 bg-slate-900/50 rounded-xl border border-white/5">
                                <span class="text-emerald-400 font-bold text-base">📊</span>
                                <span><strong>Abrechnungs-Chaos beim Steuerberater:</strong> Statt Belege per Post zu schicken, exportiert das System fertige DATEV-Buchungsstapel (SKR03/SKR04) inkl. § 13b UStG Nachunternehmer-Zuordnung.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-2">
                        <button wire:click="openDemoModal" class="px-6 py-3.5 bg-gradient-to-r from-blue-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition cursor-pointer btn-press border border-white/10">
                            Lernen Sie die BT Bauleiter-Suite unverbindlich kennen →
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-900/60 backdrop-blur-md p-6 rounded-3xl border border-white/10 space-y-3 hover:border-amber-400/40 transition shadow-sm">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-2xl font-bold">
                            🧱
                        </div>
                        <h4 class="font-black text-white text-base">Echtes Bauunternehmen</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Keine theoretische Spielerei: Wir nutzen die Software täglich auf unseren eigenen Baustellen in Bayern.
                        </p>
                    </div>

                    <div class="bg-slate-900/60 backdrop-blur-md p-6 rounded-3xl border border-white/10 space-y-3 hover:border-blue-400/40 transition shadow-sm">
                        <div class="w-12 h-12 rounded-2xl bg-blue-600/20 text-blue-400 flex items-center justify-center text-2xl font-bold">
                            📑
                        </div>
                        <h4 class="font-black text-white text-base">VOB/B Nachtragsautomatik</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Nachträge nach § 2 Abs. 5/6 sofort mit fertigem Briefkopf und rechtssicherem PDF versenden.
                        </p>
                    </div>

                    <div class="bg-slate-900/60 backdrop-blur-md p-6 rounded-3xl border border-white/10 space-y-3 hover:border-indigo-400/40 transition shadow-sm">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center text-2xl font-bold">
                            👥
                        </div>
                        <h4 class="font-black text-white text-base">360° Kunden-Zentrale</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Der Bauherr als Dreh- und Angelpunkt: Baustellen, Aufmaße und Rechnungen direkt steuern.
                        </p>
                    </div>

                    <div class="bg-slate-900/60 backdrop-blur-md p-6 rounded-3xl border border-white/10 space-y-3 hover:border-emerald-400/40 transition shadow-sm">
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
    <!-- 4. INTERAKTIVER MODULE EXPLORER (GLASS PANELS & REAL PREVIEWS)            -->
    <!-- ========================================================================= -->
    <section id="module" class="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto space-y-3 mb-12">
            <span class="px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-black uppercase">
                🚀 Die All-in-One ERP Suite
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
            <button wire:click="$set('activeModuleTab', 'cockpit')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'cockpit' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30 border border-blue-400/40' : 'bg-slate-900/70 text-slate-400 hover:text-white border border-white/10 backdrop-blur-md' }}">
                <span>🏗️ Baustellen-Cockpit</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'contacts360')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'contacts360' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/30 border border-indigo-400/40' : 'bg-slate-900/70 text-slate-400 hover:text-white border border-white/10 backdrop-blur-md' }}">
                <span>👥 360° Kunden-Zentrale</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'supplements')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'supplements' ? 'bg-amber-600 text-white shadow-lg shadow-amber-500/30 border border-amber-400/40' : 'bg-slate-900/70 text-slate-400 hover:text-white border border-white/10 backdrop-blur-md' }}">
                <span>📑 VOB/B Nachträge</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'measurements')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'measurements' ? 'bg-cyan-600 text-white shadow-lg shadow-cyan-500/30 border border-cyan-400/40' : 'bg-slate-900/70 text-slate-400 hover:text-white border border-white/10 backdrop-blur-md' }}">
                <span>📐 VOB/C Aufmaßblatt</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'dailylogs')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'dailylogs' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/30 border border-emerald-400/40' : 'bg-slate-900/70 text-slate-400 hover:text-white border border-white/10 backdrop-blur-md' }}">
                <span>🎙️ KI-Bautagebuch</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'datev')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'datev' ? 'bg-purple-600 text-white shadow-lg shadow-purple-500/30 border border-purple-400/40' : 'bg-slate-900/70 text-slate-400 hover:text-white border border-white/10 backdrop-blur-md' }}">
                <span>📊 DATEV & Finanzen</span>
            </button>
        </div>

        <!-- Interactive Module Showcase Screen -->
        <div class="bg-slate-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl">
            
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

                    <div class="lg:col-span-7 bg-slate-950/80 p-5 rounded-2xl border border-white/10 space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-white/10">
                            <span class="font-bold text-xs text-white">🏢 Projekt: Neubau Wohnanlage Regensburg</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-400">KW 28 – KW 42</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="bg-slate-900/80 p-2.5 rounded-xl border border-white/5">
                                <span class="text-[9px] text-slate-400 block font-bold">BUDGET SOLL</span>
                                <span class="font-black text-white text-sm">120.000 €</span>
                            </div>
                            <div class="bg-slate-900/80 p-2.5 rounded-xl border border-white/5">
                                <span class="text-[9px] text-slate-400 block font-bold">IST-KOSTEN</span>
                                <span class="font-black text-blue-400 text-sm">74.500 €</span>
                            </div>
                            <div class="bg-slate-900/80 p-2.5 rounded-xl border border-white/5">
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

                    <div class="lg:col-span-7 bg-slate-950/80 p-5 rounded-2xl border border-white/10 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-xs text-white">👤 Kunde: Hausverwaltung Schmidt & Co.</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/20 text-indigo-400">4 Baustellen</span>
                        </div>
                        <div class="p-3 bg-slate-900/80 rounded-xl border border-white/10 flex flex-wrap gap-2 text-xs">
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
                        <span class="text-xs font-black uppercase text-amber-400 tracking-wider">Kernmodul 03</span>
                        <h3 class="text-2xl font-black text-white">VOB/B Nachtragsmanagement (§ 2)</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Schluss mit vergessenen oder abgewiesenen Nachträgen. Erfassen Sie Mehrleistungen nach § 2 Abs. 5 (Leistungsänderung) oder § 2 Abs. 6 (Zusätzliche Leistung) sofort mit rechtssicherem PDF-Export.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ Automatische VOB-Begründung & Fristüberwachung</p>
                            <p class="flex items-center gap-2">✅ PDF-Nachtragsangebot mit rechtssicherem VOB-Briefkopf</p>
                            <p class="flex items-center gap-2">✅ Status: Eingereicht, Geprüft, Beauftragt, Abgerechnet</p>
                        </div>
                        <button wire:click="openDemoModal('sanierung_abdichtung')" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Nachtragsmodul ansehen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950/80 p-5 rounded-2xl border border-white/10 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-white/10">
                            <span class="font-bold text-xs text-white">📑 Nachtragsangebot NT-03 nach VOB/B § 2 Abs. 5</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300">BEAUFTRAGT</span>
                        </div>
                        <p class="text-xs text-slate-300 font-medium">
                            Titel: Zusätzliche Hohlkehlenabdichtung & Bitumen-Dickbeschichtung Rampe UG 2
                        </p>
                        <div class="flex justify-between items-center p-3 bg-slate-900/80 rounded-xl border border-white/10 text-xs">
                            <span class="text-slate-400">Nachtragssumme Netto:</span>
                            <span class="text-base font-black text-amber-300">4.850,00 €</span>
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

                    <div class="lg:col-span-7 bg-slate-950/80 p-5 rounded-2xl border border-white/10 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-white/10">
                            <span class="font-bold text-xs text-white">📐 Aufmaßblatt AM-2026-004 (Bodenplatte TG)</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-cyan-500/20 text-cyan-400">DIN 18299</span>
                        </div>
                        <div class="space-y-2 text-xs font-mono">
                            <div class="p-2.5 bg-slate-900/80 rounded-lg flex justify-between border border-white/5">
                                <span>Fläche Achse 1-4: 18.50 × 8.20</span>
                                <span class="font-bold text-cyan-300">= 151,70 m²</span>
                            </div>
                            <div class="p-2.5 bg-slate-900/80 rounded-lg flex justify-between text-rose-300 border border-white/5">
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

                    <div class="lg:col-span-7 bg-slate-950/80 p-5 rounded-2xl border border-white/10 space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-emerald-500/10 rounded-xl border border-emerald-500/20 text-xs text-emerald-300">
                            <span class="text-lg">🎙️</span>
                            <span class="font-medium font-sans">"Heute 4 Mann vor Ort, Abdichtung TG fertiggestellt, 2 Palmen Bitumen verbraucht, Wetter trocken 21 Grad."</span>
                        </div>
                        <div class="p-3.5 bg-slate-900/80 rounded-xl border border-white/10 space-y-1.5 text-xs text-slate-300">
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
                        <span class="text-xs font-black uppercase text-purple-400 tracking-wider">Kernmodul 06</span>
                        <h3 class="text-2xl font-black text-white">DATEV-Export & Subunternehmer-Controlling</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Übertragen Sie alle Ausgangs- und Eingangsrechnungen, Nachunternehmer-Rechnungen (§ 13b UStG) und Projektkosten im standardisierten DATEV-Format direkt an Ihren Steuerberater.
                        </p>
                        <div class="space-y-2 text-xs font-semibold text-slate-300">
                            <p class="flex items-center gap-2">✅ DATEV Buchungsstapel CSV (SKR03 & SKR04)</p>
                            <p class="flex items-center gap-2">✅ Automatische § 13b UStG Steuerschlüssel-Zuordnung</p>
                            <p class="flex items-center gap-2">✅ Rechnungsfreigabe-Workflow & Zahlungsüberwachung</p>
                        </div>
                        <button wire:click="openDemoModal('generalunternehmer')" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            DATEV-Workflow testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-950/80 p-5 rounded-2xl border border-white/10 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-white/10">
                            <span class="font-bold text-xs text-white">📊 DATEV SKR03 Buchungsstapel Export</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-purple-500/20 text-purple-300">STEUERBERATER READY</span>
                        </div>
                        <div class="p-3 bg-slate-900/80 rounded-xl border border-white/10 font-mono text-[11px] text-slate-300 space-y-1">
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
    <section id="rechner" class="py-24 bg-gradient-to-b from-slate-950 via-[#0a101d] to-slate-950 border-t border-white/10 relative">
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
                <div class="lg:col-span-6 bg-slate-900/60 backdrop-blur-xl p-6 sm:p-8 rounded-3xl border border-white/10 space-y-6">
                    
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
                            <span class="px-3 py-1 rounded-xl bg-amber-500/20 text-amber-300 font-black text-sm border border-amber-500/30">
                                {{ $roiHourlyRate }} € / Std.
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiHourlyRate" min="45" max="110" step="5" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-amber-500">
                        <div class="flex justify-between text-[10px] text-slate-500">
                            <span>45 €</span>
                            <span>110 €</span>
                        </div>
                    </div>

                </div>

                <!-- Right: Calculated Results (6 cols) -->
                <div class="lg:col-span-6 bg-gradient-to-br from-slate-900/90 via-blue-950/40 to-slate-900/90 backdrop-blur-2xl p-6 sm:p-8 rounded-3xl border border-blue-500/30 shadow-2xl space-y-6">
                    
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-amber-400 tracking-wider">Ihr kalkulierter Jahresvorteil</span>
                        <h4 class="text-3xl sm:text-4xl font-black text-white tabular-nums">
                            ~ {{ number_format($this->totalValuePerYear, 0, ',', '.') }} € <span class="text-xs text-slate-400 font-medium">/ Jahr</span>
                        </h4>
                    </div>

                    <div class="space-y-3 text-xs pt-2 border-t border-white/10">
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-white/5">
                            <span class="text-slate-300">⏱️ Eingesparte Büro- & Doku-Zeit:</span>
                            <span class="font-black text-blue-400 tabular-nums">~ {{ $this->savedHoursPerMonth }} Std. / Monat</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-white/5">
                            <span class="text-slate-300">💶 Bürokratiekosten-Ersparnis:</span>
                            <span class="font-black text-emerald-400 tabular-nums">{{ number_format($this->savedCostPerYear, 0, ',', '.') }} € / Jahr</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-slate-950/80 rounded-xl border border-white/5">
                            <span class="text-slate-300">📈 Zusätzliche Nachtragserlöse (VOB/B):</span>
                            <span class="font-black text-amber-400 tabular-nums">+ {{ number_format($this->additionalSupplementRevenue, 0, ',', '.') }} € / Jahr</span>
                        </div>
                    </div>

                    <button wire:click="openDemoModal" class="w-full py-3.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition cursor-pointer btn-press border border-white/10">
                        Diesen Vorteil jetzt für Ihren Betrieb sichern →
                    </button>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 6. VORHER VS. NACHHER VERGLEICH (SPLIT GLASS MATRIX)                      -->
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
            <div class="bg-slate-900/40 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-rose-500/20 space-y-5">
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
            <div class="bg-gradient-to-br from-slate-900/90 via-blue-950/40 to-slate-900/90 backdrop-blur-2xl p-6 sm:p-8 rounded-3xl border border-blue-500/40 space-y-5 shadow-xl shadow-blue-500/10">
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
    <section id="faq" class="py-24 bg-slate-950/60 border-t border-white/10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center space-y-3 mb-12">
                <span class="px-3 py-1 rounded-full bg-slate-900 border border-white/10 text-slate-300 text-xs font-black uppercase">
                    💬 Häufige Fragen
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                    Fragen von Bauträgern & Bauunternehmen
                </h2>
            </div>

            <div class="space-y-3 text-xs">
                
                <div class="bg-slate-900/60 backdrop-blur-md border border-white/10 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(0)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Ist die Software auf Smartphones und Tablets auf der Baustelle nutzbar?</span>
                        <span class="text-amber-400 text-base">{{ $openFaqIndex === 0 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 0)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-white/10">
                            Ja! BT Bautechnik Cockpit ist als Progressive Web App (PWA) konzipiert. Es läuft reaktionsschnell auf jedem iPhone, Android-Smartphone, iPad oder Laptop – ohne umständliche App-Store Installation.
                        </p>
                    @endif
                </div>

                <div class="bg-slate-900/60 backdrop-blur-md border border-white/10 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(1)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Wie funktioniert die Nachtragserstellung nach VOB/B § 2?</span>
                        <span class="text-amber-400 text-base">{{ $openFaqIndex === 1 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 1)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-white/10">
                            Das System unterscheidet automatisch zwischen Leistungsänderungen (§ 2 Abs. 5) und unvorhergesehenen Zusatzleistungen (§ 2 Abs. 6). Sie geben Titel und Menge ein – das System erstellt sofort das unterschriftsreife Nachtragsangebot als PDF mit rechtssicherer Klausulierung.
                        </p>
                    @endif
                </div>

                <div class="bg-slate-900/60 backdrop-blur-md border border-white/10 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(2)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Kann mein Steuerberater die Rechnungen und Kosten direkt importieren?</span>
                        <span class="text-amber-400 text-base">{{ $openFaqIndex === 2 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 2)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-white/10">
                            Ja. Das System verfügt über eine integrierte DATEV CSV-Schnittstelle nach SKR03 und SKR04 inklusive automatischem Buchungsschlüssel für Nachunternehmer-Rechnungen (§ 13b UStG Bauleistungen).
                        </p>
                    @endif
                </div>

                <div class="bg-slate-900/60 backdrop-blur-md border border-white/10 rounded-2xl p-4 transition">
                    <button wire:click="toggleFaq(3)" class="w-full flex justify-between items-center text-left font-black text-white text-sm cursor-pointer">
                        <span>Können wir das System unverbindlich testen?</span>
                        <span class="text-amber-400 text-base">{{ $openFaqIndex === 3 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 3)
                        <p class="mt-3 text-slate-300 leading-relaxed pt-2 border-t border-white/10">
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
            <div class="bg-gradient-to-r from-blue-950/80 via-slate-900/90 to-amber-950/80 backdrop-blur-2xl p-8 sm:p-12 rounded-3xl border border-white/15 shadow-2xl space-y-6">
                <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    Bereit, Ihre Baustellen & Finanzen auf das nächste Level zu heben?
                </h2>
                <p class="text-xs sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
                    Schließen Sie sich zukunftsorientierten Bauunternehmen & Bauträgern an. Fordern Sie jetzt Ihre unverbindliche Präsentation an.
                </p>
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-black text-xs rounded-xl shadow-xl transition cursor-pointer btn-press border border-white/20">
                        🚀 Jetzt kostenlose Demo anfordern
                    </button>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto px-6 py-3.5 bg-slate-900/90 hover:bg-slate-800 text-white font-bold text-xs rounded-xl border border-white/15 transition">
                        Bestehendes Kundenkonto Login ↗
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 9. FOOTER WITH REAL LEGAL ENTITY & COMPLIANCE LINKS                       -->
    <!-- ========================================================================= -->
    <footer class="border-t border-white/10 bg-[#04070d] py-12 text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            
            <!-- Real Brand Identity in Footer -->
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <x-brand-logo size="small" />
                <span class="text-slate-600 hidden sm:inline">•</span>
                <span class="text-slate-400 text-center sm:text-left">
                    BT Bautechnik UG (haftungsbeschränkt) | Berching, Bayern 🇩🇪
                </span>
            </div>

            <!-- Legal Pages Links -->
            <div class="flex flex-wrap items-center justify-center gap-6 font-bold text-slate-300">
                <a href="/impressum" class="hover:text-amber-400 transition">Impressum</a>
                <a href="/datenschutz" class="hover:text-amber-400 transition">Datenschutz</a>
                <a href="/agb" class="hover:text-amber-400 transition">AGB</a>
                <a href="{{ route('login') }}" class="hover:text-white transition text-blue-400">Kunden-Login ↗</a>
            </div>
        </div>
    </footer>

    <!-- ========================================================================= -->
    <!-- 10. INTERACTIVE LEAD CAPTURE & DEMO REQUEST MODAL                         -->
    <!-- ========================================================================= -->
    @if ($showDemoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-xl">
            <div class="bg-slate-900/95 border border-white/15 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-6 relative overflow-hidden backdrop-blur-2xl">
                
                <button wire:click="closeDemoModal" class="absolute top-5 right-5 text-slate-400 hover:text-white text-xl font-bold cursor-pointer">✕</button>

                @if ($demoSuccess)
                    <div class="py-8 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto font-bold animate-bounce">
                            ✓
                        </div>
                        <h3 class="text-xl font-black text-white">Vielen Dank für Ihre Anfrage!</h3>
                        <p class="text-xs text-slate-300 max-w-sm mx-auto leading-relaxed">
                            Wir haben Ihre Daten erhalten. Unsere Bauleitung der <strong>BT Bautechnik UG</strong> wird sich innerhalb kürzester Zeit bei Ihnen für eine persönliche Live-Präsentation melden.
                        </p>
                        <div class="pt-2">
                            <button wire:click="closeDemoModal" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl cursor-pointer">
                                Fertig
                            </button>
                        </div>
                    </div>
                @else
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-amber-400 tracking-wider">Unverbindliche Präsentation</span>
                        <h3 class="text-xl font-black text-white">Live-Demo für Ihr Bauunternehmen</h3>
                        <p class="text-xs text-slate-400">Erfahren Sie, wie BT Cockpit Ihren Baustellenalltag revolutioniert.</p>
                    </div>

                    <form wire:submit="submitDemoRequest" class="space-y-3.5 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Ihr Name / Ansprechpartner *</label>
                            <input wire:model="demoName" type="text" placeholder="z. B. Dipl.-Ing. Markus Huber" class="w-full bg-slate-950 border border-white/15 text-white font-bold rounded-xl p-2.5 focus:border-amber-400 focus:outline-none" required>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Unternehmen / Firma *</label>
                            <input wire:model="demoCompany" type="text" placeholder="z. B. Huber Bau & Sanierung GmbH" class="w-full bg-slate-950 border border-white/15 text-white font-bold rounded-xl p-2.5 focus:border-amber-400 focus:outline-none" required>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">E-Mail-Adresse *</label>
                                <input wire:model="demoEmail" type="email" placeholder="m.huber@huberbau.de" class="w-full bg-slate-950 border border-white/15 text-white font-semibold rounded-xl p-2.5 focus:border-amber-400 focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Telefon / Mobil *</label>
                                <input wire:model="demoPhone" type="tel" placeholder="0171 1234567" class="w-full bg-slate-950 border border-white/15 text-white font-semibold rounded-xl p-2.5 focus:border-amber-400 focus:outline-none" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Ihr Unternehmensschwerpunkt</label>
                                <select wire:model="demoTrade" class="w-full bg-slate-950 border border-white/15 text-white font-bold rounded-xl p-2.5 focus:border-amber-400 focus:outline-none">
                                    <option value="bautraeger">Bauträger / Entwickler</option>
                                    <option value="generalunternehmer">Generalübernehmer / GU</option>
                                    <option value="sanierung_abdichtung">Sanierung & Abdichtung</option>
                                    <option value="hoch_tiefbau">Hoch- & Tiefbau</option>
                                    <option value="handwerk">Fachhandwerksbetrieb</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-300 mb-1">Baustellen pro Jahr</label>
                                <select wire:model="demoProjectCount" class="w-full bg-slate-950 border border-white/15 text-white font-bold rounded-xl p-2.5 focus:border-amber-400 focus:outline-none">
                                    <option value="1-3">1 – 3 Bauvorhaben</option>
                                    <option value="4-10">4 – 10 Bauvorhaben</option>
                                    <option value="10+">Über 10 Bauvorhaben</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">Nachricht / Schwerpunkte (optional)</label>
                            <textarea wire:model="demoMessage" rows="2" placeholder="Welche Module interessieren Sie besonders (z.B. VOB-Nachträge, Aufmaße, KI-Bautagebuch)?" class="w-full bg-slate-950 border border-white/15 text-white rounded-xl p-2.5 focus:border-amber-400 focus:outline-none"></textarea>
                        </div>

                        <div class="pt-3 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="text-xs text-emerald-400 hover:underline flex items-center gap-1 font-bold">
                                <span>💬 Lieber per WhatsApp anfragen</span>
                            </a>

                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-500/20 cursor-pointer btn-press border border-white/15">
                                Demo-Termin vereinbaren →
                            </button>
                        </div>
                    </form>
                @endif

            </div>
        </div>
    @endif

</div>
