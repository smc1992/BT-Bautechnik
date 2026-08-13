<?php

use App\Models\Equipment;
use App\Models\Project;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = 'all';
    public string $statusFilter = 'all';
    public string $projectFilter = 'all';

    // Modal
    public bool $showModal = false;
    public ?string $editingId = null;

    public string $inventoryNumber = '';
    public string $name = '';
    public string $category = 'machine'; // machine, tool, vehicle, drying, safety
    public ?string $manufacturer = '';
    public ?string $model = '';
    public ?string $serialNumber = '';
    public ?string $currentProjectId = null;
    public string $status = 'available'; // available, on_site, in_repair, retired
    public ?string $purchaseDate = null;
    public float $purchasePrice = 0.00;
    public ?string $nextUvvInspection = null;
    public ?string $nextTuevInspection = null;
    public ?string $notes = '';

    public function with(): array
    {
        $query = Equipment::with('currentProject');

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('inventory_number', 'like', '%' . $this->search . '%')
                  ->orWhere('manufacturer', 'like', '%' . $this->search . '%')
                  ->orWhere('serial_number', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->projectFilter !== 'all') {
            $query->where('current_project_id', $this->projectFilter);
        }

        $allEquip = Equipment::all();
        $dueUvvCount = $allEquip->filter(function($e) {
            return $e->next_uvv_inspection && $e->next_uvv_inspection->isPast();
        })->count();

        return [
            'equipmentList' => $query->orderBy('inventory_number', 'asc')->paginate(12),
            'projects' => Project::orderBy('name', 'asc')->get(),
            'totalCount' => $allEquip->count(),
            'onSiteCount' => $allEquip->where('status', 'on_site')->count(),
            'dueUvvCount' => $dueUvvCount,
        ];
    }

    public function openCreateModal(): void
    {
        $this->editingId = null;
        $count = Equipment::count() + 1;
        $this->inventoryNumber = 'GER-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);
        $this->name = '';
        $this->category = 'machine';
        $this->manufacturer = '';
        $this->model = '';
        $this->serialNumber = '';
        $this->currentProjectId = null;
        $this->status = 'available';
        $this->purchaseDate = date('Y-m-d');
        $this->purchasePrice = 0.00;
        $this->nextUvvInspection = date('Y-m-d', strtotime('+1 year'));
        $this->nextTuevInspection = null;
        $this->notes = '';

        $this->showModal = true;
    }

    public function openEditModal(string $id): void
    {
        $eq = Equipment::findOrFail($id);
        $this->editingId = $eq->id;
        $this->inventoryNumber = $eq->inventory_number;
        $this->name = $eq->name;
        $this->category = $eq->category;
        $this->manufacturer = $eq->manufacturer;
        $this->model = $eq->model;
        $this->serialNumber = $eq->serial_number;
        $this->currentProjectId = $eq->current_project_id;
        $this->status = $eq->status;
        $this->purchaseDate = $eq->purchase_date ? $eq->purchase_date->format('Y-m-d') : null;
        $this->purchasePrice = (float)$eq->purchase_price;
        $this->nextUvvInspection = $eq->next_uvv_inspection ? $eq->next_uvv_inspection->format('Y-m-d') : null;
        $this->nextTuevInspection = $eq->next_tuev_inspection ? $eq->next_tuev_inspection->format('Y-m-d') : null;
        $this->notes = $eq->notes;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'inventoryNumber' => 'required|string',
            'name' => 'required|string|max:255',
            'category' => 'required|string',
        ]);

        $data = [
            'inventory_number' => $this->inventoryNumber,
            'name' => $this->name,
            'category' => $this->category,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'serial_number' => $this->serialNumber,
            'current_project_id' => $this->currentProjectId ?: null,
            'status' => $this->currentProjectId ? 'on_site' : $this->status,
            'purchase_date' => $this->purchaseDate,
            'purchase_price' => $this->purchasePrice,
            'next_uvv_inspection' => $this->nextUvvInspection,
            'next_tuev_inspection' => $this->nextTuevInspection,
            'notes' => $this->notes,
        ];

        if ($this->editingId) {
            Equipment::where('id', $this->editingId)->update($data);
            $this->dispatch('notify', 'Gerät aktualisiert!');
        } else {
            Equipment::create($data);
            $this->dispatch('notify', 'Gerät erfolgreich angelegt!');
        }

        $this->showModal = false;
    }

    public function assignToProject(string $id, ?string $projectId): void
    {
        $eq = Equipment::findOrFail($id);
        $eq->current_project_id = $projectId;
        $eq->status = $projectId ? 'on_site' : 'available';
        $eq->save();

        $this->dispatch('notify', 'Standort aktualisiert.');
    }

    public function delete(string $id): void
    {
        Equipment::destroy($id);
        $this->dispatch('notify', 'Gerät gelöscht.');
    }
}; ?>

<div class="space-y-6 font-sans">
    
    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-amber-950 text-white rounded-2xl p-6 shadow-xl border border-amber-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30 mb-2">
                <span>DGUV V3 • UVV-Prüffristen</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white flex items-center gap-2.5">
                <span>🚜 Geräte-, Maschinen- & Fuhrparkverwaltung</span>
            </h1>
            <p class="text-xs text-slate-300 mt-1">Echtzeit-Standortübersicht aller Werkzeuge, Maschinen und Baufahrzeuge mit UVV-Prüfampel.</p>
        </div>

        <button wire:click="openCreateModal" 
                class="px-4 py-2.5 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-extrabold text-xs rounded-xl shadow-md shadow-amber-500/20 transition flex items-center gap-2 cursor-pointer btn-press">
            <span>➕ Gerät / Fahrzeug anlegen</span>
        </button>
    </div>

    <!-- KPI Summary Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamte Geräte</p>
            <p class="text-2xl font-black text-slate-900 mt-1 tabular-nums">{{ $totalCount }}</p>
        </div>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aktuell auf Baustelle</p>
            <p class="text-2xl font-black text-blue-600 mt-1 tabular-nums">{{ $onSiteCount }}</p>
        </div>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Fällige UVV / TÜV Prüfungen</p>
            <p class="text-2xl font-black {{ $dueUvvCount > 0 ? 'text-rose-600' : 'text-emerald-600' }} mt-1 tabular-nums">{{ $dueUvvCount }}</p>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2.5 flex-1">
            <input wire:model.live.debounce.150ms="search" 
                   type="text" 
                   placeholder="🔍 Inventarnr., Name, Hersteller oder Seriennr...." 
                   class="w-full sm:w-72 bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-medium focus:bg-white focus:border-amber-600">

            <select wire:model.live="categoryFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold focus:bg-white focus:border-amber-600 cursor-pointer">
                <option value="all">Alle Kategorien</option>
                <option value="machine">🚜 Baumaschinen</option>
                <option value="tool">🛠️ Werkzeuge & Elektrowerkzeuge</option>
                <option value="vehicle">🚗 Baufahrzeuge & Transporter</option>
                <option value="drying">💨 Bautrockner & Heizung</option>
                <option value="safety">🦺 Absturzsicherung & Gerüst</option>
            </select>

            <select wire:model.live="projectFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold focus:bg-white focus:border-amber-600 cursor-pointer">
                <option value="all">Alle Standorte</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Equipment Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($equipmentList as $eq)
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs hover:shadow-lg hover:-translate-y-0.5 transition duration-200 flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200">
                                {{ $eq->inventory_number }}
                            </span>
                            <h3 class="font-extrabold text-slate-900 text-base mt-1.5 line-clamp-1">{{ $eq->name }}</h3>
                            <p class="text-xs text-slate-500">{{ $eq->manufacturer }} {{ $eq->model }}</p>
                        </div>
                        @php
                            $statusBadge = match($eq->status) {
                                'available' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'on_site' => 'bg-blue-100 text-blue-800 border-blue-200',
                                'in_repair' => 'bg-rose-100 text-rose-800 border-rose-200',
                                default => 'bg-slate-100 text-slate-700',
                            };
                            $statusName = match($eq->status) {
                                'available' => 'Lager verfügbar',
                                'on_site' => 'Auf Baustelle',
                                'in_repair' => 'In Reparatur',
                                default => $eq->status,
                            };
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border {{ $statusBadge }}">
                            {{ $statusName }}
                        </span>
                    </div>

                    <div class="space-y-1.5 text-xs text-slate-600 font-medium">
                        <p class="flex items-center gap-1.5">
                            <span>📍 Standort:</span>
                            <span class="font-bold text-slate-800 truncate">{{ $eq->currentProject?->name ?: '🏢 Lager / Werkstatt' }}</span>
                        </p>
                        @if ($eq->serialNumber)
                            <p class="text-slate-400 font-mono text-[11px]">S/N: {{ $eq->serialNumber }}</p>
                        @endif

                        <!-- UVV Ampel -->
                        @if ($eq->next_uvv_inspection)
                            @php
                                $isPast = $eq->next_uvv_inspection->isPast();
                                $isSoon = $eq->next_uvv_inspection->diffInDays(now()) < 30;
                            @endphp
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-bold">Nächste UVV-Prüfung:</span>
                                <span class="font-mono font-black px-2 py-0.5 rounded {{ $isPast ? 'bg-rose-100 text-rose-700 border border-rose-200' : ($isSoon ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800') }}">
                                    {{ $eq->next_uvv_inspection->format('d.m.Y') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <select wire:change="assignToProject('{{ $eq->id }}', $event.target.value)" class="bg-slate-50 border border-slate-200 rounded-lg p-1 text-[11px] font-bold text-slate-700 max-w-[140px] cursor-pointer">
                            <option value="">Lager / Frei</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}" {{ $eq->current_project_id === $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button wire:click="openEditModal('{{ $eq->id }}')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg cursor-pointer btn-press">
                            ✏️
                        </button>
                        <button wire:click="delete('{{ $eq->id }}')" wire:confirm="Gerät wirklich löschen?" class="px-2 py-1 text-rose-600 hover:bg-rose-50 text-xs rounded-lg cursor-pointer btn-press">
                            ✕
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/90 rounded-2xl text-center space-y-2">
                <div class="text-3xl">🚜</div>
                <p class="font-bold text-slate-900">Keine Geräte angelegt</p>
                <p class="text-xs text-slate-500">Erfassen Sie Ihre Maschinen, Werkzeuge und Baufahrzeuge.</p>
            </div>
        @endforelse
    </div>

    <!-- Create / Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-slate-200 space-y-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-lg font-black text-slate-900">
                        {{ $editingId ? 'Gerät / Fahrzeug bearbeiten' : 'Neues Gerät erfassen' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="save" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Inventarnummer *</label>
                            <input wire:model="inventoryNumber" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kategorie *</label>
                            <select wire:model="category" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                                <option value="machine">🚜 Baumaschine</option>
                                <option value="tool">🛠️ Werkzeug / Elektrowerkzeug</option>
                                <option value="vehicle">🚗 Baufahrzeug / Transporter</option>
                                <option value="drying">💨 Bautrockner</option>
                                <option value="safety">🦺 Absturzsicherung</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Gerätename / Bezeichnung *</label>
                        <input wire:model="name" type="text" placeholder="z.B. Hilti TE 70 Bohrhammer oder Rüttelplatte" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Hersteller</label>
                            <input wire:model="manufacturer" type="text" placeholder="z.B. Hilti, Wacker Neuson, Bosch" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-medium shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Modell / Typ</label>
                            <input wire:model="model" type="text" placeholder="z.B. TE 70-ATC" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-medium shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Seriennummer</label>
                            <input wire:model="serialNumber" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-medium shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nächste UVV-Prüfung</label>
                            <input wire:model="nextUvvInspection" type="date" class="w-full bg-white border border-slate-300 text-amber-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Aktueller Standort / Baustelle</label>
                        <select wire:model="currentProjectId" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                            <option value="">🏢 Zentrales Lager / Werkstatt</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-extrabold rounded-xl shadow-md shadow-amber-500/20 cursor-pointer btn-press">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
