<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunGuard;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Execution\CommandExecutor;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandRunFailed;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\RedactedException;

/**
 * Regressions for the second security review. Each test names the defect it pins.
 */
it('scrubs output with long runs of blank lines in linear time (quadratic regex)', function (): void {
    $text = str_repeat("\n", 200_000) . "DB_PASSWORD=hunter2\n";

    $started = microtime(true);
    $out = app(SecretRedactor::class)->scrub($text);
    $elapsed = microtime(true) - $started;

    expect($elapsed)->toBeLessThan(1.0)
        ->and($out)->toContain('DB_PASSWORD=••••••••');
});

it('captures echo/print into the run output, capped and redacted', function (): void {
    allowEverything();
    $_ENV['LAU_FIXTURE_PASSWORD'] = 'echoed-secret-value';

    $response = $this->actingAs($this->makeUser())->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo-raw'));

    unset($_ENV['LAU_FIXTURE_PASSWORD']);

    $text = implode('', array_column((array) $response->json('output'), 'text'));

    expect($text)->toContain('raw password=')->toContain('via line')->not->toContain('echoed-secret-value')
        ->and((string) $response->getContent())->toStartWith('{');
});

it('forbids framework internals and treats scheduler/queue re-runs as destructive (hidden commands)', function (): void {
    $risk = app(RiskClassifier::class);

    expect($risk->classify('invoke-serialized-closure'))->toBe(CommandRisk::Forbidden)
        ->and($risk->classify('schedule:finish'))->toBe(CommandRisk::Forbidden)
        ->and($risk->classify('schedule:test'))->toBe(CommandRisk::Destructive)
        ->and($risk->classify('queue:retry'))->toBe(CommandRisk::Destructive)
        ->and($risk->classify('env:encrypt'))->toBe(CommandRisk::Destructive);

    config()->set('laranail.artisan-ui.commands.include_hidden', true);

    expect(app(CommandRegistry::class)->has('invoke-serialized-closure'))->toBeFalse();
});

it('reports a redacted copy of the exception chain, keeping the original class (leaked DSN)', function (): void {
    Exceptions::fake();
    allowEverything();

    $this->actingAs($this->makeUser())->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:throw'))->assertOk();

    Exceptions::assertReported(static function (CommandRunFailed $e): bool {
        $cause = $e->getPrevious();

        return $cause instanceof RedactedException
            && $cause->originalClass === RuntimeException::class
            && ! str_contains($cause->getMessage(), 'hunter22')
            && str_contains($cause->getMessage(), '••••••••')
            && $e->context()['actual'] === 'threw RuntimeException';
    });
});

it('records an abandoned run as errored and frees its lock (fatal skips finally)', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();

    $executor = app(CommandExecutor::class);
    $definition = app(CommandRegistry::class)->find('lau-fixture:echo');
    $lock = Cache::lock('laranail-artisan-ui:run:lau-fixture:echo', 60);
    $lock->get();

    $context = new RunContext(
        '01ABANDONED000000000000000',
        $definition,
        new ValidatedInput(['text' => 'x']),
        ['arguments' => [], 'options' => []],
        CommandRisk::Safe,
        null,
        null,
        CarbonImmutable::now(),
    );
    app(RunRecorder::class)->started($context);

    $executor->abandon($context, $lock);

    expect(CommandRun::query()->find('01ABANDONED000000000000000')?->status)->toBe(RunStatus::Errored)
        ->and(Cache::lock('laranail-artisan-ui:run:lau-fixture:echo', 60)->get())->toBeTrue();
});

it('masks the head of a secret cut in half at the output cap', function (): void {
    $_ENV['LAU_TEST_API_TOKEN'] = 'tok_ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    $out = new SecretRedactor(app(ArtisanUIConfig::class))
        ->scrub('token: tok_ABCDEFGHIJKL', truncated: true);

    unset($_ENV['LAU_TEST_API_TOKEN']);

    expect($out)->not->toContain('tok_ABCD')->toContain('••••••••');
});

it('scrubs secrets that live only in configuration, as under config:cache', function (): void {
    config()->set('services.acme.secret', 'config-only-secret-value');

    expect(app(SecretRedactor::class)->scrub('the key is config-only-secret-value'))
        ->not->toContain('config-only-secret-value');
});

it('answers a guest from a disallowed address with 403, not a login redirect that reveals the panel', function (): void {
    config()->set('laranail.artisan-ui.allowed_ips', ['10.0.0.0/8']);

    $this->get(route('laranail-artisan-ui.home'))->assertForbidden();
});

it('leaves a successful run alone at shutdown; only an unfinished one is abandoned', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();

    $registered = [];
    $executor = app()->make(CommandExecutor::class, [
        'registerShutdown' => static function (callable $callback) use (&$registered): void {
            $registered[] = $callback;
        },
    ]);

    $execution = $executor->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'x']));

    // What PHP would do when the request ends.
    foreach ($registered as $callback) {
        $callback();
    }

    expect($registered)->toHaveCount(1)
        ->and(CommandRun::query()->find($execution->context->runId)?->status)->toBe(RunStatus::Succeeded);
});

it('abandons a run whose recording never finished', function (): void {
    $calls = 0;
    $guard = new RunGuard(static function () use (&$calls): void {
        $calls++;
    });

    $guard();
    $guard();

    expect($calls)->toBe(1);

    $disarmed = new RunGuard(static function () use (&$calls): void {
        $calls++;
    });
    $disarmed->disarm();
    $disarmed();

    expect($calls)->toBe(1);
});
