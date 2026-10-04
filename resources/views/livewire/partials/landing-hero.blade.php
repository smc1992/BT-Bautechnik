{{-- Adapted from FlyonUI Hero 4: split introduction, paired actions and product cards. --}}
<section id="start" class="bt-hero" aria-labelledby="bt-hero-title" tabindex="-1">
    <div class="bt-shell bt-hero-grid">
        <div class="bt-hero-copy">
            <div class="bt-eyebrow"><span class="bt-live-dot" aria-hidden="true"></span> Bauleitung. Einfach verbunden.</div>
            <h1 id="bt-hero-title">Ihre Baustelle.<br>Ihr Überblick.<br><span>Alles im Griff.</span></h1>
            <p class="bt-hero-description">Von der ersten Dokumentation bis zur Abrechnung: Bringen Sie Projekte, Aufmaße und Nachträge an einen Ort. Für weniger Büroarbeit und einen klaren Kopf auf der Baustelle.</p>
            <div class="bt-hero-actions">
                <button type="button" wire:click="openDemoModal" class="bt-button bt-button-dark">Kostenlose Demo anfordern <span aria-hidden="true">↗</span></button>
                <a href="#module" class="bt-button bt-button-light">Funktionen entdecken <span aria-hidden="true">↓</span></a>
            </div>
            <p class="bt-hero-note">Persönliche Vorführung · Unverbindlich · Im Browser</p>
            <div class="bt-practice-note">
                <img src="{{ asset('images/bauleiter-tablet-hero.jpg') }}" alt="Bauleiter mit Tablet auf der Baustelle" width="64" height="64" fetchpriority="high">
                <div><strong>Entwickelt aus der Baupraxis.</strong><span>Von BT Bautechnik in Berching, Bayern.</span></div>
            </div>
        </div>

        <div class="bt-product-stage" x-data="{ tab: 'today', tabs: ['today', 'budget', 'documents'] }">
            <div class="bt-preview-label"><span>BT COCKPIT</span><span>Interaktive Vorschau · Beispieldaten</span></div>
            <div class="bt-product-window">
                <div class="bt-window-bar"><span class="bt-window-brand"><span class="bt-monogram" aria-hidden="true">BT</span> Projektübersicht</span><span class="bt-window-status"><span aria-hidden="true"></span> Im Soll</span></div>
                <div class="bt-window-content">
                    <div class="bt-project-heading"><div><p class="bt-kicker">BAUVORHABEN / 026</p><h2>Sanierung · Bauabschnitt 02</h2></div><span class="bt-project-badge">KW 32–38</span></div>
                    <div class="bt-preview-tabs" role="tablist" aria-label="Cockpit-Vorschau" @keydown.right.prevent="tab = tabs[(tabs.indexOf(tab) + 1) % tabs.length]; $nextTick(() => $refs[tab].focus())" @keydown.left.prevent="tab = tabs[(tabs.indexOf(tab) + tabs.length - 1) % tabs.length]; $nextTick(() => $refs[tab].focus())" @keydown.home.prevent="tab = 'today'; $nextTick(() => $refs[tab].focus())" @keydown.end.prevent="tab = 'documents'; $nextTick(() => $refs[tab].focus())">
                        @foreach (['today' => 'Heute', 'budget' => 'Budget', 'documents' => 'Dokumente'] as $tab => $label)
                            <button type="button" id="preview-tab-{{ $tab }}" x-ref="{{ $tab }}" role="tab" aria-controls="preview-panel-{{ $tab }}" :aria-selected="tab === '{{ $tab }}'" :tabindex="tab === '{{ $tab }}' ? 0 : -1" :class="{ 'is-active': tab === '{{ $tab }}' }" @click="tab = '{{ $tab }}'">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="bt-preview-panels">
                        <div id="preview-panel-today" role="tabpanel" aria-labelledby="preview-tab-today" tabindex="0" x-show="tab === 'today'">
                            <div class="bt-preview-metrics"><div><span>Baufortschritt</span><strong>82<span>%</span></strong><div class="bt-progress" role="progressbar" aria-label="Beispiel-Baufortschritt" aria-valuenow="82" aria-valuemin="0" aria-valuemax="100"><span style="width: 82%"></span></div></div><div><span>Budget im Blick</span><strong>85.000<span> €</span></strong><small>Planbudget · Beispieldaten</small></div></div>
                            <div class="bt-task-heading"><h3>Ihr Tag auf der Baustelle</h3><span>3 Einträge</span></div>
                            <ul class="bt-task-list">
                                <li><span class="bt-task-symbol" aria-hidden="true">✓</span><div><strong>Bautagesbericht dokumentiert</strong><span>4 Fachkräfte · Abdichtung abgeschlossen</span></div><small>09:40</small></li>
                                <li><span class="bt-task-symbol bt-task-amber" aria-hidden="true">↗</span><div><strong>Nachtrag zur Prüfung</strong><span>Zusätzliche Leistung · Angebot vorbereitet</span></div><small>11:15</small></li>
                                <li><span class="bt-task-symbol" aria-hidden="true">✓</span><div><strong>Aufmaß erfasst</strong><span>620 m² · Bauabschnitt 02</span></div><small>14:30</small></li>
                            </ul>
                        </div>
                        <div id="preview-panel-budget" role="tabpanel" aria-labelledby="preview-tab-budget" tabindex="0" x-show="tab === 'budget'" x-cloak>
                            <div class="bt-preview-metrics"><div><span>Planbudget</span><strong>85.000<span> €</span></strong><small>Für diesen Bauabschnitt</small></div><div><span>Erfasste Kosten</span><strong>55.250<span> €</span></strong><small>65 % des Planbudgets</small></div></div>
                            <div class="bt-budget-summary"><span>Verbleibender Budgetrahmen</span><strong>29.750 €</strong></div>
                            <div class="bt-budget-chart" aria-label="Beispiel-Kosten: Material 22.100 Euro, Personal 19.350 Euro, Nachunternehmer 9.200 Euro, Geräte 4.600 Euro">
                                @foreach ([['Material', 100, '22.100 €'], ['Personal', 88, '19.350 €'], ['Nachunternehmer', 42, '9.200 €'], ['Geräte', 21, '4.600 €']] as [$label, $width, $amount])
                                    <div><span>{{ $label }}</span><div><i style="width: {{ $width }}%"></i></div><strong>{{ $amount }}</strong></div>
                                @endforeach
                            </div>
                        </div>
                        <div id="preview-panel-documents" role="tabpanel" aria-labelledby="preview-tab-documents" tabindex="0" x-show="tab === 'documents'" x-cloak>
                            <div class="bt-document-intro"><h3>Alles beim richtigen Projekt.</h3><p>Berichte, Aufmaße und Angebote an einem Ort.</p></div>
                            <ul class="bt-task-list bt-document-list">
                                @foreach ([['Bautagesbericht #042', 'Dokumentation · Heute', 'PDF'], ['Aufmaß Bauabschnitt 02', '620 m² · Erfasst', 'PDF'], ['Nachtragsangebot #003', 'Zusätzliche Leistung · Entwurf', 'PDF']] as [$title, $description, $type])
                                    <li><span class="bt-file-symbol" aria-hidden="true">{{ $type }}</span><div><strong>{{ $title }}</strong><span>{{ $description }}</span></div><span aria-hidden="true">↗</span></li>
                                @endforeach
                            </ul>
                            <p class="bt-document-footnote">Ansicht mit Beispieldaten. Die vollständigen Abläufe zeigen wir Ihnen in der persönlichen Demo.</p>
                        </div>
                    </div>
                </div>
                <div class="bt-window-footer"><span><span class="bt-live-dot" aria-hidden="true"></span> Baustelle und Büro verbunden</span><a href="#module">Module ansehen <span aria-hidden="true">→</span></a></div>
            </div>
            <div class="bt-stage-caption"><span aria-hidden="true">↳</span> Weniger suchen. Mehr Überblick.</div>
        </div>
    </div>
</section>
<div class="bt-workflow-strip">
    <div class="bt-shell"><p>Ein durchgängiger<br><strong>Projektalltag.</strong></p><div><span>01</span> Projekte steuern</div><div><span>02</span> Leistungen erfassen</div><div><span>03</span> Nachträge dokumentieren</div><div><span>04</span> Abrechnung vorbereiten</div></div>
</div>
