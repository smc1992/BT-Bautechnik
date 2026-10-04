<?php

use App\Models\ActualCost;
use App\Models\Budget;
use App\Models\DailyLog;
use App\Models\DailyLogShare;
use App\Models\Defect;
use App\Models\Project;
use App\Models\User;
use Livewire\Volt\Volt;

function cockpitProject(string $name, float $budget = 85000, float $cost = 94000): Project
{
    $project = Project::create(['name' => $name, 'status' => 'active', 'work_type' => 'Bauarbeiten']);
    Budget::create(['project_id' => $project->id, 'material_budget' => $budget, 'total_with_buffer' => $budget]);
    ActualCost::create(['project_id' => $project->id, 'type' => 'material', 'cost_amount' => $cost, 'description' => 'Testbeleg', 'date' => today()]);
    return $project;
}

test('project workspace is protected and unknown projects return 404', function () {
    $project = cockpitProject('Testprojekt');
    $this->get(route('projects.show', $project))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/projects/missing-project')->assertNotFound();
});

test('budget overruns stay visible above one hundred percent and money is complete', function () {
    $project = cockpitProject('Projekt mit Überschreitung');
    $this->actingAs(User::factory()->create())->get(route('projects.show', ['project' => $project, 'tab' => 'kosten']))
        ->assertOk()->assertSee('110,6')->assertSee('9.000,00 € über Budget')->assertSee('94.000,00');
    $this->get(route('dashboard'))->assertOk()->assertSee('Budgetverbrauch')->assertSee('110,6% verbraucht')
        ->assertDontSee('Im Plan')->assertDontSee('Verbleibende Marge');
});

test('project tabs have direct urls and unknown tabs fall back to overview', function () {
    $project = cockpitProject('Projekt Tabs');
    $this->actingAs(User::factory()->create());
    foreach (['uebersicht', 'kosten', 'tagebuch', 'maengel', 'dokumente'] as $tab) {
        $this->get(route('projects.show', ['project' => $project, 'tab' => $tab]))->assertOk()->assertSee('Projekt Tabs');
    }
    $this->get(route('projects.show', ['project' => $project, 'tab' => 'invalid']))
        ->assertOk()->assertSee('Kostenverbrauch ist kein Leistungsfortschritt')->assertDontSee('isMaximized');
});

test('dashboard tasks exclude resolved defects and expired or approved requests', function () {
    $project = cockpitProject('Projekt Aufgaben');
    foreach (['offen' => 'Überfälliger Mangel', 'behoben' => 'Behobener Mangel', 'abgenommen' => 'Abgenommener Mangel'] as $status => $title) {
        Defect::create(['project_id' => $project->id, 'title' => $title, 'description' => 'Beschreibung', 'status' => $status, 'deadline' => today()->subDay()]);
    }
    $log = DailyLog::create(['project_id' => $project->id, 'date' => today(), 'work_performed' => 'Arbeiten erfasst', 'weather' => 'Sonnig', 'workers_count' => 2]);
    DailyLogShare::createShareToken($log);
    DailyLogShare::createShareToken($log)->update(['expires_at' => now()->subHour()]);
    DailyLogShare::createShareToken($log)->update(['status' => 'approved']);
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertSee('Heute zu erledigen')->assertSee('Überfälliger Mangel')->assertSee('3 Hinweise')
        ->assertDontSee('Behobener Mangel')->assertDontSee('Abgenommener Mangel');
});

test('unconfigured budgets do not imply progress or a healthy margin', function () {
    $project = Project::create(['name' => 'Ohne Budget', 'status' => 'active']);
    $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertOk()
        ->assertSee('Noch kein Budget erfasst')->assertSee('Budgetvergleich noch nicht verfügbar')
        ->assertSee('Nicht erfasst')->assertDontSee('Im Plan');
});

test('global search occurs once and project results link to the workspace', function () {
    $project = cockpitProject('Suchprojekt');
    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent();
    expect(substr_count($html, 'id="ui-global-search"'))->toBe(1);
    expect($html)->toContain(route('projects.show', $project));
});

test('selected project is carried into module filters and can be cleared', function () {
    $project = cockpitProject('Kontextprojekt');
    $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertOk()->assertSessionHas('ui.project_id', $project->id);
    Volt::test('plan-manager')->assertSet('projectFilter', $project->id)->call('openCreateModal')->assertSet('projectId', $project->id);
    Volt::test('measurement-manager')->assertSet('projectFilter', $project->id)->call('openCreateSheet')->assertSet('projectId', $project->id);
    Volt::test('equipment-manager')->assertSet('projectFilter', $project->id)->call('openCreateModal')->assertSet('currentProjectId', $project->id);
    Volt::test('invoice-creator')->assertSet('contextProjectId', $project->id)->assertSet('projectId', $project->id)->assertSet('activeTab', 'archive')->call('createNewInvoice')->assertSet('projectId', $project->id);
    $this->get(route('dashboard', ['clear_project' => 1]))->assertOk()->assertSessionMissing('ui.project_id');
});

test('all cockpit modules render with the selected project', function () {
    $project = cockpitProject('Modulprüfung');
    $this->actingAs(User::factory()->create());
    foreach (config('ui.navigation') as $items) {
        foreach ($items as [$route]) {
            $this->get(route($route, ['project_id' => $project->id]))->assertOk();
        }
    }
});

test('saving a valid daily report emits confirmation and validation preserves the form', function () {
    $project = cockpitProject('Berichtsprojekt');
    $this->actingAs(User::factory()->create());
    Volt::test('daily-log-manager')->set('projectId', $project->id)->call('openCreateModal')
        ->set('workPerformed', '')->call('saveLog')->assertHasErrors(['workPerformed'])->assertSet('showModal', true)
        ->set('workPerformed', 'Dachabdichtung fertiggestellt')->call('saveLog')->assertHasNoErrors()
        ->assertDispatched('daily-log-saved', projectId: $project->id)->assertSet('showModal', false);
});
