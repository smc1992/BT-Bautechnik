<?php

use Livewire\Volt\Component;
use App\Models\CompanySetting;

new class extends Component {
    public string $companyName = '';
    public string $managingDirector = '';
    public string $street = '';
    public string $zip = '';
    public string $city = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';
    public string $taxNumber = '';
    public string $vatId = '';
    public string $commercialRegister = '';
    public string $bankName = '';
    public string $iban = '';
    public string $bic = '';
    public string $defaultPaymentTerms = '';
    public string $defaultOfferText = '';
    public string $defaultInvoiceText = '';

    public function mount()
    {
        $settings = CompanySetting::getSettings();
        $this->companyName = $settings->company_name;
        $this->managingDirector = $settings->managing_director;
        $this->street = $settings->street;
        $this->zip = $settings->zip;
        $this->city = $settings->city;
        $this->phone = $settings->phone;
        $this->email = $settings->email;
        $this->website = $settings->website;
        $this->taxNumber = $settings->tax_number;
        $this->vatId = $settings->vat_id;
        $this->commercialRegister = $settings->commercial_register;
        $this->bankName = $settings->bank_name;
        $this->iban = $settings->iban;
        $this->bic = $settings->bic;
        $this->defaultPaymentTerms = $settings->default_payment_terms ?? '';
        $this->defaultOfferText = $settings->default_offer_text ?? '';
        $this->defaultInvoiceText = $settings->default_invoice_text ?? '';
    }

    public function saveSettings()
    {
        $settings = CompanySetting::getSettings();
        $settings->update([
            'company_name' => $this->companyName,
            'managing_director' => $this->managingDirector,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'tax_number' => $this->taxNumber,
            'vat_id' => $this->vatId,
            'commercial_register' => $this->commercialRegister,
            'bank_name' => $this->bankName,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'default_payment_terms' => $this->defaultPaymentTerms,
            'default_offer_text' => $this->defaultOfferText,
            'default_invoice_text' => $this->defaultInvoiceText,
        ]);

        $this->dispatch('notify', 'Firmen-Stammdaten erfolgreich gespeichert!');
    }
}; ?>

<div class="space-y-6 sm:space-y-8 font-sans max-w-full overflow-x-hidden">
    <!-- Header -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                <span>⚙️ Firmen-Einstellungen & Stammdaten</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium">Zentrale Firmendaten, Bankverbindung, USt-ID & Briefkopf-Standardtexte für Angebote und Rechnungen.</p>
        </div>

        <button wire:click="saveSettings" class="w-full sm:w-auto px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10 cursor-pointer transition text-center">
            💾 Speichern & Aktualisieren
        </button>
    </div>

    <!-- Form Grid -->
    <form wire:submit="saveSettings" class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
        <!-- Firmendaten Box -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-sm sm:text-base border-b border-slate-100 pb-3 flex items-center gap-2">
                🏢 Allgemeine Firmendaten
            </h3>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Firmenbezeichnung</label>
                <input wire:model="companyName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Geschäftsführer / Vertretungsberechtigt</label>
                <input wire:model="managingDirector" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Straße & Hausnummer</label>
                <input wire:model="street" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">PLZ</label>
                    <input wire:model="zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ort</label>
                    <input wire:model="city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Telefon</label>
                    <input wire:model="phone" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">E-Mail</label>
                    <input wire:model="email" type="email" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
            </div>
        </div>

        <!-- Finanz- & Bankverbindung -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-sm sm:text-base border-b border-slate-100 pb-3 flex items-center gap-2">
                💶 Finanzdaten & Bankverbindung
            </h3>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bankinstitut</label>
                <input wire:model="bankName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">IBAN</label>
                <input wire:model="iban" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">BIC / SWIFT</label>
                <input wire:model="bic" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Steuernummer</label>
                    <input wire:model="taxNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">USt-IdNr.</label>
                    <input wire:model="vatId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Handelsregister (HRB)</label>
                <input wire:model="commercialRegister" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>
        </div>

        <!-- Briefkopf Standardtexte -->
        <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-sm sm:text-base border-b border-slate-100 pb-3 flex items-center gap-2">
                📝 Standardtexte & Zahlungsbedingungen
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Zahlungsbedingungen (Standard)</label>
                    <textarea wire:model="defaultPaymentTerms" rows="4" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none transition-all" placeholder="Zahlbar innerhalb von 14 Tagen rein netto..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Einleitungssatz Angebote</label>
                    <textarea wire:model="defaultOfferText" rows="4" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none transition-all" placeholder="Wir bedanken uns für Ihre Anfrage..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Schlusssatz Rechnungen</label>
                    <textarea wire:model="defaultInvoiceText" rows="4" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600 focus:outline-none transition-all" placeholder="Vielen Dank für Ihren Auftrag..."></textarea>
                </div>
            </div>
        </div>

        <!-- OpenAI & KI-Schnittstelle Card (Mobile Responsive Layout) -->
        <div class="lg:col-span-2 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 text-white rounded-2xl p-4 sm:p-6 shadow-md space-y-3.5 border border-blue-500/20">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-2xl shrink-0">🤖</span>
                    <div>
                        <h3 class="font-black text-white text-sm sm:text-base tracking-tight">OpenAI KI-Schnittstelle & Automatische Angebotserstellung</h3>
                        <p class="text-xs text-slate-300">Intelligente KI-Strukturierung unformatierter Leistungsbeschreibungen & E-Mails.</p>
                    </div>
                </div>

                <span class="px-3 py-1.5 rounded-full text-[11px] sm:text-xs font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-2 shrink-0 whitespace-nowrap">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Aktiv & Betriebsbereit (OpenAI)</span>
                </span>
            </div>

            <p class="text-xs text-slate-300 leading-relaxed pt-2.5 border-t border-white/10">
                Der OpenAI API Key ist konfiguriert. Im Rechnungs- & Angebotsmodul (<code>/rechnungen</code>) steht Ihnen ab sofort der Button <strong>✨ KI-Textimport (OpenAI)</strong> zur Verfügung, um aus jedem Freitext oder Subunternehmer-Angebot automatisch saubere Rechnungspositionen zu generieren.
            </p>
        </div>
    </form>
</div>
