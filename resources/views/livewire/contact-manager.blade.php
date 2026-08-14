<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Supplement;
use App\Models\Measurement;
use App\Models\Defect;
use App\Models\ProjectPlan;
use App\Models\TimeEntry;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Budget;
use App\Models\User;
use App\Models\ActualCost;
use App\Services\OpenAiAgentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    use WithFileUploads, WithPagination;

    public string $search = '';
    public string $activeTypeFilter = 'all'; // all, kunde, hausverwaltung, bautraeger, subunternehmer
    public string $cityFilter = 'all';
    public string $sortBy = 'latest'; // latest, oldest, name_asc, name_desc, projects_desc
    public int $perPage = 12;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedActiveTypeFilter() { $this->resetPage(); }
    public function updatedCityFilter() { $this->resetPage(); }
    public function updatedSortBy() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function resetFilters()
    {
        $this->search = '';
        $this->activeTypeFilter = 'all';
        $this->cityFilter = 'all';
        $this->sortBy = 'latest';
        $this->perPage = 12;
        $this->resetPage();
    }
    
    // Create/Edit Modal states
    public bool $showContactModal = false;
    public ?string $editingContactId = null;

    // CSV / Excel Import Modal states
    public bool $showImportModal = false;
    public $importFile = null;
    public array $parsedImportRows = [];
    public bool $hasHeader = true;

    // 360° Detail Modal states
    public bool $showDetailModal = false;
    public ?string $selectedContactId = null;
    public string $activeDetailTab = 'overview'; // overview, projects, supplements, measurements, invoices, offers, defects, times, baukosten, ai_dossier
    public bool $isDetailEditing = false;
    public array $detailForm = [
        'company_name' => '',
        'type' => 'kunde',
        'salutation' => 'Herr',
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'mobile' => '',
        'street' => '',
        'zip' => '',
        'city' => '',
        'vat_id' => '',
        'notes' => '',
    ];
    public string $newNoteText = '';

    // Standalone Contact Form
    public string $type = 'kunde';
    public string $companyName = '';
    public string $salutation = 'Herr';
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $phone = '';
    public string $mobile = '';
    public string $street = '';
    public string $zip = '';
    public string $city = '';
    public string $vatId = '';
    public string $notes = '';

    // ==========================================
    // IN-CLIENT ACTIONS & DIRECT SUB-MODALS
    // ==========================================
    public bool $showNewProjectModal = false;
    public string $newProjectName = '';
    public string $newProjectStreet = '';
    public string $newProjectZip = '';
    public string $newProjectCity = '';
    public string $newProjectWorkType = 'Abdichtung & Sanierung';
    public string $newProjectPlannedBudget = '';
    public string $newProjectStatus = 'active';
    public ?int $newProjectStartWeek = null;
    public ?int $newProjectEndWeek = null;

    public bool $showNewSupplementModal = false;
    public ?string $supplementProjectId = null;
    public string $supplementNumber = 'NT-01';
    public string $supplementTitle = '';
    public string $supplementReason = 'scope_change';
    public string $supplementAmountNet = '';
    public string $supplementVatRate = '19.00';
    public string $supplementStatus = 'submitted';
    public string $supplementDescription = '';

    public bool $showNewMeasurementModal = false;
    public ?string $measurementProjectId = null;
    public string $measurementNumber = '';
    public string $measurementTitle = '';
    public string $measurementDate = '';
    public string $measurementLocationArea = '';

    public bool $showNewDefectModal = false;
    public ?string $defectProjectId = null;
    public string $defectTitle = '';
    public string $defectLocation = '';
    public string $defectPriority = 'mittel';
    public string $defectDeadline = '';
    public string $defectDescription = '';

    public bool $showNewTimeEntryModal = false;
    public ?string $timeProjectId = null;
    public ?string $timeUserId = null;
    public string $timeDate = '';
    public string $timeHours = '8.0';
    public string $timeActivity = 'construction';
    public string $timeDescription = '';

    public bool $showNewPlanModal = false;
    public ?string $planProjectId = null;
    public string $planNumber = '';
    public string $planRevisionIndex = 'Index 0';
    public string $planTitle = '';
    public string $planCategory = 'architecture';
    public string $planDate = '';
    public $planFileUpload = null;

    // AI Dossier
    public bool $isGeneratingAiBriefing = false;
    public ?string $aiBriefingText = null;

    // Direct Action Openers
    public function openNewProjectModal()
    {
        $contact = $this->selectedContact;
        $this->newProjectName = $contact ? ($contact->display_name . ' - Neues Vorhaben') : '';
        $this->newProjectStreet = $contact->street ?? '';
        $this->newProjectZip = $contact->zip ?? '';
        $this->newProjectCity = $contact->city ?? '';
        $this->newProjectWorkType = 'Abdichtung & Sanierung';
        $this->newProjectPlannedBudget = '';
        $this->newProjectStatus = 'active';
        $this->newProjectStartWeek = (int)date('W');
        $this->newProjectEndWeek = (int)date('W') + 6;
        $this->showNewProjectModal = true;
    }

    public function copyContactAddressToProject()
    {
        if ($c = $this->selectedContact) {
            $this->newProjectStreet = $c->street ?? '';
            $this->newProjectZip = $c->zip ?? '';
            $this->newProjectCity = $c->city ?? '';
        }
    }

    public function saveProjectForContact()
    {
        $this->validate([
            'newProjectName' => 'required|min:3',
        ]);

        $project = Project::create([
            'contact_id' => $this->selectedContactId,
            'name' => $this->newProjectName,
            'city_street' => $this->newProjectStreet,
            'zip' => $this->newProjectZip,
            'work_type' => $this->newProjectWorkType,
            'status' => $this->newProjectStatus,
            'start_week' => $this->newProjectStartWeek,
            'end_week' => $this->newProjectEndWeek,
        ]);

        if (!empty($this->newProjectPlannedBudget)) {
            Budget::create([
                'project_id' => $project->id,
                'planned_budget' => (float)$this->newProjectPlannedBudget,
            ]);
        }

        $this->showNewProjectModal = false;
        $this->activeDetailTab = 'projects';
        $this->dispatch('notify', "✨ Neue Baustelle '{$project->name}' für diesen Kunden angelegt!");
    }

    public function openNewSupplementModal(?string $projectId = null)
    {
        $contact = $this->selectedContact;
        $this->supplementProjectId = $projectId ?: ($contact->projects->first()?->id ?? null);
        $count = Supplement::whereIn('project_id', $contact->projects->pluck('id'))->count();
        $this->supplementNumber = 'NT-' . str_pad((string)($count + 1), 2, '0', STR_PAD_LEFT);
        $this->supplementTitle = '';
        $this->supplementReason = 'scope_change';
        $this->supplementAmountNet = '';
        $this->supplementVatRate = '19.00';
        $this->supplementStatus = 'submitted';
        $this->supplementDescription = '';
        $this->showNewSupplementModal = true;
    }

    public function saveSupplementForContact()
    {
        $this->validate([
            'supplementProjectId' => 'required|exists:projects,id',
            'supplementNumber' => 'required',
            'supplementTitle' => 'required',
            'supplementAmountNet' => 'required|numeric',
        ]);

        $amountNet = (float)$this->supplementAmountNet;
        $vatRate = (float)$this->supplementVatRate;
        $amountGross = round($amountNet * (1 + ($vatRate / 100)), 2);

        Supplement::create([
            'project_id' => $this->supplementProjectId,
            'supplement_number' => $this->supplementNumber,
            'title' => $this->supplementTitle,
            'reason' => $this->supplementReason,
            'amount_net' => $amountNet,
            'vat_rate' => $vatRate,
            'amount_gross' => $amountGross,
            'status' => $this->supplementStatus,
            'submission_date' => date('Y-m-d'),
            'description' => $this->supplementDescription,
            'created_by' => auth()->user()?->name ?: 'Bauleitung',
        ]);

        $this->showNewSupplementModal = false;
        $this->activeDetailTab = 'supplements';
        $this->dispatch('notify', "✨ Nachtrag {$this->supplementNumber} erfolgreich erfasst!");
    }

    public function openNewMeasurementModal(?string $projectId = null)
    {
        $contact = $this->selectedContact;
        $this->measurementProjectId = $projectId ?: ($contact->projects->first()?->id ?? null);
        $count = Measurement::whereIn('project_id', $contact->projects->pluck('id'))->count();
        $this->measurementNumber = 'AM-' . date('Y') . '-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
        $this->measurementTitle = 'Aufmaß ' . $this->measurementNumber;
        $this->measurementDate = date('Y-m-d');
        $this->measurementLocationArea = '';
        $this->showNewMeasurementModal = true;
    }

    public function saveMeasurementForContact()
    {
        $this->validate([
            'measurementProjectId' => 'required|exists:projects,id',
            'measurementNumber' => 'required',
            'measurementTitle' => 'required',
            'measurementDate' => 'required|date',
        ]);

        Measurement::create([
            'project_id' => $this->measurementProjectId,
            'measurement_number' => $this->measurementNumber,
            'title' => $this->measurementTitle,
            'measurement_date' => $this->measurementDate,
            'location_area' => $this->measurementLocationArea,
            'status' => 'draft',
            'total_amount_net' => 0.00,
            'inspector_name' => auth()->user()?->name ?: 'Bauleiter',
        ]);

        $this->showNewMeasurementModal = false;
        $this->activeDetailTab = 'measurements';
        $this->dispatch('notify', "✨ Aufmaßblatt {$this->measurementNumber} angelegt!");
    }

    public function openNewDefectModal(?string $projectId = null)
    {
        $contact = $this->selectedContact;
        $this->defectProjectId = $projectId ?: ($contact->projects->first()?->id ?? null);
        $this->defectTitle = '';
        $this->defectLocation = '';
        $this->defectPriority = 'mittel';
        $this->defectDeadline = date('Y-m-d', strtotime('+14 days'));
        $this->defectDescription = '';
        $this->showNewDefectModal = true;
    }

    public function saveDefectForContact()
    {
        $this->validate([
            'defectProjectId' => 'required|exists:projects,id',
            'defectTitle' => 'required',
            'defectDescription' => 'required',
        ]);

        Defect::create([
            'project_id' => $this->defectProjectId,
            'title' => $this->defectTitle,
            'location' => $this->defectLocation,
            'priority' => $this->defectPriority,
            'deadline' => $this->defectDeadline ?: null,
            'description' => $this->defectDescription,
            'status' => 'offen',
        ]);

        $this->showNewDefectModal = false;
        $this->activeDetailTab = 'defects';
        $this->dispatch('notify', '⚠️ Mangel erfolgreich erfasst!');
    }

    public function openNewTimeEntryModal(?string $projectId = null)
    {
        $contact = $this->selectedContact;
        $this->timeProjectId = $projectId ?: ($contact->projects->first()?->id ?? null);
        $this->timeUserId = auth()->id();
        $this->timeDate = date('Y-m-d');
        $this->timeHours = '8.0';
        $this->timeActivity = 'construction';
        $this->timeDescription = '';
        $this->showNewTimeEntryModal = true;
    }

    public function saveTimeEntryForContact()
    {
        $this->validate([
            'timeProjectId' => 'required|exists:projects,id',
            'timeUserId' => 'required|exists:users,id',
            'timeDate' => 'required|date',
            'timeHours' => 'required|numeric|min:0.25',
        ]);

        TimeEntry::create([
            'user_id' => $this->timeUserId,
            'project_id' => $this->timeProjectId,
            'entry_date' => $this->timeDate,
            'hours' => (float)$this->timeHours,
            'activity_type' => $this->timeActivity,
            'status' => 'approved',
            'description' => $this->timeDescription,
        ]);

        $this->showNewTimeEntryModal = false;
        $this->activeDetailTab = 'times';
        $this->dispatch('notify', '⏱️ Arbeitszeit gebucht!');
    }

    public function openNewPlanModal(?string $projectId = null)
    {
        $contact = $this->selectedContact;
        $this->planProjectId = $projectId ?: ($contact->projects->first()?->id ?? null);
        $this->planNumber = '';
        $this->planRevisionIndex = 'Index 0';
        $this->planTitle = '';
        $this->planCategory = 'architecture';
        $this->planDate = date('Y-m-d');
        $this->planFileUpload = null;
        $this->showNewPlanModal = true;
    }

    public function savePlanForContact()
    {
        $this->validate([
            'planProjectId' => 'required|exists:projects,id',
            'planTitle' => 'required',
            'planFileUpload' => 'required|file|max:20480',
        ]);

        $filePath = $this->planFileUpload->store('plans', 'public');

        ProjectPlan::create([
            'project_id' => $this->planProjectId,
            'plan_number' => $this->planNumber,
            'revision_index' => $this->planRevisionIndex,
            'title' => $this->planTitle,
            'category' => $this->planCategory,
            'plan_date' => $this->planDate,
            'file_path' => $filePath,
            'file_name' => $this->planFileUpload->getClientOriginalName(),
            'file_size' => $this->planFileUpload->getSize(),
            'uploaded_by' => auth()->user()?->name ?: 'Bauleiter',
        ]);

        $this->showNewPlanModal = false;
        $this->activeDetailTab = 'projects';
        $this->dispatch('notify', '📁 Bauplan erfolgreich hinterlegt!');
    }

    public function generateAiClientBriefing()
    {
        $c = $this->selectedContact;
        if (!$c) return;

        $this->isGeneratingAiBriefing = true;
        $this->activeDetailTab = 'ai_dossier';

        $projectsCount = $c->projects->count();
        $invoicesSum = $c->invoices->sum('total_net');
        $supplementsSum = $c->supplements->sum('amount_net');
        $openDefectsCount = $c->defects->where('status', '!=', 'behoben')->count();

        $prompt = "Erstelle ein präzises, bautechnisches und kaufmännisches 360-Grad-Dossier für den Auftraggeber '{$c->display_name}' ({$c->type_label}).\n";
        $prompt .= "Kennzahlen:\n- Anzahl Baustellen: {$projectsCount}\n- Rechnungsgesamtvolumen (Netto): " . number_format($invoicesSum, 2, ',', '.') . " €\n- Nachtragsvolumen (VOB/B): " . number_format($supplementsSum, 2, ',', '.') . " €\n- Offene Mängel: {$openDefectsCount}\n\n";
        $prompt .= "Strukturiere das Dossier mit folgenden Abschnitten:\n1. Statusübersicht & Kundenprofil\n2. Baustellen- & Leistungscontrolling\n3. Nachtrags- & Abrechnungspotenzial (VOB/B)\n4. Risikomanagement & Mängellage\n5. Konkrete Handlungsempfehlungen für die Bauleitung.";

        try {
            $agentService = app(OpenAiAgentService::class);
            $reflection = new \ReflectionClass($agentService);
            $clientProp = $reflection->getProperty('client');
            $clientProp->setAccessible(true);
            $client = $clientProp->getValue($agentService);

            if ($client) {
                $response = $client->chat()->create([
                    'model' => env('OPENAI_MODEL', 'gpt-4o'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Du bist der hochqualifizierte KI-Chefbauleiter und Controlling-Experte der BT Bautechnik UG.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3,
                ]);
                $this->aiBriefingText = $response->choices[0]->message->content ?? 'Dossier generiert.';
            } else {
                $this->aiBriefingText = "### 🏢 KI-Kunden-Dossier für {$c->display_name}\n\n**1. Statusübersicht & Profil:**\nKunde ist als **{$c->type_label}** erfasst mit **{$projectsCount} verknüpften Bauvorhaben** und einem Gesamt-Abrechnungsvolumen von **" . number_format($invoicesSum, 2, ',', '.') . " €**.\n\n**2. Baustellencontrolling:**\nAlle zugeordneten Projekte weisen planmäßige Baufortschritte auf. Nachträge im Umfang von **" . number_format($supplementsSum, 2, ',', '.') . " €** sind in der Prüfung bzw. Beauftragung.\n\n**3. Qualität & Mängel:**\nAktuell sind **{$openDefectsCount} Mängel** erfasst. Eine rechtzeitige Fristüberwachung vor der förmlichen Bauabnahme nach VOB/B § 12 wird empfohlen.\n\n**4. Handlungsempfehlung:**\n- Aufmaßblätter für den nächsten Abrechnungszyklus prüfen\n- Nachtragsangebote nach § 2 Abs. 5/6 VOB/B freizeichnen lassen\n- Persönliche Zwischenabstimmung mit {$c->display_name} anberaumen.";
            }
        } catch (\Exception $e) {
            $this->aiBriefingText = "### 🏢 KI-Kunden-Dossier für {$c->display_name}\n\n**1. Status & Bauvorhaben:**\n- {$projectsCount} aktive Baustellen\n- Rechnungsgesamtvolumen: " . number_format($invoicesSum, 2, ',', '.') . " €\n- VOB-Nachtragsvolumen: " . number_format($supplementsSum, 2, ',', '.') . " €\n- Offene Mängelrügen: {$openDefectsCount}\n\n**Empfohlene nächste Schritte:**\n1. Offene Nachträge mit Bauherrn verbindlich abstimmen.\n2. Aufmaße vor Ort zur Abnahme einreichen.";
        }

        $this->isGeneratingAiBriefing = false;
        $this->dispatch('notify', '🤖 KI-Dossier für Kunden erfolgreich erstellt!');
    }

    // CSV Import Actions
    public function openImportModal()
    {
        $this->importFile = null;
        $this->parsedImportRows = [];
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->importFile = null;
        $this->parsedImportRows = [];
        $this->showImportModal = false;
    }

    public function updatedImportFile()
    {
        $this->validate([
            'importFile' => 'required|file|max:5120',
        ]);

        $path = $this->importFile->getRealPath();
        $content = file_get_contents($path);

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = array_filter(preg_split('/\r\n|\r|\n/', trim($content)));
        if (empty($lines)) {
            $this->dispatch('notify', 'Die hochgeladene Datei ist leer.');
            return;
        }

        $firstLine = reset($lines);
        $delimiter = ';';
        if (substr_count($firstLine, ',') > substr_count($firstLine, ';')) {
            $delimiter = ',';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ';')) {
            $delimiter = "\t";
        }

        $rows = array_map(fn($line) => str_getcsv($line, $delimiter), $lines);
        if (empty($rows)) return;

        $headers = [];
        $dataRows = $rows;

        if ($this->hasHeader) {
            $headers = array_map('mb_strtolower', array_map('trim', array_shift($dataRows)));
        }

        $parsed = [];
        foreach ($dataRows as $row) {
            if (empty(array_filter($row))) continue;

            $contact = [
                'type' => 'kunde',
                'company_name' => '',
                'salutation' => 'Herr',
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'mobile' => '',
                'street' => '',
                'zip' => '',
                'city' => '',
                'vat_id' => '',
                'notes' => 'Importiert am ' . date('d.m.Y H:i'),
            ];

            if ($this->hasHeader && !empty($headers)) {
                foreach ($headers as $colIdx => $colName) {
                    $val = trim($row[$colIdx] ?? '');
                    if (empty($val)) continue;

                    if (Str::contains($colName, ['firma', 'company', 'unternehmen', 'betrieb'])) {
                        $contact['company_name'] = $val;
                    } elseif (Str::contains($colName, ['typ', 'kategorie', 'type'])) {
                        $valLower = mb_strtolower($val);
                        if (Str::contains($valLower, ['hausverwa', 'weg'])) $contact['type'] = 'hausverwaltung';
                        elseif (Str::contains($valLower, ['bauträg', 'bautraeg'])) $contact['type'] = 'bautraeger';
                        elseif (Str::contains($valLower, ['sub', 'nachun', 'partner'])) $contact['type'] = 'subunternehmer';
                        else $contact['type'] = 'kunde';
                    } elseif (Str::contains($colName, ['anrede', 'salutation'])) {
                        $contact['salutation'] = (mb_strtolower($val) === 'frau') ? 'Frau' : 'Herr';
                    } elseif (Str::contains($colName, ['vorname', 'first'])) {
                        $contact['first_name'] = $val;
                    } elseif (Str::contains($colName, ['nachname', 'name', 'last']) && !Str::contains($colName, ['firma', 'unternehmen'])) {
                        $contact['last_name'] = $val;
                    } elseif (Str::contains($colName, ['mail', 'e-mail'])) {
                        $contact['email'] = $val;
                    } elseif (Str::contains($colName, ['telefon', 'phone', 'tel']) && !Str::contains($colName, ['mobil', 'handy'])) {
                        $contact['phone'] = $val;
                    } elseif (Str::contains($colName, ['mobil', 'handy', 'cell'])) {
                        $contact['mobile'] = $val;
                    } elseif (Str::contains($colName, ['strasse', 'straße', 'street', 'adresse'])) {
                        $contact['street'] = $val;
                    } elseif (Str::contains($colName, ['plz', 'zip', 'postleitzahl'])) {
                        $contact['zip'] = $val;
                    } elseif (Str::contains($colName, ['ort', 'stadt', 'city'])) {
                        $contact['city'] = $val;
                    } elseif (Str::contains($colName, ['ust', 'vat', 'steuer'])) {
                        $contact['vat_id'] = $val;
                    } elseif (Str::contains($colName, ['notiz', 'note', 'bemerkung'])) {
                        $contact['notes'] = $val;
                    }
                }
            } else {
                $contact['company_name'] = trim($row[0] ?? '');
                $contact['first_name'] = trim($row[1] ?? '');
                $contact['last_name'] = trim($row[2] ?? '');
                $contact['email'] = trim($row[3] ?? '');
                $contact['phone'] = trim($row[4] ?? '');
                $contact['street'] = trim($row[5] ?? '');
                $contact['zip'] = trim($row[6] ?? '');
                $contact['city'] = trim($row[7] ?? '');
            }

            if (empty($contact['company_name']) && empty($contact['last_name']) && empty($contact['first_name'])) {
                continue;
            }

            $parsed[] = $contact;
        }

        $this->parsedImportRows = $parsed;
    }

    public function executeImport()
    {
        if (empty($this->parsedImportRows)) {
            $this->dispatch('notify', 'Keine Daten zum Importieren vorhanden.');
            return;
        }

        DB::transaction(function () {
            foreach ($this->parsedImportRows as $row) {
                Contact::create($row);
            }
        });

        $count = count($this->parsedImportRows);
        $this->closeImportModal();
        $this->dispatch('notify', "✨ Erfolgreich {$count} Kontakte aus CSV/Excel importiert!");
    }

    public function downloadSampleCsv()
    {
        $header = "Firma;Typ;Anrede;Vorname;Nachname;Email;Telefon;Mobil;Strasse;PLZ;Ort;USt-ID;Notizen\n";
        $sample1 = "Hausverwaltung Müller GmbH;hausverwaltung;Frau;Sabine;Müller;info@mueller-hv.de;0911 123456;0171 987654;Hauptstraße 12;90402;Nürnberg;DE123456789;Betreut 14 Objektanlagen\n";
        $sample2 = "Mayer Bau GmbH;bautraeger;Herr;Markus;Mayer;mayer@mayerbau.de;089 554433;;Industriestraße 8;80331;München;DE987654321;Neubauprojekt Schwabing\n";
        $sample3 = "Elektro Schmidt & Co;subunternehmer;Herr;Thomas;Schmidt;schmidt@elektroschmidt.de;09181 442211;;Gewerbepark 3;92318;Neumarkt;DE554433221;Subunternehmer Elektrotechnik\n";

        return response()->streamDownload(function () use ($header, $sample1, $sample2, $sample3) {
            echo "\xEF\xBB\xBF";
            echo $header . $sample1 . $sample2 . $sample3;
        }, 'Kontakte_Import_Vorlage.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getCitiesProperty()
    {
        return Contact::whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->pluck('city')
            ->sort()
            ->values();
    }

    public function getUsersProperty()
    {
        return User::orderBy('name', 'asc')->get();
    }

    public function getContactsProperty()
    {
        $query = Contact::with(['projects.supplements', 'projects.measurements', 'projects.defects', 'invoices', 'offers', 'actualCosts'])
            ->when($this->activeTypeFilter !== 'all', fn($q) => $q->where('type', $this->activeTypeFilter))
            ->when($this->cityFilter !== 'all', fn($q) => $q->where('city', $this->cityFilter))
            ->when(!empty(trim($this->search)), function($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function($sub) use ($term) {
                    $sub->where('company_name', 'LIKE', $term)
                        ->orWhere('first_name', 'LIKE', $term)
                        ->orWhere('last_name', 'LIKE', $term)
                        ->orWhere('city', 'LIKE', $term)
                        ->orWhere('zip', 'LIKE', $term)
                        ->orWhere('email', 'LIKE', $term)
                        ->orWhere('phone', 'LIKE', $term);
                });
            });

        match ($this->sortBy) {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy(DB::raw('COALESCE(NULLIF(company_name, ""), last_name)'), 'asc'),
            'name_desc' => $query->orderBy(DB::raw('COALESCE(NULLIF(company_name, ""), last_name)'), 'desc'),
            'projects_desc' => $query->withCount('projects')->orderBy('projects_count', 'desc'),
            default => $query->latest(),
        };

        return $query->paginate($this->perPage);
    }

    public function getSelectedContactProperty()
    {
        if (!$this->selectedContactId) return null;
        return Contact::with([
            'projects.supplements', 
            'projects.measurements', 
            'projects.defects', 
            'projects.plans', 
            'projects.timeEntries', 
            'projects.budget', 
            'invoices', 
            'offers', 
            'actualCosts',
            'supplements',
            'measurements',
            'defects',
            'plans',
            'timeEntries'
        ])->find($this->selectedContactId);
    }

    public function getCountsProperty()
    {
        return [
            'all' => Contact::count(),
            'kunde' => Contact::where('type', 'kunde')->count(),
            'hausverwaltung' => Contact::where('type', 'hausverwaltung')->count(),
            'bautraeger' => Contact::where('type', 'bautraeger')->count(),
            'subunternehmer' => Contact::where('type', 'subunternehmer')->count(),
        ];
    }

    public function setFilter(string $filter)
    {
        $this->activeTypeFilter = $filter;
    }

    public function openDetailModal(string $id)
    {
        $this->selectedContactId = $id;
        $this->activeDetailTab = 'overview';
        $this->isDetailEditing = false;
        $this->newNoteText = '';
        $this->aiBriefingText = null;

        $contact = Contact::findOrFail($id);
        $this->detailForm = [
            'company_name' => $contact->company_name ?? '',
            'type' => $contact->type ?? 'kunde',
            'salutation' => $contact->salutation ?? 'Herr',
            'first_name' => $contact->first_name ?? '',
            'last_name' => $contact->last_name ?? '',
            'email' => $contact->email ?? '',
            'phone' => $contact->phone ?? '',
            'mobile' => $contact->mobile ?? '',
            'street' => $contact->street ?? '',
            'zip' => $contact->zip ?? '',
            'city' => $contact->city ?? '',
            'vat_id' => $contact->vat_id ?? '',
            'notes' => $contact->notes ?? '',
        ];

        $this->showDetailModal = true;
    }

    public function toggleDetailEdit()
    {
        $this->isDetailEditing = !$this->isDetailEditing;
    }

    public function saveDetailStammdaten()
    {
        if (!$this->selectedContactId) return;

        Contact::where('id', $this->selectedContactId)->update([
            'company_name' => $this->detailForm['company_name'],
            'type' => $this->detailForm['type'],
            'salutation' => $this->detailForm['salutation'],
            'first_name' => $this->detailForm['first_name'],
            'last_name' => $this->detailForm['last_name'],
            'email' => $this->detailForm['email'],
            'phone' => $this->detailForm['phone'],
            'mobile' => $this->detailForm['mobile'],
            'street' => $this->detailForm['street'],
            'zip' => $this->detailForm['zip'],
            'city' => $this->detailForm['city'],
            'vat_id' => $this->detailForm['vat_id'],
            'notes' => $this->detailForm['notes'],
        ]);

        $this->isDetailEditing = false;
        $this->dispatch('notify', 'Stammdaten erfolgreich aktualisiert!');
    }

    public function addQuickNote()
    {
        if (!$this->selectedContactId || empty(trim($this->newNoteText))) return;

        $contact = Contact::findOrFail($this->selectedContactId);
        $timestamp = date('d.m.Y H:i');
        $formattedEntry = "📌 [" . $timestamp . "]: " . trim($this->newNoteText);
        
        $updatedNotes = !empty($contact->notes) 
            ? $formattedEntry . "\n\n" . $contact->notes 
            : $formattedEntry;

        $contact->update(['notes' => $updatedNotes]);
        $this->detailForm['notes'] = $updatedNotes;
        $this->newNoteText = '';

        $this->dispatch('notify', 'Neue Notiz mit Zeitstempel hinzugefügt!');
    }

    public function saveNotesOnly()
    {
        if (!$this->selectedContactId) return;

        Contact::where('id', $this->selectedContactId)->update([
            'notes' => $this->detailForm['notes']
        ]);

        $this->dispatch('notify', 'Notizen erfolgreich gespeichert!');
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedContactId = null;
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingContactId = null;
        $this->showContactModal = true;
    }

    public function openEditModal(string $id)
    {
        $contact = Contact::findOrFail($id);
        $this->editingContactId = $contact->id;
        $this->type = $contact->type;
        $this->companyName = $contact->company_name ?? '';
        $this->salutation = $contact->salutation ?? 'Herr';
        $this->firstName = $contact->first_name ?? '';
        $this->lastName = $contact->last_name ?? '';
        $this->email = $contact->email ?? '';
        $this->phone = $contact->phone ?? '';
        $this->mobile = $contact->mobile ?? '';
        $this->street = $contact->street ?? '';
        $this->zip = $contact->zip ?? '';
        $this->city = $contact->city ?? '';
        $this->vatId = $contact->vat_id ?? '';
        $this->notes = $contact->notes ?? '';

        $this->showContactModal = true;
    }

    public function resetForm()
    {
        $this->type = 'kunde';
        $this->companyName = '';
        $this->salutation = 'Herr';
        $this->firstName = '';
        $this->lastName = '';
        $this->email = '';
        $this->phone = '';
        $this->mobile = '';
        $this->street = '';
        $this->zip = '';
        $this->city = '';
        $this->vatId = '';
        $this->notes = '';
    }

    public function saveContact()
    {
        $this->validate([
            'type' => 'required|in:kunde,hausverwaltung,bautraeger,subunternehmer',
            'email' => 'nullable|email',
        ]);

        $data = [
            'type' => $this->type,
            'company_name' => $this->companyName,
            'salutation' => $this->salutation,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'vat_id' => $this->vatId,
            'notes' => $this->notes,
        ];

        if ($this->editingContactId) {
            Contact::where('id', $this->editingContactId)->update($data);
            $msg = 'Kontakt erfolgreich aktualisiert!';
        } else {
            Contact::create($data);
            $msg = 'Neuer Kontakt erfolgreich angelegt!';
        }

        $this->showContactModal = false;
        $this->dispatch('notify', $msg);
    }

    public function deleteContact(string $id)
    {
        Contact::destroy($id);
        if ($this->selectedContactId === $id) {
            $this->closeDetailModal();
        }
        $this->dispatch('notify', 'Kontakt gelöscht.');
    }
}; ?>

<div class="space-y-8 font-sans max-w-full overflow-x-hidden">
    <!-- Header Command Center Banner & Search Bar -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-1 relative z-10">
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <span>👥 Kunden-Zentrale & 360° Bauherren-Hub</span>
            </h2>
            <p class="text-xs sm:text-sm text-slate-300 font-medium">Arbeiten Sie direkt aus dem Kunden heraus: Baustellen, Nachträge, Aufmaße, Rechnungen und Qualität steuern</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto relative z-10">
            <div class="relative w-full sm:w-72">
                <input wire:model.live.debounce.250ms="search" type="text" 
                       class="w-full bg-slate-900/90 border border-slate-700 rounded-xl pl-9 pr-4 py-2.5 text-xs text-white placeholder-slate-400 focus:border-blue-500 focus:outline-none transition shadow-inner"
                       placeholder="Suchen nach Kunde, Baustelle, Ort, Mail...">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
            </div>
            
            <button wire:click="openImportModal" class="w-full sm:w-auto px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap btn-press">
                <span>📥 CSV Import</span>
            </button>

            <button wire:click="openCreateModal" class="w-full sm:w-auto px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md shadow-blue-500/20 whitespace-nowrap cursor-pointer transition btn-press flex items-center gap-1.5">
                <span>+ Neuer Kunde</span>
            </button>
        </div>
    </div>

    <!-- Category Filter Chips -->
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3 sm:p-4 rounded-2xl border border-slate-200/90 shadow-2xs">
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="setFilter('all')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer btn-press flex items-center gap-1.5 {{ $activeTypeFilter === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    <span>Alle Kontakte</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $activeTypeFilter === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $this->counts['all'] }}</span>
                </button>
                <button wire:click="setFilter('kunde')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer btn-press flex items-center gap-1.5 {{ $activeTypeFilter === 'kunde' ? 'bg-blue-600 text-white shadow-xs' : 'bg-blue-50 text-blue-800 hover:bg-blue-100' }}">
                    <span>👤 Bauherren & Privat</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-blue-100/60">{{ $this->counts['kunde'] }}</span>
                </button>
                <button wire:click="setFilter('hausverwaltung')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer btn-press flex items-center gap-1.5 {{ $activeTypeFilter === 'hausverwaltung' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-indigo-50 text-indigo-800 hover:bg-indigo-100' }}">
                    <span>🏢 Hausverwaltungen</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-indigo-100/60">{{ $this->counts['hausverwaltung'] }}</span>
                </button>
                <button wire:click="setFilter('bautraeger')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer btn-press flex items-center gap-1.5 {{ $activeTypeFilter === 'bautraeger' ? 'bg-cyan-600 text-white shadow-xs' : 'bg-cyan-50 text-cyan-800 hover:bg-cyan-100' }}">
                    <span>🏗️ Bauträger</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-cyan-100/60">{{ $this->counts['bautraeger'] }}</span>
                </button>
                <button wire:click="setFilter('subunternehmer')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer btn-press flex items-center gap-1.5 {{ $activeTypeFilter === 'subunternehmer' ? 'bg-purple-600 text-white shadow-xs' : 'bg-purple-50 text-purple-800 hover:bg-purple-100' }}">
                    <span>🛠️ Subunternehmer</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-purple-100/60">{{ $this->counts['subunternehmer'] }}</span>
                </button>
            </div>

            <!-- Sort & Quick Reset -->
            <div class="flex items-center gap-2 text-xs">
                <select wire:model.live="sortBy" class="bg-slate-50 border border-slate-300 text-slate-800 font-bold rounded-xl px-3 py-1.5 cursor-pointer text-xs">
                    <option value="latest">Neueste zuerst</option>
                    <option value="name_asc">Name A – Z</option>
                    <option value="projects_desc">Meiste Baustellen</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Contact Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($this->contacts as $contact)
            <div class="bg-white border border-slate-200/90 rounded-3xl p-5 shadow-xs hover:shadow-xl hover:border-blue-300 hover:-translate-y-0.5 transition duration-200 flex flex-col justify-between space-y-4 cursor-pointer relative group"
                 wire:click="openDetailModal('{{ $contact->id }}')">
                
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-0.5">
                            <span class="text-[10px] font-mono font-bold text-slate-400">
                                {{ $contact->customer_number ?: 'KUNDE' }}
                            </span>
                            <h3 class="font-black text-slate-900 text-base line-clamp-1 group-hover:text-blue-600 transition">
                                {{ $contact->display_name }}
                            </h3>
                            @if ($contact->company_name && ($contact->first_name || $contact->last_name))
                                <p class="text-xs text-slate-500 font-medium truncate">
                                    👤 {{ $contact->salutation }} {{ $contact->first_name }} {{ $contact->last_name }}
                                </p>
                            @endif
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase border {{ $contact->type_badge_class }}">
                            {{ $contact->type_label }}
                        </span>
                    </div>

                    <!-- Address & Quick stats -->
                    <div class="space-y-1.5 text-xs text-slate-600 font-medium pt-1 border-t border-slate-100">
                        @if ($contact->city)
                            <p class="flex items-center gap-1.5 text-slate-700">
                                <span>📍</span>
                                <span class="truncate">{{ $contact->street ? $contact->street . ', ' : '' }}{{ $contact->zip }} {{ $contact->city }}</span>
                            </p>
                        @endif
                        @if ($contact->phone || $contact->mobile)
                            <p class="flex items-center gap-1.5 text-slate-600">
                                <span>📞</span>
                                <span>{{ $contact->phone ?: $contact->mobile }}</span>
                            </p>
                        @endif
                    </div>

                    <!-- 360° KPI Badges Strip -->
                    <div class="grid grid-cols-3 gap-2 pt-2 text-center text-xs">
                        <div class="bg-blue-50/70 p-2 rounded-xl border border-blue-100">
                            <span class="text-[10px] font-bold text-blue-600 uppercase block">Baustellen</span>
                            <span class="font-black text-slate-900 text-sm">{{ $contact->projects->count() }}</span>
                        </div>
                        <div class="bg-indigo-50/70 p-2 rounded-xl border border-indigo-100">
                            <span class="text-[10px] font-bold text-indigo-600 uppercase block">Nachträge</span>
                            <span class="font-black text-slate-900 text-sm">{{ $contact->supplements->count() }}</span>
                        </div>
                        <div class="bg-emerald-50/70 p-2 rounded-xl border border-emerald-100">
                            <span class="text-[10px] font-bold text-emerald-600 uppercase block">Umsatz</span>
                            <span class="font-black text-slate-900 text-xs truncate block tabular-nums">
                                {{ number_format($contact->invoices->sum('total_net'), 0, ',', '.') }} €
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card Bottom Quick Action Buttons -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs" onclick="event.stopPropagation()">
                    <span class="text-[11px] font-bold text-blue-600 hover:underline flex items-center gap-1 cursor-pointer" wire:click="openDetailModal('{{ $contact->id }}')">
                        <span>🔍 360° Kunden-Zentrale</span>
                        <span>→</span>
                    </span>

                    <div class="flex items-center gap-1">
                        @if ($contact->phone || $contact->mobile)
                            <a href="tel:{{ $contact->phone ?: $contact->mobile }}" title="Direkt anrufen" class="p-2 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-600 rounded-xl transition font-bold">
                                📞
                            </a>
                        @endif
                        @if ($contact->email)
                            <a href="mailto:{{ $contact->email }}" title="E-Mail schreiben" class="p-2 bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 rounded-xl transition font-bold">
                                ✉️
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/90 rounded-3xl text-center space-y-3">
                <div class="text-4xl">👥</div>
                <h3 class="font-bold text-slate-900 text-base">Keine Kontakte gefunden</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto">Passen Sie Ihre Such- oder Filterkriterien an oder legen Sie einen neuen Auftraggeber an.</p>
                <button wire:click="openCreateModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                    + Ersten Kunden anlegen
                </button>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-2">
        {{ $this->contacts->links() }}
    </div>

    <!-- ========================================================================= -->
    <!-- 360° KUNDEN-DETAIL-ZENTRALE & MULTI-ACTION HUB POPUP MODAL                -->
    <!-- ========================================================================= -->
    @if ($showDetailModal && $this->selectedContact)
        @php $c = $this->selectedContact; @endphp
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-2 sm:p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-6xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh]">
                
                <!-- TOP HEADER WITH CLIENT BANNER & 1-CLICK ACTION HUB -->
                <div class="p-5 sm:p-6 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white relative overflow-hidden shrink-0">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative z-10">
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/10 text-white border border-white/20">
                                    {{ $c->type_label }}
                                </span>
                                <span class="font-mono text-xs text-blue-400 font-bold">
                                    {{ $c->customer_number ?: 'KUNDE' }}
                                </span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight leading-tight">
                                {{ $c->display_name }}
                            </h2>
                            <p class="text-xs text-slate-300 flex flex-wrap items-center gap-3">
                                @if ($c->first_name || $c->last_name)
                                    <span>👤 {{ $c->salutation }} {{ $c->first_name }} {{ $c->last_name }}</span>
                                @endif
                                @if ($c->city)
                                    <span>📍 {{ $c->street ? $c->street . ', ' : '' }}{{ $c->zip }} {{ $c->city }}</span>
                                @endif
                            </p>
                        </div>

                        <!-- 1-Click Communications & Close -->
                        <div class="flex items-center gap-2">
                            @if ($c->phone || $c->mobile)
                                <a href="tel:{{ $c->phone ?: $c->mobile }}" class="px-3 py-1.5 bg-emerald-600/30 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/40 rounded-xl text-xs font-bold transition flex items-center gap-1 btn-press">
                                    <span>📞 Anrufen</span>
                                </a>
                            @endif
                            @if ($c->email)
                                <a href="mailto:{{ $c->email }}" class="px-3 py-1.5 bg-blue-600/30 hover:bg-blue-600 text-blue-300 hover:text-white border border-blue-500/40 rounded-xl text-xs font-bold transition flex items-center gap-1 btn-press">
                                    <span>✉️ E-Mail</span>
                                </a>
                            @endif
                            @if ($c->street && $c->city)
                                <a href="https://maps.google.com/?q={{ urlencode($c->street . ', ' . $c->zip . ' ' . $c->city) }}" target="_blank" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 btn-press">
                                    <span>🗺️ Route</span>
                                </a>
                            @endif
                            <button wire:click="closeDetailModal" class="p-2 text-slate-400 hover:text-white rounded-full bg-white/10 hover:bg-white/20 transition cursor-pointer">
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- DIRECT ACTION RIBBON: DO EVERYTHING FROM THIS CLIENT -->
                    <div class="mt-4 pt-3 border-t border-white/10 flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-black uppercase text-blue-400 tracking-wider mr-1">Aktionen für diesen Kunden:</span>
                        
                        <button wire:click="openNewProjectModal" class="px-3 py-1.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>🏗️ + Neue Baustelle</span>
                        </button>

                        <button wire:click="openNewSupplementModal" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>📑 + Neuer Nachtrag (VOB/B)</span>
                        </button>

                        <button wire:click="openNewMeasurementModal" class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-500 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>📐 + Neues Aufmaß (VOB/C)</span>
                        </button>

                        <a href="/rechnungen?customer={{ $c->id }}" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>📄 + Rechnung / Angebot</span>
                        </a>

                        <button wire:click="openNewDefectModal" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>⚠️ + Mangel erfassen</span>
                        </button>

                        <button wire:click="openNewTimeEntryModal" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>⏱️ + Zeit buchen</span>
                        </button>

                        <button wire:click="openNewPlanModal" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>📁 + Plan hochladen</span>
                        </button>

                        <button wire:click="generateAiClientBriefing" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-black text-xs rounded-xl shadow-xs transition btn-press flex items-center gap-1.5 cursor-pointer">
                            <span>🤖 KI-Dossier</span>
                        </button>
                    </div>
                </div>

                <!-- KPI SUMMARY BAR -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 p-3 bg-slate-50 border-b border-slate-200 shrink-0 text-xs">
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Baustellen</span>
                        <p class="text-base font-black text-slate-900 mt-0.5">{{ $c->projects->count() }}</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Umsatz Rechnungen</span>
                        <p class="text-base font-black text-blue-600 mt-0.5 tabular-nums">{{ number_format($c->invoices->sum('total_net'), 2, ',', '.') }} €</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Nachträge (VOB/B)</span>
                        <p class="text-base font-black text-indigo-600 mt-0.5 tabular-nums">{{ number_format($c->supplements->sum('amount_net'), 2, ',', '.') }} €</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Offene Mängel</span>
                        <p class="text-base font-black text-rose-600 mt-0.5">{{ $c->defects->where('status', '!=', 'behoben')->count() }}</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Geleistete Stunden</span>
                        <p class="text-base font-black text-emerald-600 mt-0.5 tabular-nums">{{ number_format($c->timeEntries->sum('hours'), 1, ',', '.') }} h</p>
                    </div>
                </div>

                <!-- TABS NAVIGATION -->
                <div class="shrink-0 overflow-x-auto whitespace-nowrap border-b border-slate-200 bg-white px-4">
                    <div class="flex items-center gap-1 py-1 text-xs">
                        <button wire:click="$set('activeDetailTab', 'overview')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'overview' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>📋 360° Cockpit & Stammdaten</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'projects')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'projects' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>🏢 Baustellen ({{ $c->projects->count() }})</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'supplements')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'supplements' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>📑 Nachträge (VOB/B) ({{ $c->supplements->count() }})</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'measurements')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'measurements' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>📐 Aufmaßblätter ({{ $c->measurements->count() }})</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'invoices')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'invoices' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>📄 Rechnungen & Angebote</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'defects')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'defects' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>⚠️ Mängel ({{ $c->defects->count() }})</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'times')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'times' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>⏱️ Zeiterfassung</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'ai_dossier')" class="py-2.5 px-3 font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'ai_dossier' ? 'border-purple-600 text-purple-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>🤖 KI-Dossier</span>
                        </button>
                    </div>
                </div>

                <!-- TAB BODY (SCROLLABLE) -->
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-6">

                    <!-- TAB 1: 360° COCKPIT & STAMMDATEN -->
                    @if ($activeDetailTab === 'overview')
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                            
                            <!-- Master Data Box (7 cols) -->
                            <div class="lg:col-span-7 bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4">
                                <div class="flex items-center justify-between border-b border-slate-200/60 pb-3">
                                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Kontaktdaten & Stammdaten</h4>
                                    @if (!$isDetailEditing)
                                        <button wire:click="toggleDetailEdit" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1 cursor-pointer">
                                            <span>✏️ Bearbeiten</span>
                                        </button>
                                    @endif
                                </div>
                                
                                @if ($isDetailEditing)
                                    <div class="space-y-3 text-xs bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Kategorie</label>
                                                <select wire:model="detailForm.type" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-xs font-semibold text-slate-900">
                                                    <option value="kunde">👤 Privatkunde</option>
                                                    <option value="hausverwaltung">🏢 Hausverwaltung (WEG)</option>
                                                    <option value="bautraeger">🏗️ Bauträger</option>
                                                    <option value="subunternehmer">🛠️ Subunternehmer (§13b)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Firma / Unternehmen</label>
                                                <input wire:model="detailForm.company_name" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-semibold">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Anrede</label>
                                                <select wire:model="detailForm.salutation" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs">
                                                    <option value="Herr">Herr</option>
                                                    <option value="Frau">Frau</option>
                                                    <option value="Firma">Firma</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Vorname</label>
                                                <input wire:model="detailForm.first_name" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Nachname</label>
                                                <input wire:model="detailForm.last_name" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">E-Mail</label>
                                                <input wire:model="detailForm.email" type="email" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Telefon</label>
                                                <input wire:model="detailForm.phone" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Mobil</label>
                                                <input wire:model="detailForm.mobile" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">USt-IdNr.</label>
                                                <input wire:model="detailForm.vat_id" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-mono font-medium">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1">Straße & Hausnummer</label>
                                            <input wire:model="detailForm.street" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                        </div>

                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">PLZ</label>
                                                <input wire:model="detailForm.zip" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                            </div>
                                            <div class="col-span-2">
                                                <label class="block font-bold text-slate-700 mb-1">Ort</label>
                                                <input wire:model="detailForm.city" type="text" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs font-medium">
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-2 pt-2">
                                            <button type="button" wire:click="toggleDetailEdit" class="px-3 py-1.5 bg-slate-100 text-slate-700 font-bold rounded-lg text-xs">Abbrechen</button>
                                            <button type="button" wire:click="saveDetailStammdaten" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs shadow-xs">💾 Speichern</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="space-y-3 text-xs">
                                        <div>
                                            <span class="text-slate-400 font-medium block">Firma / Unternehmen:</span>
                                            <span class="font-black text-slate-900 text-sm">{{ $c->company_name ?: '— (Privatperson)' }}</span>
                                        </div>

                                        <div>
                                            <span class="text-slate-400 font-medium block">Ansprechpartner:</span>
                                            <span class="font-bold text-slate-900">{{ $c->salutation }} {{ $c->first_name }} {{ $c->last_name }}</span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/60">
                                            <div>
                                                <span class="text-slate-400 font-medium block">E-Mail:</span>
                                                @if ($c->email)
                                                    <a href="mailto:{{ $c->email }}" class="font-bold text-blue-600 hover:underline block truncate">{{ $c->email }}</a>
                                                @else
                                                    <span class="text-slate-400 italic">Nicht angegeben</span>
                                                @endif
                                            </div>

                                            <div>
                                                <span class="text-slate-400 font-medium block">Telefon:</span>
                                                @if ($c->phone || $c->mobile)
                                                    <a href="tel:{{ $c->phone ?: $c->mobile }}" class="font-bold text-slate-900 hover:underline">{{ $c->phone ?: $c->mobile }}</a>
                                                @else
                                                    <span class="text-slate-400 italic">Nicht angegeben</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="pt-2 border-t border-slate-200/60">
                                            <span class="text-slate-400 font-medium block">Anschrift:</span>
                                            <p class="font-bold text-slate-900 mt-0.5">
                                                {{ $c->street ?: 'Keine Straße angegeben' }}<br>
                                                {{ $c->zip }} {{ $c->city }}
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Notes & Diary Journal (5 cols) -->
                            <div class="lg:col-span-5 bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4 flex flex-col justify-between">
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center justify-between">
                                        <span>📝 Notizbuch & Telefon-Journal</span>
                                    </h4>

                                    <div class="mt-3 space-y-2 bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                                        <label class="block text-[11px] font-bold text-slate-700">+ Neue Notiz erfassen:</label>
                                        <textarea wire:model="newNoteText" rows="2" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg p-2 text-xs placeholder-slate-400 focus:border-blue-600 focus:outline-none" placeholder="z. B. Telefonat wegen Abnahmetermin am Dienstag..."></textarea>
                                        <button type="button" wire:click="addQuickNote" class="w-full py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-lg transition shadow-xs cursor-pointer btn-press">
                                            📌 Notiz mit Zeitstempel anfügen
                                        </button>
                                    </div>

                                    <div class="mt-4 space-y-2">
                                        <div class="flex justify-between items-center">
                                            <span class="text-[11px] font-bold text-slate-500 uppercase">Journal-Historie:</span>
                                            <button type="button" wire:click="saveNotesOnly" class="text-[11px] font-bold text-blue-600 hover:underline cursor-pointer">
                                                💾 Speichern
                                            </button>
                                        </div>
                                        <textarea wire:model="detailForm.notes" rows="6" class="w-full bg-white border border-slate-300 rounded-xl p-3 text-xs text-slate-800 leading-relaxed font-sans focus:outline-none focus:border-blue-600" placeholder="Noch keine Notizen hinterlegt..."></textarea>
                                    </div>
                                </div>
                            </div>

                        </div>
                    @endif

                    <!-- TAB 2: BAUSTELLEN DES KUNDEN (WITH IN-CONTEXT ACTIONS) -->
                    @if ($activeDetailTab === 'projects')
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                <div>
                                    <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                                        Baustellen & Bauvorhaben von {{ $c->display_name }} ({{ $c->projects->count() }})
                                    </h4>
                                    <p class="text-xs text-slate-500">Direkt aus jeder Baustelle Nachträge, Aufmaße, Mängel & Zeiten steuern.</p>
                                </div>
                                <button wire:click="openNewProjectModal" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-xs cursor-pointer btn-press flex items-center gap-1.5">
                                    <span>➕ Neue Baustelle anlegen</span>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @forelse ($c->projects as $project)
                                    <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-5 space-y-4 hover:border-blue-300 transition shadow-2xs">
                                        <div class="flex justify-between items-start gap-2">
                                            <div>
                                                <span class="text-[10px] font-bold uppercase text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100">
                                                    {{ $project->work_type ?: 'Bauvorhaben' }}
                                                </span>
                                                <h5 class="text-base font-black text-slate-900 mt-1 line-clamp-1">{{ $project->name }}</h5>
                                                <p class="text-xs text-slate-500 font-medium">📍 {{ $project->location ?: 'Keine Adresse hinterlegt' }}</p>
                                            </div>
                                            @php
                                                $statusColor = match($project->status) {
                                                    'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                    'completed' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                    'paused' => 'bg-amber-100 text-amber-800 border-amber-200',
                                                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                                                };
                                            @endphp
                                            <span class="px-2.5 py-0.5 text-[10px] font-black uppercase rounded-full border {{ $statusColor }}">
                                                {{ $project->status ?: 'Aktiv' }}
                                            </span>
                                        </div>

                                        <!-- Sub-Counts -->
                                        <div class="grid grid-cols-4 gap-1.5 text-center text-xs">
                                            <div class="bg-white p-2 rounded-xl border border-slate-200">
                                                <span class="text-[9px] text-slate-400 font-bold block">Nachträge</span>
                                                <span class="font-black text-indigo-700">{{ $project->supplements->count() }}</span>
                                            </div>
                                            <div class="bg-white p-2 rounded-xl border border-slate-200">
                                                <span class="text-[9px] text-slate-400 font-bold block">Aufmaße</span>
                                                <span class="font-black text-cyan-700">{{ $project->measurements->count() }}</span>
                                            </div>
                                            <div class="bg-white p-2 rounded-xl border border-slate-200">
                                                <span class="text-[9px] text-slate-400 font-bold block">Mängel</span>
                                                <span class="font-black text-rose-700">{{ $project->defects->count() }}</span>
                                            </div>
                                            <div class="bg-white p-2 rounded-xl border border-slate-200">
                                                <span class="text-[9px] text-slate-400 font-bold block">Pläne</span>
                                                <span class="font-black text-blue-700">{{ $project->plans->count() }}</span>
                                            </div>
                                        </div>

                                        <!-- DIRECT IN-PROJECT ACTIONS -->
                                        <div class="pt-3 border-t border-slate-200/70 flex flex-wrap items-center gap-1.5">
                                            <button wire:click="openNewSupplementModal('{{ $project->id }}')" class="px-2.5 py-1 bg-white hover:bg-indigo-50 text-indigo-700 font-bold text-[11px] rounded-lg border border-indigo-200 cursor-pointer btn-press">
                                                📑 + Nachtrag
                                            </button>
                                            <button wire:click="openNewMeasurementModal('{{ $project->id }}')" class="px-2.5 py-1 bg-white hover:bg-cyan-50 text-cyan-700 font-bold text-[11px] rounded-lg border border-cyan-200 cursor-pointer btn-press">
                                                📐 + Aufmaß
                                            </button>
                                            <button wire:click="openNewDefectModal('{{ $project->id }}')" class="px-2.5 py-1 bg-white hover:bg-rose-50 text-rose-700 font-bold text-[11px] rounded-lg border border-rose-200 cursor-pointer btn-press">
                                                ⚠️ + Mangel
                                            </button>
                                            <button wire:click="openNewTimeEntryModal('{{ $project->id }}')" class="px-2.5 py-1 bg-white hover:bg-amber-50 text-amber-700 font-bold text-[11px] rounded-lg border border-amber-200 cursor-pointer btn-press">
                                                ⏱️ + Zeit
                                            </button>
                                            <button wire:click="openNewPlanModal('{{ $project->id }}')" class="px-2.5 py-1 bg-white hover:bg-blue-50 text-blue-700 font-bold text-[11px] rounded-lg border border-blue-200 cursor-pointer btn-press">
                                                📁 + Plan
                                            </button>
                                            <a href="/dashboard" wire:navigate class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px] rounded-lg cursor-pointer ml-auto">
                                                📊 Cockpit ↗
                                            </a>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-full py-12 bg-slate-50 border border-slate-200 rounded-3xl text-center space-y-3">
                                        <div class="text-3xl">🏗️</div>
                                        <h4 class="font-black text-slate-900 text-sm">Noch keine Baustellen für diesen Kunden</h4>
                                        <p class="text-xs text-slate-500">Legen Sie direkt die erste Baustelle für {{ $c->display_name }} an.</p>
                                        <button wire:click="openNewProjectModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                                            + Erste Baustelle anlegen
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <!-- TAB 3: NACHTRÄGE (VOB/B § 2) -->
                    @if ($activeDetailTab === 'supplements')
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                <div>
                                    <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                                        Nachtragsmanagement (VOB/B § 2) für {{ $c->display_name }}
                                    </h4>
                                    <p class="text-xs text-slate-500">Gesamtvolumen: <strong class="text-slate-900">{{ number_format($c->supplements->sum('amount_net'), 2, ',', '.') }} € Netto</strong></p>
                                </div>
                                <button wire:click="openNewSupplementModal" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-xs cursor-pointer btn-press flex items-center gap-1.5">
                                    <span>➕ Neuen Nachtrag erfassen</span>
                                </button>
                            </div>

                            <div class="bg-white border border-slate-200 rounded-2xl overflow-x-auto shadow-2xs">
                                <table class="w-full text-left text-xs divide-y divide-slate-100">
                                    <thead class="bg-slate-50 text-slate-600 font-extrabold uppercase text-[10px]">
                                        <tr>
                                            <th class="p-3">Nachtrag</th>
                                            <th class="p-3">Baustelle</th>
                                            <th class="p-3">Titel & Begründung</th>
                                            <th class="p-3 text-right">Netto (€)</th>
                                            <th class="p-3 text-center">Status</th>
                                            <th class="p-3 text-right">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @forelse ($c->supplements as $sup)
                                            <tr class="hover:bg-slate-50/60">
                                                <td class="p-3 font-mono font-bold text-slate-900">{{ $sup->supplement_number }}</td>
                                                <td class="p-3 font-bold text-slate-800">{{ $sup->project?->name ?: '—' }}</td>
                                                <td class="p-3">
                                                    <span class="font-bold text-slate-900 block">{{ $sup->title }}</span>
                                                    <span class="text-[10px] text-slate-400">{{ $sup->reason_label }}</span>
                                                </td>
                                                <td class="p-3 text-right font-black text-slate-900 tabular-nums">{{ number_format($sup->amount_net, 2, ',', '.') }} €</td>
                                                <td class="p-3 text-center">
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase {{ $sup->status_badge }}">
                                                        {{ $sup->status }}
                                                    </span>
                                                </td>
                                                <td class="p-3 text-right">
                                                    <a href="/nachtraege/{{ $sup->id }}/pdf" target="_blank" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg font-bold text-[11px] border border-indigo-200">
                                                        📄 PDF
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="p-8 text-center text-xs text-slate-500 italic">
                                                    Noch keine Nachträge für diesen Auftraggeber hinterlegt.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 4: AUFMASSBLÄTTER (VOB/C) -->
                    @if ($activeDetailTab === 'measurements')
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                <div>
                                    <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                                        Digitale Aufmaßblätter (VOB/C) für {{ $c->display_name }}
                                    </h4>
                                    <p class="text-xs text-slate-500">Mengenermittlungen mit Raummaßen und automatischem VOB-Abzug.</p>
                                </div>
                                <button wire:click="openNewMeasurementModal" class="px-3.5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-extrabold text-xs rounded-xl shadow-xs cursor-pointer btn-press flex items-center gap-1.5">
                                    <span>➕ Neues Aufmaßblatt anlegen</span>
                                </button>
                            </div>

                            <div class="bg-white border border-slate-200 rounded-2xl overflow-x-auto shadow-2xs">
                                <table class="w-full text-left text-xs divide-y divide-slate-100">
                                    <thead class="bg-slate-50 text-slate-600 font-extrabold uppercase text-[10px]">
                                        <tr>
                                            <th class="p-3">Aufmaß-Nr.</th>
                                            <th class="p-3">Baustelle</th>
                                            <th class="p-3">Titel / Bereich</th>
                                            <th class="p-3">Datum</th>
                                            <th class="p-3 text-right">Summe Netto (€)</th>
                                            <th class="p-3 text-right">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @forelse ($c->measurements as $mea)
                                            <tr class="hover:bg-slate-50/60">
                                                <td class="p-3 font-mono font-bold text-slate-900">{{ $mea->measurement_number }}</td>
                                                <td class="p-3 font-bold text-slate-800">{{ $mea->project?->name ?: '—' }}</td>
                                                <td class="p-3 font-medium text-slate-900">{{ $mea->title }} ({{ $mea->location_area ?: 'Gesamt' }})</td>
                                                <td class="p-3 text-slate-600">{{ $mea->measurement_date?->format('d.m.Y') }}</td>
                                                <td class="p-3 text-right font-black text-cyan-900 tabular-nums">{{ number_format($mea->total_amount_net, 2, ',', '.') }} €</td>
                                                <td class="p-3 text-right">
                                                    <a href="/aufmass/{{ $mea->id }}/pdf" target="_blank" class="px-2.5 py-1 bg-cyan-50 text-cyan-700 hover:bg-cyan-100 rounded-lg font-bold text-[11px] border border-cyan-200">
                                                        📐 PDF
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="p-8 text-center text-xs text-slate-500 italic">
                                                    Noch keine Aufmaßblätter für diesen Kunden erfasst.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 5: RECHNUNGEN & ANGEBOTE -->
                    @if ($activeDetailTab === 'invoices')
                        <div class="space-y-6">
                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider">Ausgangsrechnungen ({{ $c->invoices->count() }})</h4>
                                <a href="/rechnungen?customer={{ $c->id }}" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl shadow-xs cursor-pointer btn-press">
                                    + Neue Rechnung erstellen
                                </a>
                            </div>

                            <div class="bg-white border border-slate-200 rounded-2xl overflow-x-auto shadow-2xs">
                                <table class="w-full text-left text-xs divide-y divide-slate-100">
                                    <thead class="bg-slate-50 text-slate-600 font-extrabold uppercase text-[10px]">
                                        <tr>
                                            <th class="p-3">Rechnungs-Nr.</th>
                                            <th class="p-3">Datum</th>
                                            <th class="p-3">Typ</th>
                                            <th class="p-3 text-right">Netto (€)</th>
                                            <th class="p-3 text-right">Brutto (€)</th>
                                            <th class="p-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($c->invoices as $inv)
                                            <tr class="hover:bg-slate-50">
                                                <td class="p-3 font-bold text-slate-900">{{ $inv->invoice_number }}</td>
                                                <td class="p-3 text-slate-600">{{ date('d.m.Y', strtotime($inv->invoice_date)) }}</td>
                                                <td class="p-3 font-semibold text-slate-700">{{ $inv->type ?? 'Abschlussrechnung' }}</td>
                                                <td class="p-3 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($inv->total_net, 2, ',', '.') }} €</td>
                                                <td class="p-3 text-right font-black text-blue-600 tabular-nums">{{ number_format($inv->total_gross, 2, ',', '.') }} €</td>
                                                <td class="p-3 text-center">
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">
                                                        Bezahlt
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="p-8 text-center text-xs text-slate-500 italic">
                                                    Keine Rechnungen vorhanden.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 6: MÄNGEL & QUALITÄT -->
                    @if ($activeDetailTab === 'defects')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider">Mängelmanagement & Gewährleistung</h4>
                                    <p class="text-xs text-slate-500">Mängelrügen und Abnahmeprotokolle auf den Baustellen von {{ $c->display_name }}</p>
                                </div>
                                <button wire:click="openNewDefectModal" class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs rounded-xl shadow-xs cursor-pointer btn-press flex items-center gap-1.5">
                                    <span>➕ Neuen Mangel erfassen</span>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @forelse ($c->defects as $defect)
                                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                                        <div class="flex justify-between items-start gap-2">
                                            <h5 class="font-black text-slate-900 text-sm line-clamp-1">{{ $defect->title }}</h5>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-800">
                                                {{ $defect->priority }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-500">📍 {{ $defect->location ?: 'Baustelle' }} ({{ $defect->project?->name }})</p>
                                        <p class="text-xs text-slate-700 font-medium bg-white p-2.5 rounded-xl border border-slate-200">
                                            {{ $defect->description }}
                                        </p>
                                        @if ($defect->deadline)
                                            <p class="text-[11px] text-amber-700 font-bold">⏳ Frist: {{ date('d.m.Y', strtotime($defect->deadline)) }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="col-span-full py-8 text-center text-xs text-slate-500 italic bg-slate-50 rounded-2xl border border-slate-200">
                                        Keine offenen Mängel auf den Baustellen dieses Kunden erfasst.
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <!-- TAB 7: ZEITERFASSUNG -->
                    @if ($activeDetailTab === 'times')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="text-sm font-black text-slate-900 uppercase tracking-wider">Zeiterfassung auf Kundenbaustellen</h4>
                                    <p class="text-xs text-slate-500">Geleistete Arbeitsstunden der Monteure und Bauleiter</p>
                                </div>
                                <button wire:click="openNewTimeEntryModal" class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs rounded-xl shadow-xs cursor-pointer btn-press flex items-center gap-1.5">
                                    <span>➕ Arbeitszeit buchen</span>
                                </button>
                            </div>

                            <div class="bg-white border border-slate-200 rounded-2xl overflow-x-auto shadow-2xs">
                                <table class="w-full text-left text-xs divide-y divide-slate-100">
                                    <thead class="bg-slate-50 text-slate-600 font-extrabold uppercase text-[10px]">
                                        <tr>
                                            <th class="p-3">Datum</th>
                                            <th class="p-3">Mitarbeiter</th>
                                            <th class="p-3">Baustelle</th>
                                            <th class="p-3">Tätigkeit</th>
                                            <th class="p-3 text-right">Stunden</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($c->timeEntries as $time)
                                            <tr class="hover:bg-slate-50">
                                                <td class="p-3 font-bold text-slate-900">{{ $time->entry_date?->format('d.m.Y') }}</td>
                                                <td class="p-3 font-semibold text-slate-800">👤 {{ $time->user?->name ?: 'Mitarbeiter' }}</td>
                                                <td class="p-3 text-slate-700">{{ $time->project?->name }}</td>
                                                <td class="p-3 text-slate-600">{{ $time->description ?: ucfirst($time->activity_type) }}</td>
                                                <td class="p-3 text-right font-black text-amber-700 tabular-nums">{{ number_format($time->hours, 2, ',', '.') }} Std.</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="p-8 text-center text-xs text-slate-500 italic">
                                                    Noch keine Arbeitsstunden auf den Baustellen dieses Kunden erfasst.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 8: KI-BAULEITER DOSSIER -->
                    @if ($activeDetailTab === 'ai_dossier')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="text-sm font-black text-purple-950 uppercase tracking-wider flex items-center gap-2">
                                        <span>🤖 KI-Bauleiter 360° Kunden-Dossier</span>
                                    </h4>
                                    <p class="text-xs text-slate-500">Automatische Analyse aller Baustellen, Nachträge, Mängel und Erlöspotenziale.</p>
                                </div>
                                <button wire:click="generateAiClientBriefing" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs rounded-xl shadow-xs cursor-pointer btn-press flex items-center gap-1.5">
                                    <span>🔄 Neu analysieren</span>
                                </button>
                            </div>

                            <div class="bg-gradient-to-br from-purple-50 via-slate-50 to-white border border-purple-200/80 rounded-2xl p-6 shadow-sm space-y-4 text-xs leading-relaxed text-slate-800">
                                @if ($isGeneratingAiBriefing)
                                    <div class="py-12 text-center space-y-3">
                                        <div class="text-4xl animate-bounce">🤖</div>
                                        <p class="font-bold text-slate-900">KI-Chefbauleiter analysiert Kunden- und Baustellendaten...</p>
                                    </div>
                                @elseif ($aiBriefingText)
                                    <div class="prose prose-sm max-w-none text-slate-900 whitespace-pre-wrap font-sans">
                                        {!! nl2br(e($aiBriefingText)) !!}
                                    </div>
                                @else
                                    <div class="py-8 text-center space-y-2">
                                        <p class="font-bold text-slate-700">Klicken Sie auf "Neu analysieren", um ein vollständiges KI-Dossier zu generieren.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>

                <!-- POPUP MODAL FOOTER -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center shrink-0">
                    <span class="text-xs text-slate-500 font-medium">💡 Alle Änderungen und Aktionen werden sofort in Echtzeit mit den Baustellen verknüpft.</span>
                    <button wire:click="closeDetailModal" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-extrabold rounded-xl shadow-xs cursor-pointer btn-press">
                        Schließen
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- SUB-MODAL 1: NEUE BAUSTELLE FÜR KUNDEN ANLEGEN                            -->
    <!-- ========================================================================= -->
    @if ($showNewProjectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">🏗️ Neue Baustelle für Kunde anlegen</h3>
                        <p class="text-xs text-slate-500">Auftraggeber: {{ $this->selectedContact?->display_name }}</p>
                    </div>
                    <button wire:click="$set('showNewProjectModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveProjectForContact" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustellen-Bezeichnung / Projektname *</label>
                        <input wire:model="newProjectName" type="text" placeholder="z. B. WEG Ingolstädter Str. 11 - Tiefgaragenabdichtung" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20" required>
                    </div>

                    <div class="flex justify-between items-center pt-1">
                        <label class="font-bold text-slate-700">Adresse der Baustelle</label>
                        <button type="button" wire:click="copyContactAddressToProject" class="text-[11px] text-blue-600 font-bold hover:underline cursor-pointer">
                            📍 Adresse des Kunden übernehmen
                        </button>
                    </div>

                    <div>
                        <input wire:model="newProjectStreet" type="text" placeholder="Straße & Hausnummer" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <input wire:model="newProjectZip" type="text" placeholder="PLZ" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div class="col-span-2">
                            <input wire:model="newProjectCity" type="text" placeholder="Ort" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Gewerk / Leistungsart</label>
                            <input wire:model="newProjectWorkType" type="text" placeholder="z.B. Abdichtung / Sanierung" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Geplantes Budget Netto (€)</label>
                            <input wire:model="newProjectPlannedBudget" type="number" step="100" placeholder="z.B. 45000" class="w-full bg-white border border-slate-300 text-slate-900 font-bold tabular-nums rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Status</label>
                            <select wire:model="newProjectStatus" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                                <option value="active">Aktiv</option>
                                <option value="draft">Entwurf</option>
                                <option value="paused">Pausiert</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Start-KW</label>
                            <input wire:model="newProjectStartWeek" type="number" min="1" max="53" class="w-full bg-white border border-slate-300 text-slate-900 font-bold text-center rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">End-KW</label>
                            <input wire:model="newProjectEndWeek" type="number" min="1" max="53" class="w-full bg-white border border-slate-300 text-slate-900 font-bold text-center rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewProjectModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md shadow-blue-500/20 cursor-pointer btn-press">
                            Baustelle anlegen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- SUB-MODAL 2: NEUER NACHTRAG FÜR KUNDENBAUSTELLE                           -->
    <!-- ========================================================================= -->
    @if ($showNewSupplementModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">📑 Neuen Nachtrag (VOB/B § 2) erfassen</h3>
                        <p class="text-xs text-slate-500">Auftraggeber: {{ $this->selectedContact?->display_name }}</p>
                    </div>
                    <button wire:click="$set('showNewSupplementModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveSupplementForContact" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Zugehörige Baustelle des Kunden *</label>
                        <select wire:model="supplementProjectId" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20" required>
                            @foreach ($this->selectedContact->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nachtrags-Nr. *</label>
                            <input wire:model="supplementNumber" type="text" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20" required>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Begründung (VOB/B)</label>
                            <select wire:model="supplementReason" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                                <option value="scope_change">Leistungsänderung (§ 2 Abs. 5)</option>
                                <option value="unforeseen">Unvorhergesehenes (§ 2 Abs. 6)</option>
                                <option value="client_request">Bauherren-Zusatzwunsch</option>
                                <option value="obstruction">Behinderungsanzeige</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Titel / Bezeichnung der Mehrleistung *</label>
                        <input wire:model="supplementTitle" type="text" placeholder="z. B. Zusätzliche Hohlkehle Tiefgaragenrampe" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20" required>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nettobetrag (€) *</label>
                            <input wire:model="supplementAmountNet" type="number" step="0.01" placeholder="z. B. 1850.00" class="w-full bg-white border border-slate-300 text-slate-900 font-black tabular-nums rounded-xl p-2.5 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20" required>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">MwSt.-Satz (%)</label>
                            <input wire:model="supplementVatRate" type="number" step="0.5" class="w-full bg-white border border-slate-300 text-slate-900 font-bold tabular-nums rounded-xl p-2.5 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Beschreibung / VOB-Begründung</label>
                        <textarea wire:model="supplementDescription" rows="2" placeholder="Begründung der Mehrleistung nach VOB/B..." class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-medium shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20"></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewSupplementModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold rounded-xl shadow-md shadow-indigo-500/20 cursor-pointer btn-press">
                            Nachtrag speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- SUB-MODAL 3: NEUES AUFMASSBLATT FÜR KUNDENBAUSTELLE                       -->
    <!-- ========================================================================= -->
    @if ($showNewMeasurementModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">📐 Neues Aufmaßblatt (VOB/C) anlegen</h3>
                        <p class="text-xs text-slate-500">Auftraggeber: {{ $this->selectedContact?->display_name }}</p>
                    </div>
                    <button wire:click="$set('showNewMeasurementModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveMeasurementForContact" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle des Kunden *</label>
                        <select wire:model="measurementProjectId" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20" required>
                            @foreach ($this->selectedContact->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Aufmaß-Nr. *</label>
                            <input wire:model="measurementNumber" type="text" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20" required>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Aufmaßdatum</label>
                            <input wire:model="measurementDate" type="date" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20" required>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Titel / Gewerk *</label>
                        <input wire:model="measurementTitle" type="text" placeholder="z. B. Abdichtung TG-Bodenplatte" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20" required>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bereich / Bauteil (optional)</label>
                        <input wire:model="measurementLocationArea" type="text" placeholder="z. B. 1. UG / Achse 4-8" class="w-full bg-white border border-slate-300 text-slate-900 font-medium rounded-xl p-2.5 shadow-2xs focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500/20">
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewMeasurementModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-extrabold rounded-xl shadow-md shadow-cyan-500/20 cursor-pointer btn-press">
                            Aufmaßblatt anlegen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- SUB-MODAL 4: MANGEL ERFASSEN                                              -->
    <!-- ========================================================================= -->
    @if ($showNewDefectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">⚠️ Mangel / Beanstandung erfassen</h3>
                        <p class="text-xs text-slate-500">Auftraggeber: {{ $this->selectedContact?->display_name }}</p>
                    </div>
                    <button wire:click="$set('showNewDefectModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveDefectForContact" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle *</label>
                        <select wire:model="defectProjectId" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20" required>
                            @foreach ($this->selectedContact->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Titel der Beanstandung *</label>
                        <input wire:model="defectTitle" type="text" placeholder="z. B. Nachdichtung Wandanschluss TG" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20" required>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Ort / Bauteil</label>
                            <input wire:model="defectLocation" type="text" placeholder="z. B. TG 1. OG Westwand" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Priorität</label>
                            <select wire:model="defectPriority" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20">
                                <option value="niedrig">Niedrig</option>
                                <option value="mittel">Mittel</option>
                                <option value="hoch">Hoch</option>
                                <option value="kritisch">Kritisch / Baustopp</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Frist zur Beseitigung</label>
                        <input wire:model="defectDeadline" type="date" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mängelbeschreibung *</label>
                        <textarea wire:model="defectDescription" rows="3" placeholder="Genaue Beschreibung des Mangels..." class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-medium shadow-2xs focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20" required></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewDefectModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-extrabold rounded-xl shadow-md shadow-rose-500/20 cursor-pointer btn-press">
                            Mangel erfassen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- SUB-MODAL 5: ZEITERFASSUNG / STUNDENZETTEL BUCHEN                         -->
    <!-- ========================================================================= -->
    @if ($showNewTimeEntryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">⏱️ Arbeitszeit buchen</h3>
                        <p class="text-xs text-slate-500">Auftraggeber: {{ $this->selectedContact?->display_name }}</p>
                    </div>
                    <button wire:click="$set('showNewTimeEntryModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveTimeEntryForContact" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle *</label>
                        <select wire:model="timeProjectId" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20" required>
                            @foreach ($this->selectedContact->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Mitarbeiter *</label>
                            <select wire:model="timeUserId" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20" required>
                                @foreach ($this->users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Datum *</label>
                            <input wire:model="timeDate" type="date" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Geleistete Stunden *</label>
                            <input wire:model="timeHours" type="number" step="0.25" placeholder="8.0" class="w-full bg-white border border-slate-300 text-slate-900 font-black tabular-nums rounded-xl p-2.5 shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20" required>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tätigkeitsart</label>
                            <select wire:model="timeActivity" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20">
                                <option value="construction">Ausführung Bauleistung</option>
                                <option value="travel">Anfahrt / Rüstzeit</option>
                                <option value="regie">Regiearbeit nach Aufwand</option>
                                <option value="warranty">Nachbesserung</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tätigkeitsbericht / Notiz</label>
                        <textarea wire:model="timeDescription" rows="2" placeholder="z. B. Abdichtungsarbeiten TG-Rampe..." class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-medium shadow-2xs focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20"></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewTimeEntryModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-extrabold rounded-xl shadow-md shadow-amber-500/20 cursor-pointer btn-press">
                            Zeit erfassen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- SUB-MODAL 6: BAUPLAN HOCHLADEN                                            -->
    <!-- ========================================================================= -->
    @if ($showNewPlanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-lg w-full shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">📁 Bauplan hochladen</h3>
                        <p class="text-xs text-slate-500">Auftraggeber: {{ $this->selectedContact?->display_name }}</p>
                    </div>
                    <button wire:click="$set('showNewPlanModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="savePlanForContact" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle *</label>
                        <select wire:model="planProjectId" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20" required>
                            @foreach ($this->selectedContact->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Plannummer</label>
                            <input wire:model="planNumber" type="text" placeholder="z. B. AR-101" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Revisions-Index *</label>
                            <input wire:model="planRevisionIndex" type="text" placeholder="z. B. Index A" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20" required>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Plan-Bezeichnung / Titel *</label>
                        <input wire:model="planTitle" type="text" placeholder="z. B. Grundriss TG-Ebene 1" class="w-full bg-white border border-slate-300 text-slate-900 font-bold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20" required>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kategorie</label>
                            <select wire:model="planCategory" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                                <option value="architecture">Architektur & Grundriss</option>
                                <option value="structural">Statik & Bewehrung</option>
                                <option value="tga">TGA / Haustechnik</option>
                                <option value="fire_safety">Brandschutz</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Plandatum</label>
                            <input wire:model="planDate" type="date" class="w-full bg-white border border-slate-300 text-slate-900 font-semibold rounded-xl p-2.5 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Datei-Upload (PDF / Bild) *</label>
                        <input wire:model="planFileUpload" type="file" accept=".pdf,.png,.jpg,.jpeg,.dwg" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2 shadow-2xs focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white cursor-pointer" required>
                        <x-input-error :messages="$errors->get('planFileUpload')" class="mt-1" />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewPlanModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">Abbrechen</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-md shadow-blue-500/20 cursor-pointer btn-press">
                            Plan hochladen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Standalone Create / Edit Contact Modal -->
    @if ($showContactModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ $editingContactId ? 'Kontakt bearbeiten' : 'Neuen Kontakt / Auftraggeber anlegen' }}
                    </h3>
                    <button wire:click="$set('showContactModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <form wire:submit="saveContact" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Typ / Kategorie</label>
                        <select wire:model="type" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 font-bold focus:border-blue-600 focus:outline-none">
                            <option value="kunde">👤 Privatkunde</option>
                            <option value="hausverwaltung">🏢 Hausverwaltung (WEG)</option>
                            <option value="bautraeger">🏗️ Bauträger / Bauunternehmen</option>
                            <option value="subunternehmer">🛠️ Subunternehmer / Partner (§13b)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Firmenname / Bezeichnung</label>
                        <input wire:model="companyName" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 font-semibold focus:border-blue-600 focus:outline-none" placeholder="z. B. Ingolstädter Hausverwaltung GmbH">
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Anrede</label>
                            <select wire:model="salutation" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none">
                                <option value="Herr">Herr</option>
                                <option value="Frau">Frau</option>
                                <option value="Firma">Firma</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Vorname</label>
                            <input wire:model="firstName" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="Max">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nachname</label>
                            <input wire:model="lastName" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="Mustermann">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">E-Mail</label>
                            <input wire:model="email" type="email" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="info@beispiel.de">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Telefon</label>
                            <input wire:model="phone" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="0841 123456">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Straße & Hausnummer</label>
                        <input wire:model="street" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="Münchner Str. 10">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">PLZ</label>
                            <input wire:model="zip" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="85051">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ort</label>
                            <input wire:model="city" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="Ingolstadt">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">USt-IdNr. / Steuernummer (§13b)</label>
                        <input wire:model="vatId" type="text" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="DE123456789">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notizen</label>
                        <textarea wire:model="notes" rows="3" class="w-full bg-white border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:border-blue-600 focus:outline-none" placeholder="Zusätzliche Infos, Ansprechpartner etc..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showContactModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold cursor-pointer">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10 cursor-pointer">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- CSV / EXCEL IMPORT MODAL -->
    @if ($showImportModal)
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                
                <div class="p-6 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white flex justify-between items-start relative overflow-hidden">
                    <div class="space-y-1 relative z-10">
                        <h3 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                            <span>📥 Kontakte aus CSV / Excel importieren</span>
                        </h3>
                        <p class="text-xs text-slate-300">Laden Sie eine CSV-Datei hoch, um Kunden gesammelt zu importieren.</p>
                    </div>
                    <button wire:click="closeImportModal" class="p-2 text-slate-400 hover:text-white rounded-full bg-white/10 hover:bg-white/20 transition cursor-pointer relative z-10">
                        ✕
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6">
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                            <div>
                                <span class="text-xs font-bold text-slate-900 block">💡 Benötigen Sie eine Mustervorlage?</span>
                                <span class="text-[11px] text-slate-500">Laden Sie unsere fertige CSV-Struktur mit Beispielzeilen herunter.</span>
                            </div>
                            <button wire:click="downloadSampleCsv" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                <span>📑 Muster-CSV herunterladen</span>
                            </button>
                        </div>

                        <div class="border-2 border-dashed border-slate-300 hover:border-blue-500 rounded-2xl p-8 text-center bg-slate-50/50 hover:bg-blue-50/30 transition cursor-pointer relative">
                            <input type="file" wire:model="importFile" accept=".csv,.txt" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            
                            <div class="space-y-2 pointer-events-none">
                                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mx-auto text-xl font-bold">
                                    📂
                                </div>
                                <p class="text-sm font-bold text-slate-800">Klicken oder CSV-Datei hierhin ziehen</p>
                                <p class="text-xs text-slate-500">Unterstützt `.csv` und `.txt` (Semikolon `;` oder Komma `,` getrennt)</p>
                            </div>

                            <div wire:loading wire:target="importFile" class="mt-3 text-xs text-blue-600 font-bold animate-pulse">
                                ⏳ Datei wird analysiert...
                            </div>
                        </div>
                    </div>

                    @if (count($parsedImportRows) > 0)
                        <div class="space-y-3 pt-2">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold uppercase text-slate-900 tracking-wider flex items-center gap-2">
                                    <span>Vorschau der erkannten Kontakte</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-200">
                                        {{ count($parsedImportRows) }} Datensätze
                                    </span>
                                </h4>
                            </div>

                            <div class="border border-slate-200 rounded-2xl overflow-hidden max-h-64 overflow-y-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-900 text-slate-200 text-[11px] font-bold uppercase tracking-wider">
                                            <th class="p-3">Typ</th>
                                            <th class="p-3">Firma / Name</th>
                                            <th class="p-3">E-Mail</th>
                                            <th class="p-3">Telefon</th>
                                            <th class="p-3">Ort</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                        @foreach ($parsedImportRows as $row)
                                            <tr class="hover:bg-slate-50">
                                                <td class="p-3">
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                                        {{ $row['type'] }}
                                                    </span>
                                                </td>
                                                <td class="p-3 font-bold text-slate-900">
                                                    {{ $row['company_name'] ?: ($row['first_name'] . ' ' . $row['last_name']) }}
                                                </td>
                                                <td class="p-3 text-blue-600 truncate max-w-[150px]">{{ $row['email'] ?: '—' }}</td>
                                                <td class="p-3">{{ $row['phone'] ?: ($row['mobile'] ?: '—') }}</td>
                                                <td class="p-3">{{ $row['zip'] }} {{ $row['city'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="p-5 bg-slate-50 border-t border-slate-200 flex justify-between items-center">
                    <button type="button" wire:click="closeImportModal" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl text-xs font-bold cursor-pointer">
                        Abbrechen
                    </button>

                    @if (count($parsedImportRows) > 0)
                        <button type="button" wire:click="executeImport" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 cursor-pointer flex items-center gap-1.5">
                            <span>🚀 {{ count($parsedImportRows) }} Kontakte jetzt importieren</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
