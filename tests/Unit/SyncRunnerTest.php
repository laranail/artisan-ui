<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Core\Execution\SyncRunner;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

function bareDefinition(string $name): CommandDefinition
{
    return new CommandDefinition($name, '', '', '', false, [], [], []);
}

it('always runs non-interactively (upstream PR #10), whatever the input says', function (): void {
    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('call')
        ->once()
        ->withArgs(static fn (string $name, array $parameters): bool => $name === 'demo'
            && ($parameters['--no-interaction'] ?? null) === true
            && $parameters['--flag'] === true)
        ->andReturn(0);

    $result = new SyncRunner($kernel, app(ArtisanUIConfig::class))
        ->run(bareDefinition('demo'), new ValidatedInput([], ['flag' => true]));

    expect($result->status)->toBe(RunStatus::Succeeded)->and($result->exitCode)->toBe(0);
});

it('maps exit codes and exceptions to statuses', function (): void {
    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('call')->once()->andReturn(3);
    $kernel->shouldReceive('call')->once()->andThrow(new RuntimeException('boom'));

    $runner = new SyncRunner($kernel, app(ArtisanUIConfig::class));

    $failed = $runner->run(bareDefinition('a'), new ValidatedInput);
    $errored = $runner->run(bareDefinition('b'), new ValidatedInput);

    expect($failed->status)->toBe(RunStatus::Failed)->and($failed->exitCode)->toBe(3)
        ->and($errored->status)->toBe(RunStatus::Errored)->and($errored->exception)->toBeInstanceOf(RuntimeException::class);
});

it('caps output at the configured byte limit and says so', function (): void {
    config()->set('laranail.artisan-ui.limits.max_output_bytes', 10);

    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('call')->once()->andReturnUsing(static function (string $name, array $params, $output): int {
        $output->write(str_repeat('x', 50));

        return 0;
    });

    $result = new SyncRunner($kernel, app(ArtisanUIConfig::class))->run(bareDefinition('loud'), new ValidatedInput);

    expect(strlen($result->output))->toBe(10)->and($result->truncated)->toBeTrue();
});
