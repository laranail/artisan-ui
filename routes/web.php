<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\HomeController;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\CommandController;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\ExecuteController;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\HistoryController;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\CsrfTokenController;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\ConfirmPasswordController;

/*
 * Registered inside the group built by WebUIServiceProvider, so every route here carries the
 * configured middleware, SecurityHeaders and EnsureArtisanUIAccess, and is named
 * `laranail-artisan-ui.*`.
 *
 * There is exactly one route that runs anything: POST commands/{command}/run. Quick actions
 * and history reruns link to the command page with the form pre-filled.
 */

Route::get('/', HomeController::class)->name('home');

Route::get('/history', HistoryController::class)->name('history');

Route::get('/csrf-token', CsrfTokenController::class)->name('csrf-token');

Route::post('/confirm-password', ConfirmPasswordController::class)
    ->middleware('throttle:laranail-artisan-ui-confirm')
    ->name('confirm-password');

Route::get('/commands/{command}', CommandController::class)
    ->where('command', '[A-Za-z0-9:_.\-]+')
    ->name('detail');

Route::post('/commands/{command}/run', ExecuteController::class)
    ->where('command', '[A-Za-z0-9:_.\-]+')
    ->middleware('throttle:laranail-artisan-ui')
    ->name('execute');
