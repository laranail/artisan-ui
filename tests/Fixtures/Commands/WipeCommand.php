<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

final class WipeCommand extends Command
{
    public static int $runs = 0;

    protected $signature = 'lau-fixture:wipe {--password= : A secret-named option}';

    protected $description = 'Pretend to destroy something';

    public function handle(): int
    {
        self::$runs++;
        $this->line('wiped');

        return self::SUCCESS;
    }
}
