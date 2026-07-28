<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\Contact;
use App\Models\WorkerSchedule;
use Carbon\Carbon;

new class extends Component {
    public string $selectedWeekStart;
    public bool $showModal = false;

    // Form fields
    public ?string $editingId = null;
    public string $projectId = '';
    public string $workerType = 'mitarbeiter'; // mitarbeiter, subunternehmer
    public string $contactId = '';
    public string $workerName = '';
    public string $date = '';
    public string $shiftType = 'ganztags';
    public string $notes = '';

    public function mount()
    {
        $this->selectedWeekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->date = Carbon::now()->format('Y-m-d');
    }

    public function previousWeek()
    {
        $this->selectedWeekStart = Carbon::parse($this->selectedWeekStart)->subWeek()->format('Y-m-d');
    }

    public function nextWeek()
    {
        $this->selectedWeekStart = Carbon::parse($this->selectedWeekStart)->addWeek()->format('Y-m-d');
    }

    public function resetToCurrentWeek()
    {
        $this->selectedWeekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
    }

    public function openModal(?string $projectId = null, ?string $dateStr = null)
    {
        $this->resetForm();
        if ($projectId) {
            $this->projectId = $projectId;
        } else {
            $firstP = Project::where('status', 'active')->first();
            $this->projectId = $firstP?->id ?? '';
        }

        if ($dateStr) {
            $this->date = $dateStr;
        } else {
            $this->date = Carbon::now()->format('Y-m-d');
        }

        $this->showModal = true;
    }

    public function editSchedule(string $id)
    {
        $sched = WorkerSchedule::find($id);
        if (!$sched) return;

        $this->editingId = $sched->id;
        $this->projectId = $sched->project_id;
        $this->contactId = $sched->contact_id ?? '';
        $this->workerName = $sched->worker_name;
        $this->workerType = $sched->worker_type;
        $this->date = $sched->date->format('Y-m-d');
        $this->shiftType = $sched->shift_type;
        $this->notes = $sched->notes ?? '';
        $this->showModal = true;
    }

    public function saveSchedule()
    {
        $this->validate([
            'projectId' => 'required',
            'date' => 'required|date',
            'shiftType' => 'required',
        ]);

        $name = trim($this->workerName);
        if ($this->workerType === 'subunternehmer' && !empty($this->contactId)) {
            $sub = Contact::find($this->contactId);
            if ($sub) {
                $name = $sub->display_name;
            }
        }

        if (empty($name)) {
            $name = $this->workerType === 'subunternehmer' ? 'Subunternehmer' : 'Monteur';
        }

        if ($this->editingId) {
            $sched = WorkerSchedule::find($this->editingId);
            if ($sched) {
                $sched->update([
                    'project_id' => $this->projectId,
                    'contact_id' => $this->workerType === 'subunternehmer' ? ($this->contactId ?: null) : null,
                    'worker_name' => $name,
                    'worker_type' => $this->workerType,
                    'date' => $this->date,
                    'shift_type' => $this->shiftType,
                    'notes' => $this->notes,
                ]);
                $this->dispatch('notify', '✅ Einsatzplan-Eintrag aktualisiert.');
            }
        } else {
            WorkerSchedule::create([
                'project_id' => $this->projectId,
                'contact_id' => $this->workerType === 'subunternehmer' ? ($this->contactId ?: null) : null,
                'worker_name' => $name,
                'worker_type' => $this->workerType,
                'date' => $this->date,
                'shift_type' => $this->shiftType,
                'notes' => $this->notes,
            ]);
            $this->dispatch('notify', '✅ Einsatz erfolgreich eingeplant.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function deleteSchedule(string $id)
    {
        WorkerSchedule::destroy($id);
        $this->dispatch('notify', '🗑️ Einsatzplan-Eintrag gelöscht.');
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->workerType = 'mitarbeiter';
        $this->contactId = '';
        $this->workerName = '';
        $this->shiftType = 'ganztags';
        $this->notes = '';
    }

    public function getWeekDaysProperty()
    {
        $start = Carbon::parse($this->selectedWeekStart);
        $days = [];
        for ($i = 0; $i < 6; $i++) { // Monday to Saturday
            $d = $start->copy()->addDays($i);
            $days[] = [
                'date' => $d->format('Y-m-d'),
                'day_name' => $d->locale('de')->dayName,
                'short_date' => $d->format('d.m.'),
                'is_today' => $d->isToday(),
            ];
        }
        return $days;
    }

    public function getCalendarWeekProperty()
    {
        return Carbon::parse($this->selectedWeekStart)->weekOfYear;
    }

    public function getProjectsProperty()
    {
        return Project::where('status', 'active')->orderBy('name')->get();
    }

    public function getSubcontractorsProperty()
    {
        return Contact::where('type', 'subunternehmer')->orderBy('company_name')->get();
    }

    public function getSchedulesProperty()
    {
        $start = Carbon::parse($this->selectedWeekStart)->format('Y-m-d');
        $end = Carbon::parse($this->selectedWeekStart)->addDays(5)->format('Y-m-d');

        return WorkerSchedule::with(['project', 'contact'])
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy(function ($item) {
                return $item->project_id . '_' . $item->date->format('Y-m-d');
            });
    }

    public function getStatsProperty()
    {
        $start = Carbon::parse($this->selectedWeekStart)->format('Y-m-d');
        $end = Carbon::parse($this->selectedWeekStart)->addDays(5)->format('Y-m-d');

        $schedules = WorkerSchedule::whereBetween('date', [$start, $end])->get();

        return [
            'total_shifts' => $schedules->count(),
            'employees_count' => $schedules->where('worker_type', 'mitarbeiter')->count(),
            'subcontractors_count' => $schedules->where('worker_type', 'subunternehmer')->count(),
            'active_projects' => $schedules->pluck('project_id')->unique()->count(),
        ];
    }
}; ?>

<div class="space-y-6 font-sans">
    <!-- Header Command Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 text-white rounded-2xl p-4 sm:p-6 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex items-center gap-3.5 relative z-10">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 shadow-lg text-2xl flex items-center justify-center border border-blue-400/30 shrink-0">
                👷
            </div>
            <div>
                <h2 class="text-lg sm:text-xl font-black text-white tracking-tight flex items-center gap-2">
                    Handwerker- & Monteur-Einsatzplaner
                </h2>
                <p class="text-xs text-slate-300 font-medium">Wochen-Disziplinierung & Baustellen-Belegung für Mitarbeiter & Subunternehmer</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 w-full md:w-auto relative z-10 shrink-0 justify-between sm:justify-end">
            <!-- Week Navigation Controls -->
            <div class="flex items-center bg-slate-800/90 rounded-xl p-1 border border-slate-700">
                <button wire:click="previousWeek" class="px-2.5 py-1.5 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-bold transition cursor-pointer">
                    ◀ KW {{ $this->calendarWeek - 1 }}
                </button>
                <button wire:click="resetToCurrentWeek" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-extrabold transition cursor-pointer">
                    KW {{ $this->calendarWeek }} (Heute)
                </button>
                <button wire:click="nextWeek" class="px-2.5 py-1.5 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-bold transition cursor-pointer">
                    KW {{ $this->calendarWeek + 1 }} ▶
                </button>
            </div>

            <button wire:click="openModal()" class="px-3.5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer">
                <span>➕</span>
                <span>Einsatz einplanen</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white p-3.5 sm:p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Eingeplante Einsätze</div>
            <div class="text-xl sm:text-2xl font-black text-slate-900 mt-1">{{ $this->stats['total_shifts'] }} <span class="text-xs font-medium text-slate-500">Schichten</span></div>
        </div>
        <div class="bg-white p-3.5 sm:p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Mitarbeiter</div>
            <div class="text-xl sm:text-2xl font-black text-blue-700 mt-1">{{ $this->stats['employees_count'] }} <span class="text-xs font-medium text-slate-500">Tage</span></div>
        </div>
        <div class="bg-white p-3.5 sm:p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Subunternehmer</div>
            <div class="text-xl sm:text-2xl font-black text-indigo-700 mt-1">{{ $this->stats['subcontractors_count'] }} <span class="text-xs font-medium text-slate-500">Tage</span></div>
        </div>
        <div class="bg-white p-3.5 sm:p-4 rounded-xl border border-slate-200/80 shadow-2xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Belegte Baustellen</div>
            <div class="text-xl sm:text-2xl font-black text-emerald-600 mt-1">{{ $this->stats['active_projects'] }} <span class="text-xs font-medium text-slate-500">Baustellen</span></div>
        </div>
    </div>

    <!-- Weekly Schedule Matrix Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[850px]">
                <thead>
                    <tr class="bg-slate-900 text-white text-xs font-extrabold uppercase">
                        <th class="p-3.5 sm:p-4 w-64 border-r border-slate-800">
                            🏗️ Baustelle / Gewerk
                        </th>
                        @foreach($this->weekDays as $day)
                            <th class="p-3.5 text-center border-r border-slate-800 min-w-[130px] {{ $day['is_today'] ? 'bg-blue-800/90 text-white' : '' }}">
                                <div>{{ $day['day_name'] }}</div>
                                <div class="text-[11px] font-normal opacity-80 mt-0.5">{{ $day['short_date'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($this->projects as $project)
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Project Info Cell -->
                            <td class="p-3.5 font-bold border-r border-slate-200/80 bg-slate-50/50">
                                <div class="text-slate-900 font-extrabold text-xs leading-snug">{{ $project->name }}</div>
                                <div class="text-[11px] text-slate-500 font-normal mt-0.5 flex items-center gap-1">
                                    <span>📍</span> <span>{{ $project->zip }} {{ $project->city_street }}</span>
                                </div>
                                <div class="inline-block mt-1 px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-50 text-blue-700 border border-blue-200/60">
                                    {{ $project->work_type }}
                                </div>
                            </td>

                            <!-- Day Matrix Cells -->
                            @foreach($this->weekDays as $day)
                                @php
                                    $key = $project->id . '_' . $day['date'];
                                    $daySchedules = $this->schedules[$key] ?? collect();
                                @endphp
                                <td class="p-2 border-r border-slate-100 align-top {{ $day['is_today'] ? 'bg-blue-50/20' : '' }}">
                                    <div class="space-y-1.5 min-h-[70px] flex flex-col justify-between">
                                        <!-- Schedules badges -->
                                        <div class="space-y-1.5">
                                            @foreach($daySchedules as $sched)
                                                <div class="p-2 rounded-xl text-[11px] font-bold border transition group relative shadow-2xs {{ $sched->worker_type === 'subunternehmer' ? 'bg-indigo-50/90 text-indigo-900 border-indigo-200' : 'bg-blue-50/90 text-blue-900 border-blue-200' }}">
                                                    <div class="flex items-start justify-between gap-1">
                                                        <span class="font-extrabold leading-tight">
                                                            {{ $sched->worker_type === 'subunternehmer' ? '🏗️' : '👤' }}
                                                            {{ $sched->worker_name }}
                                                        </span>
                                                        <button wire:click="deleteSchedule('{{ $sched->id }}')" 
                                                                onclick="confirm('Einsatz wirklich löschen?') || event.stopImmediatePropagation()"
                                                                title="Löschen" 
                                                                class="text-slate-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition cursor-pointer">
                                                            ✕
                                                        </button>
                                                    </div>
                                                    <div class="text-[10px] font-medium text-slate-500 mt-1 flex items-center gap-1">
                                                        <span>⏱️</span>
                                                        <span>{{ $sched->shift_label }}</span>
                                                    </div>
                                                    @if($sched->notes)
                                                        <div class="text-[10px] font-normal text-slate-600 mt-1 bg-white/70 p-1 rounded border border-slate-200/50 italic">
                                                            "{{ $sched->notes }}"
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        <!-- Quick Add Button per Cell -->
                                        <button wire:click="openModal('{{ $project->id }}', '{{ $day['date'] }}')" 
                                                class="w-full py-1 text-[10px] font-bold text-slate-400 hover:text-blue-700 bg-slate-50 hover:bg-blue-50 border border-dashed border-slate-200 hover:border-blue-300 rounded-lg transition text-center cursor-pointer flex items-center justify-center gap-1">
                                            <span>+</span> <span>Einsatz</span>
                                        </button>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400 font-medium">
                                Keine aktiven Baustellen gefunden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assignment Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 animate-fadeIn">
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <span>👷</span>
                        <span>{{ $editingId ? 'Einsatz bearbeiten' : 'Neuen Einsatz einplanen' }}</span>
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveSchedule" class="space-y-4 text-xs">
                    <!-- Project Select -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle / Objekt *</label>
                        <select wire:model="projectId" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 font-medium text-xs">
                            @foreach($this->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->city_street }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Worker Type Radio -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Einsatzkraft-Typ *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="p-2.5 rounded-xl border cursor-pointer font-bold flex items-center justify-center gap-2 {{ $workerType === 'mitarbeiter' ? 'bg-blue-50 text-blue-800 border-blue-300' : 'bg-slate-50 text-slate-600 border-slate-200' }}">
                                <input type="radio" wire:model.live="workerType" value="mitarbeiter" class="hidden">
                                <span>👤 Eigenes Team</span>
                            </label>
                            <label class="p-2.5 rounded-xl border cursor-pointer font-bold flex items-center justify-center gap-2 {{ $workerType === 'subunternehmer' ? 'bg-indigo-50 text-indigo-800 border-indigo-300' : 'bg-slate-50 text-slate-600 border-slate-200' }}">
                                <input type="radio" wire:model.live="workerType" value="subunternehmer" class="hidden">
                                <span>🏗️ Subunternehmer</span>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Worker Selection -->
                    @if($workerType === 'subunternehmer')
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Subunternehmer aus Kontakte wählen</label>
                            <select wire:model="contactId" class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 font-medium text-xs">
                                <option value="">-- Nachunternehmer wählen --</option>
                                @foreach($this->subcontractors as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->display_name }} ({{ $sub->city }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Monteur / Name / Team *</label>
                        <input type="text" wire:model="workerName" placeholder="z.B. Klaus Eder, Alex oder Team Alpha" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 font-medium text-xs">
                    </div>

                    <!-- Date & Shift -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Datum *</label>
                            <input type="date" wire:model="date" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 font-medium text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Schicht *</label>
                            <select wire:model="shiftType" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 font-medium text-xs">
                                <option value="ganztags">Ganztags (07-17 Uhr)</option>
                                <option value="vormittags">Vormittags (07-12 Uhr)</option>
                                <option value="nachmittags">Nachmittags (12-17 Uhr)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Anmerkungen / Vorhaushalt</label>
                        <textarea wire:model="notes" rows="2" placeholder="z.B. Rissverpressung, Dichtspachtel oder Gerüstaufbau..." class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 font-medium text-xs"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" wire:click="$set('showModal', false)" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md transition cursor-pointer">
                            Einsatz speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
