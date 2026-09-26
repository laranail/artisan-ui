<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Exceptions;

use RuntimeException;

/**
 * Another run of the same command (or, for a destructive command, any destructive command)
 * is already in progress.
 */
final class CommandBusy extends RuntimeException
{
    public static function for(string $command): self
    {
        return new self("[{$command}] is already running. Wait for it to finish and try again.");
    }
}
