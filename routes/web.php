<?php

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('landing');
})->name('home');

Route::view('software', 'landing')->name('landing');
Route::view('loesung', 'landing');


Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::view('rechnungen', 'rechnungen')
    ->middleware(['auth', 'verified'])
    ->name('invoices');

Route::view('kontakte', 'kontakte')
    ->middleware(['auth', 'verified'])
    ->name('contacts');

Route::view('bautagebuch', 'bautagebuch')
    ->middleware(['auth', 'verified'])
    ->name('daily-logs');

Route::view('baukosten', 'baukosten')
    ->middleware(['auth', 'verified'])
    ->name('subcontractor-invoices');

Route::view('maengel', 'maengel')
    ->middleware(['auth', 'verified'])
    ->name('defects');

Route::view('firmeneinstellungen', 'einstellungen')
    ->middleware(['auth', 'verified'])
    ->name('company-settings');

Route::view('einstellungen', 'einstellungen')
    ->middleware(['auth', 'verified']);

Route::view('ki-agent', 'ki-agent')
    ->middleware(['auth', 'verified'])
    ->name('ai-agent');

Route::view('wissen', 'wissen')
    ->middleware(['auth', 'verified'])
    ->name('knowledge-base');

Route::view('einsatzplan', 'einsatzplan')
    ->middleware(['auth', 'verified'])
    ->name('work-schedule');

Route::view('analytics', 'analytics')
    ->middleware(['auth', 'verified'])
    ->name('analytics');

Route::view('planung', 'planung')
    ->middleware(['auth', 'verified'])
    ->name('planning');

Route::view('materialien', 'materialien')
    ->middleware(['auth', 'verified'])
    ->name('materials');

Route::view('nachtraege', 'nachtraege')
    ->middleware(['auth', 'verified'])
    ->name('supplements');

Route::view('aufmass', 'aufmass')
    ->middleware(['auth', 'verified'])
    ->name('measurements');

Route::view('zeiterfassung', 'zeiterfassung')
    ->middleware(['auth', 'verified'])
    ->name('time-tracking');

Route::view('bauplaene', 'bauplaene')
    ->middleware(['auth', 'verified'])
    ->name('project-plans');

Route::view('geraetepark', 'geraetepark')
    ->middleware(['auth', 'verified'])
    ->name('equipment');

Route::get('/datev-export', function(\Illuminate\Http\Request $request, \App\Services\DatevExportService $service) {
    $year = $request->query('year', 'all');
    $skr = $request->query('skr', 'SKR03');
    $csv = $service->generateDatevCsv($year, $skr);
    return response($csv, 200, [
        'Content-Type' => 'text/csv; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="DATEV_Buchungsstapel_' . $skr . '_' . ($year === 'all' ? date('Y') : $year) . '.csv"',
    ]);
})->middleware(['auth', 'verified'])->name('datev.export');

Route::get('/bautagebuch/freigabe/{token}', App\Livewire\PublicDailyLogApproval::class)
    ->name('daily-log.public-approval');

Route::get('/projects/{project}/abnahmeprotokoll-pdf', function (\App\Models\Project $project) {
    $company = \App\Models\CompanySetting::getSettings();
    $project->loadMissing(['defects', 'contact']);
    $defects = $project->defects ?? collect();
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.abnahmeprotokoll', [
        'project' => $project,
        'defects' => $defects,
        'company' => $company,
        'contractorName' => $company->company_name ?? 'BT Bautechnik UG (haftungsbeschränkt)',
        'contractorRepresentative' => $company->managing_director ?? 'Bauleitung',
        'clientName' => $project->contact?->display_name ?? $project->contact_address ?? 'Kunde / Bauherr',
        'clientRepresentative' => $project->contact?->first_name ? ($project->contact->first_name . ' ' . $project->contact->last_name) : 'Bauherr / Architekt',
        'selectedSubcontractor' => null,
        'acceptanceDate' => date('Y-m-d'),
        'workScopeDescription' => $project->work_type ? ("Ausführung des Gewerks: " . $project->work_type . "\nGemäß Leistungsverzeichnis, VOB/B und anerkannten Regeln der Bautechnik.") : 'Ausführung der vertraglich vereinbarten Bau- und Sanierungsleistungen gem. Leistungsverzeichnis und VOB/B.',
        'acceptanceResult' => ($defects->where('status', '!=', 'behoben')->count() > 0) ? 'mit_vorbehalt' : 'ohne_vorbehalt',
        'defectRemediationDeadline' => date('Y-m-d', strtotime('+14 days')),
        'warrantyPeriod' => '4 Jahre nach VOB/B § 13 Abs. 4 bzw. 5 Jahre BGB § 634a',
        'notes' => '',
        'logoBase64' => null,
        'date' => date('d.m.Y'),
    ]);
    return $pdf->download('VOB_Abnahmeprotokoll_' . \Illuminate\Support\Str::slug($project->name) . '.pdf');
})->middleware(['auth', 'verified'])->name('project.abnahmeprotokoll-pdf');


Route::get('/nachtraege/{supplement}/pdf', function (\App\Models\Supplement $supplement) {
    $company = \App\Models\CompanySetting::getSettings();
    $project = $supplement->project;
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.nachtragsangebot', [
        'supplement' => $supplement,
        'project' => $project,
        'company' => $company,
    ]);
    return $pdf->download('Nachtragsangebot_' . $supplement->supplement_number . '_' . \Illuminate\Support\Str::slug($project->name) . '.pdf');
})->middleware(['auth', 'verified'])->name('supplement.pdf');

Route::get('/aufmass/{measurement}/pdf', function (\App\Models\Measurement $measurement) {
    $company = \App\Models\CompanySetting::getSettings();
    $project = $measurement->project;
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.aufmassblatt', [
        'measurement' => $measurement->load('items'),
        'project' => $project,
        'company' => $company,
    ]);
    return $pdf->download('Aufmassblatt_' . $measurement->measurement_number . '_' . \Illuminate\Support\Str::slug($project->name) . '.pdf');
})->middleware(['auth', 'verified'])->name('measurement.pdf');

require __DIR__.'/auth.php';
