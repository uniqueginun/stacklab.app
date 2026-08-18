<?php

use App\Http\Controllers\ServersController;
use App\Http\Controllers\ServerSSHController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/sites', 'sites/Index')->name('sites.index');
Route::inertia('/sites/create', 'sites/Create')->name('sites.create');
Route::inertia('/sites/chirper', 'sites/Show')->name('sites.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ServersController::class, 'index'])->name('dashboard');

    Route::get('servers', [ServersController::class, 'index'])->name('servers.index');
    Route::get('servers/create', [ServersController::class, 'create'])->name('servers.create');
    Route::post('servers', [ServersController::class, 'store'])->name('servers.store');
    Route::get('servers/{server:uuid}', [ServersController::class, 'show'])->name('servers.show');
    Route::delete('servers/{server:uuid}', [ServersController::class, 'destroy'])->name('servers.destroy');

    Route::post('servers/{server:uuid}/ssh-connect', [ServerSSHController::class, 'connect'])->name('servers.ssh.connect');
    Route::post('servers/{server:uuid}/ssh-verify', [ServerSSHController::class, 'verify'])->name('servers.ssh.verify');
    Route::post('servers/{server:uuid}/ssh-confirm', [ServerSSHController::class, 'confirm'])->name('servers.ssh.confirm');
});

require __DIR__.'/settings.php';
