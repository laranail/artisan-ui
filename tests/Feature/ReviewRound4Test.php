<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayLock;
use Illuminate\Console\Command;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunGuard;
use Simtabi\Laranail\ArtisanUI\Core\Events\AccessDenied;
use Simtabi\Laranail\ArtisanUI\Core\Presets\PresetGroup;
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Presets\PresetCatalog;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Execution\CommandExecutor;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Environment\EnvironmentInfo;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\ArgumentDefinition;

/*
 * Regressions from the 2026-11-25 audit (design folder run-2026-11-25/FINDINGS.md).
 */

// Several tests here delete files under storage/logs, laravel.log included. On Windows a file with a
// pending delete cannot be reopened, so the next log write throws; log to the null channel unless a
// test sets its own (the tidy test files do the same).
beforeEach(function (): void {
    config()->set('logging.default', 'null');
});

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

// AUI-16: the asset route answered 200 to anyone while the panel was enabled, from any address
// and in any environment, which confirmed the panel was there where every other route says 403.
it('answers a bare 404 for assets outside the allowed network or environment', function (string $setting, mixed $value): void {
    config()->set($setting, $value);
    $file = Assets::SCRIPT;

    $response = $this->get(route('laranail-artisan-ui.asset', ['file' => $file]));

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('artisan-ui');
})->with([
    'outside allowed_ips'  => ['laranail.artisan-ui.allowed_ips', ['10.0.0.0/8']],
    'outside environments' => ['laranail.artisan-ui.environments', ['staging']],
]);

// AUI-17: CSRF protection comes only from the configurable `middleware` list. Removing `web`
// (or listing a group without the CSRF middleware) silently removed it from a panel that runs
// commands on POST.
it('fails the doctor when the panel middleware has no CSRF protection', function (): void {
    config()->set('laranail.artisan-ui.middleware', ['api']);

    $check = doctorCheck('CSRF protection');

    expect($check['status'])->toBe('fail')
        ->and($check['message'])->toContain('CSRF');
});

it('passes the CSRF doctor check with the web group', function (): void {
    expect(doctorCheck('CSRF protection')['status'])->toBe('pass');
});

// AUI-14: a wrong password before a destructive command was throttled but left no trace, so a
// guessing attempt was invisible to anyone watching the AccessDenied event or the log.
it('records a failed password confirmation as an access denial', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
    Event::fake([AccessDenied::class]);

    $this->postJson(route('laranail-artisan-ui.confirm-password'), ['password' => TestCredentials::password('wrong')])
        ->assertStatus(422);

    Event::assertDispatched(
        AccessDenied::class,
        static fn ($event): bool => $event->reason === 'password',
    );
});

// AUI-15: a guard with no user provider (a custom guard) made the confirmation a fatal 500.
it('refuses a password confirmation cleanly when the guard has no user provider', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());

    Auth::extend('providerless', static fn ($app, $name, array $config): Guard => new class($app['auth']->guard('web')) implements Guard
    {
        use GuardHelpers;

        public function __construct(private Guard $inner) {}

        public function user()
        {
            return $this->inner->user();
        }

        public function validate(array $credentials = [])
        {
            return false;
        }

        public function getProvider(): null
        {
            return null;
        }
    });
    config()->set('auth.guards.providerless', ['driver' => 'providerless']);
    config()->set('laranail.artisan-ui.guard', 'providerless');

    $this->postJson(route('laranail-artisan-ui.confirm-password'), ['password' => TestCredentials::password()])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

// AUI-19: with the database driver and no table, the start fell back to the log, and the finish
// fell back again and logged "run started" a second time, so one run read as two.
it('logs the start of a run once when the database recorder falls back at both ends', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    config()->set('laranail.artisan-ui.audit.channel', 'audit-file');
    config()->set('logging.channels.audit-file', ['driver' => 'single', 'path' => storage_path('logs/lau-fallback.log')]);
    @unlink(storage_path('logs/lau-fallback.log'));

    app(CommandExecutor::class)->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'x']));

    $log = (string) file_get_contents(storage_path('logs/lau-fallback.log'));
    @unlink(storage_path('logs/lau-fallback.log'));

    expect(substr_count($log, 'run started'))->toBe(1)
        ->and(substr_count($log, 'run finished'))->toBe(1);
});

// AUI-22: the command links' focus style was a near-white background on white (about 1.04:1),
// which fails WCAG 2.4.7 and 1.4.11; a keyboard user could not see where they were.
// AUI-23: risk help was only in a title attribute, which screen readers and touch users miss.
it('gives command links a visible focus ring and risk badges readable help', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());

    $html = (string) $this->get(route('laranail-artisan-ui.home'))->assertOk()->getContent();

    expect($html)->toMatch('/<li data-lau-command[^>]*>\s*<a [^>]*focus-visible:outline-2/')
        ->and($html)->not->toMatch('/<li data-lau-command[^>]*>\s*<a [^>]*focus:outline-none/');

    $detail = (string) $this->get(route('laranail-artisan-ui.detail', 'lau-fixture:wipe'))->assertOk()->getContent();

    expect($detail)->toContain('<span class="sr-only">');
});

// AUI-33: every Access holder saw the environment name in the header, while the environment
// panel that shows the same fact is gated by ViewEnvironment.
it('shows the environment in the header only to those who may view the environment', function (bool $allowed): void {
    Gate::define(Ability::Access->value, static fn (): bool => true);
    Gate::define(Ability::ViewEnvironment->value, static fn (): bool => $allowed);
    $this->actingAs($this->makeUser());

    $html = (string) $this->get(route('laranail-artisan-ui.home'))->assertOk()->getContent();

    expect(str_contains($html, 'data-lau-header-environment'))->toBe($allowed);
})->with([true, false]);

// AUI-34: the environment panel opened a database connection on every page load to read the
// server version, so a slow database stalled the panel.
it('reads the environment snapshot without opening a database connection', function (): void {
    // A connection nothing has opened yet: the snapshot must leave it closed.
    config()->set('database.connections.lau-probe', ['driver' => 'sqlite', 'database' => ':memory:']);
    $default = config('database.default');
    config()->set('database.default', 'lau-probe');

    $snapshot = app(EnvironmentInfo::class)->snapshot();
    $opened = array_key_exists('lau-probe', DB::getConnections());
    config()->set('database.default', $default);

    expect($opened)->toBeFalse()
        ->and(collect($snapshot)->firstWhere('label', 'Database')['value'])->toBe('sqlite');
});

// AUI-24: quick-action and environment labels were hard-coded English. They render through the
// translator now, keyed by the English text, so an application translates them in its own
// lang/<locale>.json and everything else is unchanged.
it('translates quick-action and environment labels through the application JSON translations', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
    app('translator')->addLines(['*.Caches' => 'Cachés', '*.Optimize' => 'Optimiser', '*.Debug mode' => 'Mode débogage'], 'fr', '*');
    app()->setLocale('fr');

    $html = (string) $this->get(route('laranail-artisan-ui.home'))->assertOk()->getContent();

    expect($html)->toContain('Cachés')
        ->and($html)->toContain('Optimiser')
        ->and($html)->toContain('Mode débogage');
});

// AUI-13: the history page's Rerun link carried the recorded input in the query string, where it
// reached access logs and browser history. It carries the run id now, and the form is filled on the
// server from the recorded (already redacted) input.
it('reruns from history by run id, filling the form on the server', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();

    $run = app(CommandExecutor::class)->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'from-history']));

    $history = (string) $this->get(route('laranail-artisan-ui.history'))->assertOk()->getContent();
    $link = route('laranail-artisan-ui.detail', ['command' => 'lau-fixture:echo', 'rerun' => $run->context->runId]);

    expect($history)->toContain(e($link))
        ->and($history)->not->toContain('arguments%5Btext%5D=from-history');

    expect((string) $this->get($link)->assertOk()->getContent())->toContain('value="from-history"');
});

it('ignores a rerun id for another command or without the history ability', function (bool $mismatch): void {
    allowEverything();
    $this->actingAs($this->makeUser());
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();
    $run = app(CommandExecutor::class)->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'from-history']));

    if (! $mismatch) {
        Gate::define(Ability::ViewHistory->value, static fn (): bool => false);
    }

    $html = (string) $this->get(route('laranail-artisan-ui.detail', [
        'command' => $mismatch ? 'lau-fixture:buffers' : 'lau-fixture:echo',
        'rerun'   => $run->context->runId,
    ]))->assertOk()->getContent();

    expect($html)->not->toContain('from-history');
})->with(['another command' => true, 'no history ability' => false]);

it('fills a recorded flag back in when rerunning by id', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();
    $run = app(CommandExecutor::class)->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'x'], ['shout' => true]));

    $html = (string) $this->get(route('laranail-artisan-ui.detail', ['command' => 'lau-fixture:echo', 'rerun' => $run->context->runId]))->assertOk()->getContent();

    expect($html)->toMatch('/name="options\[shout\]"[^>]*checked/');
});

// AUI-11: History showed each failed run's exception message to every view-history holder, while
// the live response deliberately shows only a run id. The message is server detail, so it now takes
// the view-environment ability; others see the run id.
it('shows a failed run\'s error in history only to those who may view the environment', function (bool $allowed): void {
    allowEverything();
    Gate::define(Ability::ViewEnvironment->value, static fn (): bool => $allowed);
    $this->actingAs($this->makeUser());
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();

    CommandRun::query()->create([
        'id'   => '01ERRORRUN0000000000000000', 'command' => 'lau-fixture:echo', 'arguments' => [], 'options' => [],
        'risk' => 'safe', 'status' => 'errored', 'error' => 'SQLSTATE: connection to db-internal-7 refused', 'started_at' => now(),
    ]);

    $html = (string) $this->get(route('laranail-artisan-ui.history'))->assertOk()->getContent();

    expect(str_contains($html, 'db-internal-7'))->toBe($allowed)
        ->and($html)->toContain('01ERRORRUN0000000000000000');
})->with(['with view-environment' => true, 'without it' => false]);

// AUI-12: history showed every user's runs with only a user id, which is ambiguous across user
// types. A "Mine" filter narrows it to the viewer's own runs; the default stays every run.
it('filters history to the viewer\'s own runs and names the user type', function (): void {
    allowEverything();
    $me = $this->makeUser();
    $this->actingAs($me);
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();

    $row = static fn (string $id, ?string $userId, string $text): array => [
        'id'        => $id, 'user_type' => $me::class, 'user_id' => $userId, 'command' => 'lau-fixture:echo',
        'arguments' => ['text' => $text], 'options' => [], 'risk' => 'safe', 'status' => 'succeeded', 'started_at' => now(),
    ];
    CommandRun::query()->create($row('01MINE00000000000000000000', (string) $me->getAuthIdentifier(), 'mine-run'));
    CommandRun::query()->create($row('01THEIRS000000000000000000', '999999', 'their-run'));

    $all = (string) $this->get(route('laranail-artisan-ui.history'))->assertOk()->getContent();
    $mine = (string) $this->get(route('laranail-artisan-ui.history', ['mine' => '1']))->assertOk()->getContent();

    expect($all)->toContain('01THEIRS000000000000000000')->toContain('01MINE00000000000000000000')
        ->and($mine)->toContain('01MINE00000000000000000000')->not->toContain('01THEIRS000000000000000000')
        ->and($mine)->toMatch('/name="mine"[^>]*checked/')
        ->and($all)->toContain(class_basename($me::class));
});

// AUI-26: tidy is destructive as a whole, so even its Preview quick actions (--dry-run) asked for
// the typed name and a password. A tidy run with --dry-run deletes nothing, so it runs as Safe; any
// other tidy run still confirms, and another command's --dry-run is not trusted.
it('runs a tidy preview without the destructive confirmation', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());

    $this->postJson(route('laranail-artisan-ui.execute', 'laranail::artisan-ui.tidy'), [
        'arguments' => ['action' => 'logs'],
        'options'   => ['dry-run' => '1', 'days' => '7'],
    ])->assertOk()->assertJson(['success' => true]);
});

it('still confirms a tidy run that is not a preview', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());

    $this->postJson(route('laranail-artisan-ui.execute', 'laranail::artisan-ui.tidy'), [
        'arguments' => ['action' => 'logs'],
        'options'   => ['days' => '7', 'force' => '1'],
    ])->assertStatus(422)->assertJsonValidationErrors(['confirm']);
});

it('classifies by input only for the commands whose preview flag it knows', function (): void {
    $classifier = app(RiskClassifier::class);
    $preview = new ValidatedInput([], ['dry-run' => true]);

    expect($classifier->classifyRun('laranail::artisan-ui.tidy', $preview))->toBe(CommandRisk::Safe)
        ->and($classifier->classifyRun('laranail::artisan-ui.tidy', new ValidatedInput))->toBe(CommandRisk::Destructive)
        ->and($classifier->classifyRun('migrate:fresh', $preview))->toBe(CommandRisk::Destructive)
        ->and($classifier->classify('laranail::artisan-ui.tidy'))->toBe(CommandRisk::Destructive);
});

/*
 * Independent review of this branch (2026-11-25), R-1 to R-8.
 */

// R-1: `cache:clear` is Safe, but `--locks` flushes the lock store itself, separate or not, so a
// Safe user could drop a running destructive command's lock. That one run takes the destructive class.
it('classifies cache:clear --locks as destructive for that run', function (): void {
    $classifier = app(RiskClassifier::class);

    expect($classifier->classifyRun('cache:clear', new ValidatedInput([], ['locks' => true])))->toBe(CommandRisk::Destructive)
        ->and($classifier->classifyRun('cache:clear', new ValidatedInput))->toBe(CommandRisk::Safe);
});

// R-2: tidy read the raw audit.channel, while the recorder treats '' as "the default channel";
// LARANAIL_ARTISAN_UI_AUDIT_CHANNEL= (set but empty) protected nothing.
it('protects the default channel when the audit channel is set but empty', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'log');
    config()->set('laranail.artisan-ui.audit.channel', '');
    config()->set('logging.default', 'file');
    config()->set('logging.channels.file', ['driver' => 'single', 'path' => storage_path('logs/app.log')]);

    $audit = tidyStorageFile('logs/app.log', 'run started');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])->assertExitCode(0);

    expect($audit)->toBeFile();
});

// R-4: with the database driver and its table gone (migrate:fresh), a ?rerun= link threw.
it('falls back to the query-string prefill when the run table is missing', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
    config()->set('laranail.artisan-ui.audit.driver', 'database');

    $this->get(route('laranail-artisan-ui.detail', ['command' => 'lau-fixture:echo', 'rerun' => '01NOTABLE00000000000000000', 'arguments' => ['text' => 'fallback']]))
        ->assertOk()
        ->assertSee('value="fallback"', false);
});

// R-6: a label equal to a translation group name (`auth`, `validation`) made __() return an array,
// and the page a 500.
it('renders a quick-action label that names a translation group as written', function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
    app(PresetCatalog::class)->register(
        new PresetGroup('mine', 'validation', [
            new QuickAction('auth', 'about', [], []),
        ]),
    );

    $html = (string) $this->get(route('laranail-artisan-ui.home'))->assertOk()->getContent();

    expect($html)->toContain('>validation</summary>');
});

// R-3: outside the allowlists the asset route answers 404, so the 403 page shown there must not
// link a stylesheet it cannot load.
it('renders the network-refused page without the stylesheet it cannot load', function (): void {
    config()->set('laranail.artisan-ui.allowed_ips', ['10.0.0.0/8']);

    $html = (string) $this->get(route('laranail-artisan-ui.home'))->assertForbidden()->getContent();

    expect($html)->not->toContain('rel="stylesheet"');
});
