<?php

use Livewire\Volt\Component;
use App\Models\SubcontractorInvoice;
use App\Models\Project;
use App\Models\Contact;
use App\Models\ActualCost;

new class extends Component {
    public bool $showModal = false;

    // Form
    public string $projectId = '';
    public string $contactId = '';
    public string $invoiceNumber = '';
    public string $invoiceDate = '';
    public float $amountNet = 0.0;
    public string $taxMode = '13b'; // 13b, 19, 0
    public string $status = 'in_review';
    public string $description = '';

    public function mount()
    {
        $this->invoiceDate = date('Y-m-d');
        $p = Project::first();
        if ($p) $this->projectId = $p->id;
    }

    public function getInvoicesProperty()
    {
        return SubcontractorInvoice::with(['project', 'contact'])->latest()->get();
    }

    public function getProjectsProperty()
    {
        return Project::all();
    }

    public function getSubcontractorsProperty()
    {
        return Contact::where('type', 'subunternehmer')->get();
    }

    public function openCreateModal()
    {
        $this->invoiceNumber = 'RE-SUB-' . rand(1000, 9999);
        $this->amountNet = 0.0;
        $this->description = '';
        $this->showModal = true;
    }

    public function updateStatus(string $id, string $newStatus)
    {
        $inv = SubcontractorInvoice::findOrFail($id);
        $inv->update(['status' => $newStatus]);

        // Automatically log as actual cost when approved or paid!
        if ($newStatus === 'approved' || $newStatus === 'paid') {
            ActualCost::firstOrCreate([
                'project_id' => $inv->project_id,
                'description' => 'Subunternehmer Re-Nr: ' . $inv->invoice_number,
            ], [
                'type' => 'subcontractor',
                'subcontractor_name' => $inv->contact?->display_name ?: 'Subunternehmer',
                'cost_amount' => $inv->amount_net,
                'date' => $inv->invoice_date,
            ]);
        }

        $this->dispatch('notify', 'Status der Eingangsrechnung aktualisiert!');
    }

    public function saveInvoice()
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'invoiceNumber' => 'required|string',
            'amountNet' => 'required|numeric|min:0.01',
            'description' => 'required|string',
        ]);

        $inv = SubcontractorInvoice::create([
            'project_id' => $this->projectId,
            'contact_id' => $this->contactId ?: null,
            'invoice_number' => $this->invoiceNumber,
            'invoice_date' => $this->invoiceDate,
            'amount_net' => $this->amountNet,
            'tax_mode' => $this->taxMode,
            'status' => $this->status,
            'description' => $this->description,
        ]);

        if ($this->status === 'approved' || $this->status === 'paid') {
            ActualCost::create([
                'project_id' => $inv->project_id,
                'type' => 'subcontractor',
                'subcontractor_name' => $inv->contact?->display_name ?: 'Subunternehmer',
                'cost_amount' => $inv->amount_net,
                'description' => 'Subunternehmer Re-Nr: ' . $inv->invoice_number . ' (' . $inv->description . ')',
                'date' => $inv->invoice_date,
            ]);
        }

        $this->showModal = false;
        $this->dispatch('notify', 'Eingangsrechnung erfolgreich erfasst!');
    }
}; ?>

<div class="space-y-8 font-sans">
    <!-- Header -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Subunternehmer & Baukosten (§13b UStG)</h2>
            <p class="text-xs text-slate-500">Prüfung, Steuernachweise & Rechnungsfreigabe von Fremdleistungen und Material.</p>
        </div>

        <button wire:click="openCreateModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10 whitespace-nowrap">
            + Eingangsrechnung erfassen
        </button>
    </div>

    <!-- Invoices Directory -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase">
                    <tr>
                        <th class="p-4">Rechnungs-Nr / Datum</th>
                        <th class="p-4">Subunternehmer</th>
                        <th class="p-4">Baustelle</th>
                        <th class="p-4">Steuer-Modus</th>
                        <th class="p-4 text-right">Netto-Betrag</th>
                        <th class="p-4">Status & Freigabe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse ($this->invoices as $inv)
                        <tr wire:key="{{ $inv->id }}" class="hover:bg-slate-50/80 transition">
                            <td class="p-4">
                                <p class="font-bold text-slate-900">{{ $inv->invoice_number }}</p>
                                <p class="text-[10px] text-slate-400">{{ date('d.m.Y', strtotime($inv->invoice_date)) }}</p>
                            </td>
                            <td class="p-4">
                                <p class="font-bold text-slate-900">{{ $inv->contact?->display_name ?: 'Direkter Beleg' }}</p>
                                <p class="text-[10px] text-slate-500">{{ $inv->contact?->vat_id ?: 'Keine USt-ID' }}</p>
                            </td>
                            <td class="p-4 text-slate-800 font-semibold">
                                {{ $inv->project->name }}
                            </td>
                            <td class="p-4">
                                @if ($inv->tax_mode === '13b')
                                    <span class="px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-full font-bold text-[10px]">
                                        §13b Reverse Charge
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 border border-slate-200 rounded-full text-[10px]">
                                        Standard USt
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right font-extrabold text-slate-900 text-sm">
                                {{ number_format($inv->amount_net, 2, ',', '.') }} €
                            </td>
                            <td class="p-4">
                                <select wire:change="updateStatus('{{ $inv->id }}', $event.target.value)" class="text-xs font-bold rounded-lg px-2.5 py-1 border border-slate-200 focus:outline-none {{ $inv->status_badge_class }}">
                                    <option value="in_review" {{ $inv->status === 'in_review' ? 'selected' : '' }}>⏳ In Prüfung</option>
                                    <option value="approved" {{ $inv->status === 'approved' ? 'selected' : '' }}>✅ Freigegeben</option>
                                    <option value="paid" {{ $inv->status === 'paid' ? 'selected' : '' }}>💶 Bezahlt</option>
                                    <option value="rejected" {{ $inv->status === 'rejected' ? 'selected' : '' }}>❌ Abgelehnt</option>
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500 italic">Noch keine Eingangsrechnungen erfasst.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Modal -->
    @if ($showModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Eingangsrechnung / Baukosten erfassen</h3>
                    <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <form wire:submit="saveInvoice" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Baustelle</label>
                        <select wire:model="projectId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                            @foreach ($this->projects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subunternehmer / Partner</label>
                        <select wire:model="contactId" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                            <option value="">Direkter Beleg / Kein Subunternehmer</option>
                            @foreach ($this->subcontractors as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->display_name }} ({{ $sub->vat_id ?: 'Keine USt-ID' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Rechnungs-Nr</label>
                            <input wire:model="invoiceNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Rechnungs-Datum</label>
                            <input wire:model="invoiceDate" type="date" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Netto-Betrag (€)</label>
                            <input wire:model="amountNet" type="number" step="0.01" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Steuer-Regelung</label>
                            <select wire:model="taxMode" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                                <option value="13b">§13b Reverse Charge (Bauleistung)</option>
                                <option value="19">19% Standard USt</option>
                                <option value="0">0% Steuerbefreit / Kleinunternehmer</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Leistungsbeschreibung / Gewerk</label>
                        <input wire:model="description" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600" placeholder="z. B. Flachdach Abdichtung Abschnitt 2" required>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Beleg buchen</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
