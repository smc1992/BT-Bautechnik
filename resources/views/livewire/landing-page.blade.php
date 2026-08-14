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

<div class="min-h-screen bg-slate-50 text-slate-900 font-sans selection:bg-blue-600 selection:text-white relative overflow-x-hidden">
    
    <!-- Subtle Blueprint Geometric Background Accent -->
    <div class="absolute inset-0 bg-[radial-gradient(#e2e8f0_1px,transparent_1px)] [background-size:24px_24px] pointer-events-none opacity-60"></div>
    <div class="fixed top-0 left-1/4 w-[600px] h-[500px] bg-blue-100/60 rounded-full blur-[140px] pointer-events-none -z-10"></div>
    <div class="fixed top-1/3 right-10 w-[500px] h-[500px] bg-amber-100/50 rounded-full blur-[160px] pointer-events-none -z-10"></div>

    <!-- ========================================================================= -->
    <!-- 1. STICKY TOP NAVBAR (CLEAN WHITE / FROSTED GLASS)                        -->
    <!-- ========================================================================= -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-xl border-b border-slate-200/90 shadow-xs transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Real Brand Logo Component -->
            <a href="/" class="hover:opacity-95 transition group">
                <x-brand-logo size="default" />
            </a>

            <!-- Nav Links (Desktop) -->
            <nav class="hidden lg:flex items-center gap-8 text-xs font-black text-slate-600">
                <a href="#story" class="hover:text-blue-700 transition flex items-center gap-1">
                    <span>🧱 Baupraxis & Story</span>
                </a>
                <a href="#module" class="hover:text-blue-700 transition">ERP-Module & VOB</a>
                <a href="#rechner" class="hover:text-emerald-700 transition flex items-center gap-1.5 font-black text-emerald-700">
                    <span>🧮 Ersparnisrechner</span>
                </a>
                <a href="#vorteile" class="hover:text-blue-700 transition">Vorher / Nachher</a>
                <a href="#faq" class="hover:text-slate-900 transition">FAQ</a>
            </nav>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
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
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- 2. HERO SECTION WITH CRISP LIGHT ARCHITECTURAL AESTHETICS                -->
    <!-- ========================================================================= -->
    <section class="relative pt-12 pb-20 lg:pt-20 lg:pb-28 overflow-hidden">
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <div class="text-center max-w-4xl mx-auto space-y-6">
                
                <!-- Origin Badge with Micro-Animation Float -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white border border-amber-300 text-amber-900 text-xs font-black shadow-xs animate-float">
                    <span class="flex h-2 w-2 rounded-full bg-amber-500 animate-ping"></span>
                    <span>🏗️ Entwickelt von der BT Bautechnik UG – Aus der echten Baupraxis für Bauträger & Bauleiter</span>
                </div>

                <!-- Main Hero Headline -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-950 leading-tight sm:leading-[1.12]">
                    Die Bauleiter- & Bauträger-Software,<br>
                    <span class="bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 bg-clip-text text-transparent">
                        die direkt auf der Baustelle geboren wurde.
                    </span>
                </h1>

                <!-- Subtitle with Construction Authenticity -->
                <p class="text-sm sm:text-lg text-slate-600 font-medium max-w-3xl mx-auto leading-relaxed">
                    Wir sind selbst aktives Bauunternehmen in Bayern. Wir kennen den Zeitdruck, unübersichtliche Aufmaße und vergessene Nachträge nach VOB/B § 2. Das <strong>BT Bautechnik Cockpit</strong> vereint Baustellen-Steuerung, 360° Kunden-Zentrale, digitale VOB/C Aufmaße, KI-Bautagebücher und DATEV-Finanzen in einer blitzschnellen Lösung.
                </p>

                <!-- Hero CTAs with Glow and Hover Elevation -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 hover:from-blue-600 hover:to-amber-500 text-white font-black text-sm rounded-2xl shadow-xl shadow-blue-600/20 hover:shadow-amber-500/25 transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer flex items-center justify-center gap-2 btn-press">
                        <span>🚀 Kostenlose Live-Demo vereinbaren</span>
                        <span class="inline-block transition-transform group-hover:translate-x-1">→</span>
                    </button>

                    <a href="https://wa.me/4917612345678?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="w-full sm:w-auto px-6 py-4 bg-white hover:bg-slate-50 text-slate-800 font-black text-sm rounded-2xl border border-slate-300 shadow-sm transition-all duration-300 transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <span>💬 Direkt per WhatsApp anfragen</span>
                    </a>
                </div>

                <!-- Trust Badges Strip (Light Theme with Card Lift) -->
                <div class="pt-6 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                    <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs card-lift hover:border-blue-400 group cursor-default">
                        <span class="text-blue-700 font-black block text-sm group-hover:scale-110 transition-transform">⚖️ VOB/B § 2 & VOB/C</span>
                        <span class="text-slate-500 text-[11px] font-semibold">Rechtssichere Nachträge & Aufmaße</span>
                    </div>
                    <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs card-lift hover:border-amber-400 group cursor-default">
                        <span class="text-amber-700 font-black block text-sm group-hover:scale-110 transition-transform">🎙️ KI-Sprachmemo</span>
                        <span class="text-slate-500 text-[11px] font-semibold">Bautagebuch in 30 Sek. vor Ort</span>
                    </div>
                    <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs card-lift hover:border-emerald-400 group cursor-default">
                        <span class="text-emerald-700 font-black block text-sm group-hover:scale-110 transition-transform">📊 DATEV SKR03/04</span>
                        <span class="text-slate-500 text-[11px] font-semibold">Buchungsstapel für Steuerberater</span>
                    </div>
                    <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs card-lift hover:border-cyan-400 group cursor-default">
                        <span class="text-cyan-700 font-black block text-sm group-hover:scale-110 transition-transform">📱 Mobile PWA</span>
                        <span class="text-slate-500 text-[11px] font-semibold">Für Smartphone & Baustellen-Tablet</span>
                    </div>
                </div>

            </div>

            <!-- Crisp Architectural Cockpit Preview Card (High Contrast with Real Construction Photography) -->
            <div class="mt-14 max-w-6xl mx-auto rounded-3xl p-3 sm:p-4 bg-gradient-to-b from-blue-100 via-slate-200 to-amber-50 border border-slate-300 shadow-2xl reveal-on-scroll">
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-inner">
                    
                    <!-- Window bar -->
                    <div class="px-5 py-3.5 bg-slate-900 border-b border-slate-800 flex items-center justify-between text-white">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                            <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            <span class="text-xs text-slate-300 font-mono ml-2">bautechnik-bt.de / bauleiter-cockpit / live</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-500/30 text-amber-300 border border-amber-400/40">
                                BT BAUTECHNIK UG
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/30 text-emerald-300 border border-emerald-400/40 animate-pulse">
                                LIVE COCKPIT
                            </span>
                        </div>
                    </div>

                    <!-- Split Mockup & On-Site Action Photo -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 bg-slate-50">
                        
                        <!-- Left: Real Bauleiter On-Site Tablet Photography -->
                        <div class="lg:col-span-5 relative overflow-hidden border-b lg:border-b-0 lg:border-r border-slate-200 group">
                            <img src="{{ asset('images/bauleiter-tablet-hero.jpg') }}" 
                                 alt="Bauleiter vor Ort mit digitalem BT Bautechnik Tablet Cockpit" 
                                 class="w-full h-full object-cover min-h-[300px] lg:min-h-[440px] group-hover:scale-105 transition-transform duration-700">
                            
                            <!-- Floating Glass Badge over photo -->
                            <div class="absolute bottom-4 left-4 right-4 bg-slate-950/85 backdrop-blur-md text-white p-3.5 rounded-2xl border border-white/20 shadow-lg">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                                    <span class="text-xs font-black text-amber-300 uppercase tracking-wider">Echte Baustelle vor Ort</span>
                                </div>
                                <p class="text-xs text-slate-200 font-medium">
                                    Bautagesberichte, digitale VOB/C Aufmaße und Mängelerfassung in Echtzeit auf dem Tablet.
                                </p>
                            </div>
                        </div>

                        <!-- Right: Interactive Cockpit KPIs & Status -->
                        <div class="lg:col-span-7 p-5 sm:p-7 space-y-5 flex flex-col justify-between">
                            
                            <!-- Project Banner Header -->
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                                <div>
                                    <span class="text-[10px] font-mono text-blue-700 font-black uppercase tracking-wider">BAUVORHABEN #2026-081</span>
                                    <h3 class="text-base font-black text-slate-950">WEG Maximilianstraße 44 – Tiefgaragenabdichtung</h3>
                                    <p class="text-xs text-slate-500 font-medium">Auftraggeber / Bauherr: Hausverwaltung Müller & Partner GmbH</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-3 py-1 rounded-xl bg-blue-50 text-blue-800 border border-blue-200 font-black text-xs">
                                        KW 32 – 38
                                    </span>
                                    <span class="px-3 py-1 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 font-black text-xs flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Im Plan
                                    </span>
                                </div>
                            </div>

                            <!-- Progress & Budget Cards -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs card-lift">
                                    <span class="text-[10px] text-slate-500 font-bold uppercase">Geplantes Budget</span>
                                    <p class="text-base font-black text-slate-900 mt-0.5">85.000,00 €</p>
                                    <div class="w-full bg-slate-100 h-2 rounded-full mt-2 overflow-hidden">
                                        <div class="bg-blue-600 h-full w-[65%]"></div>
                                    </div>
                                </div>
                                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs card-lift">
                                    <span class="text-[10px] text-slate-500 font-bold uppercase">Nachträge (VOB/B)</span>
                                    <p class="text-base font-black text-amber-700 mt-0.5">+ 12.450,00 €</p>
                                    <span class="text-[10px] text-emerald-700 font-bold">3 freigegeben, 1 offen</span>
                                </div>
                                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs card-lift">
                                    <span class="text-[10px] text-slate-500 font-bold uppercase">Aufmaß (VOB/C)</span>
                                    <p class="text-base font-black text-blue-700 mt-0.5">620 m² / 750 m²</p>
                                    <span class="text-[10px] text-slate-600 font-bold">82% fertiggestellt</span>
                                </div>
                            </div>

                            <!-- Mini Sub Action Bar -->
                            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2 text-slate-700 font-medium">
                                    <span>🎙️ Bautagesbericht heute per Sprachmemo erfasst</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-slate-600">4 Monteure vor Ort</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg font-bold text-[11px]">
                                        📑 Nachtrags-PDF erzeugt
                                    </span>
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg font-bold text-[11px]">
                                        📐 Aufmaß exportiert
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
    <!-- 3. DIE STORY: VON DER BRANCHE FÜR DIE BRANCHE                              -->
    <!-- ========================================================================= -->
    <section id="story" class="py-24 bg-gradient-to-b from-white via-slate-50/50 to-white border-y border-slate-200/90 relative overflow-hidden">
        
        <!-- Background Ambient Glow -->
        <div class="absolute top-1/2 -left-40 w-96 h-96 bg-blue-100/40 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/3 -right-40 w-96 h-96 bg-amber-100/40 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Section Header -->
            <div class="max-w-3xl mb-14 space-y-3 reveal-on-scroll">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-50 border border-amber-300 text-amber-900 text-xs font-black uppercase shadow-2xs">
                    <span class="text-sm">🧱</span>
                    <span>Aus der echten Baupraxis – Für Bauträger, Generalübernehmer & Bauleiter</span>
                </div>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-950 tracking-tight leading-tight">
                    Wir bauen selbst.<br>
                    <span class="bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 bg-clip-text text-transparent">
                        Wir kennen jeden Engpass auf der Baustelle.
                    </span>
                </h2>
                <p class="text-sm sm:text-base text-slate-600 font-medium leading-relaxed">
                    Hinter dieser Lösung steht kein reines Softwarehaus, sondern die <strong>BT Bautechnik UG (haftungsbeschränkt)</strong> mit Sitz in Berching (Bayern). Jede Funktion löst ein reales Problem, das wir selbst auf unseren Baustellen erlebt haben:
                </p>
            </div>

            <!-- Bento Grid Showcase: 3 Interactive Problem/Solution Cards (Left) & Photo + 4 Core Cards (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left Column: 3 Rich Problem -> Solution Cards (6 cols) -->
                <div class="lg:col-span-6 space-y-4 reveal-on-scroll">
                    
                    <!-- Card 1: Nachträge -->
                    <div class="bg-white p-6 sm:p-7 rounded-3xl border border-slate-200 shadow-sm hover:border-amber-400 card-lift transition-all space-y-3 group">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                ⚠️ Das alte Problem
                            </span>
                            <span class="text-xs font-black text-amber-600 flex items-center gap-1">
                                <span>VOB/B § 2 Abs. 5 & 6</span>
                            </span>
                        </div>
                        <h3 class="font-black text-slate-900 text-base group-hover:text-amber-600 transition-colors">
                            Nachträge wurden vergessen oder mündlich verhandelt
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Weil Poliere und Bauleiter vor Ort keine Zeit hatten, am PC Angebote zu tippen, blieben berechtigte Mehrleistungen unvergütet.
                        </p>
                        <div class="pt-2 border-t border-slate-100 flex items-center gap-2 text-xs font-bold text-emerald-800 bg-emerald-50/70 p-3 rounded-2xl border border-emerald-200">
                            <span class="text-base">✨</span>
                            <span><strong>BT Lösung:</strong> Nachtragsangebot nach § 2 VOB/B mit 2 Klicks vor Ort als PDF erzeugen.</span>
                        </div>
                    </div>

                    <!-- Card 2: Bautagebuch -->
                    <div class="bg-white p-6 sm:p-7 rounded-3xl border border-slate-200 shadow-sm hover:border-blue-400 card-lift transition-all space-y-3 group">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                ⚠️ Das alte Problem
                            </span>
                            <span class="text-xs font-black text-blue-600 flex items-center gap-1">
                                <span>KI-Sprachmemo (Whisper)</span>
                            </span>
                        </div>
                        <h3 class="font-black text-slate-900 text-base group-hover:text-blue-700 transition-colors">
                            Mühsame Bautagebücher nach 10 Stunden Arbeit
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Niemand tippt abends gern Berichte. Die Folge: Lückenhafte Dokumentation und Beweisnot bei späteren Gewährleistungsstreitigkeiten.
                        </p>
                        <div class="pt-2 border-t border-slate-100 flex items-center gap-2 text-xs font-bold text-blue-900 bg-blue-50/70 p-3 rounded-2xl border border-blue-200">
                            <span class="text-base">🎙️</span>
                            <span><strong>BT Lösung:</strong> 30-Sekunden Sprachmemo einsprechen – KI formuliert fertigen Tagesbericht samt Wetter & Fotos.</span>
                        </div>
                    </div>

                    <!-- Card 3: Steuerberater & Abrechnung -->
                    <div class="bg-white p-6 sm:p-7 rounded-3xl border border-slate-200 shadow-sm hover:border-emerald-400 card-lift transition-all space-y-3 group">
                        <div class="flex items-center justify-between">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                ⚠️ Das alte Problem
                            </span>
                            <span class="text-xs font-black text-emerald-600 flex items-center gap-1">
                                <span>DATEV SKR03 / SKR04</span>
                            </span>
                        </div>
                        <h3 class="font-black text-slate-900 text-base group-hover:text-emerald-700 transition-colors">
                            Abrechnungs-Chaos & manuelle Buchhaltungs-Übergabe
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Belege per Post, fehlende Zuordnung nach § 13b UStG für Nachunternehmer und Verzögerungen beim Monatsabschluss.
                        </p>
                        <div class="pt-2 border-t border-slate-100 flex items-center gap-2 text-xs font-bold text-emerald-900 bg-emerald-50/70 p-3 rounded-2xl border border-emerald-200">
                            <span class="text-base">📊</span>
                            <span><strong>BT Lösung:</strong> Fertiger DATEV-Export auf Knopfdruck für Ihren Steuerberater ohne Doppeleingaben.</span>
                        </div>
                    </div>

                    <div class="pt-3">
                        <button wire:click="openDemoModal" class="w-full py-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-amber-600 hover:from-blue-600 hover:to-amber-500 text-white font-black text-xs sm:text-sm rounded-2xl shadow-lg shadow-blue-600/20 transition cursor-pointer flex items-center justify-center gap-2 btn-press">
                            <span>Lernen Sie die BT Bauleiter-Suite unverbindlich kennen</span>
                            <span>→</span>
                        </button>
                    </div>

                </div>

                <!-- Right Column: Planning Office Photo + 4 High-Impact Value Cards (6 cols) -->
                <div class="lg:col-span-6 space-y-6 reveal-on-scroll reveal-delay-200">
                    
                    <!-- Bauträger Planning Office Image with Floating Badges -->
                    <div class="relative rounded-3xl overflow-hidden border border-slate-200 shadow-xl group">
                        <img src="{{ asset('images/bautraeger-office-cockpit.jpg') }}" 
                             alt="BT Bautechnik Bauträger Planungsbüro & Projektmanagement" 
                             class="w-full h-72 sm:h-80 object-cover group-hover:scale-105 transition-transform duration-700">
                        
                        <!-- Overlay Gradient & Glass Badges -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/30 to-transparent flex flex-col justify-between p-6">
                            <div class="flex justify-end">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase bg-white/90 backdrop-blur-md text-slate-900 shadow-md border border-white">
                                    📍 Berching, Bayern
                                </span>
                            </div>
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                                    <span class="text-xs font-black text-amber-300 uppercase tracking-wider">
                                        Praxiseinsatz vor Ort
                                    </span>
                                </div>
                                <h4 class="text-base sm:text-lg font-black text-white leading-snug">
                                    Planungsbüro & Baustellen-Zentrale BT Bautechnik UG
                                </h4>
                                <p class="text-xs text-slate-200 font-medium">
                                    Wir testen und optimieren jedes Release täglich auf unseren eigenen Bauvorhaben.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 4 Generously Padded, High-Contrast Feature Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        
                        <!-- Card 1 -->
                        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm card-lift hover:border-amber-400 group cursor-default flex flex-col justify-between space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center text-2xl font-bold group-hover:scale-110 transition-transform">
                                    🧱
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase bg-amber-50 text-amber-800 border border-amber-200">
                                    100% Praxis
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-base group-hover:text-amber-700 transition-colors">
                                    Echtes Bauunternehmen
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Keine theoretische Spielerei: Entwickelt von aktiven Bauleitern für den harten Baustellenalltag in Bayern.
                                </p>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm card-lift hover:border-blue-400 group cursor-default flex flex-col justify-between space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-800 flex items-center justify-center text-2xl font-bold group-hover:scale-110 transition-transform">
                                    📑
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase bg-blue-50 text-blue-800 border border-blue-200">
                                    Rechtssicher
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-base group-hover:text-blue-700 transition-colors">
                                    VOB/B § 2 Automatik
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Mehrvergütung sofort mit offiziellem Briefkopf, Begründung und rechtssicherem PDF-Angebot versenden.
                                </p>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm card-lift hover:border-indigo-400 group cursor-default flex flex-col justify-between space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-800 flex items-center justify-center text-2xl font-bold group-hover:scale-110 transition-transform">
                                    👥
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase bg-indigo-50 text-indigo-800 border border-indigo-200">
                                    Alles vernetzt
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-base group-hover:text-indigo-700 transition-colors">
                                    360° Kunden-Zentrale
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Der Bauherr im Mittelpunkt: Baustellen, Aufmaße, Rechnungen und Telefonnotizen mit einem Klick steuern.
                                </p>
                            </div>
                        </div>

                        <!-- Card 4 -->
                        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm card-lift hover:border-emerald-400 group cursor-default flex flex-col justify-between space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-2xl font-bold group-hover:scale-110 transition-transform">
                                    📊
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[9.5px] font-black uppercase bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    DATEV SKR03/04
                                </span>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-950 text-base group-hover:text-emerald-700 transition-colors">
                                    Steuerberater Export
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed font-medium mt-1">
                                    Inklusive § 13b UStG Steuerschlüsseln für Subunternehmer. Kein mühsames Nachbuchen am Monatsende.
                                </p>
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
    <section id="module" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 reveal-on-scroll">
        
        <div class="text-center max-w-3xl mx-auto space-y-3 mb-10">
            <span class="px-3 py-1 rounded-full bg-blue-100 border border-blue-200 text-blue-800 text-xs font-black uppercase">
                🚀 Die All-in-One ERP Suite
            </span>
            <h2 class="text-2xl sm:text-4xl font-black text-slate-950 tracking-tight">
                Entdecken Sie alle Module im interaktiven Simulator
            </h2>
            <p class="text-xs sm:text-sm text-slate-600 font-medium">
                Wählen Sie ein Modul, um Funktionen und Workflows zu testen:
            </p>
        </div>

        <!-- Module Selector Tabs -->
        <div class="flex flex-wrap items-center justify-center gap-2 mb-8">
            <button wire:click="$set('activeModuleTab', 'cockpit')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'cockpit' ? 'bg-blue-700 text-white shadow-md shadow-blue-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200' }}">
                <span>🏗️ Baustellen-Cockpit</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'contacts360')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'contacts360' ? 'bg-indigo-700 text-white shadow-md shadow-indigo-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200' }}">
                <span>👥 360° Kunden-Zentrale</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'supplements')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'supplements' ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200' }}">
                <span>📑 VOB/B Nachträge</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'measurements')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'measurements' ? 'bg-cyan-700 text-white shadow-md shadow-cyan-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200' }}">
                <span>📐 VOB/C Aufmaßblatt</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'dailylogs')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'dailylogs' ? 'bg-emerald-700 text-white shadow-md shadow-emerald-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200' }}">
                <span>🎙️ KI-Bautagebuch</span>
            </button>
            <button wire:click="$set('activeModuleTab', 'datev')" class="px-4 py-2.5 rounded-xl text-xs font-black transition cursor-pointer btn-press flex items-center gap-2 {{ $activeModuleTab === 'datev' ? 'bg-purple-700 text-white shadow-md shadow-purple-600/20' : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-200' }}">
                <span>📊 DATEV & Finanzen</span>
            </button>
        </div>

        <!-- Interactive Module Showcase Screen (Light High-Contrast) -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-xl">
            
            @if ($activeModuleTab === 'cockpit')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-blue-700 tracking-wider">Kernmodul 01</span>
                        <h3 class="text-2xl font-black text-slate-950">Baustellen-Cockpit & Soll/Ist-Steuerung</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Behalten Sie jedes Bauvorhaben im Griff: Budgetüberwachung, Bauzeitenplan nach Kalenderwochen, automatische Wetteraufzeichnung und lückenloses Fotoprotokoll.
                        </p>
                        <div class="space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ Echtzeit-Budgetverbrauch mit Soll/Ist-Kosten</p>
                            <p class="flex items-center gap-2">✅ Kalenderwochen-Terminplan (Start-KW bis End-KW)</p>
                            <p class="flex items-center gap-2">✅ Wetter-API mit automatischer Temperatur & Niederschlag</p>
                        </div>
                        <button wire:click="openDemoModal('bautraeger')" class="px-4 py-2 bg-blue-700 hover:bg-blue-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Cockpit live testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">🏢 Projekt: Neubau Wohnanlage Regensburg</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">KW 28 – KW 42</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs">
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
            @endif

            @if ($activeModuleTab === 'contacts360')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-indigo-700 tracking-wider">Kernmodul 02</span>
                        <h3 class="text-2xl font-black text-slate-950">360° Kunden- & Bauherren-Zentrale</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Da der Kunde der Eigentümer der Baustellen ist, steuern Sie alles direkt aus dem Kunden heraus: Neue Baustellen anlegen, VOB-Nachträge erfassen, Aufmaße abrufen und Mängel überwachen.
                        </p>
                        <div class="space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ 1-Klick-Aktionen pro Kunde (Baustelle, Nachtrag, Aufmaß, Rechnung)</p>
                            <p class="flex items-center gap-2">✅ Zeitgestempeltes Telefon- & Notizjournal</p>
                            <p class="flex items-center gap-2">✅ KI-Chefbauleiter Dossier für jedes Kundenprofil</p>
                        </div>
                        <button wire:click="openDemoModal('generalunternehmer')" class="px-4 py-2 bg-indigo-700 hover:bg-indigo-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Kunden-Zentrale testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="font-black text-xs text-slate-900">👤 Kunde: Hausverwaltung Schmidt & Co.</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-indigo-100 text-indigo-800">4 Baustellen</span>
                        </div>
                        <div class="p-3 bg-white rounded-xl border border-slate-200 flex flex-wrap gap-2 text-xs">
                            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg font-bold">🏗️ + Baustelle</span>
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg font-bold">📑 + Nachtrag</span>
                            <span class="px-2.5 py-1 bg-cyan-50 text-cyan-700 border border-cyan-200 rounded-lg font-bold">📐 + Aufmaß</span>
                            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg font-bold">📄 + Rechnung</span>
                            <span class="px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg font-bold">🤖 KI-Dossier</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeModuleTab === 'supplements')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-amber-700 tracking-wider">Kernmodul 03</span>
                        <h3 class="text-2xl font-black text-slate-950">VOB/B Nachtragsmanagement (§ 2)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Schluss mit vergessenen oder abgewiesenen Nachträgen. Erfassen Sie Mehrleistungen nach § 2 Abs. 5 oder § 2 Abs. 6 sofort mit rechtssicherem PDF-Export.
                        </p>
                        <div class="space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ Automatische VOB-Begründung & Fristüberwachung</p>
                            <p class="flex items-center gap-2">✅ PDF-Nachtragsangebot mit rechtssicherem VOB-Briefkopf</p>
                            <p class="flex items-center gap-2">✅ Status: Eingereicht, Geprüft, Beauftragt, Abgerechnet</p>
                        </div>
                        <button wire:click="openDemoModal('sanierung_abdichtung')" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Nachtragsmodul ansehen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">📑 Nachtragsangebot NT-03 nach VOB/B § 2 Abs. 5</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800">BEAUFTRAGT</span>
                        </div>
                        <p class="text-xs text-slate-700 font-medium">
                            Titel: Zusätzliche Hohlkehlenabdichtung & Bitumen-Dickbeschichtung Rampe UG 2
                        </p>
                        <div class="flex justify-between items-center p-3 bg-white rounded-xl border border-slate-200 text-xs">
                            <span class="text-slate-500 font-medium">Nachtragssumme Netto:</span>
                            <span class="text-base font-black text-amber-700">4.850,00 €</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeModuleTab === 'measurements')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-cyan-700 tracking-wider">Kernmodul 04</span>
                        <h3 class="text-2xl font-black text-slate-950">Digitales Aufmaßblatt (VOB/C / DIN 18299)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Erfassen Sie Mengen direkt vor Ort mit Raummaßen (Länge × Breite × Höhe / Faktor), automatischem VOB-Abzug für Öffnungen und sofortigem PDF-Prüfprotokoll für den Bauherrn.
                        </p>
                        <div class="space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ Flexible Formeln (z. B. 12.50 * 4.20 * 2)</p>
                            <p class="flex items-center gap-2">✅ Automatischer VOB-Abzug nach DIN 18299 / DIN 18336</p>
                            <p class="flex items-center gap-2">✅ 1-Klick Übergabe in die Schlussrechnung</p>
                        </div>
                        <button wire:click="openDemoModal('hoch_tiefbau')" class="px-4 py-2 bg-cyan-700 hover:bg-cyan-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Aufmaß-Engine testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">📐 Aufmaßblatt AM-2026-004 (Bodenplatte TG)</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-cyan-100 text-cyan-800">DIN 18299</span>
                        </div>
                        <div class="space-y-2 text-xs font-mono">
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
            @endif

            @if ($activeModuleTab === 'dailylogs')
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-5 space-y-4">
                        <span class="text-xs font-black uppercase text-emerald-700 tracking-wider">Kernmodul 05</span>
                        <h3 class="text-2xl font-black text-slate-950">KI-Bautagebuch & Sprachmemo (Whisper)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Kein Bauleiter tippt gern Berichte auf der Baustelle. Nehmen Sie einfach 30 Sekunden Sprachmemo auf – die KI formuliert einen druckreifen, rechtssicheren Bautagesbericht mit Wetter, Anwesenden und Gewerken.
                        </p>
                        <div class="space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ KI-Sprachaufnahme & automatische Text-Strukturierung</p>
                            <p class="flex items-center gap-2">✅ Integrierter Fotoupload mit Beschriftung</p>
                            <p class="flex items-center gap-2">✅ Digitaler Freigabe-Link & PDF-Versand an Bauherrn</p>
                        </div>
                        <button wire:click="openDemoModal('bautraeger')" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            Sprach-Bautagebuch testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900">
                            <span class="text-lg">🎙️</span>
                            <span class="font-medium font-sans">"Heute 4 Mann vor Ort, Abdichtung TG fertiggestellt, 2 Paletten Bitumen verbraucht, Wetter trocken 21 Grad."</span>
                        </div>
                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 space-y-1.5 text-xs text-slate-700">
                            <span class="text-[10px] text-emerald-700 font-black uppercase">✨ KI-Generierter Bericht:</span>
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
                        <span class="text-xs font-black uppercase text-purple-700 tracking-wider">Kernmodul 06</span>
                        <h3 class="text-2xl font-black text-slate-950">DATEV-Export & Subunternehmer-Controlling</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">
                            Übertragen Sie alle Ausgangs- und Eingangsrechnungen, Nachunternehmer-Rechnungen (§ 13b UStG) und Projektkosten im standardisierten DATEV-Format direkt an Ihren Steuerberater.
                        </p>
                        <div class="space-y-2 text-xs font-bold text-slate-700">
                            <p class="flex items-center gap-2">✅ DATEV Buchungsstapel CSV (SKR03 & SKR04)</p>
                            <p class="flex items-center gap-2">✅ Automatische § 13b UStG Steuerschlüssel-Zuordnung</p>
                            <p class="flex items-center gap-2">✅ Rechnungsfreigabe-Workflow & Zahlungsüberwachung</p>
                        </div>
                        <button wire:click="openDemoModal('generalunternehmer')" class="px-4 py-2 bg-purple-700 hover:bg-purple-600 text-white font-black text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                            DATEV-Workflow testen →
                        </button>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                            <span class="font-black text-xs text-slate-900">📊 DATEV SKR03 Buchungsstapel Export</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-purple-100 text-purple-800">STEUERBERATER READY</span>
                        </div>
                        <div class="p-3 bg-white rounded-xl border border-slate-200 font-mono text-[11px] text-slate-700 space-y-1">
                            <p>Umsatz;S/H;Konto;Gegenkonto;BU;Beleg1;Datum;Text</p>
                            <p class="text-emerald-700 font-bold">14850.00;S;8400;10000;;RE-2026-041;1408;AR WEG Maxstr</p>
                            <p class="text-amber-700 font-bold">4200.00;H;3100;70000;19;ER-88412;1408;Subunt. Abdichtung</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>

    </section>

    <!-- ========================================================================= -->
    <!-- 5. INTERAKTIVER ROI & ERSPARNISRECHNER                                    -->
    <!-- ========================================================================= -->
    <section id="rechner" class="py-20 bg-slate-100 border-t border-slate-200 relative reveal-on-scroll">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center max-w-3xl mx-auto space-y-3 mb-12">
                <span class="px-3 py-1 rounded-full bg-emerald-100 border border-emerald-200 text-emerald-800 text-xs font-black uppercase">
                    🧮 Wirtschaftlichkeitsrechner
                </span>
                <h2 class="text-2xl sm:text-4xl font-black text-slate-950 tracking-tight">
                    Berechnen Sie Ihre Ersparnis & Mehrumsatz mit BT Cockpit
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 font-medium">
                    Passen Sie die Schieberegler an Ihre Betriebsgröße an:
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center max-w-5xl mx-auto">
                
                <!-- Left: Interactive Sliders (6 cols) -->
                <div class="lg:col-span-6 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6 card-lift">
                    
                    <!-- Slider 1: Baustellen -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-800">Gleichzeitige Baustellen:</label>
                            <span class="px-3 py-1 rounded-xl bg-blue-50 text-blue-700 font-black text-sm border border-blue-200">
                                {{ $roiProjectCount }} Baustellen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiProjectCount" min="1" max="25" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-blue-600">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>1 Baustelle</span>
                            <span>25 Baustellen</span>
                        </div>
                    </div>

                    <!-- Slider 2: Mitarbeiter -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-800">Mitarbeiter & Bauleiter:</label>
                            <span class="px-3 py-1 rounded-xl bg-indigo-50 text-indigo-700 font-black text-sm border border-indigo-200">
                                {{ $roiWorkerCount }} Personen
                            </span>
                        </div>
                        <input type="range" wire:model.live="roiWorkerCount" min="2" max="40" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>2 Mitarbeiter</span>
                            <span>40 Mitarbeiter</span>
                        </div>
                    </div>

                    <!-- Slider 3: Stundensatz -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label class="font-bold text-slate-800">Kalkulatorischer Stundensatz:</label>
                            <span class="px-3 py-1 rounded-xl bg-amber-50 text-amber-800 font-black text-sm border border-amber-200">
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

                <!-- Right: Calculated Results (6 cols) -->
                <div class="lg:col-span-6 bg-slate-900 text-white p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-2xl space-y-6 card-lift">
                    
                    <div class="space-y-1">
                        <span class="text-[10px] font-black uppercase text-amber-400 tracking-wider">Ihr kalkulierter Jahresvorteil</span>
                        <h4 class="text-3xl sm:text-4xl font-black text-white tabular-nums">
                            ~ {{ number_format($this->totalValuePerYear, 0, ',', '.') }} € <span class="text-xs text-slate-400 font-medium">/ Jahr</span>
                        </h4>
                    </div>

                    <div class="space-y-3 text-xs pt-2 border-t border-slate-800">
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

                    <button wire:click="openDemoModal" class="w-full py-3.5 bg-gradient-to-r from-blue-600 via-indigo-600 to-amber-500 hover:from-blue-500 hover:to-amber-400 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-500/20 transition cursor-pointer btn-press">
                        Diesen Vorteil jetzt für Ihren Betrieb sichern →
                    </button>
                </div>

            </div>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 6. VORHER VS. NACHHER VERGLEICH (LIGHT SPLIT CARDS)                       -->
    <!-- ========================================================================= -->
    <section id="vorteile" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 reveal-on-scroll">
        
        <div class="text-center max-w-3xl mx-auto space-y-3 mb-12">
            <span class="px-3 py-1 rounded-full bg-cyan-100 border border-cyan-200 text-cyan-800 text-xs font-black uppercase">
                ⚡ Der direkte Vergleich
            </span>
            <h2 class="text-2xl sm:text-4xl font-black text-slate-950 tracking-tight">
                Vorher vs. Nachher: Ihr Baustellenalltag transformiert
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
            
            <!-- BEFORE CARD -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-rose-200 shadow-sm space-y-5 card-lift">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-black">
                        ✕
                    </div>
                    <div>
                        <h4 class="font-black text-slate-900 text-base">Klassischer Baualltag (Vorher)</h4>
                        <span class="text-[11px] text-rose-700 font-bold">Hoher Zeitverlust & Haftungsrisiko</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-600 font-medium">
                    <li class="flex items-start gap-2">
                        <span class="text-rose-600 font-bold">✕</span>
                        <span>Papier-Bautagebücher werden unvollständig oder erst Tage später ausgefüllt.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-600 font-bold">✕</span>
                        <span>Nachträge nach VOB/B § 2 werden formlos per Mail oder Zuruf verhandelt und nicht vergütet.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-600 font-bold">✕</span>
                        <span>Handschriftliche Handaufmaße mit unklaren Formeln führen zu Streit bei der Abnahme.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-600 font-bold">✕</span>
                        <span>Stundenzettel müssen am Monatsende mühsam abgetippt und korrigiert werden.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-rose-600 font-bold">✕</span>
                        <span>Steuerberater wartet auf Belege; keine DATEV-Schnittstelle.</span>
                    </li>
                </ul>
            </div>

            <!-- AFTER CARD -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border-2 border-blue-600 shadow-xl space-y-5 relative card-lift">
                <div class="absolute -top-3 right-6 px-3 py-1 bg-blue-600 text-white rounded-full text-[10px] font-black tracking-wider uppercase shadow-xs">
                    Empfohlener Standard
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-black">
                        ✓
                    </div>
                    <div>
                        <h4 class="font-black text-slate-900 text-base">Mit BT Bautechnik Cockpit (Nachher)</h4>
                        <span class="text-[11px] text-emerald-700 font-bold">100% rechtssicher, digital & rentabel</span>
                    </div>
                </div>

                <ul class="space-y-3 text-xs text-slate-700 font-semibold">
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span>30-Sekunden Sprachmemo erzeugt das fertige Bautagebuch samt Wetter & Fotos.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span>1-Klick Nachtragsangebote mit rechtssicherem VOB/B-Bezug und fertigem PDF.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span>Digitales Aufmaßblatt (VOB/C / DIN 18299) mit automatischem Raumabzug.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span>Mobile Zeiterfassung (MiLoG-konform) direkt auf der Baustelle per Klick.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span>DATEV SKR03/04 Export mit automatischer § 13b UStG Steuerschlüssel-Vergabe.</span>
                    </li>
                </ul>
            </div>

        </div>

    </section>

    <!-- ========================================================================= -->
    <!-- 7. FAQ SECTION                                                            -->
    <!-- ========================================================================= -->
    <section id="faq" class="py-20 bg-slate-100 border-t border-slate-200 reveal-on-scroll">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="text-center space-y-3 mb-10">
                <span class="px-3 py-1 rounded-full bg-white border border-slate-200 text-slate-700 text-xs font-black uppercase">
                    💬 Häufige Fragen
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-950 tracking-tight">
                    Fragen von Bauträgern & Bauunternehmen
                </h2>
            </div>

            <div class="space-y-3 text-xs">
                
                <div class="bg-white border border-slate-200 rounded-2xl p-4 transition shadow-xs card-lift">
                    <button wire:click="toggleFaq(0)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-sm cursor-pointer">
                        <span>Ist die Software auf Smartphones und Tablets auf der Baustelle nutzbar?</span>
                        <span class="text-blue-700 text-base font-bold">{{ $openFaqIndex === 0 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 0)
                        <p class="mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium">
                            Ja! BT Bautechnik Cockpit ist als Progressive Web App (PWA) konzipiert. Es läuft reaktionsschnell auf jedem iPhone, Android-Smartphone, iPad oder Laptop – ohne umständliche App-Store Installation.
                        </p>
                    @endif
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 transition shadow-xs card-lift">
                    <button wire:click="toggleFaq(1)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-sm cursor-pointer">
                        <span>Wie funktioniert die Nachtragserstellung nach VOB/B § 2?</span>
                        <span class="text-blue-700 text-base font-bold">{{ $openFaqIndex === 1 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 1)
                        <p class="mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium">
                            Das System unterscheidet automatisch zwischen Leistungsänderungen (§ 2 Abs. 5) und unvorhergesehenen Zusatzleistungen (§ 2 Abs. 6). Sie geben Titel und Menge ein – das System erstellt sofort das unterschriftsreife Nachtragsangebot als PDF mit rechtssicherer Klausulierung.
                        </p>
                    @endif
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 transition shadow-xs card-lift">
                    <button wire:click="toggleFaq(2)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-sm cursor-pointer">
                        <span>Kann mein Steuerberater die Rechnungen und Kosten direkt importieren?</span>
                        <span class="text-blue-700 text-base font-bold">{{ $openFaqIndex === 2 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 2)
                        <p class="mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium">
                            Ja. Das System verfügt über eine integrierte DATEV CSV-Schnittstelle nach SKR03 und SKR04 inklusive automatischem Buchungsschlüssel für Nachunternehmer-Rechnungen (§ 13b UStG Bauleistungen).
                        </p>
                    @endif
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 transition shadow-xs card-lift">
                    <button wire:click="toggleFaq(3)" class="w-full flex justify-between items-center text-left font-black text-slate-900 text-sm cursor-pointer">
                        <span>Können wir das System unverbindlich testen?</span>
                        <span class="text-blue-700 text-base font-bold">{{ $openFaqIndex === 3 ? '−' : '+' }}</span>
                    </button>
                    @if ($openFaqIndex === 3)
                        <p class="mt-3 text-slate-600 leading-relaxed pt-2 border-t border-slate-100 font-medium">
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
    <section class="py-20 relative overflow-hidden bg-white border-t border-slate-200 reveal-on-scroll">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white p-8 sm:p-12 rounded-3xl shadow-2xl space-y-6">
                <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    Bereit, Ihre Baustellen & Finanzen auf das nächste Level zu heben?
                </h2>
                <p class="text-xs sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed font-medium">
                    Schließen Sie sich zukunftsorientierten Bauunternehmen & Bauträgern an. Fordern Sie jetzt Ihre persönliche Live-Präsentation an.
                </p>
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button wire:click="openDemoModal" class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-blue-500 via-indigo-500 to-amber-500 hover:from-blue-400 hover:to-amber-400 text-white font-black text-xs rounded-xl shadow-xl transition cursor-pointer btn-press">
                        🚀 Jetzt kostenlose Demo anfordern
                    </button>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto px-6 py-4 bg-white/10 hover:bg-white/20 text-white font-black text-xs rounded-xl border border-white/20 transition">
                        Bestehendes Kundenkonto Login ↗
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 9. FOOTER WITH REAL LEGAL ENTITY & COMPLIANCE LINKS                       -->
    <!-- ========================================================================= -->
    <footer class="border-t border-slate-200 bg-white py-12 text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            
            <!-- Real Brand Identity in Footer -->
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <x-brand-logo size="small" />
                <span class="text-slate-300 hidden sm:inline">•</span>
                <span class="text-slate-500 text-center sm:text-left font-medium">
                    BT Bautechnik UG (haftungsbeschränkt) | Sollngriesbacher Str. 4, 92334 Berching 🇩🇪
                </span>
            </div>

            <!-- Legal Pages Links -->
            <div class="flex flex-wrap items-center justify-center gap-6 font-bold text-slate-700">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-6 relative overflow-hidden">
                
                <button wire:click="closeDemoModal" class="absolute top-5 right-5 text-slate-400 hover:text-slate-900 text-xl font-bold cursor-pointer">✕</button>

                @if ($demoSuccess)
                    <div class="py-8 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-3xl mx-auto font-bold animate-bounce">
                            ✓
                        </div>
                        <h3 class="text-xl font-black text-slate-900">Vielen Dank für Ihre Anfrage!</h3>
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
                        <span class="text-[10px] font-black uppercase text-amber-700 tracking-wider">Unverbindliche Präsentation</span>
                        <h3 class="text-xl font-black text-slate-900">Live-Demo für Ihr Bauunternehmen</h3>
                        <p class="text-xs text-slate-500 font-medium">Erfahren Sie, wie BT Cockpit Ihren Baustellenalltag revolutioniert.</p>
                    </div>

                    <form wire:submit="submitDemoRequest" class="space-y-3.5 text-xs">
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

</div>
