<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use RuntimeException;
use Illuminate\Console\Command;

final class ThrowCommand extends Command
{
    protected $signature = 'lau-fixture:throw';

    protected $description = 'Throw with internal detail in the message';

    public function handle(): int
    {
        throw new RuntimeException('internal detail: mysql://root:hunter22@db/prod');
    }
}
