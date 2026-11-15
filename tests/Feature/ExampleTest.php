<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('<title>OmniCast | SMS, Voice &amp; Email Broadcasting Platform</title>', false)
        ->assertSee('name="description" content="OmniCast by SysUp360 is a multi-channel communication platform', false)
        ->assertSee('rel="canonical" href="https://omnicast.sysup360.com/"', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"SoftwareApplication"', false);
});
