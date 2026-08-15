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

    // Quick Invoice Modal State & Logic
    public bool $showQuickInvoiceModal = false;
    public string $quickInvoiceNumber = '';
    public string $quickInvoiceDate = '';
    public float $quickInvoiceAmount = 0.0;
    public string $quickInvoiceDescription = '';

    public function openQuickInvoiceModalForProject(string $id)
    {
        $this->selectedProjectId = $id;
        $this->openQuickInvoiceModal();
    }

    public function openQuickOfferModalForProject(string $id)
    {
        $this->selectedProjectId = $id;
        $this->openQuickOfferModal();
    }

    public function openQuickDailyLogModalForProject(string $id)
    {
        $this->selectedProjectId = $id;
        $this->openQuickDailyLogModal();
    }

    public function openQuickInvoiceModal()
    {
        $this->quickInvoiceNumber = 'RE-' . date('Y') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $this->quickInvoiceDate = date('Y-m-d');
        $this->quickInvoiceAmount = (float) ($this->selectedProject?->budget?->total_with_buffer ?? 1000.0);
        $this->quickInvoiceDescription = 'Rechnung für Baustellenarbeiten ' . ($this->selectedProject?->name ?: '');
        $this->showQuickInvoiceModal = true;
    }

    public function saveQuickInvoice()
    {
        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'quickInvoiceNumber' => 'required|string',
            'quickInvoiceAmount' => 'required|numeric|min:0.01',
        ]);

        $subtotal = round($this->quickInvoiceAmount / 1.19, 2);
        $vat = round($this->quickInvoiceAmount - $subtotal, 2);

        $inv = \App\Models\Invoice::create([
            'project_id' => $this->selectedProjectId,
            'invoice_number' => $this->quickInvoiceNumber,
            'invoice_date' => $this->quickInvoiceDate,
            'due_date' => date('Y-m-d', strtotime('+14 days', strtotime($this->quickInvoiceDate))),
            'total_amount' => $this->quickInvoiceAmount,
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'status' => 'draft',
            'notes' => $this->quickInvoiceDescription,
        ]);

        \App\Models\InvoiceItem::create([
            'invoice_id' => $inv->id,
            'description' => $this->quickInvoiceDescription ?: 'Bau- & Sanierungsarbeiten laut Vereinbarung',
            'quantity' => 1,
            'unit' => 'Pauschal',
            'unit_price' => $subtotal,
            'total_price' => $subtotal,
        ]);

        $this->showQuickInvoiceModal = false;
        $this->dispatch('notify', '🧾 Rechnung ' . $this->quickInvoiceNumber . ' (' . number_format($this->quickInvoiceAmount, 2, ',', '.') . ' €) erfolgreich direkt im Projekt erstellt!');
    }

    // Quick Offer Modal State & Logic
    public bool $showQuickOfferModal = false;
    public string $quickOfferNumber = '';
    public string $quickOfferDate = '';
    public string $quickOfferTitle = '';
    public float $quickOfferAmount = 0.0;

    public function openQuickOfferModal()
    {
        $this->quickOfferNumber = 'ANG-' . date('Y') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $this->quickOfferDate = date('Y-m-d');
        $this->quickOfferTitle = 'Angebot & LV: ' . ($this->selectedProject?->work_type ?: 'Bauleistungen');
        $this->quickOfferAmount = (float) ($this->selectedProject?->budget?->total_with_buffer ?? 2500.0);
        $this->showQuickOfferModal = true;
    }

    public function saveQuickOffer()
    {
        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'quickOfferNumber' => 'required|string',
            'quickOfferAmount' => 'required|numeric|min:0.01',
        ]);

        $subtotal = round($this->quickOfferAmount / 1.19, 2);
        $vat = round($this->quickOfferAmount - $subtotal, 2);

        $offer = \App\Models\Offer::create([
            'project_id' => $this->selectedProjectId,
            'offer_number' => $this->quickOfferNumber,
            'offer_date' => $this->quickOfferDate,
            'title' => $this->quickOfferTitle,
            'total_amount' => $this->quickOfferAmount,
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'status' => 'draft',
        ]);

        $sec = \App\Models\OfferSection::create([
            'offer_id' => $offer->id,
            'title' => 'Hauptgewerk / Leistungen',
            'sort_order' => 1,
        ]);

        \App\Models\OfferItem::create([
            'offer_section_id' => $sec->id,
            'item_number' => '1.1',
            'description' => $this->quickOfferTitle,
            'quantity' => 1,
            'unit' => 'Pauschal',
            'unit_price' => $subtotal,
            'total_price' => $subtotal,
        ]);

        $this->showQuickOfferModal = false;
        $this->dispatch('notify', '📄 Angebot ' . $this->quickOfferNumber . ' (' . number_format($this->quickOfferAmount, 2, ',', '.') . ' €) erfolgreich direkt im Projekt erstellt!');
    }

    // Quick Daily Log Modal State & Logic
    public bool $showQuickDailyLogModal = false;
    public string $quickLogDate = '';
    public string $quickLogWeather = 'Sonnig';
    public int $quickLogWorkersCount = 2;
    public string $quickLogContactId = '';
    public string $quickLogWorkPerformed = '';
    public string $quickLogSpecialOccurrences = '';

    public function openQuickDailyLogModal()
    {
        $this->quickLogDate = date('Y-m-d');
        $this->quickLogWeather = 'Sonnig';
        $this->quickLogWorkersCount = 2;
        $this->quickLogContactId = '';
        $this->quickLogWorkPerformed = '';
        $this->quickLogSpecialOccurrences = '';
        $this->showQuickDailyLogModal = true;
    }

    public function saveQuickDailyLog()
    {
        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'quickLogDate' => 'required|date',
            'quickLogWorkPerformed' => 'required|string|min:3',
        ]);

        \App\Models\DailyLog::create([
            'project_id' => $this->selectedProjectId,
            'contact_id' => $this->quickLogContactId ?: null,
            'date' => $this->quickLogDate,
            'weather' => $this->quickLogWeather,
            'temperature' => '20°C',
            'workers_count' => $this->quickLogWorkersCount,
            'work_performed' => $this->quickLogWorkPerformed,
            'special_occurrences' => $this->quickLogSpecialOccurrences ?: null,
        ]);

        $this->showQuickDailyLogModal = false;
        $this->dispatch('notify', '🎙️ Bautagebuch-Eintrag für Baustelle erfolgreich direkt erstellt!');
    }

    // Quick Schedule Modal State & Logic
    public bool $showQuickScheduleModal = false;
    public string $quickScheduleWorkerType = 'mitarbeiter';
    public string $quickScheduleContactId = '';
    public string $quickScheduleWorkerName = '';
    public string $quickScheduleDate = '';
    public string $quickScheduleShiftType = 'ganztags';
    public string $quickScheduleNotes = '';

    public function openQuickScheduleModal()
    {
        $this->quickScheduleWorkerType = 'mitarbeiter';
        $this->quickScheduleContactId = '';
        $this->quickScheduleWorkerName = '';
        $this->quickScheduleDate = date('Y-m-d');
        $this->quickScheduleShiftType = 'ganztags';
        $this->quickScheduleNotes = '';
        $this->showQuickScheduleModal = true;
    }

    public function saveQuickSchedule()
    {
        $this->validate([
            'selectedProjectId' => 'required|exists:projects,id',
            'quickScheduleDate' => 'required|date',
        ]);

        \App\Models\WorkerSchedule::create([
            'project_id' => $this->selectedProjectId,
            'contact_id' => $this->quickScheduleContactId ?: null,
            'worker_name' => $this->quickScheduleWorkerName ?: 'Handwerker / Subunternehmer',
            'worker_type' => $this->quickScheduleWorkerType,
            'date' => $this->quickScheduleDate,
            'shift_type' => $this->quickScheduleShiftType,
            'notes' => $this->quickScheduleNotes ?: null,
        ]);

        $this->showQuickScheduleModal = false;
        $this->dispatch('notify', '👷 Einsatzplan-Eintrag für Baustelle erfolgreich direkt erstellt!');
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
    <div class="bg-[#091224] text-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative overflow-hidden">
        <!-- Hairline & Blueprint Grid Overlay -->
        <div class="arch-hairline-overlay"></div>
        <div class="absolute -right-10 -bottom-10 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-2 relative z-10">
            <div class="arch-section-label">
                <span>BAULEITER-COCKPIT & PROJEKTSTEUERUNG</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <span>BT Bautechnik Steuerzentrale</span>
            </h2>
            <p class="text-xs text-slate-300 font-medium">Echtzeit-Kostenkontrolle, VOB/B § 2 Nachträge & Baustellen-Pipeline</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 relative z-10">
            <button wire:click="openCreateProject" 
                    class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-xl shadow-lg shadow-amber-500/20 transition-all flex items-center gap-1.5 cursor-pointer btn-press">
                <span>+ Neue Baustelle anlegen</span>
            </button>

            <a href="{{ route('daily-logs') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 btn-press">
                <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                </svg>
                <span>Bautagebuch</span>
            </a>

            <a href="{{ route('defects') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 btn-press">
                <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Mängel</span>
            </a>

            <a href="{{ route('invoices') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 btn-press">
                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Rechnungen</span>
            </a>

            <a href="{{ route('knowledge-base') }}" wire:navigate 
               class="px-3.5 py-2.5 bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 btn-press">
                <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span>Wissen</span>
            </a>
        </div>
    </div>

    <!-- Header Summary Stats (Architectural KPI Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Stat Card 1: Aktive Baustellen -->
        <div class="arch-card p-5 sm:p-6 space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Aktive Baustellen</p>
                <span class="w-10 h-10 rounded-xl bg-slate-950 text-white flex items-center justify-center shadow-xs shrink-0">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </span>
            </div>
            <p class="text-2xl xl:text-3xl font-black text-slate-950 tracking-tight tabular-nums">{{ $this->stats['active_projects'] }}</p>
            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> In laufender Betreuung
                </span>
                <span class="text-[10px] text-slate-600 font-bold bg-slate-100 px-2 py-0.5 rounded-md">100% aktiv</span>
            </div>
        </div>

        <!-- Stat Card 2: Gesamtes Budget -->
        <div class="arch-card p-5 sm:p-6 space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Gesamtes Budget (Soll)</p>
                <span class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-600 flex items-center justify-center shadow-xs shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl xl:text-3xl font-black text-slate-950 tracking-tight tabular-nums truncate">{{ number_format($this->stats['total_budget'], 2, ',', '.') }}</span>
                <span class="text-base font-bold text-slate-500">€</span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <span class="text-xs font-semibold text-slate-600">Kalkuliert inkl. Puffer</span>
                <span class="text-[10px] text-amber-800 font-bold bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">15% Puffer</span>
            </div>
        </div>

        <!-- Stat Card 3: Gesamte Ist-Kosten -->
        <div class="arch-card p-5 sm:p-6 space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Gesamte Ist-Kosten</p>
                <span class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 flex items-center justify-center shadow-xs shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl xl:text-3xl font-black text-rose-600 tracking-tight tabular-nums truncate">{{ number_format($this->stats['total_costs'], 2, ',', '.') }}</span>
                <span class="text-base font-bold text-rose-500">€</span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <span class="text-xs font-semibold text-slate-600">Material & Subunternehmer</span>
                @php
                    $consumption = $this->stats['total_budget'] > 0 ? ($this->stats['total_costs'] / $this->stats['total_budget']) * 100 : 0;
                @endphp
                <span class="text-[10px] font-bold {{ $consumption > 90 ? 'text-rose-700 bg-rose-50 border border-rose-200' : 'text-slate-700 bg-slate-100' }} px-2 py-0.5 rounded-md">
                    {{ number_format($consumption, 0) }}% verbraucht
                </span>
            </div>
        </div>

        <!-- Stat Card 4: Verbleibende Marge -->
        <div class="arch-card p-5 sm:p-6 space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-[10.5px] font-bold uppercase tracking-wider text-slate-500">Verbleibende Marge</p>
                <span class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 flex items-center justify-center shadow-xs shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl xl:text-3xl font-black text-emerald-600 tracking-tight tabular-nums truncate">{{ number_format($this->stats['margin'], 1, ',', '.') }}</span>
                <span class="text-base font-bold text-emerald-500">%</span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 gap-1.5 flex-wrap">
                <span class="text-xs font-semibold text-slate-600">
                    Rest: <strong class="tabular-nums text-slate-950">{{ number_format($this->stats['remaining_budget'], 0, ',', '.') }} €</strong>
                </span>
                <span class="text-[10px] font-black text-emerald-800 bg-emerald-100/80 px-2 py-0.5 rounded-md border border-emerald-200">
                    Im Plan
                </span>
            </div>
        </div>
    </div>

    <!-- Main Workspace Split Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Projects Directory List (Full Width) -->
        <div class="lg:col-span-12 arch-card shadow-sm overflow-hidden space-y-0">
            <!-- Header & Search/Filter Bar -->
            <div class="p-6 border-b border-slate-200 bg-slate-50/70 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="text-base font-black text-slate-950 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                            Baustellenübersicht & Pipeline
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Echtzeit-Projektfortschritt & Kostenkontrolle nach KW</p>
                    </div>
                    <button wire:click="openCreateProject" class="px-4 py-2 bg-slate-950 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-md border border-slate-800 transition cursor-pointer btn-press">
                        + Neue Baustelle
                    </button>
                </div>

                <!-- Live Search & Filter Bar -->
                <div class="flex flex-col sm:flex-row gap-3 items-center justify-between pt-1">
                    <div class="relative w-full sm:w-80">
                        <input wire:model.live.debounce.250ms="searchQuery" type="text" 
                               class="w-full bg-white border border-slate-300 text-slate-950 rounded-xl pl-9 pr-4 py-2 text-xs placeholder-slate-400 focus:border-slate-950 focus:ring-2 focus:ring-amber-500/20 focus:outline-none transition shadow-2xs"
                               placeholder="Baustelle, Ort oder Gewerk suchen...">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5 bg-slate-200/70 p-1 rounded-xl w-full sm:w-auto overflow-x-auto">
                        <button wire:click="$set('statusFilter', 'all')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $statusFilter === 'all' ? 'bg-white text-slate-950 shadow-xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                            Alle ({{ \App\Models\Project::count() }})
                        </button>
                        <button wire:click="$set('statusFilter', 'active')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $statusFilter === 'active' ? 'bg-slate-950 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                            Aktiv
                        </button>
                        <button wire:click="$set('statusFilter', 'completed')" 
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $statusFilter === 'completed' ? 'bg-emerald-700 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                            Beendet
                        </button>
                    </div>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($this->projects as $proj)
                    <div wire:key="{{ $proj->id }}" wire:click="selectProject('{{ $proj->id }}')" 
                         class="p-5 sm:p-6 cursor-pointer hover:bg-slate-50/90 transition duration-200 flex flex-col gap-4 group relative overflow-hidden {{ $this->selectedProjectId === $proj->id ? 'bg-amber-50/40 border-l-4 border-l-amber-500 shadow-xs' : '' }}">
                        
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <!-- Left: Status & Title & Metadata -->
                            <div class="space-y-2 max-w-xl">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider {{ $proj->status === 'active' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300/80 shadow-2xs' : 'bg-slate-100 text-slate-700' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $proj->status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                        {{ $proj->status === 'active' ? 'Aktiv' : $proj->status }}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100/80 text-amber-900 border border-amber-300/60 shadow-2xs">
                                        KW {{ $proj->start_week }} — KW {{ $proj->end_week }}
                                    </span>
                                </div>

                                <div>
                                    <h4 class="font-black text-slate-950 text-base sm:text-lg tracking-tight group-hover:text-amber-700 transition flex items-center gap-2">
                                        <span>{{ $proj->name }}</span>
                                    </h4>
                                    <p class="text-xs text-slate-600 font-medium leading-relaxed flex items-center gap-2 mt-0.5">
                                        <span>{{ $proj->work_type }}</span>
                                        <span class="text-slate-300">•</span>
                                        <span>{{ $proj->city_street }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Right: Budget Gauge & Metrics -->
                            <div class="text-left md:text-right space-y-2 shrink-0 min-w-[220px]">
                                @php 
                                    $costSum = (float) $proj->actualCosts->sum('cost_amount');
                                    $budgetTotal = (float) ($proj->budget?->total_with_buffer ?? 0);
                                    $percent = $budgetTotal > 0 ? min(($costSum / $budgetTotal) * 100, 100) : 0;
                                @endphp

                                <div class="flex justify-between md:justify-end items-center gap-2">
                                    <span class="text-[10px] text-slate-500 font-black uppercase tracking-wider">Kosten / Budget:</span>
                                    <span class="text-xs sm:text-sm font-black text-slate-950">
                                        <span class="{{ $costSum > $budgetTotal ? 'text-rose-600 font-black' : 'text-slate-900' }}">{{ number_format($costSum, 2, ',', '.') }} €</span> 
                                        <span class="text-slate-300">/</span> 
                                        <span class="text-slate-600">{{ number_format($budgetTotal, 2, ',', '.') }} €</span>
                                    </span>
                                </div>

                                <div class="space-y-1">
                                    <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden border border-slate-300/50 p-0.5 shadow-inner">
                                        <div class="h-full rounded-full transition-all duration-500 {{ $percent > 90 ? 'bg-rose-500' : 'bg-slate-950' }}" style="width: {{ max($percent, 3) }}%"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-bold text-slate-500">
                                        <span>Fortschritt</span>
                                        <span class="{{ $percent > 90 ? 'text-rose-600 font-black' : 'text-slate-700' }}">{{ number_format($percent, 1, ',', '.') }}% ausgeschöpft</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer: Direct 1-Click Action Bar -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap" @click.stop>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button wire:click="openQuickInvoiceModalForProject('{{ $proj->id }}')" 
                                        class="px-2.5 py-1 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-950 font-bold text-[11px] rounded-lg border border-slate-200 shadow-2xs transition flex items-center gap-1 cursor-pointer"
                                        title="Schnell-Rechnung für diese Baustelle erstellen">
                                    <span>Rechnung</span>
                                </button>

                                <button wire:click="openQuickOfferModalForProject('{{ $proj->id }}')" 
                                        class="px-2.5 py-1 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-950 font-bold text-[11px] rounded-lg border border-slate-200 shadow-2xs transition flex items-center gap-1 cursor-pointer"
                                        title="Angebot / LV für diese Baustelle erstellen">
                                    <span>Angebot</span>
                                </button>

                                <button wire:click="openQuickDailyLogModalForProject('{{ $proj->id }}')" 
                                        class="px-2.5 py-1 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-950 font-bold text-[11px] rounded-lg border border-slate-200 shadow-2xs transition flex items-center gap-1 cursor-pointer"
                                        title="Tagesbericht für diese Baustelle verfassen">
                                    <span>Tagebuch</span>
                                </button>

                                <button wire:click="openCreateDefectModal('{{ $proj->id }}')" 
                                        class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-[11px] rounded-lg border border-amber-200 shadow-2xs transition flex items-center gap-1 cursor-pointer"
                                        title="Mangel erfassen">
                                    <span>Mangel</span>
                                </button>

                                <a href="/projects/{{ $proj->id }}/abnahmeprotokoll-pdf" target="_blank"
                                   class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[11px] rounded-lg border border-slate-200 transition flex items-center gap-1"
                                   title="VOB/B Abnahmeprotokoll PDF herunterladen">
                                    <span>Abnahme-PDF</span>
                                </a>
                            </div>

                            <div class="flex items-center gap-2">
                                <button wire:click="selectProject('{{ $proj->id }}')" 
                                        class="px-3 py-1 bg-slate-950 hover:bg-slate-800 text-white font-bold text-[11px] rounded-lg transition shadow-2xs cursor-pointer flex items-center gap-1">
                                    <span>Öffnen →</span>
                                </button>
                                <button wire:click.stop="confirmDeleteProject('{{ $proj->id }}')" 
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded hover:bg-rose-50 transition cursor-pointer"
                                        title="Baustelle löschen">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400 space-y-2">
                        <p class="text-xs font-semibold">Keine Baustellen für Ihre Filterkriterien gefunden.</p>
                    </div>
                @endforelse
            </div>
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
                <div class="shrink-0 p-4 sm:p-6 bg-[#091224] text-white relative overflow-hidden space-y-3 border-b border-slate-800">
                    <div class="arch-hairline-overlay"></div>
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <!-- Top Bar: Status + Action Buttons -->
                    <div class="flex items-center justify-between gap-2 relative z-10">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-black uppercase {{ $proj->status === 'active' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800 text-slate-300 border border-slate-700' }}">
                                {{ $proj->status === 'active' ? '● Aktiv' : ($proj->status === 'draft' ? 'Planung / Aufnahme' : $proj->status) }}
                            </span>
                            <span class="text-[10px] sm:text-xs font-bold text-slate-300 bg-slate-900/90 px-2.5 py-0.5 rounded-full border border-slate-700">
                                KW {{ $proj->start_week }} — KW {{ $proj->end_week }}
                            </span>
                        </div>

                        <!-- Header Controls -->
                        <div class="flex items-center gap-2">
                            <!-- Quick Defect Button -->
                            <button wire:click="openCreateDefectModal('{{ $proj->id }}')"
                                    class="px-2.5 py-1 sm:py-1.5 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 hover:text-white font-bold text-xs rounded-xl transition border border-amber-500/30 flex items-center gap-1 cursor-pointer"
                                    title="Mangel für diese Baustelle aufnehmen">
                                <span class="hidden sm:inline">+ Mangel erfassen</span>
                            </button>

                            <!-- Delete Button -->
                            <button wire:click="confirmDeleteProject('{{ $proj->id }}')"
                                    class="px-2.5 py-1 sm:py-1.5 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 hover:text-white font-bold text-xs rounded-xl transition border border-rose-500/30 flex items-center gap-1 cursor-pointer"
                                    title="Baustelle löschen">
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
                            <span>{{ $proj->work_type }}</span>
                            <span>•</span>
                            <span>{{ $proj->contact_address ?: $proj->city_street }}</span>
                        </p>
                    </div>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">
                    
                    <!-- PROJEKT COMMAND CENTER (All-in-One 1-Klick Aktionsleiste) -->
                    <div class="bg-[#091224] text-white p-4 sm:p-5 rounded-2xl shadow-md border border-slate-800 space-y-3 relative overflow-hidden">
                        <div class="arch-hairline-overlay"></div>
                        <div class="flex items-center justify-between flex-wrap gap-2 relative z-10">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                                <h4 class="font-black text-xs uppercase tracking-wider text-white">Projekt Schnellaktionen</h4>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium">Belege & Berichte direkt für <strong>{{ $proj->name }}</strong> erstellen</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 pt-1 relative z-10">
                            <!-- 1. Rechnung erstellen -->
                            <button wire:click="openQuickInvoiceModal" 
                                    class="p-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl border border-slate-700 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <svg class="w-4 h-4 text-emerald-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-[11px] truncate">Rechnung</span>
                            </button>

                            <!-- 2. Angebot / LV -->
                            <button wire:click="openQuickOfferModal" 
                                    class="p-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl border border-slate-700 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <svg class="w-4 h-4 text-amber-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-[11px] truncate">Angebot / LV</span>
                            </button>

                            <!-- 3. Bautagebuch -->
                            <button wire:click="openQuickDailyLogModal" 
                                    class="p-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl border border-slate-700 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <svg class="w-4 h-4 text-cyan-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                                </svg>
                                <span class="text-[11px] truncate">Bautagebuch</span>
                            </button>

                            <!-- 4. Mangel erfassen -->
                            <button wire:click="openCreateDefectModal('{{ $proj->id }}')" 
                                    class="p-2.5 bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 hover:text-white font-bold text-xs rounded-xl border border-amber-500/30 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <svg class="w-4 h-4 text-amber-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="text-[11px] truncate">Mangel</span>
                            </button>

                            <!-- 5. VOB/B Abnahmeprotokoll PDF -->
                            <a href="/projects/{{ $proj->id }}/abnahmeprotokoll-pdf" 
                               target="_blank"
                               class="p-2.5 bg-slate-900 hover:bg-slate-800 text-slate-200 hover:text-white font-bold text-xs rounded-xl border border-slate-700 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group shadow-xs">
                                <svg class="w-4 h-4 text-blue-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-[11px] truncate">Abnahme-PDF</span>
                            </a>

                            <!-- 6. Einsatzplan -->
                            <button wire:click="openQuickScheduleModal" 
                                    class="p-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl border border-slate-700 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <svg class="w-4 h-4 text-indigo-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[11px] truncate">Einsatzplan</span>
                            </button>

                            <!-- 7. KI-Wochenbericht -->
                            <button wire:click="generateWeeklyReport" 
                                    class="p-2.5 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 hover:text-white font-bold text-xs rounded-xl border border-emerald-500/30 transition flex flex-col items-center justify-center gap-1 cursor-pointer text-center group">
                                <svg class="w-4 h-4 text-emerald-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
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

    <!-- Quick Invoice Modal (In-Page) -->
    @if ($showQuickInvoiceModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🧾</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Neue Rechnung direkt im Projekt erstellen</h3>
                            <p class="text-[11px] text-blue-300 font-medium">{{ $this->selectedProject?->name }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showQuickInvoiceModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <form wire:submit="saveQuickInvoice" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Rechnungsnummer *</label>
                            <input wire:model="quickInvoiceNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:bg-white" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Rechnungsdatum *</label>
                            <input wire:model="quickInvoiceDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Rechnungsbetrag brutto (€) *</label>
                        <div class="relative">
                            <input wire:model="quickInvoiceAmount" type="number" step="0.01" min="0.01" class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-3.5 pr-8 py-2.5 text-sm font-extrabold text-slate-900 focus:border-blue-600 focus:bg-white" required>
                            <span class="absolute right-3 top-2.5 text-sm font-bold text-slate-400">€</span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Enthält 19% MwSt (Netto: {{ number_format(round($quickInvoiceAmount / 1.19, 2), 2, ',', '.') }} €)</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bezeichnung / Betreff</label>
                        <textarea wire:model="quickInvoiceDescription" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" placeholder="Beschreibung der Bauleistungen..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showQuickInvoiceModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20">
                            🧾 Rechnung jetzt erstellen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Quick Offer Modal (In-Page) -->
    @if ($showQuickOfferModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📄</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Neues Angebot direkt im Projekt erstellen</h3>
                            <p class="text-[11px] text-blue-300 font-medium">{{ $this->selectedProject?->name }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showQuickOfferModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <form wire:submit="saveQuickOffer" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Angebotstitel / Gewerk *</label>
                        <input wire:model="quickOfferTitle" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:bg-white" required>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Angebotsnummer *</label>
                            <input wire:model="quickOfferNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:bg-white" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Datum *</label>
                            <input wire:model="quickOfferDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Angebotssumme brutto (€) *</label>
                        <div class="relative">
                            <input wire:model="quickOfferAmount" type="number" step="0.01" min="0.01" class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-3.5 pr-8 py-2.5 text-sm font-extrabold text-slate-900 focus:border-blue-600 focus:bg-white" required>
                            <span class="absolute right-3 top-2.5 text-sm font-bold text-slate-400">€</span>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showQuickOfferModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20">
                            📄 Angebot jetzt erstellen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Quick Daily Log Modal (In-Page) -->
    @if ($showQuickDailyLogModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎙️</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Tagesbericht direkt im Projekt verfassen</h3>
                            <p class="text-[11px] text-blue-300 font-medium">{{ $this->selectedProject?->name }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showQuickDailyLogModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <form wire:submit="saveQuickDailyLog" class="p-6 space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Datum *</label>
                            <input wire:model="quickLogDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Wetter</label>
                            <select wire:model="quickLogWeather" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white">
                                <option value="Sonnig">Sonnig</option>
                                <option value="Bewölkt">Bewölkt</option>
                                <option value="Regen">Regen</option>
                                <option value="Frost/Schnee">Frost/Schnee</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Arbeiter</label>
                            <input wire:model="quickLogWorkersCount" type="number" min="1" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subunternehmer / Gewerk</label>
                        <select wire:model="quickLogContactId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-900 focus:border-blue-600 focus:bg-white">
                            <option value="">🏢 Eigenleistung (BT Bautechnik)</option>
                            @foreach ($this->subcontractors as $sub)
                                <option value="{{ $sub->id }}">🏗️ {{ $sub->display_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Geleistete Arbeiten *</label>
                        <textarea wire:model="quickLogWorkPerformed" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" placeholder="Details zu Fortschritt, Materialverbrauch und Monteuren..." required></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Vorkommnisse / Störungen (Optional)</label>
                        <textarea wire:model="quickLogSpecialOccurrences" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" placeholder="Verzögerungen, Behinderungen, Materialmangel..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showQuickDailyLogModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20">
                            🎙️ Tagesbericht speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Quick Schedule Modal (In-Page) -->
    @if ($showQuickScheduleModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">👷</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Handwerker für Baustelle einteilen</h3>
                            <p class="text-[11px] text-blue-300 font-medium">{{ $this->selectedProject?->name }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showQuickScheduleModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <form wire:submit="saveQuickSchedule" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Personal-Typ</label>
                            <select wire:model.live="quickScheduleWorkerType" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:bg-white">
                                <option value="mitarbeiter">Mitarbeiter</option>
                                <option value="subunternehmer">Subunternehmer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Datum *</label>
                            <input wire:model="quickScheduleDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" required>
                        </div>
                    </div>

                    @if($quickScheduleWorkerType === 'subunternehmer')
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subunternehmer Auswählen</label>
                            <select wire:model="quickScheduleContactId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-900 focus:border-blue-600 focus:bg-white">
                                <option value="">-- Subunternehmer wählen --</option>
                                @foreach ($this->subcontractors as $sub)
                                    <option value="{{ $sub->id }}">🏗️ {{ $sub->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Name des Mitarbeiters / Teams</label>
                            <input wire:model="quickScheduleWorkerName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" placeholder="z. B. Spengler-Kolonne 2">
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Schicht / Einsatz</label>
                        <select wire:model="quickScheduleShiftType" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white">
                            <option value="ganztags">Ganztags (8 Std)</option>
                            <option value="vormittags">Vormittags</option>
                            <option value="nachmittags">Nachmittags</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notizen / Aufgabenstellung</label>
                        <textarea wire:model="quickScheduleNotes" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:border-blue-600 focus:bg-white" placeholder="Spezielle Anweisungen für den Tag..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showQuickScheduleModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20">
                            👷 Handwerker einteilen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Global Command Palette Modal (`Cmd + K`) -->
    <div x-data="{ showCmdPalette: false, cmdQuery: '' }" 
         x-on:keydown.window.cmd.k.prevent="showCmdPalette = true"
         x-on:keydown.window.ctrl.k.prevent="showCmdPalette = true"
         x-on:open-cmd-palette.window="showCmdPalette = true"
         x-show="showCmdPalette" 
         x-cloak
         class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-start justify-center z-50 pt-20 p-4 transition-all">
        
        <div @click.away="showCmdPalette = false" 
             class="bg-white border border-slate-200 rounded-3xl w-full max-w-xl shadow-2xl overflow-hidden flex flex-col space-y-0">
            
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
                <span class="text-slate-400 text-lg">🔍</span>
                <input x-model="cmdQuery" 
                       x-ref="cmdInput"
                       x-effect="if (showCmdPalette) setTimeout(() => $refs.cmdInput.focus(), 50)"
                       type="text" 
                       class="w-full bg-transparent border-0 text-sm font-bold text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0" 
                       placeholder="Suchen nach Baustellen, Werkzeugen, Rechnungen... (z. B. Berching, Mangel, Tagebuch)">
                <button @click="showCmdPalette = false" class="text-slate-400 hover:text-slate-700 text-xs font-bold">✕</button>
            </div>

            <div class="p-4 max-h-96 overflow-y-auto space-y-3">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 px-2">Schnell-Aktionen & Navigation</div>
                
                <div class="grid grid-cols-2 gap-2">
                    <button @click="showCmdPalette = false; $wire.openCreateProject()" class="p-3 bg-slate-50 hover:bg-blue-50 text-left rounded-xl border border-slate-200 hover:border-blue-300 transition cursor-pointer flex items-center gap-2">
                        <span class="text-base">🏗️</span>
                        <div>
                            <div class="text-xs font-bold text-slate-900">Neue Baustelle</div>
                            <div class="text-[10px] text-slate-500">Projekt anlegen</div>
                        </div>
                    </button>

                    <button @click="showCmdPalette = false; window.location.href='/bautagebuch'" class="p-3 bg-slate-50 hover:bg-blue-50 text-left rounded-xl border border-slate-200 hover:border-blue-300 transition cursor-pointer flex items-center gap-2">
                        <span class="text-base">🎙️</span>
                        <div>
                            <div class="text-xs font-bold text-slate-900">Bautagebuch</div>
                            <div class="text-[10px] text-slate-500">Tagesberichte verfassen</div>
                        </div>
                    </button>

                    <button @click="showCmdPalette = false; window.location.href='/einsatzplan'" class="p-3 bg-slate-50 hover:bg-blue-50 text-left rounded-xl border border-slate-200 hover:border-blue-300 transition cursor-pointer flex items-center gap-2">
                        <span class="text-base">👷</span>
                        <div>
                            <div class="text-xs font-bold text-slate-900">Einsatzplaner</div>
                            <div class="text-[10px] text-slate-500">Handwerker & Subunternehmer</div>
                        </div>
                    </button>

                    <button @click="showCmdPalette = false; window.location.href='/ki-agent'" class="p-3 bg-slate-50 hover:bg-blue-50 text-left rounded-xl border border-slate-200 hover:border-blue-300 transition cursor-pointer flex items-center gap-2">
                        <span class="text-base">🤖</span>
                        <div>
                            <div class="text-xs font-bold text-slate-900">KI-Assistent</div>
                            <div class="text-[10px] text-slate-500">Autonomer Bot PRO</div>
                        </div>
                    </button>
                </div>
            </div>

            <div class="p-3 bg-slate-50 border-t border-slate-200 flex justify-between items-center text-[11px] text-slate-400 font-medium">
                <span>BT Bautechnik Global Command Palette</span>
                <span>Drücken Sie <kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[9px]">ESC</kbd> zum Schließen</span>
            </div>
        </div>
    </div>
</div>
