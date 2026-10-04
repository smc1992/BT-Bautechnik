{{-- FlyonUI FAQ layout adapted with native keyboard-accessible disclosure controls. --}}
<section id="faq" class="bt-faq-section" aria-labelledby="bt-faq-title">
    <div class="bt-shell bt-faq-grid">
        <div data-reveal><p class="bt-eyebrow">Gut zu wissen</p><h2 id="bt-faq-title">Noch Fragen?<br>Hier geht’s weiter.</h2><p>Ein erster Überblick. Ihre konkreten Abläufe besprechen wir gerne in einer persönlichen Vorführung.</p><button type="button" wire:click="openDemoModal" class="bt-card-link">Persönliche Demo anfragen <span aria-hidden="true">↗</span></button></div>
        <div class="bt-faq-list">
            @foreach ([
                ['Kann ich BT Cockpit auf der Baustelle nutzen?', 'Ja. Sie können BT Cockpit im Browser auf dem Smartphone, Tablet oder Laptop öffnen. Eine Installation aus dem App Store ist dafür nicht erforderlich.'],
                ['Wie werden zusätzliche Leistungen dokumentiert?', 'Sie erfassen die zusätzliche Leistung im passenden Projekt und bereiten daraus ein Nachtragsangebot vor. In der Demo zeigen wir Ihnen den Ablauf von der Erfassung bis zum PDF.'],
                ['Wie unterstützt die Software die Buchhaltung?', 'Rechnungen und Kosten werden im Projekt erfasst. Ein CSV-Export unterstützt die Übergabe an die Buchhaltung. Das passende Exportformat und Ihren konkreten Ablauf stimmen wir in der Demo ab.'],
                ['Wie läuft eine persönliche Demo ab?', 'Sie senden uns Ihre Anfrage mit Ihren Kontaktdaten. Wir melden uns zur Terminabstimmung und zeigen Ihnen die Module, die zu Ihrem Unternehmen passen. Die Anfrage ist kostenlos und unverbindlich.'],
            ] as [$question, $answer])
                <details class="bt-faq-item" @if ($loop->first) open @endif><summary>{{ $question }}<span aria-hidden="true">+</span></summary><p>{{ $answer }}</p></details>
            @endforeach
        </div>
    </div>
</section>
