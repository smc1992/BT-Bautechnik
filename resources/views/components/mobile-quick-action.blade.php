@php $contextId = \App\Support\ProjectContext::id(); @endphp
<div x-data="{ open: false }" class="lg:hidden">
    <nav class="ui-bottom-nav" aria-label="Mobile Schnellnavigation">
        <a href="{{ route('dashboard') }}" wire:navigate @if(request()->routeIs('dashboard', 'projects.show')) aria-current="page" @endif><x-ui-icon name="grid" /><span>Baustellen</span></a>
        <a href="{{ \App\Support\ProjectContext::url('daily-logs', $contextId) }}" wire:navigate @if(request()->routeIs('daily-logs')) aria-current="page" @endif><x-ui-icon name="book" /><span>Tagebuch</span></a>
        <button type="button" @click="open = true" class="ui-quick-add" aria-label="Baustellenaktion erstellen" :aria-expanded="open"><x-ui-icon name="plus" /></button>
        <a href="{{ \App\Support\ProjectContext::url('time-tracking', $contextId) }}" wire:navigate @if(request()->routeIs('time-tracking')) aria-current="page" @endif><x-ui-icon name="clock" /><span>Zeit</span></a>
        <a href="{{ \App\Support\ProjectContext::url('defects', $contextId) }}" wire:navigate @if(request()->routeIs('defects')) aria-current="page" @endif><x-ui-icon name="warning" /><span>Mängel</span></a>
    </nav>
    <div x-show="open" x-cloak class="ui-sheet-backdrop" data-ui-dialog role="dialog" aria-modal="true" aria-labelledby="ui-quick-title" @click.self="open = false" @keydown.escape.stop="open = false">
        <div class="ui-sheet">
            <div class="flex items-center justify-between gap-3"><h2 id="ui-quick-title" class="text-lg font-semibold">Vor Ort erfassen</h2><button type="button" class="ui-icon-button" data-dialog-close @click="open = false" aria-label="Schnellaktionen schließen"><x-ui-icon name="close" /></button></div>
            <p class="text-sm text-slate-500">{{ $contextId ? \App\Models\Project::find($contextId)?->name : 'Baustelle im nächsten Schritt auswählen' }}</p>
            <div class="ui-quick-grid">
                <a href="{{ route('daily-logs', array_filter(['project_id' => $contextId, 'action' => 'new'])) }}" wire:navigate @click="open = false"><x-ui-icon name="book" /><strong>Tagesbericht</strong><span>Arbeiten dokumentieren</span></a>
                <a href="{{ $contextId ? route('projects.show', ['project' => $contextId, 'tab' => 'dokumente']) : route('dashboard') }}" wire:navigate @click="open = false"><x-ui-icon name="camera" /><strong>Foto hinzufügen</strong><span>{{ $contextId ? 'Baustelle dokumentieren' : 'Zuerst Baustelle öffnen' }}</span></a>
                <a href="{{ route('defects', array_filter(['project_id' => $contextId, 'action' => 'new'])) }}" wire:navigate @click="open = false"><x-ui-icon name="warning" /><strong>Mangel</strong><span>Ort, Frist und Beschreibung</span></a>
                <a href="{{ route('time-tracking', array_filter(['project_id' => $contextId, 'action' => 'new'])) }}" wire:navigate @click="open = false"><x-ui-icon name="clock" /><strong>Arbeitszeit</strong><span>Zeiten und Tätigkeit erfassen</span></a>
            </div>
            <details class="ui-more-actions"><summary>Weitere Aktionen</summary><a href="{{ \App\Support\ProjectContext::url('supplements', $contextId) }}" wire:navigate>Nachtrag erfassen</a><a href="{{ \App\Support\ProjectContext::url('measurements', $contextId) }}" wire:navigate>Aufmaß öffnen</a><a href="{{ \App\Support\ProjectContext::url('project-plans', $contextId) }}" wire:navigate>Baupläne öffnen</a></details>
        </div>
    </div>
</div>
