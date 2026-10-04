@php $tasks = $this->todayTasks; $stats = $this->stats; $taskCount = count($tasks['defects']) + count($tasks['approvals']) + count($tasks['budgets']); @endphp
<div class="ui-page-header">
    <div><h1>Baustellen im Blick</h1><p>Was heute Ihre Aufmerksamkeit braucht.</p></div>
    <button type="button" wire:click="openCreateProject" class="ui-button ui-button-primary"><x-ui-icon name="plus" /> Neue Baustelle</button>
</div>
<section class="ui-card" aria-labelledby="today-tasks-title">
    <div class="flex items-center justify-between gap-3 mb-4"><h2 id="today-tasks-title" class="text-lg font-semibold">Heute zu erledigen</h2><span class="text-sm text-slate-500">{{ $taskCount }} {{ $taskCount === 1 ? 'Hinweis' : 'Hinweise' }}</span></div>
    <div class="ui-task-list">
        @foreach($tasks['defects']->take(3) as $defect)
            @if($defect->project)
            <a href="{{ route('projects.show', ['project' => $defect->project, 'tab' => 'maengel']) }}" wire:navigate class="ui-task"><div><strong>{{ $defect->title }}</strong><span>{{ $defect->project->name }} · Frist überschritten seit {{ date('d.m.Y', strtotime($defect->deadline)) }}</span></div><x-ui-icon name="arrow" /></a>
            @endif
        @endforeach
        @foreach($tasks['budgets']->take(3) as $project)
            <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'kosten']) }}" wire:navigate class="ui-task"><div><strong class="text-rose-700">{{ number_format($project->actual_costs_sum_cost_amount - $project->budget->total_with_buffer, 2, ',', '.') }} € über Budget</strong><span>{{ $project->name }} · Kosten prüfen</span></div><x-ui-icon name="arrow" /></a>
        @endforeach
        @foreach($tasks['approvals']->take(3) as $share)
            @if($share->dailyLog?->project)
            <a href="{{ route('daily-logs', ['project_id' => $share->dailyLog->project_id]) }}" wire:navigate class="ui-task"><div><strong>Tagesbericht wartet auf Freigabe</strong><span>{{ $share->dailyLog->project->name }} · {{ date('d.m.Y', strtotime($share->dailyLog->date)) }}</span></div><x-ui-icon name="arrow" /></a>
            @endif
        @endforeach
        @if($taskCount === 0)
            <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Keine überfälligen Mängel, offenen Berichtsfreigaben oder Budgetüberschreitungen erfasst.</div>
        @endif
    </div>
    @if(count($tasks['defects']) > 3 || count($tasks['approvals']) > 3 || count($tasks['budgets']) > 3)
        <div class="flex flex-wrap gap-3 mt-4 text-sm">
            @if(count($tasks['defects']) > 3)<a class="underline" href="{{ route('defects', ['clear_project' => 1]) }}" wire:navigate>Alle {{ count($tasks['defects']) }} überfälligen Mängel ansehen</a>@endif
            @if(count($tasks['approvals']) > 3)<a class="underline" href="{{ route('daily-logs', ['clear_project' => 1]) }}" wire:navigate>Alle Berichte und Freigaben ansehen</a>@endif
            @if(count($tasks['budgets']) > 3)<p>{{ count($tasks['budgets']) }} Baustellen über Budget – in der Übersicht prüfen.</p>@endif
        </div>
    @endif
</section>
<section class="ui-kpis" aria-label="Kennzahlen aller Baustellen">
    <div class="ui-card"><p class="ui-kpi-label">Aktive Baustellen</p><p class="ui-kpi-value">{{ $stats['active_projects'] }}</p><p class="ui-kpi-detail">Aktuell in Ausführung</p></div>
    <div class="ui-card"><p class="ui-kpi-label">Geplantes Budget</p><p class="ui-kpi-value">{{ number_format($stats['total_budget'], 2, ',', '.') }} €</p><p class="ui-kpi-detail">Alle Baustellen · inklusive erfasster Puffer</p></div>
    <div class="ui-card"><p class="ui-kpi-label">Erfasste Kosten</p><p class="ui-kpi-value">{{ number_format($stats['total_costs'], 2, ',', '.') }} €</p><p class="ui-kpi-detail">Material, Arbeit und weitere Belege</p></div>
    <div class="ui-card"><p class="ui-kpi-label">{{ $stats['remaining_budget'] < 0 ? 'Budgetüberschreitung' : 'Verbleibendes Budget' }}</p><p class="ui-kpi-value {{ $stats['remaining_budget'] < 0 ? 'text-rose-700' : '' }}">{{ number_format(abs($stats['remaining_budget']), 2, ',', '.') }} €</p><p class="ui-kpi-detail">{{ $stats['total_budget'] > 0 ? number_format(abs($stats['margin']), 1, ',', '.') . '% ' . ($stats['remaining_budget'] < 0 ? 'über Gesamtbudget' : 'des Budgets verbleibend') : 'Budgetplanung noch offen' }}</p></div>
</section>
