<?php

Route::get('/', function () {
    return redirect()->route('login');
});

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

Route::get('/bautagebuch/freigabe/{token}', App\Livewire\PublicDailyLogApproval::class)
    ->name('daily-log.public-approval');

Route::get('/projects/{project}/abnahmeprotokoll-pdf', function (\App\Models\Project $project) {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.abnahmeprotokoll', [
        'project' => $project,
        'defects' => $project->defects ?? collect(),
        'date' => date('d.m.Y'),
    ]);
    return $pdf->download('VOB_Abnahmeprotokoll_' . \Illuminate\Support\Str::slug($project->name) . '.pdf');
})->middleware(['auth', 'verified'])->name('project.abnahmeprotokoll-pdf');

require __DIR__.'/auth.php';
