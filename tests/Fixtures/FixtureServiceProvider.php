<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests\Fixtures;

use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\EchoCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\SecretCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\ThrowCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\AskCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\HiddenCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\WipeCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\MakeCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\EchoRawCommand;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\BufferCommand;
use Illuminate\Support\ServiceProvider;

final class FixtureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            EchoCommand::class,
            SecretCommand::class,
            ThrowCommand::class,
            AskCommand::class,
            HiddenCommand::class,
            WipeCommand::class,
            MakeCommand::class,
            EchoRawCommand::class,
            BufferCommand::class,
        ]);
    }
}
