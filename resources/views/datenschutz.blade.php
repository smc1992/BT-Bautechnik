<x-public-layout>
    <x-slot name="title">
        Datenschutzerklärung | BT Bautechnik UG (haftungsbeschränkt)
    </x-slot>

    <!-- Header Navigation -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-xl border-b border-slate-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between">
            <a href="/" class="hover:opacity-90 transition-opacity">
                <x-brand-logo size="default" />
            </a>
            <div class="flex items-center gap-3">
                <a href="/" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200/80 text-slate-700 hover:text-slate-900 font-semibold text-xs sm:text-[13px] rounded-xl border border-slate-200/80 transition flex items-center gap-1.5">
                    <span>← Zurück zur Startseite</span>
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-10">
        
        <div class="space-y-3 border-b border-slate-200 pb-8">
            <span class="px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-black uppercase tracking-wider">
                DSGVO-Konformität
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">
                Datenschutzerklärung
            </h1>
            <p class="text-sm text-slate-500">
                Informationen über die Erhebung und Verarbeitung personenbezogener Daten gemäß Art. 13, 14 DSGVO
            </p>
        </div>

        <div class="space-y-6 text-sm text-slate-700 leading-relaxed font-sans">
            
            <!-- 1. Verantwortlicher -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    1. Name und Kontaktdaten des Verantwortlichen
                </h2>
                <div class="space-y-1 text-slate-800">
                    <p class="font-extrabold text-slate-900">BT Bautechnik UG (haftungsbeschränkt)</p>
                    <p>Sollngriesbacher Str. 4, 92334 Berching, Deutschland</p>
                    <p>Geschäftsführerin: Julia Haberzettel</p>
                    <p>Telefon: 08462 123456 | E-Mail: <a href="mailto:info@bt-bautechnik.de" class="text-blue-600 hover:underline">info@bt-bautechnik.de</a></p>
                </div>
            </div>

            <!-- 2. Bereitstellung der Website -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    2. Erhebung und Speicherung von Server-Logfiles
                </h2>
                <p>
                    Beim Aufrufen unserer Website werden durch den auf Ihrem Endgerät zum Einsatz kommenden Browser automatisch Informationen an den Server unserer Website gesendet. Diese Informationen werden temporär in einem sogenannten Logfile gespeichert:
                </p>
                <ul class="list-disc pl-5 space-y-1 text-xs text-slate-500">
                    <li>IP-Adresse des anfragenden Rechners</li>
                    <li>Datum und Uhrzeit des Zugriffs</li>
                    <li>Name und URL der abgerufenen Datei</li>
                    <li>Website, von der aus der Zugriff erfolgt (Referrer-URL)</li>
                    <li>Verwendeter Browser und ggf. das Betriebssystem Ihres Rechners</li>
                </ul>
                <p class="text-xs text-slate-500">
                    Die Rechtsgrundlage für die Datenverarbeitung ist Art. 6 Abs. 1 S. 1 lit. f DSGVO zur Gewährleistung eines reibungslosen Verbindungsaufbaus und der Systemsicherheit.
                </p>
            </div>

            <!-- 3. Demo-Anfragen & Kontaktaufnahme -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    3. Kontaktaufnahme & Demo-Anfrageformular
                </h2>
                <p>
                    Wenn Sie uns über das Demo-Anfrageformular oder per E-Mail Anfragen zukommen lassen, werden Ihre Angaben aus dem Anfrageformular inklusive der von Ihnen dort angegebenen Kontaktdaten (Name, Firma, Telefonnummer, E-Mail-Adresse, Angaben zu Baustellen) zwecks Bearbeitung der Anfrage und für den Fall von Anschlussfragen bei uns gespeichert.
                </p>
                <p class="text-xs text-slate-500">
                    Die Verarbeitung dieser Daten erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO zur Durchführung vorvertraglicher Maßnahmen bzw. auf Grundlage unseres berechtigten Interesses an der effektiven Bearbeitung der an uns gerichteten Anfragen (Art. 6 Abs. 1 lit. f DSGVO).
                </p>
            </div>

            <!-- 4. Rechte der Betroffenen -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    4. Ihre Rechte als betroffene Person
                </h2>
                <p>Sie haben das Recht:</p>
                <ul class="list-disc pl-5 space-y-1.5 text-xs text-slate-500">
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
    <footer class="border-t border-slate-200 bg-white py-8 text-xs text-slate-500 mt-16">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <x-brand-logo size="small" />
            <div class="flex items-center gap-4">
                <a href="/impressum" class="hover:text-slate-900 transition">Impressum</a>
                <a href="/agb" class="hover:text-slate-900 transition">AGB</a>
                <a href="/" class="hover:text-slate-900 transition">Zur Startseite</a>
            </div>
        </div>
    </footer>
</x-public-layout>
