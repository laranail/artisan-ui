<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers\AssetController;

/*
 * The stylesheet and script, when `assets.mode` is `route`. The filename is constrained to
 * the two files the package ships, so nothing else can reach the controller. No sign-in: the
 * files are the same bundle for everyone, and a login redirect would break the panel's own
 * styling on the 403 page. The environment and network allowlists still apply (see the
 * controller): outside them it answers 404.
 */
Route::get('/{file}', AssetController::class)
    ->where('file', implode('|', array_map(
        static fn (string $file): string => preg_quote($file, '/'),
        Assets::filenames(),
    )))
    ->name('asset');
