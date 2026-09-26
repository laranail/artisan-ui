<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

final class UppercaseDecorator implements OutputDecorator
{
    public function decorate(string $output, CommandDefinition $command, RunResult $result): string
    {
        return strtoupper($output);
    }
}
