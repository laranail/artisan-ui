<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use RuntimeException;
use Illuminate\Console\Command;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;

final class ThrowCommand extends Command
{
    protected $signature = 'lau-fixture:throw';

    protected $description = 'Throw with internal detail in the message';

    public function handle(): int
    {
        // Built at run time so no credentialed URL literal sits in the source.
        $password = TestCredentials::secret('canary');

        throw new RuntimeException('internal detail: mysql://root:' . $password . '@db/prod');
    }
}
