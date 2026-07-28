<?php

namespace App\Services;

use OpenAI;
use Exception;
use App\Models\Project;
use App\Models\DailyLog;
use App\Models\Defect;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SubcontractorInvoice;
use App\Models\ActualCost;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OpenAiAgentService
{
    protected ?OpenAI\Client $client = null;
    protected string $model;

    public function __construct()
    {
        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if ($apiKey) {
            $this->client = OpenAI::client($apiKey);
        }
        $this->model = config('services.openai.model') ?: env('OPENAI_MODEL', 'gpt-4o');
    }

    /**
     * Transcribe spoken audio using OpenAI Whisper API.
     *
     * @param string $filePath
     * @return string Transcribed text
     */
    public function transcribeAudio(string $filePath): string
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        try {
            $response = $this->client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($filePath, 'r'),
                'language' => 'de',
            ]);

            return trim($response->text ?? '');
        } catch (Exception $e) {
            Log::error("Whisper Audio Transcription Error: " . $e->getMessage());
            throw new Exception("Fehler bei der Spracheingabe (Whisper): " . $e->getMessage());
        }
    }

    /**
     * Analyze construction site photo using OpenAI GPT-4o Vision API.
     *
     * @param string $imagePath
     * @return string Detailed AI description of the construction site photo
     */
    public function analyzePhoto(string $imagePath): string
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $response = $this->client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Du bist ein erfahrener Bauleiter und Baugutachter der BT Bautechnik UG. Analysiere das Baustellen-Foto präzise: Erfasse Baumängel, Bausubstanz, Baustoffe und Ausführungsstand. Gib eine prägnante, professionelle Zusammenfassung auf Deutsch ohne Sternchen (**).'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Bitte analysiere dieses Bestandsaufnahme-Foto von der Baustelle.'],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}"
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            return trim(preg_replace('/\*\*|\*/', '', $response->choices[0]->message->content ?? ''));
        } catch (Exception $e) {
            Log::error("GPT-4o Vision Photo Analysis Error: " . $e->getMessage());
            throw new Exception("Fehler bei der Bildanalyse (GPT-4o Vision): " . $e->getMessage());
        }
    }

    /**
     * Run the Agent loop on a user message with tool calling capability.
     *
     * @param string $userPrompt
     * @param array $chatHistory
     * @return array ['reply' => string, 'tools_executed' => array]
     */
    public function runAgent(string $userPrompt, array $chatHistory = []): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $tools = $this->getAvailableTools();

        $messages = [
            [
                'role' => 'system',
                'content' => "Du bist der autonome KI-Betriebsassistent (Copilot) der BT Bautechnik UG. Deine Aufgabe ist es, Aufgaben für das Bauunternehmen selbstständig auszuführen.\n" .
                    "Du kannst Bautagebuch-Einträge anlegen, Mängel erzeugen & aktualisieren, Baustellen-Risiken analysieren, Kontakte suchen, Rechnungs-Entwürfe erstellen, Baukosten erfassen und offene Zahlungen prüfen.\n" .
                    "Verwende deine Werkzeuge (Tools) wann immer eine Aktion ausgeführt werden soll, und bestätige die Ausführung anschließend höflich, präzise und übersichtlich.\n" .
                    "Füge bei erstellten Objekten nützliche Markdown-Links ein (z.B. [Zu den Bautagebüchern](/bautagebuch), [Zu den Rechnungen](/rechnungen), [Zu den Mängeln](/maengel), [Zu den Baukosten](/baukosten))."
            ]
        ];

        // Append recent chat history
        foreach ($chatHistory as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $toolsExecuted = [];
        
        try {
            $response = $this->client->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ]);

            $choice = $response->choices[0]->message;

            // Handle Tool Calls
            if (!empty($choice->toolCalls)) {
                $messages[] = $choice->toArray();

                foreach ($choice->toolCalls as $toolCall) {
                    $functionName = $toolCall->function->name;
                    $arguments = json_decode($toolCall->function->arguments, true) ?? [];

                    Log::info("AI Agent Tool Execution: {$functionName}", $arguments);

                    $toolResult = $this->executeTool($functionName, $arguments);
                    $toolsExecuted[] = [
                        'tool' => $functionName,
                        'result' => $toolResult['summary'] ?? 'Erfolgreich ausgeführt'
                    ];

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->id,
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE)
                    ];
                }

                // Final summary response from AI after tools run
                $finalResponse = $this->client->chat()->create([
                    'model' => $this->model,
                    'messages' => $messages
                ]);

                return [
                    'reply' => $finalResponse->choices[0]->message->content,
                    'tools_executed' => $toolsExecuted
                ];
            }

            return [
                'reply' => $choice->content ?? 'Aufgabe erfolgreich verarbeitet.',
                'tools_executed' => []
            ];

        } catch (Exception $e) {
            Log::error("OpenAiAgentService Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Define the tools available to the OpenAI Agent
     */
    protected function getAvailableTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_daily_log',
                    'description' => 'Erstellt einen neuen Bautagebuch-Eintrag für eine Baustelle.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name oder Stichwort der Baustelle (z.B. Berching)'],
                            'weather' => ['type' => 'string', 'description' => 'Sonnig, Bewölkt, Regen, Frost oder Schnee'],
                            'temperature' => ['type' => 'string', 'description' => 'Temperatur z.B. 22°C'],
                            'workers_count' => ['type' => 'integer', 'description' => 'Anzahl Handwerker/Monteure'],
                            'work_performed' => ['type' => 'string', 'description' => 'Geleistete Arbeiten / Gewerk'],
                            'special_occurrences' => ['type' => 'string', 'description' => 'Besonderheiten, Verzögerungen oder Keines']
                        ],
                        'required' => ['project_name', 'work_performed']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_defect',
                    'description' => 'Erfasst einen Mangel oder eine Restarbeit auf einer Baustelle.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'title' => ['type' => 'string', 'description' => 'Titel des Mangels'],
                            'location' => ['type' => 'string', 'description' => 'Ort / Bauteil (z.B. Dachgeschoss Haus B)'],
                            'description' => ['type' => 'string', 'description' => 'Mängelbeschreibung'],
                            'deadline_days' => ['type' => 'integer', 'description' => 'Frist in Tagen (Standard 7)'],
                            'subcontractor_name' => ['type' => 'string', 'description' => 'Name des verantwortlichen Subunternehmers (Optional)']
                        ],
                        'required' => ['project_name', 'title', 'description']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_defect_status',
                    'description' => 'Aktualisiert den Status eines Mangels (z.B. auf behoben, in_bearbeitung, abgenommen).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'defect_title' => ['type' => 'string', 'description' => 'Titel oder Stichwort des Mangels'],
                            'status' => ['type' => 'string', 'enum' => ['offen', 'in_bearbeitung', 'behoben', 'abgenommen'], 'description' => 'Neuer Status des Mangels'],
                            'notes' => ['type' => 'string', 'description' => 'Ergänzende Anmerkung oder Begründung']
                        ],
                        'required' => ['defect_title', 'status']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_draft_invoice',
                    'description' => 'Erstellt einen Rechnungs-Entwurf für einen Kunden / Baustelle.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'client_name' => ['type' => 'string', 'description' => 'Kundenname / Firma'],
                            'amount' => ['type' => 'number', 'description' => 'Nettobetrag in Euro'],
                            'description' => ['type' => 'string', 'description' => 'Leistungsbeschreibung der Rechnung'],
                            'invoice_type' => ['type' => 'string', 'enum' => ['Abschlagsrechnung', 'Schlussrechnung', 'Einzelrechnung'], 'description' => 'Rechnungsart']
                        ],
                        'required' => ['amount', 'description']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_subcontractor_cost',
                    'description' => 'Erfasst eine Baukosten-Eingangsrechnung eines Subunternehmers / Lieferanten.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'subcontractor_name' => ['type' => 'string', 'description' => 'Firma oder Name des Nachunternehmers'],
                            'amount' => ['type' => 'number', 'description' => 'Nettobetrag in Euro'],
                            'description' => ['type' => 'string', 'description' => 'Beschreibung der Gewerk-Leistung']
                        ],
                        'required' => ['project_name', 'amount', 'description']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_overdue_invoices',
                    'description' => 'Fragt offene oder überfällige Kundenrechnungen und Beträge ab.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'status_filter' => ['type' => 'string', 'description' => 'Optionaler Status-Filter (z.B. offen, gemahnt, entwurf)'],
                            'project_name' => ['type' => 'string', 'description' => 'Optionaler Name der Baustelle']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'analyze_project_risks',
                    'description' => 'Analysiert die Ist-Kosten, Budgets und Mängel einer Baustelle und gibt eine Risikoeinschätzung ab.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle']
                        ],
                        'required' => ['project_name']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recent_daily_logs',
                    'description' => 'Fragt die Bautagebücher der letzten Tage für eine Baustelle ab.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle']
                        ],
                        'required' => ['project_name']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_database',
                    'description' => 'Sucht in Baustellen, Projekten, Kontakten und Rechnungen nach Schlagwörtern.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Suchbegriff (z.B. Firma, Kundenname, Baustelle)']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_knowledge_base',
                    'description' => 'Durchsucht die Firmen-Wissensdatenbank (DIN-Normen, Baustellenvorschriften, Verträge, Fachwissen) mit OpenAI Vektor-Semantik.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Suchbegriff oder Fachfrage (z.B. DIN 18533 Bitumen Überlappung)']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_weekly_report',
                    'description' => 'Erstellt einen zusammenfassenden KI-Wochenbericht für Hausverwaltungen/Eigentümer auf Basis der Bautagebücher der letzten 7 Tage.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle']
                        ],
                        'required' => ['project_name']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_vob_notice',
                    'description' => 'Erzeugt ein formell rechtssicheres VOB/B Schreiben (Bedenkenanmeldung gem. § 4 Abs. 3 VOB/B oder Behinderungsanzeige gem. § 6 VOB/B).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'notice_type' => ['type' => 'string', 'enum' => ['Bedenkenanmeldung', 'Behinderungsanzeige'], 'description' => 'Art des Schreiben'],
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'details' => ['type' => 'string', 'description' => 'Beschreibung des Grundes / Mangel / Behinderung (z.B. feuchter Untergrund Estrich)']
                        ],
                        'required' => ['notice_type', 'project_name', 'details']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_project',
                    'description' => 'Legt eine neue Baustelle / ein neues Projekt im System an (z.B. nach einer Bestandsaufnahme vor Ort).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Name oder Bezeichnung der Baustelle'],
                            'city_street' => ['type' => 'string', 'description' => 'Straße und Hausnummer'],
                            'zip' => ['type' => 'string', 'description' => 'Postleitzahl'],
                            'city' => ['type' => 'string', 'description' => 'Ort'],
                            'work_type' => ['type' => 'string', 'description' => 'Gewerk / Art der Arbeiten (z.B. Abdichtung, Dachsanierung)'],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'active', 'paused'], 'description' => 'Status (draft = Bestandsaufnahme/Planung, active = Laufende Baustelle)']
                        ],
                        'required' => ['name', 'work_type']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_project',
                    'description' => 'Löscht eine bestehende Baustelle / ein Projekt aus dem System.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der zu löschenden Baustelle']
                        ],
                        'required' => ['project_name']
                    ]
                ]
            ]
        ];
    }

    /**
     * Execute internal PHP logic for tool calls
     */
    protected function executeTool(string $name, array $args): array
    {
        switch ($name) {
            case 'create_daily_log':
                $project = Project::where('name', 'LIKE', '%' . ($args['project_name'] ?? '') . '%')->first();
                if (!$project) {
                    $project = Project::first();
                }

                $log = DailyLog::create([
                    'project_id' => $project->id,
                    'date' => date('Y-m-d'),
                    'weather' => $args['weather'] ?? 'Sonnig',
                    'temperature' => $args['temperature'] ?? '20°C',
                    'workers_count' => intval($args['workers_count'] ?? 2),
                    'work_performed' => $args['work_performed'] ?? 'Arbeiten ausgeführt',
                    'special_occurrences' => $args['special_occurrences'] ?? 'Keine Störungen',
                ]);

                return [
                    'success' => true,
                    'summary' => "Bautagebuch-Eintrag für Baustelle '{$project->name}' am " . date('d.m.Y') . " erfolgreich angelegt. [Zu den Bautagebüchern](/bautagebuch)",
                    'log_id' => $log->id
                ];

            case 'create_defect':
                $project = Project::where('name', 'LIKE', '%' . ($args['project_name'] ?? '') . '%')->first();
                if (!$project) {
                    $project = Project::first();
                }

                $contact = null;
                if (!empty($args['subcontractor_name'])) {
                    $contact = Contact::where('name', 'LIKE', '%' . $args['subcontractor_name'] . '%')
                        ->orWhere('company_name', 'LIKE', '%' . $args['subcontractor_name'] . '%')
                        ->first();
                }

                $days = intval($args['deadline_days'] ?? 7);

                $defect = Defect::create([
                    'project_id' => $project->id,
                    'assigned_contact_id' => $contact?->id,
                    'title' => $args['title'],
                    'location' => $args['location'] ?? 'Baustelle',
                    'description' => $args['description'],
                    'deadline' => date('Y-m-d', strtotime("+{$days} days")),
                    'priority' => 'hoch',
                    'status' => 'offen'
                ]);

                return [
                    'success' => true,
                    'summary' => "Mangel '{$defect->title}' für Baustelle '{$project->name}' erfasst. Frist: " . date('d.m.Y', strtotime($defect->deadline)) . ". [Zu den Mängeln](/maengel)",
                    'defect_id' => $defect->id
                ];

            case 'update_defect_status':
                $defect = Defect::where('title', 'LIKE', '%' . ($args['defect_title'] ?? '') . '%')->first();
                if (!$defect) {
                    return ['success' => false, 'summary' => "Mangel mit Titel '{$args['defect_title']}' nicht gefunden."];
                }

                $newStatus = $args['status'];
                $defect->update(['status' => $newStatus]);

                return [
                    'success' => true,
                    'summary' => "Status von Mangel '{$defect->title}' erfolgreich auf '{$newStatus}' geändert. [Zu den Mängeln](/maengel)",
                    'defect_id' => $defect->id
                ];

            case 'create_draft_invoice':
                $project = Project::where('name', 'LIKE', '%' . ($args['project_name'] ?? '') . '%')->first() ?: Project::first();
                $contact = Contact::where('name', 'LIKE', '%' . ($args['client_name'] ?? '') . '%')->orWhere('company_name', 'LIKE', '%' . ($args['client_name'] ?? '') . '%')->first();

                $amount = floatval($args['amount'] ?? 0.0);
                $invNumber = 'RE-' . date('Y') . '-' . rand(100, 999);

                $invoice = Invoice::create([
                    'project_id' => $project?->id,
                    'contact_id' => $contact?->id,
                    'invoice_number' => $invNumber,
                    'invoice_date' => date('Y-m-d'),
                    'due_date' => date('Y-m-d', strtotime('+14 days')),
                    'status' => 'entwurf',
                    'total_net' => $amount,
                    'total_gross' => $amount * 1.19,
                    'notes' => ($args['invoice_type'] ?? 'Abschlagsrechnung') . ' - ' . ($args['description'] ?? 'Per KI-Agent generiert')
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $args['description'] ?? 'Bauleistung',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount
                ]);

                return [
                    'success' => true,
                    'summary' => "Rechnungs-Entwurf {$invNumber} über " . number_format($amount, 2, ',', '.') . " € (Brutto " . number_format($amount * 1.19, 2, ',', '.') . " €) erstellt. [Zu den Rechnungen](/rechnungen)",
                    'invoice_id' => $invoice->id
                ];

            case 'add_subcontractor_cost':
                $project = Project::where('name', 'LIKE', '%' . ($args['project_name'] ?? '') . '%')->first() ?: Project::first();
                $contact = Contact::where('name', 'LIKE', '%' . ($args['subcontractor_name'] ?? '') . '%')->orWhere('company_name', 'LIKE', '%' . ($args['subcontractor_name'] ?? '') . '%')->first();

                $amount = floatval($args['amount'] ?? 0.0);

                $subInv = SubcontractorInvoice::create([
                    'project_id' => $project->id,
                    'contact_id' => $contact?->id,
                    'invoice_number' => 'NU-' . date('Y') . '-' . rand(100, 999),
                    'invoice_date' => date('Y-m-d'),
                    'amount_net' => $amount,
                    'tax_mode' => '13b',
                    'status' => 'in_pruefung',
                    'description' => $args['description'] ?? 'Nachunternehmerleistung'
                ]);

                ActualCost::create([
                    'project_id' => $project->id,
                    'cost_type' => 'subcontractor',
                    'cost_amount' => $amount,
                    'cost_date' => date('Y-m-d'),
                    'description' => ($args['subcontractor_name'] ?? 'Nachunternehmer') . ': ' . ($args['description'] ?? 'Gewerkleistung')
                ]);

                return [
                    'success' => true,
                    'summary' => "Baukosten-Rechnung über " . number_format($amount, 2, ',', '.') . " € für Baustelle '{$project->name}' verbucht. [Zu den Baukosten](/baukosten)",
                    'subcontractor_invoice_id' => $subInv->id
                ];

            case 'get_overdue_invoices':
                $query = Invoice::with(['contact', 'project']);

                if (!empty($args['status_filter'])) {
                    $query->where('status', 'LIKE', '%' . $args['status_filter'] . '%');
                } else {
                    $query->whereIn('status', ['offen', 'gemahnt', 'entwurf']);
                }

                if (!empty($args['project_name'])) {
                    $project = Project::where('name', 'LIKE', '%' . $args['project_name'] . '%')->first();
                    if ($project) {
                        $query->where('project_id', $project->id);
                    }
                }

                $invoices = $query->get();
                $totalSum = $invoices->sum('total_gross');

                $summaryList = [];
                foreach ($invoices as $inv) {
                    $summaryList[] = "- Rechnung **{$inv->invoice_number}**: " . number_format($inv->total_gross, 2, ',', '.') . " € (Status: {$inv->status})";
                }

                return [
                    'success' => true,
                    'count' => $invoices->count(),
                    'total_sum' => $totalSum,
                    'summary' => "Es wurden " . $invoices->count() . " offene Rechnungen mit einer Gesamtsumme von " . number_format($totalSum, 2, ',', '.') . " € gefunden.\n\n" . (empty($summaryList) ? "Keine passenden Rechnungen." : implode("\n", $summaryList)) . "\n\n[Zu den Rechnungen](/rechnungen)"
                ];

            case 'get_recent_daily_logs':
                $project = Project::where('name', 'LIKE', '%' . ($args['project_name'] ?? '') . '%')->first() ?: Project::first();
                $logs = DailyLog::where('project_id', $project->id)->orderBy('date', 'desc')->take(5)->get();

                $logEntries = [];
                foreach ($logs as $l) {
                    $logEntries[] = "- **" . date('d.m.Y', strtotime($l->date)) . "** ({$l->weather}, {$l->temperature}): {$l->workers_count} Mann. Arbeiten: {$l->work_performed}";
                }

                return [
                    'success' => true,
                    'project' => $project->name,
                    'count' => $logs->count(),
                    'summary' => "Bautagebücher der letzten Tage für '{$project->name}':\n\n" . implode("\n", $logEntries) . "\n\n[Zu den Bautagebüchern](/bautagebuch)"
                ];

            case 'analyze_project_risks':
                $project = Project::with(['budget', 'actualCosts', 'defects'])->where('name', 'LIKE', '%' . ($args['project_name'] ?? '') . '%')->first();
                if (!$project) {
                    return ['success' => false, 'summary' => 'Baustelle nicht gefunden.'];
                }

                $totalBudget = $project->budget?->total_with_buffer ?: 0.00;
                $actualCosts = $project->actualCosts->sum('cost_amount');
                $openDefects = $project->defects->where('status', '!=', 'behoben')->count();

                $risk = ($actualCosts > $totalBudget && $totalBudget > 0) ? 'KRITISCH (Budget überschritten)' : (($openDefects > 2) ? 'ERHÖHT (Offene Mängel)' : 'GERING (Im Plan)');

                return [
                    'success' => true,
                    'project_name' => $project->name,
                    'total_budget' => $totalBudget,
                    'actual_costs' => $actualCosts,
                    'open_defects_count' => $openDefects,
                    'risk_level' => $risk,
                    'summary' => "Baustelle '{$project->name}': Ist-Kosten " . number_format($actualCosts, 2, ',', '.') . " € von Soll-Budget " . number_format($totalBudget, 2, ',', '.') . " €. Offene Mängel: {$openDefects}. Risiko: {$risk}."
                ];

            case 'search_database':
                $query = $args['query'] ?? '';
                $projects = Project::where('name', 'LIKE', "%{$query}%")->orWhere('city_street', 'LIKE', "%{$query}%")->get(['id', 'name', 'city_street', 'status']);
                $contacts = Contact::where('name', 'LIKE', "%{$query}%")->orWhere('company_name', 'LIKE', "%{$query}%")->get(['id', 'name', 'company_name', 'type', 'email']);

                return [
                    'success' => true,
                    'query' => $query,
                    'projects_found' => $projects->toArray(),
                    'contacts_found' => $contacts->toArray(),
                    'summary' => "Gefunden: " . $projects->count() . " Baustellen, " . $contacts->count() . " Kontakte."
                ];

            case 'search_knowledge_base':
                $kbService = app(\App\Services\KnowledgeBaseService::class);
                $queryStr = $args['query'] ?? '';
                $results = $kbService->searchSimilarChunks($queryStr, 4, 0.35);

                if (empty($results)) {
                    return [
                        'success' => true,
                        'count' => 0,
                        'summary' => "In der Wissensdatenbank wurden keine passenden Vektor-Treffer für '{$queryStr}' gefunden. [Zur Wissensdatenbank](/wissen)"
                    ];
                }

                $formattedHits = [];
                foreach ($results as $h) {
                    $scorePercent = round($h['similarity'] * 100);
                    $formattedHits[] = "### 📄 {$h['document_title']} ({$h['category']} • Relevanz: {$scorePercent}%)\n> " . str_replace("\n", "\n> ", $h['content']);
                }

                return [
                    'success' => true,
                    'count' => count($results),
                    'summary' => "Relevante Abschnitte aus der Wissensdatenbank:\n\n" . implode("\n\n", $formattedHits) . "\n\n[Zur Wissensdatenbank](/wissen)"
                ];

            case 'generate_weekly_report':
                $project = Project::where('name', 'LIKE', '%' . ($args['project_name'] ?? '') . '%')->first() ?: Project::first();
                if (!$project) {
                    return ['success' => false, 'summary' => 'Baustelle nicht gefunden.'];
                }

                $logs = DailyLog::where('project_id', $project->id)
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
                    return ['success' => false, 'summary' => "Keine Bautagebücher für Baustelle '{$project->name}' gefunden."];
                }

                $parser = app(\App\Services\OpenAiParserService::class);
                $report = $parser->generateWeeklyReportFromLogs($logs);
                $cleanReport = preg_replace('/\*\*|\*/', '', $report);

                return [
                    'success' => true,
                    'project' => $project->name,
                    'summary' => "📊 KI-Wochenbericht für Baustelle {$project->name}:\n\n" . $cleanReport . "\n\n[Zum Dashboard](/dashboard)"
                ];

            case 'generate_vob_notice':
                $noticeType = $args['notice_type'] ?? 'Bedenkenanmeldung';
                $projectName = $args['project_name'] ?? 'Bauvorhaben';
                $details = $args['details'] ?? '';

                $kbService = app(\App\Services\KnowledgeBaseService::class);
                $relevantKb = $kbService->searchSimilarChunks($noticeType . ' ' . $details, 2, 0.3);

                $contextStr = '';
                foreach ($relevantKb as $k) {
                    $contextStr .= "\n" . $k['content'];
                }

                $prompt = "Erstelle ein rechtssicheres {$noticeType}-Schreiben für ein deutsches Bauunternehmen.\nBaustelle: {$projectName}\nDetails/Grund: {$details}\nVerwende KEINE Markdown-Sternchen (**). Formatiere in klarem Geschäftsbrief-Stil.";
                if ($contextStr) {
                    $prompt .= "\nVerwende folgende Vorgaben aus der Wissensdatenbank:\n" . $contextStr;
                }

                $response = $this->client->chat()->create([
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Du bist Jurist und Bauleiter der BT Bautechnik UG. Erstelle formell rechtssichere Bedenkenanmeldungen (§ 4 Abs. 3 VOB/B) und Behinderungsanzeigen (§ 6 VOB/B). Verwende KEINE Sternchen (**).'],
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]);

                $content = preg_replace('/\*\*|\*/', '', $response->choices[0]->message->content ?? '');

                return [
                    'success' => true,
                    'notice_type' => $noticeType,
                    'summary' => "📄 {$noticeType} gemäß VOB/B erzeugt:\n\n" . $content . "\n\n[Zur Wissensdatenbank](/wissen)"
                ];

            case 'create_project':
                $name = $args['name'] ?? 'Neue Baustelle';
                $street = $args['city_street'] ?? '';
                $zip = $args['zip'] ?? '';
                $city = $args['city'] ?? '';
                $workType = $args['work_type'] ?? 'Sanierung';
                $status = $args['status'] ?? 'active';

                $fullStreet = $street . ($city ? (', ' . $city) : '');

                $project = Project::create([
                    'name' => $name,
                    'city_street' => $fullStreet ?: $name,
                    'zip' => $zip ?: '92334',
                    'work_type' => $workType,
                    'status' => $status,
                    'start_week' => date('W'),
                    'end_week' => date('W', strtotime('+4 weeks'))
                ]);

                return [
                    'success' => true,
                    'project_id' => $project->id,
                    'summary' => "🏗️ Neue Baustelle **{$project->name}** ({$workType}, Status: {$status}) erfolgreich angelegt! [Zur Baustellenübersicht](/projekte)"
                ];

            case 'delete_project':
                $searchName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', '%' . $searchName . '%')->first();

                if (!$project) {
                    return ['success' => false, 'summary' => "⚠️ Baustelle '{$searchName}' wurde nicht im System gefunden."];
                }

                $name = $project->name;
                foreach ($project->photos as $photo) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($photo->photo_path)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->photo_path);
                    }
                }

                $project->delete();

                return [
                    'success' => true,
                    'summary' => "🗑️ Baustelle **{$name}** wurde erfolgreich aus dem System gelöscht!"
                ];

            default:
                return ['success' => false, 'summary' => "Werkzeug '{$name}' unbekannt."];
        }
    }
}
