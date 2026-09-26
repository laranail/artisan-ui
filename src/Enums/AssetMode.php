<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Enums;

use Simtabi\Laranail\Enumerator\Attributes\Help;
use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;
use Simtabi\Laranail\Enumerator\Attributes\Description;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumeratorBehavior;

/**
 * How the panel's stylesheet and script reach the browser.
 *
 * `Route` is the default because it cannot serve a stale bundle: the URL carries a content
 * hash, so upgrading the package changes the URL.
 */
#[Description('How the artisan-ui assets are delivered.')]
enum AssetMode: string implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('Route'), Help('Served by the package from a content-hashed, immutably cached route. No publish step.')]
    case Route = 'route';

    #[Label('Published'), Help('Served from public/vendor/artisan-ui after vendor:publish. Re-publish on upgrade.')]
    case Published = 'published';

    public static function translationNamespace(): string
    {
        return 'laranail/artisan-ui';
    }
}
