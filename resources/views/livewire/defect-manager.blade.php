<?php

use Livewire\Volt\Component;
use App\Models\Defect;
use App\Models\Project;
use App\Models\Contact;

new class extends Component {
    public bool $showModal = false;

    // Form
    public string $projectId = '';
    public string $assignedContactId = '';
    public string $title = '';
    public string $location = '';
    public string $description = '';
    public string $deadline = '';
    public string $priority = 'mittel';
    public string $status = 'offen';

    public function mount()
    {
        $this->deadline = date('Y-m-d', strtotime('+14 days'));
        $p = Project::first();
        if ($p) $this->projectId = $p->id;
    }

    public function getDefectsProperty()
    {
        return Defect::with(['project', 'assignedContact'])->latest()->get();
    }

    public function getProjectsProperty()
    {
        return Project::all();
    }

    public function getSubcontractorsProperty()
    {
        return Contact::where('type', 'subunternehmer')->get();
    }

    public function openCreateModal()
    {
        $this->title = '';
        $this->location = '';
        $this->description = '';
        $this->showModal = true;
    }

    public function updateStatus(string $id, string $newStatus)
    {
        Defect::where('id', $id)->update(['status' => $newStatus]);
        $this->dispatch('notify', 'Status der Mängelbeseitigung aktualisiert!');
    }

    public function saveDefect()
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        Defect::create([
            'project_id' => $this->projectId,
            'assigned_contact_id' => $this->assignedContactId ?: null,
            'title' => $this->title,
            'location' => $this->location,
            'description' => $this->description,
            'deadline' => $this->deadline ?: null,
            'priority' => $this->priority,
            'status' => $this->status,
        ]);

        $this->showModal = false;
        $this->dispatch('notify', 'Mangel erfolgreich erfasst!');
    }

    // AI VOB/B Notice Generator
    public bool $showNoticeModal = false;
    public string $noticeText = '';

    public function generateNoticeLetter(string $defectId, \App\Services\OpenAiParserService $parser)
    {
        $defect = Defect::with(['project', 'assignedContact'])->find($defectId);
        if (!$defect) return;

        try {
            $this->noticeText = $parser->generateDefectNoticeLetter([
                'project' => $defect->project?->name ?? 'Baustelle',
                'contact' => $defect->assignedContact?->company_name ?? $defect->assignedContact?->name ?? 'Subunternehmer',
                'title' => $defect->title,
                'location' => $defect->location,
                'description' => $defect->description,
                'deadline' => $defect->deadline ? date('d.m.Y', strtotime($defect->deadline)) : '7 Tage',
            ]);
            $this->showNoticeModal = true;
            $this->dispatch('notify', '✨ VOB/B Mängelrüge per KI erzeugt!');
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler bei Erstellung der Mängelrüge: ' . $e->getMessage());
        }
    }
}; ?>

<div class="space-y-8 font-sans">
    <!-- Header -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Mängelmanagement & Abnahmeprotokolle</h2>
            <p class="text-xs text-slate-500">Erfassung von Restarbeiten, Mängelbeseitigungsfristen & Zuordnung an Nachunternehmer.</p>
        </div>

        <button wire:click="openCreateModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10 whitespace-nowrap">
            + Mangel / Restarbeit erfassen
        </button>
    </div>

    <!-- Defects Grid / Kanban -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($this->defects as $defect)
            <div wire:key="{{ $defect->id }}" class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between space-y-4 hover:shadow-md transition">
                <div class="space-y-3">
                    <div class="flex justify-between items-start gap-2">
                        <span class="px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider border shadow-2xs {{ $defect->priority_badge_class }}">
                            Prio: {{ ucfirst($defect->priority) }}
                        </span>

                        <select wire:change="updateStatus('{{ $defect->id }}', $event.target.value)" class="text-xs font-bold rounded-lg px-2 py-0.5 border border-slate-200 focus:outline-none {{ $defect->status_badge_class }}">
                            <option value="offen" {{ $defect->status === 'offen' ? 'selected' : '' }}>🔴 Offen</option>
                            <option value="in_bearbeitung" {{ $defect->status === 'in_bearbeitung' ? 'selected' : '' }}>🔵 In Bearbeitung</option>
                            <option value="behoben" {{ $defect->status === 'behoben' ? 'selected' : '' }}>🟡 Behoben (Prüfung)</option>
                            <option value="abgenommen" {{ $defect->status === 'abgenommen' ? 'selected' : '' }}>🟢 Abgenommen</option>
                        </select>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">{{ $defect->title }}</h3>
                        <p class="text-xs text-slate-500 font-medium">Baustelle: <span class="text-slate-900 font-bold">{{ $defect->project->name }}</span></p>
                        @if ($defect->location)
                            <p class="text-xs text-slate-500 font-medium">Ort: {{ $defect->location }}</p>
                        @endif
                    </div>

                    <p class="text-xs text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200/80 leading-relaxed">{{ $defect->description }}</p>
                </div>

                <div class="pt-3 border-t border-slate-100 space-y-2">
                    @if ($defect->assignedContact)
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Zuständig:</span>
                            <span class="font-bold text-slate-900">{{ $defect->assignedContact->display_name }}</span>
                        </div>
                    @endif

                    @if ($defect->deadline)
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Frist zur Behebung:</span>
                            <span class="font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">
                                {{ date('d.m.Y', strtotime($defect->deadline)) }}
                            </span>
                        </div>
                    @endif

                    <div class="pt-2">
                        <button wire:click="generateNoticeLetter('{{ $defect->id }}')" class="w-full py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold text-xs rounded-lg border border-purple-200 transition flex items-center justify-center gap-1">
                            📄 KI VOB/B Mängelrüge erzeugen
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/80 rounded-2xl text-center space-y-3">
                <p class="text-base font-bold text-slate-900">Keine offenen Mängel erfasst</p>
                <p class="text-xs text-slate-500">Klicken Sie auf "+ Mangel / Restarbeit erfassen" zur Dokumentation.</p>
            </div>
        @endforelse
    </div>

    <!-- Create Modal -->
    @if ($showModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Mangel / Restarbeit aufnehmen</h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <form wire:submit="saveDefect" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Baustelle</label>
                        <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                            @foreach ($this->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Titel des Mangels</label>
                        <input wire:model="title" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. Nachdichtung Wandanschluss TG" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Genaue Lage / Ort</label>
                            <input wire:model="location" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. TG 1. OG Westwand">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Priorität</label>
                            <select wire:model="priority" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                                <option value="niedrig">Niedrig</option>
                                <option value="mittel">Mittel</option>
                                <option value="hoch">Hoch</option>
                                <option value="kritisch">Kritisch</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Zuständiger Subunternehmer / Partner</label>
                        <select wire:model="assignedContactId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                            <option value="">Kein Subunternehmer (Eigenleistung)</option>
                            @foreach ($this->subcontractors as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->display_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Beseitigungsfrist</label>
                        <input wire:model="deadline" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Mängelbeschreibung & Maßnahmen</label>
                        <textarea wire:model="description" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Beschreibung der Mangelerscheinung..." required></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Mangel erfassen</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- KI VOB/B Mängelrüge Modal -->
    @if ($showNoticeModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📄</span>
                        <h3 class="text-base font-extrabold text-white">Rechtssichere Mängelrüge nach VOB/B § 13</h3>
                    </div>
                    <button wire:click="$set('showNoticeModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-mono text-slate-800 leading-relaxed max-h-96 overflow-y-auto whitespace-pre-wrap selection:bg-purple-100">{{ $noticeText }}</div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-xs text-slate-500">Formular inkl. Fristsetzung & Ersatzvornahme</span>
                        <div class="flex space-x-3">
                            <button type="button" wire:click="$set('showNoticeModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Schließen</button>
                            <button type="button" onclick="navigator.clipboard.writeText(`{{ addslashes($noticeText) }}`); alert('Mängelrüge in Zwischenablage kopiert!');" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-500/20">
                                📋 In Zwischenablage kopieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
