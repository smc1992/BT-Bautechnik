<x-public-layout>
    <x-slot name="title">
        Allgemeine Geschäftsbedingungen (AGB) | BT Bautechnik UG
    </x-slot>

    <!-- Header Navigation -->
    <header class="sticky top-0 z-40 bg-slate-950/85 backdrop-blur-md border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="/" class="hover:opacity-90 transition">
                <x-brand-logo size="default" />
            </a>
            <div class="flex items-center gap-3">
                <a href="/" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white font-bold text-xs rounded-xl border border-slate-800 transition">
                    ← Zurück zur Startseite
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-12">
        
        <div class="space-y-3 border-b border-slate-800 pb-8">
            <span class="px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-black uppercase tracking-wider">
                Vertragsbedingungen
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                Allgemeine Geschäftsbedingungen (AGB)
            </h1>
            <p class="text-sm text-slate-400">
                Nutzungsbedingungen für das BT Bautechnik Bauleiter-ERP Cockpit (B2B)
            </p>
        </div>

        <div class="space-y-8 text-sm text-slate-300 leading-relaxed font-sans">
            
            <!-- § 1 Geltungsbereich -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    § 1 Geltungsbereich & Vertragspartner
                </h2>
                <p>
                    (1) Diese Allgemeinen Geschäftsbedingungen gelten für alle Verträge über die Nutzung der Softwarelösung „BT Bautechnik ERP Cockpit“ zwischen der <strong>BT Bautechnik UG (haftungsbeschränkt)</strong>, Sollngriesbacher Str. 4, 92334 Berching (nachfolgend „Anbieter“) und dem Kunden (ausschließlich Unternehmer im Sinne des § 14 BGB, Bauträger, Generalübernehmer, Bauunternehmen und Handwerksbetriebe).
                </p>
                <p>
                    (2) Abweichende oder entgegenstehende Bedingungen des Kunden werden nicht anerkannt, es sei denn, der Anbieter stimmt ihrer Geltung ausdrücklich schriftlich zu.
                </p>
            </div>

            <!-- § 2 Leistungsgegenstand -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    § 2 Leistungsgegenstand & Verfügbarkeit
                </h2>
                <p>
                    (1) Der Anbieter stellt dem Kunden eine webbasierte Softwarelösung (SaaS) zur Verwaltung von Baustellen, Bautagebüchern, Aufmaßen, VOB-Nachträgen, Zeiterfassung und DATEV-Schnittstellen zur Verfügung.
                </p>
                <p>
                    (2) Der Anbieter bemüht sich um eine durchschnittliche jährliche Verfügbarkeit der Software von 99,0% im Jahresmittel, ausgenommen planmäßige Wartungsfenster.
                </p>
            </div>

            <!-- § 3 Datenschutz & Datensicherheit -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    § 3 Datenschutz, Geheimhaltung & Datensicherheit
                </h2>
                <p>
                    (1) Die Parteien verpflichten sich zur Einhaltung aller einschlägigen datenschutzrechtlichen Bestimmungen, insbesondere der DSGVO.
                </p>
                <p>
                    (2) Soweit der Kunde im Rahmen der Nutzung personenbezogene Daten verarbeitet, schließen die Parteien auf Wunsch eine Vereinbarung zur Auftragsverarbeitung (AVV) gemäß Art. 28 DSGVO.
                </p>
            </div>

            <!-- § 4 Schlussbestimmungen -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    § 4 Anwendbares Recht & Gerichtsstand
                </h2>
                <p>
                    (1) Es gilt das Recht der Bundesrepublik Deutschland unter Ausschluss des UN-Kaufrechts (CISG).
                </p>
                <p>
                    (2) Ausschließlicher Gerichtsstand für alle Streitigkeiten aus oder im Zusammenhang mit diesem Vertrag ist, soweit gesetzlich zulässig, der Sitz des Anbieters (Nürnberg / Regensburg).
                </p>
            </div>

        </div>

    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950 py-8 text-xs text-slate-500 mt-16">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <x-brand-logo size="small" />
            <div class="flex items-center gap-4">
                <a href="/impressum" class="hover:text-white transition">Impressum</a>
                <a href="/datenschutz" class="hover:text-white transition">Datenschutz</a>
                <a href="/" class="hover:text-white transition">Zur Startseite</a>
            </div>
        </div>
    </footer>
</x-public-layout>
