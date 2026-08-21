<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the welcome page can be rendered', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});
