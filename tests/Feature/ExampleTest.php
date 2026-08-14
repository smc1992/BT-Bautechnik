<?php

it('renders the landing page on home route', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
