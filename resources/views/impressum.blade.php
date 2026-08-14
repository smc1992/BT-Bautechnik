<x-public-layout>
    <x-slot name="title">
        Impressum | BT Bautechnik UG (haftungsbeschränkt)
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
            <span class="px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-black uppercase tracking-wider">
                Rechtliche Angaben
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                Impressum
            </h1>
            <p class="text-sm text-slate-400">
                Angaben gemäß § 5 Digitale-Dienste-Gesetz (DDG) / § 55 RStV
            </p>
        </div>

        <div class="space-y-8 text-sm text-slate-300 leading-relaxed font-sans">
            
            <!-- Company Info Box -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-4">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    Diensteanbieter / Betreiber
                </h2>
                <div class="space-y-1 text-slate-200">
                    <p class="font-extrabold text-base text-white">BT Bautechnik UG (haftungsbeschränkt)</p>
                    <p>Sollngriesbacher Str. 4</p>
                    <p>92334 Berching</p>
                    <p>Deutschland</p>
                </div>
            </div>

            <!-- Vertretungsberechtigte Person -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-4">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    Vertretungsberechtigte Geschäftsführung
                </h2>
                <p>
                    <strong>Geschäftsführerin:</strong> Julia Haberzettel
                </p>
            </div>

            <!-- Kontakt -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-4">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    Kontaktmöglichkeiten
                </h2>
                <div class="space-y-1.5">
                    <p><strong>Telefon:</strong> 08462 123456</p>
                    <p><strong>E-Mail:</strong> <a href="mailto:info@bt-bautechnik.de" class="text-blue-400 hover:underline font-bold">info@bt-bautechnik.de</a></p>
                    <p><strong>Website:</strong> <a href="https://bautechnik-bt.de" class="text-blue-400 hover:underline">www.bautechnik-bt.de</a></p>
                </div>
            </div>

            <!-- Register & Steuernummer -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-4">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    Registereintrag & Umsatzsteuer-Identifikationsnummer
                </h2>
                <div class="space-y-1.5">
                    <p><strong>Handelsregister:</strong> Amtsgericht Nürnberg / Regensburg</p>
                    <p><strong>Registernummer:</strong> HRB 12345</p>
                    <p><strong>Umsatzsteuer-Identifikationsnummer gem. § 27a UStG:</strong> DE345678901</p>
                    <p><strong>Steuernummer:</strong> 110/123/45678</p>
                </div>
            </div>

            <!-- Haftungsausschluss -->
            <div class="bg-slate-900/60 backdrop-blur-md p-6 sm:p-8 rounded-3xl border border-slate-800/80 space-y-4">
                <h2 class="text-lg font-black text-white border-b border-slate-800 pb-2">
                    Haftung für Inhalte & Urheberrecht
                </h2>
                <div class="space-y-3 text-xs text-slate-400 leading-relaxed">
                    <p>
                        Als Diensteanbieter sind wir gemäß § 7 Abs.1 DDG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.
                    </p>
                    <p>
                        Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.
                    </p>
                </div>
            </div>

        </div>

    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950 py-8 text-xs text-slate-500 mt-16">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <x-brand-logo size="small" />
            <div class="flex items-center gap-4">
                <a href="/datenschutz" class="hover:text-white transition">Datenschutz</a>
                <a href="/agb" class="hover:text-white transition">AGB</a>
                <a href="/" class="hover:text-white transition">Zur Startseite</a>
            </div>
        </div>
    </footer>
</x-public-layout>
