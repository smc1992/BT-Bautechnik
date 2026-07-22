<?php

use Livewire\Volt\Component;
use App\Models\DailyLog;
use App\Models\Project;

new class extends Component {
    public ?string $selectedProjectId = null;
    public bool $showModal = false;

    // Form
    public string $projectId = '';
    public string $date = '';
    public string $weather = 'Sonnig';
    public string $temperature = '20°C';
    public int $workersCount = 2;
    public string $workPerformed = '';
    public string $specialOccurrences = '';

    public function mount()
    {
        $this->date = date('Y-m-d');
        $firstProject = Project::first();
        if ($firstProject) {
            $this->projectId = $firstProject->id;
            $this->selectedProjectId = $firstProject->id;
        }
    }

    public function getProjectsProperty()
    {
        return Project::all();
    }

    public function getDailyLogsProperty()
    {
        return DailyLog::with('project')
            ->when($this->selectedProjectId, fn($q) => $q->where('project_id', $this->selectedProjectId))
            ->orderBy('date', 'desc')
            ->get();
    }

    public function openCreateModal()
    {
        $this->workPerformed = '';
        $this->specialOccurrences = '';
        $this->showModal = true;
    }

    public function saveLog()
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'date' => 'required|date',
            'workPerformed' => 'required|string|min:5',
        ]);

        DailyLog::create([
            'project_id' => $this->projectId,
            'date' => $this->date,
            'weather' => $this->weather,
            'temperature' => $this->temperature,
            'workers_count' => $this->workersCount,
            'work_performed' => $this->workPerformed,
            'special_occurrences' => $this->specialOccurrences,
        ]);

        $this->showModal = false;
        $this->dispatch('notify', 'Bautagebuch-Eintrag erfolgreich gespeichert!');
    }
}; ?>

<div class="space-y-8 font-sans">
    <!-- Header Bar -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Bautagebuch & Regieberichte</h2>
            <p class="text-xs text-slate-500">Tägliche Dokumentation von Wetter, Baufortschritt, Personal und Besonderheiten.</p>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
            <select wire:model.live="selectedProjectId" class="bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:border-blue-600">
                <option value="">Alle Baustellen anzeigen</option>
                @foreach ($this->projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>

            <button wire:click="openCreateModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10 whitespace-nowrap">
                + Tagebucheintrag
            </button>
        </div>
    </div>

    <!-- Timeline Entries List -->
    <div class="space-y-4">
        @forelse ($this->dailyLogs as $log)
            <div wire:key="{{ $log->id }}" class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 bg-blue-50 text-blue-700 font-extrabold text-xs rounded-full border border-blue-200">
                            {{ date('d.m.Y', strtotime($log->date)) }}
                        </span>
                        <h3 class="font-bold text-slate-900 text-base tracking-tight">{{ $log->project->name }}</h3>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-slate-600 font-medium">
                        <span class="bg-slate-100 px-2.5 py-1 rounded-lg">☀️ {{ $log->weather }} ({{ $log->temperature }})</span>
                        <span class="bg-slate-100 px-2.5 py-1 rounded-lg">👷 {{ $log->workers_count }} Mitarbeiter vor Ort</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Geleistete Arbeiten / Gewerk</h4>
                    <p class="text-sm text-slate-800 leading-relaxed whitespace-pre-line">{{ $log->work_performed }}</p>
                </div>

                @if ($log->special_occurrences)
                    <div class="bg-amber-50 border border-amber-200 p-3.5 rounded-xl space-y-1">
                        <h4 class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                            <span>⚠️</span> Vorkommnisse / Störungen
                        </h4>
                        <p class="text-xs text-amber-800 leading-relaxed">{{ $log->special_occurrences }}</p>
                    </div>
                @endif
            </div>
        @empty
            <div class="py-16 bg-white border border-slate-200/80 rounded-2xl text-center space-y-3">
                <p class="text-base font-bold text-slate-900">Keine Tagebucheinträge vorhanden</p>
                <p class="text-xs text-slate-500">Erfassen Sie Ihren ersten Tagesbericht über den Button "+ Tagebucheintrag".</p>
            </div>
        @endforelse
    </div>

    <!-- Create Modal -->
    @if ($showModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Neuen Tagesbericht verfassen</h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <form wire:submit="saveLog" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Baustelle</label>
                        <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                            @foreach ($this->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Datum</label>
                            <input wire:model="date" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Wetter</label>
                            <select wire:model="weather" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                                <option value="Sonnig">Sonnig</option>
                                <option value="Bewölkt">Bewölkt</option>
                                <option value="Regen">Regen</option>
                                <option value="Frost/Schnee">Frost/Schnee</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Arbeiter (Anzahl)</label>
                            <input wire:model="workersCount" type="number" min="1" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Geleistete Arbeiten</label>
                        <textarea wire:model="workPerformed" rows="4" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Details zu Fortschritt, Materialverbrauch und Monteuren..." required></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Vorkommnisse / Störungen (Optional)</label>
                        <textarea wire:model="specialOccurrences" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Verzögerungen, Behinderungen durch Dritte, Materialmangel..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Eintrag speichern</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
