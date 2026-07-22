<?php

use Livewire\Volt\Component;
use App\Models\Contact;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $search = '';
    public string $activeTypeFilter = 'all'; // all, kunde, hausverwaltung, bautraeger, subunternehmer
    
    // Modal states
    public bool $showContactModal = false;
    public ?string $editingContactId = null;

    // Form fields
    public string $type = 'kunde';
    public string $companyName = '';
    public string $salutation = 'Herr';
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $phone = '';
    public string $mobile = '';
    public string $street = '';
    public string $zip = '';
    public string $city = '';
    public string $vatId = '';
    public string $notes = '';

    public function getContactsProperty()
    {
        return Contact::with(['projects'])
            ->when($this->activeTypeFilter !== 'all', fn($q) => $q->where('type', $this->activeTypeFilter))
            ->when(!empty($this->search), function($q) {
                $term = '%' . $this->search . '%';
                $q->where(function($sub) use ($term) {
                    $sub->where('company_name', 'LIKE', $term)
                        ->orWhere('first_name', 'LIKE', $term)
                        ->orWhere('last_name', 'LIKE', $term)
                        ->orWhere('city', 'LIKE', $term)
                        ->orWhere('email', 'LIKE', $term);
                });
            })
            ->latest()
            ->get();
    }

    public function getCountsProperty()
    {
        return [
            'all' => Contact::count(),
            'kunde' => Contact::where('type', 'kunde')->count(),
            'hausverwaltung' => Contact::where('type', 'hausverwaltung')->count(),
            'bautraeger' => Contact::where('type', 'bautraeger')->count(),
            'subunternehmer' => Contact::where('type', 'subunternehmer')->count(),
        ];
    }

    public function setFilter(string $filter)
    {
        $this->activeTypeFilter = $filter;
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->editingContactId = null;
        $this->showContactModal = true;
    }

    public function openEditModal(string $id)
    {
        $contact = Contact::findOrFail($id);
        $this->editingContactId = $contact->id;
        $this->type = $contact->type;
        $this->companyName = $contact->company_name ?? '';
        $this->salutation = $contact->salutation ?? 'Herr';
        $this->firstName = $contact->first_name ?? '';
        $this->lastName = $contact->last_name ?? '';
        $this->email = $contact->email ?? '';
        $this->phone = $contact->phone ?? '';
        $this->mobile = $contact->mobile ?? '';
        $this->street = $contact->street ?? '';
        $this->zip = $contact->zip ?? '';
        $this->city = $contact->city ?? '';
        $this->vatId = $contact->vat_id ?? '';
        $this->notes = $contact->notes ?? '';

        $this->showContactModal = true;
    }

    public function resetForm()
    {
        $this->type = 'kunde';
        $this->companyName = '';
        $this->salutation = 'Herr';
        $this->firstName = '';
        $this->lastName = '';
        $this->email = '';
        $this->phone = '';
        $this->mobile = '';
        $this->street = '';
        $this->zip = '';
        $this->city = '';
        $this->vatId = '';
        $this->notes = '';
    }

    public function saveContact()
    {
        $this->validate([
            'type' => 'required|in:kunde,hausverwaltung,bautraeger,subunternehmer',
            'email' => 'nullable|email',
        ]);

        $data = [
            'type' => $this->type,
            'company_name' => $this->companyName,
            'salutation' => $this->salutation,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'vat_id' => $this->vatId,
            'notes' => $this->notes,
        ];

        if ($this->editingContactId) {
            Contact::where('id', $this->editingContactId)->update($data);
            $msg = 'Kontakt erfolgreich aktualisiert!';
        } else {
            Contact::create($data);
            $msg = 'Neuer Kontakt erfolgreich angelegt!';
        }

        $this->showContactModal = false;
        $this->dispatch('notify', $msg);
    }

    public function deleteContact(string $id)
    {
        Contact::destroy($id);
        $this->dispatch('notify', 'Kontakt gelöscht.');
    }
}; ?>

<div class="space-y-8 font-sans">
    <!-- Header Actions & Search Bar -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Kunden, Hausverwaltungen & Partner</h2>
            <p class="text-xs text-slate-500">Zentrale Verwaltung aller Auftraggeber, Bauträger, Subunternehmer und Betriebe.</p>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="relative w-full md:w-64">
                <input wire:model.live.debounce.250ms="search" type="text" 
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:outline-none"
                       placeholder="Suchen nach Name, Firma, Ort...">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <button wire:click="openCreateModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10 whitespace-nowrap">
                + Neu anlegen
            </button>
        </div>
    </div>

    <!-- Category Filter Chips -->
    <div class="flex flex-wrap items-center gap-2">
        <button wire:click="setFilter('all')" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 {{ $activeTypeFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
            Alle Kontakte
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'all' ? 'bg-slate-800 text-slate-200' : 'bg-slate-100 text-slate-600' }}">{{ $this->counts['all'] }}</span>
        </button>

        <button wire:click="setFilter('hausverwaltung')" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 {{ $activeTypeFilter === 'hausverwaltung' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
            🏢 Hausverwaltungen
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'hausverwaltung' ? 'bg-indigo-700 text-indigo-100' : 'bg-indigo-50 text-indigo-700' }}">{{ $this->counts['hausverwaltung'] }}</span>
        </button>

        <button wire:click="setFilter('bautraeger')" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 {{ $activeTypeFilter === 'bautraeger' ? 'bg-cyan-600 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
            🏗️ Bauträger
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'bautraeger' ? 'bg-cyan-700 text-cyan-100' : 'bg-cyan-50 text-cyan-700' }}">{{ $this->counts['bautraeger'] }}</span>
        </button>

        <button wire:click="setFilter('kunde')" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 {{ $activeTypeFilter === 'kunde' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
            👤 Privatkunden
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'kunde' ? 'bg-blue-700 text-blue-100' : 'bg-blue-50 text-blue-700' }}">{{ $this->counts['kunde'] }}</span>
        </button>

        <button wire:click="setFilter('subunternehmer')" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition shadow-2xs flex items-center gap-2 {{ $activeTypeFilter === 'subunternehmer' ? 'bg-purple-600 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
            🛠️ Subunternehmer
            <span class="px-2 py-0.5 rounded-full text-[10px] {{ $activeTypeFilter === 'subunternehmer' ? 'bg-purple-700 text-purple-100' : 'bg-purple-50 text-purple-700' }}">{{ $this->counts['subunternehmer'] }}</span>
        </button>
    </div>

    <!-- Contacts Cards Directory -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($this->contacts as $contact)
            <div wire:key="{{ $contact->id }}" class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase border shadow-2xs {{ $contact->type_badge_class }}">
                                {{ $contact->type_label }}
                            </span>
                            <h3 class="text-base font-bold text-slate-900 mt-2 tracking-tight line-clamp-1">{{ $contact->display_name }}</h3>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-xs text-slate-600 font-medium">
                        @if ($contact->first_name || $contact->last_name)
                            <p class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $contact->salutation }} {{ $contact->first_name }} {{ $contact->last_name }}
                            </p>
                        @endif

                        @if ($contact->email)
                            <p class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <a href="mailto:{{ $contact->email }}" class="text-blue-600 hover:underline">{{ $contact->email }}</a>
                            </p>
                        @endif

                        @if ($contact->phone || $contact->mobile)
                            <p class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                {{ $contact->phone ?: $contact->mobile }}
                            </p>
                        @endif

                        @if ($contact->street || $contact->city)
                            <p class="flex items-center gap-2 text-slate-500">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $contact->street }} {{ $contact->zip }} {{ $contact->city }}
                            </p>
                        @endif

                        @if ($contact->vat_id)
                            <p class="text-[11px] text-slate-400 font-mono pt-1">
                                USt-ID: {{ $contact->vat_id }}
                            </p>
                        @endif
                    </div>

                    <!-- Linked Projects -->
                    <div class="pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-semibold">Baustellen:</span>
                            <span class="font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded-md">
                                {{ $contact->projects->count() }} Verknüpft
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                    <button wire:click="openEditModal('{{ $contact->id }}')" class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                        Bearbeiten
                    </button>
                    <button wire:click="deleteContact('{{ $contact->id }}')" wire:confirm="Kontakt wirklich löschen?" class="px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg transition">
                        Löschen
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/80 rounded-2xl text-center space-y-3">
                <p class="text-base font-bold text-slate-900">Keine Kontakte gefunden</p>
                <p class="text-xs text-slate-500">Legen Sie über den Button "+ Neu anlegen" Ihren ersten Kunden oder Hausverwaltung an.</p>
            </div>
        @endforelse
    </div>

    <!-- Create / Edit Contact Modal -->
    @if ($showContactModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ $editingContactId ? 'Kontakt bearbeiten' : 'Neuen Kontakt / Auftraggeber anlegen' }}
                    </h3>
                    <button wire:click="$set('showContactModal', false)" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <form wire:submit="saveContact" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Typ / Kategorie</label>
                        <select wire:model="type" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none">
                            <option value="kunde">👤 Privatkunde</option>
                            <option value="hausverwaltung">🏢 Hausverwaltung (WEG)</option>
                            <option value="bautraeger">🏗️ Bauträger / Bauunternehmen</option>
                            <option value="subunternehmer">🛠️ Subunternehmer / Partner (§13b)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Firmenname / Bezeichnung</label>
                        <input wire:model="companyName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="z. B. Ingolstädter Hausverwaltung GmbH">
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Anrede</label>
                            <select wire:model="salutation" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none">
                                <option value="Herr">Herr</option>
                                <option value="Frau">Frau</option>
                                <option value="Firma">Firma</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Vorname</label>
                            <input wire:model="firstName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Max">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nachname</label>
                            <input wire:model="lastName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Mustermann">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">E-Mail</label>
                            <input wire:model="email" type="email" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="info@beispiel.de">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Telefon</label>
                            <input wire:model="phone" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="0841 123456">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Straße & Hausnummer</label>
                        <input wire:model="street" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Münchner Str. 10">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">PLZ</label>
                            <input wire:model="zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="85051">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ort</label>
                            <input wire:model="city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Ingolstadt">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">USt-IdNr. / Steuernummer (§13b)</label>
                        <input wire:model="vatId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="DE123456789">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Notizen</label>
                        <textarea wire:model="notes" rows="3" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none" placeholder="Zusätzliche Infos, Ansprechpartner etc..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-200">
                        <button type="button" wire:click="$set('showContactModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">Abbrechen</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/10">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
