<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\AssetController;

/*
 * The stylesheet and script, when `assets.mode` is `route`. The filename is constrained to
 * the two files the package ships, so nothing else can reach the controller. No auth: the
 * files are the same public bundle for everyone, and the login page redirect would
 * otherwise break the panel's own styling on the 403 page.
 */
Route::get('/{file}', AssetController::class)
    ->where('file', implode('|', array_map(
        static fn (string $file): string => preg_quote($file, '/'),
        Assets::filenames(),
    )))
    ->name('asset');
