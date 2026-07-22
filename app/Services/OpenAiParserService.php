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
        
        // Default to GPT-5.6 Terra as of 2026 for balanced intelligence/cost
        $this->model = config('services.openai.model') ?: env('OPENAI_MODEL', 'gpt-5.6-terra');
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
}
