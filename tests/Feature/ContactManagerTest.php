<?php

use App\Models\User;
use App\Models\Contact;
use Livewire\Volt\Volt;

test('contacts page can be rendered for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/kontakte');

    $response->assertStatus(200);
});

test('contact manager component can create a hausverwaltung contact', function () {
    $user = User::factory()->create();

    Volt::test('contact-manager')
        ->set('type', 'hausverwaltung')
        ->set('companyName', 'Müller Hausverwaltung GmbH')
        ->set('firstName', 'Hans')
        ->set('lastName', 'Müller')
        ->set('email', 'info@mueller-hv.de')
        ->set('city', 'Ingolstadt')
        ->call('saveContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('contacts', [
        'type' => 'hausverwaltung',
        'company_name' => 'Müller Hausverwaltung GmbH',
        'city' => 'Ingolstadt',
    ]);
});

test('contact manager filters contacts by type', function () {
    Contact::create(['type' => 'hausverwaltung', 'company_name' => 'HV Super GmbH']);
    Contact::create(['type' => 'subunternehmer', 'company_name' => 'Sub Dichtung UG']);

    Volt::test('contact-manager')
        ->set('activeTypeFilter', 'hausverwaltung')
        ->assertSee('HV Super GmbH')
        ->assertDontSee('Sub Dichtung UG');
});
