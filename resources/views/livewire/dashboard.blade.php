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

    // AI Weekly Report Integration
    public bool $showWeeklyReportModal = false;
    public string $weeklyReportText = '';

    public function generateWeeklyReport(OpenAiParserService $parser)
    {
        $project = $this->selectedProjectId ? Project::find($this->selectedProjectId) : Project::first();
        if (!$project) {
            $this->dispatch('notify', 'Keine Baustelle ausgewählt.');
            return;
        }

        $logs = \App\Models\DailyLog::where('project_id', $project->id)
            ->orderBy('date', 'desc')
            ->take(7)
            ->get()
            ->map(fn($l) => [
                'date' => $l->date,
                'weather' => $l->weather,
                'work' => $l->work_performed,
                'special' => $l->special_occurrences
            ])
            ->toArray();

        if (empty($logs)) {
            $this->dispatch('notify', 'Keine Bautagebuch-Einträge für diese Baustelle vorhanden.');
            return;
        }

        try {
            $this->weeklyReportText = $parser->generateWeeklyReportFromLogs($logs);
            $this->showWeeklyReportModal = true;
            $this->dispatch('notify', '✨ KI-Wochenbericht erfolgreich generiert!');
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler beim Wochenbericht: ' . $e->getMessage());
        }
    }
}; ?>

<div class="space-y-8 font-sans">
    <!-- Header Summary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Stat Card 1 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aktive Baustellen</p>
                <span class="p-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200/60 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3 tracking-tight">{{ $this->stats['active_projects'] }}</p>
            <span class="inline-flex items-center text-xs font-semibold text-blue-600 mt-2">
                In laufender Betreuung
            </span>
        </div>

        <!-- Stat Card 2 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamtes Budget (Soll)</p>
                <span class="p-2.5 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200/60 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3 tracking-tight">{{ number_format($this->stats['total_budget'], 2, ',', '.') }} €</p>
            <span class="inline-flex items-center text-xs font-semibold text-slate-500 mt-2">
                Kalkuliert inkl. 15% Puffer
            </span>
        </div>

        <!-- Stat Card 3 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamte Ist-Kosten</p>
                <span class="p-2.5 rounded-xl bg-rose-50 text-rose-600 border border-rose-200/60 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-rose-600 mt-3 tracking-tight">{{ number_format($this->stats['total_costs'], 2, ',', '.') }} €</p>
            <span class="inline-flex items-center text-xs font-semibold text-slate-500 mt-2">
                Material & Subunternehmer
            </span>
        </div>

        <!-- Stat Card 4 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Verbleibende Marge</p>
                <span class="p-2.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200/60 shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-emerald-600 mt-3 tracking-tight">{{ number_format($this->stats['margin'], 1, ',', '.') }} %</p>
            <span class="inline-flex items-center text-xs font-semibold text-slate-500 mt-2">
                Restbudget: {{ number_format($this->stats['remaining_budget'], 0, ',', '.') }} €
            </span>
        </div>
    </div>

    <!-- Main Workspace Split Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Projects Directory List (Left Column) -->
        <div class="lg:col-span-7 bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200/80 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/60">
                <div>
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                        Baustellenübersicht (Excel-Pipeline)
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Projektübersicht nach Bauabschnitten & KW-Planung</p>
                </div>
                <button wire:click="openCreateProject" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-md shadow-blue-500/10 transition duration-150">
                    + Neue Baustelle anlegen
                </button>
            </div>

            <div class="divide-y divide-slate-200/80">
                @foreach ($this->projects as $proj)
                    <div wire:key="{{ $proj->id }}" wire:click="selectProject('{{ $proj->id }}')" 
                         class="p-6 cursor-pointer hover:bg-slate-50/80 transition duration-150 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 {{ $this->selectedProjectId === $proj->id ? 'bg-blue-50/60 border-l-4 border-blue-600' : '' }}">
                        
                        <!-- Project Info -->
                        <div class="space-y-1.5 max-w-md">
                            <h4 class="font-bold text-slate-900 text-base tracking-tight hover:text-blue-600 transition">{{ $proj->name }}</h4>
                            <p class="text-xs text-slate-600 font-medium leading-relaxed">{{ $proj->work_type }} <span class="text-slate-300">•</span> {{ $proj->city_street }}</p>
                            
                            <div class="pt-1">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">
                                    KW {{ $proj->start_week }} — KW {{ $proj->end_week }}
                                </span>
                            </div>
                        </div>

                        <!-- Cost & Progress -->
                        <div class="text-left sm:text-right space-y-2 w-full sm:w-auto min-w-[200px]">
                            @php 
                                $costSum = $proj->actualCosts->sum('cost_amount');
                                $budgetTotal = $proj->budget?->total_with_buffer ?: 1;
                                $percent = min(($costSum / $budgetTotal) * 100, 100);
                            @endphp

                            <div class="flex justify-between sm:justify-end items-center space-x-2">
                                <span class="text-xs text-slate-500 uppercase font-semibold">Ist / Soll:</span>
                                <span class="text-sm font-bold text-slate-900">
                                    <span class="{{ $costSum > $budgetTotal ? 'text-rose-600' : 'text-blue-600' }}">{{ number_format($costSum, 2, ',', '.') }} €</span> 
                                    <span class="text-slate-400">/</span> 
                                    <span class="text-slate-700">{{ number_format($budgetTotal, 2, ',', '.') }} €</span>
                                </span>
                            </div>

                            <!-- High Visibility Bar -->
                            <div class="space-y-1">
                                <div class="w-full bg-slate-200/80 rounded-full h-3 overflow-hidden border border-slate-300/60 p-0.5 shadow-inner">
                                    <div class="h-full rounded-full transition-all duration-500 {{ $percent > 90 ? 'bg-gradient-to-r from-amber-500 to-rose-600' : 'bg-gradient-to-r from-blue-600 to-cyan-500' }}" style="width: {{ max($percent, 2) }}%"></div>
                                </div>
                                <div class="flex justify-end text-[11px] font-bold text-slate-600">
                                    {{ number_format($percent, 1, ',', '.') }}% ausgeschöpft
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Project Detail Panel (Right Column) -->
        <div class="lg:col-span-5 bg-white border border-slate-200/80 rounded-2xl shadow-sm p-6 sticky top-6">
            @if ($this->selectedProject)
                <div class="space-y-6">
                    <!-- Title & Header -->
                    <div class="flex justify-between items-start border-b border-slate-200/80 pb-4">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600">Ausgewählte Baustelle</span>
                            <h3 class="text-xl font-bold text-slate-900 tracking-tight mt-0.5">{{ $this->selectedProject->name }}</h3>
                            <p class="text-xs text-slate-600 mt-1">{{ $this->selectedProject->contact_address ?: $this->selectedProject->city_street }}</p>
                        </div>
                        <button wire:click="closeProjectDetails" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Budget Detail Box -->
                    <div class="space-y-3">
                        <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Budget-Detailaufschlüsselung</h4>
                        <div class="bg-slate-50/80 p-4 rounded-xl space-y-4 border border-slate-200/80">
                            <!-- Material Budget -->
                            <div>
                                <div class="flex justify-between text-xs font-semibold mb-1">
                                    <span class="text-amber-900 flex items-center gap-1.5 font-bold">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Materialbudget
                                    </span>
                                    <span class="text-slate-900 font-bold">{{ number_format($this->selectedProject->budget?->material_budget, 2, ',', '.') }} €</span>
                                </div>
                                @php
                                    $matCosts = $this->selectedProject->actualCosts->where('type', 'material')->sum('cost_amount');
                                    $matBudget = $this->selectedProject->budget?->material_budget ?: 1;
                                    $matPercent = min(($matCosts / $matBudget) * 100, 100);
                                @endphp
                                <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden border border-slate-300/60 p-0.5">
                                    <div class="bg-amber-500 h-full rounded-full transition-all" style="width: {{ $matPercent }}%"></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-600 mt-1 font-semibold">
                                    <span>Verbraucht: {{ number_format($matCosts, 2, ',', '.') }} €</span>
                                    <span>{{ number_format($matPercent, 1) }}%</span>
                                </div>
                            </div>

                            <!-- Wage / Subcontractor Budget -->
                            <div>
                                <div class="flex justify-between text-xs font-semibold mb-1">
                                    <span class="text-blue-900 flex items-center gap-1.5 font-bold">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span> Lohn- & Fremdleistung
                                    </span>
                                    <span class="text-slate-900 font-bold">{{ number_format($this->selectedProject->budget?->wage_budget, 2, ',', '.') }} €</span>
                                </div>
                                @php
                                    $wageCosts = $this->selectedProject->actualCosts->whereIn('type', ['subcontractor', 'internal_wage'])->sum('cost_amount');
                                    $wageBudget = $this->selectedProject->budget?->wage_budget ?: 1;
                                    $wagePercent = min(($wageCosts / $wageBudget) * 100, 100);
                                @endphp
                                <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden border border-slate-300/60 p-0.5">
                                    <div class="bg-blue-600 h-full rounded-full transition-all" style="width: {{ $wagePercent }}%"></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-600 mt-1 font-semibold">
                                    <span>Verbraucht: {{ number_format($wageCosts, 2, ',', '.') }} €</span>
                                    <span>{{ number_format($wagePercent, 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Parser & Weekly Report Card -->
                    <div class="bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 border border-indigo-200 p-4 rounded-xl space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                🤖 KI-Assistent Baustelle
                            </span>
                            <div class="flex space-x-2">
                                <button wire:click="generateWeeklyReport" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-lg shadow-xs transition flex items-center gap-1">
                                    📊 KI-Wochenbericht
                                </button>
                                <button wire:click="openParseOffer" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-lg shadow-xs transition">
                                    KI-Einlesen
                                </button>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-600 leading-relaxed">Erzeugen Sie per Klick einen Wochenbericht für Hausverwaltungen & Eigentümer oder analysieren Sie Subunternehmer-LVs.</p>
                    </div>

                    <!-- Offers & Items list -->
                    <div class="space-y-3">
                        <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Erstellte Angebote</h4>
                        <div class="space-y-2">
                            @forelse ($this->selectedProject->offers as $offer)
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex justify-between items-center">
                                    <div>
                                        <p class="text-xs font-bold text-slate-900">Nr: {{ $offer->offer_number }}</p>
                                        <p class="text-[10px] text-slate-500 font-medium">{{ date('d.m.Y', strtotime($offer->date)) }} | {{ $offer->sections->count() }} Abschnitte</p>
                                    </div>
                                    <p class="text-xs font-bold text-blue-700">{{ number_format($offer->total_net, 2, ',', '.') }} €</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 italic">Noch keine Angebote erfasst.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Actual Costs Form Trigger & List -->
                    <div class="space-y-3 pt-4 border-t border-slate-200/80">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Ist-Kosten Belege</h4>
                            <button wire:click="openAddCost" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition">+ Beleg erfassen</button>
                        </div>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            @forelse ($this->selectedProject->actualCosts as $cost)
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex justify-between items-center text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900">{{ $cost->description }}</p>
                                        <p class="text-[10px] text-slate-500 font-medium">{{ date('d.m.Y', strtotime($cost->date)) }} • {{ ucfirst($cost->type) }}</p>
                                    </div>
                                    <p class="font-bold text-rose-600">-{{ number_format($cost->cost_amount, 2, ',', '.') }} €</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 italic">Keine Belege vorhanden.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @else
                <!-- Empty Selection Placeholder -->
                <div class="py-16 flex flex-col items-center justify-center text-center space-y-3 text-slate-500">
                    <div class="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 shadow-inner">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div class="max-w-xs space-y-1">
                        <p class="text-sm font-bold text-slate-900">Keine Baustelle ausgewählt</p>
                        <p class="text-xs text-slate-500">Klicken Sie in der Liste links auf ein Projekt, um das kalkulierte Budget und die Belege einzusehen.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- MODALS -->

    <!-- 1. Create Project Modal -->
    @if($showCreateProjectModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Neue Baustelle anlegen</h3>
                    <button wire:click="$set('showCreateProjectModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>
                <form wire:submit="saveProject" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Baustellen-Bezeichnung</label>
                        <input wire:model="projectName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="z. B. WEG Ingolstädter Str. 11" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Art der Arbeiten</label>
                        <input wire:model="projectWorkType" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="z. B. Flachdachsanierung" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">PLZ</label>
                            <input wire:model="projectZip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="85092">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ort & Straße</label>
                            <input wire:model="projectCityStreet" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="Kösching">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">KW Beginn</label>
                            <input wire:model="projectStartWeek" type="number" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">KW Ende</label>
                            <input wire:model="projectEndWeek" type="number" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showCreateProjectModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Projekt speichern</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- 2. Add Cost Modal -->
    @if($showAddCostModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Ist-Kosten Beleg erfassen</h3>
                    <button wire:click="$set('showAddCostModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>
                <form wire:submit="saveCost" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kategorie</label>
                        <select wire:model.live="costType" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600">
                            <option value="material">Materialeinkauf</option>
                            <option value="subcontractor">Subunternehmer / Fremdleistung</option>
                            <option value="internal_wage">Eigene Lohnstunden</option>
                            <option value="other">Sonstiges</option>
                        </select>
                    </div>
                    @if($costType === 'subcontractor')
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subunternehmer Name</label>
                            <input wire:model="costSubcontractor" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600" placeholder="z. B. Harry, Hofbauer, Samir" required>
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Netto-Betrag (€)</label>
                        <input wire:model="costAmount" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Beschreibung / Beleg-Nr</label>
                        <input wire:model="costDescription" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Datum</label>
                        <input wire:model="costDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:border-blue-600" required>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showAddCostModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Beleg buchen</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- 3. Parse Offer Modal -->
    @if($showParseOfferModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        KI Angebote / LVs analysieren
                    </h3>
                    <button wire:click="$set('showParseOfferModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>
                <form wire:submit="parseOfferDirectly" class="p-6 space-y-4">
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Fügen Sie hier unstrukturierten Text aus einer LV-E-Mail oder Kopie einer PDF ein. OpenAI extrahiert automatisch Positionen, Mengen & Preise und passt das Budget an.
                    </p>
                    <div>
                        <textarea wire:model="offerText" rows="10" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 font-mono focus:outline-none focus:border-blue-600" placeholder="Kopieren Sie den Text hier hinein..." required></textarea>
                        @error('offerText') <span class="text-rose-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showParseOfferModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center shadow-md shadow-blue-500/10" wire:loading.attr="disabled">
                            <span wire:loading class="border-2 border-t-transparent border-white rounded-full w-4 h-4 animate-spin mr-2"></span>
                            KI-Strukturierung starten
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- 4. KI Weekly Report Modal -->
    @if ($showWeeklyReportModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-purple-950 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📊</span>
                        <h3 class="text-base font-extrabold text-white">KI-Wochenbericht für Hausverwaltungen & Eigentümer</h3>
                    </div>
                    <button wire:click="$set('showWeeklyReportModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-sans text-slate-800 leading-relaxed max-h-96 overflow-y-auto whitespace-pre-wrap selection:bg-purple-100">{{ $weeklyReportText }}</div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-xs text-slate-500">Zusammenfassung der letzten 7 Tage Bautagebuch</span>
                        <div class="flex space-x-3">
                            <button type="button" wire:click="$set('showWeeklyReportModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Schließen</button>
                            <button type="button" onclick="navigator.clipboard.writeText(`{{ addslashes($weeklyReportText) }}`); alert('Wochenbericht in Zwischenablage kopiert!');" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-500/20">
                                📋 In Zwischenablage kopieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
