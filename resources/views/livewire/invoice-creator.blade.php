<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offer;
use App\Models\OfferSection;
use App\Models\OfferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    // Mode
    public string $mode = 'invoice'; // invoice, offer

    // Company Profile
    public array $profile = [
        'company' => 'BT Bautechnik UG',
        'address' => 'Brunnenstraße 4',
        'zip' => '92334',
        'city' => 'Berching',
        'mail' => 'bt-bautechnik@gmx.de',
        'managing' => 'Frau Julia Haberzettel',
        'taxId' => '235/224/10632',
        'vatId' => '',
        'iban' => 'DE93 7215 0000 0054 9064 82',
        'bic' => 'BYLADEM1ING',
        'registry' => 'Amtsgericht Nürnberg',
        'hrb' => '46210'
    ];

    // Document Meta
    public ?string $projectId = null;
    public string $docNumber = '';
    public string $docDate = '';
    public string $deliveryDate = 'Leistungsdatum entspricht Rechnungsdatum';
    public int $dueDays = 14;
    public float $discountRate = 0.0;
    public string $taxMode = 'standard'; // standard, reverse, small, custom
    public string $taxReasonSelectValue = '';
    public string $taxReasonText = '';
    public string $customPaymentNote = '';
    public string $customLegalText = '';

    // Client Address
    public array $client = [
        'name' => '',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => 'Deutschland',
        'clientNumber' => ''
    ];

    // Document Items
    public array $items = [];

    // Historical documents list
    public array $savedDocs = [];

    public function mount()
    {
        $this->docDate = date('Y-m-d');
        $this->resetForm();
        $this->loadSavedDocuments();
    }

    public function loadSavedDocuments()
    {
        if ($this->mode === 'invoice') {
            $this->savedDocs = Invoice::with('project')->latest()->get()->toArray();
        } else {
            $this->savedDocs = Offer::with('project')->latest()->get()->toArray();
        }
    }

    public function setMode($newMode)
    {
        $this->mode = $newMode;
        $this->loadSavedDocuments();
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->projectId = null;
        $this->client = [
            'name' => '',
            'street' => '',
            'zip' => '',
            'city' => '',
            'country' => 'Deutschland',
            'clientNumber' => 'KD-' . rand(10000, 99999)
        ];
        $this->items = [
            [
                'id' => Str::random(8),
                'pos_number' => '1',
                'description' => 'Bauleistung / Stundenlohnarbeiten laut Leistungsbeschreibung',
                'quantity' => 1,
                'unit' => 'pauschal',
                'price' => 1500.00,
                'vatRate' => 19.00
            ]
        ];
        $this->docNumber = $this->suggestNumber();
        $this->docDate = date('Y-m-d');
        $this->deliveryDate = 'Leistungsdatum entspricht Rechnungsdatum';
        $this->dueDays = 14;
        $this->discountRate = 0.0;
        $this->taxMode = 'standard';
        $this->taxReasonSelectValue = '';
        $this->taxReasonText = '';
        $this->customPaymentNote = '';
        $this->customLegalText = '';
    }

    public function suggestNumber()
    {
        $today = date('Ymd');
        $prefix = $this->mode === 'invoice' ? 'RE-' : 'AN-';
        
        if ($this->mode === 'invoice') {
            $count = Invoice::where('invoice_number', 'like', $prefix . $today . '-%')->count();
            return $prefix . $today . '-' . str_pad($count + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $count = Offer::where('offer_number', 'like', $prefix . $today . '-%')->count();
            return $prefix . $today . '-' . str_pad($count + 1, 2, '0', STR_PAD_LEFT);
        }
    }

    public function updatedProjectId($id)
    {
        if (!$id) return;

        $project = Project::with('offers.sections.items')->find($id);
        if ($project) {
            $this->client['name'] = $project->name;
            $this->client['zip'] = $project->zip ?: '';
            $this->client['city'] = $project->city_street ?: '';
            $this->client['street'] = $project->contact_address ?: '';
            $this->client['clientNumber'] = 'KD-' . substr(preg_replace('/\D/', '', $project->phone ?? Str::random(5)), 0, 5);
            if (empty($this->client['clientNumber']) || strlen($this->client['clientNumber']) < 5) {
                $this->client['clientNumber'] = 'KD-' . rand(10000, 99999);
            }
        }
    }

    // Import Items from Project's accepted offers
    public function importOfferItems($offerId)
    {
        $offer = Offer::with('sections.items')->find($offerId);
        if ($offer) {
            $this->items = [];
            $pos = 1;
            foreach ($offer->sections as $section) {
                foreach ($section->items as $item) {
                    $this->items[] = [
                        'id' => Str::random(8),
                        'pos_number' => $item->pos_number ?: strval($pos++),
                        'description' => $section->title . ": " . $item->description,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'price' => $item->unit_price,
                        'vatRate' => 19.00
                    ];
                }
            }
            $this->dispatch('notify', 'Positionen aus Angebot übernommen!');
        }
    }

    public function addItem()
    {
        $this->items[] = [
            'id' => Str::random(8),
            'pos_number' => strval(count($this->items) + 1),
            'description' => '',
            'quantity' => 1,
            'unit' => 'Stk.',
            'price' => 0.00,
            'vatRate' => 19.00
        ];
    }

    public function removeItem($id)
    {
        $this->items = array_values(array_filter($this->items, function ($item) use ($id) {
            return $item['id'] !== $id;
        }));
    }

    // Calculations property
    public function getCalculationProperty()
    {
        $subtotal = 0;
        foreach ($this->items as $item) {
            $qty = floatval($item['quantity'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            $subtotal += $qty * $price;
        }

        $discountRate = floatval($this->discountRate ?? 0);
        $discountValue = $subtotal * ($discountRate / 100);
        $subtotalAfterDiscount = $subtotal - $discountValue;

        $taxes = [];
        $totalTax = 0;

        if ($this->taxMode === 'standard') {
            foreach ($this->items as $item) {
                $qty = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $itemNet = $qty * $price;
                $itemNetDiscounted = $itemNet - ($itemNet * ($discountRate / 100));
                $rate = floatval($item['vatRate'] ?? 19.00);

                if ($rate > 0) {
                    $itemTax = $itemNetDiscounted * ($rate / 100);
                    if (!isset($taxes[$rate])) {
                        $taxes[$rate] = 0;
                    }
                    $taxes[$rate] += $itemTax;
                    $totalTax += $itemTax;
                }
            }
        } else {
            $taxes[0] = 0;
            $totalTax = 0;
        }

        $grandTotal = $subtotalAfterDiscount + $totalTax;

        return [
            'subtotal' => $subtotal,
            'discountValue' => $discountValue,
            'subtotalAfterDiscount' => $subtotalAfterDiscount,
            'taxes' => $taxes,
            'totalTax' => $totalTax,
            'grandTotal' => $grandTotal,
        ];
    }

    public function loadSavedDoc($id)
    {
        if ($this->mode === 'invoice') {
            $inv = Invoice::with('items')->find($id);
            if ($inv) {
                $this->projectId = $inv->project_id;
                $this->docNumber = $inv->invoice_number;
                $this->docDate = $inv->invoice_date;
                $this->deliveryDate = $inv->delivery_date;
                $this->dueDays = $inv->due_days;
                $this->discountRate = $inv->discount_rate;
                $this->taxMode = $inv->tax_mode;
                $this->taxReasonText = $inv->tax_reason ?: '';
                $this->customPaymentNote = $inv->custom_payment_note ?: '';
                $this->customLegalText = $inv->custom_legal_text ?: '';
                
                $this->items = [];
                foreach ($inv->items as $item) {
                    $this->items[] = [
                        'id' => Str::random(8),
                        'pos_number' => $item->pos_number,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'price' => $item->unit_price,
                        'vatRate' => $item->vat_rate
                    ];
                }
            }
        } else {
            $off = Offer::with('sections.items')->find($id);
            if ($off) {
                $this->projectId = $off->project_id;
                $this->docNumber = $off->offer_number;
                $this->docDate = $off->date;
                $this->deliveryDate = '';
                $this->discountRate = 0.0;
                $this->taxMode = 'standard';
                $this->items = [];
                
                $pos = 1;
                foreach ($off->sections as $sec) {
                    foreach ($sec->items as $item) {
                        $this->items[] = [
                            'id' => Str::random(8),
                            'pos_number' => $item->pos_number ?: strval($pos++),
                            'description' => $sec->title . ': ' . $item->description,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'price' => $item->unit_price,
                            'vatRate' => 19.00
                        ];
                    }
                }
            }
        }
    }

    public function saveDocument()
    {
        $this->validate([
            'docNumber' => 'required|string',
            'docDate' => 'required|date',
            'client.name' => 'required|string|max:255',
        ]);

        $calc = $this->calculation;

        if ($this->mode === 'invoice') {
            DB::transaction(function () use ($calc) {
                $invoice = Invoice::updateOrCreate(
                    ['invoice_number' => $this->docNumber],
                    [
                        'project_id' => $this->projectId,
                        'invoice_date' => $this->docDate,
                        'delivery_date' => $this->deliveryDate,
                        'due_days' => $this->dueDays,
                        'discount_rate' => $this->discountRate,
                        'tax_mode' => $this->taxMode,
                        'tax_reason' => ($this->taxMode === 'custom' || $this->taxMode === 'reverse') ? $this->taxReasonText : null,
                        'custom_payment_note' => $this->customPaymentNote,
                        'custom_legal_text' => $this->customLegalText,
                        'total_net' => $calc['subtotalAfterDiscount'],
                        'total_tax' => $calc['totalTax'],
                        'total_gross' => $calc['grandTotal'],
                        'status' => 'sent'
                    ]
                );

                // Re-create items
                $invoice->items()->delete();
                foreach ($this->items as $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'pos_number' => $item['pos_number'],
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'unit_price' => $item['price'],
                        'vat_rate' => $item['vatRate'],
                        'total_price' => floatval($item['quantity']) * floatval($item['price'])
                    ]);
                }
            });

            $this->dispatch('notify', 'Rechnung erfolgreich archiviert!');
        } else {
            DB::transaction(function () use ($calc) {
                $offer = Offer::updateOrCreate(
                    ['offer_number' => $this->docNumber],
                    [
                        'project_id' => $this->projectId,
                        'date' => $this->docDate,
                        'status' => 'sent',
                        'total_net' => $calc['subtotalAfterDiscount'],
                        'total_gross' => $calc['grandTotal']
                    ]
                );

                // In Offer model, structure is sections -> items.
                // We create a single section named 'Leistungen' or matching first import
                $offer->sections()->delete();
                $section = OfferSection::create([
                    'offer_id' => $offer->id,
                    'title' => 'Angebotsleistungen',
                    'sort_order' => 1
                ]);

                foreach ($this->items as $item) {
                    OfferItem::create([
                        'section_id' => $section->id,
                        'pos_number' => $item['pos_number'],
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'unit_price' => $item['price'],
                        'total_price' => floatval($item['quantity']) * floatval($item['price'])
                    ]);
                }
            });

            $this->dispatch('notify', 'Angebot erfolgreich archiviert!');
        }

        $this->loadSavedDocuments();
    }

    // AI OpenAI Integration
    public bool $showAiModal = false;
    public string $aiRawText = '';

    public function parseWithAi(\App\Services\OpenAiParserService $parser)
    {
        if (empty(trim($this->aiRawText))) {
            $this->dispatch('notify', 'Bitte geben Sie zuerst einen Text oder ein Angebot ein.');
            return;
        }

        try {
            $parsed = $parser->parseOfferDocument($this->aiRawText);

            if (!empty($parsed['sections'])) {
                $newItems = [];
                $posCount = 1;
                foreach ($parsed['sections'] as $section) {
                    foreach ($section['items'] ?? [] as $it) {
                        $newItems[] = [
                            'id' => Str::random(8),
                            'pos_number' => $it['pos_number'] ?? strval($posCount++),
                            'description' => ($section['title'] ?? '') ? ($section['title'] . ': ' . ($it['description'] ?? '')) : ($it['description'] ?? ''),
                            'quantity' => floatval($it['quantity'] ?? 1),
                            'unit' => $it['unit'] ?? 'Stk',
                            'price' => floatval($it['unit_price'] ?? 0),
                            'vatRate' => 19.00
                        ];
                    }
                }

                if (count($newItems) > 0) {
                    $this->items = $newItems;
                    $this->showAiModal = false;
                    $this->aiRawText = '';
                    $this->dispatch('notify', '✨ ' . count($newItems) . ' Positionen erfolgreich per OpenAI analysiert und importiert!');
                }
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler bei der KI-Analyse: ' . $e->getMessage());
        }
    }

    // AI Cover Letter & Offer Audit Integration
    public bool $showCoverLetterModal = false;
    public string $coverLetterText = '';

    public bool $showOfferAuditModal = false;
    public array $offerAuditResults = [];

    public function generateCoverLetter(\App\Services\OpenAiParserService $parser)
    {
        try {
            $totals = $this->calculateTotals();
            $this->coverLetterText = $parser->generateCoverLetter($this->mode, [
                'client_name' => $this->client['name'] ?: 'Sehr geehrte Damen und Herren',
                'number' => $this->docNumber,
                'project' => $this->projectId ? (\App\Models\Project::find($this->projectId)?->name) : 'Baustelle',
                'total' => number_format($totals['gross'], 2, ',', '.'),
            ]);
            $this->showCoverLetterModal = true;
            $this->dispatch('notify', '✨ KI-E-Mail Anschreiben erfolgreich erzeugt!');
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler: ' . $e->getMessage());
        }
    }

    public function auditOfferRisk(\App\Services\OpenAiParserService $parser)
    {
        if (empty($this->items)) {
            $this->dispatch('notify', 'Keine Positionen im Angebot zum Prüfen vorhanden.');
            return;
        }

        try {
            $this->offerAuditResults = $parser->auditOfferItems($this->items, $this->mode === 'offer' ? 'Bauangebot ' . $this->docNumber : 'Rechnung ' . $this->docNumber);
            $this->showOfferAuditModal = true;
            $this->dispatch('notify', '✨ KI-Angebots-Check abgeschlossen!');
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler beim Angebots-Check: ' . $e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <!-- Load custom legacy CSS styles dynamically -->
    <link rel="stylesheet" href="{{ asset('css/invoice-style.css') }}">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm backdrop-blur-md dashboard-btn-container">
        <div class="flex space-x-2">
            <button wire:click="setMode('invoice')" class="px-4 py-2 text-xs font-bold rounded-xl transition shadow-xs {{ $mode === 'invoice' ? 'bg-blue-600 text-white shadow-blue-500/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                📄 Rechnungs-Modus
            </button>
            <button wire:click="setMode('offer')" class="px-4 py-2 text-xs font-bold rounded-xl transition shadow-xs {{ $mode === 'offer' ? 'bg-blue-600 text-white shadow-blue-500/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                📑 Angebots-Modus
            </button>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="$set('showAiModal', true)" class="px-3.5 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-purple-500/20 flex items-center gap-1.5">
                ✨ KI-Textimport
            </button>
            <button wire:click="generateCoverLetter" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-indigo-500/20 flex items-center gap-1.5">
                ✉️ KI-Anschreiben
            </button>
            <button wire:click="auditOfferRisk" class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-amber-500/20 flex items-center gap-1.5">
                🛡️ KI-Angebots-Check
            </button>
            <button wire:click="resetForm" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition border border-slate-200 shadow-2xs">
                Formular leeren
            </button>
            <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-emerald-500/20">
                🖨️ Drucken / PDF
            </button>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- EDITOR PANEL (LEFT COLUMN) -->
        <div class="lg:col-span-5 space-y-6 editor-panel">
            
            <!-- Quick select project / Import details -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Baustellen-Schnellwahl & Vorlagen</h3>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Projekt auswählen</label>
                    <select wire:model.live="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none">
                        <option value="">-- Freie Erstellung (keine Baustelle) --</option>
                        @foreach (\App\Models\Project::all() as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($projectId)
                    @php 
                        $projectOffers = \App\Models\Project::find($projectId)?->offers;
                    @endphp
                    @if ($projectOffers && $projectOffers->count() > 0)
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Posten aus Angebot übernehmen</label>
                            <select onchange="ConfirmImport(this)" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none">
                                <option value="">-- Angebot wählen --</option>
                                @foreach ($projectOffers as $o)
                                    <option value="{{ $o->id }}">Nr: {{ $o->offer_number }} ({{ number_format($o->total_net, 2, ',', '.') }} €)</option>
                                @endforeach
                            </select>
                            <script>
                                function ConfirmImport(select) {
                                    if(select.value && confirm("Möchten Sie alle Positionen aus diesem Angebot in das Formular importieren? Bisherige Posten werden überschrieben.")) {
                                        @this.importOfferItems(select.value);
                                    }
                                    select.value = "";
                                }
                            </script>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Profile Settings -->
            <details class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4" open>
                <summary class="text-sm font-bold text-slate-900 uppercase tracking-wider cursor-pointer select-none">Firmenprofil (Absender)</summary>
                <div class="space-y-3 pt-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Firma / Name</label>
                            <input wire:model.live="profile.company" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Geschäftsführung</label>
                            <input wire:model.live="profile.managing" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Straße & Nr</label>
                        <input wire:model.live="profile.address" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">PLZ</label>
                            <input wire:model.live="profile.zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Ort</label>
                            <input wire:model.live="profile.city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Steuernummer</label>
                            <input wire:model.live="profile.taxId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">USt-IdNr.</label>
                            <input wire:model.live="profile.vatId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">IBAN</label>
                            <input wire:model.live="profile.iban" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">BIC</label>
                            <input wire:model.live="profile.bic" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                </div>
            </details>

            <!-- Recipient Address details -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Empfänger (Kunde)</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Name / Firma des Kunden</label>
                        <input wire:model.live="client.name" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Straße & Hausnummer</label>
                        <input wire:model.live="client.street" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">PLZ</label>
                            <input wire:model.live="client.zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Ort</label>
                            <input wire:model.live="client.city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Meta Details -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Dokumenten-Metadaten</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">{{ $mode === 'invoice' ? 'Rechnungsnummer' : 'Angebotsnummer' }}</label>
                            <input wire:model.live="docNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Kundennummer</label>
                            <input wire:model.live="client.clientNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Datum</label>
                            <input wire:model.live="docDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Leistungszeitraum</label>
                            <input wire:model.live="deliveryDate" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    @if ($mode === 'invoice')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Zahlungsziel (Tage)</label>
                                <input wire:model.live="dueDays" type="number" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Rabatt / Skonto (%)</label>
                                <input wire:model.live="discountRate" type="number" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Umsatzsteuer-Modus</label>
                            <select wire:model.live="taxMode" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                                <option value="standard">Standardbesteuerung (19% USt.)</option>
                                <option value="reverse">Reverse Charge § 13b UStG (Bauleistung)</option>
                                <option value="small">Kleinunternehmer § 19 UStG</option>
                                <option value="custom">Sonstige Steuerbefreiung (Freitext)</option>
                            </select>
                        </div>
                        @if ($taxMode === 'custom' || $taxMode === 'reverse')
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Begründung für 0% USt.</label>
                                <input wire:model.live="taxReasonText" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. Steuerschuldnerschaft des Leistungsempfängers nach § 13b UStG">
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Items Editor Table -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Positionen</h3>
                    <button wire:click="addItem" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition">+ Posten</button>
                </div>
                <div class="space-y-3">
                    @foreach ($items as $idx => $item)
                        <div wire:key="{{ $item['id'] }}" class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-2 relative">
                            <button wire:click="removeItem('{{ $item['id'] }}')" class="absolute top-2 right-2 text-rose-500 hover:text-rose-700 text-xs font-bold">✕</button>
                            <div class="grid grid-cols-6 gap-2">
                                <div class="col-span-1">
                                    <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Pos</label>
                                    <input wire:model.live="items.{{ $idx }}.pos_number" type="text" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-900">
                                </div>
                                <div class="col-span-5">
                                    <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Beschreibung</label>
                                    <textarea wire:model.live="items.{{ $idx }}.description" rows="2" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-900 font-sans"></textarea>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Menge</label>
                                    <input wire:model.live="items.{{ $idx }}.quantity" type="number" step="0.001" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-900">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Einheit</label>
                                    <input wire:model.live="items.{{ $idx }}.unit" type="text" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-900">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Einzel (€)</label>
                                    <input wire:model.live="items.{{ $idx }}.price" type="number" step="0.01" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-900">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-0.5">USt. (%)</label>
                                    <input wire:model.live="items.{{ $idx }}.vatRate" type="number" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-900">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Footnotes & Paynotes -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Zahlungskonditionen & Notizen</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Individueller Zahlungshinweis</label>
                        <textarea wire:model.live="customPaymentNote" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Bitte überweisen Sie den Betrag..."></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Gesetzlicher Hinweistext (Zusatz)</label>
                        <textarea wire:model.live="customLegalText" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. Freistellungsbescheinigung nach § 48b EStG liegt vor."></textarea>
                    </div>
                </div>
            </div>

            <!-- Save Action Button -->
            <button wire:click="saveDocument" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md shadow-blue-500/10 transition">
                💾 Archivieren / In Datenbank speichern
            </button>

            <!-- Documents Archive list -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Vorhandene {{ $mode === 'invoice' ? 'Rechnungen' : 'Angebote' }}</h3>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    @forelse ($savedDocs as $doc)
                        <div wire:click="loadSavedDoc('{{ $doc['id'] }}')" class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl cursor-pointer hover:bg-slate-100 transition flex justify-between items-center text-xs">
                            <div>
                                <p class="font-bold text-slate-900">{{ $doc['invoice_number'] ?? $doc['offer_number'] }}</p>
                                <p class="text-slate-500 font-medium">{{ date('d.m.Y', strtotime($doc['invoice_date'] ?? $doc['date'])) }}</p>
                            </div>
                            <p class="font-bold text-blue-700">{{ number_format($doc['total_net'] ?? $doc['total_net'], 2, ',', '.') }} €</p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 italic">Keine Dokumente gefunden.</p>
                    @endforelse
                </div>
            </div>

        </div>

        <!-- PREVIEW PANEL (RIGHT COLUMN - A4 BRIEFBOGEN) -->
        <div class="lg:col-span-7 flex justify-center preview-panel">
            
            <div class="paper-container" id="paperContainer">
                <div class="fold-mark-2"></div>

                <!-- Briefkopf Header -->
                <header class="letterhead-header">
                    <div class="logo-column">
                        <img src="{{ asset('logo.png') }}" alt="BT Bautechnik Logo" class="logo-img" style="height: 52px; width: auto; display: block;">
                    </div>
                    
                    <div class="contact-column">
                        <div class="company-name" id="viewProfileCompany">{{ $profile['company'] }}</div>
                        <div class="contact-label">Kontakt</div>
                        <div id="viewProfileAddress">{{ $profile['address'] }}</div>
                        <div id="viewProfileCity">{{ $profile['zip'] }} {{ $profile['city'] }}</div>
                        <div class="contact-label">Mail</div>
                        <div id="viewProfileMail">{{ $profile['mail'] }}</div>
                    </div>
                </header>

                <!-- Info block -->
                <div class="address-meta-container">
                    <section class="recipient-block">
                        <span class="sender-line" id="viewSenderLine">{{ $profile['company'] }} · {{ $profile['address'] }} · {{ $profile['zip'] }} {{ $profile['city'] }}</span>
                        <div class="recipient-address" id="viewRecipientAddress">
                            <strong>{{ $client['name'] ?: 'Musterkunde GmbH' }}</strong><br>
                            @if ($client['street']) {{ $client['street'] }}<br> @endif
                            @if ($client['zip'] || $client['city']) {{ $client['zip'] }} {{ $client['city'] }}<br> @endif
                            @if ($client['country'] && strtolower($client['country']) !== 'deutschland') {{ $client['country'] }} @endif
                        </div>
                    </section>

                    <section class="meta-block">
                        <div class="meta-label">Kundennummer:</div>
                        <div class="meta-value" id="viewClientNumber">{{ $client['clientNumber'] ?: 'KD-XXXX' }}</div>

                        <div class="meta-label">{{ $mode === 'invoice' ? 'Rechnungsnummer' : 'Angebotsnummer' }}:</div>
                        <div class="meta-value" id="viewInvoiceNumber" style="font-weight: 700;">{{ $docNumber ?: 'RE-XXXX' }}</div>

                        <div class="meta-label">Datum:</div>
                        <div class="meta-value" id="viewInvoiceDate">{{ date('d.m.Y', strtotime($docDate)) }}</div>

                        @if ($mode === 'invoice')
                            <div class="meta-label">Leistungsdatum:</div>
                            <div class="meta-value" id="viewDeliveryDate">{{ $deliveryDate }}</div>
                        @endif
                    </section>
                </div>

                <!-- Document Title -->
                <div class="invoice-title" style="margin-top: 15mm; margin-bottom: 5mm; font-family: 'Outfit', sans-serif;">
                    <h2 style="font-size: 20px; font-weight: 800; color: #1a1a1a; text-transform: uppercase; letter-spacing: 0.5px;">
                        {{ $mode === 'invoice' ? 'RECHNUNG' : 'ANGEBOT' }}
                    </h2>
                </div>

                <!-- Items Table -->
                <table class="invoice-table" style="width: 100%; border-collapse: collapse; margin-top: 5mm;">
                    <thead>
                        <tr style="border-bottom: 2px solid #0056b3; font-family: 'Outfit', sans-serif; font-size: 11px; text-transform: uppercase; color: #555;">
                            <th style="padding: 6px 4px; text-align: left; width: 8%;">Pos</th>
                            <th style="padding: 6px 4px; text-align: left;">Beschreibung</th>
                            <th style="padding: 6px 4px; text-align: right; width: 12%;">Menge</th>
                            <th style="padding: 6px 4px; text-align: right; width: 12%;">E-Preis</th>
                            <th style="padding: 6px 4px; text-align: right; width: 15%;">Gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr style="border-bottom: 1px solid #eee; font-size: 11px;">
                                <td style="padding: 8px 4px; vertical-align: top; font-weight: 500;">{{ $item['pos_number'] }}</td>
                                <td style="padding: 8px 4px; vertical-align: top; white-space: pre-line;">{!! e($item['description']) !!}</td>
                                <td style="padding: 8px 4px; vertical-align: top; text-align: right;">{{ number_format($item['quantity'], 2, ',', '.') }} {{ $item['unit'] }}</td>
                                <td style="padding: 8px 4px; vertical-align: top; text-align: right;">{{ number_format($item['price'], 2, ',', '.') }} €</td>
                                <td style="padding: 8px 4px; vertical-align: top; text-align: right; font-weight: 600;">{{ number_format(floatval($item['quantity']) * floatval($item['price']), 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Totals Section -->
                <div class="totals-section" style="margin-top: 6mm; font-size: 11px; width: 50%; margin-left: 50%;">
                    <div style="display: flex; justify-between; padding: 3px 0; border-bottom: 1px solid #ddd;">
                        <span style="flex: 1;">Netto-Zwischensumme:</span>
                        <span style="font-weight: 600; width: 80px; text-align: right;">{{ number_format($this->calculation['subtotal'], 2, ',', '.') }} €</span>
                    </div>
                    @if ($discountRate > 0)
                        <div style="display: flex; justify-between; padding: 3px 0; border-bottom: 1px solid #ddd; color: #28a745;">
                            <span style="flex: 1;">Rabatt ({{ $discountRate }}%):</span>
                            <span style="font-weight: 600; width: 80px; text-align: right;">-{{ number_format($this->calculation['discountValue'], 2, ',', '.') }} €</span>
                        </div>
                        <div style="display: flex; justify-between; padding: 3px 0; border-bottom: 1px solid #ddd;">
                            <span style="flex: 1;">Netto nach Rabatt:</span>
                            <span style="font-weight: 600; width: 80px; text-align: right;">{{ number_format($this->calculation['subtotalAfterDiscount'], 2, ',', '.') }} €</span>
                        </div>
                    @endif
                    @if ($taxMode === 'standard')
                        @foreach ($this->calculation['taxes'] as $rate => $val)
                            <div style="display: flex; justify-between; padding: 3px 0; border-bottom: 1px solid #ddd;">
                                <span style="flex: 1;">USt. {{ $rate }}%:</span>
                                <span style="font-weight: 600; width: 80px; text-align: right;">{{ number_format($val, 2, ',', '.') }} €</span>
                            </div>
                        @endforeach
                    @endif
                    <div style="display: flex; justify-between; padding: 5px 0; font-size: 13px; font-weight: 700; border-bottom: 2px double #0056b3; background-color: #f8f9fa;">
                        <span style="flex: 1; padding-left: 3px;">Gesamtsumme ({{ $taxMode === 'standard' ? 'Brutto' : 'Netto' }}):</span>
                        <span style="color: #0056b3; width: 80px; text-align: right; padding-right: 3px;">{{ number_format($this->calculation['grandTotal'], 2, ',', '.') }} €</span>
                    </div>
                </div>

                <!-- Tax Exemption Notice -->
                @if ($mode === 'invoice' && $taxMode !== 'standard')
                    <div class="tax-notice" style="margin-top: 6mm; font-size: 10px; border-left: 3px solid #0056b3; padding-left: 8px; font-style: italic; color: #555;">
                        @if ($taxMode === 'reverse')
                            {{ $taxReasonText ?: 'Steuerschuldnerschaft des Leistungsempfängers nach § 13b UStG' }}
                        @elseif ($taxMode === 'small')
                            Gemäß § 19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmerstatus).
                        @else
                            {{ $taxReasonText ?: 'Steuerfreie Leistung.' }}
                        @endif
                    </div>
                @endif

                <!-- Payment details note -->
                <div class="payment-terms" style="margin-top: 10mm; font-size: 10px; line-height: 1.4; color: #333;">
                    @if ($customPaymentNote)
                        <p style="white-space: pre-wrap;">{{ $customPaymentNote }}</p>
                    @else
                        @if ($mode === 'invoice')
                            <p>Bitte überweisen Sie den Rechnungsbetrag von <strong>{{ number_format($this->calculation['grandTotal'], 2, ',', '.') }} €</strong> unter Angabe der Rechnungsnummer <strong>{{ $docNumber }}</strong> bis zum <strong>{{ date('d.m.Y', strtotime($docDate . ' + ' . $dueDays . ' days')) }}</strong> (Zahlungsziel {{ $dueDays }} Tage) auf unser unten aufgeführtes Geschäftskonto.</p>
                        @else
                            <p>Dieses Angebot ist freibleibend. Bei Auftragserteilung gelten unsere allgemeinen Geschäftsbedingungen.</p>
                        @endif
                    @endif
                    
                    @if ($customLegalText)
                        <p style="margin-top: 3mm; white-space: pre-wrap; font-size: 9px; color: #666;">{{ $customLegalText }}</p>
                    @endif
                </div>

                <!-- Briefbogen Footer -->
                <footer class="letterhead-footer">
                    <div class="footer-col">
                        <strong id="viewFooterCompany">{{ $profile['company'] }}</strong><br>
                        Geschäftsführung:<br>
                        <span id="viewFooterManaging">{{ $profile['managing'] }}</span>
                    </div>
                    <div class="footer-col">
                        <strong>Firmensitz</strong><br>
                        <span id="viewFooterAddress">{!! nl2br(e($profile['address'] . "\n" . $profile['zip'] . " " . $profile['city'])) !!}</span>
                    </div>
                    <div class="footer-col">
                        <strong>Bankverbindung</strong><br>
                        IBAN:<br><span id="viewFooterIban">{{ $profile['iban'] }}</span><br>
                        BIC:<br><span id="viewFooterBic">{{ $profile['bic'] }}</span>
                    </div>
                    <div class="footer-col">
                        <strong>Registrierung</strong><br>
                        <span id="viewFooterRegistry">{{ $profile['registry'] }}</span><br>
                        <span id="viewFooterRegistryNumber">HRB-Nummer: {{ $profile['hrb'] }}</span><br>
                        <span id="viewFooterTaxNumber">Steuernummer: {{ $profile['taxId'] }}</span>
                        @if ($profile['vatId'])<br>USt-IdNr.: {{ $profile['vatId'] }}@endif
                    </div>
                </footer>
            </div>

        </div>

    </div>

    <!-- OpenAI Import Modal -->
    @if ($showAiModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🤖</span>
                        <h3 class="text-base font-extrabold text-white">KI-Freitext & Angebots-Import (OpenAI)</h3>
                    </div>
                    <button wire:click="$set('showAiModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Fügen Sie hier unstrukturierten Text (z.B. Leistungsbeschreibung, Subunternehmer-Angebot, E-Mail oder WhatsApp-Nachricht) ein. Die KI analysiert den Text und wandelt ihn automatisch in saubere LV-Positionen mit Mengen, Einheiten & Preisen um!
                    </p>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Unstrukturierter Text / Angebotstext</label>
                        <textarea wire:model="aiRawText" rows="7" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none font-sans" placeholder="Beispiel:&#10;Pos 1: 15 m² Flachdachabdichtung Bitumen für 45 EUR/m²&#10;Pos 2: 2 Stk Entwässerungsabläufe montieren je 120 EUR&#10;Pos 3: Pauschale Baustelleneinrichtung 350 EUR"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button type="button" wire:click="$set('showAiModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="button" wire:click="parseWithAi" wire:loading.attr="disabled" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-500/20 flex items-center gap-2">
                            <span wire:loading wire:target="parseWithAi">⌛ Analysiere mit OpenAI...</span>
                            <span wire:loading.remove wire:target="parseWithAi">✨ Per KI in Positionen umwandeln</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- KI Cover Letter Modal -->
    @if ($showCoverLetterModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-indigo-950 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">✉️</span>
                        <h3 class="text-base font-extrabold text-white">KI-E-Mail Anschreiben & Begleitschreiben</h3>
                    </div>
                    <button wire:click="$set('showCoverLetterModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs font-sans text-slate-800 leading-relaxed max-h-96 overflow-y-auto whitespace-pre-wrap selection:bg-indigo-100">{{ $coverLetterText }}</div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-xs text-slate-500">Formular inkl. Betreff & Höflichkeitsformeln</span>
                        <div class="flex space-x-3">
                            <button type="button" wire:click="$set('showCoverLetterModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Schließen</button>
                            <button type="button" onclick="navigator.clipboard.writeText(`{{ addslashes($coverLetterText) }}`); alert('E-Mail Anschreiben in Zwischenablage kopiert!');" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-500/20">
                                📋 In Zwischenablage kopieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- KI Offer Audit Modal -->
    @if ($showOfferAuditModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-amber-950 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🛡️</span>
                        <h3 class="text-base font-extrabold text-white">KI-Angebots-Check & Vollständigkeits-Prüfung</h3>
                    </div>
                    <button wire:click="$set('showOfferAuditModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="p-6 space-y-5">
                    <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-2xl p-4">
                        <div>
                            <span class="text-xs font-bold text-amber-800 uppercase tracking-wider">Vollständigkeits-Score</span>
                            <h4 class="text-2xl font-black text-amber-950">{{ $offerAuditResults['score'] ?? 100 }}/100 Punkte</h4>
                        </div>
                        <div class="text-3xl">
                            @if (($offerAuditResults['score'] ?? 100) >= 80) 🟢 @elseif (($offerAuditResults['score'] ?? 100) >= 50) 🟡 @else 🔴 @endif
                        </div>
                    </div>

                    @if (!empty($offerAuditResults['missing_positions']))
                        <div class="space-y-1">
                            <h4 class="text-xs font-extrabold text-slate-800 uppercase">Möglicherweise fehlende Baupositionen:</h4>
                            <ul class="list-disc list-inside text-xs text-rose-700 space-y-1 bg-rose-50 p-3 rounded-xl border border-rose-200">
                                @foreach ($offerAuditResults['missing_positions'] as $m)
                                    <li>{{ $m }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($offerAuditResults['pricing_warnings']))
                        <div class="space-y-1">
                            <h4 class="text-xs font-extrabold text-slate-800 uppercase">Preis- & Einheiten-Hinweise:</h4>
                            <ul class="list-disc list-inside text-xs text-amber-800 space-y-1 bg-amber-50 p-3 rounded-xl border border-amber-200">
                                @foreach ($offerAuditResults['pricing_warnings'] as $pw)
                                    <li>{{ $pw }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-700 leading-relaxed">
                        <strong>Einschätzung für die Geschäftsführung:</strong><br>
                        {{ $offerAuditResults['summary'] ?? 'Keine besonderen Auffälligkeiten im Angebot.' }}
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="button" wire:click="$set('showOfferAuditModal', false)" class="px-5 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold">Verstanden</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
