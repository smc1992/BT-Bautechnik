{{-- FlyonUI Navbar 3 adapted for BT, with an accessible product disclosure. --}}
@php
    $navigationModules = [
        ['cockpit', 'Projekt-Cockpit', 'Termine, Kosten und Fortschritt', '01'],
        ['dailylogs', 'Bautagebuch', 'Ihr Baustellentag dokumentiert', '02'],
        ['supplements', 'Nachträge', 'Mehrleistungen nachvollziehbar erfassen', '03'],
        ['measurements', 'Digitales Aufmaß', 'Maße und Mengen zusammenführen', '04'],
        ['contacts360', 'Kunden & Bauherren', 'Alle Ansprechpartner an einem Ort', '05'],
        ['datev', 'Abrechnung & Export', 'Baustelle und Buchhaltung verbinden', '06'],
    ];
@endphp
<a href="#start" class="ui-skip-link">Zum Inhalt</a>
<header class="bt-header" x-data="{ productOpen: false }"
        @click.outside="productOpen = false; mobileMenuOpen = false"
        @resize.window="productOpen = false; mobileMenuOpen = false"
        @focusout="if (!$el.contains($event.relatedTarget)) { productOpen = false; mobileMenuOpen = false; }"
        @keydown.escape.stop.prevent="if (productOpen) { productOpen = false; $refs.productTrigger.focus(); } else if (mobileMenuOpen) { mobileMenuOpen = false; $refs.mobileTrigger.focus(); }">
    <div class="bt-shell bt-navigation">
        <a href="{{ route('home') }}" class="bt-header-brand" aria-label="BT Bautechnik Startseite"><x-brand-logo size="default" /></a>
        <nav class="bt-desktop-nav" aria-label="Hauptnavigation">
            <button type="button" class="bt-product-trigger" x-ref="productTrigger" :aria-expanded="productOpen" aria-controls="bt-product-navigation"
                    @click="productOpen = !productOpen" @keydown.down.prevent="productOpen = true; $nextTick(() => $refs.productLinks.querySelector('a').focus())">
                Das Cockpit <svg class="bt-nav-chevron" :class="{ 'is-open': productOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <a href="#story">Baupraxis</a>
            <a href="#rechner">Ersparnis</a>
            <a href="#faq">Fragen & Antworten</a>
        </nav>
        <div class="bt-nav-actions">
            <a href="{{ route(auth()->check() ? 'dashboard' : 'login') }}" class="bt-login"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 8l4 4-4 4M14 12h7"/></svg>{{ auth()->check() ? 'Zum Cockpit' : 'Kunden-Login' }}</a>
            <button type="button" wire:click="openDemoModal" @click="productOpen = false; mobileMenuOpen = false" class="bt-button bt-button-dark bt-nav-demo">Demo anfordern <span aria-hidden="true">↗</span></button>
            <button type="button" class="bt-menu-toggle" x-ref="mobileTrigger" @click="mobileMenuOpen = !mobileMenuOpen; productOpen = false" :aria-expanded="mobileMenuOpen" :aria-label="mobileMenuOpen ? 'Menü schließen' : 'Menü öffnen'" aria-controls="bt-mobile-navigation">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path x-show="!mobileMenuOpen" d="M4 7h16M4 12h16M4 17h16"/><path x-show="mobileMenuOpen" x-cloak d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
        </div>
    </div>
    <div id="bt-product-navigation" class="bt-product-navigation bt-shell" x-ref="productLinks" x-show="productOpen" x-cloak>
        <div class="bt-product-intro"><span class="bt-kicker">DAS BT COCKPIT</span><strong>Ein System.<br>Sechs verbundene Module.</strong><p>Für Ihren Alltag zwischen Baustelle und Büro.</p><a href="#module" @click="productOpen = false">Alle Funktionen entdecken <span aria-hidden="true">↗</span></a></div>
        <nav class="bt-product-links" aria-label="Produktmodule">
            @foreach ($navigationModules as [$id, $title, $description, $number])
                <a href="#modul-{{ $id }}" @click="productOpen = false"><span class="bt-nav-module-number" aria-hidden="true">{{ $number }}</span><div><strong>{{ $title }}</strong><span>{{ $description }}</span></div><span class="bt-nav-module-arrow" aria-hidden="true">↗</span></a>
            @endforeach
        </nav>
    </div>
    <nav id="bt-mobile-navigation" class="bt-mobile-nav bt-shell" aria-label="Mobile Hauptnavigation" x-show="mobileMenuOpen" x-cloak>
        <div class="bt-mobile-nav-heading"><span class="bt-kicker">IHR WEG DURCH DAS COCKPIT</span><span>Navigation</span></div>
        <a href="#module" @click="mobileMenuOpen = false"><div><strong>Das Cockpit</strong><small>Alle sechs Module kennenlernen</small></div><span aria-hidden="true">↗</span></a>
        <a href="#story" @click="mobileMenuOpen = false">Aus der Baupraxis <span aria-hidden="true">↗</span></a>
        <a href="#rechner" @click="mobileMenuOpen = false">Ersparnis berechnen <span aria-hidden="true">↗</span></a>
        <a href="#faq" @click="mobileMenuOpen = false">Fragen & Antworten <span aria-hidden="true">↗</span></a>
        <div class="bt-mobile-nav-actions">
            <button type="button" wire:click="openDemoModal" @click="mobileMenuOpen = false" class="bt-button bt-button-dark">Kostenlose Demo anfordern <span aria-hidden="true">↗</span></button>
            <a href="{{ route(auth()->check() ? 'dashboard' : 'login') }}">{{ auth()->check() ? 'Zum Cockpit' : 'Kunden-Login' }} <span aria-hidden="true">↗</span></a>
        </div>
    </nav>
</header>
