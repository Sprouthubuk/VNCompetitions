<?php

it('reports healthy on /up', function () {
    $this->get('/up')->assertOk();
});

it('shows the Filament login at /admin', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk()->assertSee('Sign in');
});

it('allows the storefront origin through CORS', function () {
    $this->withHeaders(['Origin' => 'http://localhost:4321'])
        ->get('/up')
        ->assertOk();

    $this->options('/api/v1/competitions', [], [
        'Origin' => 'http://localhost:4321',
        'Access-Control-Request-Method' => 'GET',
    ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4321')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});
