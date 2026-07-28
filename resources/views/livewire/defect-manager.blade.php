<?php

use Livewire\Volt\Component;
use App\Models\Defect;
use App\Models\Project;
use App\Models\Contact;

new class extends Component {
    public bool $showModal = false;

    // Form
    public string $projectId = '';
    public string $assignedContactId = '';
    public string $title = '';
    public string $location = '';
    public string $description = '';
    public string $deadline = '';
    public string $priority = 'mittel';
    public string $status = 'offen';

    public function mount()
    {
        $this->deadline = date('Y-m-d', strtotime('+14 days'));
        $p = Project::first();
        if ($p) $this->projectId = $p->id;
    }

    public function getDefectsProperty()
    {
        return Defect::with(['project', 'assignedContact'])->latest()->get();
    }

    public function getProjectsProperty()
    {
        return Project::all();
    }

    public function getSubcontractorsProperty()
    {
        return Contact::where('type', 'subunternehmer')->get();
    }

    public function openCreateModal(?string $projId = null)
    {
        if ($projId) {
            $this->projectId = $projId;
        }
        $this->title = '';
        $this->location = '';
        $this->description = '';
        $this->showModal = true;
    }

    public function updateStatus(string $id, string $newStatus)
    {
        Defect::where('id', $id)->update(['status' => $newStatus]);
        $this->dispatch('notify', 'Status der Mängelbeseitigung aktualisiert!');
    }

    public function saveDefect()
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        Defect::create([
            'project_id' => $this->projectId,
            'assigned_contact_id' => $this->assignedContactId ?: null,
            'title' => $this->title,
            'location' => $this->location,
            'description' => $this->description,
            'deadline' => $this->deadline ?: null,
            'priority' => $this->priority,
            'status' => $this->status,
        ]);

        $this->showModal = false;
        $this->dispatch('notify', 'Mangel erfolgreich erfasst!');
    }

    // AI VOB/B Notice Generator
    public bool $showNoticeModal = false;
    public string $noticeText = '';

    // Abnahmeprotokoll (VOB/B & BGB) Generator
    public bool $showAcceptanceModal = false;
    public string $acceptanceProjectId = '';
    public string $acceptanceSubcontractorId = '';
    public string $acceptanceDate = '';
    public string $contractorName = 'BT Bautechnik UG';
    public string $contractorRepresentative = 'Geschäftsführung';
    public string $clientName = '';
    public string $clientRepresentative = 'Bauherr / Architekt';
    public string $workScopeDescription = '';
    public string $acceptanceResult = 'mit_vorbehalt'; // ohne_vorbehalt, mit_vorbehalt, verweigert
    public string $defectRemediationDeadline = '';
    public string $warrantyPeriod = '5 Jahre gemäß § 13 Abs. 4 VOB/B';
    public string $notes = '';

    public function generateNoticeLetter(string $defectId, ?\App\Services\OpenAiParserService $parser = null)
    {
        $parser = $parser ?? app(\App\Services\OpenAiParserService::class);
        $defect = Defect::with(['project', 'assignedContact'])->find($defectId);
        if (!$defect) return;

        try {
            $this->noticeText = $parser->generateDefectNoticeLetter([
                'project' => $defect->project?->name ?? 'Baustelle',
                'contact' => $defect->assignedContact?->company_name ?? $defect->assignedContact?->name ?? 'Subunternehmer',
                'title' => $defect->title,
                'location' => $defect->location,
                'description' => $defect->description,
                'deadline' => $defect->deadline ? date('d.m.Y', strtotime($defect->deadline)) : '7 Tage',
            ]);
            $this->showNoticeModal = true;
            $this->dispatch('notify', '✨ VOB/B Mängelrüge per KI erzeugt!');
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler bei Erstellung der Mängelrüge: ' . $e->getMessage());
        }
    }

    public function openAcceptanceModal(?string $projId = null)
    {
        $this->acceptanceDate = date('Y-m-d');
        $this->defectRemediationDeadline = date('Y-m-d', strtotime('+14 days'));
        $this->acceptanceSubcontractorId = '';
        
        $proj = $projId 
            ? Project::with(['contact', 'dailyLogs.contact', 'defects.assignedContact'])->find($projId) 
            : Project::with(['contact', 'dailyLogs.contact', 'defects.assignedContact'])->first();

        if ($proj) {
            $this->acceptanceProjectId = $proj->id;
            $this->loadAcceptanceProjectData($proj);
        }

        $this->showAcceptanceModal = true;
    }

    public function updatedAcceptanceProjectId($val)
    {
        $proj = Project::with(['contact', 'dailyLogs.contact', 'defects.assignedContact'])->find($val);
        if ($proj) {
            $this->loadAcceptanceProjectData($proj);
        }
    }

    public function updatedAcceptanceSubcontractorId($val)
    {
        $proj = Project::with(['contact', 'dailyLogs.contact', 'defects.assignedContact'])->find($this->acceptanceProjectId);
        if ($proj) {
            $this->loadAcceptanceProjectData($proj);
        }
    }

    public function loadAcceptanceProjectData(Project $proj)
    {
        if ($proj->contact) {
            $this->clientName = $proj->contact->company_name ?: $proj->contact->name;
            $this->clientRepresentative = $proj->contact->name;
        } else {
            $this->clientName = $proj->contact_address ?: 'Auftraggeber';
            $this->clientRepresentative = 'Bauherr / Architekt';
        }

        $setting = \App\Models\CompanySetting::first();
        if ($setting && $setting->company_name) {
            $this->contractorName = $setting->company_name;
        }
        if ($setting && $setting->ceo_name) {
            $this->contractorRepresentative = $setting->ceo_name;
        }

        // Subunternehmer Filter Check
        $subId = $this->acceptanceSubcontractorId;
        $subContact = $subId ? Contact::find($subId) : null;

        $workLines = [];
        if ($subContact) {
            $workLines[] = "Gewerk / Subunternehmer: " . $subContact->display_name;
        } elseif ($proj->work_type) {
            $workLines[] = "Gewerk / Bauauftrag: " . $proj->work_type;
        }

        $logsQuery = $proj->dailyLogs();
        if ($subId) {
            $logsQuery->where('contact_id', $subId);
        }
        $logs = $logsQuery->get();

        foreach ($logs as $log) {
            $dateFormatted = date('d.m.Y', strtotime($log->date));
            $subInfo = $log->contact ? " [" . $log->contact->display_name . "]" : "";
            $workLines[] = "• " . $dateFormatted . $subInfo . ": " . $log->work_performed;
        }

        if (empty($workLines)) {
            $this->workScopeDescription = "Vertragsgemäß erbrachte Bauleistungen für das Bauvorhaben " . $proj->name;
        } else {
            $this->workScopeDescription = implode("\n", array_slice($workLines, 0, 20));
        }

        $defectsQuery = $proj->defects();
        if ($subId) {
            $defectsQuery->where('assigned_contact_id', $subId);
        }
        $defects = $defectsQuery->get();

        $openDefectsCount = $defects->where('status', '!=', 'abgenommen')->count();
        if ($openDefectsCount > 0) {
            $this->acceptanceResult = 'mit_vorbehalt';
        } else {
            $this->acceptanceResult = 'ohne_vorbehalt';
        }
    }

    public function generatePdfBinary(string $html): string
    {
        try {
            if (class_exists(\Spatie\LaravelPdf\Facades\Pdf::class)) {
                $tempPath = storage_path('app/temp_' . \Illuminate\Support\Str::random(10) . '.pdf');
                $chromeTesting = '/Users/smc/.cache/puppeteer/chrome/mac_arm-150.0.7871.24/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';

                \Spatie\LaravelPdf\Facades\Pdf::html($html)
                    ->withBrowsershot(function ($browsershot) use ($chromeTesting) {
                        $browsershot->noSandbox()
                            ->addChromiumArguments(['disable-gpu', 'disable-dev-shm-usage', 'no-zygote']);
                        if (file_exists($chromeTesting)) {
                            $browsershot->setChromePath($chromeTesting);
                        } elseif (file_exists('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')) {
                            $browsershot->setChromePath('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome');
                        }
                    })
                    ->paperSize(210, 297, 'mm')
                    ->save($tempPath);

                if (file_exists($tempPath) && filesize($tempPath) > 0) {
                    $content = file_get_contents($tempPath);
                    @unlink($tempPath);
                    return $content;
                }
            }
        } catch (\Throwable $e) {}

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'portrait'
        ]);
        $dompdf->loadHtml($html);
        $dompdf->render();
        return $dompdf->output();
    }

    public function downloadAcceptancePdf()
    {
        $proj = Project::with(['contact', 'dailyLogs.contact', 'defects.assignedContact'])->find($this->acceptanceProjectId);
        if (!$proj) {
            $this->dispatch('notify', 'Bitte wählen Sie eine Baustelle aus.');
            return;
        }

        $subId = $this->acceptanceSubcontractorId;
        $selectedSubcontractor = $subId ? Contact::find($subId) : null;

        $company = \App\Models\CompanySetting::first();

        $logoBase64 = null;
        if ($company && $company->logo_path && file_exists(storage_path('app/public/' . $company->logo_path))) {
            $logoData = file_get_contents(storage_path('app/public/' . $company->logo_path));
            $mime = mime_content_type(storage_path('app/public/' . $company->logo_path));
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($logoData);
        }

        $logsQuery = $proj->dailyLogs();
        if ($subId) {
            $logsQuery->where('contact_id', $subId);
        }
        $logs = $logsQuery->get();

        $defectsQuery = $proj->defects();
        if ($subId) {
            $defectsQuery->where('assigned_contact_id', $subId);
        }
        $defects = $defectsQuery->get();

        $html = view('pdf.abnahmeprotokoll', [
            'project' => $proj,
            'company' => $company,
            'logoBase64' => $logoBase64,
            'selectedSubcontractor' => $selectedSubcontractor,
            'acceptanceDate' => $this->acceptanceDate,
            'contractorName' => $this->contractorName,
            'contractorRepresentative' => $this->contractorRepresentative,
            'clientName' => $this->clientName,
            'clientRepresentative' => $this->clientRepresentative,
            'workScopeDescription' => $this->workScopeDescription,
            'acceptanceResult' => $this->acceptanceResult,
            'defectRemediationDeadline' => $this->defectRemediationDeadline,
            'warrantyPeriod' => $this->warrantyPeriod,
            'notes' => $this->notes,
            'dailyLogs' => $logs,
            'defects' => $defects,
        ])->render();

        $pdfBinary = $this->generatePdfBinary($html);
        $safeName = \Illuminate\Support\Str::slug($proj->name, '_');
        $subPrefix = $selectedSubcontractor ? '_' . \Illuminate\Support\Str::slug($selectedSubcontractor->display_name, '_') : '';
        $fileName = 'Abnahmeprotokoll_' . $safeName . $subPrefix . '_' . date('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($pdfBinary) {
            echo $pdfBinary;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}; ?>

<div class="space-y-8 font-sans">
    <!-- Header -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Mängelmanagement & Abnahmeprotokolle</h2>
            <p class="text-xs text-slate-500">Erfassung von Restarbeiten, Mängelbeseitigungsfristen & Zuordnung an Nachunternehmer.</p>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="openAcceptanceModal" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer">
                📋 Abnahmeprotokoll (VOB/B) generieren
            </button>
            <button wire:click="openCreateModal" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-md whitespace-nowrap cursor-pointer">
                + Mangel / Restarbeit erfassen
            </button>
        </div>
    </div>

    <!-- Defects Grid / Kanban -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($this->defects as $defect)
            <div wire:key="{{ $defect->id }}" class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between space-y-4 hover:shadow-md transition">
                <div class="space-y-3">
                    <div class="flex justify-between items-start gap-2">
                        <span class="px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider border shadow-2xs {{ $defect->priority_badge_class }}">
                            Prio: {{ ucfirst($defect->priority) }}
                        </span>

                        <select wire:change="updateStatus('{{ $defect->id }}', $event.target.value)" class="text-xs font-bold rounded-lg px-2 py-0.5 border border-slate-200 focus:outline-none {{ $defect->status_badge_class }}">
                            <option value="offen" {{ $defect->status === 'offen' ? 'selected' : '' }}>🔴 Offen</option>
                            <option value="in_bearbeitung" {{ $defect->status === 'in_bearbeitung' ? 'selected' : '' }}>🔵 In Bearbeitung</option>
                            <option value="behoben" {{ $defect->status === 'behoben' ? 'selected' : '' }}>🟡 Behoben (Prüfung)</option>
                            <option value="abgenommen" {{ $defect->status === 'abgenommen' ? 'selected' : '' }}>🟢 Abgenommen</option>
                        </select>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">{{ $defect->title }}</h3>
                        <p class="text-xs text-slate-500 font-medium">Baustelle: <span class="text-slate-900 font-bold">{{ $defect->project->name }}</span></p>
                        @if ($defect->location)
                            <p class="text-xs text-slate-500 font-medium">Ort: {{ $defect->location }}</p>
                        @endif
                    </div>

                    <p class="text-xs text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200/80 leading-relaxed">{{ $defect->description }}</p>
                </div>

                <div class="pt-3 border-t border-slate-100 space-y-2">
                    @if ($defect->assignedContact)
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Zuständig:</span>
                            <span class="font-bold text-slate-900">{{ $defect->assignedContact->display_name }}</span>
                        </div>
                    @endif

                    @if ($defect->deadline)
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-500">Frist zur Behebung:</span>
                            <span class="font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">
                                {{ date('d.m.Y', strtotime($defect->deadline)) }}
                            </span>
                        </div>
                    @endif

                    <!-- Print VOB Mängelrüge PDF Button -->
                    <div class="pt-2 flex justify-end">
                        <button onclick="window.print()" 
                                title="VOB/B Mängelrüge als PDF / Druckansicht generieren"
                                class="w-full px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl transition shadow-2xs cursor-pointer flex items-center justify-center gap-1.5">
                            <span>📄</span>
                            <span>Mängelrüge PDF / VOB Druck</span>
                        </button>
                    </div>

                    <div class="pt-2">
                        <button wire:click="generateNoticeLetter('{{ $defect->id }}')" class="w-full py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs rounded-lg border border-blue-200 transition flex items-center justify-center gap-1">
                            📄 KI VOB/B Mängelrüge erzeugen
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/80 rounded-2xl text-center space-y-3">
                <p class="text-base font-bold text-slate-900">Keine offenen Mängel erfasst</p>
                <p class="text-xs text-slate-500">Klicken Sie auf "+ Mangel / Restarbeit erfassen" zur Dokumentation.</p>
            </div>
        @endforelse
    </div>

    <!-- Create Modal -->
    @if ($showModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Mangel / Restarbeit aufnehmen</h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <form wire:submit="saveDefect" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Baustelle</label>
                        <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                            @foreach ($this->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Titel des Mangels</label>
                        <input wire:model="title" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. Nachdichtung Wandanschluss TG" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Genaue Lage / Ort</label>
                            <input wire:model="location" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. TG 1. OG Westwand">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Priorität</label>
                            <select wire:model="priority" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                                <option value="niedrig">Niedrig</option>
                                <option value="mittel">Mittel</option>
                                <option value="hoch">Hoch</option>
                                <option value="kritisch">Kritisch</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Zuständiger Subunternehmer / Partner</label>
                        <select wire:model="assignedContactId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                            <option value="">Kein Subunternehmer (Eigenleistung)</option>
                            @foreach ($this->subcontractors as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->display_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Beseitigungsfrist</label>
                        <input wire:model="deadline" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Mängelbeschreibung & Maßnahmen</label>
                        <textarea wire:model="description" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Beschreibung der Mangelerscheinung..." required></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Mangel erfassen</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- KI VOB/B Mängelrüge Modal -->
    @if ($showNoticeModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📄</span>
                        <h3 class="text-base font-extrabold text-white">Rechtssichere Mängelrüge nach VOB/B § 13</h3>
                    </div>
                    <button wire:click="$set('showNoticeModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-mono text-slate-800 leading-relaxed max-h-96 overflow-y-auto whitespace-pre-wrap selection:bg-blue-100">{{ $noticeText }}</div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-xs text-slate-500">Gemäß § 13 Abs. 5 VOB/B mit Fristsetzung & Abnahmehinweis</span>
                        <div class="flex space-x-3">
                            <button type="button" wire:click="$set('showNoticeModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Schließen</button>
                            <button type="button" onclick="navigator.clipboard.writeText(`{{ addslashes($noticeText) }}`); alert('Mängelrüge in Zwischenablage kopiert!');" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20">
                                📋 In Zwischenablage kopieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Abnahmeprotokoll (VOB/B & BGB) Modal -->
    @if ($showAcceptanceModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden my-6 flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl">📋</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Vorausgefülltes Abnahmeprotokoll (VOB/B § 12 & BGB § 640)</h3>
                            <p class="text-[11px] text-slate-300">Automatischer Zusammenzug aller Bautagebücher, Mängel & Stammdaten</p>
                        </div>
                    </div>
                    <button wire:click="$set('showAcceptanceModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <div class="p-6 space-y-5 overflow-y-auto grow">
                    <!-- Project & Subcontractor Selector -->
                    <div class="bg-blue-50/70 border border-blue-200/80 rounded-2xl p-4 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-extrabold text-blue-900 uppercase tracking-wider mb-1">1. Baustelle / Bauvorhaben:</label>
                                <select wire:model.live="acceptanceProjectId" class="w-full bg-white border border-blue-300 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 shadow-2xs">
                                    @foreach ($this->projects as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->city_street ?: 'Keine Adresse' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-extrabold text-blue-900 uppercase tracking-wider mb-1">2. Protokoll-Umfang / Subunternehmer:</label>
                                <select wire:model.live="acceptanceSubcontractorId" class="w-full bg-white border border-blue-300 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:border-blue-600 shadow-2xs">
                                    <option value="">🏢 Gesamt-Abnahme (Alle Gewerke / BT Bautechnik)</option>
                                    @foreach ($this->subcontractors as $sub)
                                        <option value="{{ $sub->id }}">🏗️ Subunternehmer Teilabnahme: {{ $sub->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Grid: Contracting Parties -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3">
                            <h4 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Auftraggeber (Bauherr)</h4>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Name / Firma</label>
                                <input wire:model="clientName" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 font-bold focus:outline-none focus:border-blue-600">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Vertreten durch (Bauleiter / Architekt)</label>
                                <input wire:model="clientRepresentative" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-blue-600">
                            </div>
                        </div>

                        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3">
                            <h4 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Auftragnehmer (Bauunternehmen)</h4>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Firma</label>
                                <input wire:model="contractorName" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 font-bold focus:outline-none focus:border-blue-600">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Vertreten durch</label>
                                <input wire:model="contractorRepresentative" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-blue-600">
                            </div>
                        </div>
                    </div>

                    <!-- Work Scope (Auto-populated from Daily Logs) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1 flex justify-between">
                            <span>Abnahmegegenstand & Ausgeführte Leistungen:</span>
                            <span class="text-[10px] text-blue-600 font-bold">✨ Automatisch aus Bautagebüchern aggregiert</span>
                        </label>
                        <textarea wire:model="workScopeDescription" rows="4" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600 font-mono leading-relaxed"></textarea>
                    </div>

                    <!-- Acceptance Status Options -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Abnahmeergebnis (Formelle Erklärung):</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <label class="p-3 border rounded-xl cursor-pointer transition flex items-start gap-2.5 {{ $acceptanceResult === 'ohne_vorbehalt' ? 'bg-emerald-50 border-emerald-500 text-emerald-900 font-bold shadow-2xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                                <input type="radio" wire:model.live="acceptanceResult" value="ohne_vorbehalt" class="mt-0.5 text-emerald-600">
                                <div>
                                    <div class="text-xs font-extrabold">✅ Abnahme ohne Vorbehalt</div>
                                    <div class="text-[10px] text-slate-500 font-normal leading-tight mt-0.5">Leistung vertragsgemäß & mängelfrei.</div>
                                </div>
                            </label>

                            <label class="p-3 border rounded-xl cursor-pointer transition flex items-start gap-2.5 {{ $acceptanceResult === 'mit_vorbehalt' ? 'bg-amber-50 border-amber-500 text-amber-900 font-bold shadow-2xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                                <input type="radio" wire:model.live="acceptanceResult" value="mit_vorbehalt" class="mt-0.5 text-amber-600">
                                <div>
                                    <div class="text-xs font-extrabold">⚠️ Mit Vorbehalt (Mängel)</div>
                                    <div class="text-[10px] text-slate-500 font-normal leading-tight mt-0.5">Abnahme vorbehaltlich Restarbeiten.</div>
                                </div>
                            </label>

                            <label class="p-3 border rounded-xl cursor-pointer transition flex items-start gap-2.5 {{ $acceptanceResult === 'verweigert' ? 'bg-rose-50 border-rose-500 text-rose-900 font-bold shadow-2xs' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                                <input type="radio" wire:model.live="acceptanceResult" value="verweigert" class="mt-0.5 text-rose-600">
                                <div>
                                    <div class="text-xs font-extrabold">❌ Abnahme verweigert</div>
                                    <div class="text-[10px] text-slate-500 font-normal leading-tight mt-0.5">Wegen wesentlicher Mängel abgelehnt.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Deadlines & Notes -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Abnahmedatum</label>
                            <input wire:model="acceptanceDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Mängelbeseitigung bis</label>
                            <input wire:model="defectRemediationDeadline" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Gewährleistungsfrist</label>
                            <input wire:model="warrantyPeriod" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Zusatzvereinbarungen / Anmerkungen</label>
                        <input wire:model="notes" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. Nachabnahme der Dachabdichtung am 12.08. vereinbart...">
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center shrink-0">
                    <button type="button" wire:click="$set('showAcceptanceModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">
                        Abbrechen
                    </button>

                    <button type="button" wire:click="downloadAcceptancePdf" wire:loading.attr="disabled" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-black text-xs rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer">
                        <span wire:loading.remove wire:target="downloadAcceptancePdf">📄 Abnahmeprotokoll (PDF) herunterladen</span>
                        <span wire:loading wire:target="downloadAcceptancePdf" class="flex items-center gap-2">
                            <span class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            <span>Erstelle DIN-PDF...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
