<?php

use Livewire\Volt\Component;
use App\Models\Contact;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $search = '';
    public string $activeTypeFilter = 'all'; // all, kunde, hausverwaltung, bautraeger, subunternehmer
    
    // Create/Edit Modal states
    public bool $showContactModal = false;
    public ?string $editingContactId = null;

    // Detail Modal states
    public bool $showDetailModal = false;
    public ?string $selectedContactId = null;
    public string $activeDetailTab = 'overview'; // overview, projects, invoices, offers, baukosten

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
        return Contact::with(['projects', 'invoices', 'offers', 'actualCosts'])
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

    public function getSelectedContactProperty()
    {
        if (!$this->selectedContactId) return null;
        return Contact::with(['projects', 'invoices', 'offers', 'actualCosts'])->find($this->selectedContactId);
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

    public function openDetailModal(string $id)
    {
        $this->selectedContactId = $id;
        $this->activeDetailTab = 'overview';
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedContactId = null;
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
        if ($this->selectedContactId === $id) {
            $this->closeDetailModal();
        }
        $this->dispatch('notify', 'Kontakt gelöscht.');
    }
}; ?>

<div class="space-y-8 font-sans">
    <!-- Header Actions & Search Bar -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Kunden, Hausverwaltungen & Partner</h2>
            <p class="text-xs text-slate-500">Zentrale Verwaltung aller Auftraggeber, Bauträger, Subunternehmer und Betriebe mit Baustellenverknüpfung.</p>
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
                            <h3 wire:click="openDetailModal('{{ $contact->id }}')" class="text-base font-bold text-slate-900 mt-2 tracking-tight hover:text-blue-600 cursor-pointer line-clamp-1">
                                {{ $contact->display_name }}
                            </h3>
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
                    </div>

                    <!-- Linked Projects Summary -->
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
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button wire:click="openDetailModal('{{ $contact->id }}')" class="px-3 py-1.5 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition border border-blue-200/60 flex items-center gap-1">
                        🔍 Details
                    </button>

                    <div class="flex items-center gap-2">
                        <button wire:click="openEditModal('{{ $contact->id }}')" class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                            Bearbeiten
                        </button>
                        <button wire:click="deleteContact('{{ $contact->id }}')" wire:confirm="Kontakt wirklich löschen?" class="px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg transition">
                            Löschen
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white border border-slate-200/80 rounded-2xl text-center space-y-3">
                <p class="text-base font-bold text-slate-900">Keine Kontakte gefunden</p>
                <p class="text-xs text-slate-500">Legen Sie über den Button "+ Neu anlegen" Ihren ersten Kunden oder Hausverwaltung an.</p>
            </div>
        @endforelse
    </div>

    <!-- CONTACT DETAIL VIEW MODAL (DETAILANSICHT) -->
    @if ($showDetailModal && $this->selectedContact)
        @php $c = $this->selectedContact; @endphp
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                
                <!-- Modal Header -->
                <div class="p-6 bg-slate-900 text-white flex justify-between items-start relative overflow-hidden">
                    <div class="space-y-2 relative z-10">
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold uppercase bg-white/10 text-white backdrop-blur-md border border-white/20">
                                {{ $c->type_label }}
                            </span>
                            @if ($c->vat_id)
                                <span class="text-xs font-mono text-slate-300">USt-ID: {{ $c->vat_id }}</span>
                            @endif
                        </div>
                        <h2 class="text-2xl font-black text-white tracking-tight">{{ $c->display_name }}</h2>
                        <p class="text-xs text-slate-300 flex items-center gap-3">
                            @if ($c->first_name || $c->last_name)
                                <span>👤 {{ $c->salutation }} {{ $c->first_name }} {{ $c->last_name }}</span>
                            @endif
                            @if ($c->city)
                                <span>📍 {{ $c->street }}, {{ $c->zip }} {{ $c->city }}</span>
                            @endif
                        </p>
                    </div>

                    <!-- Action buttons & Close -->
                    <div class="flex items-center gap-2 relative z-10">
                        <button wire:click="openEditModal('{{ $c->id }}')" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl transition border border-white/20">
                            ✏️ Bearbeiten
                        </button>
                        <button wire:click="closeDetailModal" class="p-2 text-slate-400 hover:text-white rounded-full bg-white/10 hover:bg-white/20 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Quick KPI Summary Strip -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-slate-50 border-b border-slate-200">
                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Verknüpfte Baustellen</span>
                        <p class="text-lg font-black text-slate-900 mt-0.5">{{ $c->projects->count() }}</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Rechnungen (Gesamt)</span>
                        <p class="text-lg font-black text-blue-600 mt-0.5">{{ number_format($c->invoices->sum('total_net'), 2, ',', '.') }} €</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Angebote</span>
                        <p class="text-lg font-black text-slate-900 mt-0.5">{{ $c->offers->count() }}</p>
                    </div>
                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-2xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Fremdleistung / Baukosten</span>
                        <p class="text-lg font-black text-purple-600 mt-0.5">{{ number_format($c->actualCosts->sum('amount'), 2, ',', '.') }} €</p>
                    </div>
                </div>

                <!-- Detail Tabs Navigation -->
                <div class="flex border-b border-slate-200 bg-white px-6">
                    <button wire:click="$set('activeDetailTab', 'overview')" class="py-3.5 px-4 text-xs font-bold border-b-2 transition {{ $activeDetailTab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                        📋 Stammdaten & Kontakt
                    </button>
                    <button wire:click="$set('activeDetailTab', 'projects')" class="py-3.5 px-4 text-xs font-bold border-b-2 transition flex items-center gap-1.5 {{ $activeDetailTab === 'projects' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                        🏢 Baustellen & Projekte <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700">{{ $c->projects->count() }}</span>
                    </button>
                    <button wire:click="$set('activeDetailTab', 'invoices')" class="py-3.5 px-4 text-xs font-bold border-b-2 transition flex items-center gap-1.5 {{ $activeDetailTab === 'invoices' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                        📄 Rechnungen <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700">{{ $c->invoices->count() }}</span>
                    </button>
                    <button wire:click="$set('activeDetailTab', 'offers')" class="py-3.5 px-4 text-xs font-bold border-b-2 transition flex items-center gap-1.5 {{ $activeDetailTab === 'offers' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                        📑 Angebote <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700">{{ $c->offers->count() }}</span>
                    </button>
                    @if ($c->type === 'subunternehmer')
                        <button wire:click="$set('activeDetailTab', 'baukosten')" class="py-3.5 px-4 text-xs font-bold border-b-2 transition flex items-center gap-1.5 {{ $activeDetailTab === 'baukosten' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                            🛠️ Baukosten (§13b) <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700">{{ $c->actualCosts->count() }}</span>
                        </button>
                    @endif
                </div>

                <!-- Tab Contents Container -->
                <div class="p-6 overflow-y-auto flex-1 space-y-6">

                    <!-- TAB 1: STAMMDATEN & KONTAKT -->
                    @if ($activeDetailTab === 'overview')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Master Data Box -->
                            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Kontaktdaten & Kommunikation</h4>
                                
                                <div class="space-y-3 text-xs">
                                    <div>
                                        <span class="text-slate-400 font-medium block">Firma / Unternehmen:</span>
                                        <span class="font-bold text-slate-900 text-sm">{{ $c->company_name ?: '— (Privatperson)' }}</span>
                                    </div>

                                    <div>
                                        <span class="text-slate-400 font-medium block">Ansprechpartner:</span>
                                        <span class="font-bold text-slate-900">{{ $c->salutation }} {{ $c->first_name }} {{ $c->last_name }}</span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200/60">
                                        <div>
                                            <span class="text-slate-400 font-medium block">E-Mail:</span>
                                            @if ($c->email)
                                                <a href="mailto:{{ $c->email }}" class="font-bold text-blue-600 hover:underline block truncate">{{ $c->email }}</a>
                                            @else
                                                <span class="text-slate-400 italic">Nicht angegeben</span>
                                            @endif
                                        </div>

                                        <div>
                                            <span class="text-slate-400 font-medium block">Telefon Festnetz:</span>
                                            @if ($c->phone)
                                                <a href="tel:{{ $c->phone }}" class="font-bold text-slate-900 hover:underline">{{ $c->phone }}</a>
                                            @else
                                                <span class="text-slate-400 italic">Nicht angegeben</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200/60">
                                        <div>
                                            <span class="text-slate-400 font-medium block">Mobiltelefon:</span>
                                            @if ($c->mobile)
                                                <a href="tel:{{ $c->mobile }}" class="font-bold text-slate-900 hover:underline">{{ $c->mobile }}</a>
                                            @else
                                                <span class="text-slate-400 italic">Nicht angegeben</span>
                                            @endif
                                        </div>

                                        <div>
                                            <span class="text-slate-400 font-medium block">USt-IdNr. / Steuernummer:</span>
                                            <span class="font-mono text-slate-900 font-bold">{{ $c->vat_id ?: 'Keine angegeben' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Address & Notes Box -->
                            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Anschrift & Notizen</h4>
                                
                                <div class="space-y-3 text-xs">
                                    <div>
                                        <span class="text-slate-400 font-medium block">Anschrift:</span>
                                        <p class="font-bold text-slate-900 mt-0.5">
                                            {{ $c->street ?: 'Keine Straße angegeben' }}<br>
                                            {{ $c->zip }} {{ $c->city }}
                                        </p>
                                        @if ($c->street && $c->city)
                                            <a href="https://maps.google.com/?q={{ urlencode($c->street . ', ' . $c->zip . ' ' . $c->city) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-blue-600 font-bold hover:underline mt-1">
                                                🗺️ In Google Maps öffnen ↗
                                            </a>
                                        @endif
                                    </div>

                                    <div class="pt-3 border-t border-slate-200/60">
                                        <span class="text-slate-400 font-medium block mb-1">Interne Notizen & Anmerkungen:</span>
                                        <div class="bg-white p-3 rounded-xl border border-slate-200 text-slate-700 italic min-h-20 text-xs leading-relaxed">
                                            {{ $c->notes ?: 'Keine Notizen zu diesem Kontakt hinterlegt.' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 2: BAUSTELLEN & PROJEKTE -->
                    @if ($activeDetailTab === 'projects')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Verknüpfte Baustellen ({{ $c->projects->count() }})</h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @forelse ($c->projects as $project)
                                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 space-y-2">
                                        <div class="flex justify-between items-start">
                                            <h5 class="text-sm font-bold text-slate-900 line-clamp-1">{{ $project->name }}</h5>
                                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-100 text-blue-800">
                                                {{ $project->status ?? 'In Ausführung' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-500">📍 {{ $project->location ?? 'Kein Ort hinterlegt' }}</p>
                                        <div class="flex justify-between items-center text-xs pt-2 border-t border-slate-200/60">
                                            <span class="text-slate-500">Soll-Budget:</span>
                                            <span class="font-bold text-slate-900">{{ number_format($project->planned_budget ?? 0, 2, ',', '.') }} €</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500 italic col-span-full py-8 text-center bg-slate-50 rounded-2xl border border-slate-200/60">
                                        Keine Baustellen mit diesem Kontakt verknüpft.
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <!-- TAB 3: RECHNUNGEN -->
                    @if ($activeDetailTab === 'invoices')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Ausgangsrechnungen an {{ $c->display_name }}</h4>
                            </div>

                            <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-2xs">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                                            <th class="py-3 px-4">Rechnungs-Nr.</th>
                                            <th class="py-3 px-4">Datum</th>
                                            <th class="py-3 px-4">Typ</th>
                                            <th class="py-3 px-4 text-right">Netto (€)</th>
                                            <th class="py-3 px-4 text-right">Brutto (€)</th>
                                            <th class="py-3 px-4 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs">
                                        @forelse ($c->invoices as $inv)
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-3 px-4 font-bold text-slate-900">{{ $inv->invoice_number }}</td>
                                                <td class="py-3 px-4 text-slate-600">{{ date('d.m.Y', strtotime($inv->invoice_date)) }}</td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-100 text-slate-700">
                                                        {{ $inv->type ?? 'Abschlussrechnung' }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-right font-semibold text-slate-900">{{ number_format($inv->total_net, 2, ',', '.') }} €</td>
                                                <td class="py-3 px-4 text-right font-extrabold text-blue-600">{{ number_format($inv->total_gross, 2, ',', '.') }} €</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        Bezahlt
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-8 text-center text-xs text-slate-500 italic">
                                                    Keine Rechnungen für diesen Kontakt vorhanden.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 4: ANGEBOTE -->
                    @if ($activeDetailTab === 'offers')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Erstellte Angebote</h4>
                            </div>

                            <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-2xs">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                                            <th class="py-3 px-4">Angebots-Nr.</th>
                                            <th class="py-3 px-4">Datum</th>
                                            <th class="py-3 px-4">Status</th>
                                            <th class="py-3 px-4 text-right">Gesamt Netto (€)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs">
                                        @forelse ($c->offers as $off)
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-3 px-4 font-bold text-slate-900">{{ $off->offer_number }}</td>
                                                <td class="py-3 px-4 text-slate-600">{{ date('d.m.Y', strtotime($off->date)) }}</td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-50 text-blue-700">
                                                        {{ $off->status ?? 'Gesendet' }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-right font-extrabold text-slate-900">{{ number_format($off->total_net, 2, ',', '.') }} €</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="py-8 text-center text-xs text-slate-500 italic">
                                                    Keine Angebote für diesen Kontakt vorhanden.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- TAB 5: BAUKOSTEN / SUBUNTERNEHMER -->
                    @if ($activeDetailTab === 'baukosten' && $c->type === 'subunternehmer')
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Eingangsrechnungen & Baukosten (§13b)</h4>
                            </div>

                            <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-2xs">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                                            <th class="py-3 px-4">Rechnungs-Nr.</th>
                                            <th class="py-3 px-4">Datum</th>
                                            <th class="py-3 px-4">Gewerk / Leistung</th>
                                            <th class="py-3 px-4 text-right">Betrag (€)</th>
                                            <th class="py-3 px-4 text-center">§ 13b UStG</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs">
                                        @forelse ($c->actualCosts as $cost)
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-3 px-4 font-bold text-slate-900">{{ $cost->invoice_number ?? '—' }}</td>
                                                <td class="py-3 px-4 text-slate-600">{{ date('d.m.Y', strtotime($cost->cost_date)) }}</td>
                                                <td class="py-3 px-4 text-slate-800 font-medium">{{ $cost->description }}</td>
                                                <td class="py-3 px-4 text-right font-extrabold text-purple-700">{{ number_format($cost->amount, 2, ',', '.') }} €</td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md {{ $cost->is_reverse_charge ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-600' }}">
                                                        {{ $cost->is_reverse_charge ? 'Ja (§13b)' : 'Nein' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-8 text-center text-xs text-slate-500 italic">
                                                    Keine Fremdleistungs-Rechnungen für diesen Subunternehmer erfasst.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button wire:click="closeDetailModal" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-xs">
                        Schließen
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- Create / Edit Contact Modal -->
    @if ($showContactModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-xl shadow-2xl overflow-hidden">
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
