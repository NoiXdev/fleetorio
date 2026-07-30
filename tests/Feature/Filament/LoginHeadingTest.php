<?php

it('shows the custom heading on the login page', function () {
    $this->get('/admin/login')->assertSee('Login zum Fleetorio');
});

it('reflects the configured brand name in the login heading', function () {
    \App\Models\AppSetting::factory()->create(['brand_name' => 'Testflotte']);

    $this->get('/admin/login')->assertSee('Login zum Testflotte');
});
