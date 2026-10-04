@php
    $contextId = \App\Support\ProjectContext::id();
    $searchItems = [];
    foreach(config('ui.navigation') as $category => $items) {
        foreach($items as [$route, $label, $icon]) {
            $searchItems[] = ['title' => $label, 'category' => $category, 'url' => \App\Support\ProjectContext::url($route, $contextId)];
        }
    }
    foreach(\App\Models\Project::whereIn('status', ['active', 'draft', 'paused'])->orderBy('name')->get(['id', 'name', 'city_street']) as $project) {
        $searchItems[] = ['title' => $project->name, 'category' => 'Projekt', 'detail' => $project->city_street, 'url' => route('projects.show', $project)];
    }
@endphp
<div x-data="{
        open: false, query: '', index: 0, items: @js($searchItems),
        get results() { const q = this.query.trim().toLocaleLowerCase('de'); return this.items.filter(item => !q || [item.title, item.category, item.detail || ''].join(' ').toLocaleLowerCase('de').includes(q)); },
        show() { this.query = ''; this.index = 0; this.open = true; this.$nextTick(() => this.$refs.search.focus()); },
        move(direction) { if (!this.results.length) return; this.index = (this.index + direction + this.results.length) % this.results.length; this.$nextTick(() => this.$refs.results.querySelectorAll('a')[this.index]?.scrollIntoView({block: 'nearest'})); },
        choose() { const link = this.$refs.results.querySelectorAll('a')[this.index]; if (link) link.click(); }
     }"
     @open-cmd-palette.window="show()"
     @keydown.window="if (($event.metaKey || $event.ctrlKey) && $event.key.toLowerCase() === 'k') { $event.preventDefault(); show(); }"
     @keydown.escape.stop="open = false"
     x-show="open" x-cloak data-ui-dialog role="dialog" aria-modal="true" aria-labelledby="ui-search-title"
     class="ui-search-backdrop" @click.self="open = false">
    <div class="ui-search-dialog">
        <div class="ui-search-header">
            <x-ui-icon name="search" />
            <div class="flex-1 min-w-0">
                <h2 id="ui-search-title" class="sr-only">Module und Projekte suchen</h2>
                <label class="sr-only" for="ui-global-search">Suchbegriff</label>
                <input id="ui-global-search" x-ref="search" x-model="query" @input="index = 0" type="search" role="combobox" aria-autocomplete="list" :aria-expanded="open" autocomplete="off" placeholder="Projekt oder Modul suchen …" :aria-activedescendant="results.length ? 'ui-result-' + index : null" aria-controls="ui-search-results" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="choose()">
            </div>
            <button type="button" class="ui-icon-button" data-dialog-close @click="open = false" aria-label="Suche schließen"><x-ui-icon name="close" /></button>
        </div>
        <div id="ui-search-results" x-ref="results" role="listbox" aria-label="Suchergebnisse" class="ui-search-results">
            <template x-for="(item, i) in results" :key="item.url">
                <a :id="'ui-result-' + i" :href="item.url" role="option" :aria-selected="index === i" wire:navigate @click="open = false" @focus="index = i" :class="{'is-selected': index === i}" class="ui-search-result">
                    <div><span class="ui-search-result-title" x-text="item.title"></span><span class="ui-search-result-detail" x-text="[item.category, item.detail].filter(Boolean).join(' · ')"></span></div>
                    <x-ui-icon name="arrow" />
                </a>
            </template>
            <div x-show="results.length === 0" class="ui-empty-state"><p>Keine Treffer gefunden.</p><p class="text-sm text-slate-500">Versuchen Sie einen Projektnamen, Ort oder Modulnamen.</p></div>
        </div>
        <div class="ui-search-footer"><span>↑ ↓ Auswählen · Enter Öffnen</span><span>Esc Schließen</span></div>
    </div>
</div>
