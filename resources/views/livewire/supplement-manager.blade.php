<?php

use App\Models\Supplement;
use App\Models\Project;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all'; // all, draft, submitted, approved, rejected, billed
    public string $projectFilter = 'all';

    // Create / Edit Modal State
    public bool $showModal = false;
    public ?string $editingId = null;

    public string $projectId = '';
    public string $supplementNumber = '';
    public string $title = '';
    public ?string $description = '';
    public string $reason = 'scope_change'; // scope_change, unforeseen, client_request, obstruction
    public float $amountNet = 0.00;
    public float $vatRate = 19.00;
    public string $status = 'draft';
    public ?string $submissionDate = null;
    public ?string $approvalDate = null;
    public ?string $notes = '';

    public function mount(): void
    {
        $this->submissionDate = date('Y-m-d');
    }

    public function with(): array
    {
        $query = Supplement::with('project');

        if ($this->search) {
            $query->where(function($q) {
                $q->where('supplement_number', 'like', '%' . $this->search . '%')
                  ->orWhere('title', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->projectFilter !== 'all') {
            $query->where('project_id', $this->projectFilter);
        }

        $allSupplements = Supplement::all();
        $stats = [
            'total_count' => $allSupplements->count(),
            'approved_count' => $allSupplements->where('status', 'approved')->count(),
            'approved_volume' => $allSupplements->where('status', 'approved')->sum('amount_gross'),
            'pending_volume' => $allSupplements->whereIn('status', ['draft', 'submitted'])->sum('amount_gross'),
        ];

        return [
            'supplements' => $query->orderBy('created_at', 'desc')->paginate(12),
            'projects' => Project::orderBy('name', 'asc')->get(),
            'stats' => $stats,
        ];
    }

    public function openCreateModal(?string $defaultProjectId = null): void
    {
        $this->reset(['editingId', 'description', 'notes', 'approvalDate']);
        $this->projectId = $defaultProjectId ?: (Project::first()?->id ?? '');
        $this->submissionDate = date('Y-m-d');
        $this->amountNet = 0.00;
        $this->vatRate = 19.00;
        $this->status = 'draft';
        $this->reason = 'scope_change';

        // Auto-generate supplement number (e.g. NT-01)
        $count = Supplement::where('project_id', $this->projectId)->count() + 1;
        $this->supplementNumber = 'NT-' . str_pad((string)$count, 2, '0', STR_PAD_LEFT);
        $this->title = 'Nachtrag ' . $this->supplementNumber;

        $this->showModal = true;
    }

    public function openEditModal(string $id): void
    {
        $supplement = Supplement::findOrFail($id);
        $this->editingId = $supplement->id;
        $this->projectId = $supplement->project_id;
        $this->supplementNumber = $supplement->supplement_number;
        $this->title = $supplement->title;
        $this->description = $supplement->description;
        $this->reason = $supplement->reason;
        $this->amountNet = (float) $supplement->amount_net;
        $this->vatRate = (float) $supplement->vat_rate;
        $this->status = $supplement->status;
        $this->submissionDate = $supplement->submission_date ? $supplement->submission_date->format('Y-m-d') : null;
        $this->approvalDate = $supplement->approval_date ? $supplement->approval_date->format('Y-m-d') : null;
        $this->notes = $supplement->notes;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'supplementNumber' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'amountNet' => 'required|numeric|min:0',
            'vatRate' => 'required|numeric|min:0',
            'status' => 'required|string',
        ]);

        $amountGross = $this->amountNet * (1 + ($this->vatRate / 100));

        $data = [
            'project_id' => $this->projectId,
            'supplement_number' => $this->supplementNumber,
            'title' => $this->title,
            'description' => $this->description,
            'reason' => $this->reason,
            'amount_net' => $this->amountNet,
            'vat_rate' => $this->vatRate,
            'amount_gross' => $amountGross,
            'status' => $this->status,
            'submission_date' => $this->submissionDate,
            'approval_date' => $this->status === 'approved' ? ($this->approvalDate ?: date('Y-m-d')) : null,
            'notes' => $this->notes,
        ];

        if ($this->editingId) {
            Supplement::where('id', $this->editingId)->update($data);
            $this->dispatch('notify', 'Nachtrag erfolgreich aktualisiert!');
        } else {
            Supplement::create($data);
            $this->dispatch('notify', 'Neuer Nachtrag erfolgreich angelegt!');
        }

        $this->showModal = false;
    }

    public function updateStatus(string $id, string $newStatus): void
    {
        $supplement = Supplement::findOrFail($id);
        $supplement->status = $newStatus;
        if ($newStatus === 'approved' && !$supplement->approval_date) {
            $supplement->approval_date = now();
        }
        $supplement->save();

        $this->dispatch('notify', 'Status auf "' . ucfirst($newStatus) . '" gesetzt.');
    }

    public function delete(string $id): void
    {
        Supplement::destroy($id);
        $this->dispatch('notify', 'Nachtrag gelöscht.');
    }
}; ?>

<div class="space-y-6 font-sans">
    
    <!-- Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 text-white rounded-2xl p-6 shadow-xl border border-indigo-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 mb-2">
                <span>VOB/B § 2 Abs. 5 & 6</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white flex items-center gap-2.5">
                <span>📑 Nachtragsmanagement & Leistungsänderungen</span>
            </h1>
            <p class="text-xs text-slate-300 mt-1">Rechtssichere Erfassung von Mehrkosten, Behinderungsanzeigen und Bauherren-Nachträgen.</p>
        </div>

        <button wire:click="openCreateModal" 
                class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-extrabold text-xs rounded-xl shadow-md shadow-indigo-500/20 transition flex items-center gap-2 cursor-pointer btn-press">
            <span>➕ Neuer Nachtrag</span>
        </button>
    </div>

    <!-- KPI Summary Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamt Nachträge</p>
            <p class="text-2xl font-black text-slate-900 mt-1 tabular-nums">{{ $stats['total_count'] }}</p>
        </div>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Genehmigt</p>
            <p class="text-2xl font-black text-emerald-600 mt-1 tabular-nums">{{ $stats['approved_count'] }}</p>
        </div>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Genehmigtes Volumen</p>
            <p class="text-2xl font-black text-slate-900 mt-1 tabular-nums">{{ number_format($stats['approved_volume'], 2, ',', '.') }} €</p>
        </div>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Offenes Volumen (In Prüfung)</p>
            <p class="text-2xl font-black text-amber-600 mt-1 tabular-nums">{{ number_format($stats['pending_volume'], 2, ',', '.') }} €</p>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
            <input wire:model.live.debounce.150ms="search" 
                   type="text" 
                   placeholder="🔍 Nachtragsnr., Titel oder Beschreibung suchen..." 
                   class="w-full sm:w-72 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-medium focus:bg-white focus:border-indigo-600 focus:outline-none">

            <select wire:model.live="statusFilter" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 cursor-pointer">
                <option value="all">Alle Status</option>
                <option value="draft">Entwurf</option>
                <option value="submitted">Eingereicht / In Prüfung</option>
                <option value="approved">Genehmigt</option>
                <option value="rejected">Abgelehnt</option>
                <option value="billed">In Rechnung gestellt</option>
            </select>

            <select wire:model.live="projectFilter" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-600 cursor-pointer">
                <option value="all">Alle Baustellen ({{ count($projects) }})</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Supplements Table Directory -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs divide-y divide-slate-100">
                <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="p-3.5">Nachtrags-Nr. & Titel</th>
                        <th class="p-3.5">Baustelle</th>
                        <th class="p-3.5">Begründung</th>
                        <th class="p-3.5 text-right">Netto</th>
                        <th class="p-3.5 text-right">Brutto</th>
                        <th class="p-3.5 text-center">Status</th>
                        <th class="p-3.5 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse ($supplements as $sup)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="p-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md font-mono text-[11px] font-black bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        {{ $sup->supplement_number }}
                                    </span>
                                    <span class="font-extrabold text-slate-900">{{ $sup->title }}</span>
                                </div>
                                @if ($sup->description)
                                    <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ $sup->description }}</p>
                                @endif
                            </td>
                            <td class="p-3.5 text-slate-700">
                                <span class="font-bold">📍 {{ $sup->project?->name ?: 'Keine Baustelle' }}</span>
                            </td>
                            <td class="p-3.5 text-slate-600">
                                @php
                                    $reasonLabel = match($sup->reason) {
                                        'scope_change' => 'Leistungsänderung',
                                        'unforeseen' => 'Unvorhersehbare Bausituation',
                                        'client_request' => 'Bauherrenwunsch',
                                        'obstruction' => 'Behinderungsanzeige',
                                        default => 'Sonstiges',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">{{ $reasonLabel }}</span>
                            </td>
                            <td class="p-3.5 text-right font-mono font-bold text-slate-900 tabular-nums">
                                {{ number_format($sup->amount_net, 2, ',', '.') }} €
                            </td>
                            <td class="p-3.5 text-right font-mono font-extrabold text-indigo-900 tabular-nums">
                                {{ number_format($sup->amount_gross, 2, ',', '.') }} €
                            </td>
                            <td class="p-3.5 text-center">
                                @php
                                    $statusBadge = match($sup->status) {
                                        'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
                                        'submitted' => 'bg-amber-100 text-amber-800 border-amber-200',
                                        'approved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                        'rejected' => 'bg-rose-100 text-rose-800 border-rose-200',
                                        'billed' => 'bg-blue-100 text-blue-800 border-blue-200',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                    $statusName = match($sup->status) {
                                        'draft' => 'Entwurf',
                                        'submitted' => 'Eingereicht',
                                        'approved' => 'Genehmigt',
                                        'rejected' => 'Abgelehnt',
                                        'billed' => 'Abgerechnet',
                                        default => $sup->status,
                                    };
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase border {{ $statusBadge }}">
                                    {{ $statusName }}
                                </span>
                            </td>
                            <td class="p-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="/nachtraege/{{ $sup->id }}/pdf" target="_blank" title="Nachtragsangebot PDF exportieren" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-lg border border-indigo-200 cursor-pointer btn-press">
                                        📄 PDF
                                    </a>
                                    @if ($sup->status !== 'approved')
                                        <button wire:click="updateStatus('{{ $sup->id }}', 'approved')" title="Als genehmigt markieren" class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-lg border border-emerald-200 cursor-pointer btn-press">
                                            ✓ Freigabe
                                        </button>
                                    @endif
                                    <button wire:click="openEditModal('{{ $sup->id }}')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg cursor-pointer btn-press">
                                        ✏️ Bearbeiten
                                    </button>
                                    <button wire:click="delete('{{ $sup->id }}')" wire:confirm="Nachtrag wirklich löschen?" class="px-2 py-1 text-rose-600 hover:bg-rose-50 rounded-lg cursor-pointer btn-press">
                                        ✕
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 space-y-2">
                                <div class="text-2xl">📑</div>
                                <p class="font-bold">Keine Nachträge gefunden</p>
                                <p class="text-xs text-slate-400">Erfassen Sie neue Leistungsänderungen oder Mehrkosten.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $supplements->links() }}
        </div>
    </div>

    <!-- Create / Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-xl w-full shadow-2xl border border-slate-200 space-y-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-lg font-black text-slate-900">
                        {{ $editingId ? 'Nachtrag bearbeiten' : 'Neuen VOB/B Nachtrag anlegen' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">✕</button>
                </div>

                <form wire:submit="save" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Baustelle *</label>
                        <select wire:model="projectId" class="w-full bg-white border border-slate-300 rounded-xl p-2.5 font-bold text-slate-900 shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nachtrags-Nr. *</label>
                            <input wire:model="supplementNumber" type="text" placeholder="z.B. NT-01" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Begründung (VOB/B)</label>
                            <select wire:model="reason" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-semibold shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                                <option value="scope_change">Leistungsänderung (§ 2 Abs. 5)</option>
                                <option value="unforeseen">Unvorhergesehenes (§ 2 Abs. 6)</option>
                                <option value="client_request">Bauherren-Zusatzwunsch</option>
                                <option value="obstruction">Behinderungsanzeige / Mehraufwand</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Titel / Bezeichnung *</label>
                        <input wire:model="title" type="text" placeholder="z.B. Zusätzliche Hohlkehlenausbildung TG-Rampe" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Leistungsbeschreibung / Begründungstext</label>
                        <textarea wire:model="description" rows="3" placeholder="Detaillierte Beschreibung der Mehrleistung gemäß VOB..." class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-medium shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nettobetrag (€) *</label>
                            <input wire:model="amountNet" type="number" step="0.01" class="w-full bg-white border border-slate-300 rounded-xl p-2.5 font-bold text-slate-900 tabular-nums shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">MwSt.-Satz (%)</label>
                            <input wire:model="vatRate" type="number" step="0.5" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold tabular-nums shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Status</label>
                            <select wire:model="status" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-bold shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                                <option value="draft">Entwurf</option>
                                <option value="submitted">Eingereicht / In Prüfung</option>
                                <option value="approved">Genehmigt</option>
                                <option value="rejected">Abgelehnt</option>
                                <option value="billed">In Rechnung gestellt</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Einreichungsdatum</label>
                            <input wire:model="submissionDate" type="date" class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl p-2.5 font-semibold shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold rounded-xl shadow-md shadow-indigo-500/20 cursor-pointer btn-press">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
