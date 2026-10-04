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
        $this->resetValidation();
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
        ], [
            'required' => 'Bitte füllen Sie das Feld :attribute aus.',
            'min' => ':attribute muss mindestens :min Zeichen enthalten.',
            'email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        ], [
            'demoName' => 'Name',
            'demoCompany' => 'Unternehmen',
            'demoEmail' => 'E-Mail-Adresse',
            'demoPhone' => 'Telefon',
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
            Log::warning('Demo request could not be saved.', ['exception_type' => get_class($e)]);
            $this->addError('demoRequest', 'Ihre Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.');
            return;
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
     @scroll.window="showStickyBar = (window.pageYOffset || document.documentElement.scrollTop) > 450 && document.getElementById('bt-footer').getBoundingClientRect().top > window.innerHeight"
     class="bt-landing min-h-screen text-slate-900 font-sans selection:bg-amber-500 selection:text-slate-950 relative">
    
    <!-- Architectural Hairline Vertical Guides & Ambient Layer -->
    <div class="arch-hairline-overlay"></div>
    <div class="fixed top-0 left-1/3 w-[650px] h-[550px] bg-slate-200/40 rounded-full blur-[160px] pointer-events-none -z-10 animate-glow"></div>
    <div class="fixed bottom-1/4 right-10 w-[550px] h-[550px] bg-amber-100/30 rounded-full blur-[180px] pointer-events-none -z-10"></div>

    <!-- ========================================================================= -->
    <!-- 1. STICKY TOP NAVBAR (ARCHITECTURAL DUAL-TONE & GLASS)                     -->
    <!-- ========================================================================= -->
    @include('livewire.partials.landing-navigation')

    <!-- ========================================================================= -->
    <!-- 2. HERO SECTION (CITY CONSTRUCT ARCHITECTURAL EDITORIAL STYLE)            -->
    <!-- ========================================================================= -->
    @include('livewire.partials.landing-hero')

    <!-- ========================================================================= -->
    <!-- 4. DIE STORY: VON BAUUNTERNEHMERN FÜR BAUUNTERNEHMER                      -->
    <!-- ========================================================================= -->
    <section id="story" class="py-14 sm:py-24 relative overflow-hidden">
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Section Header -->
            <div data-reveal class="max-w-3xl mb-12 space-y-3">
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
            <div data-reveal-group class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 items-start">
                
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
    @include('livewire.partials.landing-modules')

    <!-- ========================================================================= -->
    <!-- 6. CITY CONSTRUCT STYLE: HORIZONTALES SCHNELL-ANFRAGE DOCK                -->
    <!-- ========================================================================= -->
    <section class="py-12 bg-slate-950 text-white">
        <div class="bt-shell bt-demo-entry">
            <div><p class="bt-eyebrow">Ihr Betrieb. Ihre Abläufe.</p><h3>Sehen Sie, wie alles zusammenpasst.</h3><p>Wir zeigen Ihnen die passenden Module und besprechen, wie Sie Ihre Baustellen damit organisieren können.</p></div>
            <button type="button" wire:click="openDemoModal" class="bt-button">Persönliche Demo anfordern <span aria-hidden="true">↗</span></button>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 7. INTERAKTIVER WHISPER KI-BAGEBUCH VOICE SIMULATOR                       -->
    <!-- ========================================================================= -->
    <section class="py-14 sm:py-24 bg-slate-50 border-b border-slate-200 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div data-reveal class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-14">
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
            }" data-reveal="scale" class="arch-dock-dark p-6 sm:p-8 space-y-6">
                
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
            
            <div data-reveal class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-14">
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

            <div data-reveal-group class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-center max-w-5xl mx-auto">
                
                <!-- Left: Sliders -->
                <div class="lg:col-span-6 arch-card p-6 sm:p-8 space-y-6">
                    
                    <!-- Slider 1 -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label for="roiProjectCount" class="font-bold text-slate-900">Gleichzeitige Baustellen:</label>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-950 font-black text-xs sm:text-sm border border-slate-200 tabular-nums">
                                {{ $roiProjectCount }} Baustellen
                            </span>
                        </div>
                        <input id="roiProjectCount" type="range" wire:model.live="roiProjectCount" min="1" max="25" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-slate-950">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>1 Baustelle</span>
                            <span>25 Baustellen</span>
                        </div>
                    </div>

                    <!-- Slider 2 -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label for="roiWorkerCount" class="font-bold text-slate-900">Mitarbeiter & Bauleiter:</label>
                            <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-950 font-black text-xs sm:text-sm border border-slate-200 tabular-nums">
                                {{ $roiWorkerCount }} Personen
                            </span>
                        </div>
                        <input id="roiWorkerCount" type="range" wire:model.live="roiWorkerCount" min="2" max="40" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-slate-950">
                        <div class="flex justify-between text-[10px] text-slate-500 font-semibold">
                            <span>2 Mitarbeiter</span>
                            <span>40 Mitarbeiter</span>
                        </div>
                    </div>

                    <!-- Slider 3 -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <label for="roiHourlyRate" class="font-bold text-slate-900">Kalkulatorischer Stundensatz:</label>
                            <span class="px-3 py-1 rounded-lg bg-amber-50 text-amber-800 font-black text-xs sm:text-sm border border-amber-200 tabular-nums">
                                {{ $roiHourlyRate }} € / Std.
                            </span>
                        </div>
                        <input id="roiHourlyRate" type="range" wire:model.live="roiHourlyRate" min="45" max="110" step="1" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-amber-600">
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

                    <button wire:click="openDemoModal" class="micro-action w-full py-3.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 transition cursor-pointer btn-press">
                        <span>Diesen Vorteil für Ihren Betrieb sichern</span>
                        <span class="micro-arrow ml-1">→</span>
                    </button>
                </div>

            </div>
            <p class="mt-6 text-center text-xs text-slate-500 max-w-3xl mx-auto">Unverbindliche Modellrechnung mit angenommenen Zeitersparnissen und Nachtragserlösen. Der tatsächliche Nutzen hängt von Ihren Projekten und Arbeitsabläufen ab.</p>

        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 9. VORHER VS. NACHHER VERGLEICH (ARCHITECTURAL SPLIT)                     -->
    <!-- ========================================================================= -->
    <section id="vorteile" x-data="{ viewMode: 'both' }" class="py-14 sm:py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div data-reveal class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-12">
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

        <div data-reveal-group class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 max-w-5xl mx-auto">
            
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
            
            <div data-reveal class="text-center max-w-3xl mx-auto space-y-3 mb-10 sm:mb-14">
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

            <div data-reveal-group class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                
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
    @include('livewire.partials.landing-faq')

    @include('livewire.partials.landing-footer')

    <!-- ========================================================================= -->
    <!-- 14. DEMO REQUEST MODAL                                                    -->
    <!-- ========================================================================= -->
    @if ($showDemoModal)
        <div data-ui-dialog role="dialog" aria-modal="true" aria-label="Live-Demo anfordern" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/70 backdrop-blur-sm">
            <div class="bg-white border border-slate-200 rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-lg w-full max-h-[92vh] overflow-y-auto shadow-2xl space-y-4 sm:space-y-6 relative">
                
                <button type="button" data-dialog-close aria-label="Dialog schließen" wire:click="closeDemoModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-900 text-xl font-bold cursor-pointer">✕</button>

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
                        <p class="text-xs text-slate-500 font-medium">Lernen Sie die passenden Module für Ihren Betrieb kennen.</p>
                    </div>

                    <form wire:submit="submitDemoRequest" class="space-y-3 sm:space-y-3.5 text-xs">
                        @error('demoRequest') <p role="alert" class="bt-form-error">{{ $message }}</p> @enderror
                        <div>
                            <label for="demoName" class="block font-bold text-slate-800 mb-1">Ihr Name / Ansprechpartner *</label>
                            <input id="demoName" wire:model="demoName" @error('demoName') aria-describedby="demoName-error" aria-invalid="true" @enderror type="text" placeholder="z. B. Dipl.-Ing. Markus Huber" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                            @error('demoName') <p id="demoName-error" class="bt-form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="demoCompany" class="block font-bold text-slate-800 mb-1">Unternehmen / Firma *</label>
                            <input id="demoCompany" wire:model="demoCompany" @error('demoCompany') aria-describedby="demoCompany-error" aria-invalid="true" @enderror type="text" placeholder="z. B. Huber Bau & Sanierung GmbH" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                            @error('demoCompany') <p id="demoCompany-error" class="bt-form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="demoEmail" class="block font-bold text-slate-800 mb-1">E-Mail-Adresse *</label>
                                <input id="demoEmail" wire:model="demoEmail" @error('demoEmail') aria-describedby="demoEmail-error" aria-invalid="true" @enderror type="email" placeholder="m.huber@huberbau.de" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-medium rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                            @error('demoEmail') <p id="demoEmail-error" class="bt-form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="demoPhone" class="block font-bold text-slate-800 mb-1">Telefon / Mobil *</label>
                                <input id="demoPhone" wire:model="demoPhone" @error('demoPhone') aria-describedby="demoPhone-error" aria-invalid="true" @enderror type="tel" placeholder="0171 1234567" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-medium rounded-xl p-2.5 focus:border-amber-500 focus:outline-none" required>
                            @error('demoPhone') <p id="demoPhone-error" class="bt-form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="demoTrade" class="block font-bold text-slate-800 mb-1">Ihr Schwerpunkt</label>
                                <select id="demoTrade" wire:model="demoTrade" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                                    <option value="bautraeger">Bauträger / Entwickler</option>
                                    <option value="generalunternehmer">Generalübernehmer / GU</option>
                                    <option value="sanierung_abdichtung">Sanierung & Abdichtung</option>
                                    <option value="hoch_tiefbau">Hoch- & Tiefbau</option>
                                    <option value="handwerk">Fachhandwerksbetrieb</option>
                                </select>
                            </div>
                            <div>
                                <label for="demoProjectCount" class="block font-bold text-slate-800 mb-1">Baustellen pro Jahr</label>
                                <select id="demoProjectCount" wire:model="demoProjectCount" class="w-full bg-slate-50 border border-slate-200 text-slate-900 font-bold rounded-xl p-2.5 focus:border-amber-500 focus:outline-none">
                                    <option value="1-3">1 – 3 Bauvorhaben</option>
                                    <option value="4-10">4 – 10 Bauvorhaben</option>
                                    <option value="10+">Über 10 Bauvorhaben</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="demoMessage" class="block font-bold text-slate-800 mb-1">Nachricht / Notiz (optional)</label>
                            <textarea id="demoMessage" wire:model="demoMessage" rows="2" placeholder="Welche Module interessieren Sie besonders (z.B. VOB-Nachträge, Aufmaße, KI-Bautagebuch)?" class="w-full bg-slate-50 border border-slate-200 text-slate-900 rounded-xl p-2.5 focus:border-amber-500 focus:outline-none"></textarea>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <a href="https://wa.me/4916096275910?text=Hallo%20BT%20Bautechnik,%20ich%20m%C3%B6chte%20gerne%20eine%20Live-Demo%20f%C3%BCr%20unser%20Bauunternehmen%20anfragen." target="_blank" class="text-xs text-emerald-700 hover:underline flex items-center gap-1 font-bold">
                                <span>💬 Lieber per WhatsApp anfragen</span>
                            </a>

                            <button type="submit" wire:loading.attr="disabled" wire:target="submitDemoRequest" class="w-full sm:w-auto px-6 py-2.5 bg-slate-950 hover:bg-slate-800 text-white font-black text-xs rounded-xl shadow-md cursor-pointer btn-press">
                                <span wire:loading.remove wire:target="submitDemoRequest">Demo-Termin anfragen →</span>
                                <span wire:loading wire:target="submitDemoRequest">Anfrage wird gesendet …</span>
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
    <div x-show="showStickyBar && !mobileMenuOpen"
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
