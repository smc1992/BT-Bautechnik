<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Services\OpenAiAgentService;
use App\Models\AgentChat;
use App\Models\AgentChatMessage;

new class extends Component {
    use WithFileUploads;

    public ?string $activeChatId = null;
    public string $userMessage = '';
    public bool $isProcessing = false;
    public $audioFile;
    public $photoFile;

    public function mount()
    {
        $lastChat = AgentChat::latest('updated_at')->first();
        if ($lastChat) {
            $this->activeChatId = $lastChat->id;
        } else {
            $this->createNewChat();
        }
    }

    public function createNewChat()
    {
        $chat = AgentChat::create([
            'title' => 'Neue Unterhaltung',
        ]);

        AgentChatMessage::create([
            'agent_chat_id' => $chat->id,
            'role' => 'assistant',
            'content' => "Hallo! Ich bin Ihr autonomer **KI-Agent & Betriebs-Assistent** für die BT Bautechnik UG.\n\nIch kann für Sie direkt im System arbeiten (z.B. **Bautagebuch führen**, **Mängel verwalten**, **Rechnungen erstellen**, **Baukosten verbuchen** und **Risiken analysieren**).\n\nSagen Sie mir einfach per Freitext, Foto oder Sprachbefehl, was zu tun ist!",
            'tools' => []
        ]);

        $this->activeChatId = $chat->id;
        $this->userMessage = '';
    }

    public function loadChat(string $chatId)
    {
        $chat = AgentChat::find($chatId);
        if ($chat) {
            $this->activeChatId = $chat->id;
            $this->userMessage = '';
        }
    }

    public function deleteChat(string $chatId)
    {
        AgentChat::destroy($chatId);
        if ($this->activeChatId === $chatId) {
            $last = AgentChat::latest('updated_at')->first();
            if ($last) {
                $this->activeChatId = $last->id;
            } else {
                $this->createNewChat();
            }
        }
        $this->dispatch('notify', '🗑️ Unterhaltung gelöscht.');
    }

    public function getChatsProperty()
    {
        return AgentChat::withCount('messages')->orderBy('updated_at', 'desc')->get();
    }

    public function getActiveChatProperty()
    {
        if (!$this->activeChatId) return null;
        return AgentChat::with('messages')->find($this->activeChatId);
    }

    public function updatedPhotoFile(OpenAiAgentService $agentService)
    {
        if (!$this->photoFile) return;

        $this->isProcessing = true;

        try {
            $path = $this->photoFile->getRealPath();
            $analysis = $agentService->analyzePhoto($path);

            if (!empty($analysis)) {
                $this->userMessage = "📷 Baustellen-Foto-Analyse:\n" . $analysis . "\n\nBitte erstelle daraus bei Bedarf eine neue Baustelle oder einen Bautagebuch-Eintrag.";
                $this->sendPrompt($agentService);
            } else {
                $this->dispatch('notify', '⚠️ Foto konnte nicht analysiert werden.');
            }
        } catch (\Exception $e) {
            if ($this->activeChatId) {
                AgentChatMessage::create([
                    'agent_chat_id' => $this->activeChatId,
                    'role' => 'assistant',
                    'content' => '⚠️ Fehler bei der GPT-4o Vision Bildanalyse: ' . $e->getMessage(),
                    'tools' => []
                ]);
            }
        } finally {
            $this->isProcessing = false;
            $this->photoFile = null;
        }
    }

    public function processAudioUpload(OpenAiAgentService $agentService)
    {
        if (!$this->audioFile) return;

        $this->isProcessing = true;

        try {
            $path = $this->audioFile->getRealPath();
            $transcription = $agentService->transcribeAudio($path);

            if (!empty($transcription)) {
                $this->userMessage = $transcription;
                $this->sendPrompt($agentService);
            } else {
                $this->dispatch('notify', '⚠️ Keine Sprache im Audio erkannt.');
            }
        } catch (\Exception $e) {
            if ($this->activeChatId) {
                AgentChatMessage::create([
                    'agent_chat_id' => $this->activeChatId,
                    'role' => 'assistant',
                    'content' => '⚠️ Fehler bei der OpenAI Whisper Transkription: ' . $e->getMessage(),
                    'tools' => []
                ]);
            }
        } finally {
            $this->isProcessing = false;
            $this->audioFile = null;
        }
    }

    public function sendPrompt(OpenAiAgentService $agentService)
    {
        $prompt = trim($this->userMessage);
        if (empty($prompt)) return;

        if (!$this->activeChatId) {
            $this->createNewChat();
        }

        $chat = AgentChat::find($this->activeChatId);
        if (!$chat) return;

        // Auto-generate title from first prompt if title is "Neue Unterhaltung"
        if ($chat->title === 'Neue Unterhaltung') {
            $title = \Illuminate\Support\Str::limit($prompt, 38, '...');
            $chat->update(['title' => $title]);
        } else {
            $chat->touch(); // update updated_at timestamp
        }

        // Save user message to database
        AgentChatMessage::create([
            'agent_chat_id' => $chat->id,
            'role' => 'user',
            'content' => $prompt,
            'tools' => []
        ]);

        $this->userMessage = '';
        $this->isProcessing = true;

        try {
            // Load messages for history context
            $dbMessages = $chat->messages()->get();
            $history = [];
            foreach ($dbMessages as $msg) {
                if ($msg->role === 'user' || $msg->role === 'assistant') {
                    $history[] = [
                        'role' => $msg->role,
                        'content' => $msg->content
                    ];
                }
            }

            $res = $agentService->runAgent($prompt, array_slice($history, -8));

            // Save assistant message to database
            AgentChatMessage::create([
                'agent_chat_id' => $chat->id,
                'role' => 'assistant',
                'content' => $res['reply'],
                'tools' => $res['tools_executed'] ?? []
            ]);

            $this->dispatch('notify', '🤖 KI-Agent hat die Aufgabe verarbeitet!');

        } catch (\Exception $e) {
            AgentChatMessage::create([
                'agent_chat_id' => $chat->id,
                'role' => 'assistant',
                'content' => '⚠️ Fehler bei der Ausführung der Aufgabe: ' . $e->getMessage(),
                'tools' => []
            ]);
        } finally {
            $this->isProcessing = false;
        }
    }

    public function runQuickAction(string $actionText)
    {
        $this->userMessage = $actionText;
    }

    public function exportChatMarkdown()
    {
        $chat = AgentChat::with('messages')->find($this->activeChatId);
        if (!$chat) {
            $this->dispatch('notify', '⚠️ Keine Unterhaltung zum Exportieren gefunden.');
            return;
        }

        $md = "# 🏗️ BT Bautechnik UG — KI-Agent Protokoll\n";
        $md .= "**Unterhaltung:** " . $chat->title . "\n";
        $md .= "**Erstellt am:** " . $chat->created_at->format('d.m.Y H:i') . " Uhr\n";
        $md .= "**Export-Datum:** " . date('d.m.Y H:i') . " Uhr\n\n";
        $md .= "---\n\n";

        foreach ($chat->messages as $msg) {
            $sender = $msg->role === 'user' ? '👤 Bauleiter / Anwender' : '🤖 BT KI-Agent PRO';
            $md .= "### " . $sender . " (" . $msg->created_at->format('H:i') . " Uhr)\n\n";
            $md .= $msg->content . "\n\n";

            if (!empty($msg->tools)) {
                $md .= "**Ausgeführte Werkzeuge:**\n";
                foreach ($msg->tools as $tExecuted) {
                    $md .= "- `" . ($tExecuted['tool'] ?? 'Werkzeug') . "`: " . strip_tags($tExecuted['result'] ?? '') . "\n";
                }
                $md .= "\n";
            }
            $md .= "---\n\n";
        }

        $filename = 'KI-Protokoll-' . \Illuminate\Support\Str::slug($chat->title) . '-' . date('Y-m-d') . '.md';

        return response()->streamDownload(function () use ($md) {
            echo $md;
        }, $filename);
    }
}; ?>

<div x-data="{ showHistoryMobile: false }" class="space-y-6 font-sans max-w-full overflow-x-hidden">
    <!-- Header Command Center Banner (BT Bautechnik CI Colors: Deep Slate & Blue) -->
    <div class="bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 text-white rounded-2xl p-4 sm:p-6 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
        <!-- Background Ambient Glow -->
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex items-center gap-3.5 relative z-10">
            <div class="relative flex items-center justify-center w-11 h-11 sm:w-13 sm:h-13 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 shadow-lg shadow-blue-500/30 text-2xl shrink-0 border border-blue-400/30">
                🤖
                <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-slate-900"></span>
                </span>
            </div>
            <div class="space-y-0.5">
                <h2 class="text-lg sm:text-xl font-black text-white tracking-tight flex items-center gap-2">
                    BT Bautechnik KI-Agent Steuerzentrale
                </h2>
                <p class="text-xs text-slate-300 font-medium leading-relaxed">Autonomer Betriebs-Assistent • Baustellen, Bautagebücher, Angebote & Belege steuern</p>
            </div>
        </div>

        <div class="flex items-center justify-between sm:justify-end gap-2 w-full md:w-auto relative z-10 shrink-0">
            <!-- Mobile Toggle Button for Chat History -->
            <button @click="showHistoryMobile = !showHistoryMobile" 
                    class="lg:hidden px-3 py-2 bg-slate-800 hover:bg-slate-700 text-blue-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                <span>💬</span>
                <span>Verlauf ({{ count($this->chats) }})</span>
            </button>

            <span class="hidden sm:flex px-3 py-1.5 rounded-full text-[11px] sm:text-xs font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 items-center gap-2 shadow-xs backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>System Aktiv</span>
            </span>

            <button wire:click="createNewChat" @click="showHistoryMobile = false"
                    title="Neue Unterhaltung starten"
                    class="px-3.5 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-blue-500/20 cursor-pointer">
                <span>➕</span>
                <span class="hidden sm:inline">Neuer Chat</span>
                <span class="sm:hidden">Neu</span>
            </button>
        </div>
    </div>

    <!-- Main Grid: Sidebar History & Active Chat -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        <!-- Left Sidebar: Past Conversations (BT Bautechnik CI Colors) -->
        <div :class="showHistoryMobile ? 'block' : 'hidden lg:block'"
             class="lg:col-span-1 bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden flex flex-col h-[380px] sm:h-[520px] lg:h-[680px] transition-all">
            <div class="p-3.5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800 shrink-0">
                <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                    <span>💬</span>
                    <span>Vergangene Chats</span>
                </h3>
                <div class="flex items-center gap-2">
                    <button wire:click="createNewChat" @click="showHistoryMobile = false" class="text-xs font-bold text-blue-400 hover:text-white transition cursor-pointer">
                        + Neu
                    </button>
                    <button @click="showHistoryMobile = false" class="lg:hidden text-xs text-slate-400 hover:text-white cursor-pointer px-1">
                        ✕
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-2 space-y-1.5 bg-slate-50/50">
                @forelse ($this->chats as $c)
                    <div wire:click="loadChat('{{ $c->id }}'); showHistoryMobile = false;"
                         class="group p-3 rounded-xl border transition-all cursor-pointer relative flex flex-col gap-1 {{ $activeChatId === $c->id ? 'bg-slate-900 text-white border-blue-500 shadow-md' : 'bg-white text-slate-800 border-slate-200/80 hover:border-blue-300 hover:bg-blue-50/40' }}">
                        
                        <div class="flex items-center justify-between gap-1">
                            <p class="font-bold text-xs truncate leading-snug flex-1 pr-6 {{ $activeChatId === $c->id ? 'text-white' : 'text-slate-900' }}">
                                {{ $c->title }}
                            </p>

                            <!-- Delete Chat Button -->
                            <button wire:click.stop="deleteChat('{{ $c->id }}')" 
                                    title="Unterhaltung löschen"
                                    class="opacity-80 lg:opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-rose-500 rounded transition cursor-pointer absolute right-2 top-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>

                        <div class="flex items-center justify-between text-[10px] {{ $activeChatId === $c->id ? 'text-blue-200' : 'text-slate-400' }}">
                            <span>{{ $c->updated_at->format('d.m.Y H:i') }}</span>
                            <span class="font-semibold px-1.5 py-0.5 rounded-md {{ $activeChatId === $c->id ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                                {{ $c->messages_count }} Msg
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-xs text-slate-400 italic">
                        Keine vergangenen Chats.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Right Main Column: Chat Console & Quick Actions -->
        <div class="lg:col-span-3 space-y-6">
            
            <!-- Quick Action Chips (8-Card Grid) -->
            <div class="space-y-2">
                <div class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                    <span>⚡ Schnellauswahl für typische Aufgaben:</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                    <button wire:click="runQuickAction('Trage für heute im Bautagebuch ein: 3 Mann auf Baustelle Berching, 40m² Bitumenabdichtung verlegt bei 22 Grad sonnig.')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-blue-50/50 text-slate-700 hover:text-blue-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-blue-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-blue-100 group-hover:bg-blue-600 text-blue-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">🎙️</span>
                        <span class="truncate">Bautagebuch</span>
                    </button>

                    <button wire:click="runQuickAction('Erfasse einen Mangel: Dachdurchführung undicht im Dachgeschoss Haus A, Frist 7 Tage.')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-amber-50/50 text-slate-700 hover:text-amber-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-amber-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-amber-100 group-hover:bg-amber-600 text-amber-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">⚠️</span>
                        <span class="truncate">Mangel erfassen</span>
                    </button>

                    <button wire:click="runQuickAction('Erstelle einen Rechnungs-Entwurf über 4500 Euro für die Baustelle Berching für Flachdachabdichtung.')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-blue-50/50 text-slate-700 hover:text-blue-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-blue-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-slate-100 group-hover:bg-blue-600 text-slate-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">📄</span>
                        <span class="truncate">Rechnung</span>
                    </button>

                    <button wire:click="runQuickAction('Verbuchen wir Baukosten von 2800 Euro von Nachunternehmer Spenglerei Meier für Baustelle Berching.')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-emerald-50/50 text-slate-700 hover:text-emerald-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-emerald-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-emerald-100 group-hover:bg-emerald-600 text-emerald-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">🏗️</span>
                        <span class="truncate">Baukosten</span>
                    </button>

                    <button wire:click="runQuickAction('Teile Subunternehmer Meier Bausanierung für morgen ganztags auf Baustelle Berching ein.')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-indigo-50/50 text-slate-700 hover:text-indigo-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-indigo-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-indigo-100 group-hover:bg-indigo-600 text-indigo-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">👷</span>
                        <span class="truncate">Einsatzplaner</span>
                    </button>

                    <button wire:click="runQuickAction('Wie steht Baustelle Berching finanziell da? Berechne Rohgewinn und Marge.')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-emerald-50/50 text-slate-700 hover:text-emerald-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-emerald-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-emerald-100 group-hover:bg-emerald-600 text-emerald-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">📊</span>
                        <span class="truncate">Gewinn-Check</span>
                    </button>

                    <button wire:click="runQuickAction('Gib mir die Kontaktdaten und Telefonnummer von Immo Köhler.')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-blue-50/50 text-slate-700 hover:text-blue-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-blue-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-blue-100 group-hover:bg-blue-600 text-blue-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">📇</span>
                        <span class="truncate">Kontakte</span>
                    </button>

                    <button wire:click="runQuickAction('Können wir auf Baustelle Berching Bitumenabdichtungen verlegen oder gibt es Wetter-Warnungen?')" 
                            class="group p-2.5 sm:p-3 bg-white hover:bg-amber-50/50 text-slate-700 hover:text-amber-900 font-semibold text-xs rounded-xl border border-slate-200/90 hover:border-amber-300 shadow-2xs hover:shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer text-left">
                        <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-amber-100 group-hover:bg-amber-600 text-amber-700 group-hover:text-white flex items-center justify-center text-xs transition-colors shrink-0 font-bold">☀️</span>
                        <span class="truncate">Wetter-Check</span>
                    </button>
                </div>
            </div>

            <!-- Chat Console Area -->
            <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden flex flex-col h-[520px] sm:h-[600px]">
                
                <!-- Chat Window Header -->
                @if ($this->activeChat)
                    <div class="px-4 py-3 bg-slate-900 text-white border-b border-slate-800 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <h3 class="font-bold text-xs text-white truncate max-w-xs sm:max-w-md">
                                {{ $this->activeChat->title }}
                            </h3>
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="exportChatMarkdown" 
                                    title="Chat-Protokoll als Markdown herunterladen"
                                    class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-blue-300 hover:text-white border border-slate-700 rounded-lg text-[10px] font-bold transition flex items-center gap-1 cursor-pointer">
                                <span>📥</span> <span>Exportieren</span>
                            </button>
                            <span class="text-[10px] text-slate-400 font-mono hidden sm:inline">
                                {{ $this->activeChat->created_at->format('d.m.Y H:i') }}
                            </span>
                        </div>
                    </div>
                @endif

                <!-- Messages Display with Instant Auto-Scroll to Newest Answer -->
                <div x-data="{
                         scrollToBottom() {
                             this.$nextTick(() => {
                                 $el.scrollTo({ top: $el.scrollHeight, behavior: 'smooth' });
                             });
                         }
                     }"
                     x-init="
                         scrollToBottom();
                         const observer = new MutationObserver(() => scrollToBottom());
                         observer.observe($el, { childList: true, subtree: true, characterData: true });
                     "
                     class="flex-1 p-3.5 sm:p-6 overflow-y-auto space-y-4 sm:space-y-5 bg-slate-50/60 scroll-smooth">
                    
                    @if ($this->activeChat)
                        @foreach ($this->activeChat->messages as $msg)
                            @if ($msg->role === 'user')
                                <!-- User Message Bubble -->
                                <div class="flex justify-end items-end gap-2">
                                    <div class="max-w-[92%] sm:max-w-xl">
                                        <div class="flex justify-end items-center gap-2 mb-1 pr-1">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sie</span>
                                        </div>
                                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl rounded-tr-xs px-3.5 sm:px-5 py-2.5 sm:py-3 text-xs shadow-md shadow-blue-500/10 font-medium leading-relaxed">
                                            {{ $msg->content }}
                                        </div>
                                    </div>
                                    <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-gradient-to-br from-slate-700 to-slate-900 text-white flex items-center justify-center text-xs font-bold shadow-xs shrink-0 mb-0.5">
                                        👤
                                    </div>
                                </div>
                            @else
                                <!-- AI Assistant Bubble -->
                                <div class="flex gap-2 sm:gap-3 items-start max-w-full sm:max-w-3xl">
                                    <div class="w-7 h-7 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white flex items-center justify-center text-xs sm:text-sm shadow-md border border-blue-500/20 shrink-0 mt-0.5">
                                        🤖
                                    </div>
                                    <div class="space-y-2 flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] font-bold text-slate-900">BT KI-Agent</span>
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200/60">Autonom</span>
                                            </div>

                                            <button onclick="navigator.clipboard.writeText(`{{ addslashes($msg->content) }}`); alert('Antwort in Zwischenablage kopiert!');"
                                                    title="Antwort kopieren"
                                                    class="text-[10px] text-slate-400 hover:text-blue-600 font-semibold flex items-center gap-1 transition cursor-pointer">
                                                <span>📋</span>
                                                <span class="hidden sm:inline">Kopieren</span>
                                            </button>
                                        </div>

                                        <!-- Executed Tools Badges -->
                                        @if (!empty($msg->tools))
                                            <div class="space-y-1.5 mb-2">
                                                <div class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                                                    <span>⚙️ Ausgeführte Werkzeuge & System-Aktionen</span>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($msg->tools as $tExecuted)
                                                        <div class="px-3 py-1.5 bg-slate-900 text-slate-100 border border-slate-800 rounded-xl text-3xs font-medium flex items-center gap-2 shadow-xs">
                                                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                                            <span class="font-mono text-blue-300">{{ $tExecuted['tool'] }}</span>
                                                            <span class="text-slate-500">•</span>
                                                            <span class="text-emerald-300 font-semibold">{!! \Illuminate\Support\Str::markdown($tExecuted['result']) !!}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Message Content with Rendered Markdown -->
                                        <div class="bg-white border border-slate-200/90 rounded-2xl rounded-tl-xs p-3.5 sm:p-6 text-xs text-slate-800 leading-relaxed shadow-sm font-sans [&_p]:mb-3 [&_p:last-child]:mb-0 [&_strong]:font-bold [&_strong]:text-slate-900 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:my-2.5 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:my-2.5 [&_li]:mb-1.5 [&_h1]:text-base [&_h1]:font-bold [&_h1]:text-slate-900 [&_h1]:my-2.5 [&_h2]:text-sm [&_h2]:font-bold [&_h2]:text-slate-900 [&_h2]:my-2.5 [&_h3]:text-xs [&_h3]:font-bold [&_h3]:text-slate-900 [&_h3]:my-2 [&_code]:bg-blue-50 [&_code]:text-blue-700 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded-md [&_code]:font-mono [&_code]:text-[11px] [&_pre]:bg-slate-950 [&_pre]:text-slate-100 [&_pre]:p-3.5 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:my-2.5 [&_blockquote]:border-l-4 [&_blockquote]:border-blue-600 [&_blockquote]:pl-3.5 [&_blockquote]:italic [&_blockquote]:text-slate-600 [&_table]:w-full [&_table]:border-collapse [&_table]:my-2.5 [&_th]:bg-slate-100 [&_th]:p-2.5 [&_th]:text-left [&_th]:font-bold [&_th]:border [&_th]:border-slate-200 [&_td]:p-2.5 [&_td]:border [&_td]:border-slate-200 [&_a]:text-blue-600 [&_a]:font-bold [&_a]:underline">
                                            {!! \Illuminate\Support\Str::markdown($msg->content) !!}
                                            
                                            <!-- Feature 3: Interactive Direct Action Buttons -->
                                            @php $lower = strtolower($msg->content); @endphp
                                            @if (str_contains($lower, 'bautagebuch') || str_contains($lower, 'tagesbericht') || str_contains($lower, 'mangel') || str_contains($lower, 'rechnung') || str_contains($lower, 'einsatzplan'))
                                                <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap gap-2">
                                                    @if(str_contains($lower, 'bautagebuch') || str_contains($lower, 'tagesbericht'))
                                                        <a href="/bautagebuch" wire:navigate class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-800 font-extrabold text-[10px] rounded-lg border border-blue-200 transition flex items-center gap-1">
                                                            <span>🎙️</span> <span>Zum Bautagebuch ➔</span>
                                                        </a>
                                                    @endif
                                                    @if(str_contains($lower, 'mangel') || str_contains($lower, 'mängel'))
                                                        <a href="/defects" wire:navigate class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-900 font-extrabold text-[10px] rounded-lg border border-amber-200 transition flex items-center gap-1">
                                                            <span>⚠️</span> <span>Zur Mängel-Verwaltung ➔</span>
                                                        </a>
                                                    @endif
                                                    @if(str_contains($lower, 'rechnung') || str_contains($lower, 'angebot'))
                                                        <a href="/rechnungen" wire:navigate class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 font-extrabold text-[10px] rounded-lg border border-emerald-200 transition flex items-center gap-1">
                                                            <span>📄</span> <span>Zu den Rechnungen ➔</span>
                                                        </a>
                                                    @endif
                                                    @if(str_contains($lower, 'einsatzplan') || str_contains($lower, 'handwerker'))
                                                        <a href="/einsatzplan" wire:navigate class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 font-extrabold text-[10px] rounded-lg border border-indigo-200 transition flex items-center gap-1">
                                                            <span>👷</span> <span>Zum Einsatzplaner ➔</span>
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Feature 4: Smart Follow-Up Chips on Latest Message -->
                                        @if ($loop->last)
                                            <div class="pt-2 space-y-1.5">
                                                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 flex items-center gap-1">
                                                    <span>💡 Vorgeschlagene Folge-Aktionen:</span>
                                                </div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <button wire:click="runQuickAction('Welche Fristen oder VOB/B Bedenken sind hierbei zu beachten?')" 
                                                            class="px-2.5 py-1 bg-white hover:bg-amber-50 text-slate-700 hover:text-amber-900 font-bold text-[11px] rounded-lg border border-slate-200 hover:border-amber-300 transition shadow-2xs cursor-pointer">
                                                        <span>⚠️ Fristen & VOB/B prüfen</span>
                                                    </button>
                                                    <button wire:click="runQuickAction('Erstelle daraus einen Bautagebuch-Eintrag für heute.')" 
                                                            class="px-2.5 py-1 bg-white hover:bg-blue-50 text-slate-700 hover:text-blue-900 font-bold text-[11px] rounded-lg border border-slate-200 hover:border-blue-300 transition shadow-2xs cursor-pointer">
                                                        <span>🎙️ In Bautagebuch eintragen</span>
                                                    </button>
                                                    <button wire:click="runQuickAction('Wie sieht die finanzielle Marge für diese Baustelle aktuell aus?')" 
                                                            class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-slate-700 hover:text-emerald-900 font-bold text-[11px] rounded-lg border border-slate-200 hover:border-emerald-300 transition shadow-2xs cursor-pointer">
                                                        <span>📊 Rohgewinn-Check</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endif

                    <!-- Instant Frontend Loading Indicator when Senden is clicked -->
                    <div wire:loading.flex wire:target="sendPrompt, runQuickAction, photoFile, processVoiceRecording" 
                         class="flex gap-3 items-start max-w-full sm:max-w-xl animate-fade-in my-2">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-slate-900 text-white flex items-center justify-center text-sm shadow-md shadow-blue-500/20 animate-bounce shrink-0 mt-0.5">
                            🤖
                        </div>
                        <div class="bg-white border border-blue-200/90 rounded-2xl rounded-tl-xs px-4 py-3 text-xs text-slate-800 font-medium shadow-md shadow-blue-500/5 flex items-center gap-3">
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-ping"></span>
                                <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            </div>
                            <div class="space-y-0.5">
                                <p class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                    <span>BT KI-Agent generiert Antwort...</span>
                                    <span class="text-[10px] text-blue-600 font-semibold animate-pulse">(Live)</span>
                                </p>
                                <p class="text-[11px] text-slate-500 italic">Analysiere Baustellen-Datenbank, VOB/B & Werkzeuge...</p>
                            </div>
                        </div>
                    </div>

                    @if ($isProcessing)
                        <div wire:loading.remove class="flex gap-3 items-start max-w-full sm:max-w-xl animate-fade-in my-2">
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-slate-900 text-white flex items-center justify-center text-sm shadow-md shadow-blue-500/20 animate-bounce shrink-0 mt-0.5">
                                🤖
                            </div>
                            <div class="bg-white border border-blue-200/90 rounded-2xl rounded-tl-xs px-4 py-3 text-xs text-slate-800 font-medium shadow-md shadow-blue-500/5 flex items-center gap-3">
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-ping"></span>
                                    <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                </div>
                                <div class="space-y-0.5">
                                    <p class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                        <span>BT KI-Agent verarbeitet Befehl...</span>
                                    </p>
                                    <p class="text-[11px] text-slate-500 italic">Führe Datenbank-Werkzeuge & Berechnungen aus...</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Input Form with OpenAI Whisper Voice Recording & Vision Photo Upload -->
                <div x-data="{
                    recording: false,
                    recordingTime: 0,
                    recordingInterval: null,
                    mediaRecorder: null,
                    audioChunks: [],
                    async startRecording() {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            this.mediaRecorder = new MediaRecorder(stream);
                            this.audioChunks = [];
                            this.mediaRecorder.ondataavailable = e => {
                                if (e.data.size > 0) this.audioChunks.push(e.data);
                            };
                            this.mediaRecorder.onstop = async () => {
                                clearInterval(this.recordingInterval);
                                const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                                const file = new File([blob], 'speech.webm', { type: 'audio/webm' });
                                @this.upload('audioFile', file, () => {
                                    @this.processAudioUpload();
                                });
                            };
                            this.mediaRecorder.start();
                            this.recording = true;
                            this.recordingTime = 0;
                            this.recordingInterval = setInterval(() => this.recordingTime++, 1000);
                        } catch (err) {
                            alert('Mikrofon-Zugriff fehlgeschlagen: ' + err.message);
                        }
                    },
                    stopRecording() {
                        if (this.mediaRecorder && this.recording) {
                            this.mediaRecorder.stop();
                            this.recording = false;
                            clearInterval(this.recordingInterval);
                            if (this.mediaRecorder.stream) {
                                this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
                            }
                        }
                    }
                }">
                    <!-- Photo Thumbnail Preview if uploaded -->
                    @if ($photoFile)
                        <div class="px-4 py-2 bg-blue-50 border-t border-blue-200 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">📷</span>
                                <div>
                                    <span class="text-xs font-extrabold text-blue-900">Baustellen-Foto ausgewählt (Vision KI)</span>
                                    <span class="text-[10px] text-blue-600 block">{{ $photoFile->getClientOriginalName() }}</span>
                                </div>
                            </div>
                            <button type="button" wire:click="$set('photoFile', null)" class="text-slate-400 hover:text-rose-600 font-bold text-xs cursor-pointer">✕ Entfernen</button>
                        </div>
                    @endif

                    <!-- Quick Action Pills -->
                    <div class="px-3 sm:px-4 pt-2.5 bg-slate-50 border-t border-slate-200/60 flex flex-wrap gap-1.5 sm:gap-2 text-[11px]">
                        <button type="button" wire:click="$set('userMessage', 'Berechne Aufmaß: Kellerwand Süd 14,5m x 2,8m mit Fenster 1,20m x 1,00m nach VOB/B')" class="px-2 sm:px-2.5 py-1 bg-white hover:bg-indigo-50 text-indigo-700 hover:text-indigo-900 rounded-lg border border-indigo-200 hover:border-indigo-300 font-bold transition-all shadow-2xs cursor-pointer flex items-center gap-1">
                            <span>📐</span> <span>Aufmaß berechnen</span>
                        </button>
                        <button type="button" wire:click="$set('userMessage', 'Zeige Baustoffpreise Juli 2026 für Bitumen, Injektionsharz und Dämmung')" class="px-2 sm:px-2.5 py-1 bg-white hover:bg-blue-50 text-blue-700 hover:text-blue-900 rounded-lg border border-blue-200 hover:border-blue-300 font-bold transition-all shadow-2xs cursor-pointer flex items-center gap-1">
                            <span>📦</span> <span>Materialpreise</span>
                        </button>
                        <button type="button" wire:click="$set('userMessage', 'Erstelle einen KI-Wochenbericht für Baustelle Berching')" class="px-2 sm:px-2.5 py-1 bg-white hover:bg-blue-50 text-slate-700 hover:text-blue-700 rounded-lg border border-slate-200 hover:border-blue-300 font-medium transition-all shadow-2xs cursor-pointer flex items-center gap-1">
                            <span>📊</span> <span>Wochenbericht</span>
                        </button>
                        <button type="button" wire:click="$set('userMessage', 'Erstelle eine Bedenkenanmeldung gem. § 4 VOB/B wegen feuchtem Untergrund')" class="px-2 sm:px-2.5 py-1 bg-white hover:bg-blue-50 text-slate-700 hover:text-blue-700 rounded-lg border border-slate-200 hover:border-blue-300 font-medium transition-all shadow-2xs cursor-pointer flex items-center gap-1">
                            <span>⚖️</span> <span>VOB/B Bedenken</span>
                        </button>
                    </div>

                    <form wire:submit="sendPrompt" class="p-2.5 sm:p-4 bg-white border-t border-slate-200/80 flex items-end gap-1.5 sm:gap-3">
                        
                        <!-- Photo Upload Button (OpenAI Vision) -->
                        <label title="Baustellen-Foto hochladen"
                               class="p-2.5 sm:px-3.5 sm:py-3.5 bg-slate-100 hover:bg-blue-100 text-slate-700 hover:text-blue-800 font-bold text-xs rounded-xl border border-slate-200 hover:border-blue-300 transition-all flex items-center justify-center gap-1.5 shrink-0 cursor-pointer h-10 sm:h-auto">
                            <span class="text-sm sm:text-lg">📷</span>
                            <span class="hidden md:inline">Foto</span>
                            <input type="file" wire:model="photoFile" accept="image/*" class="hidden">
                        </label>

                        <!-- Voice Recording Button (OpenAI Whisper) -->
                        <template x-if="!recording">
                            <button type="button" 
                                    @click="startRecording()"
                                    title="Spracheingabe starten"
                                    class="p-2.5 sm:px-3.5 sm:py-3.5 bg-slate-100 hover:bg-blue-100 text-slate-700 hover:text-blue-800 font-bold text-xs rounded-xl border border-slate-200 hover:border-blue-300 transition-all flex items-center justify-center gap-1.5 shrink-0 cursor-pointer h-10 sm:h-auto">
                                <span class="text-sm sm:text-lg">🎙️</span>
                                <span class="hidden md:inline">Einsprechen</span>
                            </button>
                        </template>

                        <template x-if="recording">
                            <button type="button" 
                                    @click="stopRecording()"
                                    title="Aufnahme beenden & analysieren"
                                    class="px-2.5 sm:px-3.5 py-2.5 sm:py-3.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-md shadow-rose-500/20 animate-pulse transition-all flex items-center gap-1.5 shrink-0 cursor-pointer h-10 sm:h-auto">
                                <span class="w-2.5 h-2.5 rounded-full bg-white animate-ping"></span>
                                <span>Stopp (<span x-text="recordingTime"></span>s)</span>
                            </button>
                        </template>

                        <div class="relative flex-1">
                            <textarea wire:model="userMessage" 
                                      x-data="{
                                          resize() {
                                              $el.style.height = 'auto';
                                              $el.style.height = Math.min($el.scrollHeight, 180) + 'px';
                                          }
                                      }"
                                      x-init="resize()"
                                      @input="resize()"
                                      x-effect="resize(); $nextTick(() => resize())"
                                      @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendPrompt(); }"
                                      rows="1"
                                      class="w-full bg-slate-50/80 border border-slate-200 rounded-xl pl-3.5 sm:pl-4 pr-3.5 sm:pr-14 py-2.5 sm:py-3 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition-all shadow-2xs resize-none overflow-y-auto max-h-44 leading-relaxed block"
                                      placeholder="Aufgabe eingeben oder einsprechen..." required></textarea>
                            
                            <div class="absolute right-2.5 bottom-2.5 hidden sm:flex items-center gap-1 text-[9px] font-semibold text-slate-400 bg-slate-100/90 border border-slate-200/80 px-1.5 py-0.5 rounded shadow-2xs backdrop-blur-xs">
                                Enter ↵
                            </div>
                        </div>

                        <button type="submit" wire:loading.attr="disabled" 
                                class="px-3 sm:px-5 py-2.5 sm:py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 active:scale-95 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-1.5 shrink-0 disabled:opacity-50 cursor-pointer h-10 sm:h-auto">
                            <span wire:loading.remove wire:target="sendPrompt" class="flex items-center gap-1.5">
                                <span>🚀</span>
                                <span class="hidden sm:inline">Senden</span>
                            </span>
                            <span wire:loading wire:target="sendPrompt" class="flex items-center gap-2">
                                <span class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                <span class="hidden sm:inline">Führe aus...</span>
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
