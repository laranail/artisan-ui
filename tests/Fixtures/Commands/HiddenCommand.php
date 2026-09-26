<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

final class HiddenCommand extends Command
{
    protected $signature = 'lau-fixture:hidden';

    protected $description = 'A hidden command';

    protected $hidden = true;

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
