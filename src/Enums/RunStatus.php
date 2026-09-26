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
 * Where a run ended up.
 *
 * `Failed` and `Errored` are kept apart on purpose: a non-zero exit code is the command
 * reporting a problem it understood, while an exception is the command not finishing.
 */
#[Description('Outcome of a command run.')]
enum RunStatus: string implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('Running'), Color('blue'), Help('Started and not yet recorded as finished.')]
    case Running = 'running';

    #[Label('Succeeded'), Color('green'), Help('Exited with status 0.')]
    case Succeeded = 'succeeded';

    #[Label('Failed'), Color('amber'), Help('Exited with a non-zero status.')]
    case Failed = 'failed';

    #[Label('Errored'), Color('red'), Help('Threw before it could exit.')]
    case Errored = 'errored';

    public static function fromExitCode(int $exitCode): self
    {
        return $exitCode === 0 ? self::Succeeded : self::Failed;
    }

    public static function translationNamespace(): string
    {
        return 'laranail/artisan-ui';
    }

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }
}
