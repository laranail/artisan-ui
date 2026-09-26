<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Enums;

use Simtabi\Laranail\Enumerator\Attributes\Help;
use Simtabi\Laranail\Enumerator\Attributes\Color;
use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;
use Simtabi\Laranail\Enumerator\Attributes\Description;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumeratorBehavior;

/**
 * How much damage a command can do, which decides what it takes to run it.
 *
 * Classification is by name, from configurable wildcard lists. It is a speed bump against
 * the obvious mistakes, not a sandbox: a custom command can do anything, which is why the
 * Run ability is checked for every command regardless of its class.
 */
#[Description('Risk class of an Artisan command.')]
enum CommandRisk: string implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('Safe'), Color('green'), Help('Runs after the Gate allows it.')]
    case Safe = 'safe';

    #[Label('Writes files'), Color('amber'), Help('Changes the codebase. Runs only in the configured writes-files environments.')]
    case WritesFiles = 'writes_files';

    #[Label('Destructive'), Color('red'), Help('Destroys data or availability. Needs the command name typed back and a fresh password confirmation.')]
    case Destructive = 'destructive';

    #[Label('Forbidden'), Color('gray'), Help('Long-running or interactive. Never listed, never runnable from the web.')]
    case Forbidden = 'forbidden';

    public static function translationNamespace(): string
    {
        return 'laranail/artisan-ui';
    }

    public function isRunnable(): bool
    {
        return $this !== self::Forbidden;
    }

    public function requiresConfirmation(): bool
    {
        return $this === self::Destructive;
    }
}
