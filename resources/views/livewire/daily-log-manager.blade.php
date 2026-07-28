<?php

use Livewire\Volt\Component;
use App\Models\DailyLog;
use App\Models\Project;
use App\Models\DailyLogShare;
use App\Models\Contact;

new class extends Component {
    public ?string $selectedProjectId = null;
    public bool $showModal = false;

    // Form
    public string $projectId = '';
    public string $contactId = '';
    public string $date = '';
    public string $weather = 'Sonnig';
    public string $temperature = '20°C';
    public int $workersCount = 2;
    public string $workPerformed = '';
    public string $specialOccurrences = '';

    // AI & Voice Dictation
    public bool $showAiModal = false;
    public string $aiDraftText = '';

    // Share & Approval Modal
    public bool $showShareModal = false;
    public ?string $selectedLogIdForShare = null;
    public string $shareRole = 'Architekt';
    public string $shareName = '';
    public ?string $generatedShareUrl = null;

    public function mount()
    {
        $this->date = date('Y-m-d');
        $firstProject = Project::first();
        if ($firstProject) {
            $this->projectId = $firstProject->id;
            $this->selectedProjectId = $firstProject->id;
        }
    }

    public function getProjectsProperty()
    {
        return Project::all();
    }

    public function getSubcontractorsProperty()
    {
        return Contact::where('type', 'subunternehmer')->get();
    }

    public function getDailyLogsProperty()
    {
        return DailyLog::with(['project', 'shares', 'contact'])
            ->when($this->selectedProjectId, fn($q) => $q->where('project_id', $this->selectedProjectId))
            ->orderBy('date', 'desc')
            ->get();
    }

    public function openCreateModal()
    {
        $this->contactId = '';
        $this->workPerformed = '';
        $this->specialOccurrences = '';
        $this->showModal = true;
    }

    public function saveLog()
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'date' => 'required|date',
            'workPerformed' => 'required|string|min:5',
        ]);

        DailyLog::create([
            'project_id' => $this->projectId,
            'contact_id' => $this->contactId ?: null,
            'date' => $this->date,
            'weather' => $this->weather,
            'temperature' => $this->temperature,
            'workers_count' => $this->workersCount,
            'work_performed' => $this->workPerformed,
            'special_occurrences' => $this->specialOccurrences,
        ]);

        $this->showModal = false;
        $this->dispatch('notify', 'Bautagebuch-Eintrag erfolgreich gespeichert!');
    }

    public function generateLogWithAi(?\App\Services\OpenAiParserService $parser = null)
    {
        $parser = $parser ?? app(\App\Services\OpenAiParserService::class);
        if (empty(trim($this->aiDraftText))) {
            $this->dispatch('notify', 'Bitte geben Sie zuerst Stichpunkte ein.');
            return;
        }

        try {
            $res = $parser->generateDailyLogFromDraft($this->aiDraftText);
            $this->weather = $res['weather'] ?? 'Sonnig';
            $this->temperature = $res['temperature'] ?? '20°C';
            $this->workersCount = intval($res['workers_count'] ?? 2);
            $this->workPerformed = $res['work_performed'] ?? '';
            $this->specialOccurrences = $res['special_occurrences'] ?? '';

            $this->showAiModal = false;
            $this->aiDraftText = '';
            $this->showModal = true;
            $this->dispatch('notify', '✨ Bautagebuch-Eintrag erfolgreich per KI ausformuliert!');
        } catch (\Exception $e) {
            $this->dispatch('notify', 'KI-Fehler: ' . $e->getMessage());
        }
    }

    public function openShareModal(string $logId)
    {
        $this->selectedLogIdForShare = $logId;
        $this->shareRole = 'Architekt';
        $this->shareName = '';
        $this->generatedShareUrl = null;
        $this->showShareModal = true;
    }

    public function generateShareLink()
    {
        if (!$this->selectedLogIdForShare) return;

        $log = DailyLog::findOrFail($this->selectedLogIdForShare);
        $share = DailyLogShare::createShareToken($log, $this->shareRole, $this->shareName);

        $this->generatedShareUrl = route('daily-log.public-approval', ['token' => $share->share_token]);
        $this->dispatch('notify', '🔗 Freigabe-Link für ' . $this->shareRole . ' wurde erstellt!');
    }
}; ?>

<div class="space-y-8 font-sans max-w-full overflow-x-hidden">
    <!-- Header Command Center Banner -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <span>🎙️ Bautagebuch & Regieberichte</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium">Tägliche Dokumentation von Wetter, Baufortschritt, Personal, KI-Diktat und Bauherren-Freigaben.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
            <select wire:model.live="selectedProjectId" class="w-full sm:w-auto bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-blue-600 cursor-pointer">
                <option value="">Alle Baustellen anzeigen</option>
                @foreach ($this->projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>

            <button wire:click="$set('showAiModal', true)" class="w-full sm:w-auto px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
                <span>🎙️ KI-Diktat / Spracheingabe</span>
            </button>

            <button wire:click="openCreateModal" class="w-full sm:w-auto px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10 flex items-center justify-center gap-1 cursor-pointer whitespace-nowrap">
                <span>+ Tagebucheintrag</span>
            </button>
        </div>
    </div>

    <!-- Timeline Entries List -->
    <div class="space-y-4">
        @forelse ($this->dailyLogs as $log)
            @php
                $latestApprovedShare = $log->shares->where('status', 'approved')->first();
                $latestPendingShare = $log->shares->where('status', 'pending')->first();
                $latestRejectedShare = $log->shares->where('status', 'rejected')->first();
            @endphp
            <div wire:key="{{ $log->id }}" class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="px-3 py-1 bg-blue-50 text-blue-700 font-extrabold text-xs rounded-full border border-blue-200">
                            {{ date('d.m.Y', strtotime($log->date)) }}
                        </span>
                        <h3 class="font-bold text-slate-900 text-sm sm:text-base tracking-tight">{{ $log->project->name }}</h3>

                        <!-- Freigabe Status Badges -->
                        @if($latestApprovedShare)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                🟢 Freigegeben ({{ $latestApprovedShare->approver_role }}: {{ $latestApprovedShare->approver_name ?: 'Bestätigt' }})
                            </span>
                        @elseif($latestRejectedShare)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                🔴 Abgelehnt ({{ $latestRejectedShare->approver_name }})
                            </span>
                        @elseif($latestPendingShare)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                🟡 Wartet auf Freigabe ({{ $latestPendingShare->approver_role }})
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs                         <span class="bg-slate-100 px-2.5 py-1 rounded-lg">🌤️ {{ $log->weather }} ({{ $log->temperature }})</span>
                        <span class="bg-slate-100 px-2.5 py-1 rounded-lg">👷 {{ $log->workers_count }} Mitarbeiter</span>
                        
                        @if($log->contact)
                            <span class="bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-bold px-2.5 py-1 rounded-lg flex items-center gap-1">
                                <span>🏗️ Subunternehmer:</span>
                                <span>{{ $log->contact->display_name }}</span>
                            </span>
                        @else
                            <span class="bg-slate-100 text-slate-600 text-xs font-semibold px-2.5 py-1 rounded-lg">
                                🏢 Eigenleistung (BT Bautechnik)
                            </span>
                        @endif

                        <!-- Freigabe-Link Modal Trigger -->
                        <button wire:click="openShareModal('{{ $log->id }}')" 
                                class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-lg transition shadow-2xs cursor-pointer flex items-center gap-1">
                            <span>🔗</span>
                            <span class="hidden sm:inline">Freigabe-Link</span>
                        </button>

                        <!-- Print / PDF Button -->
                        <button onclick="window.print()" 
                                title="Bautagebuch-Eintrag als PDF / Druckansicht öffnen"
                                class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-lg transition shadow-2xs cursor-pointer flex items-center gap-1">
                            <span>🖨️</span>
                            <span class="hidden sm:inline">PDF / Druck</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Geleistete Arbeiten / Gewerk</h4>
                    <p class="text-xs sm:text-sm text-slate-800 leading-relaxed whitespace-pre-line">{{ $log->work_performed }}</p>
                </div>

                @if ($log->special_occurrences)
                    <div class="bg-amber-50 border border-amber-200 p-3.5 rounded-xl space-y-1">
                        <h4 class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                            <span>⚠️</span> Vorkommnisse / Störungen
                        </h4>
                        <p class="text-xs text-amber-800 leading-relaxed">{{ $log->special_occurrences }}</p>
                    </div>
                @endif
            </div>
        @empty
            <div class="py-16 bg-white border border-slate-200/80 rounded-2xl text-center space-y-3">
                <p class="text-base font-bold text-slate-900">Keine Tagebucheinträge vorhanden</p>
                <p class="text-xs text-slate-500">Erfassen Sie Ihren ersten Tagesbericht über den Button "+ Tagebucheintrag" oder per "🎙️ KI-Diktat".</p>
            </div>
        @endforelse
    </div>

    <!-- Create Modal -->
    @if ($showModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-2 sm:p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center shrink-0">
                    <h3 class="text-base font-bold text-slate-900">Neuen Tagesbericht verfassen</h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-700 cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveLog" class="p-4 sm:p-6 space-y-4 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Baustelle</label>
                            <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:border-blue-600">
                                @foreach ($this->projects as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subunternehmer / Gewerk</label>
                            <select wire:model="contactId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:border-blue-600">
                                <option value="">🏢 Eigenleistung (BT Bautechnik)</option>
                                @foreach ($this->subcontractors as $sub)
                                    <option value="{{ $sub->id }}">🏗️ {{ $sub->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>                       </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Datum</label>
                            <input wire:model="date" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-blue-600" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Wetter</label>
                            <select wire:model="weather" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-blue-600">
                                <option value="Sonnig">Sonnig</option>
                                <option value="Bewölkt">Bewölkt</option>
                                <option value="Regen">Regen</option>
                                <option value="Frost/Schnee">Frost/Schnee</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Arbeiter (Anzahl)</label>
                            <input wire:model="workersCount" type="number" min="1" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-blue-600" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Geleistete Arbeiten</label>
                        <textarea wire:model="workPerformed" rows="4" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Details zu Fortschritt, Materialverbrauch und Monteuren..." required></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Vorkommnisse / Störungen (Optional)</label>
                        <textarea wire:model="specialOccurrences" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600" placeholder="Verzögerungen, Behinderungen durch Dritte, Materialmangel..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Eintrag speichern</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- KI Bautagebuch & Voice Dictation Modal -->
    @if ($showAiModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-2 sm:p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" x-data="{ isRecording: false, recognition: null }">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                    <h3 class="text-base font-black flex items-center gap-2">
                        <span>🤖 KI-gestützte Bautagebuch-Erstellung</span>
                    </h3>
                    <button wire:click="$set('showAiModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-blue-50 border border-blue-200 p-3.5 rounded-xl text-xs text-blue-900 flex items-start gap-2.5">
                        <span class="text-base shrink-0">💡</span>
                        <span>Sprechen Sie frei auf der Baustelle oder geben Sie Stichpunkte ein. Die KI strukturiert Ihr Diktat automatisch in Arbeiterzahl, Witterung, Tätigkeiten und Behinderungen!</span>
                    </div>

                    <!-- Voice Record Button -->
                    <div class="flex flex-col items-center justify-center py-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                        <button type="button" 
                                @click="
                                    if (!isRecording) {
                                        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                                        if (!SpeechRecognition) {
                                            alert('Spracherkennung wird in diesem Browser nicht unterstützt. Bitte nutzen Sie Chrome/Safari.');
                                            return;
                                        }
                                        recognition = new SpeechRecognition();
                                        recognition.lang = 'de-DE';
                                        recognition.continuous = true;
                                        recognition.interimResults = true;
                                        recognition.onresult = (e) => {
                                            let text = '';
                                            for (let i = e.resultIndex; i < e.results.length; i++) {
                                                text += e.results[i][0].transcript + ' ';
                                            }
                                            $wire.set('aiDraftText', text);
                                        };
                                        recognition.start();
                                        isRecording = true;
                                    } else {
                                        if (recognition) recognition.stop();
                                        isRecording = false;
                                    }
                                " 
                                :class="isRecording ? 'bg-red-600 animate-pulse text-white shadow-red-500/50' : 'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-500/20'"
                                class="px-4 py-2.5 font-bold text-xs rounded-xl transition shadow-md flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <span x-text="isRecording ? '🔴 Aufnahme stoppen' : '🎙️ Einsprechen (Whisper)'"></span>
                        </button>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700">Notiz / Stichpunkte zur Baustelle:</label>
                        <textarea wire:model="aiDraftText" rows="4" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="z. B. 4 Mann auf Baustelle, 1 Bagger, leichter Regen, 120qm Bitumenabdichtung Haus A verlegt, Voranstrich witterungsbedingt verzögert..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2.5 pt-2 border-t border-slate-100">
                        <button type="button" wire:click="$set('showAiModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">Abbrechen</button>
                        <button type="button" wire:click="generateLogWithAi" wire:loading.attr="disabled" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 flex items-center gap-2">
                            <span wire:loading wire:target="generateLogWithAi">⌛ KI strukturiert Diktat...</span>
                            <span wire:loading.remove wire:target="generateLogWithAi">✨ In Bautagebuch umwandeln</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Share & Approval Link Modal -->
    @if ($showShareModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-2 sm:p-4 font-sans">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🔗</span>
                        <h3 class="text-base font-extrabold text-white">Freigabe-Link erstellen</h3>
                    </div>
                    <button wire:click="$set('showShareModal', false)" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="p-4 sm:p-6 space-y-4 overflow-y-auto">
                    <p class="text-xs text-slate-600">
                        Erstellen Sie einen sicheren Link zur digitalen Freigabe durch den Bauherren oder Architekten. Der Prüfer kann den Tagesbericht einsehen und direkt digital unterschreiben.
                    </p>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Rolle des Prüfers</label>
                        <select wire:model="shareRole" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-900">
                            <option value="Architekt">Architekt / Fachplaner</option>
                            <option value="Bauherr">Bauherr / Auftraggeber</option>
                            <option value="Bauleiter">Bauleiter</option>
                            <option value="Prüfingenieur">Prüfingenieur</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Name des Empfängers (Optional)</label>
                        <input type="text" wire:model="shareName" placeholder="z. B. Dipl.-Ing. Julia Weber" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-900">
                    </div>

                    @if($generatedShareUrl)
                        <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-2xl space-y-2">
                            <span class="text-xs font-bold text-emerald-900 block">✓ Link erfolgreich generiert:</span>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $generatedShareUrl }}" id="shareUrlInput" class="w-full bg-white border border-emerald-300 rounded-xl px-3 py-1.5 text-xs text-slate-800 font-mono">
                                <button type="button" onclick="navigator.clipboard.writeText('{{ $generatedShareUrl }}'); alert('Freigabe-Link in die Zwischenablage kopiert!');" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl whitespace-nowrap shadow-sm">
                                    Kopieren 📋
                                </button>
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="generateShareLink" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-2">
                            <span>🔗</span> Link jetzt generieren
                        </button>
                    @endif

                    <div class="flex justify-end pt-2">
                        <button type="button" wire:click="$set('showShareModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- GLOBAL KI LOADING OVERLAY FOR BAUTAGEBUCH -->
    <div wire:loading wire:target="generateLogWithAi" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center z-50 p-4">
        <div class="bg-slate-900 border border-blue-500/30 rounded-3xl p-8 max-w-md w-full shadow-2xl text-center space-y-5">
            <div class="relative w-16 h-16 mx-auto flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-blue-500/20 border-t-blue-500 animate-spin"></div>
                <div class="w-14 h-14 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-full flex items-center justify-center shadow-lg shadow-blue-500/40">
                    <span class="text-2xl">🤖</span>
                </div>
            </div>
            <div class="space-y-1.5">
                <h3 class="text-lg font-black text-white">Bautagebuch wird strukturiert...</h3>
                <p class="text-xs text-blue-200/80">OpenAI wandelt Ihre Notizen in ein strukturiertes Bautagebuch um. Bitte einen kurzen Moment Geduld.</p>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 via-indigo-500 to-blue-500 h-full w-3/4 animate-pulse"></div>
            </div>
        </div>
    </div>
</div>
