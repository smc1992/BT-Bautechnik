<?php

use App\Models\Measurement;
use App\Models\MeasurementItem;
use App\Models\Project;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $projectFilter = 'all';

    // Sheet Modal State
    public bool $showSheetModal = false;
    public ?string $activeMeasurementId = null;

    // Measurement Header Data
    public string $projectId = '';
    public string $measurementNumber = '';
    public string $title = '';
    public string $measurementDate = '';
    public ?string $locationArea = '';
    public ?string $inspectorName = '';
    public ?string $clientRepresentative = '';
    public ?string $notes = '';

    // Measurement Items array for live editing
    public array $items = [];

    public function mount(): void
    {
        $this->measurementDate = date('Y-m-d');
    }

    public function with(): array
    {
        $query = Measurement::with(['project', 'items']);

        if ($this->search) {
            $query->where(function($q) {
                $q->where('measurement_number', 'like', '%' . $this->search . '%')
                  ->orWhere('title', 'like', '%' . $this->search . '%')
                  ->orWhere('location_area', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->projectFilter !== 'all') {
            $query->where('project_id', $this->projectFilter);
        }

        return [
            'measurements' => $query->orderBy('measurement_date', 'desc')->paginate(12),
            'projects' => Project::orderBy('name', 'asc')->get(),
        ];
    }

    public function openCreateSheet(): void
    {
        $this->activeMeasurementId = null;
        $this->projectId = Project::first()?->id ?? '';
        $this->measurementDate = date('Y-m-d');
        $this->locationArea = '';
        $this->inspectorName = 'Bauleiter BT';
        $this->clientRepresentative = '';
        $this->notes = '';

        $count = Measurement::count() + 1;
        $this->measurementNumber = 'AM-' . date('Y') . '-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);
        $this->title = 'Aufmaß ' . $this->measurementNumber;

        // Initialize with 1 default row
        $this->items = [
            [
                'position_index' => 1,
                'item_code' => '01.01',
                'description' => 'Bitumen-Abdichtung 2-lagig',
                'unit' => 'm²',
                'room_or_axis' => 'Tiefgarage Achse 1-4',
                'length' => 12.50,
                'width' => 4.20,
                'height' => 1.00,
                'factor' => 1.00,
                'deduction' => 0.00,
                'quantity' => 52.50,
                'unit_price' => 45.00,
                'total_price' => 2362.50,
            ]
        ];

        $this->showSheetModal = true;
    }

    public function openEditSheet(string $id): void
    {
        $m = Measurement::with('items')->findOrFail($id);
        $this->activeMeasurementId = $m->id;
        $this->projectId = $m->project_id;
        $this->measurementNumber = $m->measurement_number;
        $this->title = $m->title;
        $this->measurementDate = $m->measurement_date->format('Y-m-d');
        $this->locationArea = $m->location_area;
        $this->inspectorName = $m->inspector_name;
        $this->clientRepresentative = $m->client_representative;
        $this->notes = $m->notes;

        $this->items = [];
        foreach ($m->items as $item) {
            $this->items[] = [
                'id' => $item->id,
                'position_index' => $item->position_index,
                'item_code' => $item->item_code,
                'description' => $item->description,
                'unit' => $item->unit,
                'room_or_axis' => $item->room_or_axis,
                'length' => (float)$item->length,
                'width' => (float)$item->width,
                'height' => (float)$item->height,
                'factor' => (float)$item->factor,
                'deduction' => (float)$item->deduction,
                'quantity' => (float)$item->quantity,
                'unit_price' => (float)$item->unit_price,
                'total_price' => (float)$item->total_price,
            ];
        }

        if (empty($this->items)) {
            $this->addItem();
        }

        $this->showSheetModal = true;
    }

    public function addItem(): void
    {
        $nextIndex = count($this->items) + 1;
        $this->items[] = [
            'position_index' => $nextIndex,
            'item_code' => '01.' . str_pad((string)$nextIndex, 2, '0', STR_PAD_LEFT),
            'description' => '',
            'unit' => 'm²',
            'room_or_axis' => '',
            'length' => 1.00,
            'width' => 1.00,
            'height' => 1.00,
            'factor' => 1.00,
            'deduction' => 0.00,
            'quantity' => 1.00,
            'unit_price' => 0.00,
            'total_price' => 0.00,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function recalculateRow(int $index): void
    {
        if (!isset($this->items[$index])) return;

        $row = &$this->items[$index];
        $l = (float)($row['length'] ?: 1);
        $w = (float)($row['width'] ?: 1);
        $h = (float)($row['height'] ?: 1);
        $factor = (float)($row['factor'] ?: 1);
        $deduction = (float)($row['deduction'] ?: 0);
        $unitPrice = (float)($row['unit_price'] ?: 0);

        $unit = strtolower($row['unit'] ?? 'm²');
        if (in_array($unit, ['m²', 'qm'])) {
            $rawQty = ($l * $w * $factor) - $deduction;
        } elseif (in_array($unit, ['m³', 'cbm'])) {
            $rawQty = ($l * $w * $h * $factor) - $deduction;
        } elseif (in_array($unit, ['m', 'lfdm'])) {
            $rawQty = ($l * $factor) - $deduction;
        } else {
            $rawQty = (float)($row['quantity'] ?: 1);
        }

        $row['quantity'] = max(0, round($rawQty, 3));
        $row['total_price'] = round($row['quantity'] * $unitPrice, 2);
    }

    public function saveSheet(): void
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'measurementNumber' => 'required|string',
            'title' => 'required|string',
            'measurementDate' => 'required|date',
        ]);

        $totalNet = 0.00;
        foreach ($this->items as $it) {
            $totalNet += (float)($it['total_price'] ?? 0);
        }

        $data = [
            'project_id' => $this->projectId,
            'measurement_number' => $this->measurementNumber,
            'title' => $this->title,
            'measurement_date' => $this->measurementDate,
            'location_area' => $this->locationArea,
            'inspector_name' => $this->inspectorName,
            'client_representative' => $this->clientRepresentative,
            'total_amount_net' => $totalNet,
            'notes' => $this->notes,
        ];

        if ($this->activeMeasurementId) {
            $m = Measurement::findOrFail($this->activeMeasurementId);
            $m->update($data);
            $m->items()->delete();
        } else {
            $m = Measurement::create($data);
        }

        foreach ($this->items as $idx => $it) {
            if (empty($it['description'])) continue;
            MeasurementItem::create([
                'measurement_id' => $m->id,
                'position_index' => $idx + 1,
                'item_code' => $it['item_code'] ?? null,
                'description' => $it['description'],
                'unit' => $it['unit'] ?? 'm²',
                'length' => $it['length'] ?? 1,
                'width' => $it['width'] ?? 1,
                'height' => $it['height'] ?? 1,
                'factor' => $it['factor'] ?? 1,
                'deduction' => $it['deduction'] ?? 0,
                'quantity' => $it['quantity'] ?? 1,
                'unit_price' => $it['unit_price'] ?? 0,
                'total_price' => $it['total_price'] ?? 0,
                'room_or_axis' => $it['room_or_axis'] ?? null,
            ]);
        }

        $this->dispatch('notify', 'Aufmaßblatt erfolgreich gespeichert!');
        $this->showSheetModal = false;
    }

    public function deleteMeasurement(string $id): void
    {
        Measurement::destroy($id);
        $this->dispatch('notify', 'Aufmaßblatt gelöscht.');
    }
}; ?>

<div class="space-y-6 font-sans">
    
    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-cyan-950 text-white rounded-2xl p-6 shadow-xl border border-cyan-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 mb-2">
                <span>VOB/C • DIN 18299</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white flex items-center gap-2.5">
                <span>📐 Digitales Aufmaßblatt & Mengenermittlung</span>
            </h1>
            <p class="text-xs text-slate-300 mt-1">Präzise Berechnung nach Formel (L × B × H) mit VOB-Abzügen für Abrechnung und Bauabnahmen.</p>
        </div>

        <button wire:click="openCreateSheet" 
                class="px-4 py-2.5 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-extrabold text-xs rounded-xl shadow-md shadow-cyan-500/20 transition flex items-center gap-2 cursor-pointer btn-press">
            <span>➕ Neues Aufmaßblatt</span>
        </button>
    </div>

    <!-- Filter & Search Strip -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
            <input wire:model.live.debounce.150ms="search" 
                   type="text" 
                   placeholder="🔍 Aufmaß-Nr., Titel oder Bereich suchen..." 
                   class="w-full sm:w-72 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-medium focus:bg-white focus:border-cyan-600 focus:outline-none">

            <select wire:model.live="projectFilter" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:bg-white focus:border-cyan-600 cursor-pointer">
                <option value="all">Alle Baustellen ({{ count($projects) }})</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Measurements Directory Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($measurements as $m)
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs hover:shadow-lg hover:-translate-y-0.5 transition duration-200 flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-md font-mono text-[10px] font-black bg-cyan-50 text-cyan-700 border border-cyan-200">
                                {{ $m->measurement_number }}
                            </span>
                            <h3 class="font-extrabold text-slate-900 text-base mt-1.5 line-clamp-1">{{ $m->title }}</h3>
                        </div>
                        <span class="text-xs font-bold text-slate-500 font-mono">{{ $m->measurement_date->format('d.m.Y') }}</span>
                    </div>

                    <div class="space-y-1 text-xs text-slate-600 font-medium">
                        <p class="flex items-center gap-1.5 text-slate-800 font-bold">
                            <span>📍</span> <span>{{ $m->project?->name ?: 'Keine Baustelle' }}</span>
                        </p>
                        @if ($m->location_area)
                            <p class="flex items-center gap-1.5 text-slate-500">
                                <span>🏷️</span> <span>Bereich: {{ $m->location_area }}</span>
                            </p>
                        @endif
                        <p class="text-slate-500">
                            {{ $m->items->count() }} Positionen erfasst
                        </p>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400">Aufmaß-Netto</span>
                        <p class="font-mono font-black text-sm text-slate-900 tabular-nums">
                            {{ number_format($m->total_amount_net, 2, ',', '.') }} €
                        </p>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button wire:click="openEditSheet('{{ $m->id }}')" class="px-3 py-1.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-800 font-extrabold text-xs rounded-xl border border-cyan-200 cursor-pointer btn-press">
                            ✏️ Öffnen
                        </button>
                        <button wire:click="deleteMeasurement('{{ $m->id }}')" wire:confirm="Aufmaßblatt wirklich löschen?" class="px-2 py-1.5 text-rose-600 hover:bg-rose-50 text-xs rounded-xl cursor-pointer btn-press">
                            ✕
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/90 rounded-2xl text-center space-y-2">
                <div class="text-3xl">📐</div>
                <p class="font-bold text-slate-900">Keine Aufmaßblätter gefunden</p>
                <p class="text-xs text-slate-500">Legen Sie Ihr erstes digitales Aufmaßblatt nach VOB/C an.</p>
            </div>
        @endforelse
    </div>

    <!-- Sheet Modal (Full Editor with Live Formula Engine) -->
    @if ($showSheetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-5xl w-full shadow-2xl border border-slate-200 space-y-5 max-h-[95vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-lg font-black text-slate-900">
                            {{ $activeMeasurementId ? 'Aufmaßblatt bearbeiten' : 'Neues Aufmaßblatt erstellen' }}
                        </h3>
                        <p class="text-xs text-slate-500">Mengenermittlung nach VOB/C mit Raummaßen und automatischem Abzug</p>
                    </div>
                    <button wire:click="$set('showSheetModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <!-- Header inputs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle *</label>
                        <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 font-bold focus:bg-white focus:border-cyan-600">
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Aufmaß-Nr. *</label>
                        <input wire:model="measurementNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 font-mono font-bold focus:bg-white focus:border-cyan-600">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Titel / Gewerk</label>
                        <input wire:model="title" type="text" placeholder="z.B. Abdichtung Tiefgarage" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 font-bold focus:bg-white focus:border-cyan-600">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Aufmaßdatum</label>
                        <input wire:model="measurementDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 font-medium focus:bg-white focus:border-cyan-600">
                    </div>
                </div>

                <!-- Items Table with live formulas -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-black uppercase text-slate-700">Aufmaßzeilen & Raummaße</h4>
                        <button type="button" wire:click="addItem" class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press flex items-center gap-1">
                            <span>➕ Zeile hinzufügen</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                        <table class="w-full text-left text-xs divide-y divide-slate-100 min-w-[750px]">
                            <thead class="bg-slate-50 text-slate-600 font-bold uppercase text-[10px]">
                                <tr>
                                    <th class="p-2.5">Pos. / Raum / Achse</th>
                                    <th class="p-2.5">Leistungsbeschreibung</th>
                                    <th class="p-2.5 text-center">Einh.</th>
                                    <th class="p-2.5 text-center">L (m)</th>
                                    <th class="p-2.5 text-center">B (m)</th>
                                    <th class="p-2.5 text-center">Faktor</th>
                                    <th class="p-2.5 text-center">Abzug</th>
                                    <th class="p-2.5 text-right">Menge</th>
                                    <th class="p-2.5 text-right">EP (€)</th>
                                    <th class="p-2.5 text-right">Gesamt (€)</th>
                                    <th class="p-2.5 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($items as $idx => $it)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-2 w-32">
                                            <input wire:model="items.{{ $idx }}.room_or_axis" type="text" placeholder="Raum / Achse" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1.5 text-xs font-bold">
                                        </td>
                                        <td class="p-2">
                                            <input wire:model="items.{{ $idx }}.description" type="text" placeholder="Bezeichnung der Leistung..." class="w-full bg-slate-50 border border-slate-200 rounded-lg p-1.5 text-xs">
                                        </td>
                                        <td class="p-2 w-16 text-center">
                                            <select wire:model="items.{{ $idx }}.unit" wire:change="recalculateRow({{ $idx }})" class="bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs">
                                                <option value="m²">m²</option>
                                                <option value="m">m</option>
                                                <option value="m³">m³</option>
                                                <option value="Stk.">Stk.</option>
                                                <option value="Std.">Std.</option>
                                            </select>
                                        </td>
                                        <td class="p-2 w-16">
                                            <input wire:model="items.{{ $idx }}.length" wire:change="recalculateRow({{ $idx }})" type="number" step="0.01" class="w-full text-center bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs font-mono font-bold">
                                        </td>
                                        <td class="p-2 w-16">
                                            <input wire:model="items.{{ $idx }}.width" wire:change="recalculateRow({{ $idx }})" type="number" step="0.01" class="w-full text-center bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs font-mono font-bold">
                                        </td>
                                        <td class="p-2 w-14">
                                            <input wire:model="items.{{ $idx }}.factor" wire:change="recalculateRow({{ $idx }})" type="number" step="1" class="w-full text-center bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs font-mono">
                                        </td>
                                        <td class="p-2 w-16">
                                            <input wire:model="items.{{ $idx }}.deduction" wire:change="recalculateRow({{ $idx }})" type="number" step="0.01" placeholder="0" class="w-full text-center bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs font-mono text-rose-600">
                                        </td>
                                        <td class="p-2 w-20 text-right font-mono font-bold text-slate-900 tabular-nums">
                                            {{ number_format($items[$idx]['quantity'] ?? 0, 2, ',', '.') }}
                                        </td>
                                        <td class="p-2 w-20">
                                            <input wire:model="items.{{ $idx }}.unit_price" wire:change="recalculateRow({{ $idx }})" type="number" step="0.5" class="w-full text-right bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs font-mono font-bold">
                                        </td>
                                        <td class="p-2 w-24 text-right font-mono font-black text-cyan-900 tabular-nums">
                                            {{ number_format($items[$idx]['total_price'] ?? 0, 2, ',', '.') }} €
                                        </td>
                                        <td class="p-2 text-center">
                                            <button type="button" wire:click="removeItem({{ $idx }})" class="text-rose-500 hover:text-rose-700 font-bold text-sm cursor-pointer">✕</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Summary & Actions -->
                <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    @php
                        $sumTotal = 0;
                        foreach($items as $i) { $sumTotal += (float)($i['total_price'] ?? 0); }
                    @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-500">Gesamtsumme Netto:</span>
                        <span class="text-lg font-black font-mono text-slate-900 tabular-nums">{{ number_format($sumTotal, 2, ',', '.') }} €</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$set('showSheetModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="button" wire:click="saveSheet" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-extrabold text-xs rounded-xl shadow-md shadow-cyan-500/20 cursor-pointer btn-press">
                            Aufmaßblatt speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
