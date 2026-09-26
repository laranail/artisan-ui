<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\ArtisanUI\Enums\AssetMode;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Middleware\SecurityHeaders;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Middleware\EnsureArtisanUIAccess;

/**
 * The web panel: routes, and the middleware no configuration can remove.
 *
 * The route group is built by hand rather than through package-tools' RouteGroupDefinition,
 * because that can only take its middleware list from configuration, and the guards here
 * must be appended to whatever the configuration says, never replaced by it. Removing `web`
 * from the config can break the panel; it cannot open it.
 *
 * Off means absent: while the panel is disabled, no route is registered. A route cache built
 * while it was enabled still carries the routes, which is why EnsureArtisanUIAccess checks
 * `enabled` again on every request.
 */
final class WebUIServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $config = $this->app->make(ArtisanUIConfig::class);

        if (! $config->enabled()) {
            return;
        }

        Route::group([
            'domain'     => $config->domain(),
            'prefix'     => $config->path(),
            'as'         => 'laranail-artisan-ui.',
            'middleware' => [
                ...$config->middleware(),
                SecurityHeaders::class,
                EnsureArtisanUIAccess::class,
            ],
        ], function (): void {
            $this->loadRoutesFrom(dirname(__DIR__, 4) . '/routes/web.php');
        });

        if ($config->assetMode() === AssetMode::Route) {
            Route::group([
                'domain' => $config->domain(),
                'prefix' => $config->assetRoute(),
                'as'     => 'laranail-artisan-ui.',
            ], function (): void {
                $this->loadRoutesFrom(dirname(__DIR__, 4) . '/routes/assets.php');
            });
        }
    }
}
