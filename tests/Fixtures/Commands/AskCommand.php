<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

final class AskCommand extends Command
{
    protected $signature = 'lau-fixture:ask';

    protected $description = 'Prompt for confirmation';

    public function handle(): int
    {
        return $this->confirm('Continue?') ? self::SUCCESS : 7;
    }
}
