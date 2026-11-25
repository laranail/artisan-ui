<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayLock;
use Illuminate\Console\Command;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunGuard;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Execution\CommandExecutor;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\ArgumentDefinition;

/*
 * Regressions from the 2026-11-25 audit (design folder run-2026-11-25/FINDINGS.md).
 */

afterEach(function (): void {
    tidyCleanUp();
});

// AUI-04: the shutdown function keeps every guard for the life of the worker. A guard that kept
// its closure kept the executor, the run's unredacted input and its lock with it, run after run.
it('lets go of its callback once disarmed', function (): void {
    $captured = new stdClass;
    $ref = WeakReference::create($captured);
    $guard = new RunGuard(static function () use ($captured): void {
        $captured->ran = true;
    });

    unset($captured);
    $guard->disarm();
    gc_collect_cycles();

    expect($ref->get())->toBeNull()
        ->and($guard->isArmed())->toBeFalse();
});

it('lets go of its callback once it has run', function (): void {
    $captured = new stdClass;
    $ref = WeakReference::create($captured);
    $guard = new RunGuard(static function () use ($captured): void {
        $captured->ran = true;
    });

    unset($captured);
    $guard();
    gc_collect_cycles();

    expect($ref->get())->toBeNull();
});

it('does not keep a finished run reachable from the shutdown queue', function (): void {
    $registered = [];
    $executor = app()->make(CommandExecutor::class, [
        'registerShutdown' => static function (callable $callback) use (&$registered): void {
            $registered[] = $callback;
        },
    ]);

    $execution = $executor->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'x']));
    $context = WeakReference::create($execution->context);

    unset($execution);
    gc_collect_cycles();

    expect($registered)->toHaveCount(1)
        ->and($context->get())->toBeNull();
});

// AUI-05: a run whose lock cannot be released (its table was just dropped by db:wipe on the
// database cache store) succeeded and was recorded; the client must not get a 500 for it.
it('finishes a recorded run even when releasing its lock throws', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();

    $store = new class extends ArrayStore
    {
        public function lock($name, $seconds = 0, $owner = null): ArrayLock
        {
            return new class($this, $name, $seconds, $owner) extends ArrayLock
            {
                public function release(): bool
                {
                    throw new RuntimeException('cache_locks does not exist');
                }
            };
        }
    };

    $executor = app()->make(CommandExecutor::class, [
        'cache'            => new Repository($store),
        'registerShutdown' => static function (): void {},
    ]);

    $execution = $executor->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'x']));

    expect($execution->result->succeeded())->toBeTrue()
        ->and(CommandRun::query()->find($execution->context->runId)?->status)->toBe(RunStatus::Succeeded);
});

// AUI-08: tidy ignored the exit code of every command it called, so a migrate:fresh that failed
// printed "Database refreshed." and the panel recorded the run as succeeded.
function failingStandIn(string $name): void
{
    $command = new class($name) extends Command
    {
        public function __construct(string $name)
        {
            $this->signature = $name . ' {--force} {--seed}';

            parent::__construct();
        }

        public function handle(): int
        {
            return self::FAILURE;
        }
    };

    $command->setLaravel(app());
    app(Kernel::class)->registerCommand($command);
}

it('fails the db action when a command it runs fails', function (string $failing, array $options): void {
    failingStandIn($failing);

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'db', '--force' => true, ...$options])
        ->doesntExpectOutputToContain('Database refreshed.')
        ->expectsOutputToContain("{$failing} failed")
        ->assertExitCode(1);
})->with([
    'migrate:fresh' => ['migrate:fresh', []],
    'db:seed'       => ['db:seed', ['--seed' => true]],
]);

it('fails the cache action when a command it runs fails', function (string $failing, array $options): void {
    failingStandIn($failing);

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'cache', '--force' => true, ...$options])
        ->doesntExpectOutputToContain('Cache tidied.')
        ->expectsOutputToContain("{$failing} failed")
        ->assertExitCode(1);
})->with([
    'cache:clear'    => ['cache:clear', []],
    'optimize:clear' => ['optimize:clear', ['--optimize' => true]],
]);

// AUI-10: once a command has run, Symfony merges the application definition into it, which adds
// the application's own `command` argument. In a worker whose kernel outlives the request, the
// next listing then showed a required `command` argument the form would pass to Kernel::call.
it('never lists the application command argument, even after the command has run', function (): void {
    $kernel = app(Kernel::class);
    $kernel->call('lau-fixture:echo', ['text' => 'x']);

    $command = $kernel->all()['lau-fixture:echo'];
    $definition = CommandDefinition::fromCommand($command);

    expect($command->getDefinition()->hasArgument('command'))->toBeTrue('the precondition: Symfony merged it in')
        ->and(array_map(static fn (ArgumentDefinition $argument): string => $argument->name, $definition->arguments))->not->toContain('command');
});

// AUI-07: the `log` audit driver writes to the default channel, which is a file under
// storage/logs; `tidy logs` and the "Delete old logs" quick action deleted it with the rest.
it('keeps the file the audit trail is written to when tidying logs', function (string $driver, string $channel): void {
    config()->set('laranail.artisan-ui.audit.driver', 'log');
    config()->set('logging.default', 'null');
    config()->set('laranail.artisan-ui.audit.channel', 'audit');
    config()->set('logging.channels.audit', ['driver' => $driver, 'path' => storage_path('logs/artisan-audit.log')]);

    $audit = tidyStorageFile($channel, 'run started');
    $other = tidyStorageFile('logs/worker.log', 'noise');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
        ->expectsOutputToContain('audit log')
        ->assertExitCode(0);

    expect($audit)->toBeFile('the audit trail was deleted')
        ->and($other)->not->toBeFile();
})->with([
    'single channel' => ['single', 'logs/artisan-audit.log'],
    'daily channel'  => ['daily', 'logs/artisan-audit-2026-11-25.log'],
]);

it('follows a stack channel to the file behind it', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'log');
    config()->set('laranail.artisan-ui.audit.channel');
    config()->set('logging.default', 'stack');
    config()->set('logging.channels.stack', ['driver' => 'stack', 'channels' => ['file']]);
    config()->set('logging.channels.file', ['driver' => 'single', 'path' => storage_path('logs/app.log')]);

    $audit = tidyStorageFile('logs/app.log', 'run started');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])->assertExitCode(0);

    expect($audit)->toBeFile();
});

it('sweeps every log when nothing is audited', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'none');
    config()->set('logging.default', 'null');
    config()->set('logging.channels.file', ['driver' => 'single', 'path' => storage_path('logs/app.log')]);
    config()->set('laranail.artisan-ui.audit.channel', 'file');

    $log = tidyStorageFile('logs/app.log', 'noise');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])->assertExitCode(0);

    expect($log)->not->toBeFile();
});

// AUI-06: run locks live in the cache store, and cache:clear, optimize:clear and the tidy cache,
// temp and all actions flush it. Unless the store keeps its locks apart, a Safe command can drop
// a running destructive command's lock and let a second one start alongside it.
function doctorCheck(string $name): array
{
    Artisan::call('laranail::artisan-ui.doctor', ['--json' => true]);

    return collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('name', $name);
}

it('warns when run locks share the cache data cache:clear flushes', function (): void {
    config()->set('cache.default', 'file');
    config()->set('cache.stores.file', ['driver' => 'file', 'path' => storage_path('framework/cache/data')]);

    $check = doctorCheck('Run locks');

    expect($check['status'])->toBe('warn')
        ->and($check['message'])->toContain('cache:clear');
});

it('passes when the cache store keeps its locks apart', function (): void {
    config()->set('cache.default', 'file');
    config()->set('cache.stores.file', [
        'driver'    => 'file',
        'path'      => storage_path('framework/cache/data'),
        'lock_path' => storage_path('framework/cache/locks'),
    ]);

    expect(doctorCheck('Run locks')['status'])->toBe('pass');
});
