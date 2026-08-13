<?php

use App\Models\ProjectPlan;
use App\Models\Project;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

new class extends Component {
    use WithPagination;
    use WithFileUploads;

    public string $search = '';
    public string $projectFilter = 'all';
    public string $categoryFilter = 'all';

    // Upload / Create Modal
    public bool $showModal = false;
    public ?string $editingId = null;

    public string $projectId = '';
    public ?string $planNumber = '';
    public string $title = '';
    public string $category = 'architecture'; // architecture, structural, tga, fire_safety, permit
    public string $revisionIndex = 'Index 0'; // Index 0, Index A, Index B...
    public ?string $planDate = null;
    public ?string $notes = '';
    public $fileUpload = null;

    public function mount(): void
    {
        $this->planDate = date('Y-m-d');
        $this->projectId = Project::first()?->id ?? '';
    }

    public function with(): array
    {
        $query = ProjectPlan::with('project');

        if ($this->search) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('plan_number', 'like', '%' . $this->search . '%')
                  ->orWhere('revision_index', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->projectFilter !== 'all') {
            $query->where('project_id', $this->projectFilter);
        }

        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        return [
            'plans' => $query->orderBy('created_at', 'desc')->paginate(12),
            'projects' => Project::orderBy('name', 'asc')->get(),
        ];
    }

    public function openCreateModal(): void
    {
        $this->editingId = null;
        $this->projectId = Project::first()?->id ?? '';
        $this->planNumber = 'PL-' . date('y') . '-' . str_pad((string)(ProjectPlan::count() + 1), 3, '0', STR_PAD_LEFT);
        $this->title = '';
        $this->category = 'architecture';
        $this->revisionIndex = 'Index 0';
        $this->planDate = date('Y-m-d');
        $this->notes = '';
        $this->fileUpload = null;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'revisionIndex' => 'required|string',
        ]);

        $filePath = '';
        $fileName = '';
        $fileSize = 0;
        $fileMime = '';

        if ($this->fileUpload) {
            $filePath = $this->fileUpload->store('project_plans', 'public');
            $fileName = $this->fileUpload->getClientOriginalName();
            $fileSize = $this->fileUpload->getSize();
            $fileMime = $this->fileUpload->getMimeType();
        }

        $data = [
            'project_id' => $this->projectId,
            'plan_number' => $this->planNumber,
            'title' => $this->title,
            'category' => $this->category,
            'revision_index' => $this->revisionIndex,
            'plan_date' => $this->planDate,
            'uploaded_by' => auth()->user()?->name ?: 'Bauleiter',
            'notes' => $this->notes,
        ];

        if ($this->fileUpload) {
            $data['file_path'] = $filePath;
            $data['file_name'] = $fileName;
            $data['file_size'] = $fileSize;
            $data['file_mime'] = $fileMime;
        }

        if ($this->editingId) {
            ProjectPlan::where('id', $this->editingId)->update($data);
            $this->dispatch('notify', 'Plan-Metadaten aktualisiert!');
        } else {
            if (!$this->fileUpload) {
                $this->addError('fileUpload', 'Bitte laden Sie eine Datei (PDF oder Bild) hoch.');
                return;
            }
            ProjectPlan::create($data);
            $this->dispatch('notify', 'Bauplan erfolgreich abgelegt!');
        }

        $this->showModal = false;
    }

    public function deletePlan(string $id): void
    {
        ProjectPlan::destroy($id);
        $this->dispatch('notify', 'Bauplan gelöscht.');
    }
}; ?>

<div class="space-y-6 font-sans">
    
    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white rounded-2xl p-6 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-500/30 mb-2">
                <span>Planstand-Management • Revisionssicher</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white flex items-center gap-2.5">
                <span>📐 Bauplan- & Dokumentenverwaltung</span>
            </h1>
            <p class="text-xs text-slate-300 mt-1">Architektur-, Statik- und TGA-Pläne mit Revisionsindexierung (Index A, B, C) und Sofort-Vorschau.</p>
        </div>

        <button wire:click="openCreateModal" 
                class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-md shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer btn-press">
            <span>➕ Plan hochladen</span>
        </button>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2.5 flex-1">
            <input wire:model.live.debounce.150ms="search" 
                   type="text" 
                   placeholder="🔍 Plannummer, Titel oder Index suchen..." 
                   class="w-full sm:w-72 bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-medium focus:bg-white focus:border-blue-600">

            <select wire:model.live="projectFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold focus:bg-white focus:border-blue-600 cursor-pointer">
                <option value="all">Alle Baustellen ({{ count($projects) }})</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="categoryFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold focus:bg-white focus:border-blue-600 cursor-pointer">
                <option value="all">Alle Kategorien</option>
                <option value="architecture">Architektur & Grundrisse</option>
                <option value="structural">Statik & Bewehrung</option>
                <option value="tga">TGA / Haustechnik</option>
                <option value="fire_safety">Brandschutz</option>
                <option value="permit">Baugenehmigung</option>
            </select>
        </div>
    </div>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($plans as $plan)
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs hover:shadow-lg hover:-translate-y-0.5 transition duration-200 flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-black bg-blue-50 text-blue-800 border border-blue-200">
                                    {{ $plan->plan_number ?: 'PL' }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-black bg-slate-900 text-white shadow-2xs">
                                    {{ $plan->revision_index }}
                                </span>
                            </div>
                            <h3 class="font-extrabold text-slate-900 text-base mt-2 line-clamp-1">{{ $plan->title }}</h3>
                        </div>
                        <span class="text-xs font-bold text-slate-400 font-mono">{{ $plan->plan_date ? $plan->plan_date->format('d.m.Y') : '' }}</span>
                    </div>

                    <div class="space-y-1.5 text-xs text-slate-600 font-medium">
                        <p class="flex items-center gap-1.5 text-slate-800 font-bold">
                            <span>📍</span> <span>{{ $plan->project?->name ?: 'Keine Baustelle' }}</span>
                        </p>
                        @php
                            $categoryLabel = match($plan->category) {
                                'architecture' => '📐 Architektur',
                                'structural' => '🏗️ Statik',
                                'tga' => '⚡ TGA / Haustechnik',
                                'fire_safety' => '🔥 Brandschutz',
                                'permit' => '🏛️ Genehmigung',
                                default => '📄 Plan',
                            };
                        @endphp
                        <p class="text-slate-500">Kategorie: <span class="font-bold text-slate-700">{{ $categoryLabel }}</span></p>
                        @if ($plan->file_name)
                            <p class="text-[11px] text-slate-400 font-mono truncate">Datei: {{ $plan->file_name }} ({{ round($plan->file_size / 1024, 0) }} KB)</p>
                        @endif
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400">Hochgeladen von {{ $plan->uploaded_by ?: 'Bauleiter' }}</span>

                    <div class="flex items-center gap-1.5">
                        @if ($plan->file_path)
                            <a href="{{ asset('storage/' . $plan->file_path) }}" target="_blank" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-extrabold text-xs rounded-xl border border-blue-200 cursor-pointer btn-press">
                                👁️ Vorschau
                            </a>
                        @endif
                        <button wire:click="deletePlan('{{ $plan->id }}')" wire:confirm="Plan wirklich löschen?" class="px-2 py-1.5 text-rose-600 hover:bg-rose-50 text-xs rounded-xl cursor-pointer btn-press">
                            ✕
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/90 rounded-2xl text-center space-y-2">
                <div class="text-3xl">📐</div>
                <p class="font-bold text-slate-900">Keine Baupläne hinterlegt</p>
                <p class="text-xs text-slate-500">Laden Sie Ausführungspläne für Ihre Baustellen hoch.</p>
            </div>
        @endforelse
    </div>

    <!-- Upload Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-lg font-black text-slate-900">Bauplan hochladen</h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="save" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle *</label>
                        <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-bold focus:bg-white focus:border-blue-600">
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Plannummer</label>
                            <input wire:model="planNumber" type="text" placeholder="z.B. AR-101" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-bold font-mono focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Revisions-Index *</label>
                            <input wire:model="revisionIndex" type="text" placeholder="z.B. Index 0 / Index A" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-bold font-mono focus:bg-white focus:border-blue-600">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Plan-Bezeichnung / Titel *</label>
                        <input wire:model="title" type="text" placeholder="z.B. Grundriss Tiefgarage WGB 11c" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-bold focus:bg-white focus:border-blue-600">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kategorie</label>
                            <select wire:model="category" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-medium focus:bg-white focus:border-blue-600">
                                <option value="architecture">Architektur & Grundriss</option>
                                <option value="structural">Statik & Bewehrung</option>
                                <option value="tga">TGA / Haustechnik</option>
                                <option value="fire_safety">Brandschutz</option>
                                <option value="permit">Baugenehmigung</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Plandatum</label>
                            <input wire:model="planDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-medium focus:bg-white focus:border-blue-600">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Datei-Upload (PDF / Bild) *</label>
                        <input wire:model="fileUpload" type="file" accept=".pdf,.png,.jpg,.jpeg,.dwg" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 focus:bg-white focus:border-blue-600 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white cursor-pointer">
                        <x-input-error :messages="$errors->get('fileUpload')" class="mt-1" />
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md shadow-blue-500/20 cursor-pointer btn-press">
                            Hochladen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
