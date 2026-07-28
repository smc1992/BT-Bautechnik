<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds for Contacts.
     */
    public function run(): void
    {
        $contacts = [
            [
                'customer_number' => 'KD-2026-0001',
                'type' => 'hausverwaltung',
                'company_name' => 'Immo Köhler GmbH & Co. KG',
                'salutation' => 'Herr',
                'first_name' => 'Markus',
                'last_name' => 'Köhler',
                'email' => 'verwaltung@immo-koehler.de',
                'phone' => '0842 49356890',
                'mobile' => '0171 4820193',
                'street' => 'Ingolstädter Straße 11',
                'zip' => '85092',
                'city' => 'Kösching',
                'vat_id' => 'DE314892015',
                'notes' => 'Verwaltung für WEG Ingolstädter Str. 11-11c und WEG Pfaffenhofener Str. 10.',
            ],
            [
                'customer_number' => 'KD-2026-0002',
                'type' => 'bautraeger',
                'company_name' => 'Pfeifer & Perrine Bauberatung GmbH',
                'salutation' => 'Herr',
                'first_name' => 'Alexander',
                'last_name' => 'Pfeifer',
                'email' => 'kontakt@pfeifer-perrine.de',
                'phone' => '0176 92476566',
                'mobile' => '0176 92476566',
                'street' => 'Ingolstädter Straße 5c',
                'zip' => '85092',
                'city' => 'Kösching',
                'vat_id' => 'DE295710483',
                'notes' => 'Generalplaner und Bauträger für Tiefgaragenrampe WGB 11c.',
            ],
            [
                'customer_number' => 'KD-2026-0003',
                'type' => 'privatkunde',
                'company_name' => '',
                'salutation' => 'Herr',
                'first_name' => 'Jürgen',
                'last_name' => 'Baumgärtner',
                'email' => 'j.baumgaertner@gmx.de',
                'phone' => '0172 4410142',
                'mobile' => '0172 4410142',
                'street' => 'Wackenstraße 5c',
                'zip' => '85051',
                'city' => 'Ingolstadt',
                'vat_id' => '',
                'notes' => 'Eigentümer Baustelle Wackenstraße 5c Kellerwand-Teilabdichtung.',
            ],
            [
                'customer_number' => 'KD-2026-0004',
                'type' => 'privatkunde',
                'company_name' => '',
                'salutation' => 'Herr & Frau',
                'first_name' => 'Claudia und Peter',
                'last_name' => 'Reagan',
                'email' => 'p.reagan@t-online.de',
                'phone' => '0841 38123244',
                'mobile' => '0170 9182304',
                'street' => 'Kaltnerstraße 10a',
                'zip' => '85055',
                'city' => 'Ingolstadt',
                'vat_id' => '',
                'notes' => 'Auftraggeber Kaltnerstraße 10a (Kellersanierung / Undichter Keller).',
            ],
            [
                'customer_number' => 'KD-2026-0005',
                'type' => 'hausverwaltung',
                'company_name' => 'Immo Regler Hausverwaltung GmbH',
                'salutation' => 'Herr',
                'first_name' => 'Stefan',
                'last_name' => 'Regler',
                'email' => 'verwaltung@immo-regler.de',
                'phone' => '0841 959760',
                'mobile' => '0172 8593021',
                'street' => 'Reichenhaustraße 10',
                'zip' => '85055',
                'city' => 'Ingolstadt',
                'vat_id' => 'DE128593021',
                'notes' => 'Hausverwaltung für WGB Reichenhaustraße 10 (Teilfugensanierung).',
            ],
            [
                'customer_number' => 'KD-2026-0006',
                'type' => 'privatkunde',
                'company_name' => '',
                'salutation' => 'Herr',
                'first_name' => 'Christian',
                'last_name' => 'Dexl',
                'email' => 'christian.dexl@web.de',
                'phone' => '0841 78003',
                'mobile' => '0175 4910283',
                'street' => 'Am Damm 12',
                'zip' => '85051',
                'city' => 'Ingolstadt',
                'vat_id' => '',
                'notes' => 'Auftraggeber Am Damm 12 (Sanierungsarbeiten & Kellertüre erneuern).',
            ],
            [
                'customer_number' => 'KD-2026-0007',
                'type' => 'subunternehmer',
                'company_name' => 'Meier Bausanierung & Injektionstechnik GmbH',
                'salutation' => 'Herr',
                'first_name' => 'Thomas',
                'last_name' => 'Meier',
                'email' => 'info@meier-bausanierung.de',
                'phone' => '0841 629103',
                'mobile' => '0171 9301928',
                'street' => 'Gewerbestraße 4',
                'zip' => '85053',
                'city' => 'Ingolstadt',
                'vat_id' => 'DE274019482',
                'notes' => 'Fachnachunternehmer für Druckinjektion und Betonsanierung (§ 13b UStG).',
            ],
            [
                'customer_number' => 'KD-2026-0008',
                'type' => 'subunternehmer',
                'company_name' => 'Wagner Gerüstbau & Spezialisolierung KGaA',
                'salutation' => 'Herr',
                'first_name' => 'Klaus',
                'last_name' => 'Wagner',
                'email' => 'dispo@wagner-geruestbau.de',
                'phone' => '08456 91280',
                'mobile' => '0173 8492019',
                'street' => 'Handwerkerpark 8',
                'zip' => '85098',
                'city' => 'Großmehring',
                'vat_id' => 'DE193850193',
                'notes' => 'Subunternehmer für Fassadengerüstung und Spezialdämmung.',
            ],
        ];

        foreach ($contacts as $cData) {
            $existing = Contact::where('email', $cData['email'])
                ->orWhere('customer_number', $cData['customer_number'])
                ->first();

            if ($existing) {
                $existing->update($cData);
                $this->command->info("Kunde '{$cData['customer_number']} - {$cData['first_name']} {$cData['last_name']}' aktualisiert.");
            } else {
                Contact::create($cData);
                $this->command->info("✅ Kunde '{$cData['customer_number']} - {$cData['first_name']} {$cData['last_name']}' geseeded.");
            }
        }
    }
}
