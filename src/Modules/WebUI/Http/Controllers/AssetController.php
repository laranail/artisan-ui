<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simtabi\Laranail\ArtisanUI\Core\Policy\PanelAccess;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets;

/**
 * Serves the built stylesheet and script from the package directory, immutably cached: the
 * URL carries the content hash, so a new build is a new URL.
 *
 * No sign-in is asked (the 403 page for a signed-out or refused user needs the stylesheet too),
 * but the environment and network allowlists are: outside them the route answers a bare 404, so
 * the bundle cannot confirm the panel exists (AUI-16). The 403 page shown there renders plain.
 */
final class AssetController
{
    public function __invoke(Request $request, Assets $assets, PanelAccess $access, string $file): Response
    {
        if (! $access->environmentAllowed() || ! $access->ipAllowed($request->ip())) {
            return new Response('', 404);
        }

        if (! Assets::isKnown($file) || ! $assets->exists($file)) {
            return new Response('/* laranail/artisan-ui: asset not built. Run `npm install && npm run build` in the package. */', 404, [
                'Content-Type'           => 'text/plain; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $response = new Response($assets->contents($file), 200, [
            'Content-Type'           => $assets->contentType($file),
            'Cache-Control'          => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setEtag($assets->hash($file));
        $response->isNotModified($request);

        return $response;
    }
}
