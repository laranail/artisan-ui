<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

final class EchoRawCommand extends Command
{
    protected $signature = 'lau-fixture:echo-raw';

    protected $description = 'Print with echo, bypassing the console output';

    public function handle(): int
    {
        echo 'raw password=' . ($_ENV['LAU_FIXTURE_PASSWORD'] ?? '') . "\n";
        $this->line('via line');

        return self::SUCCESS;
    }
}
