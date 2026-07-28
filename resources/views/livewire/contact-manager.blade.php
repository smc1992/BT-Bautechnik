<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\Contact;
use App\Models\Project;
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

    // Detail Modal states
    public bool $showDetailModal = false;
    public ?string $selectedContactId = null;
    public string $activeDetailTab = 'overview'; // overview, projects, invoices, offers, baukosten
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

    // Form fields for standalone create modal
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

    public function getContactsProperty()
    {
        $query = Contact::with(['projects', 'invoices', 'offers', 'actualCosts'])
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
        return Contact::with(['projects', 'invoices', 'offers', 'actualCosts'])->find($this->selectedContactId);
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
        $this->dispatch('notify', 'Stammdaten erfolgreich im Popup aktualisiert!');
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
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white rounded-2xl p-6 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-1 relative z-10">
            <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2.5">
                <span>🎇️ Kunden, Hausverwaltungen & Partner</span>
            </h2>
            <p class="text-xs text-slate-300 font-medium">Zentrale CRM-Verwaltung aller Auftraggeber, Bauträger, Subunternehmer & Ansprechpartner</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto relative z-10">
            <div class="relative w-full sm:w-72">
                <input wire:model.live.debounce.250ms="search" type="text" 
                       class="w-full bg-slate-900/90 border border-slate-700 rounded-xl pl-9 pr-4 py-2.5 text-xs text-white placeholder-slate-400 focus:border-blue-500 focus:outline-none transition shadow-inner"
                       placeholder="Suchen nach Name, Firma, Ort, Mail...">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
            </div>
            
            <button wire:click="openImportModal" class="w-full sm:w-auto px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                <span>📥 CSV / Excel Import</span>
            </button>

            <button wire:click="openCreateModal" class="w-full sm:w-auto px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 whitespace-nowrap cursor-pointer transition">
                + Neu anlegen
            </button>
        </div>
    </div>

    <!-- Category Filter Chips & Multi-Filter Bar (Mobile-First Optimization) -->
    <div class="space-y-3">
        <!-- Category Chips (Horizontal Scrollable on Mobile) -->
        <div class="overflow-x-auto pb-1.5 scrollbar-none max-w-full">
            <div class="flex items-center gap-2 whitespace-nowrap min-w-max">
                <button wire:click="setFilter('all')" 
                        class="px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 cursor-pointer shrink-0 {{ $activeTypeFilter === 'all' ? 'bg-slate-950 text-white shadow-md' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                    <span>Alle Kontakte</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'all' ? 'bg-slate-800 text-slate-200' : 'bg-slate-100 text-slate-600' }}">{{ $this->counts['all'] }}</span>
                </button>

                <button wire:click="setFilter('hausverwaltung')" 
                        class="px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 cursor-pointer shrink-0 {{ $activeTypeFilter === 'hausverwaltung' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                    <span>🏢 Hausverwaltungen</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'hausverwaltung' ? 'bg-indigo-700 text-indigo-100' : 'bg-indigo-50 text-indigo-700' }}">{{ $this->counts['hausverwaltung'] }}</span>
                </button>

                <button wire:click="setFilter('bautraeger')" 
                        class="px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 cursor-pointer shrink-0 {{ $activeTypeFilter === 'bautraeger' ? 'bg-cyan-600 text-white shadow-md' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                    <span>🏗️ Bauträger</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'bautraeger' ? 'bg-cyan-700 text-cyan-100' : 'bg-cyan-50 text-cyan-700' }}">{{ $this->counts['bautraeger'] }}</span>
                </button>

                <button wire:click="setFilter('kunde')" 
                        class="px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 cursor-pointer shrink-0 {{ $activeTypeFilter === 'kunde' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                    <span>👤 Privatkunden</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'kunde' ? 'bg-blue-700 text-blue-100' : 'bg-blue-50 text-blue-700' }}">{{ $this->counts['kunde'] }}</span>
                </button>

                <button wire:click="setFilter('subunternehmer')" 
                        class="px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 cursor-pointer shrink-0 {{ $activeTypeFilter === 'subunternehmer' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                    <span>🏗️ Subunternehmer</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'subunternehmer' ? 'bg-indigo-700 text-indigo-100' : 'bg-indigo-50 text-indigo-700' }}">{{ $this->counts['subunternehmer'] }}</span>
                </button>
            </div>
        </div>

        <!-- Secondary Filter Controls Strip (Responsive Grid) -->
        <div class="bg-white border border-slate-200/80 p-3 sm:p-3.5 rounded-2xl shadow-2xs grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <!-- City Filter -->
            <div class="flex items-center justify-between sm:justify-start gap-2 bg-slate-50 p-2 sm:p-0 rounded-xl sm:bg-transparent">
                <span class="text-slate-500 font-bold whitespace-nowrap">📍 Ort:</span>
                <select wire:model.live="cityFilter" class="w-full bg-white sm:bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 font-medium focus:border-blue-500 focus:outline-none cursor-pointer">
                    <option value="all">Alle Orte ({{ count($this->cities) }})</option>
                    @foreach ($this->cities as $cty)
                        <option value="{{ $cty }}">{{ $cty }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Sort By -->
            <div class="flex items-center justify-between sm:justify-start gap-2 bg-slate-50 p-2 sm:p-0 rounded-xl sm:bg-transparent">
                <span class="text-slate-500 font-bold whitespace-nowrap">🔃 Sortierung:</span>
                <select wire:model.live="sortBy" class="w-full bg-white sm:bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 font-medium focus:border-blue-500 focus:outline-none cursor-pointer">
                    <option value="latest">Neueste zuerst</option>
                    <option value="oldest">Älteste zuerst</option>
                    <option value="name_asc">Name (A – Z)</option>
                    <option value="name_desc">Name (Z – A)</option>
                    <option value="projects_desc">Meiste Baustellen</option>
                </select>
            </div>

            <!-- Per Page -->
            <div class="flex items-center justify-between sm:justify-start gap-2 bg-slate-50 p-2 sm:p-0 rounded-xl sm:bg-transparent">
                <span class="text-slate-500 font-bold whitespace-nowrap">Zeigen:</span>
                <select wire:model.live="perPage" class="w-full bg-white sm:bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-800 font-medium focus:border-blue-500 focus:outline-none cursor-pointer">
                    <option value="9">9 pro Seite</option>
                    <option value="12">12 pro Seite</option>
                    <option value="24">24 pro Seite</option>
                    <option value="48">48 pro Seite</option>
                </select>
            </div>

            <!-- Reset Filters button -->
            <div class="flex items-center justify-end">
                @if ($search || $activeTypeFilter !== 'all' || $cityFilter !== 'all' || $sortBy !== 'latest' || $perPage !== 12)
                    <button wire:click="resetFilters" class="w-full sm:w-auto px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs rounded-xl border border-rose-200 transition flex items-center justify-center gap-1 cursor-pointer">
                        <span>↺ Filter zurücksetzen</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Contacts Cards Directory -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($this->contacts as $contact)
            <div wire:key="{{ $contact->id }}" 
                 class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-200 flex flex-col justify-between space-y-4 relative overflow-hidden group">
                
                <!-- Category Color Top Accent Line -->
                @php
                    $accentGradient = match($contact->type) {
                        'hausverwaltung' => 'from-indigo-600 to-blue-600',
                        'bautraeger' => 'from-cyan-600 to-teal-600',
                        'subunternehmer' => 'from-indigo-600 to-blue-600',
                        default => 'from-blue-600 to-indigo-600',
                    };
                @endphp
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r {{ $accentGradient }}"></div>

                <div class="space-y-3">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase border shadow-2xs {{ $contact->type_badge_class }}">
                                    {{ $contact->type_label }}
                                </span>
                                @if ($contact->customer_number)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ $contact->customer_number }}
                                    </span>
                                @endif
                            </div>
                            <h3 wire:click="openDetailModal('{{ $contact->id }}')" class="text-base font-extrabold text-slate-900 mt-2 tracking-tight hover:text-blue-600 cursor-pointer line-clamp-1">
                                {{ $contact->display_name }}
                            </h3>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-xs text-slate-600 font-medium">
                        @if ($contact->first_name || $contact->last_name)
                            <p class="flex items-center gap-2">
                                <span class="text-slate-400">👤</span>
                                <span class="font-semibold text-slate-800">{{ $contact->salutation }} {{ $contact->first_name }} {{ $contact->last_name }}</span>
                            </p>
                        @endif

                        @if ($contact->email)
                            <p class="flex items-center gap-2">
                                <span class="text-slate-400">✉️</span>
                                <a href="mailto:{{ $contact->email }}" class="text-blue-600 hover:underline font-semibold truncate">{{ $contact->email }}</a>
                            </p>
                        @endif

                        @if ($contact->phone || $contact->mobile)
                            <p class="flex items-center gap-2">
                                <span class="text-slate-400">📞</span>
                                <a href="tel:{{ $contact->phone ?: $contact->mobile }}" class="text-slate-800 font-semibold hover:underline">{{ $contact->phone ?: $contact->mobile }}</a>
                            </p>
                        @endif

                        @if ($contact->street || $contact->city)
                            <p class="flex items-center gap-2 text-slate-500">
                                <span class="text-slate-400">📍</span>
                                <span>{{ $contact->street }} {{ $contact->zip }} {{ $contact->city }}</span>
                            </p>
                        @endif
                    </div>

                    <!-- Linked Projects Summary -->
                    <div class="pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-semibold">Baustellen:</span>
                            <span class="font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200/60">
                                {{ $contact->projects->count() }} Verknüpft
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button wire:click="openDetailModal('{{ $contact->id }}')" class="px-3 py-1.5 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-xl transition border border-blue-200/60 flex items-center gap-1 cursor-pointer">
                        <span>🔍 Details & Notizen</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button wire:click="openEditModal('{{ $contact->id }}')" class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer">
                            Bearbeiten
                        </button>
                        <button wire:click="deleteContact('{{ $contact->id }}')" wire:confirm="Kontakt wirklich löschen?" class="px-2 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer">
                            Löschen
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/80 rounded-2xl text-center space-y-3 shadow-xs">
                <div class="text-3xl">🎇️</div>
                <p class="text-base font-bold text-slate-900">Keine Kontakte für Ihre Filterkriterien gefunden</p>
            <p class="text-xs text-slate-500">Versuchen Sie Ihre Suche oder Ortsfilter anzupassen.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination Links Footer -->
    <div class="pt-2">
        {{ $this->contacts->links() }}
    </div>

    <!-- CONTACT DETAIL VIEW MODAL (MOBILE ALWAYS FULLSCREEN, DESKTOP MAXIMIZABLE) -->
    @if ($showDetailModal && $this->selectedContact)
        @php $c = $this->selectedContact; @endphp
        <div x-data="{ isMaximized: false }" 
             x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflowX = 'hidden';"
             x-on:unmount.window="document.body.style.overflow = ''; document.documentElement.style.overflowX = '';"
             class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center z-50 transition-all duration-300 overflow-x-hidden p-0 sm:p-4"
             :class="isMaximized ? 'sm:p-0' : 'sm:p-4'">
            <div class="bg-white border-0 sm:border border-slate-200 shadow-2xl overflow-hidden flex flex-col transition-all duration-300 min-w-0 max-w-full w-screen h-screen max-w-none max-h-none rounded-none sm:w-full sm:max-w-4xl sm:max-h-[90vh] sm:rounded-3xl"
                 :class="isMaximized ? 'sm:w-screen sm:h-screen sm:max-w-none sm:max-h-none sm:rounded-none sm:border-0' : ''">
                
                <!-- Modal Header (Rich Dark Gradient, Sticky Top & Shrink-0) -->
                <div class="shrink-0 p-4 sm:p-6 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white relative overflow-hidden space-y-3">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <!-- Top Bar: Badge + USt-ID + Action Buttons -->
                    <div class="flex items-center justify-between gap-2 relative z-10">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-extrabold uppercase bg-blue-500/30 text-blue-200 border border-blue-400/30">
                                {{ $c->type_label }}
                            </span>
                            @if ($c->vat_id)
                                <span class="text-[10px] sm:text-xs font-mono text-slate-300">USt-ID: {{ $c->vat_id }}</span>
                            @endif
                        </div>

                        <!-- Top Right Controls (Edit, Fullscreen & Close) -->
                        <div class="flex items-center gap-2">
                            <button wire:click="toggleDetailEdit" class="px-3 py-1 sm:py-1.5 {{ $isDetailEditing ? 'bg-amber-500 hover:bg-amber-600 text-slate-900 font-extrabold' : 'bg-white/10 hover:bg-white/20 text-white font-bold' }} text-xs rounded-xl transition border border-white/20 flex items-center gap-1.5 cursor-pointer">
                                {{ $isDetailEditing ? '👁️ Fertig' : '✏️ Bearbeiten' }}
                            </button>

                            <!-- Desktop-only Fullscreen / Maximize Toggle Button -->
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

                            <button wire:click="closeDetailModal" onclick="document.body.style.overflow = ''; document.documentElement.style.overflowX = '';" class="p-1.5 sm:p-2 text-slate-300 hover:text-white rounded-full bg-white/10 hover:bg-white/20 transition cursor-pointer" title="Schließen">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Title & Ansprechpartner/Address -->
                    <div class="space-y-1 relative z-10">
                        <h2 class="text-lg sm:text-2xl font-black text-white tracking-tight leading-snug">{{ $c->display_name }}</h2>
                        <p class="text-xs text-slate-300 flex flex-wrap items-center gap-2 sm:gap-3">
                            @if ($c->first_name || $c->last_name)
                                <span>👤 {{ $c->salutation }} {{ $c->first_name }} {{ $c->last_name }}</span>
                            @endif
                            @if ($c->city)
                                <span>📍 {{ $c->street }}, {{ $c->zip }} {{ $c->city }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Quick KPI Summary Strip (Responsive Grid) -->
                <div class="shrink-0 grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 p-3 bg-slate-50 border-b border-slate-200">
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase">Baustellen</span>
                        <p class="text-sm sm:text-lg font-black text-slate-900 mt-0.5">{{ $c->projects->count() }}</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase">Rechnungen</span>
                        <p class="text-sm sm:text-lg font-black text-blue-600 mt-0.5">{{ number_format($c->invoices->sum('total_net'), 2, ',', '.') }} €</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase">Angebote</span>
                        <p class="text-sm sm:text-lg font-black text-slate-900 mt-0.5">{{ $c->offers->count() }}</p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase">Fremdleistung</span>
                        <p class="text-sm sm:text-lg font-black text-indigo-600 mt-0.5">{{ number_format($c->actualCosts->sum('amount'), 2, ',', '.') }} €</p>
                    </div>
                </div>

                <!-- Detail Tabs Navigation (Starts at 0 scroll left, touch-friendly) -->
                <div class="shrink-0 overflow-x-auto whitespace-nowrap scrollbar-none border-b border-slate-200 bg-white px-2 sm:px-4 max-w-full">
                    <div class="flex items-center gap-1 min-w-max py-1">
                        <button wire:click="$set('activeDetailTab', 'overview')" class="py-2.5 px-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'overview' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>📋 Stammdaten & Notizen</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'projects')" class="py-2.5 px-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'projects' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>🏢 Baustellen</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-100 text-slate-700 font-bold">{{ $c->projects->count() }}</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'invoices')" class="py-2.5 px-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'invoices' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>📄 Rechnungen</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-100 text-slate-700 font-bold">{{ $c->invoices->count() }}</span>
                        </button>
                        <button wire:click="$set('activeDetailTab', 'offers')" class="py-2.5 px-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'offers' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            <span>📑 Angebote</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-100 text-slate-700 font-bold">{{ $c->offers->count() }}</span>
                        </button>
                        @if ($c->type === 'subunternehmer')
                            <button wire:click="$set('activeDetailTab', 'baukosten')" class="py-2.5 px-3 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer {{ $activeDetailTab === 'baukosten' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                                <span>🛠️ Baukosten (§13b)</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-100 text-slate-700 font-bold">{{ $c->actualCosts->count() }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Tab Contents Container (Scrollable Body) -->
                <div class="p-3.5 sm:p-6 overflow-y-auto overflow-x-hidden flex-1 space-y-5 max-w-full">

                    <!-- TAB 1: STAMMDATEN & NOTIZEN (MIT INLINE-BEARBEITUNG) -->
                    @if ($activeDetailTab === 'overview')
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                            
                            <!-- Master Data Box -->
                            <div class="lg:col-span-7 bg-slate-50 border border-slate-200/80 rounded-2xl p-4 sm:p-5 space-y-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-3">
                                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Kontaktdaten & Stammdaten</h4>
                                    @if (!$isDetailEditing)
                                        <button wire:click="toggleDetailEdit" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1 cursor-pointer">
                                            <span>✏️ Bearbeiten</span>
                                        </button>
                                    @endif
                                </div>
                                
                                @if ($isDetailEditing)
                                    <!-- INLINE EDITING FORM INSIDE POPUP -->
                                    <div class="space-y-3 text-xs bg-white p-3.5 sm:p-4 rounded-xl border border-slate-200 shadow-2xs">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Kategorie / Typ</label>
                                                <select wire:model="detailForm.type" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs font-semibold">
                                                    <option value="kunde">👤 Privatkunde</option>
                                                    <option value="hausverwaltung">🏢 Hausverwaltung (WEG)</option>
                                                    <option value="bautraeger">🏗️ Bauträger</option>
                                                    <option value="subunternehmer">🛠️ Subunternehmer (§13b)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Firma / Unternehmen</label>
                                                <input wire:model="detailForm.company_name" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Anrede</label>
                                                <select wire:model="detailForm.salutation" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                                    <option value="Herr">Herr</option>
                                                    <option value="Frau">Frau</option>
                                                    <option value="Firma">Firma</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Vorname</label>
                                                <input wire:model="detailForm.first_name" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Nachname</label>
                                                <input wire:model="detailForm.last_name" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">E-Mail</label>
                                                <input wire:model="detailForm.email" type="email" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Telefon</label>
                                                <input wire:model="detailForm.phone" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Mobil</label>
                                                <input wire:model="detailForm.mobile" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">USt-IdNr.</label>
                                                <input wire:model="detailForm.vat_id" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1">Straße & Nr</label>
                                            <input wire:model="detailForm.street" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                        </div>

                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">PLZ</label>
                                                <input wire:model="detailForm.zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                            <div class="col-span-2">
                                                <label class="block font-bold text-slate-700 mb-1">Ort</label>
                                                <input wire:model="detailForm.city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs">
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-2 pt-2">
                                            <button type="button" wire:click="toggleDetailEdit" class="px-3 py-1.5 bg-slate-100 text-slate-700 font-bold rounded-lg text-xs">Abbrechen</button>
                                            <button type="button" wire:click="saveDetailStammdaten" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs shadow-xs">💾 Speichern</button>
                                        </div>
                                    </div>
                                @else
                                    <!-- READ-ONLY STAMMDATEN VIEW (MOBILE 1-COL GRID FOR LONG CONTACT DETAILS) -->
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
                                                <span class="text-slate-400 font-medium block">Telefon Festnetz:</span>
                                                @if ($c->phone)
                                                    <a href="tel:{{ $c->phone }}" class="font-bold text-slate-900 hover:underline">{{ $c->phone }}</a>
                                                @else
                                                    <span class="text-slate-400 italic">Nicht angegeben</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/60">
                                            <div>
                                                <span class="text-slate-400 font-medium block">Mobiltelefon:</span>
                                                @if ($c->mobile)
                                                    <a href="tel:{{ $c->mobile }}" class="font-bold text-slate-900 hover:underline">{{ $c->mobile }}</a>
                                                @else
                                                    <span class="text-slate-400 italic">Nicht angegeben</span>
                                                @endif
                                            </div>

                                            <div>
                                                <span class="text-slate-400 font-medium block">USt-IdNr. / Steuernummer:</span>
                                                <span class="font-mono text-slate-900 font-bold">{{ $c->vat_id ?: 'Keine angegeben' }}</span>
                                            </div>
                                        </div>

                                        <div class="pt-2 border-t border-slate-200/60">
                                            <span class="text-slate-400 font-medium block">Anschrift:</span>
                                            <p class="font-bold text-slate-900 mt-0.5">
                                                {{ $c->street ?: 'Keine Straße angegeben' }}<br>
                                                {{ $c->zip }} {{ $c->city }}
                                            </p>
                                            @if ($c->street && $c->city)
                                                <a href="https://maps.google.com/?q={{ urlencode($c->street . ', ' . $c->zip . ' ' . $c->city) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-blue-600 font-bold hover:underline mt-1.5">
                                                    🗺️ In Google Maps öffnen ↗
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- MULTIPLE NOTES & NOTIZ-BUCH SYSTEM (5 cols) -->
                            <div class="lg:col-span-5 bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4 flex flex-col justify-between">
                                <div>
                                    <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center justify-between">
                                        <span>📝 Notizen & Notizbuch</span>
                                        <span class="text-[10px] font-bold text-slate-400">Zeitstempel-Journal</span>
                                    </h4>

                                    <!-- Quick add new note box -->
                                    <div class="mt-3 space-y-2 bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                                        <label class="block text-[11px] font-bold text-slate-700">+ Neue Notiz / Telefonnotiz hinzufügen:</label>
                                        <textarea wire:model="newNoteText" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="z. B. 23.07. Telefonat wegen Abnahme am Dienstag..."></textarea>
                                        <button type="button" wire:click="addQuickNote" class="w-full py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-lg transition shadow-xs">
                                            📌 Notiz mit Datum anfügen
                                        </button>
                                    </div>

                                    <!-- Editable / Formatted Notes History -->
                                    <div class="mt-4 space-y-2">
                                        <div class="flex justify-between items-center">
                                            <span class="text-[11px] font-bold text-slate-500 uppercase">Notiz-Historie (Volltext):</span>
                                            <button type="button" wire:click="saveNotesOnly" class="text-[11px] font-bold text-blue-600 hover:underline">
                                                💾 Notizen speichern
                                            </button>
                                        </div>
                                        <textarea wire:model="detailForm.notes" rows="6" class="w-full bg-white border border-slate-300 rounded-xl p-3 text-xs text-slate-800 leading-relaxed font-sans focus:outline-none focus:border-blue-600" placeholder="Noch keine Notizen hinterlegt..."></textarea>
                                    </div>
                                </div>
                            </div>

                        </div>
                    @endif

                    <!-- TAB 2: BAUSTELLEN & PROJEKTE -->
                    @if ($activeDetailTab === 'projects')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Verknüpfte Baustellen ({{ $c->projects->count() }})</h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @forelse ($c->projects as $project)
                                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 space-y-2">
                                        <div class="flex justify-between items-start">
                                            <h5 class="text-sm font-bold text-slate-900 line-clamp-1">{{ $project->name }}</h5>
                                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-100 text-blue-800">
                                                {{ $project->status ?? 'In Ausführung' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-500">📍 {{ $project->location ?? 'Kein Ort hinterlegt' }}</p>
                                        <div class="flex justify-between items-center text-xs pt-2 border-t border-slate-200/60">
                                            <span class="text-slate-500">Soll-Budget:</span>
                                            <span class="font-bold text-slate-900">{{ number_format($project->planned_budget ?? 0, 2, ',', '.') }} €</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500 italic col-span-full py-8 text-center bg-slate-50 rounded-2xl border border-slate-200/60">
                                        Keine Baustellen mit diesem Kontakt verknüpft.
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <!-- TAB 3: RECHNUNGEN -->
                    @if ($activeDetailTab === 'invoices')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Ausgangsrechnungen an {{ $c->display_name }}</h4>
                            </div>

                            <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto shadow-2xs">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                                            <th class="py-3 px-4">Rechnungs-Nr.</th>
                                            <th class="py-3 px-4">Datum</th>
                                            <th class="py-3 px-4">Typ</th>
                                            <th class="py-3 px-4 text-right">Netto (€)</th>
                                            <th class="py-3 px-4 text-right">Brutto (€)</th>
                                            <th class="py-3 px-4 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs">
                                        @forelse ($c->invoices as $inv)
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-3 px-4 font-bold text-slate-900">{{ $inv->invoice_number }}</td>
                                                <td class="py-3 px-4 text-slate-600">{{ date('d.m.Y', strtotime($inv->invoice_date)) }}</td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-100 text-slate-700">
                                                        {{ $inv->type ?? 'Abschlussrechnung' }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-right font-semibold text-slate-900">{{ number_format($inv->total_net, 2, ',', '.') }} €</td>
                                                <td class="py-3 px-4 text-right font-extrabold text-blue-600">{{ number_format($inv->total_gross, 2, ',', '.') }} €</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        Bezahlt
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-8 text-center text-xs text-slate-500 italic">
                                                    Keine Rechnungen für diesen Kontakt vorhanden.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 4: ANGEBOTE -->
                    @if ($activeDetailTab === 'offers')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Erstellte Angebote</h4>
                            </div>

                            <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto shadow-2xs">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                                            <th class="py-3 px-4">Angebots-Nr.</th>
                                            <th class="py-3 px-4">Datum</th>
                                            <th class="py-3 px-4">Status</th>
                                            <th class="py-3 px-4 text-right">Gesamt Netto (€)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs">
                                        @forelse ($c->offers as $off)
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-3 px-4 font-bold text-slate-900">{{ $off->offer_number }}</td>
                                                <td class="py-3 px-4 text-slate-600">{{ date('d.m.Y', strtotime($off->date)) }}</td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-50 text-blue-700">
                                                        {{ $off->status ?? 'Gesendet' }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-right font-extrabold text-slate-900">{{ number_format($off->total_net, 2, ',', '.') }} €</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="py-8 text-center text-xs text-slate-500 italic">
                                                    Keine Angebote für diesen Kontakt vorhanden.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 5: BAUKOSTEN / SUBUNTERNEHMER -->
                    @if ($activeDetailTab === 'baukosten' && $c->type === 'subunternehmer')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Eingangsrechnungen & Baukosten (§13b)</h4>
                            </div>

                            <div class="bg-white border border-slate-200/80 rounded-2xl overflow-x-auto shadow-2xs">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                                            <th class="py-3 px-4">Rechnungs-Nr.</th>
                                            <th class="py-3 px-4">Datum</th>
                                            <th class="py-3 px-4">Gewerk / Leistung</th>
                                            <th class="py-3 px-4 text-right">Betrag (€)</th>
                                            <th class="py-3 px-4 text-center">§ 13b UStG</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs">
                                        @forelse ($c->actualCosts as $cost)
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-3 px-4 font-bold text-slate-900">{{ $cost->invoice_number ?? '—' }}</td>
                                                <td class="py-3 px-4 text-slate-600">{{ date('d.m.Y', strtotime($cost->cost_date)) }}</td>
                                                <td class="py-3 px-4 text-slate-800 font-medium">{{ $cost->description }}</td>
                                                <td class="py-3 px-4 text-right font-extrabold text-indigo-700">{{ number_format($cost->amount, 2, ',', '.') }} €</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md {{ $cost->is_reverse_charge ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600' }}">
                                                        {{ $cost->is_reverse_charge ? 'Ja (§13b)' : 'Nein' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-8 text-center text-xs text-slate-500 italic">
                                                    Keine Fremdleistungs-Rechnungen für diesen Subunternehmer erfasst.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center">
                    <span class="text-xs text-slate-500 font-medium">💡 Änderungen werden in Echtzeit in der Datenbank aktualisiert.</span>
                    <button wire:click="closeDetailModal" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-xs">
                        Schließen
                    </button>
                </div>

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
                        <select wire:model="type" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none">
                            <option value="kunde">👤 Privatkunde</option>
                            <option value="hausverwaltung">🏢 Hausverwaltung (WEG)</option>
                            <option value="bautraeger">🏗️ Bauträger / Bauunternehmen</option>
                            <option value="subunternehmer">🛠️ Subunternehmer / Partner (§13b)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Firmenname / Bezeichnung</label>
                        <input wire:model="companyName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="z. B. Ingolstädter Hausverwaltung GmbH">
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Anrede</label>
                            <select wire:model="salutation" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none">
                                <option value="Herr">Herr</option>
                                <option value="Frau">Frau</option>
                                <option value="Firma">Firma</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Vorname</label>
                            <input wire:model="firstName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Max">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nachname</label>
                            <input wire:model="lastName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Mustermann">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">E-Mail</label>
                            <input wire:model="email" type="email" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="info@beispiel.de">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Telefon</label>
                            <input wire:model="phone" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="0841 123456">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Straße & Hausnummer</label>
                        <input wire:model="street" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Münchner Str. 10">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">PLZ</label>
                            <input wire:model="zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="85051">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ort</label>
                            <input wire:model="city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Ingolstadt">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">USt-IdNr. / Steuernummer (§13b)</label>
                        <input wire:model="vatId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="DE123456789">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notizen</label>
                        <textarea wire:model="notes" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Zusätzliche Infos, Ansprechpartner etc..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showContactModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- CSV / EXCEL IMPORT MODAL -->
    @if ($showImportModal)
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                
                <!-- Modal Header -->
                <div class="p-6 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white flex justify-between items-start relative overflow-hidden">
                    <div class="space-y-1 relative z-10">
                        <h3 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                            <span>📥 Kontakte aus CSV / Excel importieren</span>
                        </h3>
                        <p class="text-xs text-slate-300">Laden Sie eine CSV-Datei hoch, um Kunden, Hausverwaltungen oder Subunternehmer gesammelt zu importieren.</p>
                    </div>
                    <button wire:click="closeImportModal" class="p-2 text-slate-400 hover:text-white rounded-full bg-white/10 hover:bg-white/20 transition cursor-pointer relative z-10">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6">
                    <!-- Step 1: File Dropzone & Template Download -->
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

                        <!-- Dropzone area -->
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

                    <!-- Step 2: Parsed Preview Table -->
                    @if (count($parsedImportRows) > 0)
                        <div class="space-y-3 pt-2">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold uppercase text-slate-900 tracking-wider flex items-center gap-2">
                                    <span>Vorschau der erkannten Kontakte</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-200">
                                        {{ count($parsedImportRows) }} Datensätze
                                    </span>
                                </h4>
                                <span class="text-[11px] text-slate-500">Automatische Spaltenzuordnung aktiv</span>
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

                <!-- Modal Footer -->
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
