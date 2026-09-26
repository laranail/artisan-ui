<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Execution\Execution;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\RunPresenter;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;

function executionThrowing(Throwable $e): Execution
{
    $context = new RunContext('01RUN00000000000000000000A', new CommandDefinition('demo', '', '', '', false, [], [], []), new ValidatedInput, ['arguments' => [], 'options' => []], CommandRisk::Safe, null, null, CarbonImmutable::now());

    return new Execution($context, new RunResult(RunStatus::Errored, null, '', 1, false, $e), '');
}

it('shows a console input error, which is a validation message by contract', function (): void {
    $payload = app(RunPresenter::class)->toArray(executionThrowing(new ConsoleRuntimeException('Not enough arguments (missing: "name").')));

    expect($payload['error'])->toBe('Not enough arguments (missing: "name").');
});

it('describes any other exception by run id only, in every environment', function (bool $debug): void {
    config()->set('app.debug', $debug);

    $payload = app(RunPresenter::class)->toArray(executionThrowing(new RuntimeException('SQLSTATE secret detail')));

    expect($payload['error'])->toContain('01RUN00000000000000000000A')->not->toContain('SQLSTATE');
})->with([true, false]);
