<?php

use App\Models\User;
use App\Models\Project;
use Livewire\Volt\Volt;

test('all new enterprise module routes can be accessed by authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/bautagebuch')->assertStatus(200);
    $this->actingAs($user)->get('/baukosten')->assertStatus(200);
    $this->actingAs($user)->get('/maengel')->assertStatus(200);
    $this->actingAs($user)->get('/einstellungen')->assertStatus(200);
});

test('daily log component can create a log entry', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'name' => 'Test Baustelle',
        'work_type' => 'Flachdach',
    ]);

    Volt::test('daily-log-manager')
        ->set('projectId', $project->id)
        ->set('date', date('Y-m-d'))
        ->set('weather', 'Sonnig')
        ->set('workersCount', 3)
        ->set('workPerformed', 'Abdichtung verlegt')
        ->call('saveLog')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('daily_logs', [
        'project_id' => $project->id,
        'work_performed' => 'Abdichtung verlegt',
    ]);
});

test('company settings component updates company master data', function () {
    Volt::test('company-settings-manager')
        ->set('companyName', 'BT Bautechnik UG Test')
        ->set('iban', 'DE1234567890')
        ->call('saveSettings')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('company_settings', [
        'company_name' => 'BT Bautechnik UG Test',
        'iban' => 'DE1234567890',
    ]);
});
