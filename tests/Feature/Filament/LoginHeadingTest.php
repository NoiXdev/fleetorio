<?php

it('shows the custom heading on the login page', function () {
    $this->get('/admin/login')->assertSee('Login zum Fuhrparkmanagement');
});
