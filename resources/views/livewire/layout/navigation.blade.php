<?php
use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
new class extends Component {
    public function logout(Logout $logout): void { $logout(); $this->redirect('/', navigate: true); }
}; ?>
@php $context = \App\Support\ProjectContext::current(); @endphp
<header class="ui-topbar" x-data="{ mobileMenu: false }" wire:key="ui-topbar">
    <a href="{{ route('dashboard') }}" wire:navigate class="ui-brand" aria-label="BT Bautechnik · Zur Übersicht"><x-application-logo /></a>
    <div class="ui-topbar-context">
        @if($context)
            <a href="{{ route('projects.show', $context) }}" wire:navigate class="ui-context-link"><x-ui-icon name="folder" /><span>{{ $context->name }}</span></a>
            <a href="{{ route('dashboard', ['clear_project' => 1]) }}" wire:navigate class="ui-icon-button" aria-label="Projektkontext aufheben"><x-ui-icon name="close" /></a>
        @else
            <span class="text-sm text-slate-500 hidden lg:block">Ihr Bauleiter-Cockpit</span>
        @endif
    </div>
    <div class="ui-topbar-actions">
        <button type="button" @click="$dispatch('open-cmd-palette')" class="ui-search-trigger" aria-label="Globale Suche öffnen"><x-ui-icon name="search" /><span class="hidden lg:inline">Suchen</span><kbd class="hidden lg:inline">⌘ K</kbd></button>
        <details class="ui-profile hidden md:block">
            <summary class="ui-button ui-button-secondary">{{ auth()->user()->name }}</summary>
            <div class="ui-profile-menu">
                <a href="{{ route('profile') }}" wire:navigate>Mein Profil</a>
                <a href="{{ route('company-settings') }}" wire:navigate>Firmeneinstellungen</a>
                <button type="button" wire:click="logout">Abmelden</button>
            </div>
        </details>
        <button type="button" class="ui-icon-button lg:hidden" @click="mobileMenu = true" aria-label="Modulnavigation öffnen" :aria-expanded="mobileMenu"><x-ui-icon name="menu" /></button>
    </div>
    <div x-show="mobileMenu" x-cloak data-ui-dialog role="dialog" aria-modal="true" aria-label="Modulnavigation" class="ui-mobile-menu lg:hidden" @click.self="mobileMenu = false" @keydown.escape.stop="mobileMenu = false">
        <div class="ui-mobile-menu-panel">
            <div class="flex items-center justify-between mb-4"><h2 class="text-lg font-semibold">Navigation</h2><button type="button" class="ui-icon-button" data-dialog-close @click="mobileMenu = false" aria-label="Navigation schließen"><x-ui-icon name="close" /></button></div>
            @if($context)
                <div class="ui-card mb-4"><p class="text-sm text-slate-500">Ausgewählte Baustelle</p><a href="{{ route('projects.show', $context) }}" wire:navigate class="ui-nav-item"><x-ui-icon name="folder" /><span>{{ $context->name }}</span></a><a href="{{ route('dashboard', ['clear_project' => 1]) }}" wire:navigate class="ui-nav-item">Alle Baustellen anzeigen</a></div>
            @endif
            <x-ui-navigation />
            <a href="{{ route('profile') }}" wire:navigate class="ui-nav-item mt-4">Mein Profil</a>
            <button type="button" wire:click="logout" class="ui-nav-item">Abmelden</button>
        </div>
    </div>
</header>
