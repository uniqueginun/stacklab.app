<?php

use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerProvisionController;
use App\Http\Controllers\ServersController;
use App\Http\Controllers\ServerSSHController;
use App\Http\Controllers\SiteCommandController;
use App\Http\Controllers\SiteDeployController;
use App\Http\Controllers\SiteEnvironmentController;
use App\Http\Controllers\SiteRepositoryController;
use App\Http\Controllers\SiteRollbackController;
use App\Http\Controllers\SitesController;
use App\Http\Controllers\VersionControlProviderController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ServersController::class, 'index'])->name('dashboard');

    Route::get('servers', [ServersController::class, 'index'])->name('servers.index');
    Route::get('servers/create', [ServersController::class, 'create'])->name('servers.create');
    Route::post('servers', [ServersController::class, 'store'])->name('servers.store');
    Route::get('servers/{server:uuid}', [ServersController::class, 'show'])->name('servers.show');
    Route::delete('servers/{server:uuid}', [ServersController::class, 'destroy'])->name('servers.destroy');

    Route::get('servers/{server:uuid}/databases', [ServerDatabaseController::class, 'index'])->name('servers.databases');
    Route::post('servers/{server:uuid}/databases', [ServerDatabaseController::class, 'store'])->name('servers.databases.store');

    Route::post('servers/{server:uuid}/provision', ServerProvisionController::class)->name('servers.provision');

    Route::post('servers/{server:uuid}/ssh-connect', [ServerSSHController::class, 'connect'])->name('servers.ssh.connect');
    Route::post('servers/{server:uuid}/ssh-verify', [ServerSSHController::class, 'verify'])->name('servers.ssh.verify');
    Route::post('servers/{server:uuid}/ssh-confirm', [ServerSSHController::class, 'confirm'])->name('servers.ssh.confirm');

    Route::get('sites/{site:uuid}/repository', [SiteRepositoryController::class, 'edit'])->name('sites.repository.edit');
    Route::put('sites/{site:uuid}/repository', [SiteRepositoryController::class, 'update'])->name('sites.repository.update');

    Route::post('sites/{site:uuid}/deployments', [SiteDeployController::class, 'store'])->name('sites.deployments.store');
    Route::post('sites/{site:uuid}/rollbacks/{release:uuid}', [SiteRollbackController::class, 'store'])
        ->name('sites.rollbacks.store')
        ->scopeBindings();

    Route::get('sites/{site:uuid}/environment/file', [SiteEnvironmentController::class, 'edit'])->name('sites.environment.edit');
    Route::put('sites/{site:uuid}/environment', [SiteEnvironmentController::class, 'update'])->name('sites.environment.update');

    Route::post('sites/{site:uuid}/commands', [SiteCommandController::class, 'store'])
        ->name('sites.commands.store')
        ->middleware('throttle:10,1');

    Route::get('sites', [SitesController::class, 'index'])->name('sites.index');
    Route::get('sites/create', [SitesController::class, 'create'])->name('sites.create');
    Route::post('sites', [SitesController::class, 'store'])->name('sites.store');
    Route::get('sites/{site:uuid}', [SitesController::class, 'show'])->name('sites.show');
    Route::get('sites/{site:uuid}/source', [SitesController::class, 'source'])->name('sites.source');
    Route::get('sites/{site:uuid}/deployments', [SitesController::class, 'deployments'])->name('sites.deployments');
    Route::get('sites/{site:uuid}/environment', [SitesController::class, 'environment'])->name('sites.environment');
    Route::get('sites/{site:uuid}/commands', [SitesController::class, 'commands'])->name('sites.commands');
    Route::delete('sites/{site:uuid}', [SitesController::class, 'destroy'])->name('sites.destroy');

    Route::get('settings/connections/{provider}/redirect', [VersionControlProviderController::class, 'redirect'])
        ->name('connections.provider.redirect');
    Route::get('settings/connections/{provider}/callback', [VersionControlProviderController::class, 'callback'])
        ->name('connections.provider.callback');
    Route::delete('settings/connections/{provider}', [VersionControlProviderController::class, 'destroy'])
        ->name('connections.provider.destroy');
});

require __DIR__.'/settings.php';
