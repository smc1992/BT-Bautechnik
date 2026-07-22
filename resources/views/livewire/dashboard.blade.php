<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\Budget;
use App\Models\ActualCost;
use App\Models\Offer;
use App\Models\OfferSection;
use App\Models\OfferItem;
use App\Services\OpenAiParserService;
use App\Jobs\ParseOfferPdfJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    // Selected Project & Modals
    public ?string $selectedProjectId = null;
    public bool $showCreateProjectModal = false;
    public bool $showAddCostModal = false;
    public bool $showParseOfferModal = false;
    public bool $isParsing = false;

    // Create Project Form
    public string $projectName = '';
    public string $projectZip = '';
    public string $projectCityStreet = '';
    public string $projectContactAddress = '';
    public string $projectPhone = '';
    public string $projectWorkType = '';
    public int $projectStartWeek = 20;
    public int $projectEndWeek = 24;
    public string $projectStatus = 'active';

    // Add Actual Cost Form
    public string $costType = 'material'; // material, subcontractor, internal_wage, other
    public string $costSubcontractor = '';
    public float $costAmount = 0.0;
    public string $costDescription = '';
    public string $costDate = '';

    // Parse Offer Form
    public string $offerText = '';

    public function mount()
    {
        $this->costDate = date('Y-m-d');
    }

    // Computed / Realtime Stats
    public function getStatsProperty()
    {
        $activeCount = Project::where('status', 'active')->count();
        
        $totalBudget = Budget::sum('total_with_buffer');
        $materialBudget = Budget::sum('material_budget');
        $wageBudget = Budget::sum('wage_budget');

        $totalCosts = ActualCost::sum('cost_amount');
        $materialCosts = ActualCost::where('type', 'material')->sum('cost_amount');
        $wageCosts = ActualCost::whereIn('type', ['subcontractor', 'internal_wage'])->sum('cost_amount');

        $remainingBudget = $totalBudget - $totalCosts;
        $margin = $totalBudget > 0 ? (($totalBudget - $totalCosts) / $totalBudget) * 100 : 0;

        return [
            'active_projects' => $activeCount,
            'total_budget' => $totalBudget,
            'material_budget' => $materialBudget,
            'wage_budget' => $wageBudget,
            'total_costs' => $totalCosts,
            'material_costs' => $materialCosts,
            'wage_costs' => $wageCosts,
            'remaining_budget' => $remainingBudget,
            'margin' => $margin,
        ];
    }

    public function getProjectsProperty()
    {
        return Project::with(['budget', 'actualCosts'])->get();
    }

    public function getSelectedProjectProperty()
    {
        if (!$this->selectedProjectId) {
            return null;
        }
        return Project::with(['budget', 'actualCosts', 'offers.sections.items'])->find($this->selectedProjectId);
    }

    // Actions
    public function selectProject($id)
    {
        $this->selectedProjectId = $id;
    }

    public function closeProjectDetails()
    {
        $this->selectedProjectId = null;
    }

    public function openCreateProject()
    {
        $this->resetProjectForm();
        $this->showCreateProjectModal = true;
    }

    public function resetProjectForm()
    {
        $this->projectName = '';
        $this->projectZip = '';
        $this->projectCityStreet = '';
        $this->projectContactAddress = '';
        $this->projectPhone = '';
        $this->projectWorkType = '';
        $this->projectStartWeek = intval(date('W'));
        $this->projectEndWeek = intval(date('W')) + 4;
        $this->projectStatus = 'active';
    }

    public function saveProject()
    {
        $this->validate([
            'projectName' => 'required|string|max:255',
            'projectWorkType' => 'required|string|max:255',
        ]);

        DB::transaction(function () {
            $project = Project::create([
                'name' => $this->projectName,
                'zip' => $this->projectZip,
                'city_street' => $this->projectCityStreet,
                'contact_address' => $this->projectContactAddress,
                'phone' => $this->projectPhone,
                'work_type' => $this->projectWorkType,
                'start_week' => $this->projectStartWeek,
                'end_week' => $this->projectEndWeek,
                'status' => $this->projectStatus,
            ]);

            Budget::create([
                'project_id' => $project->id,
                'material_budget' => 0.00,
                'wage_budget' => 0.00,
                'buffer_rate' => 15.00,
                'buffer_amount' => 0.00,
                'total_with_buffer' => 0.00,
            ]);
        });

        $this->showCreateProjectModal = false;
        $this->dispatch('notify', 'Projekt erfolgreich angelegt!');
    }

    public function openAddCost()
    {
        $this->costAmount = 0.0;
        $this->costDescription = '';
        $this->costSubcontractor = '';
        $this->showAddCostModal = true;
    }

    public function saveCost()
    {
        $this->validate([
            'costAmount' => 'required|numeric|min:0.01',
            'costDescription' => 'required|string|max:255',
            'costDate' => 'required|date',
        ]);

        ActualCost::create([
            'project_id' => $this->selectedProjectId,
            'type' => $this->costType,
            'subcontractor_name' => $this->costType === 'subcontractor' ? $this->costSubcontractor : null,
            'cost_amount' => $this->costAmount,
            'description' => $this->costDescription,
            'date' => $this->costDate,
        ]);

        $this->showAddCostModal = false;
        $this->dispatch('notify', 'Ist-Kosten erfolgreich erfasst!');
    }

    public function openParseOffer()
    {
        $this->offerText = '';
        $this->showParseOfferModal = true;
    }

    public function parseOfferDirectly(OpenAiParserService $parser)
    {
        $this->validate([
            'offerText' => 'required|string|min:10',
        ]);

        $this->isParsing = true;

        try {
            // We run parsing synchronously here for instant response on host environment
            $parsedData = $parser->parseOfferDocument($this->offerText);

            DB::transaction(function () use ($parsedData) {
                $offerNumber = 'AN-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                
                $offer = Offer::create([
                    'project_id' => $this->selectedProjectId,
                    'offer_number' => $offerNumber,
                    'date' => date('Y-m-d'),
                    'status' => 'draft',
                    'total_net' => 0.00,
                    'total_gross' => 0.00,
                ]);

                $totalNet = 0.00;
                $predictedMaterialBudget = 0.00;
                $predictedWageBudget = 0.00;

                foreach ($parsedData['sections'] as $secIndex => $secData) {
                    $section = OfferSection::create([
                        'offer_id' => $offer->id,
                        'title' => $secData['title'],
                        'sort_order' => $secIndex + 1,
                    ]);

                    foreach ($secData['items'] as $itemData) {
                        $itemTotal = floatval($itemData['quantity']) * floatval($itemData['unit_price']);
                        $totalNet += $itemTotal;

                        OfferItem::create([
                            'section_id' => $section->id,
                            'pos_number' => $itemData['pos_number'],
                            'description' => $itemData['description'],
                            'quantity' => $itemData['quantity'],
                            'unit' => $itemData['unit'],
                            'unit_price' => $itemData['unit_price'],
                            'total_price' => $itemTotal,
                        ]);

                        // Heuristic budget categorisation
                        $descLower = mb_strtolower($itemData['description']);
                        $isWage = Str::contains($descLower, ['montage', 'lohn', 'arbeit', 'betonieren', 'abbruch', 'stunden', 'lfm', 'entsorgung']) 
                                  && !Str::contains($descLower, ['tür', 'fenster', 'material']);
                        
                        if ($isWage) {
                            $predictedWageBudget += $itemTotal;
                        } else {
                            if (Str::contains($descLower, ['tür', 'fenster', 'material'])) {
                                $predictedMaterialBudget += $itemTotal * 0.8;
                                $predictedWageBudget += $itemTotal * 0.2;
                            } else {
                                $predictedMaterialBudget += $itemTotal;
                            }
                        }
                    }
                }

                $vatAmount = $totalNet * 0.19;
                $offer->update([
                    'total_net' => $totalNet,
                    'total_gross' => $totalNet + $vatAmount,
                ]);

                // Update project budget
                $project = Project::find($this->selectedProjectId);
                $budget = $project->budget;
                if ($budget) {
                    $subtotal = $predictedMaterialBudget + $predictedWageBudget;
                    $bufferAmount = $subtotal * ($budget->buffer_rate / 100);
                    $budget->update([
                        'material_budget' => $predictedMaterialBudget,
                        'wage_budget' => $predictedWageBudget,
                        'buffer_amount' => $bufferAmount,
                        'total_with_buffer' => $subtotal + $bufferAmount,
                    ]);
                }
            });

            $this->showParseOfferModal = false;
            $this->dispatch('notify', 'Angebot erfolgreich per KI strukturiert!');
        } catch (\Exception $e) {
            $this->addError('offerText', 'Fehler beim Parsen: ' . $e->getMessage());
        } finally {
            $this->isParsing = false;
        }
    }
}; ?>

<div class="space-y-6">
    <!-- Header Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Stat Card 1 -->
        <div class="bg-gray-800 border border-gray-700/50 rounded-xl p-6 shadow-md transition duration-300 hover:border-red-500/30">
            <p class="text-sm font-medium text-gray-400">Aktive Baustellen</p>
            <p class="text-3xl font-bold text-white mt-2">{{ $this->stats['active_projects'] }}</p>
        </div>
        <!-- Stat Card 2 -->
        <div class="bg-gray-800 border border-gray-700/50 rounded-xl p-6 shadow-md transition duration-300 hover:border-red-500/30">
            <p class="text-sm font-medium text-gray-400">Gesamtes Budget (Soll)</p>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($this->stats['total_budget'], 2, ',', '.') }} €</p>
            <span class="text-xs text-gray-400">Inkl. 15% Puffer</span>
        </div>
        <!-- Stat Card 3 -->
        <div class="bg-gray-800 border border-gray-700/50 rounded-xl p-6 shadow-md transition duration-300 hover:border-red-500/30">
            <p class="text-sm font-medium text-gray-400">Gesamte Ist-Kosten</p>
            <p class="text-3xl font-bold text-red-500 mt-2">{{ number_format($this->stats['total_costs'], 2, ',', '.') }} €</p>
            <span class="text-xs text-gray-400">Material + Fremdleistung</span>
        </div>
        <!-- Stat Card 4 -->
        <div class="bg-gray-800 border border-gray-700/50 rounded-xl p-6 shadow-md transition duration-300 hover:border-red-500/30">
            <p class="text-sm font-medium text-gray-400">Verbleibende Marge</p>
            <p class="text-3xl font-bold text-green-500 mt-2">{{ number_format($this->stats['margin'], 1, ',', '.') }} %</p>
            <span class="text-xs text-gray-400">Budgetüberhang</span>
        </div>
    </div>

    <!-- Main Workspace Split Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Projects Directory List (Left Column) -->
        <div class="lg:col-span-2 bg-gray-800 border border-gray-700/50 rounded-xl shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-700/50 flex justify-between items-center bg-gray-900/30">
                <h3 class="text-lg font-semibold text-white">Baustellenübersicht (Excel-Pipeline)</h3>
                <button wire:click="openCreateProject" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium text-sm rounded-lg shadow transition">
                    + Projekt anlegen
                </button>
            </div>
            <div class="divide-y divide-gray-700/50">
                @foreach ($this->projects as $proj)
                    <div wire:key="{{ $proj->id }}" wire:click="selectProject('{{ $proj->id }}')" 
                         class="p-6 cursor-pointer hover:bg-gray-700/30 transition flex items-center justify-between {{ $this->selectedProjectId === $proj->id ? 'bg-red-500/10 border-l-4 border-red-600' : '' }}">
                        <div class="space-y-1">
                            <h4 class="font-bold text-white text-base">{{ $proj->name }}</h4>
                            <p class="text-sm text-gray-400">{{ $proj->work_type }} | {{ $proj->city_street }}</p>
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold mt-1 bg-red-500/20 text-red-400">
                                KW {{ $proj->start_week }} - KW {{ $proj->end_week }}
                            </span>
                        </div>
                        <div class="text-right space-y-2">
                            <p class="text-sm font-semibold text-white">
                                {{ number_format($proj->actualCosts->sum('cost_amount'), 2, ',', '.') }} € / 
                                {{ number_format($proj->budget?->total_with_buffer, 2, ',', '.') }} €
                            </p>
                            <!-- Progress Bar -->
                            <div class="w-48 bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                @php 
                                    $costSum = $proj->actualCosts->sum('cost_amount');
                                    $budgetTotal = $proj->budget?->total_with_buffer ?: 1;
                                    $percent = min(($costSum / $budgetTotal) * 100, 100);
                                @endphp
                                <div class="bg-red-500 h-2.5 rounded-full transition-all" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Project Detail Panel (Right Column) -->
        <div class="bg-gray-800 border border-gray-700/50 rounded-xl shadow-md p-6">
            @if ($this->selectedProject)
                <div class="space-y-6">
                    <div class="flex justify-between items-start border-b border-gray-700/50 pb-4">
                        <div>
                            <h3 class="text-xl font-bold text-white">{{ $this->selectedProject->name }}</h3>
                            <p class="text-sm text-gray-400">{{ $this->selectedProject->city_street }}</p>
                        </div>
                        <button wire:click="closeProjectDetails" class="text-gray-400 hover:text-white">✕</button>
                    </div>

                    <!-- Budget Detail Box -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-white text-sm uppercase tracking-wider">Budget-Detailverteilung</h4>
                        <div class="bg-gray-900/40 p-4 rounded-xl space-y-3 border border-gray-700/30">
                            <div>
                                <div class="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Materialbudget</span>
                                    <span>{{ number_format($this->selectedProject->budget?->material_budget, 2, ',', '.') }} €</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    @php
                                        $matCosts = $this->selectedProject->actualCosts->where('type', 'material')->sum('cost_amount');
                                        $matBudget = $this->selectedProject->budget?->material_budget ?: 1;
                                        $matPercent = min(($matCosts / $matBudget) * 100, 100);
                                    @endphp
                                    <div class="bg-yellow-500 h-2 rounded-full" style="width: {{ $matPercent }}%"></div>
                                </div>
                                <span class="text-[10px] text-gray-400 mt-1 block">Ist: {{ number_format($matCosts, 2, ',', '.') }} €</span>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Lohn- & Fremdleistung</span>
                                    <span>{{ number_format($this->selectedProject->budget?->wage_budget, 2, ',', '.') }} €</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    @php
                                        $wageCosts = $this->selectedProject->actualCosts->whereIn('type', ['subcontractor', 'internal_wage'])->sum('cost_amount');
                                        $wageBudget = $this->selectedProject->budget?->wage_budget ?: 1;
                                        $wagePercent = min(($wageCosts / $wageBudget) * 100, 100);
                                    @endphp
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $wagePercent }}%"></div>
                                </div>
                                <span class="text-[10px] text-gray-400 mt-1 block">Ist: {{ number_format($wageCosts, 2, ',', '.') }} €</span>
                            </div>
                        </div>
                    </div>

                    <!-- AI Parser Quick Launch -->
                    <div class="space-y-3">
                        <h4 class="font-semibold text-white text-sm uppercase tracking-wider">Angebotsautomatisierung</h4>
                        <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl flex items-center justify-between">
                            <div class="text-xs text-gray-300">Subunternehmer-LV oder E-Mail per KI einlesen</div>
                            <button wire:click="openParseOffer" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-semibold text-xs rounded transition shadow">
                                KI-Einlesen
                            </button>
                        </div>
                    </div>

                    <!-- Offers & Items list -->
                    <div class="space-y-3">
                        <h4 class="font-semibold text-white text-sm uppercase tracking-wider">Erstellte Angebote</h4>
                        <div class="space-y-2">
                            @forelse ($this->selectedProject->offers as $offer)
                                <div class="bg-gray-900/40 p-3 rounded-lg border border-gray-700/30 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-bold text-white">Nr: {{ $offer->offer_number }}</p>
                                        <p class="text-xs text-gray-400">{{ date('d.m.Y', strtotime($offer->date)) }} | {{ $offer->sections->count() }} Abschnitte</p>
                                    </div>
                                    <p class="text-sm font-bold text-red-500">{{ number_format($offer->total_net, 2, ',', '.') }} € (Netto)</p>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">Keine Angebote erfasst.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Actual Costs Form Trigger -->
                    <div class="space-y-3 pt-4 border-t border-gray-700/50">
                        <div class="flex justify-between items-center">
                            <h4 class="font-semibold text-white text-sm uppercase tracking-wider">Ist-Kosten Belege</h4>
                            <button wire:click="openAddCost" class="text-xs text-red-500 hover:text-red-400 font-bold">+ Beleg erfassen</button>
                        </div>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @forelse ($this->selectedProject->actualCosts as $cost)
                                <div class="bg-gray-900/40 p-2.5 rounded border border-gray-700/30 flex justify-between items-center text-xs">
                                    <div>
                                        <p class="font-bold text-white">{{ $cost->description }}</p>
                                        <p class="text-gray-400">{{ date('d.m.Y', strtotime($cost->date)) }} | {{ ucfirst($cost->type) }}</p>
                                    </div>
                                    <p class="font-bold text-red-400">-{{ number_format($cost->cost_amount, 2, ',', '.') }} €</p>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">Keine Belege vorhanden.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @else
                <div class="h-64 flex flex-col items-center justify-center text-center space-y-2 text-gray-400">
                    <svg class="w-12 h-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <p class="text-sm font-semibold">Keine Baustelle ausgewählt</p>
                    <p class="text-xs">Klicken Sie links auf ein Projekt, um Budgets, Angebote und Rechnungen einzusehen.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Modals -->
    
    <!-- 1. Create Project Modal -->
    @if($showCreateProjectModal)
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-gray-800 border border-gray-700 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-gray-900 border-b border-gray-700/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white">Neues Projekt anlegen</h3>
                    <button wire:click="$set('showCreateProjectModal', false)" class="text-gray-400 hover:text-white">✕</button>
                </div>
                <form wire:submit="saveProject" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Baustellen-Bezeichnung</label>
                        <input wire:model="projectName" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Art der Arbeiten</label>
                        <input wire:model="projectWorkType" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">PLZ</label>
                            <input wire:model="projectZip" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Ort & Straße</label>
                            <input wire:model="projectCityStreet" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">KW Beginn</label>
                            <input wire:model="projectStartWeek" type="number" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">KW Ende</label>
                            <input wire:model="projectEndWeek" type="number" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Adresse (Hausverwaltung)</label>
                        <input wire:model="projectContactAddress" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Telefon</label>
                        <input wire:model="projectPhone" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500">
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-700/50">
                        <button type="button" wire:click="$set('showCreateProjectModal', false)" class="px-4 py-2 bg-gray-700 hover:bg-gray-650 text-white rounded-lg text-sm font-medium">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">Projekt speichern</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- 2. Add Cost Modal -->
    @if($showAddCostModal)
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-gray-800 border border-gray-700 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-gray-900 border-b border-gray-700/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white">Ist-Kosten Beleg erfassen</h3>
                    <button wire:click="$set('showAddCostModal', false)" class="text-gray-400 hover:text-white">✕</button>
                </div>
                <form wire:submit="saveCost" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Kategorie</label>
                        <select wire:model.live="costType" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500">
                            <option value="material">Materialeinkauf</option>
                            <option value="subcontractor">Subunternehmer/Fremdleistung</option>
                            <option value="internal_wage">Eigene Lohnstunden</option>
                            <option value="other">Sonstiges</option>
                        </select>
                    </div>
                    @if($costType === 'subcontractor')
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Subunternehmer Name</label>
                            <input wire:model="costSubcontractor" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500" placeholder="z.B. Harry, Hofbauer, Samir" required>
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Netto-Betrag (€)</label>
                        <input wire:model="costAmount" type="number" step="0.01" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Beschreibung / Belegnummer</label>
                        <input wire:model="costDescription" type="text" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Datum</label>
                        <input wire:model="costDate" type="date" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-red-500" required>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-700/50">
                        <button type="button" wire:click="$set('showAddCostModal', false)" class="px-4 py-2 bg-gray-700 hover:bg-gray-650 text-white rounded-lg text-sm font-medium">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">Beleg erfassen</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- 3. Parse Offer Modal -->
    @if($showParseOfferModal)
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-gray-800 border border-gray-700 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-gray-900 border-b border-gray-700/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white">Subunternehmer-LV per KI einlesen</h3>
                    <button wire:click="$set('showParseOfferModal', false)" class="text-gray-400 hover:text-white">✕</button>
                </div>
                <form wire:submit="parseOfferDirectly" class="p-6 space-y-4">
                    <div class="text-sm text-gray-300">
                        Fügen Sie hier unstrukturierten Text aus einer LV-E-Mail oder kopierten PDF ein. Die KI wird automatisch alle Positionen (Menge, Einheit, Einzelpreis) extrahieren, das Angebot anlegen und das Budget anpassen.
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Text-Leistungsverzeichnis</label>
                        <textarea wire:model="offerText" rows="12" class="w-full bg-gray-900 border border-gray-700 rounded-lg p-3 text-xs text-white font-mono focus:outline-none focus:border-red-500" placeholder="Kopieren Sie den Text hier hinein... z.B. &#10;Pos 24: Nebenraumtüre Kunststoff - 1 Stk @ 1195.00&#10;Pos 25: Montage Nebeneingangstür Sanierung - 6 lfm @ 77.39" required></textarea>
                        @error('offerText') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-700/50">
                        <button type="button" wire:click="$set('showParseOfferModal', false)" class="px-4 py-2 bg-gray-700 hover:bg-gray-650 text-white rounded-lg text-sm font-medium">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium flex items-center" wire:loading.attr="disabled">
                            <span wire:loading class="border-2 border-t-transparent border-white rounded-full w-4 h-4 animate-spin mr-2"></span>
                            KI-Strukturierung starten
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
