<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Events;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;

/**
 * Ran and exited non-zero, or threw. `$result->exception` is set in the second case.
 */
final readonly class CommandFailed
{
    public function __construct(
        public RunContext $context,
        public RunResult $result,
        public string $output,
    ) {}
}
