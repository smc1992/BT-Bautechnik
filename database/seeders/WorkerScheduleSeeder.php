<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Contact;
use App\Models\WorkerSchedule;
use Illuminate\Database\Seeder;

class WorkerScheduleSeeder extends Seeder
{
    /**
     * Seed initial worker schedules.
     */
    public function run(): void
    {
        $projects = Project::take(4)->get();
        if ($projects->isEmpty()) return;

        $subcontractors = Contact::where('type', 'subunternehmer')->get();
        $sub1 = $subcontractors->first();
        $sub2 = $subcontractors->skip(1)->first();

        // Current week Monday - Friday dates
        $monday = date('Y-m-d', strtotime('monday this week'));
        $tuesday = date('Y-m-d', strtotime('tuesday this week'));
        $wednesday = date('Y-m-d', strtotime('wednesday this week'));
        $thursday = date('Y-m-d', strtotime('thursday this week'));
        $friday = date('Y-m-d', strtotime('friday this week'));

        $schedules = [
            [
                'project_id' => $projects[0]->id,
                'worker_name' => 'Klaus Eder (Obermonteur)',
                'worker_type' => 'mitarbeiter',
                'date' => $monday,
                'shift_type' => 'ganztags',
                'notes' => 'Vorbereitung Baustelleneinrichtung & Materialanlieferung',
            ],
            [
                'project_id' => $projects[0]->id,
                'worker_name' => 'Stefan Meier (Monteur)',
                'worker_type' => 'mitarbeiter',
                'date' => $monday,
                'shift_type' => 'ganztags',
                'notes' => 'Untergrundreinigung & Kanten abkleben',
            ],
            [
                'project_id' => $projects[0]->id,
                'contact_id' => $sub1?->id,
                'worker_name' => $sub1 ? $sub1->display_name : 'Meier Bausanierung GmbH',
                'worker_type' => 'subunternehmer',
                'date' => $tuesday,
                'shift_type' => 'ganztags',
                'notes' => 'Druckinjektion Kellerwand Rissverpressung',
            ],
            [
                'project_id' => $projects[1]->id ?? $projects[0]->id,
                'worker_name' => 'Klaus Eder (Obermonteur)',
                'worker_type' => 'mitarbeiter',
                'date' => $wednesday,
                'shift_type' => 'ganztags',
                'notes' => 'Bitumen-Dichtanstrich & Hohlkehlenspachtelung',
            ],
            [
                'project_id' => $projects[1]->id ?? $projects[0]->id,
                'contact_id' => $sub2?->id,
                'worker_name' => $sub2 ? $sub2->display_name : 'Wagner Gerüstbau KGaA',
                'worker_type' => 'subunternehmer',
                'date' => $thursday,
                'shift_type' => 'vormittags',
                'notes' => 'Fassadengerüst Aufbau Treppenaufgang',
            ],
            [
                'project_id' => $projects[2]->id ?? $projects[0]->id,
                'worker_name' => 'Team Bausanierung (2 Mann)',
                'worker_type' => 'mitarbeiter',
                'date' => $friday,
                'shift_type' => 'ganztags',
                'notes' => 'Abnahme & Aufräumarbeiten',
            ],
        ];

        foreach ($schedules as $data) {
            WorkerSchedule::firstOrCreate(
                [
                    'project_id' => $data['project_id'],
                    'date' => $data['date'],
                    'worker_name' => $data['worker_name'],
                ],
                $data
            );
        }
    }
}
