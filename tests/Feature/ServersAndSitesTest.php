<?php

use App\Models\User;

test('the servers index can be rendered', function () {
    $this->get(route('servers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('servers/Index'));
});

test('the create server page can be rendered', function () {
    $this->get(route('servers.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('servers/Create'));
});

test('the server detail page can be rendered', function () {
    $this->get(route('servers.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('servers/Show'));
});

test('the sites index can be rendered', function () {
    $this->get(route('sites.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('sites/Index'));
});

test('the create site page can be rendered', function () {
    $this->get(route('sites.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('sites/Create'));
});

test('the site detail page can be rendered', function () {
    $this->get(route('sites.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('sites/Show'));
});

test('authenticated users see the servers index on the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('servers/Index'));
});
