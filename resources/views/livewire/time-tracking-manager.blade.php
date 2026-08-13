<?php

use App\Models\TimeEntry;
use App\Models\Project;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $selectedUser = 'all';
    public string $selectedProject = 'all';
    public string $selectedMonth = '';
    public string $statusFilter = 'all';

    // Entry Modal
    public bool $showModal = false;
    public ?string $editingId = null;

    public int $userId = 1;
    public ?string $projectId = null;
    public string $entryDate = '';
    public ?string $startTime = '07:00';
    public ?string $endTime = '16:30';
    public int $breakMinutes = 45;
    public float $hours = 8.75;
    public string $activityType = 'construction'; // construction, travel, preparation, regie, warranty
    public ?string $trade = 'Abdichtung & Bautenschutz';
    public ?string $description = '';

    public function mount(): void
    {
        $this->selectedMonth = date('Y-m');
        $this->entryDate = date('Y-m-d');
        $this->userId = auth()->id() ?: 1;
    }

    public function with(): array
    {
        $query = TimeEntry::with(['user', 'project']);

        if ($this->selectedUser !== 'all') {
            $query->where('user_id', $this->selectedUser);
        }

        if ($this->selectedProject !== 'all') {
            $query->where('project_id', $this->selectedProject);
        }

        if ($this->selectedMonth) {
            $query->where('entry_date', 'like', $this->selectedMonth . '%');
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $allEntries = (clone $query)->get();
        $totalHours = $allEntries->sum('hours');
        $approvedHours = $allEntries->where('status', 'approved')->sum('hours');

        return [
            'entries' => $query->orderBy('entry_date', 'desc')->paginate(15),
            'users' => User::orderBy('name', 'asc')->get(),
            'projects' => Project::orderBy('name', 'asc')->get(),
            'totalHours' => $totalHours,
            'approvedHours' => $approvedHours,
        ];
    }

    public function calculateHours(): void
    {
        if ($this->startTime && $this->endTime) {
            $start = strtotime($this->startTime);
            $end = strtotime($this->endTime);
            if ($end > $start) {
                $diffMinutes = ($end - $start) / 60 - ($this->breakMinutes ?: 0);
                $this->hours = max(0, round($diffMinutes / 60, 2));
            }
        }
    }

    public function openCreateModal(): void
    {
        $this->editingId = null;
        $this->userId = auth()->id() ?: 1;
        $this->projectId = Project::first()?->id;
        $this->entryDate = date('Y-m-d');
        $this->startTime = '07:00';
        $this->endTime = '16:30';
        $this->breakMinutes = 45;
        $this->calculateHours();
        $this->activityType = 'construction';
        $this->trade = 'Abdichtung & Bautenschutz';
        $this->description = '';

        $this->showModal = true;
    }

    public function openEditModal(string $id): void
    {
        $entry = TimeEntry::findOrFail($id);
        $this->editingId = $entry->id;
        $this->userId = $entry->user_id;
        $this->projectId = $entry->project_id;
        $this->entryDate = $entry->entry_date->format('Y-m-d');
        $this->startTime = $entry->start_time ? substr($entry->start_time, 0, 5) : '07:00';
        $this->endTime = $entry->end_time ? substr($entry->end_time, 0, 5) : '16:30';
        $this->breakMinutes = $entry->break_minutes;
        $this->hours = (float)$entry->hours;
        $this->activityType = $entry->activity_type;
        $this->trade = $entry->trade;
        $this->description = $entry->description;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'userId' => 'required|exists:users,id',
            'entryDate' => 'required|date',
            'hours' => 'required|numeric|min:0.25|max:24',
        ]);

        $data = [
            'user_id' => $this->userId,
            'project_id' => $this->projectId,
            'entry_date' => $this->entryDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'break_minutes' => $this->breakMinutes,
            'hours' => $this->hours,
            'activity_type' => $this->activityType,
            'trade' => $this->trade,
            'description' => $this->description,
            'status' => 'submitted',
        ];

        if ($this->editingId) {
            TimeEntry::where('id', $this->editingId)->update($data);
            $this->dispatch('notify', 'Zeiteintrag aktualisiert!');
        } else {
            TimeEntry::create($data);
            $this->dispatch('notify', 'Zeiteintrag erfolgreich erfasst!');
        }

        $this->showModal = false;
    }

    public function approve(string $id): void
    {
        $entry = TimeEntry::findOrFail($id);
        $entry->status = 'approved';
        $entry->save();
        $this->dispatch('notify', 'Stunden für Mitarbeiter freigegeben.');
    }

    public function delete(string $id): void
    {
        TimeEntry::destroy($id);
        $this->dispatch('notify', 'Zeiteintrag gelöscht.');
    }
}; ?>

<div class="space-y-6 font-sans">
    
    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-emerald-950 text-white rounded-2xl p-6 shadow-xl border border-emerald-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 mb-2">
                <span>MiLoG-konform • Stundenzettel</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white flex items-center gap-2.5">
                <span>⏱️ Mobile Zeiterfassung & Arbeitszeiten</span>
            </h1>
            <p class="text-xs text-slate-300 mt-1">Stundenerfassung pro Mitarbeiter, Baustelle und Gewerk für Lohnbuchhaltung und Nachkalkulation.</p>
        </div>

        <button wire:click="openCreateModal" 
                class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs rounded-xl shadow-md shadow-emerald-500/20 transition flex items-center gap-2 cursor-pointer btn-press">
            <span>➕ Zeiten buchen</span>
        </button>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamte Arbeitsstunden</p>
            <p class="text-3xl font-black text-slate-900 mt-1 tabular-nums">{{ number_format($totalHours, 2, ',', '.') }} Std.</p>
            <p class="text-xs text-slate-500 mt-1">Im gewählten Zeitraum</p>
        </div>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Freigegeben für Lohn</p>
            <p class="text-3xl font-black text-emerald-600 mt-1 tabular-nums">{{ number_format($approvedHours, 2, ',', '.') }} Std.</p>
            <p class="text-xs text-emerald-700 mt-1">Vom Bauleiter bestätigt</p>
        </div>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Offene Bestätigungen</p>
            <p class="text-3xl font-black text-amber-600 mt-1 tabular-nums">{{ number_format($totalHours - $approvedHours, 2, ',', '.') }} Std.</p>
            <p class="text-xs text-amber-700 mt-1">Zur Prüfung ausstehend</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2.5 flex-1">
            <div class="flex items-center gap-1.5">
                <span class="font-bold text-slate-500">Monat:</span>
                <input wire:model.live="selectedMonth" type="month" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 font-bold text-slate-800 focus:bg-white focus:border-emerald-600">
            </div>

            <select wire:model.live="selectedUser" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 font-bold text-slate-800 focus:bg-white focus:border-emerald-600 cursor-pointer">
                <option value="all">Alle Mitarbeiter ({{ count($users) }})</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="selectedProject" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 font-bold text-slate-800 focus:bg-white focus:border-emerald-600 cursor-pointer">
                <option value="all">Alle Baustellen ({{ count($projects) }})</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 font-bold text-slate-800 focus:bg-white focus:border-emerald-600 cursor-pointer">
                <option value="all">Alle Status</option>
                <option value="submitted">Eingereicht</option>
                <option value="approved">Freigegeben</option>
            </select>
        </div>
    </div>

    <!-- Timesheet Table -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-slate-100">
                <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="p-3.5">Datum</th>
                        <th class="p-3.5">Mitarbeiter</th>
                        <th class="p-3.5">Baustelle</th>
                        <th class="p-3.5">Tätigkeit & Gewerk</th>
                        <th class="p-3.5 text-center">Uhrzeit</th>
                        <th class="p-3.5 text-right">Stunden</th>
                        <th class="p-3.5 text-center">Status</th>
                        <th class="p-3.5 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse ($entries as $e)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3.5 font-bold text-slate-900 whitespace-nowrap">
                                {{ $e->entry_date->format('d.m.Y') }}
                                <span class="text-[10px] text-slate-400 block font-normal">{{ $e->entry_date->locale('de')->isoFormat('dddd') }}</span>
                            </td>
                            <td class="p-3.5">
                                <span class="font-bold text-slate-800">👤 {{ $e->user?->name ?: 'Mitarbeiter' }}</span>
                            </td>
                            <td class="p-3.5 text-slate-700">
                                <span class="font-bold">📍 {{ $e->project?->name ?: 'Allgemein / Regie' }}</span>
                            </td>
                            <td class="p-3.5 text-slate-600">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ ucfirst($e->activity_type) }}
                                </span>
                                <span class="text-slate-500 text-[11px] block mt-0.5">{{ $e->trade ?: 'Ausführung' }}</span>
                            </td>
                            <td class="p-3.5 text-center text-slate-600 font-mono text-[11px] whitespace-nowrap">
                                {{ $e->start_time ? substr($e->start_time, 0, 5) : '--:--' }} – {{ $e->end_time ? substr($e->end_time, 0, 5) : '--:--' }}
                                <span class="text-[9px] text-slate-400 block font-sans">({{ $e->break_minutes }} Min Pause)</span>
                            </td>
                            <td class="p-3.5 text-right font-mono font-black text-slate-900 text-sm tabular-nums">
                                {{ number_format($e->hours, 2, ',', '.') }} Std.
                            </td>
                            <td class="p-3.5 text-center">
                                @if ($e->status === 'approved')
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        ✓ Genehmigt
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-800 border border-amber-200">
                                        Eingereicht
                                    </span>
                                @endif
                            </td>
                            <td class="p-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($e->status !== 'approved')
                                        <button wire:click="approve('{{ $e->id }}')" title="Stunden freigeben" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-lg border border-emerald-200 cursor-pointer btn-press">
                                            ✓ Freigabe
                                        </button>
                                    @endif
                                    <button wire:click="openEditModal('{{ $e->id }}')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg cursor-pointer btn-press">
                                        ✏️
                                    </button>
                                    <button wire:click="delete('{{ $e->id }}')" wire:confirm="Eintrag wirklich löschen?" class="px-2 py-1 text-rose-600 hover:bg-rose-50 text-xs rounded-lg cursor-pointer btn-press">
                                        ✕
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500 space-y-2">
                                <div class="text-2xl">⏱️</div>
                                <p class="font-bold">Keine Zeiteinträge für diesen Filter gefunden</p>
                                <p class="text-xs text-slate-400">Erfassen Sie neue Arbeitsstunden für Ihre Mitarbeiter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $entries->links() }}
        </div>
    </div>

    <!-- Create / Edit Time Entry Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-lg font-black text-slate-900">
                        {{ $editingId ? 'Zeiteintrag bearbeiten' : 'Arbeitszeit erfassen (MiLoG)' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="save" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Mitarbeiter *</label>
                            <select wire:model="userId" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-bold focus:bg-white focus:border-emerald-600">
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Datum *</label>
                            <input wire:model="entryDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-medium focus:bg-white focus:border-emerald-600">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle</label>
                        <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-bold focus:bg-white focus:border-emerald-600">
                            <option value="">-- Allgemein / Werkstatt / Fahrzeit --</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Beginn</label>
                            <input wire:model="startTime" wire:change="calculateHours" type="time" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 font-mono text-center focus:bg-white focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Ende</label>
                            <input wire:model="endTime" wire:change="calculateHours" type="time" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 font-mono text-center focus:bg-white focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Pause (Min)</label>
                            <input wire:model="breakMinutes" wire:change="calculateHours" type="number" step="5" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 font-mono text-center focus:bg-white focus:border-emerald-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Arbeitsstunden (Netto) *</label>
                            <input wire:model="hours" type="number" step="0.25" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-black text-emerald-900 font-mono text-sm focus:bg-white focus:border-emerald-600">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tätigkeitsart</label>
                            <select wire:model="activityType" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-medium focus:bg-white focus:border-emerald-600">
                                <option value="construction">Ausführung Bauleistung</option>
                                <option value="travel">Anfahrt / Rüstzeit</option>
                                <option value="regie">Regiearbeit nach Aufwand</option>
                                <option value="preparation">Vorbereitung / Werkstatt</option>
                                <option value="warranty">Gewährleistung / Nachbesserung</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tätigkeitsbericht / Notizen</label>
                        <textarea wire:model="description" rows="2" placeholder="z.B. Tiefgarage Abdichtung Lage 1 verlegt, 50m Hohlkehle erstellt..." class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 focus:bg-white focus:border-emerald-600"></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl shadow-md shadow-emerald-500/20 cursor-pointer btn-press">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
