{{-- FlyonUI Bento Grid 3 adapted to the existing Tailwind/Livewire design system. --}}
<section id="module" class="bt-modules" aria-labelledby="bt-modules-title">
    <div class="bt-shell">
        <div class="bt-section-heading" data-reveal><div><p class="bt-eyebrow">Ein System. Ihr gesamter Baualltag.</p><h2 id="bt-modules-title">Alles hängt zusammen.<br><span>Ihre Software auch.</span></h2></div><p>Vom ersten Kontakt bis zum letzten Aufmaß: Sechs Kernmodule verbinden Ihre Baustelle mit dem Büro.</p></div>
        <div class="bt-bento" data-reveal-group>
            <article id="modul-cockpit" class="bt-bento-card bt-bento-featured" tabindex="-1">
                <div class="bt-card-top"><span class="bt-kicker">01 / PROJEKTSTEUERUNG</span><span class="bt-featured-tag">Ihr zentraler Überblick</span></div>
                <div><h3>Die ganze Baustelle.<br>Ein Cockpit.</h3><p>Projekte, Termine und Kosten zusammenführen. So sehen Sie, wo Ihr Bauvorhaben steht und was als Nächstes ansteht.</p></div>
                <div class="bt-mini-project"><div><span>Bauabschnitt 02</span><span>82 %</span></div><div class="bt-progress"><span style="width:82%"></span></div><div><span>Dokumentation</span><span>Aufmaß</span><span>Nachträge</span></div><small>Illustration mit Beispieldaten</small></div>
                <button type="button" wire:click="openDemoModal" class="bt-card-link">Cockpit in der Demo erleben <span aria-hidden="true">↗</span></button>
            </article>
            @foreach ([
                ['02 / DOKUMENTATION', 'Bautagebuch', 'Leistungen, Fotos und den Tagesverlauf direkt dem Projekt zuordnen.', 'Tagesbericht statt Zettelwirtschaft', 'dailylogs'],
                ['03 / MEHRLEISTUNGEN', 'Nachträge', 'Zusätzliche Leistungen festhalten und als nachvollziehbares Angebot vorbereiten.', 'Von der Leistung zum Angebot', 'supplements'],
                ['04 / MENGENERMITTLUNG', 'Digitales Aufmaß', 'Maße und Mengen strukturiert erfassen und für die Abrechnung zusammenstellen.', 'Mengen nachvollziehbar erfassen', 'measurements'],
                ['05 / KONTAKTE', 'Kunden & Bauherren', 'Ansprechpartner, Projekte und Notizen an einem zentralen Ort bündeln.', 'Alle Beteiligten im Blick', 'contacts360'],
                ['06 / BUCHHALTUNG', 'Abrechnung & Export', 'Rechnungen und Kosten überblicken und Daten für die Buchhaltung exportieren.', 'Baustelle und Büro verbinden', 'datev'],
            ] as [$number, $title, $description, $benefit, $key])
                <article id="modul-{{ $key }}" class="bt-bento-card bt-bento-{{ $key }}" tabindex="-1">
                    <div class="bt-card-top"><span class="bt-kicker">{{ $number }}</span><span class="bt-card-mark" aria-hidden="true">{{ $loop->iteration < 3 ? '↗' : '+' }}</span></div>
                    <div><h3>{{ $title }}</h3><p>{{ $description }}</p></div>
                    <div class="bt-card-benefit"><span aria-hidden="true">✓</span> {{ $benefit }}</div>
                    <button type="button" wire:click="openDemoModal" class="bt-card-link">{{ $title }} kennenlernen <span aria-hidden="true">↗</span></button>
                </article>
            @endforeach
        </div>
    </div>
</section>
