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
    #[\Livewire\Attributes\Locked]
    public bool $isProjectPage = false;
    #[\Livewire\Attributes\Locked]
    public string $projectTab = 'uebersicht';
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
        $routeProject = request()->route('project');
        if ($routeProject instanceof Project) {
            $this->isProjectPage = true;
            $this->selectedProjectId = $routeProject->id;
            $requestedTab = request()->query('tab', 'uebersicht');
            $this->projectTab = in_array($requestedTab, ['uebersicht', 'kosten', 'tagebuch', 'maengel', 'dokumente'], true) ? $requestedTab : 'uebersicht';
            session(['ui.project_id' => $routeProject->id]);
        }
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

    public function getTodayTasksProperty(): array
    {
        return [
            'defects' => \App\Models\Defect::with('project')
                ->whereNotIn('status', ['behoben', 'abgenommen'])->whereDate('deadline', '<', today())
                ->orderBy('deadline')->get(),
            'approvals' => \App\Models\DailyLogShare::with('dailyLog.project')
                ->where('status', 'pending')->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->oldest()->get(),
            'budgets' => Project::with('budget')->withSum('actualCosts', 'cost_amount')
                ->where('status', 'active')->get()->filter(fn($p) => $p->budget && $p->budget->total_with_buffer > 0 && $p->actual_costs_sum_cost_amount > $p->budget->total_with_buffer),
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
        return Project::with(['budget', 'actualCosts', 'offers.sections.items', 'photos', 'defects.assignedContact', 'dailyLogs'])->find($this->selectedProjectId);
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
        if ($this->isProjectPage) $this->redirect(route('dashboard'), navigate: true);
    }

    // Actions
    public function selectProject($id)
    {
        $project = Project::findOrFail($id);
        $this->redirect(route('projects.show', $project), navigate: true);
    }

    public function closeProjectDetails()
    {
        $this->redirect(route('dashboard'), navigate: true);
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
    @if(!$isProjectPage)
        @include('livewire.partials.dashboard-overview')
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
                        <p class="text-xs text-slate-500 mt-0.5">Budgetverbrauch und Projektstatus im Überblick.</p>
                    </div>

                </div>

                <!-- Live Search & Filter Bar -->
                <div class="flex flex-col sm:flex-row gap-3 items-center justify-between pt-1">
                    <div class="relative w-full sm:w-80">
                        <input aria-label="Baustellen durchsuchen" wire:model.live.debounce.250ms="searchQuery" type="text"
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
                    <div wire:key="{{ $proj->id }}"
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
                                    $percent = $budgetTotal > 0 ? ($costSum / $budgetTotal) * 100 : 0;
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
                                        <div class="h-full rounded-full transition-all duration-500 {{ $percent > 90 ? 'bg-rose-500' : 'bg-slate-950' }}" style="width: {{ min(max($percent, 0), 100) }}%"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-bold text-slate-500">
                                        <span>Budgetverbrauch</span>
                                        <span class="{{ $percent > 90 ? 'text-rose-600 font-black' : 'text-slate-700' }}">{{ $budgetTotal > 0 ? number_format($percent, 1, ',', '.') . '% verbraucht' : 'Budget fehlt' }}</span>
                                    </div>
                                </div>
                                @if($costSum > $budgetTotal && $budgetTotal > 0)
                                    <p class="text-sm text-rose-700 font-semibold">{{ number_format($costSum - $budgetTotal, 2, ',', '.') }} € über Budget</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-end pt-2 border-t border-slate-100">
                            <a href="{{ route('projects.show', $proj) }}" wire:navigate @click.stop class="ui-button ui-button-secondary" aria-label="Projekt {{ $proj->name }} öffnen">Projekt öffnen <x-ui-icon name="arrow" /></a>
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

    @endif
            
    @if($isProjectPage && $this->selectedProject)
        @include('livewire.partials.project-workspace', ['proj' => $this->selectedProject])
    @endif

    <!-- MODALS -->

    <!-- 1. Create Project Modal -->
    @if($showCreateProjectModal)
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm flex items-center justify-center z-[60] p-4 animate-in fade-in duration-200">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
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
        <div data-ui-dialog role="dialog" aria-modal="true" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
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

</div>
