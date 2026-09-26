<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Events;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;

/**
 * Ran and exited 0. Ported from pabloleone's unmerged AfterExecuteArtisanCommand.
 *
 * `$output` is decorated and redacted; the raw output is not exposed to listeners.
 */
final readonly class CommandExecuted
{
    public function __construct(
        public RunContext $context,
        public RunResult $result,
        public string $output,
    ) {}
}
