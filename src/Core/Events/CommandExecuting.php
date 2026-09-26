<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Events;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;

/**
 * About to run. Ported from pabloleone's unmerged BeforeExecuteArtisanCommand.
 */
final readonly class CommandExecuting
{
    public function __construct(
        public RunContext $context,
    ) {}
}
