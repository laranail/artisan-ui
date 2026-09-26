<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Cache\Repository;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\ArtisanUI\Enums\AuditDriver;
use Simtabi\Laranail\ArtisanUI\Core\Audit\NullRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Policy\PanelAccess;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Core\Output\AnsiFormatter;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Execution\CappedOutput;
use Simtabi\Laranail\ArtisanUI\Core\Output\DecoratorRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Policy\CommandAuthorizer;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Execution\CommandExecutor;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\ArgumentDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandNotRunnable;

function definitionNamed(string $name): CommandDefinition
{
    return new CommandDefinition($name, '', '', '', false, [], [], []);
}

it('refuses a forbidden command in the executor itself, behind every earlier check', function (): void {
    app(CommandExecutor::class)->execute(definitionNamed('serve'), new ValidatedInput);
})->throws(CommandNotRunnable::class);

it('refuses policy-denied and forbidden commands in the authorizer', function (): void {
    Gate::define('laranail-artisan-ui.run', static fn (): bool => true);
    config()->set('laranail.artisan-ui.commands.deny', ['about']);

    $user = new GenericUser(['id' => 1]);
    $authorizer = app(CommandAuthorizer::class);

    expect($authorizer->inspect($user, definitionNamed('about'), new ValidatedInput)->reason)->toBe('policy')
        ->and($authorizer->inspect($user, definitionNamed('tinker'), new ValidatedInput)->reason)->toBe('forbidden')
        ->and($authorizer->inspect($user, definitionNamed('cache:clear'), new ValidatedInput)->allowed)->toBeTrue();
});

it('treats `*` as every environment and an empty list as none', function (): void {
    $app = app(Application::class);

    expect(PanelAccess::environmentMatches($app, ['*']))->toBeTrue()
        ->and(PanelAccess::environmentMatches($app, []))->toBeFalse();
});

it('denies an IP check when no address is known and a list is configured', function (): void {
    config()->set('laranail.artisan-ui.allowed_ips', ['127.0.0.1']);

    expect(app(PanelAccess::class)->ipAllowed(null))->toBeFalse()
        ->and(app(PanelAccess::class)->ipAllowed('127.0.0.1'))->toBeTrue();
});

it('records nothing with the none driver', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', AuditDriver::None->value);

    expect(app(RunRecorder::class))->toBeInstanceOf(NullRecorder::class);
});

it('warns when output was truncated and when the cache cannot lock', function (): void {
    Log::spy();
    config()->set('laranail.artisan-ui.limits.max_output_bytes', 3);

    $store = Mockery::mock(Store::class);
    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('getStore')->andReturn($store);

    $registry = app(CommandRegistry::class);

    app()->make(CommandExecutor::class, ['cache' => $cache])
        ->execute($registry->find('lau-fixture:echo'), new ValidatedInput(['text' => 'longer than three']));

    Log::shouldHaveReceived('warning')->withArgs(static fn (string $m): bool => str_contains($m, 'laranail/artisan-ui:run.lock'))->once();
    Log::shouldHaveReceived('warning')->withArgs(static fn (string $m): bool => str_contains($m, 'laranail/artisan-ui:run.output'))->once();
});

it('accepts decorator instances and rejects classes that are not decorators', function (): void {
    $registry = app(DecoratorRegistry::class);
    $result = new RunResult(RunStatus::Succeeded, 0, 'x', 1);

    $registry->add('about', new class implements OutputDecorator
    {
        public function decorate(string $output, CommandDefinition $command, RunResult $result): string
        {
            return "[{$output}]";
        }
    });

    expect($registry->apply(definitionNamed('about'), $result, 'x'))->toBe('[x]');

    $registry->add('bad', stdClass::class);
    $registry->apply(definitionNamed('bad'), $result, 'x');
})->throws(InvalidArgumentException::class);

it('formats defaults for display', function (mixed $default, string $shown): void {
    $field = new ArgumentDefinition('a', '', false, is_array($default), $default);

    expect($field->defaultForDisplay())->toBe($shown)
        ->and($field->toArray()['default'])->toBe($default);
})->with([
    ['text', 'text'],
    [3, '3'],
    [['a', 'b'], 'a, b'],
    [null, ''],
    [true, ''],
]);

it('renders background colours, dim, italic and resets', function (): void {
    $segments = new AnsiFormatter()->segments("\e[41;97;2;3mA\e[22;23;49;39mB\e[104mC\e[0m");

    expect($segments)->toBe([
        ['text' => 'A', 'classes' => 'lau-fg-bright-white lau-bg-red lau-dim lau-italic'],
        ['text' => 'B', 'classes' => ''],
        ['text' => 'C', 'classes' => 'lau-bg-bright-blue'],
    ]);
});

it('stops accepting output exactly at the cap', function (): void {
    $output = new CappedOutput(5);
    $output->write('12345');
    $output->write('6');

    expect($output->fetch())->toBe('12345')->and($output->truncated())->toBeTrue();
});

it('builds quick actions from configuration arrays, ignoring malformed ones', function (): void {
    expect(QuickAction::fromArray(['label' => 'x']))->toBeNull()
        ->and(QuickAction::fromArray(['label' => 'Seed', 'command' => 'db:seed', 'options' => ['force' => true]])?->prefill())
        ->toBe(['options' => ['force' => '1']]);
});
