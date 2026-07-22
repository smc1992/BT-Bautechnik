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
                'content' => "Du bist der autonome KI-Betriebsassistent (Copilot) der BT Bautechnik UG. Deine Aufgabe ist es, Aufgaben für das Bauunternehmen selbstständig auszuführen. " .
                    "Du kannst Bautagebuch-Einträge anlegen, Mängel erzeugen, Baustellen-Risiken analysieren, Kontakte suchen und Rechnungen/Angebote vorbereiten. " .
                    "Verwende deine Werkzeuge (Tools) wann immer eine Aktion ausgeführt werden soll, und bestätige die Ausführung anschließend höflich, präzise und übersichtlich."
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
                    'summary' => "Bautagebuch-Eintrag für Baustelle '{$project->name}' am " . date('d.m.Y') . " erfolgreich angelegt.",
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
                    'summary' => "Mangel '{$defect->title}' für Baustelle '{$project->name}' erfasst. Frist: " . date('d.m.Y', strtotime($defect->deadline)),
                    'defect_id' => $defect->id
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
                    'summary' => "Baustelle '{$project->name}': Ist-Kosten {$actualCosts} € von Soll-Budget {$totalBudget} €. Offene Mängel: {$openDefects}. Risiko: {$risk}."
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

            default:
                return ['success' => false, 'summary' => "Werkzeug '{$name}' unbekannt."];
        }
    }
}
