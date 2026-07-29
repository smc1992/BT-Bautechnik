<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Project;
use App\Models\Budget;
use App\Models\ActualCost;
use App\Models\Offer;
use App\Models\OfferSection;
use App\Models\OfferItem;
use App\Models\ProjectPhoto;
use App\Services\OpenAiParserService;
use App\Jobs\ParseOfferPdfJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    use WithFileUploads;

    // Photo Uploads
    public $uploadPhotoFiles = [];
    public string $photoCategory = 'bestandsaufnahme';
    public string $photoCaption = '';

    // Selected Project & Modals
    public ?string $selectedProjectId = null;
    public bool $showCreateProjectModal = false;
    public bool $showAddCostModal = false;
    public bool $showParseOfferModal = false;
    public bool $showDeleteProjectModal = false;
    public ?string $projectToDeleteId = null;
    public ?string $projectToDeleteName = null;
    public bool $isParsing = false;

    // Create Project Form
    public string $projectName = '';
    public string $projectZip = '';
    public string $projectCityStreet = '';
    public string $projectContactAddress = '';
    public string $projectPhone = '';
    public string $projectWorkType = '';
    public ?int $projectStartWeek = 20;
    public ?int $projectEndWeek = 24;
    public string $projectStatus = 'active';

    // Add Actual Cost Form
    public string $costType = 'material'; // material, subcontractor, internal_wage, other
    public string $costSubcontractor = '';
    public float $costAmount = 0.0;
    public string $costDescription = '';
    public string $costDate = '';
    public $costReceiptFile;

    // Search & Filter
    public string $searchQuery = '';
    public string $statusFilter = 'all';

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
        $totalBudgetFloat = (float) $totalBudget;
        $margin = $totalBudgetFloat > 0 ? (($totalBudgetFloat - (float) $totalCosts) / $totalBudgetFloat) * 100 : 0;

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
        $query = Project::with(['budget', 'actualCosts']);

        if (!empty(trim($this->searchQuery))) {
            $term = '%' . trim($this->searchQuery) . '%';
            $query->where(function($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('city_street', 'LIKE', $term)
                  ->orWhere('work_type', 'LIKE', $term);
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    // Defect Creation within Baustellen Detail
    public bool $showCreateDefectModal = false;
    public string $defectTitle = '';
    public string $defectLocation = '';
    public string $defectDescription = '';
    public string $defectDeadline = '';
    public string $defectPriority = 'mittel';
    public string $defectAssignedContactId = '';

    public function getSubcontractorsProperty()
    {
        return \App\Models\Contact::where('type', 'subunternehmer')->get();
    }

    public function openCreateDefectModal(?string $projId = null)
    {
        if ($projId) {
            $this->selectedProjectId = $projId;
        }
        $this->defectTitle = '';
        $this->defectLocation = '';
        $this->defectDescription = '';
        $this->defectDeadline = date('Y-m-d', strtotime('+14 days'));
        $this->defectPriority = 'mittel';
        $this->defectAssignedContactId = '';
        $this->showCreateDefectModal = true;
    }

    public function saveDefectFromProjectDetail()
    {
        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'defectTitle' => 'required|string|max:255',
            'defectDescription' => 'required|string',
        ]);

        \App\Models\Defect::create([
            'project_id' => $this->selectedProjectId,
            'assigned_contact_id' => $this->defectAssignedContactId ?: null,
            'title' => $this->defectTitle,
            'location' => $this->defectLocation,
            'description' => $this->defectDescription,
            'deadline' => $this->defectDeadline ?: null,
            'priority' => $this->defectPriority,
            'status' => 'offen',
        ]);

        $this->showCreateDefectModal = false;
        $this->dispatch('notify', '⚠️ Mangel für Baustelle "' . ($this->selectedProject?->name ?: 'ausgewählt') . '" erfolgreich erfasst!');
    }

    public function getSelectedProjectProperty()
    {
        if (!$this->selectedProjectId) {
            return null;
        }
        return Project::with(['budget', 'actualCosts', 'offers.sections.items', 'photos', 'defects.assignedContact'])->find($this->selectedProjectId);
    }

    public function uploadPhotos()
    {
        if (empty($this->uploadPhotoFiles) || !$this->selectedProjectId) {
            $this->dispatch('notify', '⚠️ Bitte mindestens ein Foto auswählen.');
            return;
        }

        $count = 0;
        foreach ($this->uploadPhotoFiles as $file) {
            $path = $file->store('project_photos', 'public');
            ProjectPhoto::create([
                'project_id' => $this->selectedProjectId,
                'photo_path' => $path,
                'caption' => $this->photoCaption ?: null,
                'category' => $this->photoCategory,
            ]);
            $count++;
        }

        $this->uploadPhotoFiles = [];
        $this->photoCaption = '';
        $this->dispatch('notify', "📸 {$count} Foto(s) erfolgreich zur Baustelle hinzugefügt!");
    }

    public function deletePhoto($photoId)
    {
        $photo = ProjectPhoto::find($photoId);
        if ($photo) {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($photo->photo_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->photo_path);
            }
            $photo->delete();
            $this->dispatch('notify', '🗑️ Foto gelöscht.');
        }
    }

    public function confirmDeleteProject(string $id)
    {
        $project = Project::find($id);
        if ($project) {
            $this->projectToDeleteId = $project->id;
            $this->projectToDeleteName = $project->name;
            $this->showDeleteProjectModal = true;
        }
    }

    public function deleteProjectConfirmed()
    {
        if (!$this->projectToDeleteId) return;

        $project = Project::with('photos')->find($this->projectToDeleteId);
        if ($project) {
            $name = $project->name;

            foreach ($project->photos as $photo) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($photo->photo_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->photo_path);
                }
            }

            $project->delete();

            if ($this->selectedProjectId === $this->projectToDeleteId) {
                $this->selectedProjectId = null;
            }

            $this->dispatch('notify', "🗑️ Baustelle '{$name}' wurde erfolgreich gelöscht.");
        }

        $this->showDeleteProjectModal = false;
        $this->projectToDeleteId = null;
        $this->projectToDeleteName = null;
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
        $this->costReceiptFile = null;
        $this->showAddCostModal = true;
    }

    public function saveCost()
    {
        $this->validate([
            'costAmount' => 'required|numeric|min:0.01',
            'costDescription' => 'required|string|max:255',
            'costDate' => 'required|date',
            'costReceiptFile' => 'nullable|file|max:10240',
        ]);

        $receiptPath = null;
        if ($this->costReceiptFile) {
            $receiptPath = $this->costReceiptFile->store('cost_receipts', 'public');
        }

        ActualCost::create([
            'project_id' => $this->selectedProjectId,
            'type' => $this->costType,
            'subcontractor_name' => $this->costType === 'subcontractor' ? $this->costSubcontractor : null,
            'cost_amount' => $this->costAmount,
            'description' => $this->costDescription,
            'date' => $this->costDate,
            'receipt_path' => $receiptPath,
        ]);

        $this->showAddCostModal = false;
        $this->costReceiptFile = null;
        $this->dispatch('notify', 'Ist-Kosten / Eingangsrechnung erfolgreich verbucht!');
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
    <!-- Top Command Center Banner & Quick Actions -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white rounded-2xl p-6 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-1 relative z-10">
            <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2.5">
                <span>🏗️ BT Bautechnik CRM Steuerzentrale</span>
            </h2>
            <p class="text-xs text-slate-300 font-medium">Echtzeit-Kostenkontrolle, KI-Wochenberichte & Baustellen-Management</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 relative z-10">
            <button wire:click="openCreateProject" 
                    class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 transition-all flex items-center gap-1.5 cursor-pointer">
                <span>+ Neue Baustelle</span>
            </button>

            <a href="{{ route('daily-logs') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-slate-900/80 hover:bg-slate-800 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                <span>🎙️ Bautagebuch</span>
            </a>

            <a href="{{ route('defects') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-slate-900/80 hover:bg-slate-800 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                <span>⚠️ Mängel</span>
            </a>

            <a href="{{ route('invoices') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-slate-900/80 hover:bg-slate-800 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                <span>📄 Rechnungen</span>
            </a>

            <a href="{{ route('knowledge-base') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-blue-900/40 hover:bg-blue-900/60 text-blue-200 border border-blue-500/30 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                <span>📚 Wissen</span>
            </a>
        </div>
    </div>

    <!-- Header Summary Stats (Elevated KPI Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Stat Card 1 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-600 to-cyan-500"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aktive Baustellen</p>
                <span class="p-2.5 rounded-xl bg-blue-50 group-hover:bg-blue-600 text-blue-600 group-hover:text-white border border-blue-200/60 shadow-2xs transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3 tracking-tight">{{ $this->stats['active_projects'] }}</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100">
                <span class="inline-flex items-center text-xs font-semibold text-blue-600">
                    🟢 In laufender Betreuung
                </span>
                <span class="text-[10px] text-slate-400 font-bold">100% aktiv</span>
            </div>
        </div>

        <!-- Stat Card 2 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-600 to-indigo-600"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamtes Budget (Soll)</p>
                <span class="p-2.5 rounded-xl bg-blue-50 group-hover:bg-blue-600 text-blue-600 group-hover:text-white border border-blue-200/60 shadow-2xs transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3 tracking-tight">{{ number_format($this->stats['total_budget'], 2, ',', '.') }} €</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100">
                <span class="inline-flex items-center text-xs font-semibold text-blue-700">
                    Kalkuliert inkl. Puffer
                </span>
                <span class="text-[10px] text-blue-600 font-bold bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">15% Puffer</span>
            </div>
        </div>

        <!-- Stat Card 3 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-amber-500"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamte Ist-Kosten</p>
                <span class="p-2.5 rounded-xl bg-rose-50 group-hover:bg-rose-600 text-rose-600 group-hover:text-white border border-rose-200/60 shadow-2xs transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-rose-600 mt-3 tracking-tight">{{ number_format($this->stats['total_costs'], 2, ',', '.') }} €</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100">
                <span class="inline-flex items-center text-xs font-semibold text-slate-600">
                    Material & Nachunternehmer
                </span>
                @php
                    $consumption = $this->stats['total_budget'] > 0 ? ($this->stats['total_costs'] / $this->stats['total_budget']) * 100 : 0;
                @endphp
                <span class="text-[10px] font-bold {{ $consumption > 90 ? 'text-rose-600 bg-rose-50' : 'text-slate-600 bg-slate-100' }} px-2 py-0.5 rounded-full">
                    {{ number_format($consumption, 0) }}% verbraucht
                </span>
            </div>
        </div>

        <!-- Stat Card 4 -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Verbleibende Marge</p>
                <span class="p-2.5 rounded-xl bg-emerald-50 group-hover:bg-emerald-600 text-emerald-600 group-hover:text-white border border-emerald-200/60 shadow-2xs transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-emerald-600 mt-3 tracking-tight">{{ number_format($this->stats['margin'], 1, ',', '.') }} %</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100">
                <span class="inline-flex items-center text-xs font-semibold text-slate-600">
                    Restbudget: {{ number_format($this->stats['remaining_budget'], 0, ',', '.') }} €
                </span>
                <span class="text-[10px] font-black text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full border border-emerald-200">
                    Im Plan
                </span>
            </div>
        </div>
    </div>

    <!-- Main Workspace Split Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Projects Directory List (Full Width) -->
        <div class="lg:col-span-12 bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden space-y-0">
            <!-- Header & Search/Filter Bar -->
            <div class="p-6 border-b border-slate-200/80 bg-slate-50/60 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse"></span>
                            Baustellenübersicht & Pipeline
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Echtzeit-Projektfortschritt & Kostenkontrolle nach KW</p>
                    </div>
                    <button wire:click="openCreateProject" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-md shadow-blue-500/10 transition cursor-pointer">
                        + Neue Baustelle
                    </button>
                </div>

                <!-- Live Search & Filter Bar -->
                <div class="flex flex-col sm:flex-row gap-3 items-center justify-between pt-1">
                    <div class="relative w-full sm:w-72">
                        <input wire:model.live.debounce.250ms="searchQuery" type="text" 
                               class="w-full bg-white border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 placeholder-slate-400 focus:border-blue-600 focus:outline-none transition shadow-2xs"
                               placeholder="Baustelle, Ort oder Gewerk suchen...">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
                    </div>

                    <div class="flex items-center gap-1.5 bg-slate-200/60 p-1 rounded-xl w-full sm:w-auto overflow-x-auto">
                        <button wire:click="$set('statusFilter', 'all')" 
                                class="px-3 py-1 rounded-lg text-xs font-bold transition {{ $statusFilter === 'all' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Alle ({{ \App\Models\Project::count() }})
                        </button>
                        <button wire:click="$set('statusFilter', 'active')" 
                                class="px-3 py-1 rounded-lg text-xs font-bold transition {{ $statusFilter === 'active' ? 'bg-white text-blue-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Aktiv
                        </button>
                        <button wire:click="$set('statusFilter', 'completed')" 
                                class="px-3 py-1 rounded-lg text-xs font-bold transition {{ $statusFilter === 'completed' ? 'bg-white text-emerald-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Beendet
                        </button>
                    </div>
                </div>
            </div>

            <div class="divide-y divide-slate-200/80">
                @forelse ($this->projects as $proj)
                    <div wire:key="{{ $proj->id }}" wire:click="selectProject('{{ $proj->id }}')" 
                         class="p-6 cursor-pointer hover:bg-slate-50/80 transition duration-150 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 {{ $this->selectedProjectId === $proj->id ? 'bg-blue-50/60 border-l-4 border-blue-600 shadow-2xs' : '' }}">
                        
                        <!-- Project Info -->
                        <div class="space-y-1.5 max-w-md">
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-slate-900 text-base tracking-tight hover:text-blue-600 transition">{{ $proj->name }}</h4>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase {{ $proj->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $proj->status === 'active' ? 'Aktiv' : $proj->status }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 font-medium leading-relaxed">{{ $proj->work_type }} <span class="text-slate-300">•</span> {{ $proj->city_street }}</p>
                            
                            <div class="pt-1 flex items-center gap-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">
                                    KW {{ $proj->start_week }} — KW {{ $proj->end_week }}
                                </span>
                            </div>
                        </div>

                        <!-- Cost & Progress -->
                        <div class="text-left sm:text-right space-y-2 w-full sm:w-auto min-w-[200px]">
                            @php 
                                $costSum = (float) $proj->actualCosts->sum('cost_amount');
                                $budgetTotal = (float) ($proj->budget?->total_with_buffer ?? 0);
                                $percent = $budgetTotal > 0 ? min(($costSum / $budgetTotal) * 100, 100) : 0;
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
                                    <div class="h-full rounded-full transition-all duration-500 {{ $percent > 90 ? 'bg-gradient-to-r from-amber-500 to-rose-600' : 'bg-gradient-to-r from-blue-600 to-indigo-600' }}" style="width: {{ max($percent, 2) }}%"></div>
                                </div>
                                <div class="flex justify-end items-center gap-3">
                                    <span class="text-[11px] font-bold text-slate-600">
                                        {{ number_format($percent, 1, ',', '.') }}% ausgeschöpft
                                    </span>
                                    <button wire:click.stop="confirmDeleteProject('{{ $proj->id }}')"
                                            title="Baustelle löschen"
                                            class="p-1 text-slate-400 hover:text-rose-600 rounded hover:bg-rose-50 transition cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400 space-y-2">
                        <div class="text-3xl">🔍</div>
                        <p class="text-xs font-semibold">Keine Baustellen für Ihre Filterkriterien gefunden.</p>
                    </div>
                @endforelse
            </div>
        </div>

    <!-- BAUSTELLEN DETAIL VIEW MODAL (DESKTOP MAXIMIZABLE & MOBILE FULLSCREEN) -->
    @if ($this->selectedProject)
        @php $proj = $this->selectedProject; @endphp
        <div x-data="{ isMaximized: false }" 
             x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflowX = 'hidden';"
             x-on:unmount.window="document.body.style.overflow = ''; document.documentElement.style.overflowX = '';"
             class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center z-50 transition-all duration-300 overflow-x-hidden p-0 sm:p-4">
            
            <div class="bg-white border-0 sm:border border-slate-200 shadow-2xl overflow-hidden flex flex-col transition-all duration-300 min-w-0 max-w-full w-screen h-screen max-w-none max-h-none rounded-none sm:w-full sm:max-w-5xl sm:max-h-[92vh] sm:rounded-3xl"
                 :class="isMaximized ? 'sm:w-screen sm:h-screen sm:max-w-none sm:max-h-none sm:rounded-none sm:border-0' : ''">
                
                <!-- Modal Header -->
                <div class="shrink-0 p-4 sm:p-6 bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 text-white relative overflow-hidden space-y-3">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <!-- Top Bar: Status + Action Buttons -->
                    <div class="flex items-center justify-between gap-2 relative z-10">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-extrabold uppercase bg-blue-500/30 text-blue-200 border border-blue-400/30">
                                {{ $proj->status === 'active' ? '🟢 Aktiv' : ($proj->status === 'draft' ? '📸 Bestandsaufnahme / Planung' : $proj->status) }}
                            </span>
                            <span class="text-[10px] sm:text-xs font-semibold text-slate-300">
                                KW {{ $proj->start_week }} — KW {{ $proj->end_week }}
                            </span>
                        </div>

                        <!-- Header Controls -->
                        <div class="flex items-center gap-2">
                            <!-- Quick Defect Button -->
                            <button wire:click="openCreateDefectModal('{{ $proj->id }}')"
                                    class="px-2.5 py-1 sm:py-1.5 bg-amber-500/20 hover:bg-amber-500/40 text-amber-200 hover:text-white font-bold text-xs rounded-xl transition border border-amber-400/30 flex items-center gap-1 cursor-pointer"
                                    title="Mangel für diese Baustelle aufnehmen">
                                <span>⚠️</span>
                                <span class="hidden sm:inline">+ Mangel erfassen</span>
                            </button>

                            <!-- Delete Button -->
                            <button wire:click="confirmDeleteProject('{{ $proj->id }}')"
                                    class="px-2.5 py-1 sm:py-1.5 bg-rose-500/20 hover:bg-rose-500/40 text-rose-200 hover:text-white font-bold text-xs rounded-xl transition border border-rose-400/30 flex items-center gap-1 cursor-pointer"
                                    title="Baustelle löschen">
                                <span>🗑️</span>
                                <span class="hidden sm:inline">Löschen</span>
                            </button>

                            <!-- Maximize Toggle -->
                            <button @click="isMaximized = !isMaximized" 
                                    class="hidden sm:flex px-2.5 py-1 sm:py-1.5 bg-white/10 hover:bg-white/20 text-white font-extrabold text-xs rounded-xl transition border border-white/20 items-center gap-1.5 cursor-pointer"
                                    :title="isMaximized ? 'Normalansicht wiederherstellen' : 'Vollbild maximieren'">
                                <span x-show="!isMaximized" class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
                                    <span class="text-[11px]">Vollbild</span>
                                </span>
                                <span x-show="isMaximized" class="flex items-center gap-1" x-cloak>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 3v3a2 2 0 01-2 2H3m18 0h-3a2 2 0 01-2-2V3m0 18v-3a2 2 0 012-2h3M3 16h3a2 2 0 012 2v3"/></svg>
                                    <span class="text-[11px]">Verkleinern</span>
                                </span>
                            </button>

                            <!-- Close Button -->
                            <button wire:click="closeProjectDetails" onclick="document.body.style.overflow = ''; document.documentElement.style.overflowX = '';" class="p-1.5 sm:p-2 text-slate-300 hover:text-white rounded-full bg-white/10 hover:bg-white/20 transition cursor-pointer" title="Schließen">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Title & Address -->
                    <div class="space-y-1 relative z-10">
                        <h2 class="text-lg sm:text-2xl font-black text-white tracking-tight leading-snug">{{ $proj->name }}</h2>
                        <p class="text-xs text-slate-300 flex flex-wrap items-center gap-2">
                            <span>🏗️ {{ $proj->work_type }}</span>
                            <span>•</span>
                            <span>📍 {{ $proj->contact_address ?: $proj->city_street }}</span>
                        </p>
                    </div>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">
                    
                    <!-- PROJEKT COMMAND CENTER (All-in-One 1-Klick Aktionsleiste) -->
                    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-4 sm:p-5 rounded-2xl shadow-md border border-indigo-500/20 space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <h4 class="font-extrabold text-xs uppercase tracking-wider text-white">⚡ Projekt Schnellaktionen — Alles aus 1 Hand</h4>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium">Belege & Berichte direkt für <strong>{{ $proj->name }}</strong> erstellen</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 pt-1">
                            <!-- 1. Rechnung erstellen -->
                            <a href="/rechnungen?project_id={{ $proj->id }}" 
                               class="p-2.5 bg-white/10 hover:bg-blue-600 text-white font-bold text-xs rounded-xl border border-white/10 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <span class="text-base group-hover:scale-110 transition-transform">🧾</span>
                                <span class="text-[11px] truncate">Rechnung</span>
                            </a>

                            <!-- 2. Angebot / LV -->
                            <a href="/planung?project_id={{ $proj->id }}" 
                               class="p-2.5 bg-white/10 hover:bg-blue-600 text-white font-bold text-xs rounded-xl border border-white/10 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <span class="text-base group-hover:scale-110 transition-transform">📄</span>
                                <span class="text-[11px] truncate">Angebot / LV</span>
                            </a>

                            <!-- 3. Bautagebuch -->
                            <a href="/bautagebuch?project_id={{ $proj->id }}" 
                               class="p-2.5 bg-white/10 hover:bg-blue-600 text-white font-bold text-xs rounded-xl border border-white/10 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <span class="text-base group-hover:scale-110 transition-transform">🎙️</span>
                                <span class="text-[11px] truncate">Bautagebuch</span>
                            </a>

                            <!-- 4. Mangel erfassen -->
                            <button wire:click="openCreateDefectModal('{{ $proj->id }}')" 
                                    class="p-2.5 bg-amber-500/20 hover:bg-amber-500 text-amber-200 hover:text-white font-bold text-xs rounded-xl border border-amber-400/30 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <span class="text-base group-hover:scale-110 transition-transform">⚠️</span>
                                <span class="text-[11px] truncate">Mangel</span>
                            </button>

                            <!-- 5. VOB/B Abnahmeprotokoll PDF -->
                            <a href="/projects/{{ $proj->id }}/abnahmeprotokoll-pdf" 
                               target="_blank"
                               class="p-2.5 bg-blue-500/20 hover:bg-blue-600 text-blue-200 hover:text-white font-bold text-xs rounded-xl border border-blue-400/30 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group shadow-xs">
                                <span class="text-base group-hover:scale-110 transition-transform">📋</span>
                                <span class="text-[11px] truncate">Abnahme-PDF</span>
                            </a>

                            <!-- 6. Einsatzplan -->
                            <a href="/einsatzplan?project_id={{ $proj->id }}" 
                               class="p-2.5 bg-white/10 hover:bg-blue-600 text-white font-bold text-xs rounded-xl border border-white/10 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <span class="text-base group-hover:scale-110 transition-transform">👷</span>
                                <span class="text-[11px] truncate">Einsatzplan</span>
                            </a>

                            <!-- 7. KI-Wochenbericht -->
                            <button wire:click="generateWeeklyReport" 
                                    class="p-2.5 bg-emerald-500/20 hover:bg-emerald-600 text-emerald-200 hover:text-white font-bold text-xs rounded-xl border border-emerald-400/30 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <span class="text-base group-hover:scale-110 transition-transform">📊</span>
                                <span class="text-[11px] truncate">Wochenbericht</span>
                            </button>
                        </div>
                    </div>

                    <!-- Grid: Budget & Costs Left / Photos Right -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left Column: Budget & Cost Breakdown -->
                        <div class="space-y-4">
                            <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                                📊 Budget- & Kosten-Kalkulation
                            </h4>

                            <div class="bg-slate-50 p-4 rounded-2xl space-y-4 border border-slate-200/80">
                                <!-- Material Budget -->
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-blue-900 flex items-center gap-1.5 font-bold">
                                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Materialbudget
                                        </span>
                                        <span class="text-slate-900 font-bold">{{ number_format($proj->budget?->material_budget, 2, ',', '.') }} €</span>
                                    </div>
                                    @php
                                        $matCosts = (float) $proj->actualCosts->where('type', 'material')->sum('cost_amount');
                                        $matBudget = (float) ($proj->budget?->material_budget ?? 0);
                                        $matPercent = $matBudget > 0 ? min(($matCosts / $matBudget) * 100, 100) : 0;
                                    @endphp
                                    <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden border border-slate-300/60 p-0.5">
                                        <div class="bg-blue-500 h-full rounded-full transition-all" style="width: {{ $matPercent }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-600 mt-1 font-semibold">
                                        <span>Verbucht: {{ number_format($matCosts, 2, ',', '.') }} €</span>
                                        <span>{{ number_format($matPercent, 1) }}%</span>
                                    </div>
                                </div>

                                <!-- Wage Budget -->
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-slate-900 flex items-center gap-1.5 font-bold">
                                            <span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span> Lohn- & Subunternehmer
                                        </span>
                                        <span class="text-slate-900 font-bold">{{ number_format($proj->budget?->wage_budget, 2, ',', '.') }} €</span>
                                    </div>
                                    @php
                                        $wageCosts = (float) $proj->actualCosts->whereIn('type', ['subcontractor', 'internal_wage'])->sum('cost_amount');
                                        $wageBudget = (float) ($proj->budget?->wage_budget ?? 0);
                                        $wagePercent = $wageBudget > 0 ? min(($wageCosts / $wageBudget) * 100, 100) : 0;
                                    @endphp
                                    <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden border border-slate-300/60 p-0.5">
                                        <div class="bg-slate-500 h-full rounded-full transition-all" style="width: {{ $wagePercent }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-600 mt-1 font-semibold">
                                        <span>Verbucht: {{ number_format($wageCosts, 2, ',', '.') }} €</span>
                                        <span>{{ number_format($wagePercent, 1) }}%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Cost Receipts -->
                            <div class="space-y-3 pt-2">
                                <div class="flex justify-between items-center">
                                    <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Ist-Kosten Belege</h4>
                                    <button wire:click="openAddCost" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition cursor-pointer">+ Beleg erfassen</button>
                                </div>
                                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                    @forelse ($proj->actualCosts as $cost)
                                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex justify-between items-center text-xs">
                                            <div>
                                                <p class="font-bold text-slate-900">{{ $cost->description }}</p>
                                                <p class="text-[10px] text-slate-500 font-medium">{{ date('d.m.Y', strtotime($cost->date)) }} • {{ ucfirst($cost->type) }}</p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if ($cost->receipt_path)
                                                    <a href="{{ asset('storage/' . $cost->receipt_path) }}" target="_blank" class="px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-lg text-[10px] font-bold transition flex items-center gap-1 shadow-2xs">
                                                        <span>📄 Beleg PDF</span>
                                                    </a>
                                                @endif
                                                <p class="font-bold text-rose-600">-{{ number_format($cost->cost_amount, 2, ',', '.') }} €</p>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500 italic">Keine Belege vorhanden.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Photos & Bestandsaufnahme -->
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                                    <span>📸 Baustellen-Fotos & Bestandsaufnahme</span>
                                    <span class="bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded-full font-bold">
                                        Fotos wählbar (JPG, PNG)
                                    </span>
                                </h4>
                            </div>

                            <!-- Photo Upload Box -->
                            <div class="bg-slate-50 border border-dashed border-slate-300 rounded-2xl p-3.5 space-y-2.5 relative">
                                <div class="space-y-3">
                                    <label class="w-full flex items-center justify-center gap-2 px-3 py-2.5 bg-white hover:bg-blue-50 text-blue-700 font-bold text-xs rounded-xl border border-blue-200 cursor-pointer transition shadow-2xs">
                                        <span>📷 Fotos auswählen</span>
                                        <input type="file" wire:model="uploadPhotoFiles" multiple class="hidden">
                                    </label>

                                    <!-- Custom Alpine.js Dropdown for Photo Category -->
                                    <div x-data="{ open: false }" class="relative">
                                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Foto-Kategorie:</label>
                                        <button type="button" 
                                                @click="open = !open" 
                                                @click.outside="open = false"
                                                class="w-full flex items-center justify-between gap-2 text-xs bg-white hover:bg-blue-50/60 border border-slate-200 hover:border-blue-300 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 shadow-2xs transition cursor-pointer">
                                            <span class="truncate">
                                                @if($photoCategory === 'bestandsaufnahme') 📋 Bestandsaufnahme
                                                @elseif($photoCategory === 'fortschritt') 📈 Baufortschritt
                                                @elseif($photoCategory === 'mangel') ⚠️ Mangel
                                                @elseif($photoCategory === 'abnahme') ✅ Abnahme
                                                @else 📋 Kategorie wählen @endif
                                            </span>
                                            <svg class="w-4 h-4 text-blue-600 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                        </button>

                                        <!-- Custom Dropdown Menu Card -->
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="transform opacity-100 scale-100"
                                             x-transition:leave-end="transform opacity-0 scale-95"
                                             class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden py-1 text-xs">
                                            
                                            <button type="button" 
                                                    @click="$wire.set('photoCategory', 'bestandsaufnahme'); open = false" 
                                                    class="w-full text-left px-3.5 py-2 font-medium flex items-center justify-between transition cursor-pointer"
                                                    :class="$wire.photoCategory === 'bestandsaufnahme' ? 'bg-blue-100 text-blue-900' : 'text-slate-700 hover:bg-blue-50'">
                                                <span>📋 Bestandsaufnahme</span>
                                                <span x-show="$wire.photoCategory === 'bestandsaufnahme'" class="text-blue-600 font-bold">✓</span>
                                            </button>

                                            <button type="button" 
                                                    @click="$wire.set('photoCategory', 'fortschritt'); open = false" 
                                                    class="w-full text-left px-3.5 py-2 font-medium flex items-center justify-between transition cursor-pointer"
                                                    :class="$wire.photoCategory === 'fortschritt' ? 'bg-blue-100 text-blue-900' : 'text-slate-700 hover:bg-blue-50'">
                                                <span>📈 Baufortschritt</span>
                                                <span x-show="$wire.photoCategory === 'fortschritt'" class="text-blue-600 font-bold">✓</span>
                                            </button>

                                            <button type="button" 
                                                    @click="$wire.set('photoCategory', 'mangel'); open = false" 
                                                    class="w-full text-left px-3.5 py-2 font-medium flex items-center justify-between transition cursor-pointer"
                                                    :class="$wire.photoCategory === 'mangel' ? 'bg-blue-100 text-blue-900' : 'text-slate-700 hover:bg-blue-50'">
                                                <span>⚠️ Mangel</span>
                                                <span x-show="$wire.photoCategory === 'mangel'" class="text-blue-600 font-bold">✓</span>
                                            </button>

                                            <button type="button" 
                                                    @click="$wire.set('photoCategory', 'abnahme'); open = false" 
                                                    class="w-full text-left px-3.5 py-2 font-medium flex items-center justify-between transition cursor-pointer"
                                                    :class="$wire.photoCategory === 'abnahme' ? 'bg-blue-100 text-blue-900' : 'text-slate-700 hover:bg-blue-50'">
                                                <span>✅ Abnahme</span>
                                                <span x-show="$wire.photoCategory === 'abnahme'" class="text-blue-600 font-bold">✓</span>
                                            </button>
                                        </div>
                                    </div>

                                    @if(!empty($uploadPhotoFiles) && is_array($uploadPhotoFiles) && count($uploadPhotoFiles) > 0)
                                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                                            <span class="text-xs font-bold text-slate-700">📸 {{ count($uploadPhotoFiles) }} Datei(en) ausgewählt</span>
                                            <button wire:click="uploadPhotos" wire:loading.attr="disabled" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition shrink-0 cursor-pointer">
                                                <span wire:loading.remove wire:target="uploadPhotos">Hochladen</span>
                                                <span wire:loading wire:target="uploadPhotos">Speichere...</span>
                                            </button>
                                        </div>
                                    @endif
                            </div>

                            <!-- Photos Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-80 overflow-y-auto pr-1">
                                @forelse ($proj->photos as $photo)
                                    <div class="relative group bg-slate-100 rounded-xl overflow-hidden border border-slate-200 aspect-square shadow-2xs">
                                        <a href="{{ asset('storage/' . $photo->photo_path) }}" target="_blank">
                                            <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="{{ $photo->caption }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                        </a>
                                        
                                        <!-- Category Badge -->
                                        <span class="absolute top-1.5 left-1.5 bg-slate-900/80 backdrop-blur-xs text-white text-[9px] font-bold px-1.5 py-0.5 rounded shadow-2xs">
                                            @if($photo->category === 'bestandsaufnahme') 📋 Bestand
                                            @elseif($photo->category === 'fortschritt') 📈 Fortschritt
                                            @elseif($photo->category === 'mangel') ⚠️ Mangel
                                            @else ✅ Abnahme
                                            @endif
                                        </span>

                                        <!-- Delete Button -->
                                        <button wire:click="deletePhoto('{{ $photo->id }}')" 
                                                wire:confirm="Soll dieses Foto wirklich gelöscht werden?"
                                                class="absolute top-1.5 right-1.5 bg-rose-600/90 hover:bg-rose-700 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition shadow-xs cursor-pointer">
                                            ✕
                                        </button>

                                        @if ($photo->caption)
                                            <div class="absolute bottom-0 inset-x-0 bg-slate-950/75 text-white text-[10px] p-1.5 truncate">
                                                {{ $photo->caption }}
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="col-span-full py-8 text-center text-xs text-slate-500 italic bg-slate-50/50 border border-slate-200/60 rounded-xl">
                                        Keine Baustellen-Fotos vorhanden. Laden Sie Fotos der Bestandsaufnahme hoch!
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Defects Section for this Baustelle -->
                    <div class="pt-4 border-t border-slate-200/80 space-y-3">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">⚠️ Mängel & Restarbeiten dieser Baustelle</h4>
                                @if($proj->defects->count() > 0)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300">
                                        {{ $proj->defects->where('status', '!=', 'abgenommen')->count() }} Offen / {{ $proj->defects->count() }} Gesamt
                                    </span>
                                @endif
                            </div>
                            <button wire:click="openCreateDefectModal('{{ $proj->id }}')" class="text-xs font-bold text-amber-600 hover:text-amber-800 transition cursor-pointer flex items-center gap-1">
                                <span>⚠️</span> + Mangel erfassen
                            </button>
                        </div>

                        <div class="space-y-2">
                            @forelse ($proj->defects as $defect)
                                <div class="bg-amber-50/50 p-3 rounded-xl border border-amber-200/80 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 text-xs">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-extrabold text-slate-900">{{ $defect->title }}</span>
                                            @if($defect->status === 'abgenommen')
                                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[9px] font-bold">Abgenommen</span>
                                            @elseif($defect->status === 'behoben')
                                                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-[9px] font-bold">Behoben</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 text-[9px] font-bold">Offen</span>
                                            @endif
                                            <span class="text-[10px] text-slate-500 font-medium">📍 {{ $defect->location ?: 'Baustelle' }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600">{{ $defect->description }}</p>
                                        @if($defect->assignedContact)
                                            <p class="text-[10px] text-blue-700 font-bold">🏗️ Subunternehmer: {{ $defect->assignedContact->display_name }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-[10px] text-slate-500 block">Frist: {{ $defect->deadline ? date('d.m.Y', strtotime($defect->deadline)) : 'Keine Frist' }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="p-3 bg-emerald-50/60 border border-emerald-200/80 rounded-xl text-xs text-emerald-900 font-medium flex justify-between items-center">
                                    <span>✅ Für diese Baustelle sind aktuell keine Mängel registriert.</span>
                                    <button wire:click="openCreateDefectModal('{{ $proj->id }}')" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] transition shadow-2xs cursor-pointer">
                                        + Mangel eintragen
                                    </button>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Offers & Contracts Section -->
                    <div class="pt-4 border-t border-slate-200/80 space-y-3">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Verknüpfte Angebote & LV-Positionen</h4>
                            <button wire:click="openParseOffer" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition cursor-pointer">+ LV Angebot hochladen (PDF)</button>
                        </div>
                        <div class="space-y-2">
                            @forelse ($proj->offers as $offer)
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex justify-between items-center text-xs">
                                    <div>
                                        <p class="font-bold text-slate-900">{{ $offer->offer_number ?: 'Angebot' }} • {{ $offer->title }}</p>
                                        <p class="text-[10px] text-slate-500 font-medium">{{ $offer->sections->count() }} Abschnitte • {{ date('d.m.Y', strtotime($offer->created_at)) }}</p>
                                    </div>
                                    <p class="font-bold text-slate-900">{{ number_format($offer->total_net, 2, ',', '.') }} € netto</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 italic">Keine verknüpften Angebote vorhanden.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
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

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">📄 Eingangsrechnung / Beleg-Datei (PDF oder Bild)</label>
                        <div class="bg-slate-50 border border-dashed border-slate-300 rounded-xl p-3.5 text-center">
                            <label class="cursor-pointer flex flex-col items-center justify-center gap-1 text-xs text-blue-700 font-bold hover:text-blue-900 transition">
                                <span>📎 Rechnung / Beleg hochladen (PDF, JPG, PNG)</span>
                                <input type="file" wire:model="costReceiptFile" accept=".pdf,image/*" class="hidden">
                            </label>
                            @if ($costReceiptFile)
                                <p class="text-[11px] font-semibold text-emerald-600 mt-1.5 flex items-center justify-center gap-1">
                                    <span>✓ Ausgewählt:</span>
                                    <span>{{ $costReceiptFile->getClientOriginalName() }}</span>
                                </p>
                            @endif
                        </div>
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
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📊</span>
                        <h3 class="text-base font-extrabold text-white">KI-Wochenbericht (Vergangene 7 Tage)</h3>
                    </div>
                    <button wire:click="$set('showWeeklyReportModal', false)" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto">
                    @php
                        $cleanReport = preg_replace('/\*\*|\*/', '', $weeklyReportText ?? '');
                    @endphp
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-xs font-sans text-slate-800 leading-relaxed max-h-96 overflow-y-auto whitespace-pre-wrap selection:bg-blue-100 font-medium">{{ $cleanReport }}</div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showWeeklyReportModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Schließen</button>
                        @if ($cleanReport)
                            <button type="button" onclick="navigator.clipboard.writeText(`{{ addslashes($cleanReport) }}`); alert('📋 Wochenbericht ohne Sonderzeichen in Zwischenablage kopiert!');" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all cursor-pointer">
                                📋 Bericht kopieren
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 4. Delete Project Confirmation Modal -->
    @if($showDeleteProjectModal)
        <div class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm flex items-center justify-center z-[60] p-4 animate-in fade-in duration-200">
            <div class="bg-white border border-rose-200 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden transform transition-all">
                <!-- Header with Red Banner -->
                <div class="p-6 bg-gradient-to-r from-rose-600 to-red-700 text-white relative overflow-hidden space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-white/20 text-white border border-white/30">
                            🚨 Sicherheitsabfrage
                        </span>
                        <button wire:click="$set('showDeleteProjectModal', false)" class="text-white/80 hover:text-white text-lg cursor-pointer">✕</button>
                    </div>
                    <h3 class="text-xl font-extrabold text-white tracking-tight">Baustelle unwiderruflich löschen?</h3>
                    <p class="text-xs text-rose-100 font-medium">Sind Sie sicher, dass Sie diese Baustelle aus dem System entfernen möchten?</p>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <div class="bg-rose-50 border border-rose-200/80 rounded-2xl p-4 space-y-2 text-xs">
                        <p class="font-bold text-rose-950 text-sm flex items-center gap-1.5">
                            <span>🏗️</span>
                            <span>{{ $projectToDeleteName }}</span>
                        </p>
                        <p class="text-rose-800 leading-relaxed">
                            Mit dieser Aktion werden alle verknüpften **Budgets, Ist-Kosten Belege, Bestandsaufnahme-Fotos** und **Angebote** dauerhaft gelöscht.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" 
                                wire:click="$set('showDeleteProjectModal', false)" 
                                class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="button" 
                                wire:click="deleteProjectConfirmed" 
                                wire:loading.attr="disabled"
                                class="px-5 py-2.5 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 text-white rounded-xl text-xs font-extrabold shadow-md shadow-rose-500/20 transition flex items-center gap-2 cursor-pointer">
                            <span wire:loading.remove wire:target="deleteProjectConfirmed">🗑️ Ja, Baustelle löschen</span>
                            <span wire:loading wire:target="deleteProjectConfirmed">Lösche Baustelle...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- GLOBAL KI LOADING OVERLAY FOR WOCHENBERICHT & PARSE -->
    <div wire:loading wire:target="generateWeeklyReport, parseOfferDirectly" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center z-50 p-4">
        <div class="bg-slate-900 border border-blue-500/30 rounded-3xl p-8 max-w-md w-full shadow-2xl text-center space-y-5">
            <div class="relative w-20 h-20 mx-auto flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-blue-500/20 border-t-blue-500 animate-spin"></div>
                <div class="w-14 h-14 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-full flex items-center justify-center shadow-lg shadow-blue-500/40">
                    <span class="text-2xl animate-bounce">📊</span>
                </div>
            </div>
            <div class="space-y-2">
                <h3 class="text-lg font-extrabold text-white">KI-Wochenbericht wird generiert...</h3>
                <p class="text-xs text-blue-200/80">OpenAI wertet alle Bautagebuch-Einträge der letzten 7 Tage aus. Bitte einen kurzen Moment Geduld.</p>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 via-indigo-500 to-blue-500 h-full w-3/4 animate-pulse"></div>
            </div>
        </div>
    </div>
    <!-- Create Defect Modal (From Baustellen-Detail) -->
    @if ($showCreateDefectModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚠️</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Mangel für Baustelle erfassen</h3>
                            <p class="text-[11px] text-amber-300 font-medium">{{ $this->selectedProject?->name }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showCreateDefectModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <form wire:submit="saveDefectFromProjectDetail" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Mangel-Bezeichnung / Betreff *</label>
                        <input wire:model="defectTitle" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="z. B. Hohllage Fliesen Flur 2. OG" required>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Genaue Lage / Ort</label>
                            <input wire:model="defectLocation" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="z. B. Dachgeschoss Süd">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Priorität</label>
                            <select wire:model="defectPriority" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white">
                                <option value="niedrig">Niedrig</option>
                                <option value="mittel">Mittel</option>
                                <option value="hoch">Hoch (Kritisch)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subunternehmer / Gewerk</label>
                            <select wire:model="defectAssignedContactId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white">
                                <option value="">-- Keinem Subunternehmer zugewiesen --</option>
                                @foreach ($this->subcontractors as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->display_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Beseitigungsfrist bis</label>
                            <input wire:model="defectDeadline" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Mängelbeschreibung & Anweisung *</label>
                        <textarea wire:model="defectDescription" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white" placeholder="Detaillierte Beschreibung der Abweichung und geforderte Nachbesserung..." required></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showCreateDefectModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-500/20">
                            ⚠️ Mangel speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
