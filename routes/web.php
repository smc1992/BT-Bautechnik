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

Route::view('einstellungen', 'einstellungen')
    ->middleware(['auth', 'verified'])
    ->name('company-settings');

Route::view('ki-agent', 'ki-agent')
    ->middleware(['auth', 'verified'])
    ->name('ai-agent');

Route::view('wissen', 'wissen')
    ->middleware(['auth', 'verified'])
    ->name('knowledge-base');

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

require __DIR__.'/auth.php';
