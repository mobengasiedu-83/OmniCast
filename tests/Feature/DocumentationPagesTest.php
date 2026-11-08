<?php

use Inertia\Testing\AssertableInertia as Assert;

it('serves the user manual to a visitor', function (): void {
    $this->get(route('user-manual'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('user-manual'));
});

it('serves the api documentation to a visitor', function (): void {
    $this->get(route('api-docs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('api-docs'));
});

it('keeps both documents reachable without signing in', function (): void {
    $this->assertGuest();

    $this->get('/user-manual')->assertOk();
    $this->get('/api-docs')->assertOk();
});
