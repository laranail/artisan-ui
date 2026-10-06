<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArrayInput;
use Simtabi\Laranail\ArtisanUI\Commands\TidyCommand;

// These tests delete files under storage/logs, laravel.log included, while Monolog holds it open. On
// Windows a file with a pending delete cannot be reopened, so the next log write throws. No test here
// asserts on log output, so logging goes to the null channel.
beforeEach(function (): void {
    config()->set('logging.default', 'null');
});

/*
 * Targets the harder-to-reach tidy branches: the declined-confirmation early
 * returns, the cache-flush failure catch, the unresolvable storage path, the
 * db-action seed path, and the signal-/guard-driven sweep skips.
 *
 * Ported from laranail/toolkit's tests/Unit/Console/TidyBranchesTest.php. Toolkit drove the
 * cache and db branches through `SpyTidy`, a subclass overriding `call()`; TidyCommand is final
 * here, as every artisan-ui command is, so the same branches are reached by registering stand-in
 * `cache:clear`, `migrate:fresh` and `db:seed` commands that record (or throw) instead.
 */

/**
 * Replace the named commands in the console application with recorders that never run the
 * real thing, returning the shared call log. `cache:clear` throws when asked to.
 *
 * @param list<string> $names
 *
 * @return ArrayObject<int, string>
 */
function tidyStandInCommands(array $names, bool $throwOnCacheClear = false): ArrayObject
{
    /** @var ArrayObject<int, string> $calls */
    $calls = new ArrayObject;
    $kernel = app(Kernel::class);

    foreach ($names as $name) {
        $command = new class($name, $calls, $throwOnCacheClear) extends Command
        {
            /** @param ArrayObject<int, string> $calls */
            public function __construct(string $name, private readonly ArrayObject $calls, private readonly bool $throws)
            {
                $this->signature = $name . ' {--force} {--seed}';

                parent::__construct();
            }

            public function handle(): int
            {
                $this->calls[] = (string) $this->getName();

                if ($this->throws && $this->getName() === 'cache:clear') {
                    throw new RuntimeException('cache flush failed');
                }

                return self::SUCCESS;
            }
        };

        $command->setLaravel(app());
        $kernel->registerCommand($command);
    }

    return $calls;
}

function tidyFilesProcessed(TidyCommand $command): int
{
    $value = new ReflectionProperty(TidyCommand::class, 'filesProcessed')->getValue($command);

    return is_int($value) ? $value : -1;
}

afterEach(function (): void {
    tidyCleanUp();
});

// -----------------------------------------------------------------------
// Declined confirmations (non-interactive, no --force)
// -----------------------------------------------------------------------

it('makes the cache action a no-op when declined non-interactively', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'cache', '--no-interaction' => true])
        ->doesntExpectOutputToContain('Cache tidied.')
        ->assertExitCode(0);
});

it('makes the all action a no-op when declined non-interactively', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'all', '--no-interaction' => true])
        ->doesntExpectOutputToContain('All tidied')
        ->assertExitCode(0);
});

it('previews the cache flush on an all dry run', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'all', '--dry-run' => true])
        ->expectsOutputToContain('[dry-run] Would flush the application cache.')
        ->assertExitCode(0);
});

// -----------------------------------------------------------------------
// Unresolvable storage path
// -----------------------------------------------------------------------

it('fails when storage_path() does not resolve', function (): void {
    $original = storage_path();
    $this->app->useStoragePath('/laranail-nonexistent-' . uniqid());

    try {
        $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
            ->expectsOutputToContain('storage_path() does not resolve')
            ->assertExitCode(1);
    } finally {
        $this->app->useStoragePath($original);
    }
});

// -----------------------------------------------------------------------
// Cache-flush failure catch
// -----------------------------------------------------------------------

it('catches a cache flush failure and completes the run', function (): void {
    $calls = tidyStandInCommands(['cache:clear'], throwOnCacheClear: true);

    // cache:clear throws, but the catch logs and the run still reports the
    // cache as tidied (exit success).
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'cache', '--force' => true])
        ->expectsOutputToContain('Cache tidied.')
        ->assertExitCode(0);

    expect($calls->getArrayCopy())->toContain('cache:clear');
});

it('catches a cache flush failure during all and keeps sweeping', function (): void {
    $calls = tidyStandInCommands(['cache:clear'], throwOnCacheClear: true);

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'all', '--force' => true])
        ->expectsOutputToContain('db excluded')
        ->assertExitCode(0);

    expect($calls->getArrayCopy())->toContain('cache:clear');
});

// -----------------------------------------------------------------------
// db action: seed
// -----------------------------------------------------------------------

it('runs migrate:fresh then db:seed for the db action with --seed', function (): void {
    $calls = tidyStandInCommands(['migrate:fresh', 'db:seed']);

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'db', '--force' => true, '--seed' => true])
        ->expectsOutputToContain('Database refreshed.')
        ->assertExitCode(0);

    expect($calls->getArrayCopy())->toBe(['migrate:fresh', 'db:seed']);
});

it('never runs the refresh without --force', function (): void {
    $calls = tidyStandInCommands(['migrate:fresh', 'db:seed']);

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'db', '--seed' => true])
        ->assertExitCode(1);

    // It bailed out before invoking the destructive refresh.
    expect($calls->getArrayCopy())->toBe([]);
});

// -----------------------------------------------------------------------
// sweep(): guard + signal skips (driven directly)
// -----------------------------------------------------------------------

it('skips an unsafe relative root in sweep()', function (): void {
    $keep = tidyStorageFile('logs/guarded_' . uniqid() . '.log', 'keep');

    $command = $this->app->make(TidyCommand::class);
    $base = (string) realpath(storage_path());

    new ReflectionMethod($command, 'sweep')->invoke($command, $base, '../escape');

    expect(tidyFilesProcessed($command))->toBe(0)
        ->and($keep)->toBeFile();
});

it('stops sweeping when a termination signal arrived', function (): void {
    $keep = tidyStorageFile('logs/signalled_' . uniqid() . '.log', 'keep');

    $command = $this->app->make(TidyCommand::class);
    $command->setLaravel($this->app);

    // Provide an input so the age/size option reads resolve to their defaults.
    $input = new ArrayInput([], $command->getDefinition());
    new ReflectionProperty(Command::class, 'input')->setValue($command, $input);

    // Flip the running flag off so the per-file loop breaks immediately.
    $services = new ReflectionProperty($command, 'services')->getValue($command);
    $services->signals()->stop();

    $base = (string) realpath(storage_path());
    new ReflectionMethod($command, 'sweep')->invoke($command, $base, 'logs');

    expect(tidyFilesProcessed($command))->toBe(0)
        ->and($keep)->toBeFile();
});

// -----------------------------------------------------------------------
// sweep(): non-file entries are skipped (via the normal run)
// -----------------------------------------------------------------------

it('skips empty subdirectories while deleting files', function (): void {
    $dir = storage_path('logs/branch_' . uniqid());
    @mkdir($dir . '/emptydir', 0777, true);
    $file = $dir . '/old.log';
    file_put_contents($file, 'log');
    tidyTrack($file);

    try {
        $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
            ->assertExitCode(0);

        expect($file)->not->toBeFile();
    } finally {
        @rmdir($dir . '/emptydir');
        @rmdir($dir);
    }
});
