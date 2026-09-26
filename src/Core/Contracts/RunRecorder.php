<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Contracts;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;

/**
 * Writes the audit trail.
 *
 * Two calls rather than one, so a run that never finishes (a fatal, a killed worker) still
 * leaves a record that it started. Implementations must not throw: a broken audit store must
 * not be what stops an operator from running a command, and must not hide that it ran.
 */
interface RunRecorder
{
    public function started(RunContext $context): void;

    /**
     * @param string $output the decorated, redacted output, never the raw text
     */
    public function finished(RunContext $context, RunResult $result, string $output): void;
}
