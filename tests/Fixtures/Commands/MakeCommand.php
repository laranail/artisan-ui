<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

final class MakeCommand extends Command
{
    protected $signature = 'lau-fixture:make';

    protected $description = 'Pretend to write a file';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
