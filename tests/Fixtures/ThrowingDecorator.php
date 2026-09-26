<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures;

use RuntimeException;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

final class ThrowingDecorator implements OutputDecorator
{
    public function decorate(string $output, CommandDefinition $command, RunResult $result): string
    {
        throw new RuntimeException('decorator broke');
    }
}
