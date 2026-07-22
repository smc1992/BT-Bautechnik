<?php

namespace Tests\Feature\Auth;

use Livewire\Volt\Volt;

test('registration screen cannot be rendered when registration is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
});
