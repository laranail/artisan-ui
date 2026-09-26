<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Enums;

use Simtabi\Laranail\Enumerator\Attributes\Help;
use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;
use Simtabi\Laranail\Enumerator\Attributes\Description;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumeratorBehavior;

/**
 * The Gate abilities the package asks about.
 *
 * Each value is the ability name as registered, so `Gate::define(Ability::Access->value, ...)`
 * and `Gate::define('laranail-artisan-ui.access', ...)` are the same thing. The package defines
 * every one of them as a deny-all fallback, and only when the application has not defined it
 * first, so an application definition always wins.
 */
#[Description('Gate abilities checked by laranail/artisan-ui.')]
enum Ability: string implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('Open the panel'), Help('Receives the user. Checked on every request to the panel.')]
    case Access = 'laranail-artisan-ui.access';

    #[Label('Run a command'), Help('Receives the user, the CommandDefinition and the validated input array.')]
    case Run = 'laranail-artisan-ui.run';

    #[Label('View run history'), Help('Receives the user. Gates the History screen and rerun.')]
    case ViewHistory = 'laranail-artisan-ui.view-history';

    #[Label('View environment details'), Help('Receives the user. Gates the PHP, Laravel and database version panel.')]
    case ViewEnvironment = 'laranail-artisan-ui.view-environment';

    public static function translationNamespace(): string
    {
        return 'laranail/artisan-ui';
    }
}
