@php
    $cost = (float) $proj->actualCosts->sum('cost_amount');
    $budget = (float) ($proj->budget?->total_with_buffer ?? 0);
    $usage = $budget > 0 ? $cost / $budget * 100 : null;
    $openDefects = $proj->defects->whereNotIn('status', ['behoben', 'abgenommen'])->count();
    $tabs = ['uebersicht' => 'Übersicht', 'kosten' => 'Kosten', 'tagebuch' => 'Tagebuch', 'maengel' => 'Mängel', 'dokumente' => 'Dokumente'];
@endphp
<div class="ui-project-page">
    <a href="{{ route('dashboard') }}" wire:navigate class="ui-breadcrumb"><x-ui-icon name="grid" /> Alle Baustellen <x-ui-icon name="chevron" /></a>
    <div class="ui-page-header">
        <div><p class="text-sm text-slate-500">{{ match($proj->status) { 'active' => 'In Ausführung', 'completed' => 'Abgeschlossen', 'paused' => 'Pausiert', default => 'In Planung' } }} · KW {{ $proj->start_week }}–{{ $proj->end_week }}</p><h1>{{ $proj->name }}</h1><p>{{ $proj->work_type }}{{ $proj->location ? ' · ' . $proj->location : '' }}</p></div>
        <div class="ui-project-actions">
            <a href="{{ route('daily-logs', ['project_id' => $proj->id, 'action' => 'new']) }}" wire:navigate class="ui-button ui-button-primary"><x-ui-icon name="plus" /> Tagesbericht</a>
            <details class="ui-profile">
                <summary class="ui-button ui-button-secondary">Weitere Aktionen</summary>
                <div class="ui-profile-menu">
                    <button type="button" wire:click="openQuickInvoiceModal">Rechnung erstellen</button>
                    <button type="button" wire:click="openQuickOfferModal">Angebot erstellen</button>
                    <button type="button" wire:click="openCreateDefectModal('{{ $proj->id }}')">Mangel erfassen</button>
                    <button type="button" wire:click="openQuickScheduleModal">Einsatz planen</button>
                    <button type="button" wire:click="generateWeeklyReport">Wochenbericht erstellen</button>
                    <a href="{{ route('project.abnahmeprotokoll-pdf', $proj) }}" target="_blank" rel="noopener">Abnahmeprotokoll als PDF</a>
                    <button type="button" wire:click="confirmDeleteProject('{{ $proj->id }}')" class="text-rose-700">Baustelle löschen</button>
                </div>
            </details>
        </div>
    </div>
    <nav class="ui-project-tabs" aria-label="Projektbereiche">
        @foreach($tabs as $tab => $label)
            <a href="{{ route('projects.show', ['project' => $proj->id, 'tab' => $tab]) }}" wire:navigate @if($projectTab === $tab) aria-current="page" @endif>{{ $label }}@if($tab === 'maengel' && $openDefects) <span class="ml-1">({{ $openDefects }})</span>@endif</a>
        @endforeach
    </nav>
    @if($projectTab === 'uebersicht')
        <div class="space-y-6">
            <section class="ui-kpis" aria-label="Projektkennzahlen">
                <div class="ui-card"><p class="ui-kpi-label">Geplantes Budget</p><p class="ui-kpi-value">{{ number_format($budget, 2, ',', '.') }} €</p><p class="ui-kpi-detail">{{ $budget > 0 ? 'Inklusive erfasstem Puffer' : 'Noch kein Budget erfasst' }}</p></div>
                <div class="ui-card"><p class="ui-kpi-label">Erfasste Kosten</p><p class="ui-kpi-value">{{ number_format($cost, 2, ',', '.') }} €</p><p class="ui-kpi-detail">{{ $usage !== null ? number_format($usage, 1, ',', '.') . '% des Budgets verbraucht' : 'Budgetvergleich noch nicht verfügbar' }}</p></div>
                <div class="ui-card"><p class="ui-kpi-label">Budgetabweichung</p><p class="ui-kpi-value {{ $budget > 0 && $cost > $budget ? 'text-rose-700' : '' }}">{{ $budget > 0 ? number_format(abs($budget - $cost), 2, ',', '.') . ' €' : 'Offen' }}</p><p class="ui-kpi-detail">{{ $budget <= 0 ? 'Zuerst Budget erfassen' : ($cost > $budget ? 'Über dem geplanten Budget' : 'Budget verbleibend · keine Gewinnprognose') }}</p></div>
                <div class="ui-card"><p class="ui-kpi-label">Baufortschritt</p><p class="ui-kpi-value">{{ $proj->status === 'completed' ? 'Abgeschlossen' : 'Nicht erfasst' }}</p><p class="ui-kpi-detail">{{ $proj->status === 'completed' ? 'Laut Projektstatus' : 'Kostenverbrauch ist kein Leistungsfortschritt' }}</p></div>
            </section>
            <section class="ui-card"><h2 class="text-lg font-semibold mb-4">Nächste Schritte</h2><div class="ui-task-list">
                @if($budget > 0 && $cost > $budget)<a href="{{ route('projects.show', ['project' => $proj->id, 'tab' => 'kosten']) }}" wire:navigate class="ui-task"><div><strong class="text-rose-700">Budgetüberschreitung prüfen</strong><span>{{ number_format($cost - $budget, 2, ',', '.') }} € über Budget</span></div><x-ui-icon name="arrow" /></a>@endif
                <a href="{{ route('projects.show', ['project' => $proj->id, 'tab' => 'maengel']) }}" wire:navigate class="ui-task"><div><strong>{{ $openDefects }} offene Mängel</strong><span>Fristen und Restarbeiten prüfen</span></div><x-ui-icon name="arrow" /></a>
                <a href="{{ route('daily-logs', ['project_id' => $proj->id, 'action' => 'new']) }}" wire:navigate class="ui-task"><div><strong>Heutige Arbeiten dokumentieren</strong><span>Tagesbericht mit Wetter, Personal und Vorkommnissen</span></div><x-ui-icon name="arrow" /></a>
            </div></section>
            <div class="flex flex-wrap gap-3"><a class="ui-button ui-button-secondary" href="{{ route('supplements', ['project_id' => $proj->id]) }}" wire:navigate>Nachträge</a><a class="ui-button ui-button-secondary" href="{{ route('measurements', ['project_id' => $proj->id]) }}" wire:navigate>Aufmaße</a><a class="ui-button ui-button-secondary" href="{{ route('planning', ['project_id' => $proj->id]) }}" wire:navigate>Bauzeitenplan</a></div>
        </div>
    @elseif($projectTab === 'kosten')
        <div class="space-y-6">
            @if($budget > 0 && $cost > $budget)<div class="ui-card border-rose-200 text-rose-700"><strong>{{ number_format($cost - $budget, 2, ',', '.') }} € über Budget</strong> · {{ number_format($usage, 1, ',', '.') }} % verbraucht</div>@endif
            @include('livewire.partials.project-costs')
            <a href="{{ route('subcontractor-invoices', ['project_id' => $proj->id]) }}" wire:navigate class="ui-button ui-button-secondary">Baukosten öffnen</a>
        </div>
    @elseif($projectTab === 'tagebuch')
        <section class="ui-card"><div class="flex items-center justify-between flex-wrap gap-3 mb-4"><h2 class="text-lg font-semibold">Letzte Tagesberichte</h2><a href="{{ route('daily-logs', ['project_id' => $proj->id]) }}" wire:navigate class="ui-button ui-button-secondary">Alle Berichte & Freigaben</a></div>
            <div class="ui-task-list">@forelse($proj->dailyLogs->sortByDesc('date')->take(10) as $log)<div class="ui-task"><div><strong>{{ date('d.m.Y', strtotime($log->date)) }}</strong><p class="text-sm text-slate-600 whitespace-pre-line">{{ $log->work_performed }}</p></div></div>@empty<div class="ui-empty-state"><p>Noch keine Tagesberichte.</p><p class="text-sm text-slate-500">Erfassen Sie die heutigen Arbeiten über „Tagesbericht“.</p></div>@endforelse</div>
        </section>
    @elseif($projectTab === 'maengel')
        @include('livewire.partials.project-defects')
    @elseif($projectTab === 'dokumente')
        <div class="space-y-6">@include('livewire.partials.project-photos') @include('livewire.partials.project-offers')<a href="{{ route('project-plans', ['project_id' => $proj->id]) }}" wire:navigate class="ui-button ui-button-secondary">Baupläne & Revisionen öffnen</a></div>
    @endif
</div>
