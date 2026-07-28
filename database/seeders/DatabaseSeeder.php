<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\Budget;
use App\Models\Offer;
use App\Models\OfferSection;
use App\Models\OfferItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create/Update Default Admin Users
        User::updateOrCreate(
            ['email' => 'bt-bautechnik@gmx.de'],
            [
                'name' => 'Julia Haberzettel',
                'password' => bcrypt('BT-Bau2026#SecureAdmin!Pass'),
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@bautechnik-bt.de'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('BT-Bau2026#SecureAdmin!Pass'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Seed Detailed Materials Catalog (Juli 2026 Prices)
        $this->call(MaterialSeeder::class);

        // 3. Seed Contacts (Hausverwaltungen, Bauträger, Subunternehmer, Privatkunden)
        $this->call(ContactSeeder::class);

        // 4. Seed Real Projects from the Excel Spreadsheet
        $projectsData = [
            [
                'name' => 'WEG Ingolstädter Str. 11 - 11c',
                'zip' => '85092',
                'city_street' => 'Kösching',
                'contact_address' => 'Immo Köhler',
                'phone' => '0842 49356890',
                'work_type' => 'Flachdachsanierung/Abdichtung',
                'start_week' => 20, // 18. Mai
                'end_week' => 21, // 20. Mai
                'status' => 'active',
                'budget' => [
                    'material' => 1500.00,
                    'wage' => 4500.00,
                ]
            ],
            [
                'name' => 'Pfeifer & Perrine, Ingolstädter Straße 5c',
                'zip' => '85092',
                'city_street' => 'Kösching',
                'contact_address' => 'Pfeifer & Perrine',
                'phone' => '0176 92476566',
                'work_type' => 'Isolierabdichtung Tiefgaragenrampe WGB 11c',
                'start_week' => 21, // 24. Mai
                'end_week' => 22, // 29. Mai
                'status' => 'active',
                'budget' => [
                    'material' => 6500.00,
                    'wage' => 11000.00,
                ]
            ],
            [
                'name' => 'WEG Pfaffenhofener Str. 10',
                'zip' => '85302',
                'city_street' => 'Gerolsbach',
                'contact_address' => 'Immo Köhler',
                'phone' => '',
                'work_type' => 'Sanierungsarbeiten Treppenaufgang',
                'start_week' => 21, // 25. Mai
                'end_week' => 23, // 05. Jun
                'status' => 'active',
                'budget' => [
                    'material' => 7000.00,
                    'wage' => 8000.00,
                ]
            ],
            [
                'name' => 'Wackenstrasse 5c, 85051 Ingolstadt',
                'zip' => '85051',
                'city_street' => 'Ingolstadt',
                'contact_address' => 'Jürgen Baumgärtner',
                'phone' => '0172 4410142',
                'work_type' => 'Kellerwand-Teilabdichtung/-Sanierung',
                'start_week' => 23, // 01. Jun
                'end_week' => 24, // 12. Jun
                'status' => 'active',
                'budget' => [
                    'material' => 0.00,
                    'wage' => 0.00,
                ]
            ],
            [
                'name' => 'Reagan, Kaltnerstrasse 10a',
                'zip' => '85055',
                'city_street' => 'Ingolstadt',
                'contact_address' => 'Reagan',
                'phone' => '0841 38123244',
                'work_type' => 'Undichter Keller',
                'start_week' => 23, // 02. Jun
                'end_week' => 25, // 19. Jun
                'status' => 'active',
                'budget' => [
                    'material' => 0.00,
                    'wage' => 0.00,
                ]
            ],
            [
                'name' => 'WGB Reichenhaustrasse 10',
                'zip' => '85055',
                'city_street' => 'Ingolstadt',
                'contact_address' => 'Immo Regler',
                'phone' => '0841 959760',
                'work_type' => 'Teilfugensanierung',
                'start_week' => 23, // 05. Jun
                'end_week' => 26, // 30. Jun
                'status' => 'active',
                'budget' => [
                    'material' => 14000.00,
                    'wage' => 14000.00,
                ]
            ],
            [
                'name' => 'Dexl Christian, Am Damm 12',
                'zip' => '85051',
                'city_street' => 'Ingolstadt',
                'contact_address' => 'Christian Dexl',
                'phone' => '0841 78003',
                'work_type' => 'Sanierungs- und Abdichtungsarbeiten',
                'start_week' => 23,
                'end_week' => 25,
                'status' => 'active',
                'budget' => [
                    'material' => 5000.00,
                    'wage' => 9500.00,
                ]
            ]
        ];

        foreach ($projectsData as $data) {
            $contact = \App\Models\Contact::where('company_name', 'LIKE', '%' . $data['contact_address'] . '%')
                ->orWhere('last_name', 'LIKE', '%' . $data['contact_address'] . '%')
                ->first();

            $project = Project::firstOrCreate(
                ['name' => $data['name']],
                [
                    'contact_id' => $contact?->id,
                    'zip' => $data['zip'],
                    'city_street' => $data['city_street'],
                    'contact_address' => $data['contact_address'],
                    'phone' => $data['phone'],
                    'work_type' => $data['work_type'],
                    'start_week' => $data['start_week'],
                    'end_week' => $data['end_week'],
                    'status' => $data['status'],
                ]
            );

            // Calculate Budget Buffers (15%)
            $matBudget = $data['budget']['material'];
            $wageBudget = $data['budget']['wage'];
            $subtotal = $matBudget + $wageBudget;
            $bufferRate = 15.00;
            $bufferAmount = $subtotal * ($bufferRate / 100);
            $totalWithBuffer = $subtotal + $bufferAmount;

            Budget::updateOrCreate(
                ['project_id' => $project->id],
                [
                    'material_budget' => $matBudget,
                    'wage_budget' => $wageBudget,
                    'buffer_rate' => $bufferRate,
                    'buffer_amount' => $bufferAmount,
                    'total_with_buffer' => $totalWithBuffer,
                ]
            );

            // For Christian Dexl, also seed the Offer parsed from the PDF
            if ($data['name'] === 'Dexl Christian, Am Damm 12') {
                $offer = Offer::updateOrCreate(
                    ['offer_number' => '15262362'],
                    [
                        'project_id' => $project->id,
                        'date' => '2026-07-06',
                        'status' => 'accepted',
                        'total_net' => 3153.02,
                        'total_gross' => 3752.09,
                    ]
                );

                $section = OfferSection::firstOrCreate(
                    [
                        'offer_id' => $offer->id,
                        'title' => 'Kellertüre erneuern',
                    ],
                    [
                        'sort_order' => 1,
                    ]
                );

                $items = [
                    [
                        'pos_number' => '24',
                        'description' => "Nebenraumtüre Kunststoff\nModell Protect 03\nRAL 9016 nach außen öffnend, UG: 0,7 W/m²K\nAufpreis RAL Farbton nach Wahl",
                        'quantity' => 1.0000,
                        'unit' => 'Stk',
                        'unit_price' => 1195.0000,
                        'total_price' => 1195.00,
                    ],
                    [
                        'pos_number' => '25',
                        'description' => "Montage Nebeneingangstür Sanierung\nMontage Element nach Stand der Technik",
                        'quantity' => 6.0000,
                        'unit' => 'lfm',
                        'unit_price' => 77.3900,
                        'total_price' => 464.34,
                    ],
                    [
                        'pos_number' => '26',
                        'description' => "Nachträgliche Bauwerksabdichtung Nebeneingangstüre in Anlehnung an DIN 18533\n- Untergrund reinigen und grundieren\n- Untergrund mit Dichtspachtelmasse egalisieren\n- Rahmenanschluss mit dauerflexibeler Dichtmasse inkl. Dichtbänder",
                        'quantity' => 1.0000,
                        'unit' => 'Stk.',
                        'unit_price' => 913.3000,
                        'total_price' => 913.30,
                    ],
                    [
                        'pos_number' => '27',
                        'description' => "Stufen Betonieren\ninkl. Armierung",
                        'quantity' => 1.0000,
                        'unit' => 'Stufe',
                        'unit_price' => 265.0000,
                        'total_price' => 265.00,
                    ],
                    [
                        'pos_number' => '28',
                        'description' => "Ausbau und Verladen Fenster/Türe",
                        'quantity' => 1.0000,
                        'unit' => 'Stk',
                        'unit_price' => 163.5400,
                        'total_price' => 163.54,
                    ],
                    [
                        'pos_number' => '29',
                        'description' => "Entsorgungsfahrt -10km\ninkl. Fracht, Maut und Fahrer",
                        'quantity' => 1.0000,
                        'unit' => 'Pau',
                        'unit_price' => 57.8400,
                        'total_price' => 57.84,
                    ],
                    [
                        'pos_number' => '30',
                        'description' => "Entsorgung Nebeneingangstür",
                        'quantity' => 1.0000,
                        'unit' => 'Pau',
                        'unit_price' => 94.0000,
                        'total_price' => 94.00,
                    ]
                ];

                foreach ($items as $item) {
                    OfferItem::firstOrCreate(
                        [
                            'section_id' => $section->id,
                            'pos_number' => $item['pos_number'],
                        ],
                        [
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'unit_price' => $item['unit_price'],
                            'total_price' => $item['total_price'],
                        ]
                    );
                }
            }
        }

        // 5. Seed Knowledge Base Documents & Vectors
        $this->call(KnowledgeBaseSeeder::class);
    }
}
