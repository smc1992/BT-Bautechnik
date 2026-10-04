{{-- FlyonUI Footer 5 adapted with BT identity, grouped navigation and demo entry. --}}
<footer id="bt-footer" class="bt-footer" aria-label="Unternehmensinformationen und weitere Navigation">
    <div class="bt-shell">
        <div class="bt-footer-invitation">
            <div><p class="bt-eyebrow"><span class="bt-live-dot" aria-hidden="true"></span> Der nächste Schritt beginnt hier.</p><h2>Mehr Überblick.<br>Vom Büro bis zur Baustelle.</h2><p>Entdecken Sie in einer persönlichen Demo, wie das BT Cockpit zu Ihren Projekten und Abläufen passt.</p></div>
            <div class="bt-footer-invitation-actions"><button type="button" wire:click="openDemoModal" class="bt-button bt-button-amber">Kostenlose Demo anfordern <span aria-hidden="true">↗</span></button><span>Persönlich. Unverbindlich. Für Ihren Betrieb.</span></div>
        </div>
        <div class="bt-footer-grid">
            <div class="bt-footer-brand">
                <a href="{{ route('home') }}" aria-label="BT Bautechnik Startseite"><x-brand-logo size="default" /></a>
                <p>Software aus der Baupraxis.<br>Für Menschen, die jeden Tag bauen.</p>
                <span class="bt-footer-location"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg> Berching, Bayern</span>
            </div>
            <nav aria-labelledby="bt-footer-product-title"><h3 id="bt-footer-product-title">Das Produkt</h3><ul><li><a href="#modul-cockpit">Projekt-Cockpit</a></li><li><a href="#modul-dailylogs">Bautagebuch</a></li><li><a href="#modul-supplements">Nachträge</a></li><li><a href="#modul-measurements">Digitales Aufmaß</a></li><li><a href="#module">Alle Funktionen <span aria-hidden="true">↗</span></a></li></ul></nav>
            <nav aria-labelledby="bt-footer-discover-title"><h3 id="bt-footer-discover-title">Gut zu wissen</h3><ul><li><a href="#story">Aus der Baupraxis</a></li><li><a href="#rechner">Ersparnis berechnen</a></li><li><a href="#faq">Fragen & Antworten</a></li><li><a href="{{ route(auth()->check() ? 'dashboard' : 'login') }}">{{ auth()->check() ? 'Zum Cockpit' : 'Kunden-Login' }} <span aria-hidden="true">↗</span></a></li></ul></nav>
            <div class="bt-footer-company"><h3>BT Bautechnik</h3><address>BT Bautechnik UG<br>(haftungsbeschränkt)<br><span>Brunnenstraße 4<br>92334 Berching</span></address><a href="{{ route('impressum') }}">Zum Impressum <span aria-hidden="true">↗</span></a></div>
        </div>
        <div class="bt-footer-bottom"><p>© {{ date('Y') }} BT Bautechnik UG (haftungsbeschränkt)</p><nav aria-label="Rechtliche Informationen"><a href="{{ route('impressum') }}">Impressum</a><a href="{{ route('datenschutz') }}">Datenschutz</a><a href="{{ route('agb') }}">AGB</a></nav><a href="#start" class="bt-back-top">Nach oben <span aria-hidden="true">↑</span></a></div>
    </div>
</footer>
