<?php

use App\Models\Contact;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;

test('a complete demo request is stored before showing success', function () {
    Volt::test('landing-page')
        ->call('openDemoModal')
        ->set('demoName', 'Demo Bauleitung')
        ->set('demoCompany', 'Demo Baubetrieb')
        ->set('demoEmail', 'demo@example.test')
        ->set('demoPhone', '0000000000')
        ->call('submitDemoRequest')
        ->assertHasNoErrors()
        ->assertSet('demoSuccess', true);

    $this->assertDatabaseHas('contacts', [
        'company_name' => 'Demo Baubetrieb',
        'email' => 'demo@example.test',
    ]);
});

test('a demo request with a missing email stays in the form', function () {
    Volt::test('landing-page')
        ->call('openDemoModal')
        ->set('demoName', 'Demo Bauleitung')
        ->set('demoCompany', 'Demo Baubetrieb')
        ->set('demoPhone', '0000000000')
        ->call('submitDemoRequest')
        ->assertHasErrors(['demoEmail' => 'required'])
        ->assertSet('demoSuccess', false)
        ->assertSet('showDemoModal', true);

    $this->assertDatabaseCount('contacts', 0);
});

test('a failed demo request shows an error instead of a success confirmation', function () {
    $event = 'eloquent.creating: '.Contact::class;
    Event::listen($event, function () {
        throw new RuntimeException('Simulated storage failure');
    });

    try {
        Volt::test('landing-page')
            ->call('openDemoModal')
            ->set('demoName', 'Demo Bauleitung')
            ->set('demoCompany', 'Demo Baubetrieb')
            ->set('demoEmail', 'demo@example.test')
            ->set('demoPhone', '0000000000')
            ->call('submitDemoRequest')
            ->assertHasErrors(['demoRequest'])
            ->assertSet('demoSuccess', false)
            ->assertSet('showDemoModal', true)
            ->assertSee('Ihre Anfrage konnte nicht gespeichert werden.');

        $this->assertDatabaseCount('contacts', 0);
    } finally {
        Event::forget($event);
    }
});
