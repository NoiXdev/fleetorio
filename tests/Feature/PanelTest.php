<?php

it('serves the panel login page', function () {
    $this->get('/admin/login')->assertOk();
});
