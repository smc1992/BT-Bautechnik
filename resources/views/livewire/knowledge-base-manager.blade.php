<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeChunk;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public $uploadedFile;
    public string $title = '';
    public string $category = 'DIN-Normen';
    public string $content = '';
    public bool $showModal = false;
    public bool $isSaving = false;

    // Vector Search Test
    public string $searchQuery = '';
    public array $searchResults = [];
    public bool $isSearching = false;

    public function openModal()
    {
        $this->reset(['title', 'category', 'content', 'uploadedFile']);
        $this->category = 'DIN-Normen';
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->uploadedFile = null;
    }

    public function updatedUploadedFile()
    {
        if ($this->uploadedFile && empty($this->title)) {
            $filename = pathinfo($this->uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $this->title = str_replace(['_', '-'], ' ', $filename);
        }
    }

    public function saveDocument(KnowledgeBaseService $kbService)
    {
        $this->validate([
            'title' => 'required|string|min:3|max:255',
            'category' => 'required|string',
            'uploadedFile' => 'nullable|file|mimes:pdf,txt,md,csv,json|max:10240',
            'content' => 'nullable|string',
        ]);

        if (empty($this->uploadedFile) && empty(trim($this->content))) {
            $this->addError('content', 'Bitte laden Sie eine Datei (PDF/TXT) hoch oder geben Sie Volltext ein.');
            return;
        }

        $this->isSaving = true;

        try {
            $extractedText = $this->content;
            $storedPath = null;

            if ($this->uploadedFile) {
                $ext = $this->uploadedFile->getClientOriginalExtension();
                $extractedText = $kbService->extractTextFromFile($this->uploadedFile->getRealPath(), $ext);
                $storedPath = $this->uploadedFile->store('knowledge_documents', 'public');
            }

            if (empty(trim($extractedText))) {
                throw new \Exception("Kein lesbarer Text aus der Datei/Eingabe extrahiert.");
            }

            $doc = $kbService->storeDocument($this->title, $this->category, $extractedText, $storedPath);

            $this->dispatch('notify', "✅ Datei & Dokument '{$doc->title}' mit {$doc->chunks->count()} Vektor-Chunks gespeichert!");
            $this->closeModal();
        } catch (\Exception $e) {
            $this->dispatch('notify', '⚠️ Fehler bei Vektor-Verarbeitung: ' . $e->getMessage());
        } finally {
            $this->isSaving = false;
            $this->uploadedFile = null;
        }
    }

    public function testVectorSearch(KnowledgeBaseService $kbService)
    {
        $query = trim($this->searchQuery);
        if (empty($query)) {
            $this->searchResults = [];
            return;
        }

        $this->isSearching = true;

        try {
            $this->searchResults = $kbService->searchSimilarChunks($query, 5, 0.25);
        } catch (\Exception $e) {
            $this->dispatch('notify', '⚠️ Vektorsuche Fehler: ' . $e->getMessage());
        } finally {
            $this->isSearching = false;
        }
    }

    public function deleteDocument(string $id)
    {
        $doc = KnowledgeDocument::find($id);
        if ($doc) {
            if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                Storage::disk('public')->delete($doc->file_path);
            }
            $doc->delete();
            $this->dispatch('notify', '🗑️ Dokument, Datei und Vektor-Chunks gelöscht.');
        }
    }

    public function seedSampleKnowledge(KnowledgeBaseService $kbService)
    {
        try {
            $kbService->storeDocument(
                'DIN 18533-1: Abdichtung von erdberührten Bauteilen',
                'DIN-Normen',
                "Die DIN 18533 regelt die Abdichtung von erdberührten Bauteilen (Bodenplatten, Kellerwände).\n\nBei Wassert Einwirkungsklassen (W1.1-E mäßige Einwirkung von Bodenfeuchte) sind Bitumenbahnen mit einer Mindestüberlappung von 10 cm an den Stößen zu verlegen. Die Verklebung hat im Schweiß- oder Kaltselbstklebeverfahren zu erfolgen.\n\nBei Übergängen an Wand-Sohlen-Anschlüssen ist eine Dichtungskehle (Hohlkehle) mit einem Radius von mindestens 4 bis 5 cm anzulegen, um Spannungsrisse in den Abdichtungslagen zu vermeiden."
            );

            $kbService->storeDocument(
                'BT Bautechnik - Sicherheitsvorschriften auf der Baustelle',
                'Arbeitsschutz',
                "Auf allen Baustellen der BT Bautechnik UG gilt strenge PSA-Pflicht (Persönliche Schutzausrüstung): Sicherheitsschuhe S3, Schutzhelm und Warnweste.\n\nBei Arbeiten in Höhen ab 2,00 Metern sind Absturzsicherungen (Gerüste, Seitenschutz oder Anschlageinrichtungen) zwingend erforderlich.\n\nBeinaheunfälle und Beschädigungen von Leitungen sind unverzüglich dem zuständigen Bauleiter sowie im System als Mangel zu melden."
            );

            $this->dispatch('notify', '🚀 Beispiel-Wissen (DIN 18533 & Sicherheitsvorschriften) erfolgreich angelegt!');
        } catch (\Exception $e) {
            $this->dispatch('notify', '⚠️ Fehler beim Anlegen des Beispielwissens: ' . $e->getMessage());
        }
    }

    public function with()
    {
        return [
            'documents' => KnowledgeDocument::withCount('chunks')->orderBy('created_at', 'desc')->get(),
            'totalChunks' => KnowledgeChunk::count(),
        ];
    }
}; ?>

<div class="space-y-6 font-sans">
    <!-- Header Card -->
    <div class="bg-gradient-to-r from-slate-950 via-indigo-950 to-blue-950 text-white rounded-2xl p-6 shadow-xl border border-indigo-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">📚</span>
                <h2 class="text-xl font-black tracking-tight">Firmen-Wissensdatenbank & Vektor-Suche</h2>
            </div>
            <p class="text-xs text-slate-300 font-medium">PDF/TXT Upload • Automatic Text Extraction • OpenAI Embeddings (text-embedding-3-small, 1536 Dimensionen)</p>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="seedSampleKnowledge" 
                    class="px-3.5 py-2.5 bg-slate-900/80 hover:bg-slate-800 text-slate-300 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                <span>🌱</span>
                <span>Beispielwissen laden</span>
            </button>

            <button wire:click="openModal" 
                    class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer">
                <span>📄 PDF / Text hochladen</span>
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-bold">📄</div>
            <div>
                <div class="text-xs font-bold text-slate-400 uppercase">Dokumente & PDFs</div>
                <div class="text-lg font-black text-slate-900">{{ $documents->count() }}</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-bold">🧠</div>
            <div>
                <div class="text-xs font-bold text-slate-400 uppercase">Vektor-Chunks</div>
                <div class="text-lg font-black text-slate-900">{{ $totalChunks }}</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-bold">🎯</div>
            <div>
                <div class="text-xs font-bold text-slate-400 uppercase">Embedding Modell</div>
                <div class="text-xs font-black text-blue-700">OpenAI 1536-dim</div>
            </div>
        </div>
    </div>

    <!-- Vector Search Test Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <span>🔍 Live Vektor-Semantik-Suche testen</span>
            </h3>
            <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">Cosine Similarity</span>
        </div>

        <form wire:submit="testVectorSearch" class="flex items-center gap-3">
            <input wire:model="searchQuery" type="text" 
                   class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none"
                   placeholder="Stellen Sie eine semantische Fachfrage (z. B. 'Welche Überlappung gilt nach DIN 18533?')...">

            <button type="submit" wire:loading.attr="disabled" 
                    class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                <span wire:loading.remove wire:target="testVectorSearch">🔍 Vektoren durchsuchen</span>
                <span wire:loading wire:target="testVectorSearch" class="flex items-center gap-2">
                    <span class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                    <span>Berechne...</span>
                </span>
            </button>
        </form>

        @if (!empty($searchResults))
            <div class="space-y-3 pt-2">
                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                    Gefundene Chunks (Sortiert nach Vektor-Ähnlichkeit):
                </div>
                <div class="space-y-2.5">
                    @foreach ($searchResults as $res)
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-900">{{ $res['document_title'] }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-200">
                                    Relevanz: {{ round($res['similarity'] * 100) }}%
                                </span>
                            </div>
                            <p class="text-xs text-slate-700 leading-relaxed font-mono bg-white p-2.5 rounded-lg border border-slate-200/80">
                                {{ $res['content'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Documents List -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900">Gespeicherte Dokumente & PDFs</h3>
            <span class="text-xs text-slate-500">{{ $documents->count() }} Einträge</span>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($documents as $doc)
                <div x-data="{ expanded: false }" class="p-5 hover:bg-slate-50/60 transition">
                    <div class="flex items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2.5">
                                <span class="font-extrabold text-sm text-slate-900">{{ $doc->title }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                    {{ $doc->category }}
                                </span>
                                @if ($doc->file_path)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 border border-blue-200 flex items-center gap-1">
                                        📄 original PDF/Datei
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 flex items-center gap-3">
                                <span>Erstellt: {{ $doc->created_at->format('d.m.Y H:i') }}</span>
                                <span>•</span>
                                <span class="font-semibold text-blue-700">{{ $doc->chunks_count }} Chunks (OpenAI Vektoren)</span>
                                @if ($doc->file_path)
                                    <span>•</span>
                                    <a href="{{ Storage::disk('public')->url($doc->file_path) }}" target="_blank" class="text-blue-600 hover:underline font-bold flex items-center gap-1">
                                        📥 Datei anzeigen
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="expanded = !expanded" 
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer">
                                <span x-text="expanded ? 'Chunks verbergen ▲' : 'Chunks anzeigen ▼'"></span>
                            </button>

                            <button wire:click="deleteDocument('{{ $doc->id }}')" 
                                    wire:confirm="Möchten Sie dieses Dokument und alle Vektor-Chunks wirklich löschen?"
                                    class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-lg transition cursor-pointer">
                                🗑️ Löschen
                            </button>
                        </div>
                    </div>

                    <!-- Expandable Chunks Display -->
                    <div x-show="expanded" x-collapse class="mt-4 pt-4 border-t border-slate-200/80 space-y-2">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Generierte Vektor-Chunks:</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            @foreach ($doc->chunks as $c)
                                <div class="p-3 bg-slate-900 text-slate-200 rounded-xl text-3xs font-mono space-y-1">
                                    <div class="text-blue-400 font-bold flex justify-between">
                                        <span>Chunk #{{ $c->chunk_index }}</span>
                                        <span>1536 Floats</span>
                                    </div>
                                    <p class="text-slate-300 leading-relaxed font-sans text-xs">
                                        {{ $c->content }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-slate-400 space-y-3">
                    <div class="text-3xl">📚</div>
                    <p class="text-xs font-semibold">Noch keine Wissensdokumente hinterlegt.</p>
                    <p class="text-xs text-slate-500">Klicken Sie oben auf "PDF / Text hochladen" oder "Beispielwissen laden".</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal: Add Document or Upload PDF/TXT -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                <div class="p-5 bg-gradient-to-r from-slate-950 to-blue-950 text-white flex justify-between items-center">
                    <h3 class="font-bold text-sm flex items-center gap-2">
                        <span>📚 Dokument oder PDF/TXT hochladen</span>
                    </h3>
                    <button wire:click="closeModal" class="text-slate-400 hover:text-white font-bold text-sm cursor-pointer">✕</button>
                </div>

                <form wire:submit="saveDocument" class="p-6 space-y-4">
                    <!-- File Upload Input -->
                    <div class="p-4 bg-blue-50/60 border-2 border-dashed border-blue-200 rounded-2xl text-center space-y-2">
                        <div class="text-2xl">📄</div>
                        <div class="text-xs font-bold text-blue-950">PDF-, TXT-, MD- oder CSV-Datei hochladen</div>
                        <p class="text-[11px] text-slate-500">Der Text wird automatisch extrahiert, gechenkt und als OpenAI Vektor-Embeddings indiziert.</p>
                        
                        <input wire:model="uploadedFile" type="file" accept=".pdf,.txt,.md,.csv,.json"
                               class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                        @error('uploadedFile') <span class="text-rose-600 text-xs font-semibold block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Titel des Dokuments</label>
                        <input wire:model="title" type="text" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none"
                               placeholder="z. B. DIN 18533 Abdichtung von erdberührten Bauteilen" required>
                        @error('title') <span class="text-rose-600 text-xs font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kategorie</label>
                        <select wire:model="category" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none">
                            <option value="DIN-Normen">DIN-Normen & Baurecht</option>
                            <option value="Arbeitsschutz">Arbeitsschutz & Sicherheit</option>
                            <option value="Firmenwissen">BT Bautechnik Firmenwissen</option>
                            <option value="Verträge">Musterverträge & AGB</option>
                            <option value="Sonstiges">Sonstiges Fachwissen</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Oder manueller Volltext (Optional bei Datei-Upload)</label>
                        <textarea wire:model="content" rows="5" 
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3.5 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none font-mono"
                                  placeholder="Falls Sie keine Datei hochladen, geben Sie den Volltext hier manuell ein..."></textarea>
                        @error('content') <span class="text-rose-600 text-xs font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closeModal" 
                                class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition cursor-pointer">
                            Abbrechen
                        </button>

                        <button type="submit" wire:loading.attr="disabled" 
                                class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 transition flex items-center gap-2 cursor-pointer">
                            <span wire:loading.remove wire:target="saveDocument">🧠 Vektorieren & Speichern</span>
                            <span wire:loading wire:target="saveDocument" class="flex items-center gap-2">
                                <span class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                <span>Extrahiere & Vektoriere...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
