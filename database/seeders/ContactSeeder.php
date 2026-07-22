<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contact;
use App\Models\Project;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $hv = Contact::create([
            'type' => 'hausverwaltung',
            'company_name' => 'Ingolstädter Hausverwaltung GmbH',
            'salutation' => 'Herr',
            'first_name' => 'Markus',
            'last_name' => 'Huber',
            'email' => 'huber@ingolstadt-hv.de',
            'phone' => '0841 9876543',
            'street' => 'Münchner Straße 45',
            'zip' => '85051',
            'city' => 'Ingolstadt',
            'vat_id' => 'DE123456789',
            'notes' => 'Zuständig für WEG Ingolstädter Str. 11 - 11c',
        ]);

        $bautraeger = Contact::create([
            'type' => 'bautraeger',
            'company_name' => 'Pfeifer & Perrine Bauträger UG',
            'salutation' => 'Frau',
            'first_name' => 'Julia',
            'last_name' => 'Perrine',
            'email' => 'perrine@pfeifer-perrine.de',
            'phone' => '08456 123456',
            'street' => 'Industriestraße 12',
            'zip' => '85092',
            'city' => 'Kösching',
            'vat_id' => 'DE987654321',
            'notes' => 'Tiefgaragensanierung & Bauträgerprojekte',
        ]);

        $sub = Contact::create([
            'type' => 'subunternehmer',
            'company_name' => 'Bausanierung Hofbauer GmbH',
            'salutation' => 'Herr',
            'first_name' => 'Samir',
            'last_name' => 'Hofbauer',
            'email' => 'hofbauer@bausanierung-hofbauer.de',
            'phone' => '08421 554433',
            'street' => 'Handwerkerstraße 8',
            'zip' => '85072',
            'city' => 'Eichstätt',
            'vat_id' => 'DE312984712',
            'notes' => 'Subunternehmer für Abdichtungen & Abbrucharbeiten (Abrechnung nach §13b UStG)',
        ]);

        $kunde = Contact::create([
            'type' => 'kunde',
            'company_name' => '',
            'salutation' => 'Herr',
            'first_name' => 'Michael',
            'last_name' => 'Meier',
            'email' => 'm.meier@gmx.de',
            'phone' => '0171 9988776',
            'street' => 'Hauptstraße 22',
            'zip' => '92334',
            'city' => 'Berching',
            'notes' => 'Privatkunde Treppenaufgang Sanierung',
        ]);

        // Link existing projects to contacts
        $p1 = Project::where('name', 'LIKE', '%Ingolstädter Str. 11%')->first();
        if ($p1) {
            $p1->update(['contact_id' => $hv->id]);
        }

        $p2 = Project::where('name', 'LIKE', '%Pfeifer%')->first();
        if ($p2) {
            $p2->update(['contact_id' => $bautraeger->id]);
        }
    }
}
