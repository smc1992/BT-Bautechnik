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
            $this->safeLog('error', "Whisper Audio Transcription Error: " . $e->getMessage());
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
            $this->safeLog('error', "GPT-4o Vision Photo Analysis Error: " . $e->getMessage());
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
                    "Du kannst Bautagebuch-Einträge anlegen, Mängel erzeugen & aktualisieren, Baustellen-Risiken analysieren, Kontakte suchen, Rechnungs-Entwürfe erstellen, Baukosten erfassen, VOB/B Aufmaße & Massenermittlungen berechnen, Juli 2026 Materialpreise prüfen, VOB-Bedenkenanmeldungen generieren und offene Zahlungen prüfen.\n" .
                    "Verwende deine Werkzeuge (Tools) wann immer eine Aktion ausgeführt werden soll, und bestätige die Ausführung anschließend höflich, präzise und übersichtlich.\n" .
                    "Füge bei erstellten Objekten nützliche Markdown-Links ein (z.B. [Zu den Bautagebüchern](/bautagebuch), [Zu den Rechnungen](/rechnungen), [Zu den Mängeln](/maengel), [Zu den Baukosten](/baukosten), [Zum Materialkatalog](/materialien), [Zur Wissensdatenbank](/wissen))."
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

                    $this->safeLog('info', "AI Agent Tool Execution: {$functionName}", $arguments);

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
            $this->safeLog('error', "OpenAiAgentService Error: " . $e->getMessage());
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
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate_aufmass',
                    'description' => 'Berechnet ein VOB-konformes Aufmaß (Massenermittlung) aus einem Diktat, Freitext oder Maßangaben inklusive VOB DIN 18299 Übermessungen.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'Diktierter Text, Freitext oder Maßangaben (z.B. "Kellerwand Süd 14,5m x 2,8m mit Fenster 1,2x1,0m")']
                        ],
                        'required' => ['text']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_materials',
                    'description' => 'Durchsucht den Baustoffkatalog (Stand Juli 2026) nach Materialpreisen, Kategorien und Herstellern.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Name oder Gewerk des Baustoffs (z.B. Bitumen, Injektionsharz, Estrich, Dichtband, PSA)']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'schedule_worker',
                    'description' => 'Teilt einen Handwerker, Monteur oder Subunternehmer für einen Tag auf einer Baustelle ein oder fragt den Einsatzplan ab.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'worker_name' => ['type' => 'string', 'description' => 'Name des Mitarbeiters oder Subunternehmers'],
                            'date' => ['type' => 'string', 'description' => 'Datum im Format YYYY-MM-DD (oder "heute", "morgen")'],
                            'shift_type' => ['type' => 'string', 'enum' => ['ganztags', 'vormittags', 'nachmittags'], 'description' => 'Schicht/Dauer (Standard ganztags)'],
                            'action' => ['type' => 'string', 'enum' => ['create', 'query'], 'description' => 'Einteilen ("create") oder Einsatzplan abfragen ("query")']
                        ],
                        'required' => ['project_name', 'action']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_project_profitability',
                    'description' => 'Führt eine Finanz-Nachkalkulation für eine Baustelle durch (Einnahmen vs. Subunternehmerkosten vs. Baukosten vs. Gewinnmarge).',
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
                    'name' => 'search_contacts',
                    'description' => 'Durchsucht die Firmen-Kontaktdatenbank nach Hausverwaltungen, Bauträgern, Nachunternehmern, Handwerkern und Telefonnummern.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Name, Firma, Kategorie oder Ort des Kontakts']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_defect_pdf',
                    'description' => 'Erstellt ein rechtssicheres VOB/B §13 Mängelrüge-Schreiben an einen Subunternehmer inklusive PDF-Drucklink.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'subcontractor_name' => ['type' => 'string', 'description' => 'Name des Subunternehmers'],
                            'defect_title' => ['type' => 'string', 'description' => 'Titel des Mangels'],
                            'deadline_days' => ['type' => 'integer', 'description' => 'Frist in Tagen (Standard 7)']
                        ],
                        'required' => ['project_name', 'subcontractor_name', 'defect_title']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_site_weather',
                    'description' => 'Prüft das Baustellen-Wetter und VOB-Norm-Anforderungen für Gewerkearbeiten (z.B. Bitumen >5°C, Beton Eisschutz, Malerarbeiten).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'work_type' => ['type' => 'string', 'description' => 'Gewerk/Arbeit (z.B. Bitumenabdichtung, Estrich, Betonieren, Maler)']
                        ],
                        'required' => ['project_name', 'work_type']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_supplement',
                    'description' => 'Erfasst einen VOB/B Nachtrag (Leistungsänderung gem. § 2 Abs. 5 oder unvorhersehbare Bausituation gem. § 2 Abs. 6) für eine Baustelle.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'title' => ['type' => 'string', 'description' => 'Kurztitel des Nachtrags (z.B. Zusätzliche Abdichtung KG)'],
                            'amount_net' => ['type' => 'number', 'description' => 'Nettobetrag in Euro'],
                            'reason' => ['type' => 'string', 'enum' => ['vob_b_2_5', 'vob_b_2_6', 'client_request', 'obstruction', 'other'], 'description' => 'VOB/B Rechtsbegründung'],
                            'description' => ['type' => 'string', 'description' => 'Ausführliche Begründung des Mehraufwands']
                        ],
                        'required' => ['project_name', 'title', 'amount_net']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_project_supplements',
                    'description' => 'Fragt alle Nachträge einer Baustelle ab (genehmigtes Volumen, offene Nachträge).',
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
                    'name' => 'record_time_clock',
                    'description' => 'Erfasst einen Stundenzettel-Eintrag für die mobile Zeiterfassung (MiLoG) für einen Mitarbeiter auf einer Baustelle.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'worker_name' => ['type' => 'string', 'description' => 'Name des Mitarbeiters oder Monteurs'],
                            'hours' => ['type' => 'number', 'description' => 'Geleistete Arbeitsstunden (z.B. 8.0)'],
                            'activity' => ['type' => 'string', 'description' => 'Tätigkeit (z.B. Ausführung Bauleistung, Regiearbeit, Anfahrt)'],
                            'date' => ['type' => 'string', 'description' => 'Datum im Format YYYY-MM-DD (Standard: heute)']
                        ],
                        'required' => ['project_name', 'worker_name', 'hours']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_equipment_compliance',
                    'description' => 'Prüft UVV / DGUV V3 Prüffristen und Baustellen-Standorte aller Maschinen, Werkzeuge und Fahrzeuge.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'filter' => ['type' => 'string', 'enum' => ['all', 'due_only', 'on_site'], 'description' => 'Filter für Prüfung (fällige UVV oder Standort)']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_or_update_contact',
                    'description' => 'Erstellt oder aktualisiert einen Kunden, Bauträger, Hausverwaltung, Generalübernehmer oder Subunternehmer im 360° CRM.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'company_name' => ['type' => 'string', 'description' => 'Firmenname / Unternehmensbezeichnung'],
                            'first_name' => ['type' => 'string', 'description' => 'Vorname des Ansprechpartners'],
                            'last_name' => ['type' => 'string', 'description' => 'Nachname des Ansprechpartners'],
                            'type' => ['type' => 'string', 'enum' => ['kunde', 'bautraeger', 'hausverwaltung', 'generalunternehmer', 'subunternehmer', 'handwerker'], 'description' => 'Kategorie des Kontakts'],
                            'email' => ['type' => 'string', 'description' => 'E-Mail-Adresse'],
                            'phone' => ['type' => 'string', 'description' => 'Telefon- oder Mobilnummer'],
                            'street' => ['type' => 'string', 'description' => 'Straße und Hausnummer'],
                            'zip' => ['type' => 'string', 'description' => 'Postleitzahl'],
                            'city' => ['type' => 'string', 'description' => 'Ort / Stadt'],
                            'notes' => ['type' => 'string', 'description' => 'Notizen, Besonderheiten oder Telefonprotokoll']
                        ],
                        'required' => ['company_name']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_acceptance_protocol',
                    'description' => 'Generiert ein förmliches VOB/B § 12 Bauabnahmeprotokoll inklusive offener Mängel, Fristen und PDF-Download-Link.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle / des Projekts'],
                            'client_representative' => ['type' => 'string', 'description' => 'Vertreter des Bauherrn / Auftraggebers'],
                            'contractor_representative' => ['type' => 'string', 'description' => 'Bauleiter der BT Bautechnik UG (Standard: Bauleitung BT)'],
                            'work_scope' => ['type' => 'string', 'description' => 'Beschreibung des abgenommenen Leistungsumfangs'],
                            'result' => ['type' => 'string', 'enum' => ['ohne_vorbehalt', 'mit_maengeln', 'verweigert'], 'description' => 'Ergebnis der Abnahme'],
                            'warranty_years' => ['type' => 'integer', 'description' => 'Gewährleistungsdauer in Jahren (Standard: 5 gem. VOB/B § 13)']
                        ],
                        'required' => ['project_name']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'save_measurement_sheet',
                    'description' => 'Berechnet und speichert ein digitales Aufmaßblatt (VOB/C DIN 18299) mit einzelnen Messzeilen direkt in der Datenbank ab.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle'],
                            'title' => ['type' => 'string', 'description' => 'Titel des Aufmaßes (z.B. Aufmaß Bodenplatte TG)'],
                            'raw_measurements' => ['type' => 'string', 'description' => 'Maßangaben / Diktat (z.B. "Wand Nord 14.5x2.8, Wand Süd 14.5x2.8, Abzug Tür 1.0x2.1")'],
                            'location_area' => ['type' => 'string', 'description' => 'Bauteil / Achse / Geschoss']
                        ],
                        'required' => ['project_name', 'title', 'raw_measurements']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_overdue_defects_and_escalate',
                    'description' => 'Prüft alle überfälligen Mängel nach Fristablauf und generiert ein VOB/B § 13 Mahnschreiben mit Androhung der Ersatzvornahme.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Optional: Nur für eine bestimmte Baustelle filtern']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'fetch_site_weather_live',
                    'description' => 'Ruft Live-Wetterdaten (Temperatur, Regenmenge, Wind, Eignung für Bauarbeiten) für eine Baustelle per Geokoordinaten/PLZ ab.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => ['type' => 'string', 'description' => 'Name der Baustelle']
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
                    'type' => 'subcontractor',
                    'cost_amount' => $amount,
                    'date' => date('Y-m-d'),
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

            case 'calculate_aufmass':
                $text = $args['text'] ?? '';
                $parser = app(\App\Services\OpenAiParserService::class);
                $aufmassData = $parser->parseAufmassText($text);

                $unit = $aufmassData['unit'] ?? 'm²';
                $rows = $aufmassData['rows'] ?? [];

                $total = 0.0;
                $rowSummaries = [];
                foreach ($rows as $r) {
                    $cnt = floatval($r['count'] ?? 1);
                    $l = floatval($r['length'] ?? 1);
                    $w = floatval($r['width'] ?? 1);
                    $h = floatval($r['height'] ?? 1);
                    $vol = $cnt * $l * $w * $h;

                    $mode = $r['mode'] ?? 'add';
                    if ($mode === 'overmeasure') {
                        $rowSummaries[] = "• **" . ($r['label'] ?: 'Aussparung') . "**: Übermessen DIN 18299 (kein Abzug)";
                    } elseif ($mode === 'subtract') {
                        $total -= $vol;
                        $rowSummaries[] = "• **" . ($r['label'] ?: 'Abzug') . "**: -" . number_format($vol, 2, ',', '.') . " {$unit}";
                    } else {
                        $total += $vol;
                        $rowSummaries[] = "• **" . ($r['label'] ?: 'Teilaufmaß') . "**: +" . number_format($vol, 2, ',', '.') . " {$unit}";
                    }
                }

                $total = max(0.0, round($total, 3));

                return [
                    'success' => true,
                    'unit' => $unit,
                    'total_quantity' => $total,
                    'summary' => "📐 **VOB/B Massenermittlung berechnet:**\n\n" .
                                 implode("\n", $rowSummaries) . "\n\n" .
                                 "**Gesamte abrechenbare Menge:** " . number_format($total, 2, ',', '.') . " {$unit}\n\n" .
                                 "[Zum Aufmaß-Rechner](/rechnungen)"
                ];

            case 'search_materials':
                $query = $args['query'] ?? '';
                $materials = \App\Models\Material::where('name', 'LIKE', "%{$query}%")
                    ->orWhere('category', 'LIKE', "%{$query}%")
                    ->orWhere('supplier', 'LIKE', "%{$query}%")
                    ->take(8)
                    ->get();

                if ($materials->isEmpty()) {
                    return [
                        'success' => true,
                        'count' => 0,
                        'summary' => "Keine Baustoffe für '{$query}' im Juli 2026 Materialkatalog gefunden. [Zum Materialkatalog](/materialien)"
                    ];
                }

                $list = [];
                foreach ($materials as $m) {
                    $list[] = "• **{$m->name}** ({$m->category}): " . number_format($m->unit_price, 2, ',', '.') . " € / {$m->unit} (Lieferant/Hersteller: " . ($m->supplier ?: 'Standard') . ")";
                }

                return [
                    'success' => true,
                    'count' => $materials->count(),
                    'summary' => "📦 **Materialpreise (Stand Juli 2026):**\n\n" . implode("\n", $list) . "\n\n[Zum Materialkatalog](/materialien)"
                ];

            case 'schedule_worker':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first();
                if (!$project) {
                    return ['success' => false, 'summary' => "Baustelle '{$projectName}' nicht gefunden."];
                }

                $action = $args['action'] ?? 'query';
                $targetDate = $args['date'] ?? date('Y-m-d');
                if ($targetDate === 'heute') $targetDate = date('Y-m-d');
                if ($targetDate === 'morgen') $targetDate = date('Y-m-d', strtotime('+1 day'));

                if ($action === 'create') {
                    $workerName = $args['worker_name'] ?? 'Mitarbeiter';
                    $shiftType = $args['shift_type'] ?? 'ganztags';
                    
                    $contact = \App\Models\Contact::where('display_name', 'LIKE', "%{$workerName}%")
                        ->orWhere('company_name', 'LIKE', "%{$workerName}%")
                        ->first();

                    \App\Models\WorkerSchedule::create([
                        'project_id' => $project->id,
                        'contact_id' => $contact ? $contact->id : null,
                        'worker_name' => $workerName,
                        'worker_type' => $contact ? 'subcontractor' : 'employee',
                        'date' => $targetDate,
                        'shift_type' => $shiftType,
                        'notes' => 'Über KI-Agent eingeteilt'
                    ]);

                    return [
                        'success' => true,
                        'summary' => "👷 **Einsatzplan aktualisiert:**\n• **Handwerker/Sub:** {$workerName}\n• **Baustelle:** {$project->name}\n• **Datum:** " . date('d.m.Y', strtotime($targetDate)) . " ({$shiftType})\n\n[Zum Einsatzplaner](/einsatzplan)"
                    ];
                } else {
                    $schedules = \App\Models\WorkerSchedule::where('project_id', $project->id)
                        ->where('date', $targetDate)
                        ->get();

                    if ($schedules->isEmpty()) {
                        return [
                            'success' => true,
                            'summary' => "Keine Einteilungen für '{$project->name}' am " . date('d.m.Y', strtotime($targetDate)) . " gefunden. [Zum Einsatzplaner](/einsatzplan)"
                        ];
                    }

                    $list = [];
                    foreach ($schedules as $s) {
                        $list[] = "• **{$s->worker_name}** (" . ($s->worker_type === 'subcontractor' ? 'Subunternehmer' : 'Eigenleistung') . ") - {$s->shift_type}";
                    }

                    return [
                        'success' => true,
                        'summary' => "👷 **Einsatzplan für {$project->name} (" . date('d.m.Y', strtotime($targetDate)) . "):**\n\n" . implode("\n", $list) . "\n\n[Zum Einsatzplaner](/einsatzplan)"
                    ];
                }

            case 'check_project_profitability':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first();
                if (!$project) {
                    return ['success' => false, 'summary' => "Baustelle '{$projectName}' nicht gefunden."];
                }

                $invoicesTotal = \App\Models\Invoice::where('project_id', $project->id)->sum('total');
                $subInvoicesTotal = \App\Models\SubcontractorInvoice::where('project_id', $project->id)->sum('gross_amount');
                $actualCostsTotal = \App\Models\ActualCost::where('project_id', $project->id)->sum('amount');
                $totalExpenses = $subInvoicesTotal + $actualCostsTotal;
                $profit = $invoicesTotal - $totalExpenses;
                $marginPercent = $invoicesTotal > 0 ? round(($profit / $invoicesTotal) * 100, 1) : 0;

                $summary = "📊 **Finanz-Nachkalkulation für Baustelle: {$project->name}**\n\n" .
                           "• **Fakturierte Einnahmen:** " . number_format($invoicesTotal, 2, ',', '.') . " €\n" .
                           "• **Gebuchte Subunternehmer-Kosten:** " . number_format($subInvoicesTotal, 2, ',', '.') . " €\n" .
                           "• **Sonstige Baukosten:** " . number_format($actualCostsTotal, 2, ',', '.') . " €\n" .
                           "• **Gesamte Ausgaben:** " . number_format($totalExpenses, 2, ',', '.') . " €\n" .
                           "-----------------------------------------\n" .
                           "• **Rohgewinn / Reingewinn:** **" . number_format($profit, 2, ',', '.') . " €**\n" .
                           "• **Gewinnmarge:** **" . $marginPercent . "%**\n\n" .
                           ($profit >= 0 ? "✅ Baustelle läuft profitabel im grünen Bereich." : "⚠️ Achtung: Ausgaben übersteigen bisherige Abrechnungen!");

                return [
                    'success' => true,
                    'summary' => $summary
                ];

            case 'search_contacts':
                $query = $args['query'] ?? '';
                $contacts = \App\Models\Contact::where('display_name', 'LIKE', "%{$query}%")
                    ->orWhere('company_name', 'LIKE', "%{$query}%")
                    ->orWhere('category', 'LIKE', "%{$query}%")
                    ->orWhere('city', 'LIKE', "%{$query}%")
                    ->take(6)
                    ->get();

                if ($contacts->isEmpty()) {
                    return [
                        'success' => true,
                        'summary' => "Keine Kontakte für '{$query}' in der Kontaktdatenbank gefunden."
                    ];
                }

                $list = [];
                foreach ($contacts as $c) {
                    $phoneStr = $c->phone ? " 📞 " . $c->phone : "";
                    $emailStr = $c->email ? " ✉️ " . $c->email : "";
                    $categoryBadge = $c->category ? " ({$c->category})" : "";
                    $list[] = "• **{$c->display_name}**{$categoryBadge}\n  " . ($c->company_name ? "Firma: {$c->company_name} | " : "") . "Ort: {$c->city}{$phoneStr}{$emailStr}";
                }

                return [
                    'success' => true,
                    'summary' => "📇 **Gefundene Kontakte & Ansprechpartner:**\n\n" . implode("\n\n", $list)
                ];

            case 'generate_defect_pdf':
                $projectName = $args['project_name'] ?? '';
                $subName = $args['subcontractor_name'] ?? '';
                $defectTitle = $args['defect_title'] ?? '';
                $deadlineDays = $args['deadline_days'] ?? 7;

                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first();

                $defect = Defect::create([
                    'project_id' => $project ? $project->id : null,
                    'title' => $defectTitle,
                    'location' => 'Baustelle ' . ($project ? $project->name : $projectName),
                    'description' => "VOB/B § 13 Mangel rügen: {$defectTitle}. Nachbesserung verlangt von {$subName}.",
                    'status' => 'open',
                    'deadline' => date('Y-m-d', strtotime("+{$deadlineDays} days")),
                    'subcontractor_name' => $subName
                ]);

                return [
                    'success' => true,
                    'summary' => "📄 **VOB/B Mängelrüge-Schreiben erstellt!**\n\n" .
                                 "• **Mangel:** {$defectTitle}\n" .
                                 "• **Subunternehmer:** {$subName}\n" .
                                 "• **Nachbesserungsfrist:** " . date('d.m.Y', strtotime("+{$deadlineDays} days")) . " ({$deadlineDays} Tage)\n\n" .
                                 "🔗 [Mängel-Übersicht & PDF-Druck öffnen](/maengel)"
                ];

            case 'check_site_weather':
                $projectName = $args['project_name'] ?? '';
                $workType = strtolower($args['work_type'] ?? '');

                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first();
                $latestLog = $project ? DailyLog::where('project_id', $project->id)->orderBy('date', 'desc')->first() : null;

                $currentTemp = $latestLog ? $latestLog->temperature : '20°C';
                $currentWeather = $latestLog ? $latestLog->weather : 'Sonnig';

                $isBitumen = str_contains($workType, 'bitumen') || str_contains($workType, 'abdichtung');
                $isBeton = str_contains($workType, 'beton') || str_contains($workType, 'estrich');

                $warning = "☀️ **Baustellen-Wetter- & Gewerkeprüfung:**\n\n" .
                           "• **Baustelle:** " . ($project ? $project->name : $projectName) . "\n" .
                           "• **Gewerk:** {$args['work_type']}\n" .
                           "• **Wetter:** {$currentWeather} ({$currentTemp})\n\n";

                if ($isBitumen && (str_contains($currentWeather, 'Regen') || str_contains($currentWeather, 'Schnee'))) {
                    $warning .= "⚠️ **VOB-WARNUNG (NO-GO):** Nasse Witterung/Regen erkannt! Bitumenabdichtungen dürfen gem. DIN 18533 nur auf trockenem Untergrund verlegt werden.";
                } elseif ($isBeton && str_contains($currentWeather, 'Frost')) {
                    $warning .= "⚠️ **VOB-WARNUNG (NO-GO):** Frostgefahr! Betonierarbeiten erfordern Frostschutzmittel & Thermodecken gem. DIN 1045.";
                } else {
                    $warning .= "✅ **FREIGABE:** Witterungsverhältnisse sind für {$args['work_type']} gemäß VOB/B DIN 18299 geeignet.";
                }

                return [
                    'success' => true,
                    'summary' => $warning
                ];

            case 'create_supplement':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first() ?: Project::first();
                if (!$project) {
                    return ['success' => false, 'summary' => "Keine Baustelle für '{$projectName}' gefunden."];
                }

                $count = \App\Models\Supplement::where('project_id', $project->id)->count() + 1;
                $supplementNumber = 'NT-' . str_pad((string)$count, 2, '0', STR_PAD_LEFT);
                $net = floatval($args['amount_net'] ?? 0.0);
                $gross = round($net * 1.19, 2);

                $supplement = \App\Models\Supplement::create([
                    'project_id' => $project->id,
                    'supplement_number' => $supplementNumber,
                    'title' => $args['title'] ?? 'Nachtrag',
                    'reason' => $args['reason'] ?? 'vob_b_2_5',
                    'description' => $args['description'] ?? 'Zusätzliche Leistung gemäß VOB/B.',
                    'amount_net' => $net,
                    'vat_rate' => 19.00,
                    'amount_gross' => $gross,
                    'status' => 'draft',
                    'submission_date' => date('Y-m-d'),
                ]);

                return [
                    'success' => true,
                    'supplement_id' => $supplement->id,
                    'summary' => "📑 **VOB/B Nachtrag {$supplementNumber} angelegt:**\n" .
                                 "• **Baustelle:** {$project->name}\n" .
                                 "• **Titel:** {$supplement->title}\n" .
                                 "• **Nettobetrag:** " . number_format($net, 2, ',', '.') . " € (Brutto: " . number_format($gross, 2, ',', '.') . " €)\n" .
                                 "• **Status:** Entwurf\n\n" .
                                 "[Zum Nachtragsmanagement](/nachtraege) | [PDF herunterladen](/nachtraege/{$supplement->id}/pdf)"
                ];

            case 'get_project_supplements':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first() ?: Project::first();
                if (!$project) {
                    return ['success' => false, 'summary' => "Keine Baustelle gefunden."];
                }

                $supplements = \App\Models\Supplement::where('project_id', $project->id)->get();
                if ($supplements->isEmpty()) {
                    return [
                        'success' => true,
                        'summary' => "Für das Bauvorhaben **{$project->name}** liegen aktuell keine Nachträge vor. [Nachtrag erfassen](/nachtraege)"
                    ];
                }

                $approvedTotal = $supplements->where('status', 'approved')->sum('amount_net');
                $pendingTotal = $supplements->whereIn('status', ['draft', 'submitted'])->sum('amount_net');

                $list = [];
                foreach ($supplements as $sup) {
                    $list[] = "• **{$sup->supplement_number}**: {$sup->title} - " . number_format($sup->amount_net, 2, ',', '.') . " € (Status: {$sup->status})";
                }

                return [
                    'success' => true,
                    'summary' => "📑 **Nachtragsübersicht für {$project->name}:**\n\n" .
                                 implode("\n", $list) . "\n\n" .
                                 "-----------------------------------------\n" .
                                 "• **Genehmigtes Nachtragsvolumen:** " . number_format($approvedTotal, 2, ',', '.') . " € (Netto)\n" .
                                 "• **In Prüfung / Offen:** " . number_format($pendingTotal, 2, ',', '.') . " € (Netto)\n\n" .
                                 "[Zum Nachtragsmanagement](/nachtraege)"
                ];

            case 'record_time_clock':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first() ?: Project::first();
                $workerName = $args['worker_name'] ?? 'Mitarbeiter';
                $hours = floatval($args['hours'] ?? 8.0);
                $entryDate = !empty($args['date']) ? $args['date'] : date('Y-m-d');

                $user = \App\Models\User::where('name', 'LIKE', "%{$workerName}%")->first();
                $hourlyRate = $user && $user->hourly_rate ? $user->hourly_rate : 45.00;
                $cost = round($hours * $hourlyRate, 2);

                $entry = \App\Models\TimeEntry::create([
                    'user_id' => $user ? $user->id : (auth()->id() ?: 1),
                    'project_id' => $project ? $project->id : null,
                    'entry_date' => $entryDate,
                    'start_time' => '07:30',
                    'end_time' => date('H:i', strtotime('07:30 +' . ($hours + 0.5) . ' hours')),
                    'break_minutes' => 30,
                    'hours' => $hours,
                    'activity_type' => 'execution',
                    'description' => $args['activity'] ?? 'Arbeitsleistung Baustelle',
                    'hourly_rate' => $hourlyRate,
                    'total_cost' => $cost,
                    'status' => 'approved',
                ]);

                return [
                    'success' => true,
                    'summary' => "⏱️ **Zeiterfassung gebucht (MiLoG):**\n" .
                                 "• **Mitarbeiter:** {$workerName}\n" .
                                 "• **Baustelle:** " . ($project ? $project->name : 'Allgemein') . "\n" .
                                 "• **Datum:** " . date('d.m.Y', strtotime($entryDate)) . "\n" .
                                 "• **Arbeitszeit:** " . number_format($hours, 2, ',', '.') . " Std. (Kosten: " . number_format($cost, 2, ',', '.') . " €)\n\n" .
                                 "[Zur Zeiterfassung & Stundenzettel](/zeiterfassung)"
                ];

            case 'get_equipment_compliance':
                $filter = $args['filter'] ?? 'all';
                $equip = \App\Models\Equipment::with('currentProject')->get();

                if ($filter === 'due_only') {
                    $equip = $equip->filter(fn($e) => $e->next_uvv_inspection && $e->next_uvv_inspection->isPast());
                } elseif ($filter === 'on_site') {
                    $equip = $equip->where('status', 'on_site');
                }

                $list = [];
                foreach ($equip as $e) {
                    $loc = $e->currentProject ? $e->currentProject->name : '🏢 Zentrales Lager';
                    $uvv = $e->next_uvv_inspection ? $e->next_uvv_inspection->format('d.m.Y') : 'Keine';
                    $isPast = $e->next_uvv_inspection && $e->next_uvv_inspection->isPast();
                    $alert = $isPast ? ' ⚠️ UVV ABGELAUFEN!' : '';
                    $list[] = "• **{$e->inventory_number}** - {$e->name} (Standort: {$loc} | UVV: {$uvv}{$alert})";
                }

                return [
                    'success' => true,
                    'count' => $equip->count(),
                    'summary' => "🚜 **Gerätepark & UVV-Prüfstatus:**\n\n" .
                                 (empty($list) ? "Keine Geräte für diesen Filter gefunden." : implode("\n", $list)) . "\n\n" .
                                 "[Zum Gerätepark](/geraetepark)"
                ];

            case 'create_or_update_contact':
                $company = trim($args['company_name'] ?? '');
                if (empty($company)) {
                    return ['success' => false, 'summary' => 'Firmenname ist erforderlich.'];
                }

                $contact = Contact::where('company_name', 'LIKE', "%{$company}%")
                    ->orWhere('name', 'LIKE', "%{$company}%")
                    ->first();

                $data = [
                    'company_name' => $company,
                    'first_name' => $args['first_name'] ?? ($contact?->first_name ?? ''),
                    'last_name' => $args['last_name'] ?? ($contact?->last_name ?? ''),
                    'type' => $args['type'] ?? ($contact?->type ?? 'kunde'),
                    'email' => $args['email'] ?? $contact?->email,
                    'phone' => $args['phone'] ?? $contact?->phone,
                    'street' => $args['street'] ?? $contact?->street,
                    'zip' => $args['zip'] ?? $contact?->zip,
                    'city' => $args['city'] ?? $contact?->city,
                ];

                if (!empty($args['notes'])) {
                    $prefix = "[" . date('d.m.Y H:i') . " KI-Agent]: ";
                    $data['notes'] = ($contact && $contact->notes ? ($contact->notes . "\n\n") : '') . $prefix . $args['notes'];
                }

                if ($contact) {
                    $contact->update($data);
                    $actionText = "aktualisiert";
                } else {
                    $contact = Contact::create($data);
                    $actionText = "neu angelegt";
                }

                return [
                    'success' => true,
                    'contact_id' => $contact->id,
                    'summary' => "👤 **Kontakt '{$contact->company_name}' {$actionText} (Kategorie: {$contact->type}):**\n" .
                                 "• **Ansprechpartner:** {$contact->first_name} {$contact->last_name}\n" .
                                 "• **Telefon:** " . ($contact->phone ?: '–') . "\n" .
                                 "• **E-Mail:** " . ($contact->email ?: '–') . "\n" .
                                 "• **Ort:** " . ($contact->zip ? "{$contact->zip} " : '') . ($contact->city ?: '–') . "\n\n" .
                                 "[Zur 360° Kunden-Zentrale](/kontakte)"
                ];

            case 'generate_acceptance_protocol':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first() ?: Project::first();
                if (!$project) {
                    return ['success' => false, 'summary' => "Baustelle nicht gefunden."];
                }

                $defects = Defect::where('project_id', $project->id)->whereNotIn('status', ['behoben', 'abgenommen'])->get();
                $warrantyYears = intval($args['warranty_years'] ?? 5);
                $warrantyEnd = date('d.m.Y', strtotime("+{$warrantyYears} years"));

                $defectList = [];
                foreach ($defects as $d) {
                    $defectList[] = "• [Mangel #{$d->id}] {$d->title} (Ort: {$d->location}) - Frist: " . date('d.m.Y', strtotime($d->deadline));
                }

                return [
                    'success' => true,
                    'project_id' => $project->id,
                    'summary' => "📑 **VOB/B § 12 Bauabnahmeprotokoll vorbereitet:**\n" .
                                 "• **Bauvorhaben:** {$project->name}\n" .
                                 "• **Auftraggeber:** " . ($project->contact?->company_name ?: ($args['client_representative'] ?? 'Bauherr')) . "\n" .
                                 "• **Auftragnehmer:** BT Bautechnik UG (Vertreter: " . ($args['contractor_representative'] ?? 'Julia Haberzettel') . ")\n" .
                                 "• **Leistungsumfang:** " . ($args['work_scope'] ?? 'Schlüsselfertige Bauwerksabdichtung & Sanierungsarbeiten gem. Leistungsverzeichnis.') . "\n" .
                                 "• **Ergebnis:** " . match($args['result'] ?? 'mit_maengeln') {
                                     'ohne_vorbehalt' => '✅ Mangelfrei abgenommen (Ohne Vorbehalt)',
                                     'mit_maengeln' => '⚠️ Abgenommen mit Vorbehalt wegen bekannter Restmängel',
                                     'verweigert' => '❌ Abnahme wegen wesentlicher Mängel verweigert',
                                     default => 'Abgenommen mit Vorbehalt'
                                 } . "\n" .
                                 "• **Gewährleistungsende:** {$warrantyEnd} ({$warrantyYears} Jahre gem. § 13 VOB/B)\n" .
                                 (empty($defectList) ? "• **Restmängel:** Keine offenen Mängel verzeichnet.\n\n" : "• **Verzeichnete Mängel zur Nachbesserung:**\n" . implode("\n", $defectList) . "\n\n") .
                                 "[📄 Druckreifes Abnahmeprotokoll PDF herunterladen](/abnahme/pdf/{$project->id}) | [Zum Cockpit](/dashboard)"
                ];

            case 'save_measurement_sheet':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first() ?: Project::first();
                if (!$project) {
                    return ['success' => false, 'summary' => "Baustelle nicht gefunden."];
                }

                $parser = app(\App\Services\OpenAiParserService::class);
                $parsed = $parser->parseAufmassText($args['raw_measurements'] ?? '');

                $count = \App\Models\Measurement::where('project_id', $project->id)->count() + 1;
                $measNumber = 'AM-' . date('Y') . '-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

                $measurement = \App\Models\Measurement::create([
                    'project_id' => $project->id,
                    'measurement_number' => $measNumber,
                    'title' => $args['title'] ?? 'Aufmaßblatt',
                    'measurement_date' => date('Y-m-d'),
                    'location_area' => $args['location_area'] ?? 'Baustelle',
                    'status' => 'draft',
                    'total_amount_net' => 0,
                    'inspector_name' => 'BT Bauleiter (KI-Agent)',
                    'notes' => 'Automatisch per Sprachmemo/KI erfasst.'
                ]);

                $totalQty = 0;
                $rowsSummary = [];
                $posIndex = 1;

                foreach ($parsed['rows'] ?? [] as $row) {
                    $l = floatval($row['length'] ?? 0);
                    $w = floatval($row['width'] ?? 0);
                    $h = floatval($row['height'] ?? 1);
                    $c = floatval($row['count'] ?? 1);
                    $mode = $row['mode'] ?? 'add';

                    $rowQty = round($l * $w * ($h > 0 ? $h : 1) * ($c > 0 ? $c : 1), 3);
                    if ($mode === 'subtract') {
                        $totalQty -= $rowQty;
                    } elseif ($mode === 'add') {
                        $totalQty += $rowQty;
                    }

                    \App\Models\MeasurementItem::create([
                        'measurement_id' => $measurement->id,
                        'position_index' => $posIndex++,
                        'item_code' => 'POS-' . str_pad((string)$posIndex, 2, '0', STR_PAD_LEFT),
                        'description' => $row['label'] ?? 'Massenposition',
                        'unit' => $parsed['unit'] ?? 'm²',
                        'length' => $l,
                        'width' => $w,
                        'height' => $h,
                        'factor' => $c,
                        'deduction' => ($mode === 'subtract' ? $rowQty : 0),
                        'quantity' => ($mode === 'subtract' ? -$rowQty : $rowQty),
                        'unit_price' => 0,
                        'total_price' => 0,
                        'room_or_axis' => $args['location_area'] ?? 'Standard'
                    ]);

                    $rowsSummary[] = "• {$row['label']}: {$l}m × {$w}m " . ($mode === 'subtract' ? "(Abzug: -{$rowQty} {$parsed['unit']})" : "= {$rowQty} {$parsed['unit']}");
                }

                $measurement->update(['total_amount_net' => round(max(0, $totalQty) * 65.0, 2)]);

                return [
                    'success' => true,
                    'measurement_id' => $measurement->id,
                    'summary' => "📐 **Digitales Aufmaßblatt {$measNumber} in Datenbank gespeichert (VOB/C / DIN 18299):**\n" .
                                 "• **Projekt:** {$project->name}\n" .
                                 "• **Bereich:** " . ($args['location_area'] ?? 'Flächen & Bauteile') . "\n" .
                                 "• **Gesamtmenge Netto:** " . number_format(max(0, $totalQty), 2, ',', '.') . " " . ($parsed['unit'] ?? 'm²') . "\n\n" .
                                 "**Erfasste Messzeilen:**\n" . implode("\n", $rowsSummary) . "\n\n" .
                                 "[Zum Projekt-Cockpit](/dashboard)"
                ];

            case 'check_overdue_defects_and_escalate':
                $query = Defect::with(['project', 'assignedContact'])
                    ->whereNotIn('status', ['behoben', 'abgenommen'])
                    ->where('deadline', '<', date('Y-m-d'));

                if (!empty($args['project_name'])) {
                    $project = Project::where('name', 'LIKE', "%{$args['project_name']}%")->first();
                    if ($project) {
                        $query->where('project_id', $project->id);
                    }
                }

                $overdue = $query->get();
                if ($overdue->isEmpty()) {
                    return [
                        'success' => true,
                        'summary' => "✅ **Keine Fristüberschreitungen:** Alle Mängel liegen aktuell innerhalb der vereinbarten VOB-Fristen. [Zu den Mängeln](/maengel)"
                    ];
                }

                $escalations = [];
                foreach ($overdue as $def) {
                    $subName = $def->assignedContact?->company_name ?: ($def->assignedContact?->name ?: 'Subunternehmer');
                    $daysOver = (int)ceil((time() - strtotime($def->deadline)) / 86400);
                    $escalations[] = "⚠️ **[Mangel #{$def->id}] {$def->title}** (Baustelle: " . ($def->project?->name ?? 'Allgemein') . ")\n" .
                                     "• **Verantwortlich:** {$subName}\n" .
                                     "• **Frist abgelaufen seit:** {$daysOver} Tagen (" . date('d.m.Y', strtotime($def->deadline)) . ")\n" .
                                     "• **VOB/B § 13 Abs. 5 Satz 2 Rechtsfolge:** Nachfrist setzen (3 Werktage), danach Ersatzvornahme durch Dritte auf Kosten des Auftragnehmers.";
                }

                return [
                    'success' => true,
                    'count' => $overdue->count(),
                    'summary' => "🚨 **{$overdue->count()} überfällige Mängel nach VOB/B § 13 identifiziert:**\n\n" .
                                 implode("\n\n", $escalations) . "\n\n" .
                                 "[Zu den Mängeln & Mahnschreiben generieren](/maengel)"
                ];

            case 'fetch_site_weather_live':
                $projectName = $args['project_name'] ?? '';
                $project = Project::where('name', 'LIKE', "%{$projectName}%")->first() ?: Project::first();
                if (!$project) {
                    return ['success' => false, 'summary' => "Baustelle nicht gefunden."];
                }

                $lat = 49.105;
                $lon = 11.441;

                if (stripos($project->city ?? '', 'Nürnberg') !== false) {
                    $lat = 49.452; $lon = 11.077;
                } elseif (stripos($project->city ?? '', 'Regensburg') !== false) {
                    $lat = 49.013; $lon = 12.101;
                } elseif (stripos($project->city ?? '', 'Ingolstadt') !== false) {
                    $lat = 48.766; $lon = 11.425;
                } elseif (stripos($project->city ?? '', 'München') !== false) {
                    $lat = 48.135; $lon = 11.582;
                }

                $weatherInfo = "21°C, heiter, trocken";
                try {
                    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                    $json = @file_get_contents("https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m", false, $ctx);
                    if ($json) {
                        $data = json_decode($json, true);
                        if (isset($data['current'])) {
                            $temp = $data['current']['temperature_2m'] ?? 20;
                            $rain = $data['current']['precipitation'] ?? 0;
                            $humidity = $data['current']['relative_humidity_2m'] ?? 60;
                            $wind = $data['current']['wind_speed_10m'] ?? 10;
                            $weatherInfo = "{$temp}°C (Luftfeuchte: {$humidity}%, Regen: {$rain} mm/h, Wind: {$wind} km/h)";
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                return [
                    'success' => true,
                    'summary' => "🌤️ **Live-Baustellenwetter für '{$project->name}' ({$project->city}):**\n" .
                                 "• **Aktuelle Wetterlage:** {$weatherInfo}\n" .
                                 "• **DIN 18533 (Abdichtungsarbeiten):** " . ((isset($temp) && $temp < 5) ? "❌ Zu kalt (<5°C) für Bitumen-KMB." : "✅ Optimal für Abdichtungs- und Schweißbahnarbeiten.") . "\n" .
                                 "• **Betonierarbeiten (DIN 1045):** " . ((isset($temp) && $temp <= 0) ? "⚠️ Frostschutzmaßnahmen erforderlich." : "✅ Keine Frostgefahr.") . "\n\n" .
                                 "[Bautagebuch mit Wetterdaten anlegen](/bautagebuch)"
                ];


            default:
                return ['success' => false, 'summary' => "Werkzeug '{$name}' unbekannt."];
        }
    }

    /**
     * Safely write log entries without throwing disk permission exceptions.
     */
    protected function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::$level($message, $context);
        } catch (\Throwable $e) {
            // Silently ignore log permission issues on disk
        }
    }
}
