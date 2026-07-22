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

require __DIR__.'/auth.php';
