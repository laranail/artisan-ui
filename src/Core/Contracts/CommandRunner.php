<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Contracts;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * Runs an already-authorized, already-validated command and reports what happened.
 *
 * SyncRunner is the only implementation today. The seam exists so a queued runner, which
 * would stream output back instead of holding the request open, can be added without
 * touching authorization, validation or audit.
 */
interface CommandRunner
{
    public function run(CommandDefinition $command, ValidatedInput $input): RunResult;
}
