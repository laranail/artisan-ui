<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands;

use Illuminate\Console\Command;

final class SecretCommand extends Command
{
    protected $signature = 'lau-fixture:secret';

    protected $description = 'Print a secret and some markup';

    public function handle(): int
    {
        $this->line('password is ' . ($_ENV['LAU_FIXTURE_PASSWORD'] ?? ''));
        $this->line('<script>alert(1)</script>');
        $this->line("\e[31mred\e[0m");

        return self::SUCCESS;
    }
}
