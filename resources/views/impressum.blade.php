<x-public-layout>
    <x-slot name="title">
        Impressum | BT Bautechnik UG (haftungsbeschränkt)
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
            <span class="px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-700 text-xs font-black uppercase tracking-wider">
                Rechtliche Angaben
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">
                Impressum
            </h1>
            <p class="text-sm text-slate-500">
                Angaben gemäß § 5 Digitale-Dienste-Gesetz (DDG) / § 55 RStV
            </p>
        </div>

        <div class="space-y-6 text-sm text-slate-700 leading-relaxed">
            
            <!-- Company Info Box -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    Diensteanbieter / Betreiber
                </h2>
                <div class="space-y-1 text-slate-800">
                    <p class="font-black text-base text-slate-900">BT Bautechnik UG (haftungsbeschränkt)</p>
                    <p>Sollngriesbacher Str. 4</p>
                    <p>92334 Berching</p>
                    <p>Deutschland</p>
                </div>
            </div>

            <!-- Vertretungsberechtigte Person -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    Vertretungsberechtigte Geschäftsführung
                </h2>
                <p>
                    <strong>Geschäftsführerin:</strong> Julia Haberzettel
                </p>
            </div>

            <!-- Kontakt -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    Kontaktmöglichkeiten
                </h2>
                <div class="space-y-1.5">
                    <p><strong>Telefon:</strong> 08462 123456</p>
                    <p><strong>E-Mail:</strong> <a href="mailto:info@bt-bautechnik.de" class="text-blue-600 hover:underline font-bold">info@bt-bautechnik.de</a></p>
                    <p><strong>Website:</strong> <a href="https://bautechnik-bt.de" class="text-blue-600 hover:underline">www.bautechnik-bt.de</a></p>
                </div>
            </div>

            <!-- Register & Steuernummer -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
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
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <h2 class="text-lg font-black text-slate-900 border-b border-slate-100 pb-2">
                    Haftung für Inhalte & Urheberrecht
                </h2>
                <div class="space-y-2 text-xs text-slate-500 leading-relaxed">
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
    <footer class="border-t border-slate-200 bg-white py-8 text-xs text-slate-500 mt-16">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <x-brand-logo size="small" />
            <div class="flex items-center gap-4">
                <a href="/datenschutz" class="hover:text-slate-900 transition">Datenschutz</a>
                <a href="/agb" class="hover:text-slate-900 transition">AGB</a>
                <a href="/" class="hover:text-slate-900 transition">Zur Startseite</a>
            </div>
        </div>
    </footer>
</x-public-layout>
