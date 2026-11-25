<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Config\Repository as Config;
use Simtabi\Laranail\Package\Tools\Http\Controllers\WebController;

/**
 * A fresh CSRF token for a long-open panel (engineering reference, Part I §1).
 *
 * The client calls this after a 419 and then asks the operator to run the command again;
 * it never replays the POST on its own, because a run is not idempotent.
 */
final class CsrfTokenController extends WebController
{
    public function __invoke(Request $request, Config $config): JsonResponse
    {
        $lifetime = $config->get('session.lifetime', 120);

        return new JsonResponse([
            'token'      => $request->session()->token(),
            'expires_in' => (is_numeric($lifetime) ? (int) $lifetime : 120) * 60,
        ]);
    }
}
