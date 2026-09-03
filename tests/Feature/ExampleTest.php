<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the welcome page can be rendered', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('the welcome page links to the public source repository', function () {
    $welcome = file_get_contents(resource_path('js/pages/Welcome.vue'));

    expect($welcome)
        ->toContain('https://github.com/uniqueginun/stacklab.app')
        ->toContain('View the source')
        ->toContain('rel="noopener noreferrer"');
});
