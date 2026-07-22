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

require __DIR__.'/auth.php';
