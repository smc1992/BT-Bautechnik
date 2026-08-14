<x-public-layout>
    <x-slot name="title">
        Datenschutzerklärung | BT Bautechnik UG (haftungsbeschränkt)
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
            <span class="px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-black uppercase tracking-wider">
                DSGVO-Konformität
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                Datenschutzerklärung
            </h1>
            <p class="text-sm text-slate-400">
                Informationen über die Erhebung und Verarbeitung personenbezogener Daten gemäß Art. 13, 14 DSGVO
            </p>
        </div>

        <div class="space-y-8 text-sm text-slate-300 leading-relaxed font-sans">
            
            <!-- 1. Verantwortlicher -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    1. Name und Kontaktdaten des Verantwortlichen
                </h2>
                <div class="space-y-1 text-slate-200">
                    <p class="font-extrabold text-white">BT Bautechnik UG (haftungsbeschränkt)</p>
                    <p>Sollngriesbacher Str. 4, 92334 Berching, Deutschland</p>
                    <p>Geschäftsführerin: Julia Haberzettel</p>
                    <p>Telefon: 08462 123456 | E-Mail: <a href="mailto:info@bt-bautechnik.de" class="text-blue-400 hover:underline">info@bt-bautechnik.de</a></p>
                </div>
            </div>

            <!-- 2. Bereitstellung der Website -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    2. Erhebung und Speicherung von Server-Logfiles
                </h2>
                <p>
                    Beim Aufrufen unserer Website werden durch den auf Ihrem Endgerät zum Einsatz kommenden Browser automatisch Informationen an den Server unserer Website gesendet. Diese Informationen werden temporär in einem sogenannten Logfile gespeichert:
                </p>
                <ul class="list-disc pl-5 space-y-1 text-xs text-slate-400">
                    <li>IP-Adresse des anfragenden Rechners</li>
                    <li>Datum und Uhrzeit des Zugriffs</li>
                    <li>Name und URL der abgerufenen Datei</li>
                    <li>Website, von der aus der Zugriff erfolgt (Referrer-URL)</li>
                    <li>Verwendeter Browser und ggf. das Betriebssystem Ihres Rechners</li>
                </ul>
                <p class="text-xs text-slate-400">
                    Die Rechtsgrundlage für die Datenverarbeitung ist Art. 6 Abs. 1 S. 1 lit. f DSGVO zur Gewährleistung eines reibungslosen Verbindungsaufbaus und der Systemsicherheit.
                </p>
            </div>

            <!-- 3. Demo-Anfragen & Kontaktaufnahme -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    3. Kontaktaufnahme & Demo-Anfrageformular
                </h2>
                <p>
                    Wenn Sie uns über das Demo-Anfrageformular oder per E-Mail Anfragen zukommen lassen, werden Ihre Angaben aus dem Anfrageformular inklusive der von Ihnen dort angegebenen Kontaktdaten (Name, Firma, Telefonnummer, E-Mail-Adresse, Angaben zu Baustellen) zwecks Bearbeitung der Anfrage und für den Fall von Anschlussfragen bei uns gespeichert.
                </p>
                <p class="text-xs text-slate-400">
                    Die Verarbeitung dieser Daten erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO zur Durchführung vorvertraglicher Maßnahmen bzw. auf Grundlage unseres berechtigten Interesses an der effektiven Bearbeitung der an uns gerichteten Anfragen (Art. 6 Abs. 1 lit. f DSGVO).
                </p>
            </div>

            <!-- 4. Rechte der Betroffenen -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-3">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    4. Ihre Rechte als betroffene Person
                </h2>
                <p>Sie haben das Recht:</p>
                <ul class="list-disc pl-5 space-y-1.5 text-xs text-slate-400">
                    <li>gemäß Art. 15 DSGVO Auskunft über Ihre von uns verarbeiteten personenbezogenen Daten zu verlangen;</li>
                    <li>gemäß Art. 16 DSGVO unverzüglich die Berichtigung unrichtiger Daten zu verlangen;</li>
                    <li>gemäß Art. 17 DSGVO die Löschung Ihrer bei uns gespeicherten personenbezogenen Daten zu verlangen;</li>
                    <li>gemäß Art. 18 DSGVO die Einschränkung der Datenverarbeitung zu verlangen;</li>
                    <li>gemäß Art. 20 DSGVO Ihre Daten in einem strukturierten, gängigen Format zu erhalten (Datenübertragbarkeit);</li>
                    <li>gemäß Art. 7 Abs. 3 DSGVO Ihre einmal erteilte Einwilligung jederzeit zu widerrufen;</li>
                    <li>gemäß Art. 77 DSGVO sich bei einer Datenschutz-Aufsichtsbehörde zu beschweren.</li>
                </ul>
            </div>

        </div>

    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950 py-8 text-xs text-slate-500 mt-16">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <x-brand-logo size="small" />
            <div class="flex items-center gap-4">
                <a href="/impressum" class="hover:text-white transition">Impressum</a>
                <a href="/agb" class="hover:text-white transition">AGB</a>
                <a href="/" class="hover:text-white transition">Zur Startseite</a>
            </div>
        </div>
    </footer>
</x-public-layout>
