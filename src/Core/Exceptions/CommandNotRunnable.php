<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Exceptions;

use RuntimeException;

/**
 * The command is Forbidden, whatever the caller was told. A defence in depth behind the
 * registry and the authorizer, both of which refuse it first.
 */
final class CommandNotRunnable extends RuntimeException
{
    public static function for(string $command): self
    {
        return new self("[{$command}] cannot be run from the panel.");
    }
}
