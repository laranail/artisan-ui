<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Factory as Auth;
use Symfony\Component\HttpFoundation\Response;
use Simtabi\Laranail\ArtisanUI\Core\Policy\PanelAccess;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\AccessGuard;

/**
 * The gate in front of every panel route. Also registered as the `laranail-artisan-ui`
 * middleware alias, so an application can put the same check in front of its own routes.
 *
 * Replaces both forks' approaches: no stored credentials (dev-arindam-roy) and no "enabled
 * means open" (pabloleone). The user is whoever the configured guard says is signed in; the
 * decision is PanelAccess's, which ends in the application's own Access ability.
 */
final readonly class EnsureArtisanUIAccess
{
    public function __construct(
        private PanelAccess $access,
        private ArtisanUIConfig $config,
        private Auth $auth,
        private AccessGuard $guard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->config->guard();
        $user = $this->auth->guard($guard)->user();

        $decision = $this->access->inspect($user, $request->ip());

        if ($decision->allowed) {
            // So $request->user() and Gate checks further in resolve the same guard.
            $this->auth->shouldUse($guard ?? $this->auth->getDefaultDriver());

            /** @var Response */
            return $next($request);
        }

        if ($decision->reason === 'unauthenticated') {
            return $this->unauthenticated($request);
        }

        $this->guard->deny($request, $decision);
    }

    /**
     * Send a guest to the application's login page when it has one.
     *
     * `route('login')` rather than `Route::has('login')`: the latter asks the route collection
     * directly and skips `URL::resolveMissingNamedRoutesUsing()`, so it answers false in an
     * application whose login route is registered under a vendor-scoped name with a bare
     * fallback, which is exactly the laranail authkit shape.
     */
    private function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('laranail/artisan-ui::messages.unauthenticated')], 401);
        }

        try {
            $login = route('login');
        } catch (RouteNotFoundException) {
            abort(401);
        }

        return redirect()->guest($login);
    }
}
