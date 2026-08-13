<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Services\OpenAiAgentService;
use App\Models\AgentChat;
use App\Models\AgentChatMessage;
use Illuminate\Support\Str;

new class extends Component {
    use WithFileUploads;

    public ?string $activeChatId = null;
    public string $userMessage = '';
    public bool $isProcessing = false;
    public $audioFile;
    public $photoFile;
    public string $searchQuery = '';

    public ?string $editingChatId = null;
    public string $editingChatTitle = '';
    public bool $showRenameModal = false;

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

    public function openRenameModal(string $chatId)
    {
        $chat = AgentChat::find($chatId);
        if ($chat) {
            $this->editingChatId = $chat->id;
            $this->editingChatTitle = $chat->title;
            $this->showRenameModal = true;
        }
    }

    public function saveChatTitle()
    {
        $title = trim($this->editingChatTitle);
        if (empty($title)) {
            $this->dispatch('notify', '⚠️ Bitte einen gültigen Namen eingeben.');
            return;
        }

        if ($this->editingChatId) {
            $chat = AgentChat::find($this->editingChatId);
            if ($chat) {
                $chat->update(['title' => $title]);
                $this->dispatch('notify', '✏️ Unterhaltung umbenannt in "' . $title . '"');
            }
        }

        $this->showRenameModal = false;
        $this->editingChatId = null;
        $this->editingChatTitle = '';
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
        $query = AgentChat::withCount('messages')->orderBy('updated_at', 'desc');
        
        if (!empty(trim($this->searchQuery))) {
            $query->where('title', 'like', '%' . trim($this->searchQuery) . '%');
        }

        return $query->get();
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

        // Auto-generate title from first prompt if title is default
        if ($chat->title === 'Neue Unterhaltung') {
            $title = Str::limit($prompt, 36, '...');
            $chat->update(['title' => $title]);
        } else {
            $chat->touch();
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

    public function runQuickAction(string $actionText, bool $autoSend = true, ?OpenAiAgentService $agentService = null)
    {
        $this->userMessage = $actionText;
        if ($autoSend) {
            $this->sendPrompt($agentService ?? app(OpenAiAgentService::class));
        }
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

        $filename = 'KI-Protokoll-' . Str::slug($chat->title) . '-' . date('Y-m-d') . '.md';

        return response()->streamDownload(function () use ($md) {
            echo $md;
        }, $filename);
    }
}; ?>

<div x-data="{
        sidebarOpen: window.innerWidth >= 1024,
        mobileSidebar: false,
        copiedMsgId: null,
        toggleSidebar() {
            if (window.innerWidth < 1024) {
                this.mobileSidebar = !this.mobileSidebar;
            } else {
                this.sidebarOpen = !this.sidebarOpen;
            }
        },
        copyText(text, id) {
            navigator.clipboard.writeText(text);
            this.copiedMsgId = id;
            setTimeout(() => { this.copiedMsgId = null; }, 2000);
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const el = document.getElementById('chat-scroll-container');
                if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            });
        }
     }"
     x-init="
        scrollToBottom();
        const observer = new MutationObserver(() => scrollToBottom());
        const container = document.getElementById('chat-scroll-container');
        if (container) observer.observe(container, { childList: true, subtree: true });
        
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) mobileSidebar = false;
        });
     "
     class="h-full w-full flex overflow-hidden bg-slate-950 font-sans relative">

    <!-- Backdrop for Mobile Sidebar Drawer -->
    <div x-show="mobileSidebar" 
         x-cloak
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileSidebar = false" 
         class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs z-40 lg:hidden"></div>

    <!-- ========================================== -->
    <!-- CHATGPT-STYLE SIDEBAR (Sessions & History)  -->
    <!-- ========================================== -->
    <aside :class="{
               'translate-x-0': mobileSidebar,
               '-translate-x-full': !mobileSidebar,
               'lg:translate-x-0 lg:w-72 xl:w-80': sidebarOpen,
               'lg:-translate-x-full lg:w-0': !sidebarOpen
           }"
           class="fixed lg:static inset-y-0 left-0 z-50 flex flex-col bg-slate-950 text-slate-200 border-r border-slate-800/80 transition-all duration-300 ease-in-out w-72 sm:w-80 shrink-0 h-full overflow-hidden shadow-2xl lg:shadow-none">
        
        <!-- Sidebar Top Header & New Chat Button -->
        <div class="p-3.5 space-y-3 border-b border-slate-800/80 shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 flex items-center justify-center text-base font-bold shadow-md shadow-blue-500/20 text-white">
                        🤖
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-black text-white tracking-wide">BT Agent</span>
                            <span class="px-1.5 py-0.2 text-[9px] font-black uppercase tracking-wider rounded bg-blue-500/20 text-cyan-300 border border-cyan-500/30">PRO</span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">Betriebs-Assistent</p>
                    </div>
                </div>

                <!-- Close Button (Mobile) or Collapse Button (Desktop) -->
                <button @click="toggleSidebar()" 
                        title="Seitenleiste schließen"
                        class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800/80 transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <!-- New Chat Primary Action Button -->
            <button wire:click="createNewChat" 
                    @click="if (window.innerWidth < 1024) mobileSidebar = false;"
                    class="w-full group py-2.5 px-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-600/25 transition-all duration-150 flex items-center justify-between cursor-pointer btn-press border border-blue-400/30">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-black">+</span>
                    <span>Neuer Chat</span>
                </span>
                <span class="text-[10px] font-mono opacity-70 group-hover:opacity-100 bg-black/20 px-1.5 py-0.5 rounded">Neu</span>
            </button>

            <!-- Search Sessions Input -->
            <div class="relative">
                <input wire:model.live.debounce.250ms="searchQuery" 
                       type="text" 
                       placeholder="Chats durchsuchen..." 
                       class="w-full bg-slate-900/90 text-slate-200 placeholder-slate-500 border border-slate-800 rounded-xl pl-8 pr-3 py-1.5 text-[11px] focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition shadow-inner">
                <svg class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <!-- Chat History List Grouped by Time Periods (ChatGPT Standard) -->
        <div class="flex-1 overflow-y-auto p-2 space-y-4">
            @php
                $chats = $this->chats;
                $today = $chats->filter(fn($c) => $c->updated_at->isToday());
                $yesterday = $chats->filter(fn($c) => $c->updated_at->isYesterday());
                $lastWeek = $chats->filter(fn($c) => $c->updated_at->greaterThanOrEqualTo(now()->subDays(7)) && !$c->updated_at->isToday() && !$c->updated_at->isYesterday());
                $older = $chats->filter(fn($c) => $c->updated_at->lessThan(now()->subDays(7)));
            @endphp

            <!-- Group: Heute -->
            @if ($today->isNotEmpty())
                <div class="space-y-1">
                    <div class="px-2.5 py-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                        <span>Heute</span>
                        <span class="text-[9px] font-mono text-slate-400">{{ $today->count() }}</span>
                    </div>
                    @foreach ($today as $c)
                        @include('livewire.partials.agent-chat-list-item', ['chatItem' => $c, 'isActive' => $activeChatId === $c->id])
                    @endforeach
                </div>
            @endif

            <!-- Group: Gestern -->
            @if ($yesterday->isNotEmpty())
                <div class="space-y-1">
                    <div class="px-2.5 py-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                        <span>Gestern</span>
                        <span class="text-[9px] font-mono text-slate-400">{{ $yesterday->count() }}</span>
                    </div>
                    @foreach ($yesterday as $c)
                        @include('livewire.partials.agent-chat-list-item', ['chatItem' => $c, 'isActive' => $activeChatId === $c->id])
                    @endforeach
                </div>
            @endif

            <!-- Group: Letzte 7 Tage -->
            @if ($lastWeek->isNotEmpty())
                <div class="space-y-1">
                    <div class="px-2.5 py-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                        <span>Letzte 7 Tage</span>
                        <span class="text-[9px] font-mono text-slate-400">{{ $lastWeek->count() }}</span>
                    </div>
                    @foreach ($lastWeek as $c)
                        @include('livewire.partials.agent-chat-list-item', ['chatItem' => $c, 'isActive' => $activeChatId === $c->id])
                    @endforeach
                </div>
            @endif

            <!-- Group: Ältere Chats -->
            @if ($older->isNotEmpty())
                <div class="space-y-1">
                    <div class="px-2.5 py-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                        <span>Ältere Chats</span>
                        <span class="text-[9px] font-mono text-slate-400">{{ $older->count() }}</span>
                    </div>
                    @foreach ($older as $c)
                        @include('livewire.partials.agent-chat-list-item', ['chatItem' => $c, 'isActive' => $activeChatId === $c->id])
                    @endforeach
                </div>
            @endif

            @if ($chats->isEmpty())
                <div class="p-6 text-center text-xs text-slate-500 space-y-2">
                    <p class="text-2xl">💬</p>
                    <p class="font-medium">Keine Unterhaltungen gefunden.</p>
                </div>
            @endif
        </div>

        <!-- Sidebar Footer Info & Status -->
        <div class="p-3 bg-slate-900/90 border-t border-slate-800/80 shrink-0 space-y-2">
            <div class="flex items-center justify-between text-[11px] text-slate-300">
                <span class="flex items-center gap-1.5 font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Modell: GPT-4o Agent</span>
                </span>
                <span class="text-[10px] font-mono text-slate-400">VOB/B & ERP</span>
            </div>
            <div class="grid grid-cols-2 gap-1.5 pt-1">
                <a href="/wissen" wire:navigate class="px-2 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-[10px] font-bold transition flex items-center justify-center gap-1 text-center">
                    <span>📚</span> <span>Wissensbasis</span>
                </a>
                <a href="/dashboard" wire:navigate class="px-2 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-[10px] font-bold transition flex items-center justify-center gap-1 text-center">
                    <span>📊</span> <span>Cockpit</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- ========================================== -->
    <!-- MAIN CHAT CANVAS & CONVERSATION FEED       -->
    <!-- ========================================== -->
    <main class="flex-1 flex flex-col h-full min-w-0 bg-slate-50 relative overflow-hidden">
        
        <!-- Top Sticky Header Navigation Bar -->
        <header class="h-14 sm:h-16 px-3.5 sm:px-6 bg-white/95 backdrop-blur-md border-b border-slate-200/80 flex items-center justify-between shrink-0 z-10 shadow-2xs">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <!-- Sidebar Toggle Button -->
                <button @click="toggleSidebar()" 
                        title="Seitenleiste umschalten"
                        class="p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition cursor-pointer btn-press">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                    </svg>
                </button>

                <!-- Model Indicator Pill -->
                <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-slate-100/90 rounded-full border border-slate-200/80">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-black text-slate-800 tracking-tight">BT Bau-Agent PRO</span>
                    <span class="text-[10px] font-medium text-slate-500 border-l border-slate-300 pl-2">GPT-4o</span>
                </div>

                <!-- Active Chat Title with Quick Edit Icon -->
                @if ($this->activeChat)
                    <div class="flex items-center gap-1.5 min-w-0">
                        <h1 class="text-xs sm:text-sm font-bold text-slate-900 truncate max-w-[140px] sm:max-w-xs md:max-w-md">
                            {{ $this->activeChat->title }}
                        </h1>
                        <button wire:click="openRenameModal('{{ $this->activeChat->id }}')" 
                                title="Titel bearbeiten"
                                class="p-1 text-slate-400 hover:text-blue-600 transition cursor-pointer rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Right Controls: Export & Actions -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <button wire:click="exportChatMarkdown" 
                        title="Unterhaltung als Markdown-Protokoll exportieren"
                        class="px-2.5 sm:px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 hover:text-slate-900 font-bold text-xs rounded-xl border border-slate-200 transition flex items-center gap-1.5 cursor-pointer btn-press">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span class="hidden sm:inline">Exportieren</span>
                </button>

                <button wire:click="createNewChat" 
                        title="Neuen Chat starten"
                        class="px-2.5 sm:px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs shadow-blue-500/20 transition flex items-center gap-1 cursor-pointer btn-press">
                    <span class="text-sm leading-none">+</span>
                    <span class="hidden sm:inline">Neu</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Messages Feed -->
        <div id="chat-scroll-container" 
             class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6 scroll-smooth">
            
            <div class="max-w-4xl mx-auto w-full space-y-6">
                
                @if ($this->activeChat && $this->activeChat->messages->count() <= 1)
                    <!-- ========================================== -->
                    <!-- HERO EMPTY STATE (ChatGPT / Claude Style)   -->
                    <!-- ========================================== -->
                    <div class="py-8 sm:py-12 text-center space-y-6 animate-fade-in">
                        <div class="relative inline-flex items-center justify-center">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-3xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 flex items-center justify-center text-3xl sm:text-4xl shadow-xl shadow-blue-500/30 text-white border border-blue-400/40">
                                🤖
                            </div>
                            <span class="absolute -bottom-1 -right-1 flex h-4 w-4">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-500 border-2 border-white"></span>
                            </span>
                        </div>

                        <div class="space-y-2 max-w-xl mx-auto px-4">
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                                Wie kann ich Ihr Bauprojekt heute unterstützen?
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Ich bin Ihr autonomer KI-Betriebsassistent für die BT Bautechnik UG. Ich erstelle Bautagebücher, erfasse Mängel, berechne Aufmaße nach VOB/C und verbuche Rechnungen direkt im System.
                            </p>
                        </div>

                        <!-- 6-Card Prompt Starter Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-4 text-left">
                            
                            <!-- Card 1: Bautagebuch -->
                            <button wire:click="runQuickAction('Trage für heute im Bautagebuch ein: 3 Mann Kolonne auf Baustelle Berching, 40m² Bitumenabdichtung verlegt bei 22 Grad sonnig.')" 
                                    class="group p-4 bg-white hover:bg-blue-50/60 rounded-2xl border border-slate-200 hover:border-blue-300 shadow-2xs hover:shadow-md transition-all duration-200 cursor-pointer flex flex-col justify-between gap-3 text-left">
                                <div class="flex items-center justify-between">
                                    <span class="w-9 h-9 rounded-xl bg-blue-100 group-hover:bg-blue-600 text-blue-700 group-hover:text-white flex items-center justify-center text-base transition-colors font-bold shadow-xs">🎙️</span>
                                    <span class="text-[10px] font-bold text-blue-600 group-hover:translate-x-0.5 transition-transform">Starten ➔</span>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 group-hover:text-blue-900">Bautagebuch erfassen</h4>
                                    <p class="text-[11px] text-slate-500 group-hover:text-slate-600 line-clamp-2 mt-0.5">Kolonne, Witterung & Tagesleistung eintragen</p>
                                </div>
                            </button>

                            <!-- Card 2: Mangel -->
                            <button wire:click="runQuickAction('Erfasse einen Mangel: Dachdurchführung undicht im Dachgeschoss Haus A, Frist 7 Tage nach VOB/B.')" 
                                    class="group p-4 bg-white hover:bg-amber-50/60 rounded-2xl border border-slate-200 hover:border-amber-300 shadow-2xs hover:shadow-md transition-all duration-200 cursor-pointer flex flex-col justify-between gap-3 text-left">
                                <div class="flex items-center justify-between">
                                    <span class="w-9 h-9 rounded-xl bg-amber-100 group-hover:bg-amber-600 text-amber-700 group-hover:text-white flex items-center justify-center text-base transition-colors font-bold shadow-xs">⚠️</span>
                                    <span class="text-[10px] font-bold text-amber-600 group-hover:translate-x-0.5 transition-transform">Starten ➔</span>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 group-hover:text-amber-900">Mangel & VOB/B Frist</h4>
                                    <p class="text-[11px] text-slate-500 group-hover:text-slate-600 line-clamp-2 mt-0.5">Mangelrüge mit Nachfrist & Dokumentation</p>
                                </div>
                            </button>

                            <!-- Card 3: Aufmaß -->
                            <button wire:click="runQuickAction('Berechne Aufmaß: Kellerwand Süd 14,5m x 2,8m mit Fensteröffnung 1,20m x 1,00m nach VOB/C Übermessungsregeln.')" 
                                    class="group p-4 bg-white hover:bg-indigo-50/60 rounded-2xl border border-slate-200 hover:border-indigo-300 shadow-2xs hover:shadow-md transition-all duration-200 cursor-pointer flex flex-col justify-between gap-3 text-left">
                                <div class="flex items-center justify-between">
                                    <span class="w-9 h-9 rounded-xl bg-indigo-100 group-hover:bg-indigo-600 text-indigo-700 group-hover:text-white flex items-center justify-center text-base transition-colors font-bold shadow-xs">📐</span>
                                    <span class="text-[10px] font-bold text-indigo-600 group-hover:translate-x-0.5 transition-transform">Starten ➔</span>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 group-hover:text-indigo-900">Aufmaß & VOB/C</h4>
                                    <p class="text-[11px] text-slate-500 group-hover:text-slate-600 line-clamp-2 mt-0.5">Massenermittlung & Übermessungsregeln</p>
                                </div>
                            </button>

                            <!-- Card 4: Rechnungen -->
                            <button wire:click="runQuickAction('Erstelle einen Rechnungs-Entwurf über 4500 Euro für die Baustelle Berching für Flachdachabdichtung.')" 
                                    class="group p-4 bg-white hover:bg-emerald-50/60 rounded-2xl border border-slate-200 hover:border-emerald-300 shadow-2xs hover:shadow-md transition-all duration-200 cursor-pointer flex flex-col justify-between gap-3 text-left">
                                <div class="flex items-center justify-between">
                                    <span class="w-9 h-9 rounded-xl bg-emerald-100 group-hover:bg-emerald-600 text-emerald-700 group-hover:text-white flex items-center justify-center text-base transition-colors font-bold shadow-xs">💶</span>
                                    <span class="text-[10px] font-bold text-emerald-600 group-hover:translate-x-0.5 transition-transform">Starten ➔</span>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 group-hover:text-emerald-900">Rechnungs-Entwurf</h4>
                                    <p class="text-[11px] text-slate-500 group-hover:text-slate-600 line-clamp-2 mt-0.5">Abschlags- oder Schlussrechnung anlegen</p>
                                </div>
                            </button>

                            <!-- Card 5: Marge / Deckungsbeitrag -->
                            <button wire:click="runQuickAction('Wie steht Baustelle Berching finanziell da? Berechne Rohgewinn, Baukosten und Marge.')" 
                                    class="group p-4 bg-white hover:bg-cyan-50/60 rounded-2xl border border-slate-200 hover:border-cyan-300 shadow-2xs hover:shadow-md transition-all duration-200 cursor-pointer flex flex-col justify-between gap-3 text-left">
                                <div class="flex items-center justify-between">
                                    <span class="w-9 h-9 rounded-xl bg-cyan-100 group-hover:bg-cyan-600 text-cyan-700 group-hover:text-white flex items-center justify-center text-base transition-colors font-bold shadow-xs">📊</span>
                                    <span class="text-[10px] font-bold text-cyan-600 group-hover:translate-x-0.5 transition-transform">Starten ➔</span>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 group-hover:text-cyan-900">Finanzielle Marge</h4>
                                    <p class="text-[11px] text-slate-500 group-hover:text-slate-600 line-clamp-2 mt-0.5">Erlöse gegen Subunternehmer-Kosten stellen</p>
                                </div>
                            </button>

                            <!-- Card 6: Wetter & Ausführung -->
                            <button wire:click="runQuickAction('Können wir auf Baustelle Berching Bitumenabdichtungen verlegen oder gibt es wetterbedingte Bedenken gem. VOB/B?')" 
                                    class="group p-4 bg-white hover:bg-amber-50/60 rounded-2xl border border-slate-200 hover:border-amber-300 shadow-2xs hover:shadow-md transition-all duration-200 cursor-pointer flex flex-col justify-between gap-3 text-left">
                                <div class="flex items-center justify-between">
                                    <span class="w-9 h-9 rounded-xl bg-amber-100 group-hover:bg-amber-600 text-amber-700 group-hover:text-white flex items-center justify-center text-base transition-colors font-bold shadow-xs">☀️</span>
                                    <span class="text-[10px] font-bold text-amber-600 group-hover:translate-x-0.5 transition-transform">Starten ➔</span>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-xs text-slate-900 group-hover:text-amber-900">Wetter & Ausführung</h4>
                                    <p class="text-[11px] text-slate-500 group-hover:text-slate-600 line-clamp-2 mt-0.5">Witterungsprüfung & Bedenkenanmeldung</p>
                                </div>
                            </button>
                        </div>
                    </div>
                @endif

                <!-- ========================================== -->
                <!-- CONVERSATION MESSAGE BUBBLES               -->
                <!-- ========================================== -->
                @if ($this->activeChat)
                    @foreach ($this->activeChat->messages as $msg)
                        @if ($msg->role === 'user')
                            <!-- User Message Card -->
                            <div class="flex justify-end items-end gap-2.5 group/user">
                                <div class="max-w-[92%] sm:max-w-2xl space-y-1">
                                    <div class="flex items-center justify-end gap-2 pr-1">
                                        <span class="text-[10px] font-bold text-slate-400">{{ $msg->created_at->format('H:i') }} Uhr</span>
                                        <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Sie</span>
                                    </div>
                                    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-slate-900 text-white rounded-3xl rounded-tr-xs px-4 sm:px-6 py-3 sm:py-3.5 text-xs sm:text-[13px] shadow-md shadow-blue-500/10 font-medium leading-relaxed whitespace-pre-wrap selection:bg-white selection:text-blue-900">
                                        {{ $msg->content }}
                                    </div>
                                </div>
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 text-white flex items-center justify-center text-xs font-bold shadow-xs shrink-0 mb-1">
                                    👤
                                </div>
                            </div>
                        @else
                            <!-- Assistant Message Card -->
                            <div class="flex gap-3 items-start group/assistant">
                                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 text-white flex items-center justify-center text-sm shadow-md shadow-blue-500/20 shrink-0 mt-0.5 border border-blue-300/30">
                                    🤖
                                </div>

                                <div class="space-y-2 flex-1 min-w-0">
                                    <!-- Assistant Header Bar -->
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-slate-900">BT KI-Agent</span>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200/80">Autonom • VOB/B</span>
                                            <span class="text-[10px] text-slate-400 hidden sm:inline">{{ $msg->created_at->format('H:i') }} Uhr</span>
                                        </div>

                                        <!-- Copy Action -->
                                        <button @click="copyText(`{{ addslashes($msg->content) }}`, '{{ $msg->id }}')" 
                                                title="Antwort kopieren"
                                                class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-500 hover:text-slate-800 rounded-lg text-[10px] font-bold border border-slate-200/80 transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                            <span x-show="copiedMsgId !== '{{ $msg->id }}'">📋 Kopieren</span>
                                            <span x-show="copiedMsgId === '{{ $msg->id }}'" class="text-emerald-600">✓ Kopiert!</span>
                                        </button>
                                    </div>

                                    <!-- ReAct Agent System Action Drawer (if tools were executed) -->
                                    @if (!empty($msg->tools))
                                        <div x-data="{ openTools: false }" class="bg-slate-900 text-slate-100 rounded-2xl border border-slate-800 overflow-hidden shadow-xs">
                                            <button @click="openTools = !openTools" 
                                                    class="w-full px-3.5 py-2 flex items-center justify-between text-[11px] font-extrabold hover:bg-slate-800/60 transition cursor-pointer text-slate-300">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                                    <span>⚙️ {{ count($msg->tools) }} System-Aktionen autonom ausgeführt</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 text-slate-400">
                                                    <span x-text="openTools ? 'Details einklappen' : 'Details anzeigen'"></span>
                                                    <svg :class="openTools ? 'rotate-180' : ''" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </div>
                                            </button>

                                            <div x-show="openTools" x-cloak class="p-3 border-t border-slate-800 space-y-2 bg-slate-950/60">
                                                @foreach ($msg->tools as $tExecuted)
                                                    <div class="p-2.5 bg-slate-900 rounded-xl border border-slate-800 text-xs space-y-1">
                                                        <div class="flex items-center justify-between">
                                                            <span class="font-mono text-cyan-300 font-bold">{{ $tExecuted['tool'] ?? 'Systemaktion' }}</span>
                                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">✓ Ausgeführt</span>
                                                        </div>
                                                        <div class="text-[11px] text-slate-300 font-medium leading-relaxed">
                                                            {!! Str::markdown($tExecuted['result'] ?? '') !!}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Assistant Markdown Response Body -->
                                    <div class="bg-white border border-slate-200/90 rounded-3xl rounded-tl-xs p-4 sm:p-6 text-xs sm:text-[13px] text-slate-800 leading-relaxed shadow-sm font-sans [&_p]:mb-3 [&_p:last-child]:mb-0 [&_strong]:font-bold [&_strong]:text-slate-900 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:my-2.5 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:my-2.5 [&_li]:mb-1.5 [&_h1]:text-base [&_h1]:font-extrabold [&_h1]:text-slate-900 [&_h1]:my-3 [&_h2]:text-sm [&_h2]:font-bold [&_h2]:text-slate-900 [&_h2]:my-2.5 [&_h3]:text-xs [&_h3]:font-bold [&_h3]:text-slate-900 [&_h3]:my-2 [&_code]:bg-blue-50 [&_code]:text-blue-700 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded-md [&_code]:font-mono [&_code]:text-[11px] [&_pre]:bg-slate-950 [&_pre]:text-slate-100 [&_pre]:p-4 [&_pre]:rounded-2xl [&_pre]:overflow-x-auto [&_pre]:my-3 [&_blockquote]:border-l-4 [&_blockquote]:border-blue-600 [&_blockquote]:bg-blue-50/40 [&_blockquote]:p-3 [&_blockquote]:rounded-r-xl [&_blockquote]:italic [&_blockquote]:text-slate-700 [&_table]:w-full [&_table]:border-collapse [&_table]:my-3 [&_th]:bg-slate-100 [&_th]:p-2.5 [&_th]:text-left [&_th]:font-bold [&_th]:border [&_th]:border-slate-200 [&_td]:p-2.5 [&_td]:border [&_td]:border-slate-200 [&_a]:text-blue-600 [&_a]:font-bold [&_a]:underline">
                                        {!! Str::markdown($msg->content) !!}

                                        <!-- Interactive Navigation Buttons for context -->
                                        @php $lower = strtolower($msg->content); @endphp
                                        @if (str_contains($lower, 'bautagebuch') || str_contains($lower, 'tagesbericht') || str_contains($lower, 'mangel') || str_contains($lower, 'rechnung') || str_contains($lower, 'einsatzplan'))
                                            <div class="mt-4 pt-3.5 border-t border-slate-100 flex flex-wrap gap-2">
                                                @if(str_contains($lower, 'bautagebuch') || str_contains($lower, 'tagesbericht'))
                                                    <a href="/bautagebuch" wire:navigate class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-800 font-extrabold text-[11px] rounded-xl border border-blue-200 transition flex items-center gap-1.5 btn-press">
                                                        <span>🎙️</span> <span>Bautagebuch öffnen ➔</span>
                                                    </a>
                                                @endif
                                                @if(str_contains($lower, 'mangel') || str_contains($lower, 'mängel'))
                                                    <a href="/maengel" wire:navigate class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-900 font-extrabold text-[11px] rounded-xl border border-amber-200 transition flex items-center gap-1.5 btn-press">
                                                        <span>⚠️</span> <span>Mängel-Verwaltung öffnen ➔</span>
                                                    </a>
                                                @endif
                                                @if(str_contains($lower, 'rechnung') || str_contains($lower, 'angebot'))
                                                    <a href="/rechnungen" wire:navigate class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 font-extrabold text-[11px] rounded-xl border border-emerald-200 transition flex items-center gap-1.5 btn-press">
                                                        <span>💶</span> <span>Rechnungen ansehen ➔</span>
                                                    </a>
                                                @endif
                                                @if(str_contains($lower, 'einsatzplan') || str_contains($lower, 'handwerker'))
                                                    <a href="/einsatzplan" wire:navigate class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 font-extrabold text-[11px] rounded-xl border border-indigo-200 transition flex items-center gap-1.5 btn-press">
                                                        <span>👷</span> <span>Einsatzplaner aufrufen ➔</span>
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Smart Follow-Up Action Chips (on last assistant message) -->
                                    @if ($loop->last)
                                        <div class="pt-2 space-y-1.5">
                                            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 flex items-center gap-1">
                                                <span>💡 Vorgeschlagene nächste Schritte:</span>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button wire:click="runQuickAction('Welche Fristen oder VOB/B Bedenken sind hierbei zwingend zu beachten?')" 
                                                        class="px-3 py-1.5 bg-white hover:bg-amber-50 text-slate-700 hover:text-amber-900 font-bold text-xs rounded-xl border border-slate-200 hover:border-amber-300 transition shadow-2xs cursor-pointer btn-press">
                                                    <span>⚠️ VOB/B Fristen prüfen</span>
                                                </button>
                                                <button wire:click="runQuickAction('Erstelle daraus einen Bautagebuch-Eintrag für heute.')" 
                                                        class="px-3 py-1.5 bg-white hover:bg-blue-50 text-slate-700 hover:text-blue-900 font-bold text-xs rounded-xl border border-slate-200 hover:border-blue-300 transition shadow-2xs cursor-pointer btn-press">
                                                    <span>🎙️ In Bautagebuch eintragen</span>
                                                </button>
                                                <button wire:click="runQuickAction('Wie sieht die finanzielle Marge für diese Baustelle aktuell aus?')" 
                                                        class="px-3 py-1.5 bg-white hover:bg-emerald-50 text-slate-700 hover:text-emerald-900 font-bold text-xs rounded-xl border border-slate-200 hover:border-emerald-300 transition shadow-2xs cursor-pointer btn-press">
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

                <!-- Real-time Agent Thinking Indicator -->
                <div wire:loading.flex wire:target="sendPrompt, runQuickAction, photoFile, processAudioUpload" 
                     class="flex gap-3 items-start max-w-2xl animate-fade-in my-3">
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 text-white flex items-center justify-center text-sm shadow-md shadow-blue-500/20 animate-pulse shrink-0 border border-blue-300/30">
                        🤖
                    </div>
                    <div class="bg-white border border-blue-200/90 rounded-3xl rounded-tl-xs px-5 py-4 text-xs text-slate-800 shadow-md shadow-blue-500/5 flex items-center gap-3">
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="w-2 h-2 rounded-full bg-blue-600 animate-typing-1"></span>
                            <span class="w-2 h-2 rounded-full bg-indigo-600 animate-typing-2"></span>
                            <span class="w-2 h-2 rounded-full bg-cyan-500 animate-typing-3"></span>
                        </div>
                        <div class="space-y-0.5">
                            <p class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                <span>BT KI-Agent generiert Antwort & führt Befehle aus...</span>
                            </p>
                            <p class="text-[11px] text-slate-500">Analysiere Baustellendatenbank, VOB/B Richtlinien & ERP-Werkzeuge...</p>
                        </div>
                    </div>
                </div>

                @if ($isProcessing)
                    <div wire:loading.remove class="flex gap-3 items-start max-w-2xl animate-fade-in my-3">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 text-white flex items-center justify-center text-sm shadow-md shadow-blue-500/20 animate-pulse shrink-0 border border-blue-300/30">
                            🤖
                        </div>
                        <div class="bg-white border border-blue-200/90 rounded-3xl rounded-tl-xs px-5 py-4 text-xs text-slate-800 shadow-md shadow-blue-500/5 flex items-center gap-3">
                            <div class="flex items-center gap-1 shrink-0">
                                <span class="w-2 h-2 rounded-full bg-blue-600 animate-typing-1"></span>
                                <span class="w-2 h-2 rounded-full bg-indigo-600 animate-typing-2"></span>
                                <span class="w-2 h-2 rounded-full bg-cyan-500 animate-typing-3"></span>
                            </div>
                            <div class="space-y-0.5">
                                <p class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                    <span>BT KI-Agent verarbeitet Aufgabe...</span>
                                </p>
                                <p class="text-[11px] text-slate-500">Führe Datenbank-Werkzeuge & Berechnungen aus...</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- ========================================== -->
        <!-- FLOATING BOTTOM COMPOSER (ChatGPT Style)   -->
        <!-- ========================================== -->
        <div class="shrink-0 p-3 sm:p-5 bg-gradient-to-t from-slate-50 via-slate-50/95 to-transparent z-10">
            <div class="max-w-4xl mx-auto w-full space-y-2">
                
                <!-- Quick Topic Pills (Above Input) -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar text-[11px]">
                    <button type="button" 
                            wire:click="$set('userMessage', 'Berechne Aufmaß: Kellerwand Süd 14,5m x 2,8m mit Fenster 1,20m x 1,00m nach VOB/C')" 
                            class="px-3 py-1 bg-white hover:bg-indigo-50 text-indigo-800 rounded-full border border-slate-200 hover:border-indigo-300 font-bold transition shadow-2xs shrink-0 cursor-pointer flex items-center gap-1">
                        <span>📐</span> <span>Aufmaß VOB/C</span>
                    </button>
                    <button type="button" 
                            wire:click="$set('userMessage', 'Zeige Baustoffpreise Juli 2026 für Bitumen, Injektionsharz und Dämmung')" 
                            class="px-3 py-1 bg-white hover:bg-blue-50 text-blue-800 rounded-full border border-slate-200 hover:border-blue-300 font-bold transition shadow-2xs shrink-0 cursor-pointer flex items-center gap-1">
                        <span>📦</span> <span>Materialpreise</span>
                    </button>
                    <button type="button" 
                            wire:click="$set('userMessage', 'Erstelle einen KI-Wochenbericht für Baustelle Berching')" 
                            class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 rounded-full border border-slate-200 font-medium transition shadow-2xs shrink-0 cursor-pointer flex items-center gap-1">
                        <span>📊</span> <span>Wochenbericht</span>
                    </button>
                    <button type="button" 
                            wire:click="$set('userMessage', 'Erstelle eine Bedenkenanmeldung gem. § 4 VOB/B wegen feuchtem Untergrund')" 
                            class="px-3 py-1 bg-white hover:bg-amber-50 text-amber-900 rounded-full border border-slate-200 hover:border-amber-300 font-medium transition shadow-2xs shrink-0 cursor-pointer flex items-center gap-1">
                        <span>⚖️</span> <span>VOB/B Bedenken</span>
                    </button>
                </div>

                <!-- Floating Input Card -->
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
                     }"
                     class="bg-white border border-slate-200/90 rounded-2xl sm:rounded-3xl shadow-xl shadow-slate-900/5 focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10 transition-all overflow-hidden">
                    
                    <!-- Photo Thumbnail Preview if attached -->
                    @if ($photoFile)
                        <div class="px-4 py-2.5 bg-blue-50/80 border-b border-blue-200 flex items-center justify-between gap-3 animate-fade-in">
                            <div class="flex items-center gap-2.5">
                                <span class="text-xl">📷</span>
                                <div>
                                    <span class="text-xs font-extrabold text-blue-950 block">Baustellen-Foto ausgewählt (GPT-4o Vision)</span>
                                    <span class="text-[10px] text-blue-600">{{ $photoFile->getClientOriginalName() }}</span>
                                </div>
                            </div>
                            <button type="button" wire:click="$set('photoFile', null)" class="text-slate-400 hover:text-rose-600 font-bold text-xs cursor-pointer px-2 py-1 rounded hover:bg-white transition">
                                ✕ Entfernen
                            </button>
                        </div>
                    @endif

                    <!-- Voice Recording Banner Overlay -->
                    <div x-show="recording" x-cloak class="px-4 py-3 bg-rose-600 text-white flex items-center justify-between gap-3 animate-fade-in">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1">
                                <span class="w-1.5 bg-white rounded-full animate-soundwave-1"></span>
                                <span class="w-1.5 bg-white rounded-full animate-soundwave-2"></span>
                                <span class="w-1.5 bg-white rounded-full animate-soundwave-3"></span>
                                <span class="w-1.5 bg-white rounded-full animate-soundwave-4"></span>
                                <span class="w-1.5 bg-white rounded-full animate-soundwave-5"></span>
                            </div>
                            <span class="text-xs font-bold">Sprachaufnahme aktiv: <span x-text="recordingTime"></span>s</span>
                        </div>
                        <button type="button" @click="stopRecording()" class="px-3 py-1 bg-white text-rose-700 font-extrabold text-xs rounded-xl hover:bg-rose-50 transition cursor-pointer shadow-sm">
                            Aufnahme beenden & transkribieren
                        </button>
                    </div>

                    <!-- Composer Input Form -->
                    <form wire:submit="sendPrompt" class="p-3 sm:p-4 space-y-2">
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
                                  placeholder="Fragen Sie etwas, erteilen Sie einen Bau-Befehl oder sprechen Sie ein..." 
                                  class="w-full bg-transparent border-0 p-1 text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:ring-0 focus:outline-none resize-none max-h-44 leading-relaxed block"
                                  required></textarea>
                        
                        <!-- Toolbar Controls (Foto, Voice, Send) -->
                        <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                            <div class="flex items-center gap-1 sm:gap-2">
                                <!-- Photo Upload Button (Vision) -->
                                <label title="Baustellen-Foto für GPT-4o Vision Analyse hochladen" 
                                       class="p-2 sm:px-3 sm:py-1.5 text-slate-600 hover:text-blue-700 hover:bg-blue-50 rounded-xl transition flex items-center gap-1.5 text-xs font-bold cursor-pointer btn-press border border-transparent hover:border-blue-200">
                                    <span class="text-base">📷</span>
                                    <span class="hidden sm:inline">Foto</span>
                                    <input type="file" wire:model="photoFile" accept="image/*" class="hidden">
                                </label>

                                <!-- Whisper Voice Button -->
                                <template x-if="!recording">
                                    <button type="button" 
                                            @click="startRecording()" 
                                            title="Spracheingabe mit OpenAI Whisper starten"
                                            class="p-2 sm:px-3 sm:py-1.5 text-slate-600 hover:text-blue-700 hover:bg-blue-50 rounded-xl transition flex items-center gap-1.5 text-xs font-bold cursor-pointer btn-press border border-transparent hover:border-blue-200">
                                        <span class="text-base">🎙️</span>
                                        <span class="hidden sm:inline">Einsprechen</span>
                                    </button>
                                </template>
                            </div>

                            <!-- Send Button -->
                            <div class="flex items-center gap-2">
                                <span class="hidden sm:inline text-[10px] text-slate-400 font-mono">↵ Senden</span>
                                <button type="submit" 
                                        wire:loading.attr="disabled"
                                        title="Nachricht absenden"
                                        class="w-9 h-9 sm:w-10 sm:h-10 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 active:scale-95 text-white rounded-2xl shadow-md shadow-blue-500/20 transition flex items-center justify-center cursor-pointer btn-press disabled:opacity-50">
                                    <span wire:loading.remove wire:target="sendPrompt">
                                        <svg class="w-4 h-4 translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                        </svg>
                                    </span>
                                    <span wire:loading wire:target="sendPrompt">
                                        <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin block"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- LLM Transparency Disclaimer Footer -->
                <p class="text-center text-[10px] text-slate-400 leading-tight">
                    BT KI-Agent greift autonom auf Baustellendatenbank, Bautagebücher und VOB/B zu. Angaben bitte stets fachlich prüfen.
                </p>
            </div>
        </div>
    </main>

    <!-- ========================================== -->
    <!-- RENAME CHAT MODAL                          -->
    <!-- ========================================== -->
    @if ($showRenameModal)
        <div class="fixed inset-0 bg-slate-950/75 backdrop-blur-xs flex items-center justify-center z-50 p-4 font-sans animate-fade-in">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-950 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">✏️</span>
                        <h3 class="text-sm font-extrabold text-white">Unterhaltung umbenennen</h3>
                    </div>
                    <button wire:click="$set('showRenameModal', false)" class="text-slate-400 hover:text-white text-sm font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveChatTitle" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Titel der Unterhaltung</label>
                        <input wire:model="editingChatTitle" 
                               type="text" 
                               class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-bold focus:border-blue-600 focus:bg-white focus:outline-none" 
                               placeholder="z. B. Baustelle Berching Abnahme" 
                               required>
                    </div>

                    <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showRenameModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition cursor-pointer">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
