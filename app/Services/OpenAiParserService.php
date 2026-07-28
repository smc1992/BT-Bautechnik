<?php

namespace App\Services;

use OpenAI;
use Exception;
use Illuminate\Support\Facades\Log;

class OpenAiParserService
{
    protected ?OpenAI\Client $client = null;
    protected string $model;

    public function __construct()
    {
        $apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        
        if ($apiKey) {
            $this->client = OpenAI::client($apiKey);
        }
        
        // Default to gpt-4o for maximum reasoning accuracy on German construction LVs & offers
        $this->model = config('services.openai.model') ?: env('OPENAI_MODEL', 'gpt-4o');
    }

    /**
     * Parse unstructured offer text (e.g., from PDF OCR or email copy-paste)
     * into structured sections and items matching our database schema.
     *
     * @param string $textContents
     * @return array
     * @throws Exception
     */
    public function parseOfferDocument(string $textContents): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key is not configured. Please set OPENAI_API_KEY in your .env file.");
        }

        try {
            $response = $this->client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Du bist ein präziser KI-Assistent für ein deutsches Bauunternehmen (BT Bautechnik UG). Deine Aufgabe ist es, unstrukturierte Leistungsbeschreibungen, E-Mails oder Angebote von Subunternehmern einzulesen und strukturiert auszugeben. Achte genau auf Positionen (LV-Pos-Nr), Kurzbeschreibung, Menge, Einheit (z.B. Stk, lfm, m², Std, pauschal) und Einzelpreise."
                    ],
                    [
                        'role' => 'user',
                        'content' => "Bitte extrahiere und strukturiere das folgende Dokument:\n\n" . $textContents
                    ]
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'offer_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => [
                                    'type' => 'string',
                                    'description' => 'Der übergeordnete Titel des Angebots/Nachtrags.'
                                ],
                                'sections' => [
                                    'type' => 'array',
                                    'description' => 'Die einzelnen Gliederungspunkte (Titel) im Angebot.',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => [
                                                'type' => 'string',
                                                'description' => 'Titel des Gliederungspunkts, z.B. "Kellertüre erneuern"'
                                            ],
                                            'items' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'pos_number' => [
                                                            'type' => 'string',
                                                            'description' => 'Die Positionsnummer (z.B. "24" oder "01.01.0020").'
                                                        ],
                                                        'description' => [
                                                            'type' => 'string',
                                                            'description' => 'Die vollständige Bezeichnung und Detailbeschreibung der Leistung.'
                                                        ],
                                                        'quantity' => [
                                                            'type' => 'number',
                                                            'description' => 'Menge'
                                                        ],
                                                        'unit' => [
                                                            'type' => 'string',
                                                            'description' => 'Einheit (z.B. Stk, lfm, m², Std, Pau)'
                                                        ],
                                                        'unit_price' => [
                                                            'type' => 'number',
                                                            'description' => 'Einzelpreis in EUR (Netto)'
                                                        ]
                                                    ],
                                                    'required' => ['pos_number', 'description', 'quantity', 'unit', 'unit_price'],
                                                    'additionalProperties' => false
                                                ]
                                            ]
                                        ],
                                        'required' => ['title', 'items'],
                                        'additionalProperties' => false
                                    ]
                                ]
                            ],
                            'required' => ['title', 'sections'],
                            'additionalProperties' => false
                        ],
                        'strict' => true
                    ]
                ]
            ]);

            $jsonString = $response->choices[0]->message->content;
            $data = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Failed to decode JSON response from OpenAI: " . json_last_error_msg());
            }

            return $data;

        } catch (Exception $e) {
            Log::error("OpenAiParserService Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 1. Transform raw Stichpunkte into structured Bautagebuch entry
     */
    public function generateDailyLogFromDraft(string $draftText): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Du bist ein erfahrener Bauleiter der BT Bautechnik UG. Erstelle aus unstrukturierten Stichpunkten des Handwerkers einen DIN-konformen Bautagebuch-Eintrag."
                ],
                [
                    'role' => 'user',
                    'content' => "Analysiere diese Baustellen-Stichpunkte und erzeuge das strukturierte Bautagebuch:\n\n" . $draftText
                ]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'daily_log_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'weather' => ['type' => 'string', 'description' => 'Sonnig, Bewölkt, Regen, Frost oder Schnee'],
                            'temperature' => ['type' => 'string', 'description' => 'Geschätzte Temperatur z.B. 22°C'],
                            'workers_count' => ['type' => 'integer', 'description' => 'Anzahl eingesetzter Handwerker'],
                            'work_performed' => ['type' => 'string', 'description' => 'Fachlich sauber ausformulierte geleistete Arbeiten'],
                            'special_occurrences' => ['type' => 'string', 'description' => 'Störungen, Verzögerungen, Abnahmen oder Vorkommnisse (oder Keines)']
                        ],
                        'required' => ['weather', 'temperature', 'workers_count', 'work_performed', 'special_occurrences'],
                        'additionalProperties' => false
                    ],
                    'strict' => true
                ]
            ]
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    /**
     * 2. Generate a VOB/B §13 Compliant Defect Notice Letter
     */
    public function generateDefectNoticeLetter(array $defectData): string
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $prompt = "Erstelle ein rechtssicheres Mängelrüge-Schreiben nach VOB/B § 13 für das Bauunternehmen BT Bautechnik UG (Sollngriesbacher Str. 4, 92334 Berching).\n" .
            "Baustelle/Projekt: " . ($defectData['project'] ?? 'Baustelle') . "\n" .
            "Empfänger (Subunternehmer): " . ($defectData['contact'] ?? 'Subunternehmer') . "\n" .
            "Mangel: " . ($defectData['title'] ?? '') . "\n" .
            "Ort: " . ($defectData['location'] ?? 'Baustelle') . "\n" .
            "Beschreibung: " . ($defectData['description'] ?? '') . "\n" .
            "Beseitigungsfrist: " . ($defectData['deadline'] ?? '7 Tage') . "\n\n" .
            "Verfasse ein förmliches Schreiben mit Betreff, VOB/B Bezug, ausdrücklicher Fristsetzung und Hinweis auf Ersatzvornahme bei Verzug.";

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Du bist ein erfahrener Fachanwalt für Bau- und Architektenrecht sowie Bauleiter der BT Bautechnik UG.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        return $response->choices[0]->message->content;
    }

    /**
     * 3. Generate Cover Letter / Email for Invoices & Offers
     */
    public function generateCoverLetter(string $type, array $docMeta): string
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $prompt = "Erstelle ein hochprofessionelles Begleitschreiben / E-Mail Anschreiben für eine " . ($type === 'offer' ? 'Angebotserstellung' : 'Rechnungsstellung') . " der BT Bautechnik UG.\n" .
            "Kunde/Empfänger: " . ($docMeta['client_name'] ?? 'Sehr geehrte Damen und Herren') . "\n" .
            "Dokumentennummer: " . ($docMeta['number'] ?? '') . "\n" .
            "Projekt/Objekt: " . ($docMeta['project'] ?? '') . "\n" .
            "Gesamtsumme (Brutto): " . ($docMeta['total'] ?? '') . " EUR\n\n" .
            "Das Schreiben soll freundlich, verbindlich und betriebswirtschaftlich tadellos sein. Berücksichtige bei Hausverwaltungen die professionelle Anrede.";

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Du bist Assistent der Geschäftsführung bei der BT Bautechnik UG.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        return $response->choices[0]->message->content;
    }

    /**
     * 4. Audit Subcontractor Invoice (§13b UStG & §14 UStG)
     */
    public function auditSubcontractorInvoiceText(string $rawInvoiceText): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Du bist ein deutscher Steuerprüfer & Bauleiter für die BT Bautechnik UG. Prüfe die Eingangsrechnung auf Compliance mit § 13b UStG (Steuerschuldnerschaft bei Bauleistungen) und Pflichtangaben nach § 14 UStG.'],
                ['role' => 'user', 'content' => "Prüfe folgenden Rechnungstext:\n\n" . $rawInvoiceText]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'invoice_audit_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'is_13b_mentioned' => ['type' => 'boolean', 'description' => 'Ob §13b UStG / Steuerschuld des Leistungsempfängers explizit genannt ist.'],
                            'missing_elements' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Liste fehlender Pflichtangaben (z.B. USt-ID, Rechnungsdatum, fortlaufende Nummer)'
                            ],
                            'risk_level' => ['type' => 'string', 'description' => 'niedrig, mittel oder hoch'],
                            'advice' => ['type' => 'string', 'description' => 'Zusammenfassende Handlungsempfehlung für den Bauleiter']
                        ],
                        'required' => ['is_13b_mentioned', 'missing_elements', 'risk_level', 'advice'],
                        'additionalProperties' => false
                    ],
                    'strict' => true
                ]
            ]
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    /**
     * 5. Audit Offer Positions & Check Risk / Completeness
     */
    public function auditOfferItems(array $items, string $title = ''): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Du bist Chef-Kalkulator bei der BT Bautechnik UG. Prüfe dieses Bau-Angebot auf Vollständigkeit (z.B. fehlt Baustelleneinrichtung, Entsorgung, Gerüst, Sicherheitsabsperrung?) sowie auf Preis-Auffälligkeiten.'],
                ['role' => 'user', 'content' => "Angebotstitel: " . $title . "\nPositionen:\n" . $itemsJson]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'offer_audit_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'score' => ['type' => 'integer', 'description' => 'Vollständigkeits-Score von 1 bis 100'],
                            'missing_positions' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Typische Baupositionen, die im Angebot möglicherweise vergessen wurden'
                            ],
                            'pricing_warnings' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Warnungen zu auffällig niedrigen oder ungewöhnlichen Einheitspreisen'
                            ],
                            'summary' => ['type' => 'string', 'description' => 'Gesamteinschätzung für die Geschäftsführung']
                        ],
                        'required' => ['score', 'missing_positions', 'pricing_warnings', 'summary'],
                        'additionalProperties' => false
                    ],
                    'strict' => true
                ]
            ]
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    /**
     * 6. Generate Weekly Executive Report for Property Managers
     */
    public function generateWeeklyReportFromLogs(array $logs): string
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $logsJson = json_encode($logs, JSON_UNESCAPED_UNICODE);

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Du bist Bauleiter der BT Bautechnik UG. Erstelle aus den Bautagebuch-Einträgen einen hochprofessionellen Wochenbericht für Hausverwaltungen und Eigentümer. WICHTIGE FORMATIERUNGS-REGEL: Verwende absolut KEINE Sternchen (** oder *), KEINE Markdown-Syntax und KEINE Rauten (#). Formatiere Abschnittsüberschriften in sauberen Großbuchstaben mit Doppelpunkt (z. B. BAUSTELLE:, BERICHTSZEITRAUM:, WETTERBEDINGUNGEN:, AUSGEFÜHRTE ARBEITEN:, BESONDERE VORKOMMNISSE:, ZUSAMMENFASSUNG:) und Aufzählungen mit einfachen Bindestrichen (-).'],
                ['role' => 'user', 'content' => "Bautagebuch-Einträge der Woche:\n" . $logsJson]
            ]
        ]);

        $content = $response->choices[0]->message->content ?? '';
        // Strip any lingering asterisks or markdown hashes
        $content = preg_replace('/\*\*|\*/', '', $content);
        $content = preg_replace('/^#+\s*/m', '', $content);

        return trim($content);
    }

    /**
     * 7. Adjust Material Catalog Prices based on Natural Language Prompt
     */
    public function adjustMaterialPricesWithAi(string $userPrompt, array $materialsList): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        $materialsJson = json_encode($materialsList, JSON_UNESCAPED_UNICODE);

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Du bist ein KI-Preismanager für Baustoffe und Baumaterialien eines deutschen Bauunternehmens (BT Bautechnik UG). Der Benutzer gibt dir Anweisungen zur Preisanpassung (z.B. 'Erhöhe alle Zementpreise um 8%', 'Setze Säcke Zement 25kg auf 5,90 €', 'Passe Baustoffe von Lieferant Baustoff-Union an'). Berechne für alle betroffenen Materialien den exakten neuen Preis ('new_price'). Behalte unbetroffene Materialien unverändert oder schließe sie aus. Gib ein strukturierte JSON mit einer kurzen Zusammenfassung 'summary' und einer Liste 'updates' zurück."
                ],
                [
                    'role' => 'user',
                    'content' => "Preisanpassungs-Anweisung: {$userPrompt}\n\nAktueller Materialkatalog:\n{$materialsJson}"
                ]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'material_price_update_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => [
                                'type' => 'string',
                                'description' => 'Eine kurze, prägnante Zusammenfassung der vorgenommenen Preisänderungen (z. B. "3 Zement-Produkte um +8% erhöht").'
                            ],
                            'updates' => [
                                'type' => 'array',
                                'description' => 'Liste aller aktualisierten Materialien',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'string', 'description' => 'Die ID des Materials'],
                                        'name' => ['type' => 'string', 'description' => 'Name des Materials'],
                                        'old_price' => ['type' => 'number', 'description' => 'Bisheriger Einzelpreis'],
                                        'new_price' => ['type' => 'number', 'description' => 'Neuer berechneter Einzelpreis in EUR'],
                                        'reason' => ['type' => 'string', 'description' => 'Kurzer Grund/Erklärung der Änderung']
                                    ],
                                    'required' => ['id', 'name', 'old_price', 'new_price', 'reason'],
                                    'additionalProperties' => false
                                ]
                            ]
                        ],
                        'required' => ['summary', 'updates'],
                        'additionalProperties' => false
                    ],
                    'strict' => true
                ]
            ]
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    /**
     * Parse unstructured natural text / speech dictation into structured VOB-compliant Aufmaß rows.
     *
     * @param string $rawText
     * @return array
     * @throws Exception
     */
    public function parseAufmassText(string $rawText): array
    {
        if (!$this->client) {
            throw new Exception("OpenAI API Key ist nicht konfiguriert.");
        }

        try {
            $response = $this->client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Du bist ein erfahrener Bauingenieur für Bauabrechnung und Aufmaße nach VOB/B (DIN 18299). Deine Aufgabe ist es, Diktate, Freitexte oder Notizen von Baustellen in ein strukturiertes Aufmaßblatt zu konvertieren.\n\n" .
                                     "VOB-REGELN:\n" .
                                     "1. Hinzurechnende Bauteile -> mode: 'add'\n" .
                                     "2. Abzüge (Türen, Fenster, Aussparungen > 0,1 m² bei Flächen bzw. > 0,5 m³ bei Volumen) -> mode: 'subtract'\n" .
                                     "3. Übermessungen (Aussparungen <= 0,1 m² bei Flächen bzw. <= 0,5 m³ bei Volumen) -> mode: 'overmeasure' (nicht abziehen)\n" .
                                     "4. Einheiten: m², m³, m oder Stk"
                    ],
                    [
                        'role' => 'user',
                        'content' => "Bitte erstelle ein strukturiertes Aufmaß aus folgendem Notiztext:\n\n" . $rawText
                    ]
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'aufmass_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'unit' => [
                                    'type' => 'string',
                                    'description' => 'Haupt-Einheit des Aufmaßes: m², m³, m oder Stk'
                                ],
                                'rows' => [
                                    'type' => 'array',
                                    'description' => 'Liste aller erfassten Teilaufmaße und Abzüge',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'label' => [
                                                'type' => 'string',
                                                'description' => 'Bauteil-, Wand- oder Raumbezeichnung'
                                            ],
                                            'count' => [
                                                'type' => 'number',
                                                'description' => 'Anzahl / Faktor (Standard 1)'
                                            ],
                                            'length' => [
                                                'type' => 'number',
                                                'description' => 'Länge in Metern'
                                            ],
                                            'width' => [
                                                'type' => 'number',
                                                'description' => 'Breite oder Höhe in Metern'
                                            ],
                                            'height' => [
                                                'type' => 'number',
                                                'description' => 'Dicke oder Tiefe in Metern (nur bei m³, sonst 1.0)'
                                            ],
                                            'mode' => [
                                                'type' => 'string',
                                                'description' => 'Berechnungsmodus: "add", "subtract", oder "overmeasure"'
                                            ],
                                            'note' => [
                                                'type' => 'string',
                                                'description' => 'Optionaler VOB-Hinweis'
                                            ]
                                        ],
                                        'required' => ['label', 'count', 'length', 'width', 'height', 'mode'],
                                        'additionalProperties' => false
                                    ]
                                ]
                            ],
                            'required' => ['unit', 'rows'],
                            'additionalProperties' => false
                        ],
                        'strict' => true
                    ]
                ]
            ]);

            $jsonString = $response->choices[0]->message->content ?? '{}';
            return json_decode($jsonString, true) ?: ['unit' => 'm²', 'rows' => []];
        } catch (Exception $e) {
            Log::error("Error parsing Aufmaß with OpenAI: " . $e->getMessage());
            throw new Exception("Fehler bei der KI-Aufmaß-Analyse: " . $e->getMessage());
        }
    }
}

