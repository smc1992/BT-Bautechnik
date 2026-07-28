<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Material;

new class extends Component {
    use WithPagination;

    // Filters & Search
    public string $searchQuery = '';
    public string $selectedCategory = 'all';
    public int $perPage = 15;

    public function updatedSearchQuery()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    // Manual Form State
    public bool $showModal = false;
    public ?string $editingMaterialId = null;
    public string $name = '';
    public string $sku = '';
    public string $category = 'Rohbau';
    public string $unit = 'Stk';
    public float $unitPrice = 0.00;
    public string $supplier = '';
    public string $notes = '';

    // KI Preisanpassungs State
    public bool $showAiPromptModal = false;
    public string $aiPrompt = '';
    public bool $isProcessingAi = false;
    public array $aiPreviewUpdates = [];
    public string $aiSummary = '';

    public function getCategoriesProperty()
    {
        return [
            'Rohbau',
            'Dach & Abdichtung',
            'Trockenbau',
            'Dämmung',
            'Sanitär & Elektro',
            'Werkzeuge & Sonstiges',
            'Abdichtung & Reaktivabdichtung',
            'Injektionstechnik & Rissverpressung',
            'Beton- & Estrichsanierung',
            'Dämmung & Perimeter',
            'Drainage & Schutzsysteme',
            'Dichtbänder & Fugenabdichtung',
            'Trockenbau & Wandbaustoffe',
            'Verbrauchsmaterial & Arbeitsschutz',
        ];
    }

    public function getMaterialsProperty()
    {
        $query = Material::query()
            ->when($this->selectedCategory !== 'all', fn($q) => $q->where('category', $this->selectedCategory))
            ->when(trim($this->searchQuery) !== '', function ($q) {
                $search = '%' . trim($this->searchQuery) . '%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', $search)
                        ->orWhere('supplier', 'LIKE', $search)
                        ->orWhere('sku', 'LIKE', $search)
                        ->orWhere('category', 'LIKE', $search);
                });
            })
            ->orderBy('category', 'asc')
            ->orderBy('name', 'asc');

        return $this->perPage === -1 ? $query->get() : $query->paginate($this->perPage);
    }

    public function openCreateModal()
    {
        $this->editingMaterialId = null;
        $this->name = '';
        $this->sku = '';
        $this->category = 'Rohbau';
        $this->unit = 'Sack';
        $this->unitPrice = 0.00;
        $this->supplier = '';
        $this->notes = '';
        $this->showModal = true;
    }

    public function openEditModal(string $id)
    {
        $material = Material::find($id);
        if (!$material) return;

        $this->editingMaterialId = $material->id;
        $this->name = $material->name;
        $this->sku = $material->sku ?: '';
        $this->category = $material->category;
        $this->unit = $material->unit;
        $this->unitPrice = (float) $material->unit_price;
        $this->supplier = $material->supplier ?: '';
        $this->notes = $material->notes ?: '';
        $this->showModal = true;
    }

    public function saveMaterial()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'unit' => 'required|string',
            'unitPrice' => 'required|numeric|min:0',
        ]);

        if ($this->editingMaterialId) {
            $material = Material::find($this->editingMaterialId);
            if ($material) {
                $oldPrice = $material->unit_price;
                $material->update([
                    'name' => $this->name,
                    'sku' => $this->sku ?: null,
                    'category' => $this->category,
                    'unit' => $this->unit,
                    'unit_price' => $this->unitPrice,
                    'supplier' => $this->supplier ?: null,
                    'notes' => $this->notes ?: null,
                    'last_price_update' => $oldPrice != $this->unitPrice ? now() : $material->last_price_update,
                ]);
                $this->dispatch('notify', '📦 Baustoff "' . $material->name . '" erfolgreich aktualisiert!');
            }
        } else {
            Material::create([
                'name' => $this->name,
                'sku' => $this->sku ?: null,
                'category' => $this->category,
                'unit' => $this->unit,
                'unit_price' => $this->unitPrice,
                'supplier' => $this->supplier ?: null,
                'notes' => $this->notes ?: null,
                'last_price_update' => now(),
            ]);
            $this->dispatch('notify', '📦 Neuer Baustoff "' . $this->name . '" neu angelegt!');
        }

        $this->showModal = false;
    }

    public function deleteMaterial(string $id)
    {
        $material = Material::find($id);
        if ($material) {
            $name = $material->name;
            $material->delete();
            $this->dispatch('notify', '🗑️ Baustoff "' . $name . '" gelöscht.');
        }
    }

    public function updatePriceInline(string $id, $newPrice)
    {
        $val = floatval(str_replace(',', '.', $newPrice));
        $material = Material::find($id);
        if ($material && $val >= 0) {
            $material->update([
                'unit_price' => $val,
                'last_price_update' => now(),
            ]);
            $this->dispatch('notify', '⚡ Preis für "' . $material->name . '" auf ' . number_format($val, 2, ',', '.') . ' € angepasst!');
        }
    }

    public function seedDefaultMaterials()
    {
        $defaults = [
            ['name' => 'Zement CEM II 32.5 R (25kg Sack)', 'category' => 'Rohbau', 'unit' => 'Sack', 'unit_price' => 5.45, 'supplier' => 'Heidelberg Materials'],
            ['name' => 'Fließestrich E225 (40kg Sack)', 'category' => 'Rohbau', 'unit' => 'Sack', 'unit_price' => 7.80, 'supplier' => 'Knauf Bauprodukte'],
            ['name' => 'Bewehrungsstahl B500A (Durchmesser 10mm)', 'category' => 'Rohbau', 'unit' => 't', 'unit_price' => 1120.00, 'supplier' => 'Südstahl Baustoff'],
            ['name' => 'Mineralwolle Dämmmatte 120mm (WLG 035)', 'category' => 'Dämmung', 'unit' => 'm²', 'unit_price' => 14.50, 'supplier' => 'Isover'],
            ['name' => 'EPS Hartschaumplatte WDV 100mm', 'category' => 'Dämmung', 'unit' => 'm²', 'unit_price' => 18.20, 'supplier' => 'Sto SE'],
            ['name' => 'Dachpappe V13 Besandet (10m Rolle)', 'category' => 'Dach & Abdichtung', 'unit' => 'Rolle', 'unit_price' => 24.90, 'supplier' => 'Börner Dachbaustoffe'],
            ['name' => 'Bitumen-Schweißbahn PYP PV 200 S5 (5m Rolle)', 'category' => 'Dach & Abdichtung', 'unit' => 'Rolle', 'unit_price' => 48.50, 'supplier' => 'Bauder'],
            ['name' => 'Gipskartonplatte Imprägniert GKBI 12.5mm', 'category' => 'Trockenbau', 'unit' => 'm²', 'unit_price' => 6.90, 'supplier' => 'Knauf'],
            ['name' => 'Trockenbau UW-Profil 75x40x0.6mm (4m)', 'category' => 'Trockenbau', 'unit' => 'Stk', 'unit_price' => 11.20, 'supplier' => 'Rigips'],
            ['name' => 'Bau-Dichtstoff Polymer Hybridsilikon (310ml)', 'category' => 'Werkzeuge & Sonstiges', 'unit' => 'Kartusche', 'unit_price' => 8.90, 'supplier' => 'Würth'],
        ];

        $created = 0;
        foreach ($defaults as $d) {
            if (!Material::where('name', $d['name'])->exists()) {
                Material::create(array_merge($d, ['last_price_update' => now()]));
                $created++;
            }
        }

        $this->dispatch('notify', "🌱 {$created} Standard-Baustoffe erfolgreich geladen!");
    }

    public function processAiPriceAdjustment(?\App\Services\OpenAiParserService $parser = null)
    {
        $parser = $parser ?? app(\App\Services\OpenAiParserService::class);

        if (empty(trim($this->aiPrompt))) {
            $this->dispatch('notify', 'Bitte geben Sie eine Preisanpassungs-Anweisung ein.');
            return;
        }

        $materials = Material::all(['id', 'name', 'category', 'unit', 'unit_price', 'supplier'])->toArray();
        if (empty($materials)) {
            $this->dispatch('notify', 'Der Materialkatalog ist leer. Laden Sie zuerst Baustoffe an.');
            return;
        }

        try {
            $result = $parser->adjustMaterialPricesWithAi($this->aiPrompt, $materials);
            $this->aiSummary = $result['summary'] ?? 'KI-Analyse abgeschlossen.';
            $this->aiPreviewUpdates = $result['updates'] ?? [];

            if (empty($this->aiPreviewUpdates)) {
                $this->dispatch('notify', 'Keine passenden Materialien für diesen Prompt gefunden.');
            } else {
                $this->showAiPromptModal = true;
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler bei KI-Preisanpassung: ' . $e->getMessage());
        }
    }

    public function applyAiPriceUpdates()
    {
        $updatedCount = 0;
        foreach ($this->aiPreviewUpdates as $up) {
            $mat = Material::find($up['id']);
            if ($mat) {
                $mat->update([
                    'unit_price' => (float) $up['new_price'],
                    'last_price_update' => now(),
                ]);
                $updatedCount++;
            }
        }

        $this->showAiPromptModal = false;
        $this->aiPrompt = '';
        $this->aiPreviewUpdates = [];
        $this->dispatch('notify', "✨ {$updatedCount} Baustoffpreise erfolgreich per KI aktualisiert!");
    }
}; ?>

<div class="space-y-6 font-sans pb-12">
    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 p-6 rounded-3xl border border-indigo-500/20 shadow-2xl text-white relative overflow-hidden flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1 relative z-10">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-blue-500/30 text-blue-200 border border-blue-400/30">
                    Stammdaten & KI-Preise
                </span>
                <span class="text-xs text-slate-300">{{ $this->materials instanceof \Illuminate\Pagination\LengthAwarePaginator ? $this->materials->total() : $this->materials->count() }} Baustoffe gelistet</span>
            </div>
            <h1 class="text-xl md:text-2xl font-black tracking-tight text-white">Material- & Baustoffkatalog</h1>
            <p class="text-xs text-slate-300 max-w-xl">Zentrale Preisverwaltung aller Baumaterialien. Preisanpassungen manuell in der UI oder per KI-Prompt (z. B. <em>"Zement um +8% erhöhen"</em>).</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 relative z-10 w-full md:w-auto">
            @if(($this->materials instanceof \Illuminate\Pagination\LengthAwarePaginator ? $this->materials->total() : $this->materials->count()) === 0)
                <button wire:click="seedDefaultMaterials" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl transition shadow-md shadow-emerald-500/20 flex items-center gap-1.5 cursor-pointer">
                    <span>🌱 Standard-Baustoffe laden</span>
                </button>
            @endif

            <button wire:click="openCreateModal" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl transition shadow-md shadow-blue-500/20 flex items-center gap-1.5 cursor-pointer">
                <span>➕ Material anlegen</span>
            </button>
        </div>
    </div>

    <!-- KI PREISANPASSUNGS ASSISTENT PROMPT BAR -->
    <div class="bg-gradient-to-r from-blue-50/90 via-indigo-50/70 to-blue-50/90 border border-blue-200 rounded-3xl p-5 shadow-sm space-y-3">
        <div class="flex items-center gap-2">
            <span class="text-lg">🤖</span>
            <div>
                <h3 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">KI-Preisanpassungs-Assistent (Prompts)</h3>
                <p class="text-[11px] text-slate-600">Geben Sie eine natürliche Anweisung zur Aktualisierung von Baustoffpreisen ein.</p>
            </div>
        </div>

        <form wire:submit="processAiPriceAdjustment" class="flex flex-col sm:flex-row items-stretch gap-2.5">
            <input wire:model="aiPrompt" type="text" 
                   class="flex-1 bg-white border border-blue-300 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:border-blue-600 shadow-2xs placeholder-slate-400"
                   placeholder="z. B. 'Erhöhe alle Zement- und Betonpreise um 8%' oder 'Setze Mineralwolle 120mm auf 14.90 €'">

            <button type="submit" wire:loading.attr="disabled" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-2xl shadow-md shadow-blue-500/20 transition cursor-pointer flex items-center justify-center gap-1.5 shrink-0">
                <span wire:loading.remove wire:target="processAiPriceAdjustment">✨ Mit KI anpassen</span>
                <span wire:loading wire:target="processAiPriceAdjustment">Berechne Preise...</span>
            </button>
        </form>

        <!-- Prompt Suggestions Chips -->
        <div class="flex items-center gap-2 flex-wrap pt-1">
            <span class="text-[10px] font-bold text-slate-500 uppercase">Beispiel-Prompts:</span>
            <button type="button" wire:click="$set('aiPrompt', 'Erhöhe alle Zement- und Estrichpreise um 7,5%')" class="px-2.5 py-1 bg-white hover:bg-blue-100 border border-blue-200 text-blue-900 rounded-xl text-[11px] font-semibold transition shadow-2xs">
                💡 "Zement & Estrich +7,5%"
            </button>
            <button type="button" wire:click="$set('aiPrompt', 'Erhöhe alle Materialien in der Kategorie Dämmung um 5%')" class="px-2.5 py-1 bg-white hover:bg-blue-100 border border-blue-200 text-blue-900 rounded-xl text-[11px] font-semibold transition shadow-2xs">
                💡 "Dämmung +5%"
            </button>
            <button type="button" wire:click="$set('aiPrompt', 'Senke Bewehrungsstahl um 3%')" class="px-2.5 py-1 bg-white hover:bg-blue-100 border border-blue-200 text-blue-900 rounded-xl text-[11px] font-semibold transition shadow-2xs">
                💡 "Bewehrungsstahl -3%"
            </button>
        </div>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col md:flex-row justify-between items-stretch md:items-center gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
            <input wire:model.live.debounce.150ms="searchQuery" type="text" 
                   class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 font-medium focus:outline-none focus:border-blue-600 focus:bg-white transition"
                   placeholder="Material, Lieferant, SKU oder Kategorie suchen...">
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <!-- Category Filter Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 scrollbar-none">
            <button wire:click="$set('selectedCategory', 'all')" 
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer {{ $selectedCategory === 'all' ? 'bg-slate-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Alle Gewerke
            </button>
            @foreach($this->categories as $cat)
                <button wire:click="$set('selectedCategory', '{{ $cat }}')" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer {{ $selectedCategory === $cat ? 'bg-blue-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $cat }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- MATERIALS TABLE -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900 text-slate-200 text-[11px] font-extrabold uppercase tracking-wider">
                        <th class="py-3.5 px-4">Baustoff / Bezeichnung</th>
                        <th class="py-3.5 px-4">Kategorie</th>
                        <th class="py-3.5 px-4">Einheit</th>
                        <th class="py-3.5 px-4 text-right">Einzelpreis (€ Netto)</th>
                        <th class="py-3.5 px-4">Lieferant / Hersteller</th>
                        <th class="py-3.5 px-4">Letztes Preis-Update</th>
                        <th class="py-3.5 px-4 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($this->materials as $mat)
                        <tr class="hover:bg-slate-50/80 transition group">
                            <!-- Name & Notes -->
                            <td class="py-3.5 px-4">
                                <div class="font-extrabold text-slate-900 group-hover:text-blue-600 transition">{{ $mat->name }}</div>
                                @if($mat->sku)
                                    <div class="text-[10px] text-slate-400 font-mono">Art-Nr: {{ $mat->sku }}</div>
                                @endif
                                @if($mat->notes)
                                    <div class="text-[11px] text-slate-500 italic mt-0.5">{{ $mat->notes }}</div>
                                @endif
                            </td>

                            <!-- Category -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ $mat->category }}
                                </span>
                            </td>

                            <!-- Unit -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded text-[11px] border border-slate-200">
                                    {{ $mat->unit }}
                                </span>
                            </td>

                            <!-- Price (Inline Editable) -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <input type="number" step="0.01" value="{{ number_format($mat->unit_price, 2, '.', '') }}"
                                           wire:change="updatePriceInline('{{ $mat->id }}', $event.target.value)"
                                           class="w-24 text-right bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-blue-600 font-black text-slate-900 px-2 py-1 rounded-lg text-xs transition">
                                    <span class="font-extrabold text-slate-500">€</span>
                                </div>
                            </td>

                            <!-- Supplier -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-slate-700 font-medium">
                                {{ $mat->supplier ?: '—' }}
                            </td>

                            <!-- Last Update -->
                            <td class="py-3.5 px-4 whitespace-nowrap text-[11px] text-slate-500">
                                {{ $mat->last_price_update ? $mat->last_price_update->format('d.m.Y H:i') : 'Keine Änderung' }}
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button wire:click="openEditModal('{{ $mat->id }}')" class="p-1.5 bg-slate-100 hover:bg-blue-100 text-slate-700 hover:text-blue-700 rounded-lg transition" title="Bearbeiten">
                                        ✏️
                                    </button>
                                    <button wire:click="deleteMaterial('{{ $mat->id }}')" wire:confirm="Soll dieses Material wirklich gelöscht werden?" class="p-1.5 bg-slate-100 hover:bg-rose-100 text-slate-700 hover:text-rose-700 rounded-lg transition" title="Löschen">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 italic bg-slate-50/50">
                                Keine Baustoffe für diese Filterkriterien vorhanden.<br>
                                <button wire:click="openCreateModal" class="mt-3 px-4 py-2 bg-blue-600 text-white font-bold text-xs rounded-xl shadow-2xs">
                                    + Ersten Baustoff anlegen
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION & RESULTS FOOTER -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-xs text-slate-500 font-medium">
                @if($this->materials instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    Zeige <span class="font-extrabold text-slate-900">{{ $this->materials->firstItem() ?? 0 }}</span> bis <span class="font-extrabold text-slate-900">{{ $this->materials->lastItem() ?? 0 }}</span> von insgesamt <span class="font-extrabold text-slate-900">{{ $this->materials->total() }}</span> Baustoffen
                @else
                    Gesamt: <span class="font-extrabold text-slate-900">{{ $this->materials->count() }}</span> Baustoffe
                @endif
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 text-xs text-slate-600 font-medium">
                    <span>Pro Seite:</span>
                    <select wire:model.live="perPage" class="bg-white border border-slate-300 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-800 focus:outline-none focus:border-blue-600">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="-1">Alle</option>
                    </select>
                </div>

                @if($this->materials instanceof \Illuminate\Pagination\LengthAwarePaginator && $this->materials->hasPages())
                    <div class="text-xs font-medium">
                        {{ $this->materials->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- MANUELL ERSTELLEN / BEARBEITEN MODAL -->
    @if($showModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <h3 class="text-base font-extrabold text-white">
                        {{ $editingMaterialId ? 'Baustoff bearbeiten' : 'Neuen Baustoff anlegen' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <form wire:submit="saveMaterial" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Baustoff / Produkt-Bezeichnung *</label>
                        <input wire:model="name" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="z. B. Zement CEM II 32.5 R (25kg Sack)" required>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kategorie *</label>
                            <select wire:model="category" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white">
                                @foreach($this->categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Einheit *</label>
                            <select wire:model="unit" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white">
                                <option value="Sack">Sack</option>
                                <option value="m²">m² (Quadratmeter)</option>
                                <option value="m³">m³ (Kubikmeter)</option>
                                <option value="kg">kg (Kilogramm)</option>
                                <option value="t">t (Tonne)</option>
                                <option value="Stk">Stk (Stück)</option>
                                <option value="lfm">lfm (Laufmeter)</option>
                                <option value="Rolle">Rolle</option>
                                <option value="Kartusche">Kartusche</option>
                                <option value="L">L (Liter)</option>
                                <option value="Pau">Pauschale</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Einzelpreis Netto (€) *</label>
                            <input wire:model="unitPrice" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Artikelnummer / SKU</label>
                            <input wire:model="sku" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="z. B. MAT-9021">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Lieferant / Hersteller</label>
                        <input wire:model="supplier" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="z. B. Heidelberg Materials / Baustoff Union">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notizen & Besonderheiten</label>
                        <textarea wire:model="notes" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="Optionale Anmerkungen..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-extrabold shadow-md shadow-blue-500/20">
                            💾 Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- KI PREISANPASSUNGS PROPOSAL MODAL (PREVIEW BEFORE APPLYING) -->
    @if($showAiPromptModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden my-6 flex flex-col max-h-[85vh]">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🤖</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Vorgeschlagene Preisanpassungen der KI</h3>
                            <p class="text-[11px] text-blue-200">{{ $aiSummary }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showAiPromptModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto grow">
                    <p class="text-xs text-slate-600">Überprüfen Sie die von der KI berechneten Preisänderungen, bevor diese in den Materialkatalog übernommen werden:</p>

                    <div class="bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 text-[10px] font-extrabold uppercase">
                                    <th class="py-2.5 px-3">Baustoff</th>
                                    <th class="py-2.5 px-3 text-right">Bisheriger Preis</th>
                                    <th class="py-2.5 px-3 text-right">Neuer KI-Preis</th>
                                    <th class="py-2.5 px-3">Änderungsgrund</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($aiPreviewUpdates as $up)
                                    <tr>
                                        <td class="py-2.5 px-3 font-bold text-slate-900">{{ $up['name'] }}</td>
                                        <td class="py-2.5 px-3 text-right text-slate-500 line-through">{{ number_format($up['old_price'], 2, ',', '.') }} €</td>
                                        <td class="py-2.5 px-3 text-right font-black text-emerald-700 bg-emerald-50">{{ number_format($up['new_price'], 2, ',', '.') }} €</td>
                                        <td class="py-2.5 px-3 text-[11px] text-slate-600 italic">{{ $up['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center shrink-0">
                    <span class="text-xs font-bold text-slate-500">{{ count($aiPreviewUpdates) }} Position(en) betroffen</span>
                    <div class="flex space-x-3">
                        <button type="button" wire:click="$set('showAiPromptModal', false)" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold">Verwerfen</button>
                        <button type="button" wire:click="applyAiPriceUpdates" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold shadow-md shadow-emerald-500/20">
                            ✨ Preisanpassung übernehmen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
