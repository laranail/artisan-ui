<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Contracts;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * Rewrites a command's output before it is shown and recorded (pabloleone's decorators).
 *
 * Decorators run before redaction, so whatever a decorator adds is still scrubbed.
 * Register one per command pattern in `laranail.artisan-ui.decorators`, or at runtime with
 * `ArtisanUI::decorate()`.
 */
interface OutputDecorator
{
    public function decorate(string $output, CommandDefinition $command, RunResult $result): string;
}
