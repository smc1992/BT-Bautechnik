<?php

use Livewire\Volt\Component;
use App\Services\OpenAiAgentService;

new class extends Component {
    public string $userMessage = '';
    public array $chatMessages = [];
    public bool $isProcessing = false;

    public function mount()
    {
        $this->chatMessages = [
            [
                'role' => 'assistant',
                'content' => "Hallo! Ich bin Ihr autonomer **KI-Agent & Betriebs-Assistent** für die BT Bautechnik UG.\n\nIch kann für Sie direkt im System arbeiten. Sagen Sie mir einfach, was zu tun ist!",
                'tools' => []
            ]
        ];
    }

    public function sendPrompt(OpenAiAgentService $agentService)
    {
        $prompt = trim($this->userMessage);
        if (empty($prompt)) return;

        // Append user prompt to UI
        $this->chatMessages[] = [
            'role' => 'user',
            'content' => $prompt,
            'tools' => []
        ];

        $this->userMessage = '';
        $this->isProcessing = true;

        try {
            // Format history for agent
            $history = [];
            foreach ($this->chatMessages as $msg) {
                if ($msg['role'] === 'user' || $msg['role'] === 'assistant') {
                    $history[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content']
                    ];
                }
            }

            $res = $agentService->runAgent($prompt, array_slice($history, -6));

            $this->chatMessages[] = [
                'role' => 'assistant',
                'content' => $res['reply'],
                'tools' => $res['tools_executed'] ?? []
            ];

            $this->dispatch('notify', '🤖 KI-Agent hat die Aufgabe verarbeitet!');

        } catch (\Exception $e) {
            $this->chatMessages[] = [
                'role' => 'assistant',
                'content' => '⚠️ Fehler bei der Ausführung der Aufgabe: ' . $e->getMessage(),
                'tools' => []
            ];
        } finally {
            $this->isProcessing = false;
        }
    }

    public function runQuickAction(string $actionText)
    {
        $this->userMessage = $actionText;
    }
}; ?>

<div class="space-y-6 font-sans">
    <!-- Header Card -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-purple-950 text-white rounded-2xl p-6 shadow-md flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="text-2xl">🤖</span>
                <h2 class="text-xl font-black text-white tracking-tight">BT Bautechnik KI-Agent Steuerzentrale</h2>
            </div>
            <p class="text-xs text-slate-300">Autonomer Betriebs-Assistent. Erteilt Befehle in natürlicher Sprache & führt Datenbank-Aufgaben selbstständig aus.</p>
        </div>

        <span class="px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span> 🟢 Agent Online (Autonomous Tool Calling)
        </span>
    </div>

    <!-- Quick Action Chips -->
    <div class="flex flex-wrap gap-2">
        <button wire:click="runQuickAction('Trage für heute im Bautagebuch ein: 3 Mann auf Baustelle Berching, 40m² Bitumenabdichtung verlegt bei 22 Grad sonnig.')" 
                class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 shadow-2xs transition flex items-center gap-1.5">
            🎙️ Bautagebuch eintragen
        </button>

        <button wire:click="runQuickAction('Erfasse einen Mangel: Dachdurchführung undicht im Dachgeschoss Haus A, Frist 7 Tage.')" 
                class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 shadow-2xs transition flex items-center gap-1.5">
            ⚠️ Mangel erfassen
        </button>

        <button wire:click="runQuickAction('Führe eine Risiko-Analyse für die Baustelle Berching durch.')" 
                class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 shadow-2xs transition flex items-center gap-1.5">
            📊 Baustellen-Risiko prüfen
        </button>

        <button wire:click="runQuickAction('Suche in der Datenbank nach Kontakten und Baustellen mit dem Namen Müller.')" 
                class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 shadow-2xs transition flex items-center gap-1.5">
            🔍 Datenbank durchsuchen
        </button>
    </div>

    <!-- Chat Console Area -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden flex flex-col h-[520px]">
        
        <!-- Messages Display -->
        <div class="flex-1 p-6 overflow-y-auto space-y-4 bg-slate-50/50">
            @foreach ($chatMessages as $msg)
                @if ($msg['role'] === 'user')
                    <!-- User Message Bubble -->
                    <div class="flex justify-end">
                        <div class="bg-blue-600 text-white rounded-2xl rounded-tr-xs px-4 py-3 text-xs max-w-lg shadow-sm font-semibold leading-relaxed">
                            {{ $msg['content'] }}
                        </div>
                    </div>
                @else
                    <!-- AI Assistant Bubble -->
                    <div class="flex gap-3 items-start">
                        <div class="w-8 h-8 rounded-xl bg-purple-950 text-white flex items-center justify-center text-sm shadow-xs shrink-0 mt-0.5">
                            🤖
                        </div>
                        <div class="space-y-2 max-w-2xl">
                            <!-- Executed Tools Badges -->
                            @if (!empty($msg['tools']))
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($msg['tools'] as $tExecuted)
                                        <div class="px-3 py-1 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-3xs font-extrabold flex items-center gap-1.5">
                                            <span>⚙️ Ausgeführte Aktion: {{ $tExecuted['tool'] }}</span>
                                            <span class="text-emerald-600">• {{ $tExecuted['result'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs p-4 text-xs text-slate-900 leading-relaxed shadow-2xs font-sans whitespace-pre-wrap">
                                {!! nl2br(e($msg['content'])) !!}
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

            @if ($isProcessing)
                <div class="flex gap-3 items-start">
                    <div class="w-8 h-8 rounded-xl bg-purple-950 text-white flex items-center justify-center text-sm shadow-xs animate-pulse">
                        🤖
                    </div>
                    <div class="bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs p-3 text-xs text-slate-600 font-bold flex items-center gap-2 shadow-2xs">
                        <span class="border-2 border-t-transparent border-purple-600 rounded-full w-3.5 h-3.5 animate-spin"></span>
                        KI-Agent analysiert Befehl & führt System-Aktionen aus...
                    </div>
                </div>
            @endif
        </div>

        <!-- Input Form -->
        <form wire:submit="sendPrompt" class="p-4 bg-white border-t border-slate-200 flex items-center gap-3">
            <input wire:model="userMessage" type="text" 
                   class="flex-1 bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-purple-600 focus:outline-none"
                   placeholder="Erteilen Sie dem KI-Agenten eine Aufgabe (z. B. 'Erstelle heute Bautagebuch für Berching mit 3 Mann')..." required>

            <button type="submit" wire:loading.attr="disabled" 
                    class="px-5 py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-md shadow-purple-500/20 transition flex items-center gap-1.5 shrink-0">
                <span wire:loading.remove wire:target="sendPrompt">🚀 Befehl senden</span>
                <span wire:loading wire:target="sendPrompt">⌛ Führe aus...</span>
            </button>
        </form>
    </div>
</div>
