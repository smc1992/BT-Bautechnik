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

<div class="space-y-8 font-sans">
    <!-- Header -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Firmen-Einstellungen & Stammdaten</h2>
            <p class="text-xs text-slate-500">Zentrale Firmendaten, Bankverbindung, USt-ID & Briefkopf-Standardtexte für Angebote und Rechnungen.</p>
        </div>

        <button wire:click="saveSettings" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/10">
            Speichern & Aktualisieren
        </button>
    </div>

    <!-- Form Grid -->
    <form wire:submit="saveSettings" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Firmendaten Box -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3 flex items-center gap-2">
                🏢 Allgemeine Firmendaten
            </h3>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Firmenbezeichnung</label>
                <input wire:model="companyName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Geschäftsführer / Vertretungsberechtigt</label>
                <input wire:model="managingDirector" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Straße & Hausnummer</label>
                <input wire:model="street" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">PLZ</label>
                    <input wire:model="zip" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ort</label>
                    <input wire:model="city" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Telefon</label>
                    <input wire:model="phone" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">E-Mail</label>
                    <input wire:model="email" type="email" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
                </div>
            </div>
        </div>

        <!-- Finanz- & Bankverbindung -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3 flex items-center gap-2">
                💶 Finanzdaten & Bankverbindung
            </h3>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Bankinstitut</label>
                <input wire:model="bankName" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">IBAN</label>
                <input wire:model="iban" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">BIC / SWIFT</label>
                <input wire:model="bic" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Steuernummer</label>
                    <input wire:model="taxNumber" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">USt-IdNr.</label>
                    <input wire:model="vatId" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 font-mono focus:bg-white focus:border-blue-600">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Handelsregister (HRB)</label>
                <input wire:model="commercialRegister" type="text" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-sm text-slate-900 focus:bg-white focus:border-blue-600">
            </div>
        </div>

        <!-- Briefkopf Standardtexte -->
        <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3 flex items-center gap-2">
                📝 Standardtexte & Zahlungsbedingungen
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Zahlungsbedingungen (Standard)</label>
                    <textarea wire:model="defaultPaymentTerms" rows="4" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Einleitungssatz Angebote</label>
                    <textarea wire:model="defaultOfferText" rows="4" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Schlusssatz Rechnungen</label>
                    <textarea wire:model="defaultInvoiceText" rows="4" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:border-blue-600"></textarea>
                </div>
            </div>
        </div>
    </form>
</div>
