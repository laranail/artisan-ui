<?php

declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Simtabi\Laranail\ArtisanUI\Commands\TidyCommand;
use Symfony\Component\Console\Output\BufferedOutput;

// These tests delete files under storage/logs, laravel.log included, while Monolog holds it open. On
// Windows a file with a pending delete cannot be reopened, so the next log write throws. No test here
// asserts on log output, so logging goes to the null channel.
beforeEach(function (): void {
    config()->set('logging.default', 'null');
});

/*
 * Exercises the console-toolkit features tidy adopts from `laranail/console`: the
 * `$this->services` lifecycle (metadata, signals, performance, logger) and the fluent
 * `consoleWriter()` output. Signal handling is driven through the service's running flag — no
 * real OS signal is raised, so these pass with or without ext-pcntl.
 *
 * The tidy cases of laranail/toolkit's tests/Unit/Console/CommandConsoleFeaturesTest.php, moved
 * with the command.
 */

/**
 * Bind a buffered output and run the command with the given input array — returning the exit
 * code so the caller can assert on both the effect and the populated command metadata.
 *
 * @param array<string, mixed> $input
 */
function tidyRunDirectly(TidyCommand $command, array $input, ?callable $before = null): int
{
    $command->setLaravel(app());
    $command->setApplication(new Application);

    if ($before !== null) {
        $before($command);
    }

    return $command->run(new ArrayInput($input), new BufferedOutput);
}

afterEach(function (): void {
    tidyCleanUp();
});

// -----------------------------------------------------------------------
// metadata is populated through the lifecycle
// -----------------------------------------------------------------------

it('records files processed and space freed in the command metadata', function (): void {
    $log = tidyStorageFile('logs/meta_' . uniqid() . '.log', str_repeat('x', 128));

    $command = $this->app->make(TidyCommand::class);

    expect(tidyRunDirectly($command, ['action' => 'logs', '--force' => true]))->toBe(0);

    $metadata = $command->getServices()->metadata();

    expect($metadata->get('action'))->toBe('logs')
        ->and($metadata->get('files_processed'))->toBeGreaterThanOrEqual(1)
        ->and($metadata->get('space_freed'))->toBeString()
        ->and($log)->not->toBeFile();
});

// -----------------------------------------------------------------------
// signal-safe sweep / clean loop (driven via the service flag, no OS signal)
// -----------------------------------------------------------------------

it('stops the sweep when termination was requested', function (): void {
    $log = tidyStorageFile('logs/keep_' . uniqid() . '.log', 'keep-me');

    $command = $this->app->make(TidyCommand::class);

    // Flip the running flag off before the run so the file sweep bails on the
    // first iteration — no file is deleted. shouldKeepRunning() defaults true,
    // so this isolates the signal-safe break path without an OS signal.
    $exit = tidyRunDirectly($command, ['action' => 'logs', '--force' => true], static function (TidyCommand $c): void {
        $c->getServices()->signals()->stop();
    });

    expect($exit)->toBe(0)
        ->and($log)->toBeFile('A termination request must stop the sweep before deleting.');
});

it('stops tidy all sweeping roots when termination was requested', function (): void {
    $upload = tidyStorageFile('app/uploads/all_' . uniqid() . '.bin', 'keep-me');

    $command = $this->app->make(TidyCommand::class);

    // Stop before the run: tidyAll flushes the cache, then the per-root loop
    // breaks on the first check — so the storage upload is never swept.
    $exit = tidyRunDirectly($command, ['action' => 'all', '--force' => true], static function (TidyCommand $c): void {
        $c->getServices()->signals()->stop();
    });

    expect($exit)->toBe(0)
        ->and($upload)->toBeFile();
});
