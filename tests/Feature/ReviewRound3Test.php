<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandExecuting;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Execution\CommandExecutor;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;

/**
 * Regressions for the third review round.
 */
function runFixture(string $mode, array $options = []): TestResponse
{
    return test()->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:buffers'), [
        'arguments' => ['mode' => $mode],
        'options'   => $options,
    ]);
}

function joined(TestResponse $response): string
{
    return implode('', array_column((array) $response->json('output'), 'text'));
}

beforeEach(function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
});

it('does not treat stock Laravel config as secrets (cache key, facade aliases)', function (): void {
    config()->set('app.aliases.Password', Password::class);
    config()->set('cache.stores.session.key', '_cache');

    $redactor = app(SecretRedactor::class);

    expect($redactor->scrub('create_cache_table'))->toBe('create_cache_table')
        ->and($redactor->scrub('use Illuminate\Support\Facades\Password;'))->toBe('use Illuminate\Support\Facades\Password;');
});

it('still masks real credentials that live only in config', function (): void {
    config()->set('database.connections.reporting.password', 'reporting-db-pass');

    expect(app(SecretRedactor::class)->scrub('pw reporting-db-pass'))->not->toContain('reporting-db-pass');
});

it('does not mask a word that merely starts like a secret when output is truncated', function (): void {
    expect(app(SecretRedactor::class)->scrub('Using database', truncated: true))->toBe('Using database');
});

it('keeps echo and $this->line() in the order the command wrote them', function (): void {
    expect(joined(runFixture('interleave')->assertOk()))->toBe("A\nB\nC\n");
});

it('honours ob_clean() inside a command', function (): void {
    expect(joined(runFixture('clean')->assertOk()))->toBe("kept\n");
});

it('says so when a command removes the capture buffer', function (): void {
    $text = joined(runFixture('pop')->assertOk());

    expect($text)->toContain('output after that point was not captured');
});

it('sends negatable and valueless options through to the command', function (): void {
    expect(joined(runFixture('options', ['cache' => 'no', 'label' => true])->assertOk()))->toContain('label=NULL cache=false')
        ->and(joined(runFixture('options', ['cache' => '1', 'label' => 'x'])->assertOk()))->toContain("label='x' cache=true");
});

it('prefills a rerun of a negated option and a valueless one', function (): void {
    $html = (string) $this->get(route('laranail-artisan-ui.detail', [
        'command'   => 'lau-fixture:buffers',
        'arguments' => ['mode' => 'options'],
        'options'   => ['no-cache' => '1', 'label' => ''],
    ]))->assertOk()->getContent();

    expect($html)->toMatch('/<option value="no"\s+selected/')
        ->toMatch('/name="options-present\[label\]"[^>]*checked/');
});

it('closes out a run at once when a listener throws before it was recorded as finished', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    (require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub')->up();

    Event::listen(CommandExecuting::class, static function (): never {
        throw new RuntimeException('listener broke');
    });

    try {
        app(CommandExecutor::class)->execute(app(CommandRegistry::class)->find('lau-fixture:echo'), new ValidatedInput(['text' => 'x']));
    } catch (RuntimeException) {
    }

    expect(CommandRun::query()->latest('created_at')->first()?->status)->toBe(RunStatus::Errored);
});

it('does not cap a lock lifetime the operator configured above the time limit', function (): void {
    config()->set('laranail.artisan-ui.limits.lock_seconds', 3600);

    $method = new ReflectionMethod(CommandExecutor::class, 'lockSeconds');

    expect($method->invoke(app(CommandExecutor::class)))->toBe(3600);

    config()->set('laranail.artisan-ui.limits.lock_seconds', 5);

    expect($method->invoke(app(CommandExecutor::class)))->toBe(130);
});

it('answers a disabled panel with a bare 404 that does not fingerprint it', function (): void {
    config()->set('laranail.artisan-ui.enabled', false);

    $html = (string) $this->get(route('laranail-artisan-ui.home'))->assertNotFound()->getContent();

    expect($html)->not->toContain('data-lau-denied')->not->toContain('laranail-artisan-ui');
});
