<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers for a page that can run commands on the server.
 *
 * The CSP allows scripts and styles from this origin only, with no inline code and no
 * `eval`, which the framework-free client needs neither of. Framing is refused outright, so
 * the run button cannot be clickjacked. Responses are never cached or indexed.
 *
 * Applied to denials as well as to the panel, so a 403 is covered too.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $assetOrigin = $this->assetOrigin($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "script-src 'self'" . $assetOrigin,
            "style-src 'self'" . $assetOrigin,
            "img-src 'self' data:",
            "font-src 'self'" . $assetOrigin,
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
        ]));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * When `ASSET_URL` points the published assets at another host, that origin has to be
     * allowed too. Built from configuration, never from the request's Host header.
     */
    private function assetOrigin(Request $request): string
    {
        $assetUrl = config('app.asset_url');

        if (! is_string($assetUrl) || $assetUrl === '') {
            return '';
        }

        $parts = parse_url($assetUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $origin === $request->getSchemeAndHttpHost() ? '' : ' ' . $origin;
    }
}
