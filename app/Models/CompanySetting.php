<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CompanySetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_name',
        'managing_director',
        'street',
        'zip',
        'city',
        'phone',
        'email',
        'website',
        'tax_number',
        'vat_id',
        'commercial_register',
        'bank_name',
        'iban',
        'bic',
        'default_payment_terms',
        'default_offer_text',
        'default_invoice_text',
    ];

    public static function getSettings(): self
    {
        $settings = self::first();
        if (!$settings) {
            $settings = self::create([
                'company_name' => 'BT Bautechnik UG (haftungsbeschränkt)',
                'managing_director' => 'Julia Haberzettel',
                'street' => 'Brunnenstraße 4',
                'zip' => '92334',
                'city' => 'Berching',
                'phone' => '0160 96275910',
                'email' => 'info@bt-bautechnik.de',
                'website' => 'www.bt-bautechnik.de',
                'tax_number' => '110/123/45678',
                'vat_id' => 'DE345678901',
                'commercial_register' => 'HRB 46210 AG Nürnberg',
                'bank_name' => 'Sparkasse Neumarkt-Parsberg',
                'iban' => 'DE89 7605 0101 0001 2345 67',
                'bic' => 'BYLADEM1NM',
                'default_payment_terms' => 'Zahlbar innerhalb von 14 Tagen rein netto ohne Abzug.',
                'default_offer_text' => 'Wir bedanken uns für Ihre Anfrage und bieten Ihnen freibleibend nachstehende Bauleistungen an.',
                'default_invoice_text' => 'Vielen Dank für Ihren Auftrag. Bitte überweisen Sie den Rechnungsbetrag unter Angabe der Rechnungsnummer.',
            ]);
        }

        return $settings;
    }
}
