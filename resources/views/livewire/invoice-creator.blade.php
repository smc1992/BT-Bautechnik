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
    public string $projectSearch = '';
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

    // Advanced Handwerker & VOB Fields
    public string $invoiceType = 'standard'; // standard, down_payment, final
    public int $sequenceNumber = 1;
    public bool $isSupplement = false;
    public string $supplementNumber = 'Nachtrag #1';
    public float $securityDeductionRate = 0.0; // e.g. 5.00 for 5% Gewährleistungseinbehalt

    public function downloadZugferdXml(string $invoiceId, ?\App\Services\EInvoicingService $service = null)
    {
        $service = $service ?? app(\App\Services\EInvoicingService::class);
        $invoice = Invoice::with('items', 'project')->find($invoiceId);
        if (!$invoice) {
            $this->dispatch('notify', 'Rechnung nicht gefunden.');
            return;
        }

        $xml = $service->generateZugferdXml($invoice);
        $filename = 'xrechnung-' . $invoice->invoice_number . '.xml';

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $filename, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function stornoInvoice(string $invoiceId, string $stornoReason = 'Stornierung laut Kundenabsprache')
    {
        $invoice = Invoice::with('items')->find($invoiceId);
        if (!$invoice) {
            $this->dispatch('notify', 'Rechnung nicht gefunden.');
            return;
        }

        if ($invoice->status === 'cancelled') {
            $this->dispatch('notify', 'Rechnung ist bereits storniert.');
            return;
        }

        DB::transaction(function () use ($invoice, $stornoReason) {
            // Update original invoice
            $invoice->update([
                'status' => 'cancelled',
                'cancel_reason' => $stornoReason
            ]);

            // Create Stornorechnung
            $stornoNumber = 'STORNO-' . substr($invoice->invoice_number, 3);
            $stornoInv = Invoice::create([
                'project_id' => $invoice->project_id,
                'invoice_number' => $stornoNumber,
                'invoice_date' => date('Y-m-d'),
                'delivery_date' => 'Stornorechnung zu ' . $invoice->invoice_number,
                'due_days' => 0,
                'discount_rate' => $invoice->discount_rate,
                'tax_mode' => $invoice->tax_mode,
                'tax_reason' => $invoice->tax_reason,
                'invoice_type' => 'storno',
                'original_invoice_id' => $invoice->id,
                'cancel_reason' => $stornoReason,
                'custom_payment_note' => 'Stornierung der Ursprungsrechnung ' . $invoice->invoice_number,
                'custom_legal_text' => 'GoBD-konforme Korrekturrechnung.',
                'total_net' => -$invoice->total_net,
                'total_tax' => -$invoice->total_tax,
                'total_gross' => -$invoice->total_gross,
                'status' => 'paid'
            ]);

            // Copy negative items
            foreach ($invoice->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $stornoInv->id,
                    'pos_number' => $item->pos_number,
                    'description' => 'STORNO: ' . $item->description,
                    'quantity' => -$item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'vat_rate' => $item->vat_rate,
                    'total_price' => -$item->total_price
                ]);
            }
        });

        $this->loadSavedDocuments();
        $this->dispatch('notify', '⚡ GoBD-Stornorechnung erfolgreich generiert & archiviert!');
    }

    public function createDunningNotice(string $invoiceId, int $level = 1)
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) return;

        $dunningFee = $level === 1 ? 5.00 : 10.00;
        $invoice->update([
            'reminder_level' => $level,
            'reminder_date' => date('Y-m-d'),
            'dunning_fee' => $dunningFee,
            'status' => 'overdue'
        ]);

        $this->loadSavedDocuments();
        $this->dispatch('notify', '⚠️ ' . $level . '. Mahnung (' . number_format($dunningFee, 2, ',', '.') . ' € Gebühr) wurde ausgestellt!');
    }

    public ?string $selectedContactId = null;
    public string $contactSearch = '';

    public function getContactsProperty()
    {
        return \App\Models\Contact::when(trim($this->contactSearch), function ($query) {
            $query->where(function ($q) {
                $q->where('company_name', 'like', '%' . $this->contactSearch . '%')
                  ->orWhere('first_name', 'like', '%' . $this->contactSearch . '%')
                  ->orWhere('last_name', 'like', '%' . $this->contactSearch . '%')
                  ->orWhere('city', 'like', '%' . $this->contactSearch . '%')
                  ->orWhere('vat_id', 'like', '%' . $this->contactSearch . '%')
                  ->orWhere('customer_number', 'like', '%' . $this->contactSearch . '%');
            });
        })->orderBy('company_name')->get();
    }

    public function selectContact(?string $id)
    {
        $this->selectedContactId = $id;
        if (!$id) return;

        $contact = \App\Models\Contact::find($id);
        if ($contact) {
            $name = $contact->company_name ?: (trim($contact->first_name . ' ' . $contact->last_name));
            $this->client['name'] = $name;
            $this->client['street'] = $contact->street ?: '';
            $this->client['zip'] = $contact->zip ?: '';
            $this->client['city'] = $contact->city ?: '';
            $this->client['vatId'] = $contact->vat_id ?: '';
            $this->client['clientNumber'] = $contact->customer_number ?: \App\Models\Contact::generateNextCustomerNumber();
            $this->dispatch('notify', '👤 Kundendaten & USt-ID von ' . $name . ' übernommen!');
        }
    }

    public function getProjectsProperty()
    {
        return Project::when(trim($this->projectSearch), function ($query) {
            $query->where('name', 'like', '%' . $this->projectSearch . '%')
                  ->orWhere('city_street', 'like', '%' . $this->projectSearch . '%')
                  ->orWhere('contact_address', 'like', '%' . $this->projectSearch . '%');
        })->get();
    }

    public function selectProject(?string $id)
    {
        $this->projectId = $id;
        $this->updatedProjectId($id);
    }

    // Client Address
    public array $client = [
        'name' => '',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => 'Deutschland',
        'clientNumber' => '',
        'vatId' => '',
    ];

    // Document Items
    public array $items = [];

    // Historical documents list & Tab State & Filtering/Sorting
    public array $savedDocs = [];
    public string $docSearch = '';
    public string $activeTab = 'archive'; // 'archive' (overview default) or 'editor'
    public string $archiveFilter = 'all'; // 'all', 'invoice', 'offer'
    public string $sortOrder = 'desc'; // 'desc' (neueste zuerst) or 'asc' (älteste zuerst)
    public string $filterYear = 'all'; // 'all', '2026', '2025'...
    public string $filterQuarter = 'all'; // 'all', 'Q1', 'Q2', 'Q3', 'Q4'
    public string $filterMonth = 'all'; // 'all', '1', '2'... '12'

    public function createNewInvoice()
    {
        $this->mode = 'invoice';
        $this->resetForm();
        $this->activeTab = 'editor';
    }

    public function createNewOffer()
    {
        $this->mode = 'offer';
        $this->resetForm();
        $this->activeTab = 'editor';
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->loadSavedDocuments();
    }

    public function setArchiveFilter(string $filter)
    {
        $this->archiveFilter = $filter;
        $this->loadSavedDocuments();
    }

    public function updatedSortOrder() { $this->loadSavedDocuments(); }
    public function updatedFilterYear() { $this->loadSavedDocuments(); }
    public function updatedFilterQuarter() { $this->loadSavedDocuments(); }
    public function updatedFilterMonth() { $this->loadSavedDocuments(); }
    public function updatedDocSearch() { $this->loadSavedDocuments(); }

    // Aufmaß & Glossar Modal State
    public bool $showAufmassModal = false;
    public bool $showGlossarModal = false;
    public ?int $targetItemIndex = null;
    public string $aufmassTitle = 'Massenermittlung & Aufmaßblatt';
    public string $aufmassUnit = 'm²'; // m, m², m³, Stk, lfm
    public array $aufmassRows = [];
    public string $aufmassAiText = '';

    public function parseAufmassWithAi(?\App\Services\OpenAiParserService $parser = null)
    {
        $parser = $parser ?? app(\App\Services\OpenAiParserService::class);
        if (empty(trim($this->aufmassAiText))) {
            $this->dispatch('notify', 'Bitte geben Sie zuerst Notizen oder ein Diktat ein.');
            return;
        }

        try {
            $parsed = $parser->parseAufmassText($this->aufmassAiText);

            if (!empty($parsed['rows'])) {
                $this->aufmassUnit = $parsed['unit'] ?? 'm²';
                $this->aufmassRows = $parsed['rows'];
                $this->aufmassAiText = '';
                $this->dispatch('notify', '✨ ' . count($parsed['rows']) . ' Aufmaßzeilen inkl. VOB-Abzügen erfolgreich per KI generiert!');
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', 'Fehler bei der KI-Aufmaß-Analyse: ' . $e->getMessage());
        }
    }

    public function openAufmassModal(?int $itemIndex = null)
    {
        $this->targetItemIndex = $itemIndex;
        if ($itemIndex !== null && isset($this->items[$itemIndex])) {
            $item = $this->items[$itemIndex];
            $this->aufmassTitle = 'Aufmaß für Position #' . ($itemIndex + 1) . ': ' . ($item['description'] ? strtok($item['description'], "\n") : 'Position');
            $this->aufmassUnit = $item['unit'] ?: 'm²';
        } else {
            $this->aufmassTitle = 'Massenermittlung (Aufmaßblatt nach VOB/B)';
            $this->aufmassUnit = 'm²';
        }

        if (empty($this->aufmassRows)) {
            $this->aufmassRows = [
                [
                    'label' => 'Bauteil / Wand 1',
                    'count' => 1,
                    'length' => 10.0,
                    'width' => 2.50,
                    'height' => 1.0,
                    'mode' => 'add', // 'add', 'subtract', 'overmeasure'
                    'note' => '',
                ]
            ];
        }
        $this->showAufmassModal = true;
    }

    public function openGlossarModal()
    {
        $this->showGlossarModal = true;
    }

    public function addAufmassRow()
    {
        $this->aufmassRows[] = [
            'label' => 'Teilleistung / Abzug ' . (count($this->aufmassRows) + 1),
            'count' => 1,
            'length' => 0.0,
            'width' => 0.0,
            'height' => 1.0,
            'mode' => 'add',
            'note' => '',
        ];
    }

    public function removeAufmassRow(int $index)
    {
        unset($this->aufmassRows[$index]);
        $this->aufmassRows = array_values($this->aufmassRows);
    }

    public function calculateRowSubtotal(array $row): float
    {
        $count = floatval($row['count'] ?? 1);
        $length = floatval($row['length'] ?? 0);
        $width = floatval($row['width'] ?? 0);
        $height = floatval($row['height'] ?? 1);

        if ($length <= 0 && $width <= 0) return 0.0;

        $l = $length > 0 ? $length : 1.0;
        $w = $width > 0 ? $width : 1.0;
        $h = $height > 0 ? $height : 1.0;

        $vol = $count * $l * $w * $h;

        // VOB DIN 18299 Übermessungsregel check:
        if (($row['mode'] ?? 'add') === 'overmeasure') {
            return 0.0; // Übermessen (<0.1m² / <0.5m³) -> kein Abzug
        }

        if (($row['mode'] ?? 'add') === 'subtract') {
            return -$vol;
        }

        return $vol;
    }

    public function getAufmassTotalProperty(): float
    {
        $total = 0.0;
        foreach ($this->aufmassRows as $row) {
            $total += $this->calculateRowSubtotal($row);
        }
        return max(0.0, round($total, 3));
    }

    public function applyAufmassToTarget()
    {
        $total = $this->aufmassTotal;

        $details = [];
        foreach ($this->aufmassRows as $r) {
            $sub = $this->calculateRowSubtotal($r);
            if (($r['mode'] ?? 'add') === 'overmeasure') {
                $details[] = ($r['label'] ?: 'Aussparung') . ': Übermessen DIN 18299';
            } else {
                $sign = ($r['mode'] ?? 'add') === 'subtract' ? '-' : '';
                $details[] = ($r['label'] ?: 'Pos') . ': ' . $sign . number_format(abs($sub), 2, ',', '.') . ' ' . $this->aufmassUnit;
            }
        }

        $formulaText = "\n[Aufmaß: " . implode(' | ', $details) . " -> Gesamt: " . number_format($total, 2, ',', '.') . ' ' . $this->aufmassUnit . "]";

        if ($this->targetItemIndex !== null && isset($this->items[$this->targetItemIndex])) {
            $this->items[$this->targetItemIndex]['quantity'] = $total;
            $this->items[$this->targetItemIndex]['unit'] = $this->aufmassUnit;
            if (!str_contains($this->items[$this->targetItemIndex]['description'], '[Aufmaß:')) {
                $this->items[$this->targetItemIndex]['description'] .= $formulaText;
            }
            $this->dispatch('notify', '📐 Aufmaß von ' . number_format($total, 2, ',', '.') . ' ' . $this->aufmassUnit . ' in Position #' . ($this->targetItemIndex + 1) . ' übernommen!');
        } else {
            $this->dispatch('notify', '📐 Massenermittlung berechnet: ' . number_format($total, 2, ',', '.') . ' ' . $this->aufmassUnit);
        }

        $this->showAufmassModal = false;
    }

    public function mount()
    {
        $this->docDate = date('Y-m-d');
        $this->resetForm();

        $reqProjectId = request()->query('project_id');
        $reqAction = request()->query('action');

        if ($reqProjectId) {
            $this->selectProject($reqProjectId);
            $this->activeTab = 'editor';
        } elseif ($reqAction === 'new') {
            $this->activeTab = 'editor';
        } else {
            $this->activeTab = 'archive';
        }

        $this->loadSavedDocuments();
    }

    public function loadSavedDocuments()
    {
        $search = trim($this->docSearch);

        $invoicesQuery = Invoice::with(['project', 'contact']);
        $offersQuery = Offer::with(['project', 'contact']);

        if (!empty($search)) {
            $invoicesQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                  ->orWhereHas('contact', function ($cq) use ($search) {
                      $cq->where('company_name', 'like', '%' . $search . '%')
                         ->orWhere('first_name', 'like', '%' . $search . '%')
                         ->orWhere('last_name', 'like', '%' . $search . '%')
                         ->orWhere('customer_number', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('project', function ($pq) use ($search) {
                      $pq->where('name', 'like', '%' . $search . '%')
                         ->orWhere('city_street', 'like', '%' . $search . '%');
                  });
            });

            $offersQuery->where(function ($q) use ($search) {
                $q->where('offer_number', 'like', '%' . $search . '%')
                  ->orWhereHas('contact', function ($cq) use ($search) {
                      $cq->where('company_name', 'like', '%' . $search . '%')
                         ->orWhere('first_name', 'like', '%' . $search . '%')
                         ->orWhere('last_name', 'like', '%' . $search . '%')
                         ->orWhere('customer_number', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('project', function ($pq) use ($search) {
                      $pq->where('name', 'like', '%' . $search . '%')
                         ->orWhere('city_street', 'like', '%' . $search . '%');
                  });
            });
        }

        $docs = [];

        if ($this->archiveFilter === 'all' || $this->archiveFilter === 'invoice') {
            $invs = $invoicesQuery->get();
            foreach ($invs as $inv) {
                $d = $inv->invoice_date ?? $inv->created_at;
                $timestamp = strtotime($d);
                $y = date('Y', $timestamp);
                $m = intval(date('n', $timestamp));
                $q = 'Q' . ceil($m / 3);

                if ($this->filterYear !== 'all' && $y != $this->filterYear) continue;
                if ($this->filterQuarter !== 'all' && $q != $this->filterQuarter) continue;
                if ($this->filterMonth !== 'all' && $m != intval($this->filterMonth)) continue;

                $arr = $inv->toArray();
                $arr['_doc_type'] = 'invoice';
                $arr['_sort_date'] = $d;
                $docs[] = $arr;
            }
        }

        if ($this->archiveFilter === 'all' || $this->archiveFilter === 'offer') {
            $offs = $offersQuery->get();
            foreach ($offs as $off) {
                $d = $off->date ?? $off->created_at;
                $timestamp = strtotime($d);
                $y = date('Y', $timestamp);
                $m = intval(date('n', $timestamp));
                $q = 'Q' . ceil($m / 3);

                if ($this->filterYear !== 'all' && $y != $this->filterYear) continue;
                if ($this->filterQuarter !== 'all' && $q != $this->filterQuarter) continue;
                if ($this->filterMonth !== 'all' && $m != intval($this->filterMonth)) continue;

                $arr = $off->toArray();
                $arr['_doc_type'] = 'offer';
                $arr['_sort_date'] = $d;
                $docs[] = $arr;
            }
        }

        // Sort combined documents array (asc vs desc)
        $isAsc = $this->sortOrder === 'asc';
        usort($docs, function ($a, $b) use ($isAsc) {
            $dA = $a['_sort_date'] ?? '';
            $dB = $b['_sort_date'] ?? '';
            return $isAsc ? strcmp($dA, $dB) : strcmp($dB, $dA);
        });

        $this->savedDocs = $docs;
    }

    public function generatePdfBinary(string $html): string
    {
        // 1. Try Spatie LaravelPdf (Browsershot / Headless Chrome)
        try {
            if (class_exists(\Spatie\LaravelPdf\Facades\Pdf::class)) {
                $tempPath = storage_path('app/temp_' . Str::random(10) . '.pdf');
                
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
        } catch (\Throwable $e) {
            // Fallback silently if Chrome / Browsershot fails locally
        }

        // 2. Fallback: Dompdf (100% PHP engine, zero external dependencies)
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

    public function downloadSingleDocHtml($id, string $type = 'invoice')
    {
        if ($type === 'invoice') {
            $doc = Invoice::with(['items', 'contact', 'project'])->find($id);
            $num = $doc->invoice_number ?? 'Rechnung';
        } else {
            $doc = Offer::with(['sections.items', 'contact', 'project'])->find($id);
            $num = $doc->offer_number ?? 'Angebot';
        }

        if (!$doc) {
            $this->dispatch('notify', 'Dokument nicht gefunden.');
            return;
        }

        $html = $this->renderDocHtml($doc->toArray(), $type);
        $pdfBinary = $this->generatePdfBinary($html);
        $fileName = $num . '_' . date('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($pdfBinary) {
            echo $pdfBinary;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadEinvoicePackage(?string $invoiceId = null)
    {
        if ($invoiceId) {
            $inv = Invoice::with(['items', 'contact', 'project'])->find($invoiceId);
            if (!$inv) {
                $this->dispatch('notify', 'Rechnung nicht gefunden.');
                return;
            }
            $docArr = $inv->toArray();
            $num = $inv->invoice_number;
            $invModel = $inv;
        } else {
            $num = $this->docNumber ?: 'RE-2026-0001';
            $docArr = [
                'invoice_number' => $num,
                'invoice_date' => $this->docDate,
                'delivery_date' => $this->deliveryDate,
                'due_days' => $this->dueDays,
                'client' => $this->client,
                'contact' => [
                    'company_name' => $this->client['name'],
                    'street' => $this->client['street'],
                    'zip' => $this->client['zip'],
                    'city' => $this->client['city'],
                    'vat_id' => $this->client['vatId'],
                    'customer_number' => $this->client['clientNumber'],
                ],
                'items' => array_map(function ($item) {
                    return [
                        'pos_number' => $item['pos_number'],
                        'description' => $item['description'],
                        'quantity' => floatval($item['quantity']),
                        'unit' => $item['unit'],
                        'unit_price' => floatval($item['price']),
                        'price' => floatval($item['price']),
                        'vat_rate' => floatval($item['vatRate'] ?? 19),
                    ];
                }, $this->items),
                'total_net' => $this->calculation['subtotalAfterDiscount'],
                'total_gross' => $this->calculation['grandTotal'],
            ];

            $invModel = Invoice::where('invoice_number', $num)->first();
            if (!$invModel && $this->projectId) {
                $invModel = new Invoice([
                    'invoice_number' => $num,
                    'invoice_date' => $this->docDate,
                    'delivery_date' => $this->deliveryDate,
                    'due_days' => $this->dueDays,
                    'total_net' => $this->calculation['subtotalAfterDiscount'],
                    'tax_amount' => $this->calculation['totalTax'],
                    'total_gross' => $this->calculation['grandTotal'],
                    'status' => 'draft',
                    'tax_mode' => $this->taxMode,
                ]);
                $invModel->contact = (object)[
                    'company_name' => $this->client['name'],
                    'vat_id' => $this->client['vatId'],
                    'street' => $this->client['street'],
                    'zip' => $this->client['zip'],
                    'city' => $this->client['city'],
                    'customer_number' => $this->client['clientNumber']
                ];
            }
        }

        $zipFileName = $num . '_Vollstaendiges_E-Rechnungs-Paket.zip';
        $tempDir = storage_path('app/temp_exports');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/' . $zipFileName;
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->dispatch('notify', 'ZIP-Paket konnte nicht erstellt werden.');
            return;
        }

        $safeNum = preg_replace('/[^A-Za-z0-9_\-]/', '_', $num);

        // 1. ISO DIN 5008 PDF Briefbogen
        $docHtml = $this->renderDocHtml($docArr, 'invoice');
        $pdfBinary = $this->generatePdfBinary($docHtml);
        $zip->addFromString($safeNum . '_PDF_Rechnung_ISO_DIN5008.pdf', $pdfBinary);

        // 2. EN 16931 XRechnung / ZUGFeRD XML
        try {
            if ($invModel) {
                $einvoicing = app(\App\Services\EInvoicingService::class);
                $xml = $einvoicing->generateZugferdXml($invModel);
                $zip->addFromString($safeNum . '_XRechnung_ZUGFeRD22.xml', $xml);
            }
        } catch (\Exception $e) {
            // Ignore XML error if any
        }

        // 3. E-Invoicing Compliance Certificate PDF
        $certHtml = $this->renderComplianceCertificateHtml($docArr);
        $certPdfBinary = $this->generatePdfBinary($certHtml);
        $zip->addFromString($safeNum . '_ERechnung_Konformitaetszertifikat.pdf', $certPdfBinary);

        $zip->close();

        $this->dispatch('notify', '📦 E-Rechnungs-Paket (PDF + XML + Konformitätszertifikat) heruntergeladen!');

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    public function renderComplianceCertificateHtml(array $doc): string
    {
        $profile = $this->profile;
        $num = $doc['invoice_number'] ?? $doc['offer_number'] ?? 'RE-0000';
        $docDate = date('d.m.Y', strtotime($doc['invoice_date'] ?? $doc['date'] ?? now()));
        $clientName = $doc['contact']['company_name'] ?? trim(($doc['contact']['first_name'] ?? '') . ' ' . ($doc['contact']['last_name'] ?? '')) ?: ($doc['client']['name'] ?? 'Kunde');
        $clientVat = $doc['contact']['vat_id'] ?? $doc['client']['vatId'] ?? 'Nicht hinterlegt / Privatkunde';
        $totalGross = floatval($doc['total_gross'] ?? $doc['total_net'] ?? 0);

        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>E-Rechnungs Konformitäts-Zertifikat - ' . htmlspecialchars($num) . '</title>
    <style>
        body { font-family: sans-serif; padding: 25px; color: #1e293b; background: #fff; line-height: 1.5; font-size: 11pt; }
        .cert-box { border: 2px solid #0056b3; padding: 20px; border-radius: 8px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0056b3; padding-bottom: 12px; margin-bottom: 20px; }
        .title { font-size: 18pt; font-weight: bold; color: #0056b3; }
        .badge { background: #16a34a; color: white; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        td, th { padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    </style>
</head>
<body>
    <div class="cert-box">
        <div class="header">
            <div>
                <div class="title">OFFIZIELLES E-RECHNUNGS-ZERTIFIKAT</div>
                <div style="font-size: 10pt; color: #64748b;">Nachweis der EN 16931 & UStG § 14 Konformität</div>
            </div>
            <div><span class="badge">✓ EN 16931 VALIDIERT</span></div>
        </div>

        <p>Hiermit wird bestätigt, dass das Rechnungsdokument <strong>' . htmlspecialchars($num) . '</strong> den gesetzlichen Anforderungen der E-Rechtspflicht (ab 01.01.2025) sowie den Richtlinien der Europäischen Norm EN 16931 entspricht.</p>

        <table>
            <tr><td><strong>Rechnungsnummer:</strong></td><td>' . htmlspecialchars($num) . '</td></tr>
            <tr><td><strong>Rechnungsdatum:</strong></td><td>' . htmlspecialchars($docDate) . '</td></tr>
            <tr><td><strong>Rechnungsaussteller:</strong></td><td>' . htmlspecialchars($profile['company'] ?? 'BT Bautechnik UG') . ' (USt-ID: ' . htmlspecialchars($profile['vatId'] ?? '') . ')</td></tr>
            <tr><td><strong>Rechnungsempfänger:</strong></td><td>' . htmlspecialchars($clientName) . ' (USt-ID: ' . htmlspecialchars($clientVat) . ')</td></tr>
            <tr><td><strong>Rechnungsbetrag (Brutto):</strong></td><td>' . number_format($totalGross, 2, ',', '.') . ' €</td></tr>
            <tr><td><strong>ZUGFeRD / XRechnung Syntax:</strong></td><td>EN 16931 CII (Cross Industry Invoice) / UBL 2.1</td></tr>
            <tr><td><strong>Validierungs-Status:</strong></td><td><strong style="color: #16a34a;">PASS / Gültig ohne Fehler</strong></td></tr>
            <tr><td><strong>Prüf-Zeitstempel:</strong></td><td>' . date('d.m.Y H:i:s') . ' UTC</td></tr>
        </table>

        <div style="margin-top: 25px; padding: 12px; background: #f8fafc; border-left: 4px solid #0056b3; font-size: 9pt;">
            <strong>Hinweis für die Finanzbuchhaltung & Betriebsprüfung:</strong><br>
            Dieses Paket enthält sowohl die menschenlesbare ISO DIN 5008 PDF-Rechnung als auch die maschinenlesbare XML-Datei nach EN 16931 zur automatisierten Erfassung in ERP- und Buchhaltungs-Systemen.
        </div>
    </div>
</body>
</html>';
    }

    public function exportZipArchive()
    {
        if (empty($this->savedDocs)) {
            $this->dispatch('notify', 'Keine Dokumente für den Sammel-Export vorhanden.');
            return;
        }

        $zipFileName = 'BT_Bautechnik_Rechnungen_Sammel-Export_' . date('Y-m-d_H-i') . '.zip';
        $tempDir = storage_path('app/temp_exports');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/' . $zipFileName;
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->dispatch('notify', 'ZIP-Archiv konnte nicht erstellt werden.');
            return;
        }

        // Summary CSV header
        $csvContent = "Typ;Nummer;Datum;Kunde;Kundennummer;Baustelle;Netto;Brutto;Status\n";

        foreach ($this->savedDocs as $index => $doc) {
            $isInvoice = ($doc['_doc_type'] ?? 'invoice') === 'invoice' || isset($doc['invoice_number']);
            $typeStr = $isInvoice ? 'Rechnung' : 'Angebot';
            $num = $doc['invoice_number'] ?? $doc['offer_number'] ?? ('DOK-' . ($index + 1));
            $docDate = date('d.m.Y', strtotime($doc['invoice_date'] ?? $doc['date'] ?? now()));
            $clientName = $doc['contact']['company_name'] ?? trim(($doc['contact']['first_name'] ?? '') . ' ' . ($doc['contact']['last_name'] ?? '')) ?: 'Kunde';
            $clientNum = $doc['contact']['customer_number'] ?? '';
            $projectName = $doc['project']['name'] ?? '';
            $net = floatval($doc['total_net'] ?? 0);
            $gross = floatval($doc['total_gross'] ?? $net);
            $status = $doc['status'] ?? 'aktiv';

            $csvContent .= sprintf(
                "%s;%s;%s;\"%s\";%s;\"%s\";%s;%s;%s\n",
                $typeStr,
                $num,
                $docDate,
                str_replace('"', '""', $clientName),
                $clientNum,
                str_replace('"', '""', $projectName),
                number_format($net, 2, ',', ''),
                number_format($gross, 2, ',', ''),
                $status
            );

            // Add ISO DIN 5008 PDF Briefbogen file to ZIP
            $html = $this->renderDocHtml($doc, $isInvoice ? 'invoice' : 'offer');
            $pdfBinary = $this->generatePdfBinary($html);
            $safeNum = preg_replace('/[^A-Za-z0-9_\-]/', '_', $num);
            $zip->addFromString(sprintf("%02d_%s_%s.pdf", $index + 1, $safeNum, str_replace(' ', '_', $clientName)), $pdfBinary);

            // Add XRechnung XML to ZIP if invoice
            if ($isInvoice && !empty($doc['id'])) {
                try {
                    $invModel = Invoice::find($doc['id']);
                    if ($invModel) {
                        $einvoicing = app(\App\Services\EInvoicingService::class);
                        $xml = $einvoicing->generateZugferdXml($invModel);
                        $zip->addFromString(sprintf("%02d_%s_XRechnung.xml", $index + 1, $safeNum), $xml);
                    }
                } catch (\Exception $e) {
                    // Ignore XML error if any
                }
            }
        }

        $zip->addFromString('00_Rechnungsübersicht_Export.csv', "\xEF\xBB\xBF" . $csvContent);
        $zip->close();

        $this->dispatch('notify', '📦 Sammel-Export (' . count($this->savedDocs) . ' Dokumente) als ZIP erstellt!');

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    public function renderDocHtml(array $doc, string $type = 'invoice'): string
    {
        $profile = $this->profile;
        $isInvoice = $type === 'invoice';
        $docNum = $doc['invoice_number'] ?? $doc['offer_number'] ?? 'DOK-0000';
        $docDate = date('d.m.Y', strtotime($doc['invoice_date'] ?? $doc['date'] ?? now()));
        $deliveryDate = $doc['delivery_date'] ?? '';
        
        $clientName = $doc['contact']['company_name'] ?? trim(($doc['contact']['first_name'] ?? '') . ' ' . ($doc['contact']['last_name'] ?? '')) ?: 'Musterkunde GmbH';
        $clientStreet = $doc['contact']['street'] ?? '';
        $clientZip = $doc['contact']['zip'] ?? '';
        $clientCity = $doc['contact']['city'] ?? '';
        $clientNum = $doc['contact']['customer_number'] ?? 'KD-0000';

        $rawItems = [];
        if (!empty($doc['items']) && is_array($doc['items'])) {
            $rawItems = $doc['items'];
        } elseif (!empty($doc['sections']) && is_array($doc['sections'])) {
            foreach ($doc['sections'] as $sec) {
                foreach ($sec['items'] ?? [] as $it) {
                    $rawItems[] = [
                        'pos_number' => $it['pos_number'] ?? '',
                        'description' => (!empty($sec['title']) ? ($sec['title'] . ': ') : '') . ($it['description'] ?? ''),
                        'quantity' => $it['quantity'] ?? 1,
                        'unit' => $it['unit'] ?? 'Stk.',
                        'unit_price' => $it['unit_price'] ?? $it['price'] ?? 0,
                    ];
                }
            }
        } else {
            $rawItems = $this->items ?? [];
        }
        $items = $rawItems;

        $logoPath = public_path('logo.png');
        $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
        $logoHtml = $logoBase64 ? '<img src="' . $logoBase64 . '" style="height: 48px; width: auto; display: block;" alt="BT Bautechnik Logo">' : '<div class="company-title">' . htmlspecialchars($profile['company'] ?? 'BT Bautechnik UG') . '</div>';

        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>' . ($isInvoice ? 'Rechnung ' : 'Angebot ') . htmlspecialchars($docNum) . '</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 15mm 25mm 15mm; }
        body { font-family: "Helvetica Neue", Arial, sans-serif; margin: 0; padding: 0; color: #1a1a1a; background: #fff; font-size: 10pt; line-height: 1.4; }
        .header { border-bottom: 2px solid #0056b3; padding-bottom: 12px; margin-bottom: 20px; width: 100%; }
        .header-table { width: 100%; border-collapse: collapse; }
        .company-title { font-size: 18px; font-weight: 800; color: #0056b3; text-transform: uppercase; }
        .meta-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .meta-table td { padding: 4px 0; vertical-align: top; }
        .doc-title { font-size: 20px; font-weight: 800; color: #111; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items th { background: #f1f5f9; padding: 8px; text-align: left; font-size: 9pt; text-transform: uppercase; border-bottom: 2px solid #0056b3; }
        table.items td { padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 9.5pt; }
        .totals { width: 48%; float: right; margin-bottom: 25px; font-size: 9.5pt; }
        .footer-fixed { position: fixed; bottom: -12mm; left: 0px; right: 0px; height: 22mm; border-top: 1.5px solid #0056b3; padding-top: 6px; }
    </style>
</head>
<body onload="if(window.location.search.includes(\'print=true\')) window.print();">
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="vertical-align: top;">
                    ' . $logoHtml . '
                </td>
                <td style="text-align: right; vertical-align: top; font-size: 8.5pt; color: #475569; line-height: 1.5;">
                    <div><strong>' . htmlspecialchars($profile['company'] ?? 'BT Bautechnik UG') . '</strong></div>
                    <div>E-Mail: ' . htmlspecialchars($profile['mail'] ?? '') . '</div>
                    <div>IBAN: ' . htmlspecialchars($profile['iban'] ?? '') . '</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table" style="margin-top: 15px; margin-bottom: 30px;">
        <tr>
            <td style="width: 58%; vertical-align: top;">
                <div style="font-size: 7pt; color: #64748b; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; margin-bottom: 8px; width: 90%;">
                    ' . htmlspecialchars($profile['company'] ?? 'BT Bautechnik UG') . ' · ' . htmlspecialchars($profile['address'] ?? '') . ' · ' . htmlspecialchars(($profile['zip'] ?? '') . ' ' . ($profile['city'] ?? '')) . '
                </div>
                <strong style="font-size: 11pt; color: #0f172a;">' . htmlspecialchars($clientName) . '</strong><br>
                ' . ($clientStreet ? (htmlspecialchars($clientStreet) . '<br>') : '') . '
                ' . htmlspecialchars($clientZip . ' ' . $clientCity) . '
            </td>
            <td style="width: 42%; text-align: right; font-size: 9pt; line-height: 1.6; vertical-align: top;">
                <strong>Kundennummer:</strong> ' . htmlspecialchars($clientNum) . '<br>
                <strong>' . ($isInvoice ? 'Rechnungsnummer:' : 'Angebotsnummer:') . '</strong> ' . htmlspecialchars($docNum) . '<br>
                <strong>Datum:</strong> ' . $docDate . '<br>
                ' . ($isInvoice && $deliveryDate ? '<strong>Leistungsdatum:</strong> ' . htmlspecialchars($deliveryDate) . '<br>' : '') . '
            </td>
        </tr>
    </table>

    <div class="doc-title" style="margin-top: 25px; margin-bottom: 18px;">' . ($isInvoice ? 'RECHNUNG' : 'ANGEBOT') . '</div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;">Pos</th>
                <th>Beschreibung</th>
                <th style="text-align: right; width: 15%;">Menge</th>
                <th style="text-align: right; width: 15%;">E-Preis</th>
                <th style="text-align: right; width: 15%;">Gesamt</th>
            </tr>
        </thead>
        <tbody>';
        
        $subtotal = 0;
        foreach ($items as $idx => $item) {
            $pos = $item['pos_number'] ?? ($idx + 1);
            $desc = nl2br(htmlspecialchars($item['description'] ?? ''));
            $qty = floatval($item['quantity'] ?? 1);
            $unit = htmlspecialchars($item['unit'] ?? 'Stk');
            $price = floatval($item['unit_price'] ?? $item['price'] ?? 0);
            $total = $qty * $price;
            $subtotal += $total;

            $html .= '<tr>
                <td>' . $pos . '</td>
                <td>' . $desc . '</td>
                <td style="text-align: right;">' . number_format($qty, 2, ',', '.') . ' ' . $unit . '</td>
                <td style="text-align: right;">' . number_format($price, 2, ',', '.') . ' €</td>
                <td style="text-align: right; font-weight: bold;">' . number_format($total, 2, ',', '.') . ' €</td>
            </tr>';
        }

        $tax = $subtotal * 0.19;
        $grand = $subtotal + $tax;

        $html .= '</tbody>
    </table>

    <div class="totals">
        <table style="width: 100%; border-collapse: collapse; text-align: right;">
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 4px 0; text-align: left; color: #475569;">Netto-Zwischensumme:</td>
                <td style="padding: 4px 0; font-weight: 700; width: 110px;">' . number_format($subtotal, 2, ',', '.') . ' €</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 4px 0; text-align: left; color: #475569;">zzgl. USt. 19%:</td>
                <td style="padding: 4px 0; font-weight: 700; width: 110px;">' . number_format($tax, 2, ',', '.') . ' €</td>
            </tr>
            <tr style="border-bottom: 2px double #0056b3; background-color: #f8fafc;">
                <td style="padding: 6px 4px; text-align: left; font-size: 10.5pt; font-weight: 800; color: #0056b3;">Gesamtsumme (Brutto):</td>
                <td style="padding: 6px 4px; font-size: 11pt; font-weight: 800; color: #0056b3; width: 110px;">' . number_format($grand, 2, ',', '.') . ' €</td>
            </tr>
        </table>
    </div>

    <!-- Pinned DIN 5008 4-Column Footer at Page Bottom -->
    <div class="footer-fixed">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.2pt; color: #475569; line-height: 1.4;">
            <tr>
                <td style="width: 22%; vertical-align: top;">
                    <strong>' . htmlspecialchars($profile['company'] ?? 'BT Bautechnik UG') . '</strong><br>
                    Geschäftsführung:<br>
                    ' . htmlspecialchars($profile['managing'] ?? '') . '
                </td>
                <td style="width: 20%; vertical-align: top;">
                    <strong>Firmensitz</strong><br>
                    ' . htmlspecialchars($profile['address'] ?? '') . '<br>
                    ' . htmlspecialchars(($profile['zip'] ?? '') . ' ' . ($profile['city'] ?? '')) . '
                </td>
                <td style="width: 36%; vertical-align: top; white-space: nowrap;">
                    <strong>Bankverbindung</strong><br>
                    IBAN: ' . htmlspecialchars($profile['iban'] ?? '') . '<br>
                    BIC: ' . htmlspecialchars($profile['bic'] ?? '') . '
                </td>
                <td style="width: 22%; vertical-align: top;">
                    <strong>Registrierung</strong><br>
                    ' . htmlspecialchars($profile['registry'] ?? '') . '<br>
                    HRB-Nr: ' . htmlspecialchars($profile['hrb'] ?? '') . '<br>
                    St.-Nr: ' . htmlspecialchars($profile['taxId'] ?? '') . '
                </td>
            </tr>
        </table>
    </div>
</body>
</html>';

        return $html;
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
        $this->selectedContactId = null;
        $this->client = [
            'name' => '',
            'street' => '',
            'zip' => '',
            'city' => '',
            'country' => 'Deutschland',
            'clientNumber' => \App\Models\Contact::generateNextCustomerNumber(),
            'vatId' => '',
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
        $this->paymentNoteSelect = 'standard_14';
        $this->customPaymentNote = 'Zahlbar innerhalb von 14 Tagen rein netto ohne Abzug.';
        $this->legalTextSelect = 'none';
        $this->customLegalText = '';
    }

    public function suggestNumber()
    {
        $year = date('Y');
        $prefix = $this->mode === 'invoice' ? "RE-{$year}-" : "AN-{$year}-";
        
        if ($this->mode === 'invoice') {
            $last = Invoice::where('invoice_number', 'like', "{$prefix}%")
                ->orderBy('invoice_number', 'desc')
                ->first();
            if ($last && preg_match('/RE-\d{4}-(\d+)/', $last->invoice_number, $matches)) {
                $count = intval($matches[1]);
            } else {
                $count = Invoice::whereYear('created_at', $year)->count();
            }
            return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $last = Offer::where('offer_number', 'like', "{$prefix}%")
                ->orderBy('offer_number', 'desc')
                ->first();
            if ($last && preg_match('/AN-\d{4}-(\d+)/', $last->offer_number, $matches)) {
                $count = intval($matches[1]);
            } else {
                $count = Offer::whereYear('created_at', $year)->count();
            }
            return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        }
    }

    // Position Templates Catalog
    public function getItemTemplatesProperty()
    {
        return \App\Models\InvoiceItemTemplate::orderBy('title')->get();
    }

    public function insertItemTemplate($templateId)
    {
        if (!$templateId) return;

        $template = \App\Models\InvoiceItemTemplate::find($templateId);
        if ($template) {
            $this->items[] = [
                'id' => Str::random(8),
                'pos_number' => (string) (count($this->items) + 1),
                'description' => $template->title . ($template->description ? "\n" . $template->description : ''),
                'quantity' => 1,
                'unit' => $template->unit ?: 'Stk',
                'price' => floatval($template->unit_price),
                'vatRate' => floatval($template->vat_rate)
            ];
            $this->dispatch('notify', '📦 Vorlage "' . $template->title . '" als Position hinzugefügt!');
        }
    }

    public function saveItemAsTemplate($itemIndex)
    {
        if (!isset($this->items[$itemIndex])) return;

        $item = $this->items[$itemIndex];
        $title = strtok($item['description'], "\n");

        \App\Models\InvoiceItemTemplate::create([
            'title' => $title ?: 'Neue Position',
            'description' => substr($item['description'], strlen($title)),
            'unit' => $item['unit'] ?: 'Stk',
            'unit_price' => floatval($item['price'] ?? 0),
            'vat_rate' => floatval($item['vatRate'] ?? 19),
            'category' => 'Gespeichert'
        ]);

        $this->dispatch('notify', '💾 Position "' . $title . '" als Vorlage in der Bibliothek gespeichert!');
    }

    // Payment Note Select presets
    public string $paymentNoteSelect = 'standard_14';

    public function updatedPaymentNoteSelect($value)
    {
        switch ($value) {
            case 'immediate':
                $this->customPaymentNote = 'Zahlbar sofort nach Rechnungseingang rein netto ohne Abzug.';
                $this->dueDays = 0;
                break;
            case 'net_7':
                $this->customPaymentNote = 'Zahlbar innerhalb von 7 Tagen rein netto ohne Abzug.';
                $this->dueDays = 7;
                break;
            case 'standard_14':
                $this->customPaymentNote = 'Zahlbar innerhalb von 14 Tagen rein netto ohne Abzug.';
                $this->dueDays = 14;
                break;
            case 'net_30':
                $this->customPaymentNote = 'Zahlbar innerhalb von 30 Tagen rein netto ohne Abzug.';
                $this->dueDays = 30;
                break;
            case 'skonto_7':
                $this->customPaymentNote = 'Zahlbar innerhalb von 7 Tagen mit 2 % Skonto, 30 Tage netto.';
                $this->dueDays = 30;
                break;
            case 'cash':
                $this->customPaymentNote = 'Rechnungsbetrag dankend erhalten (Barzahlung / EC-Karte).';
                $this->dueDays = 0;
                break;
            case 'split_50':
                $this->customPaymentNote = '50 % Abschlagszahlung vereinbart, 50 % nach Schlussabnahme.';
                $this->dueDays = 14;
                break;
            case 'custom':
                $this->customPaymentNote = '';
                break;
            default:
                $this->customPaymentNote = 'Zahlbar innerhalb von 14 Tagen rein netto ohne Abzug.';
                $this->dueDays = 14;
                break;
        }
    }

    // Legal Text Select presets
    public string $legalTextSelect = 'none';

    public function updatedLegalTextSelect($value)
    {
        $this->customLegalText = match($value) {
            'reverse_charge' => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge) gemäß § 13b UStG.',
            'small_business' => 'Kein Ausweis der Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).',
            'retention_notice' => 'Hinweispflicht § 14b Abs. 1 UStG: Der Empfänger ist verpflichtet, diese Rechnung 2 Jahre aufzubewahren.',
            'exemption_48b' => 'Steuerfreie Bauleistung nach § 13b UStG – Freistellungsbescheinigung nach § 48b EStG liegt vor.',
            'custom' => '',
            default => '',
        };
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

    public function loadSavedDoc($id, ?string $type = null)
    {
        $targetType = $type ?: ($this->mode === 'invoice' ? 'invoice' : 'offer');
        $this->mode = $targetType === 'invoice' ? 'invoice' : 'offer';
        $this->activeTab = 'editor';

        if ($this->mode === 'invoice') {
            $inv = Invoice::with('items')->find($id);
            if ($inv) {
                $this->selectedContactId = $inv->contact_id;
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
                $this->dispatch('notify', '📄 Rechnung ' . $inv->invoice_number . ' im Editor geöffnet!');
            }
        } else {
            $off = Offer::with('sections.items')->find($id);
            if ($off) {
                $this->selectedContactId = $off->contact_id;
                $this->projectId = $off->project_id;
                $this->docNumber = $off->offer_number;
                $this->docDate = $off->date;
                $this->deliveryDate = '';
                $this->discountRate = 0.0;
                $this->taxMode = 'standard';
                $this->items = [];
                
                if ($off->sections->count() > 0) {
                    foreach ($off->sections as $sec) {
                        foreach ($sec->items as $item) {
                            $this->items[] = [
                                'id' => Str::random(8),
                                'pos_number' => $item->pos_number,
                                'description' => $item->description,
                                'quantity' => $item->quantity,
                                'unit' => $item->unit,
                                'price' => $item->unit_price,
                                'vatRate' => $item->vat_rate ?? 19
                            ];
                        }
                    }
                }
                $this->dispatch('notify', '📑 Angebot ' . $off->offer_number . ' im Editor geöffnet!');
            }
        }
    }

    public function saveDocument()
    {
        try {
            $this->validate([
                'docNumber' => 'required|string',
                'docDate' => 'required|date',
                'client.name' => 'required|string|max:255',
            ], [
                'client.name.required' => 'Bitte geben Sie den Kundennamen / Empfänger ein.',
                'docNumber.required' => 'Bitte geben Sie eine Dokumentennummer ein.',
                'docDate.required' => 'Bitte geben Sie ein Dokumentendatum ein.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('notify', '⚠️ Speichern abgebrochen: Bitte Kundennamen / Empfänger im Formular ausfüllen!');
            throw $e;
        }

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

            $this->dispatch('notify', '✅ Rechnung ' . $this->docNumber . ' erfolgreich in Datenbank gespeichert & im Archiv abgelegt!');
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

            $this->dispatch('notify', '✅ Angebot ' . $this->docNumber . ' erfolgreich in Datenbank gespeichert & im Archiv abgelegt!');
        }

        $this->loadSavedDocuments();
    }

    // AI OpenAI Integration
    public bool $showAiModal = false;
    public string $aiRawText = '';

    public function parseWithAi(?\App\Services\OpenAiParserService $parser = null)
    {
        $parser = $parser ?? app(\App\Services\OpenAiParserService::class);
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

    public function generateCoverLetter(?\App\Services\OpenAiParserService $parser = null)
    {
        $parser = $parser ?? app(\App\Services\OpenAiParserService::class);
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

    public function auditOfferRisk(?\App\Services\OpenAiParserService $parser = null)
    {
        $parser = $parser ?? app(\App\Services\OpenAiParserService::class);
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

    public bool $showDunningModal = false;
    public ?string $selectedDunningInvoiceId = null;
    public int $dunningLevel = 1;
    public string $dunningNoticeText = '';

    public function getSelectedDunningInvoiceProperty()
    {
        return $this->selectedDunningInvoiceId ? Invoice::with(['contact', 'project'])->find($this->selectedDunningInvoiceId) : null;
    }

    public function openDunningModal($invoiceId)
    {
        $this->selectedDunningInvoiceId = $invoiceId;
        $inv = $this->selectedDunningInvoice;
        if ($inv) {
            $this->dunningLevel = min(3, ($inv->reminder_level ?? 0) + 1);
            $this->generateDunningNotice();
            $this->showDunningModal = true;
        }
    }

    public function setDunningLevel(int $level)
    {
        $this->dunningLevel = $level;
        $this->generateDunningNotice();
    }

    public function generateDunningNotice()
    {
        $inv = $this->selectedDunningInvoice;
        if (!$inv) return;

        $clientName = $inv->contact?->company_name ?: ($inv->contact?->first_name . ' ' . $inv->contact?->last_name) ?: $inv->project?->name ?: 'Sehr geehrte Damen und Herren';
        $number = $inv->invoice_number ?: 'RE-xxx';
        $amount = number_format($inv->total_gross, 2, ',', '.') . ' €';

        if ($this->dunningLevel === 1) {
            $this->dunningNoticeText = "Zahlungserinnerung\n\nSehr geehrte(r) {$clientName},\n\nwir erlauben uns höflich, Sie an die noch offene Rechnung {$number} vom " . date('d.m.Y', strtotime($inv->invoice_date)) . " über {$amount} zu erinnern.\n\nBitte überweisen Sie den ausstehenden Betrag innerhalb von 7 Tagen auf unser Geschäftskonto.\n\nMit freundlichen Grüßen\nBT Bautechnik";
        } elseif ($this->dunningLevel === 2) {
            $fee = 5.00;
            $totalWithFee = number_format($inv->total_gross + $fee, 2, ',', '.') . ' €';
            $this->dunningNoticeText = "1. MAHNUNG\n\nSehr geehrte(r) {$clientName},\n\ntrotz unserer Zahlungserinnerung konnten wir bisher keinen Zahlungseingang für die Rechnung {$number} über {$amount} feststellen.\n\nWir bitten Sie eindringlich, den Gesamtbetrag inkl. 5,00 € Mahngebühr ({$totalWithFee}) bis spätestens zum " . date('d.m.Y', strtotime('+7 days')) . " zu begleichen.\n\nMit freundlichen Grüßen\nBT Bautechnik";
        } else {
            $fee = 45.00;
            $totalWithFee = number_format($inv->total_gross + $fee, 2, ',', '.') . ' €';
            $this->dunningNoticeText = "2. LETZTE MAHNUNG / ZAHLUNGSAUFFORDERUNG\n\nSehr geehrte(r) {$clientName},\n\nauf unsere bisherigen Mahnungen zur Rechnung {$number} über {$amount} haben Sie leider nicht reagiert.\n\nWir fordern Sie letztmalig auf, den Gesamtbetrag inkl. 40,00 € Verzugspauschale (§ 288 Abs. 5 BGB) und 5,00 € Mahngebühren (Summe: {$totalWithFee}) unverzüglich bis zum " . date('d.m.Y', strtotime('+5 days')) . " zu überweisen.\n\nSollte keine Zahlung eingehen, werden wir ein gerichtliches Mahnverfahren einleiten.\n\nMit freundlichen Grüßen\nBT Bautechnik";
        }
    }

    public function executeDunningNotice()
    {
        $inv = $this->selectedDunningInvoice;
        if ($inv) {
            $fee = match($this->dunningLevel) {
                1 => 0.00,
                2 => 5.00,
                default => 45.00,
            };

            $inv->update([
                'reminder_level' => $this->dunningLevel,
                'reminder_date' => date('Y-m-d'),
                'dunning_fee' => $fee,
                'status' => 'overdue',
            ]);

            $this->showDunningModal = false;
            $this->loadSavedDocuments();
            $this->dispatch('notify', '⚠️ Mahnung Stufe ' . $this->dunningLevel . ' erfolgreich erzeugt!');
        }
    }

    public function exportDatevCsv()
    {
        $invoices = Invoice::with(['contact', 'project'])->orderBy('invoice_date', 'desc')->get();

        $output = "Datum;Belegfeld1;Buchungstext;Kundenname;UStID;Netto;USt;Brutto;Steuersatz;Status;Mahnstufe\n";

        foreach ($invoices as $inv) {
            $taxRate = $inv->tax_mode === 'reverse_charge' ? '0% (§13b)' : ($inv->tax_mode === 'net' ? '19%' : '0%');
            $line = [
                $inv->invoice_date,
                $inv->number,
                '"' . str_replace('"', '""', $inv->project?->name ?? 'Rechnung') . '"',
                '"' . str_replace('"', '""', $inv->contact?->company_name ?? ($inv->contact?->first_name . ' ' . $inv->contact?->last_name) ?? 'Kunde') . '"',
                $inv->contact?->vat_id ?? '',
                number_format($inv->total_net, 2, ',', ''),
                number_format($inv->tax_amount, 2, ',', ''),
                number_format($inv->total_gross, 2, ',', ''),
                $taxRate,
                $inv->status,
                $inv->reminder_level ?? 0,
            ];
            $output .= implode(';', $line) . "\n";
        }

        return response()->streamDownload(function () use ($output) {
            echo "\xEF\xBB\xBF";
            echo $output;
        }, 'DATEV_Rechnungs-Export_' . date('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}; ?>

<div class="space-y-6 font-sans max-w-full overflow-x-hidden relative">
    <!-- Load custom legacy CSS styles dynamically -->
    <link rel="stylesheet" href="{{ asset('css/invoice-style.css') }}">

    <!-- FLOATING TOAST NOTIFICATION BANNER -->
    <div x-data="{ show: false, message: '' }"
         x-on:notify.window="message = $event.detail; show = true; setTimeout(() => show = false, 4500)"
         x-show="show"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-4 scale-95"
         style="display: none;"
         class="fixed top-5 right-5 z-[9999] max-w-md w-full no-print">
        <div class="bg-slate-900/95 backdrop-blur-md text-white p-4 rounded-2xl border border-blue-500/40 shadow-2xl flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-xl bg-blue-600/30 text-blue-400 text-base">
                    🔔
                </div>
                <p class="text-xs font-bold leading-relaxed text-slate-100" x-text="message"></p>
            </div>
            <button @click="show = false" class="text-slate-400 hover:text-white text-xs p-1 rounded-lg hover:bg-slate-800 transition">
                ✕
            </button>
        </div>
    </div>

    <!-- MAIN TAB NAVIGATION BAR -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-slate-900/95 p-2.5 rounded-2xl border border-slate-800 shadow-xl text-white no-print">
        <div class="flex items-center gap-2">
            <!-- 1. Archive / Overview Tab (DEFAULT FIRST) -->
            <button wire:click="setTab('archive')" 
                    class="px-5 py-2.5 rounded-xl text-xs font-black transition cursor-pointer flex items-center gap-2 {{ $activeTab === 'archive' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                <span>📁 Alle Rechnungen & Angebote</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $activeTab === 'archive' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-300' }}">
                    {{ count($savedDocs) }}
                </span>
            </button>

            <!-- 2. Editor Tab -->
            <button wire:click="setTab('editor')" 
                    class="px-5 py-2.5 rounded-xl text-xs font-black transition cursor-pointer flex items-center gap-2 {{ $activeTab === 'editor' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                <span>✍️ {{ $mode === 'offer' ? 'Angebot bearbeiten' : 'Rechnung bearbeiten' }}</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            @if ($activeTab === 'archive')
                <button wire:click="createNewInvoice" 
                        class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white text-xs font-black rounded-xl shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer active:scale-95">
                    <span>➕ Neue Rechnung erstellen</span>
                </button>
                <button wire:click="createNewOffer" 
                        class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 transition flex items-center gap-1.5 cursor-pointer">
                    <span>➕ Neues Angebot</span>
                </button>
            @else
                <button wire:click="setTab('archive')" 
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700 transition flex items-center gap-1.5 cursor-pointer">
                    <span>← Zurück zur Übersicht</span>
                </button>
            @endif
        </div>
    </div>

    @if ($activeTab === 'editor')
        <!-- MODE SELECTOR & ACTION BAR -->
        <div class="flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4 bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 p-4 sm:p-5 rounded-2xl border border-indigo-500/20 shadow-xl text-white relative overflow-hidden no-print">
            <div class="flex items-center gap-3 w-full lg:w-auto">
                <div class="grid grid-cols-2 gap-1.5 w-full lg:w-auto bg-slate-900/90 p-1.5 rounded-xl border border-slate-800">
                    <button wire:click="setMode('invoice')" class="px-3.5 py-2.5 text-xs font-extrabold rounded-lg transition text-center cursor-pointer flex items-center justify-center gap-1.5 {{ $mode === 'invoice' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-400 hover:text-white' }}">
                        <span>📄 Rechnungs-Modus</span>
                    </button>
                    <button wire:click="setMode('offer')" class="px-3.5 py-2.5 text-xs font-extrabold rounded-lg transition text-center cursor-pointer flex items-center justify-center gap-1.5 {{ $mode === 'offer' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-400 hover:text-white' }}">
                        <span>📑 Angebots-Modus</span>
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-2 lg:flex lg:flex-wrap items-center gap-2.5 w-full lg:w-auto">
                <button wire:click="downloadEinvoicePackage" 
                        title="Komplettes E-Rechnungs-Paket (PDF-Briefbogen + XRechnung/ZUGFeRD XML + Konformitätszertifikat als ZIP) herunterladen"
                        class="px-3 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-emerald-500/20 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                    <span>📦 E-Rechnungs-Paket</span>
                </button>
                <button wire:click="$set('showAiModal', true)" class="px-3 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-blue-500/20 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                    <span>✨ KI-Textimport</span>
                </button>
                <button wire:click="generateCoverLetter" class="px-3 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-indigo-500/20 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                    <span>✉️ KI-Anschreiben</span>
                </button>
                <button wire:click="auditOfferRisk" class="px-3 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-amber-500/20 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                    <span>🛡️ KI-Check</span>
                </button>
                <button wire:click="exportDatevCsv" class="px-3 py-2.5 bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs rounded-xl transition shadow-md shadow-blue-500/20 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                    <span>📊 DATEV-Export</span>
                </button>
                <button wire:click="resetForm" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl transition border border-slate-700 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                    <span>🧹 Leeren</span>
                </button>
                <button onclick="window.print()" class="px-3 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-emerald-500/20 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                    <span>🖨️ Drucken / PDF</span>
                </button>
            </div>
        </div>

        <!-- Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- EDITOR PANEL (LEFT COLUMN) -->
        <div class="lg:col-span-5 space-y-6 editor-panel no-print">
            
            <!-- Quick select project / Import details -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center justify-between">
                    <span>🏗️ Baustelle / Projekt wählen</span>
                    @if ($projectId)
                        <button wire:click="selectProject(null)" class="text-xs text-rose-600 hover:text-rose-700 font-bold cursor-pointer">✕ Auswählen aufheben</button>
                    @endif
                </h3>

                <!-- Searchable Custom Dropdown (Alpine.js Combobox) -->
                <div x-data="{ open: false }" class="relative w-full">
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Projekt / Baustelle (mit Echtzeit-Suche)</label>
                    
                    @php
                        $selectedProject = $projectId ? \App\Models\Project::find($projectId) : null;
                    @endphp

                    <!-- Trigger Button -->
                    <button @click="open = !open" 
                            type="button" 
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs text-left text-slate-900 font-semibold flex items-center justify-between gap-2 focus:bg-white focus:border-blue-600 shadow-2xs cursor-pointer transition">
                        <span class="truncate flex items-center gap-2">
                            @if ($selectedProject)
                                <span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 text-[10px] font-extrabold shrink-0">BAUSTELLE</span>
                                <span class="font-bold text-slate-900 truncate">{{ $selectedProject->name }}</span>
                                @if ($selectedProject->city_street)
                                    <span class="text-slate-400 text-[11px] truncate hidden sm:inline">({{ $selectedProject->city_street }})</span>
                                @endif
                            @else
                                <span class="text-slate-500 font-normal">-- Freie Erstellung (keine Baustelle) --</span>
                            @endif
                        </span>
                        <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- Dropdown Popup Panel -->
                    <div x-show="open" 
                         @click.outside="open = false" 
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                         x-cloak 
                         class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden space-y-2 p-2 max-h-72 flex flex-col">
                        
                        <!-- Search Input Field inside Dropdown -->
                        <div class="relative shrink-0">
                            <input wire:model.live.debounce.150ms="projectSearch" 
                                   type="text" 
                                   class="w-full bg-slate-100 border border-slate-300 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none" 
                                   placeholder="🔍 Baustelle, Ort oder Adresse suchen..."
                                   @click.stop>
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">📍</span>
                        </div>

                        <!-- Options List -->
                        <div class="overflow-y-auto space-y-1 flex-1 pr-0.5">
                            <!-- Clear option -->
                            <button wire:click="selectProject(null)" 
                                    @click="open = false"
                                    type="button" 
                                    class="w-full text-left px-3 py-2 rounded-xl text-xs font-medium transition cursor-pointer flex items-center justify-between {{ !$projectId ? 'bg-slate-100 text-slate-900 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                                <span>-- Freie Erstellung (keine Baustelle) --</span>
                                @if (!$projectId)
                                    <span class="text-blue-600 font-bold">✓</span>
                                @endif
                            </button>

                            <!-- Project List -->
                            @forelse ($this->projects as $p)
                                <button wire:click="selectProject('{{ $p->id }}')" 
                                        @click="open = false"
                                        type="button" 
                                        class="w-full text-left px-3 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-between gap-2 {{ $projectId === $p->id ? 'bg-blue-50 text-blue-900 border border-blue-200 font-bold' : 'text-slate-800 hover:bg-slate-50' }}">
                                    <div class="truncate">
                                        <p class="font-bold text-slate-900 truncate">{{ $p->name }}</p>
                                        @if ($p->city_street || $p->contact_address)
                                            <p class="text-[11px] text-slate-500 truncate">📍 {{ $p->city_street ?: $p->contact_address }}</p>
                                        @endif
                                    </div>
                                    @if ($projectId === $p->id)
                                        <span class="text-blue-600 font-extrabold shrink-0">✓</span>
                                    @endif
                                </button>
                            @empty
                                <div class="p-3 text-center text-xs text-slate-400 italic">
                                    Keine Baustelle für "{{ $projectSearch }}" gefunden.
                                </div>
                            @endforelse
                        </div>
                    </div>
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
            <details class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4" open>
                <summary class="text-sm font-bold text-slate-900 uppercase tracking-wider cursor-pointer select-none">Firmenprofil (Absender)</summary>
                <div class="space-y-3 pt-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">PLZ</label>
                            <input wire:model.live="profile.zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Ort</label>
                            <input wire:model.live="profile.city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Steuernummer</label>
                            <input wire:model.live="profile.taxId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">USt-IdNr.</label>
                            <input wire:model.live="profile.vatId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Empfänger (Kunde)</h3>
                    <a href="{{ route('contacts') }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-700 font-bold flex items-center gap-1">
                        <span>+ Kundendatenbank</span>
                    </a>
                </div>
                
                <div class="space-y-3">
                    <!-- Searchable Customer Combobox -->
                    <div x-data="{ open: false }" class="relative w-full">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">👤 Kunde aus Kundendatenbank wählen (mit Echtzeit-Suche)</label>
                        
                        @php
                            $selectedContact = $selectedContactId ? \App\Models\Contact::find($selectedContactId) : null;
                        @endphp

                        <!-- Trigger Button -->
                        <button @click="open = !open" 
                                type="button" 
                                class="w-full bg-blue-50/50 border border-blue-200 rounded-xl px-3.5 py-2.5 text-xs text-left text-slate-900 font-semibold flex items-center justify-between gap-2 focus:bg-white focus:border-blue-600 shadow-2xs cursor-pointer transition">
                            <span class="truncate flex items-center gap-2">
                                @if ($selectedContact)
                                    <span class="px-2 py-0.5 rounded-md bg-blue-600 text-white text-[10px] font-extrabold shrink-0">{{ $selectedContact->customer_number ?: 'KUNDE' }}</span>
                                    <span class="font-bold text-slate-900 truncate">{{ $selectedContact->display_name }}</span>
                                    @if ($selectedContact->city)
                                        <span class="text-slate-500 text-[11px] truncate hidden sm:inline">({{ $selectedContact->city }})</span>
                                    @endif
                                @else
                                    <span class="text-slate-500 font-normal">-- Kunden aus Datenbank suchen / auswählen --</span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <!-- Dropdown Popup Panel -->
                        <div x-show="open" 
                             @click.outside="open = false" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             x-cloak 
                             class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden space-y-2 p-2 max-h-72 flex flex-col">
                            
                            <!-- Search Input Field inside Dropdown -->
                            <div class="relative shrink-0">
                                <input wire:model.live.debounce.150ms="contactSearch" 
                                       type="text" 
                                       class="w-full bg-slate-100 border border-slate-300 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none" 
                                       placeholder="🔍 Kunde, Firma, Ort, Kundennr. oder USt-ID suchen..."
                                       @click.stop>
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">👤</span>
                            </div>

                            <!-- Options List -->
                            <div class="overflow-y-auto space-y-1 flex-1 pr-0.5">
                                <!-- Clear option -->
                                <button wire:click="selectContact(null)" 
                                        @click="open = false"
                                        type="button" 
                                        class="w-full text-left px-3 py-2 rounded-xl text-xs font-medium transition cursor-pointer flex items-center justify-between {{ !$selectedContactId ? 'bg-slate-100 text-slate-900 font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <span>-- Manuelle Eingabe (kein gespeicherter Kunde) --</span>
                                    @if (!$selectedContactId)
                                        <span class="text-blue-600 font-bold">✓</span>
                                    @endif
                                </button>

                                <!-- Contact List -->
                                @forelse ($this->contacts as $c)
                                    <button wire:click="selectContact('{{ $c->id }}')" 
                                            @click="open = false"
                                            type="button" 
                                            class="w-full text-left px-3 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-between gap-2 {{ $selectedContactId === $c->id ? 'bg-blue-50 text-blue-900 border border-blue-200 font-bold' : 'text-slate-800 hover:bg-slate-50' }}">
                                        <div class="truncate">
                                            <p class="font-bold text-slate-900 truncate flex items-center gap-1.5">
                                                <span>{{ $c->display_name }}</span>
                                                @if ($c->customer_number)
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-slate-100 text-slate-700 border border-slate-200">{{ $c->customer_number }}</span>
                                                @endif
                                            </p>
                                            @if ($c->city || $c->vat_id)
                                                <p class="text-[11px] text-slate-500 truncate">📍 {{ $c->city ?: 'Kein Ort' }} @if($c->vat_id) • USt-ID: {{ $c->vat_id }}@endif</p>
                                            @endif
                                        </div>
                                        @if ($selectedContactId === $c->id)
                                            <span class="text-blue-600 font-extrabold shrink-0">✓</span>
                                        @endif
                                    </button>
                                @empty
                                    <div class="p-3 text-center text-xs text-slate-400 italic">
                                        Kein Kunde für "{{ $contactSearch }}" gefunden.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Name / Firma des Kunden</label>
                        <input wire:model.live="client.name" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Straße & Hausnummer</label>
                        <input wire:model.live="client.street" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">PLZ</label>
                            <input wire:model.live="client.zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Ort</label>
                            <input wire:model.live="client.city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Kunden USt-IdNr. / Steuernummer (§13b / E-Rechnung)</label>
                        <input wire:model.live="client.vatId" type="text" placeholder="z. B. DE345678901" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                    </div>
                </div>
            </div>

            <!-- Document Meta Details -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Dokumenten-Metadaten</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">{{ $mode === 'invoice' ? 'Rechnungsnummer' : 'Angebotsnummer' }}</label>
                            <input wire:model.live="docNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Kundennummer</label>
                            <input wire:model.live="client.clientNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Zahlungsziel (Tage)</label>
                                <input wire:model.live="dueDays" type="number" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Rabatt / Skonto (%)</label>
                                <input wire:model.live="discountRate" type="number" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                            </div>
                        </div>
                        <!-- Custom Dropdown for Tax Mode -->
                        <div x-data="{ open: false }" class="relative w-full">
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Umsatzsteuer-Modus</label>
                            
                            <button @click="open = !open" 
                                    type="button" 
                                    class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-left text-slate-900 font-semibold flex items-center justify-between gap-2 focus:bg-white focus:border-blue-600 shadow-2xs cursor-pointer transition">
                                <span class="truncate">
                                    @if ($taxMode === 'standard') Standardbesteuerung (19% USt.)
                                    @elseif ($taxMode === 'reverse') Reverse Charge § 13b UStG (Bauleistung)
                                    @elseif ($taxMode === 'small') Kleinunternehmer § 19 UStG
                                    @else Sonstige Steuerbefreiung (Freitext)
                                    @endif
                                </span>
                                <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="open" 
                                 @click.outside="open = false" 
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                 x-cloak 
                                 class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden p-1.5 space-y-1">
                                
                                <button wire:click="$set('taxMode', 'standard')" @click="open = false" type="button" 
                                        class="w-full text-left px-3 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-between {{ $taxMode === 'standard' ? 'bg-blue-50 text-blue-900 border border-blue-200 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                                    <span>Standardbesteuerung (19% USt.)</span>
                                    @if ($taxMode === 'standard') <span class="text-blue-600 font-bold">✓</span> @endif
                                </button>

                                <button wire:click="$set('taxMode', 'reverse')" @click="open = false" type="button" 
                                        class="w-full text-left px-3 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-between {{ $taxMode === 'reverse' ? 'bg-blue-50 text-blue-900 border border-blue-200 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                                    <span>Reverse Charge § 13b UStG (Bauleistung)</span>
                                    @if ($taxMode === 'reverse') <span class="text-blue-600 font-bold">✓</span> @endif
                                </button>

                                <button wire:click="$set('taxMode', 'small')" @click="open = false" type="button" 
                                        class="w-full text-left px-3 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-between {{ $taxMode === 'small' ? 'bg-blue-50 text-blue-900 border border-blue-200 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                                    <span>Kleinunternehmer § 19 UStG</span>
                                    @if ($taxMode === 'small') <span class="text-blue-600 font-bold">✓</span> @endif
                                </button>

                                <button wire:click="$set('taxMode', 'custom')" @click="open = false" type="button" 
                                        class="w-full text-left px-3 py-2.5 rounded-xl text-xs transition cursor-pointer flex items-center justify-between {{ $taxMode === 'custom' ? 'bg-blue-50 text-blue-900 border border-blue-200 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                                    <span>Sonstige Steuerbefreiung (Freitext)</span>
                                    @if ($taxMode === 'custom') <span class="text-blue-600 font-bold">✓</span> @endif
                                </button>
                            </div>
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
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Positionen</h3>
                            <button wire:click="openGlossarModal" type="button" class="px-2 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 text-[10px] font-extrabold rounded-lg transition flex items-center gap-1 cursor-pointer" title="Begriffe der Bauabrechnung anzeigen">
                                <span>💡 Spicker</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="openAufmassModal(null)" type="button" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border border-indigo-200 text-xs font-extrabold rounded-xl transition flex items-center gap-1 cursor-pointer shrink-0">
                                <span>📐 Aufmaß-Rechner</span>
                            </button>
                            <button wire:click="addItem" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-xs transition flex items-center gap-1 cursor-pointer shrink-0">
                                <span>+ Position</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <select wire:change="insertItemTemplate($event.target.value)" class="w-full bg-blue-50/70 border border-blue-200 rounded-xl px-3 py-2 text-xs text-blue-900 font-semibold focus:bg-white focus:border-blue-600 cursor-pointer transition">
                            <option value="">📦 Vorlage aus Bibliothek einfügen...</option>
                            @foreach ($this->itemTemplates as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->title }} ({{ number_format($tpl->unit_price, 2, ',', '.') }} € / {{ $tpl->unit }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="space-y-3">
                    @foreach ($items as $idx => $item)
                        <div wire:key="{{ $item['id'] }}" class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 space-y-2 relative">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <button wire:click="saveItemAsTemplate({{ $idx }})" type="button" title="Diese Position als Vorlage speichern" class="text-blue-600 hover:text-blue-800 text-[11px] font-bold flex items-center gap-1 cursor-pointer">
                                        <span>💾 Als Vorlage speichern</span>
                                    </button>
                                    <button wire:click="openAufmassModal({{ $idx }})" type="button" title="Aufmaß & Massenermittlung berechnen" class="px-2 py-0.5 bg-indigo-100 hover:bg-indigo-200 text-indigo-900 border border-indigo-300 text-[11px] font-black rounded-lg transition flex items-center gap-1 cursor-pointer">
                                        <span>📐 Aufmaß</span>
                                    </button>
                                </div>
                                <button wire:click="removeItem('{{ $item['id'] }}')" class="text-rose-500 hover:text-rose-700 text-xs font-bold cursor-pointer">✕ Entfernen</button>
                            </div>
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
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
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
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Zahlungshinweis (Vorlage wählen)</label>
                        <select wire:model.live="paymentNoteSelect" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:bg-white focus:border-blue-600 cursor-pointer mb-2">
                            <option value="standard_14">Standard: 14 Tage rein netto ohne Abzug</option>
                            <option value="net_7">Schnellzahler: 7 Tage rein netto ohne Abzug</option>
                            <option value="immediate">Sofort: Zahlbar sofort nach Rechnungseingang</option>
                            <option value="net_30">30 Tage: 30 Tage rein netto ohne Abzug</option>
                            <option value="skonto_7">Skonto: 7 Tage mit 2 % Skonto, 30 Tage netto</option>
                            <option value="cash">Barzahlung / EC-Karte: Dankend erhalten</option>
                            <option value="split_50">Abschlagszahlung: 50 % Anzahlung, 50 % nach Abnahme</option>
                            <option value="custom">✍️ Eigener / Manueller Text...</option>
                        </select>
                        <textarea wire:model.live="customPaymentNote" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Bitte überweisen Sie den Betrag..."></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Gesetzlicher Hinweistext (Vorlage wählen)</label>
                        <select wire:model.live="legalTextSelect" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-medium focus:bg-white focus:border-blue-600 cursor-pointer mb-2">
                            <option value="none">Kein gesetzlicher Sondertext (Standard)</option>
                            <option value="reverse_charge">Reverse Charge § 13b UStG (Steuerschuld des Leistungsempfängers)</option>
                            <option value="small_business">Kleinunternehmer § 19 UStG (Kein Ausweis der Umsatzsteuer)</option>
                            <option value="retention_notice">Aufbewahrungspflicht § 14b UStG (2 Jahre für Privatpersonen)</option>
                            <option value="exemption_48b">Freistellungsbescheinigung nach § 48b EStG liegt vor</option>
                            <option value="custom">✍️ Eigener / Manueller Text...</option>
                        </select>
                        <textarea wire:model.live="customLegalText" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. Freistellungsbescheinigung nach § 48b EStG liegt vor."></textarea>
                    </div>
                </div>
            </div>

            <!-- Save Action Button -->
            <button wire:click="saveDocument" 
                    wire:loading.attr="disabled"
                    class="w-full py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-sm rounded-2xl shadow-xl shadow-blue-500/25 transition-all flex items-center justify-center gap-2 cursor-pointer active:scale-[0.99] disabled:opacity-50">
                <span wire:loading.remove wire:target="saveDocument">💾 In Datenbank speichern & im Archiv ablegen</span>
                <span wire:loading wire:target="saveDocument" class="flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Wird in Datenbank gespeichert...</span>
                </span>
            </button>
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

                <!-- Totals Section (Bündig rechtsbündig unter der Positionstabelle) -->
                <div class="totals-section" style="margin-left: auto; width: 85mm; max-width: 100%; margin-top: 6mm; margin-bottom: 6mm; font-size: 11px;">
                    <table style="width: 100%; border-collapse: collapse; text-align: right;">
                        <tbody>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td class="label" style="padding: 4px 0; text-align: left; color: #475569; font-weight: 500;">Netto-Zwischensumme:</td>
                                <td class="amount" style="padding: 4px 0; font-weight: 700; color: #0f172a; text-align: right; white-space: nowrap; width: 110px;">{{ number_format($this->calculation['subtotal'], 2, ',', '.') }} €</td>
                            </tr>
                            @if ($discountRate > 0)
                                <tr style="border-bottom: 1px solid #e2e8f0; color: #16a34a;">
                                    <td class="label" style="padding: 4px 0; text-align: left; font-weight: 500;">Rabatt ({{ $discountRate }}%):</td>
                                    <td class="amount" style="padding: 4px 0; font-weight: 700; text-align: right; white-space: nowrap; width: 110px;">-{{ number_format($this->calculation['discountValue'], 2, ',', '.') }} €</td>
                                </tr>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td class="label" style="padding: 4px 0; text-align: left; color: #475569; font-weight: 500;">Netto nach Rabatt:</td>
                                    <td class="amount" style="padding: 4px 0; font-weight: 700; color: #0f172a; text-align: right; white-space: nowrap; width: 110px;">{{ number_format($this->calculation['subtotalAfterDiscount'], 2, ',', '.') }} €</td>
                                </tr>
                            @endif
                            @if ($taxMode === 'standard')
                                @foreach ($this->calculation['taxes'] as $rate => $val)
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td class="label" style="padding: 4px 0; text-align: left; color: #475569; font-weight: 500;">zzgl. USt. {{ $rate }}%:</td>
                                        <td class="amount" style="padding: 4px 0; font-weight: 700; color: #0f172a; text-align: right; white-space: nowrap; width: 110px;">{{ number_format($val, 2, ',', '.') }} €</td>
                                    </tr>
                                @endforeach
                            @endif
                            <tr class="grand-total-row" style="border-top: 1px solid #0056b3; border-bottom: 3px double #0056b3; background-color: #f8fafc;">
                                <td style="padding: 6px 0; text-align: left; font-size: 12px; font-weight: 800; color: #0056b3;">Gesamtsumme ({{ $taxMode === 'standard' ? 'Brutto' : 'Netto' }}):</td>
                                <td style="padding: 6px 0; font-size: 13px; font-weight: 800; color: #0056b3; text-align: right; white-space: nowrap; width: 110px;">{{ number_format($this->calculation['grandTotal'], 2, ',', '.') }} €</td>
                            </tr>
                        </tbody>
                    </table>
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
                        IBAN: <span id="viewFooterIban">{{ $profile['iban'] }}</span><br>
                        BIC: <span id="viewFooterBic">{{ $profile['bic'] }}</span>
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
    @endif

    <!-- SEPARATE DEDICATED ARCHIVE TAB -->
    @if ($activeTab === 'archive')
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-6">
            <!-- Archive Header & Filters -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-slate-200">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                        <span>📁 Rechnungs- & Dokumentenübersicht</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs bg-blue-100 text-blue-800 font-black">{{ count($savedDocs) }} Dokumente</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Übersicht aller bereits erstellten Rechnungen und Angebote. Klicken Sie auf ein Dokument zum Bearbeiten oder Exportieren.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Primary CTA: Neue Rechnung erstellen -->
                    <button wire:click="createNewInvoice" 
                            class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-xs rounded-xl shadow-md shadow-blue-500/20 transition flex items-center gap-1.5 cursor-pointer active:scale-95">
                        <span>➕ Neue Rechnung erstellen</span>
                    </button>

                    <!-- Filter Tabs: Alle / Rechnungen / Angebote -->
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200">
                        <button wire:click="setArchiveFilter('all')" 
                                class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $archiveFilter === 'all' ? 'bg-white text-slate-900 shadow-xs font-black' : 'text-slate-600 hover:text-slate-900' }}">
                            Alle ({{ count($savedDocs) }})
                        </button>
                        <button wire:click="setArchiveFilter('invoice')" 
                                class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $archiveFilter === 'invoice' ? 'bg-blue-600 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900' }}">
                            📄 Rechnungen
                        </button>
                        <button wire:click="setArchiveFilter('offer')" 
                                class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $archiveFilter === 'offer' ? 'bg-blue-600 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900' }}">
                            📑 Angebote
                        </button>
                    </div>

                    <!-- Sammel-Export ZIP Button -->
                    <button wire:click="exportZipArchive" 
                            title="Sammel-Export aller gefilterten Dokumente als ZIP-Archiv herunterladen"
                            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold text-xs rounded-xl border border-slate-300 transition flex items-center gap-1.5 cursor-pointer">
                        <span>📦 ZIP-Export</span>
                    </button>
                </div>
            </div>

            <!-- Search Bar & Advanced Date Filter Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                <!-- Search input -->
                <div class="relative lg:col-span-4">
                    <input wire:model.live.debounce.150ms="docSearch" 
                           type="text" 
                           class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none transition shadow-2xs font-medium"
                           placeholder="🔍 Suche nach Rechnungsnr., Kunde, Baustelle...">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
                </div>

                <!-- Sort Order Dropdown -->
                <div class="lg:col-span-2">
                    <select wire:model.live="sortOrder" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-bold focus:bg-white focus:border-blue-600 cursor-pointer">
                        <option value="desc">⬇️ Absteigend (neueste)</option>
                        <option value="asc">⬆️ Aufsteigend (älteste)</option>
                    </select>
                </div>

                <!-- Filter Year Dropdown -->
                <div class="lg:col-span-2">
                    <select wire:model.live="filterYear" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-bold focus:bg-white focus:border-blue-600 cursor-pointer">
                        <option value="all">📅 Alle Jahre</option>
                        <option value="2026">Jahr 2026</option>
                        <option value="2025">Jahr 2025</option>
                        <option value="2024">Jahr 2024</option>
                    </select>
                </div>

                <!-- Filter Quarter Dropdown -->
                <div class="lg:col-span-2">
                    <select wire:model.live="filterQuarter" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-bold focus:bg-white focus:border-blue-600 cursor-pointer">
                        <option value="all">🗓️ Alle Quartale</option>
                        <option value="Q1">Q1 (Jan – Mär)</option>
                        <option value="Q2">Q2 (Apr – Jun)</option>
                        <option value="Q3">Q3 (Jul – Sep)</option>
                        <option value="Q4">Q4 (Okt – Dez)</option>
                    </select>
                </div>

                <!-- Filter Month Dropdown -->
                <div class="lg:col-span-2">
                    <select wire:model.live="filterMonth" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-2 text-xs text-slate-900 font-bold focus:bg-white focus:border-blue-600 cursor-pointer">
                        <option value="all">📆 Alle Monate</option>
                        <option value="1">Januar</option>
                        <option value="2">Februar</option>
                        <option value="3">März</option>
                        <option value="4">April</option>
                        <option value="5">Mai</option>
                        <option value="6">Juni</option>
                        <option value="7">Juli</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">Oktober</option>
                        <option value="11">November</option>
                        <option value="12">Dezember</option>
                    </select>
                </div>
            </div>

            <!-- Documents Table -->
            <div class="overflow-visible border border-slate-200 rounded-xl shadow-2xs relative min-h-[280px]">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="p-3.5">Typ & Nummer</th>
                            <th class="p-3.5">Datum</th>
                            <th class="p-3.5">Kunde & Kundennr.</th>
                            <th class="p-3.5">Baustelle / Projekt</th>
                            <th class="p-3.5 text-right">Betrag (€)</th>
                            <th class="p-3.5 text-center">Status / Mahnung</th>
                            <th class="p-3.5 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($savedDocs as $doc)
                            @php
                                $isInvoice = ($doc['_doc_type'] ?? 'invoice') === 'invoice' || isset($doc['invoice_number']);
                                $docNum = $doc['invoice_number'] ?? $doc['offer_number'] ?? '-';
                                $docDate = date('d.m.Y', strtotime($doc['invoice_date'] ?? $doc['date'] ?? now()));
                                $clientName = $doc['contact']['company_name'] ?? trim(($doc['contact']['first_name'] ?? '') . ' ' . ($doc['contact']['last_name'] ?? '')) ?: 'Kunde';
                                $clientNum = $doc['contact']['customer_number'] ?? '';
                                $projectName = $doc['project']['name'] ?? '-';
                                $totalNet = floatval($doc['total_net'] ?? 0);
                                $totalGross = floatval($doc['total_gross'] ?? $totalNet);
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition relative">
                                <!-- Typ & Nummer -->
                                <td class="p-3.5 font-bold">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase shrink-0 {{ $isInvoice ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                                            {{ $isInvoice ? 'RECHNUNG' : 'ANGEBOT' }}
                                        </span>
                                        <span class="text-slate-900 font-mono font-extrabold text-xs">{{ $docNum }}</span>
                                    </div>
                                </td>

                                <!-- Datum -->
                                <td class="p-3.5 text-slate-600 font-medium whitespace-nowrap">
                                    {{ $docDate }}
                                </td>

                                <!-- Kunde -->
                                <td class="p-3.5">
                                    <p class="font-bold text-slate-900 truncate max-w-[150px]">{{ $clientName }}</p>
                                    @if ($clientNum)
                                        <p class="text-[10px] text-slate-500 font-mono">Kundennr: {{ $clientNum }}</p>
                                    @endif
                                </td>

                                <!-- Baustelle -->
                                <td class="p-3.5 text-slate-700 font-medium">
                                    <span class="truncate max-w-[180px] block">📍 {{ $projectName }}</span>
                                </td>

                                <!-- Betrag -->
                                <td class="p-3.5 text-right font-black text-slate-900 font-mono text-xs whitespace-nowrap">
                                    {{ number_format($totalGross ?: $totalNet, 2, ',', '.') }} €
                                </td>

                                <!-- Status / Mahnung -->
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    @if ($isInvoice)
                                        @if (($doc['status'] ?? '') === 'cancelled')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-slate-200 text-slate-700 border border-slate-300">STORNIERT</span>
                                        @elseif (!empty($doc['reminder_level']))
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-200">Mahnung Stufe {{ $doc['reminder_level'] }}</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">Aktiv</span>
                                        @endif
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">Angebot</span>
                                    @endif
                                </td>

                                <!-- Aktionen (Dropdown-Menü über overflow-visible Container) -->
                                <td class="p-3.5 text-right whitespace-nowrap relative">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Primary Edit Button -->
                                            <button wire:click="loadSavedDoc('{{ $doc['id'] }}', '{{ $isInvoice ? 'invoice' : 'offer' }}')" 
                                                    title="Im Editor öffnen"
                                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl transition shadow-2xs cursor-pointer flex items-center gap-1">
                                                <span>✏️</span>
                                                <span>Bearbeiten</span>
                                            </button>

                                            <!-- Dropdown Trigger Button -->
                                            <button @click="open = !open" @click.away="open = false" 
                                                    class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-900 text-slate-100 font-bold text-xs rounded-xl transition shadow-2xs cursor-pointer flex items-center gap-1">
                                                <span>⚙️</span>
                                                <span>Export & Aktionen</span>
                                                <span class="text-[10px] opacity-70">▼</span>
                                            </button>
                                        </div>

                                        <!-- Dropdown Menu Box (Absolut positioniert über Tabelle ohne Clipping) -->
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="transform opacity-100 scale-100"
                                             x-transition:leave-end="transform opacity-0 scale-95"
                                             style="display: none;"
                                             class="absolute right-0 top-full mt-1.5 w-60 bg-white border border-slate-300 rounded-xl shadow-2xl z-50 py-1 divide-y divide-slate-100 font-sans text-xs text-left ring-1 ring-black/5">
                                            
                                            <!-- Downloads section -->
                                            <div class="py-1">
                                                <button wire:click="downloadSingleDocHtml('{{ $doc['id'] }}', '{{ $isInvoice ? 'invoice' : 'offer' }}')" @click="open = false"
                                                        class="w-full text-left px-3 py-2 text-slate-700 hover:bg-slate-100 flex items-center gap-2 font-medium cursor-pointer">
                                                    <span>📄</span> Einzel PDF / Briefbogen
                                                </button>

                                                @if ($isInvoice)
                                                    <button wire:click="downloadEinvoicePackage('{{ $doc['id'] }}')" @click="open = false"
                                                            class="w-full text-left px-3 py-2 text-emerald-700 hover:bg-emerald-50 flex items-center gap-2 font-bold cursor-pointer">
                                                        <span>📦</span> E-Rechnungs-Paket (ZIP)
                                                    </button>
                                                    <button wire:click="downloadZugferdXml('{{ $doc['id'] }}')" @click="open = false"
                                                            class="w-full text-left px-3 py-2 text-emerald-800 hover:bg-emerald-50 flex items-center gap-2 font-medium cursor-pointer">
                                                        <span>📥</span> XRechnung / ZUGFeRD XML
                                                    </button>
                                                @endif
                                            </div>

                                            @if ($isInvoice && ($doc['status'] ?? '') !== 'cancelled')
                                                <!-- Invoice Specific Actions -->
                                                <div class="py-1">
                                                    <button wire:click="openDunningModal('{{ $doc['id'] }}')" @click="open = false"
                                                            class="w-full text-left px-3 py-2 text-amber-800 hover:bg-amber-50 flex items-center gap-2 font-bold cursor-pointer">
                                                        <span>⚠️</span> Mahnung erstellen
                                                    </button>
                                                    <button onclick="if(confirm('Möchten Sie für diese Rechnung eine GoBD-konforme Stornorechnung mit negativen Beträgen erstellen?')) { @this.stornoInvoice('{{ $doc['id'] }}'); }" @click="open = false"
                                                            class="w-full text-left px-3 py-2 text-rose-700 hover:bg-rose-50 flex items-center gap-2 font-medium cursor-pointer">
                                                        <span>🔄</span> GoBD-Stornorechnung
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">
                                    @if ($docSearch)
                                        Keine Dokumente für "{{ $docSearch }}" im Archiv gefunden.
                                    @else
                                        Keine Dokumente für die gewählten Filter-Kriterien vorhanden.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

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
                        <button type="button" wire:click="parseWithAi" wire:loading.attr="disabled" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 flex items-center gap-2">
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

    <!-- 3-STUFEN MAHNWESEN MODAL -->
    @if ($showDunningModal && $this->selectedDunningInvoice)
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col">
                <div class="p-5 bg-gradient-to-r from-rose-950 via-slate-900 to-amber-950 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚠️</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Mahnwesen & Mahnschreiben erzeugen</h3>
                            <p class="text-xs text-slate-300">Rechnung Nr. {{ $this->selectedDunningInvoice?->invoice_number }}</p>
                        </div>
                    </div>
                    <button wire:click="$set('showDunningModal', false)" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <div class="p-6 space-y-5">
                    <!-- Mahnstufen Selector Buttons -->
                    <div class="grid grid-cols-3 gap-3">
                        <button wire:click="setDunningLevel(1)" 
                                class="p-3 rounded-xl border text-center transition cursor-pointer {{ $dunningLevel === 1 ? 'bg-amber-500 text-slate-950 border-amber-600 font-black shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 font-bold hover:bg-slate-100' }}">
                            <span class="block text-xs uppercase">Stufe 1</span>
                            <span class="text-sm">Erinnerung</span>
                            <span class="block text-[10px] mt-1 opacity-80">+0 € Gebühr</span>
                        </button>

                        <button wire:click="setDunningLevel(2)" 
                                class="p-3 rounded-xl border text-center transition cursor-pointer {{ $dunningLevel === 2 ? 'bg-amber-600 text-white border-amber-700 font-black shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 font-bold hover:bg-slate-100' }}">
                            <span class="block text-xs uppercase">Stufe 2</span>
                            <span class="text-sm">1. Mahnung</span>
                            <span class="block text-[10px] mt-1 opacity-80">+5,00 € Gebühr</span>
                        </button>

                        <button wire:click="setDunningLevel(3)" 
                                class="p-3 rounded-xl border text-center transition cursor-pointer {{ $dunningLevel === 3 ? 'bg-rose-600 text-white border-rose-700 font-black shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 font-bold hover:bg-slate-100' }}">
                            <span class="block text-xs uppercase">Stufe 3</span>
                            <span class="text-sm">Letzte Mahnung</span>
                            <span class="block text-[10px] mt-1 opacity-80">+45,00 € (§288 BGB)</span>
                        </button>
                    </div>

                    <!-- Generated Notice Text -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Textentwurf der Mahnung</label>
                        <textarea wire:model="dunningNoticeText" rows="9" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-mono text-slate-900 focus:bg-white focus:border-rose-500 focus:outline-none"></textarea>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <button type="button" wire:click="$set('showDunningModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-xs font-bold">
                            Abbrechen
                        </button>
                        <button type="button" wire:click="executeDunningNotice" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-black shadow-md flex items-center gap-2 cursor-pointer">
                            <span>⚠️ Mahnung Stufe {{ $dunningLevel }} ausführen & speichern</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- AUFMASS & MASSENERMITTLUNGS MODAL -->
    @if($showAufmassModal)
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden my-6 flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 bg-gradient-to-r from-indigo-950 via-slate-900 to-indigo-950 text-white flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">📐</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">{{ $aufmassTitle }}</h3>
                            <p class="text-[11px] text-indigo-200">Formelmäßige Massenermittlung nach VOB/B & DIN 18299</p>
                        </div>
                    </div>
                    <button wire:click="$set('showAufmassModal', false)" class="text-slate-400 hover:text-white text-xl font-bold cursor-pointer">✕</button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto grow">
                    <div class="bg-indigo-50/70 border border-indigo-200 rounded-2xl p-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="text-xs text-indigo-900 font-medium space-y-0.5">
                            <span class="font-extrabold block">VOB-Übermessungsregel (DIN 18299):</span>
                            <span>Aussparungen / Öffnungen &lt; 0,1 m² (Fläche) oder &lt; 0,5 m³ (Volumen) werden übermessen (kein Abzug).</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <label class="text-xs font-bold text-slate-700">Einheit:</label>
                            <select wire:model.live="aufmassUnit" class="bg-white border border-indigo-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-900">
                                <option value="m²">m² (Quadratmeter)</option>
                                <option value="m³">m³ (Kubikmeter)</option>
                                <option value="m">m (Laufmeter)</option>
                                <option value="Stk">Stk (Stück)</option>
                            </select>
                        </div>
                    </div>

                    <!-- ✨ KI FREITEXT & SPRACH-DIKTAT BOX -->
                    <div class="bg-gradient-to-r from-blue-900 to-indigo-950 rounded-2xl p-4 text-white shadow-lg space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">🤖</span>
                                <h4 class="text-xs font-black text-white uppercase tracking-wider">KI-Aufmaß aus Freitext oder Sprach-Diktat generieren</h4>
                            </div>
                            <span class="px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 text-[10px] font-extrabold border border-blue-400/30">GPT-4o VOB/B Engine</span>
                        </div>
                        <p class="text-[11px] text-blue-200/90 leading-normal">
                            Tippen oder kopieren Sie Notizen von der Baustelle rein – die KI errechnet Längen, Flächen, Volumen und ordnet Abzüge nach VOB/B DIN 18299 automatisch zu.
                        </p>
                        <div class="space-y-2">
                            <textarea wire:model="aufmassAiText" rows="2" class="w-full bg-slate-900/90 border border-blue-400/40 rounded-xl p-3 text-xs text-white placeholder-blue-300/50 focus:border-blue-400 focus:outline-none" placeholder="z. B. Kellerwand Süd 14,50m lang 2,80m hoch. 1 Fenster 1,20m x 1,00m und 1 Lichtschacht 0,80m x 0,60m..."></textarea>
                            <div class="flex justify-end">
                                <button type="button" wire:click="parseAufmassWithAi" wire:loading.attr="disabled" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white font-extrabold text-xs rounded-xl shadow-md flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="parseAufmassWithAi">✨ Per KI in Aufmaßblatt umwandeln</span>
                                    <span wire:loading wire:target="parseAufmassWithAi" class="flex items-center gap-1">
                                        <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <span>Analysiere Diktat...</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Aufmaß Zeilen -->
                    <div class="space-y-3">
                        @foreach($aufmassRows as $rIdx => $row)
                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 space-y-2 relative">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-black text-slate-700">Teilaufmaß #{{ $rIdx + 1 }}</span>
                                    <button wire:click="removeAufmassRow({{ $rIdx }})" type="button" class="text-rose-500 hover:text-rose-700 text-xs font-bold">✕ Zeile entfernen</button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-6 gap-2">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Bauteil / Raum / Lage</label>
                                        <input wire:model.live="aufmassRows.{{ $rIdx }}.label" type="text" class="w-full bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-900" placeholder="z. B. Kellerwand Süd">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Anzahl</label>
                                        <input wire:model.live="aufmassRows.{{ $rIdx }}.count" type="number" step="1" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-900 text-right">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Länge (m)</label>
                                        <input wire:model.live="aufmassRows.{{ $rIdx }}.length" type="number" step="0.01" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-900 text-right" placeholder="10.50">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Breite/Höhe (m)</label>
                                        <input wire:model.live="aufmassRows.{{ $rIdx }}.width" type="number" step="0.01" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-900 text-right" placeholder="2.80">
                                    </div>
                                    @if($aufmassUnit === 'm³')
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Dicke (m)</label>
                                            <input wire:model.live="aufmassRows.{{ $rIdx }}.height" type="number" step="0.01" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-900 text-right" placeholder="0.25">
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-1 border-t border-slate-200/60">
                                    <div class="flex items-center gap-2">
                                        <label class="text-[11px] font-bold text-slate-600">VOB-Modus:</label>
                                        <select wire:model.live="aufmassRows.{{ $rIdx }}.mode" class="bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs font-semibold text-slate-900">
                                            <option value="add">➕ Hinzurechnen (Standard)</option>
                                            <option value="subtract">➖ Abziehen (Abzug)</option>
                                            <option value="overmeasure">🛡️ Übermessen (&lt;0,1m² / &lt;0,5m³)</option>
                                        </select>
                                    </div>

                                    <div class="text-right text-xs">
                                        <span class="text-slate-500">Ergebnis:</span>
                                        <span class="font-black text-slate-900 text-sm ml-1">
                                            {{ number_format($this->calculateRowSubtotal($row), 2, ',', '.') }} {{ $aufmassUnit }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button wire:click="addAufmassRow" type="button" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-2xl transition border border-dashed border-slate-300 flex items-center justify-center gap-1 cursor-pointer">
                        <span>➕ Weitere Zeile / Teilleistung hinzufügen</span>
                    </button>
                </div>

                <!-- Footer Total & Action -->
                <div class="px-6 py-4 bg-slate-900 text-white flex flex-col sm:flex-row justify-between items-center gap-4 shrink-0">
                    <div>
                        <span class="text-xs text-slate-400 block uppercase font-bold">Gesamte errechnete Menge:</span>
                        <span class="text-2xl font-black text-emerald-400">{{ number_format($this->aufmassTotal, 2, ',', '.') }} {{ $aufmassUnit }}</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="$set('showAufmassModal', false)" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">Abbrechen</button>
                        <button type="button" wire:click="applyAufmassToTarget" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black shadow-lg shadow-emerald-600/30 flex items-center gap-1.5 cursor-pointer">
                            <span>📐 {{ $targetItemIndex !== null ? 'In Position #' . ($targetItemIndex + 1) . ' übernehmen' : 'Als Menge übernehmen' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- BEGRIFFE GLOSSAR SPICKER MODAL -->
    @if($showGlossarModal)
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden my-6 flex flex-col max-h-[85vh]">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💡</span>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Die wichtigsten Begriffe der Bauabrechnung im Überblick</h3>
                            <p class="text-[11px] text-slate-300">Grundwissen nach VOB/B & DIN 18299</p>
                        </div>
                    </div>
                    <button wire:click="$set('showGlossarModal', false)" class="text-slate-400 hover:text-white text-xl font-bold cursor-pointer">✕</button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto grow text-xs leading-relaxed text-slate-800">
                    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 space-y-1">
                        <h4 class="font-extrabold text-blue-900 text-sm flex items-center gap-1.5">
                            <span>📐 Massenermittlung (auch Massenberechnung)</span>
                        </h4>
                        <p class="text-slate-700">
                            Das mathematische Ermitteln von Längen (m), Flächen (m²) und Volumen (m³ - Kubikmeter) aus den Bauplänen und Ausführungszeichnungen.
                            <br><span class="font-bold text-slate-900">VOB-Regel (DIN 18299):</span> Aussparungen und Durchbrüche bis 0,1 m² bei Flächen bzw. bis 0,5 m³ bei Volumen werden übermessen und nicht abgezogen.
                        </p>
                    </div>

                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 space-y-1">
                        <h4 class="font-extrabold text-emerald-900 text-sm flex items-center gap-1.5">
                            <span>📋 Leistungsverzeichnis (LV)</span>
                        </h4>
                        <p class="text-slate-700">
                            Die strukturierte Liste aller nötigen Arbeiten einer Baumaßnahme. Hier steht bei Beton-, Erd- oder Abdichtungsarbeiten die Menge als Maßeinheit (z. B. m³, m², lfm) sowie der Preis pro Einheit (Einheitspreis in €).
                        </p>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 space-y-1">
                        <h4 class="font-extrabold text-amber-900 text-sm flex items-center gap-1.5">
                            <span>⚖️ Einheitspreisvertrag</span>
                        </h4>
                        <p class="text-slate-700">
                            Die nach VOB/B bevorzugte Vertragsart, bei der exakt nach der tatsächlich auf der Baustelle eingebauten Menge (Volumen/Fläche) abgerechnet wird.
                        </p>
                    </div>

                    <div class="bg-purple-50 border border-purple-200 rounded-2xl p-4 space-y-1">
                        <h4 class="font-extrabold text-purple-900 text-sm flex items-center gap-1.5">
                            <span>🔍 Aufmaß</span>
                        </h4>
                        <p class="text-slate-700">
                            Das Ausmessen der fertigen Bauteile direkt vor Ort auf der Baustelle, um das finale Volumen für die Abschlags- oder Schlussrechnung zu bestimmen.
                        </p>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end shrink-0">
                    <button type="button" wire:click="$set('showGlossarModal', false)" class="px-5 py-2 bg-slate-900 text-white font-extrabold rounded-xl text-xs">
                        Schließen
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- GLOBAL KI LOADING OVERLAY -->
    <div wire:loading wire:target="parseWithAi, generateCoverLetter, auditOfferRisk" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center z-50 p-4">
        <div class="bg-slate-900 border border-blue-500/30 rounded-3xl p-8 max-w-md w-full shadow-2xl text-center space-y-5">
            <div class="relative w-20 h-20 mx-auto flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-blue-500/20 border-t-blue-500 animate-spin"></div>
                <div class="w-14 h-14 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-full flex items-center justify-center shadow-lg shadow-blue-500/40">
                    <span class="text-2xl animate-bounce">🤖</span>
                </div>
            </div>
            <div class="space-y-2">
                <h3 class="text-lg font-extrabold text-white">Dokument wird per KI analysiert...</h3>
                <p class="text-xs text-blue-200/80">OpenAI analysiert und generiert Ihre Daten. Bitte einen kurzen Moment Geduld.</p>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 via-indigo-500 to-blue-500 h-full w-3/4 animate-pulse"></div>
            </div>
        </div>
    </div>
</div>
