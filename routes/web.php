<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/servers', 'servers/Index')->name('servers.index');
Route::inertia('/servers/create', 'servers/Create')->name('servers.create');
Route::inertia('/servers/fragrant-forest', 'servers/Show')->name('servers.show');

Route::inertia('/sites', 'sites/Index')->name('sites.index');
Route::inertia('/sites/create', 'sites/Create')->name('sites.create');
Route::inertia('/sites/chirper', 'sites/Show')->name('sites.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'servers/Index')->name('dashboard');
});

require __DIR__.'/settings.php';
