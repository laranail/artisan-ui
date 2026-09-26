<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Core\Validation\InputValidator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\OptionDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\ArgumentDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\InvalidCommandInput;

function definitionForValidation(): CommandDefinition
{
    return new CommandDefinition(
        name: 'demo:thing',
        description: '',
        help: '',
        synopsis: '',
        hidden: false,
        aliases: [],
        arguments: [
            new ArgumentDefinition('name', '', true, false, null),
            new ArgumentDefinition('extra', '', false, true, []),
        ],
        options: [
            new OptionDefinition('force', '', false, false, false, null, false, false),
            new OptionDefinition('step', '', true, false, null, null, true, false),
            new OptionDefinition('tag', '', true, true, [], null, true, false),
            new OptionDefinition('maybe', '', false, false, null, null, true, false),
        ],
    );
}

function validationErrors(Closure $call): array
{
    try {
        $call();
    } catch (InvalidCommandInput $e) {
        return $e->errors;
    }

    return [];
}

it('normalises valid input', function (): void {
    $input = app(InputValidator::class)->validate(definitionForValidation(), [
        'name'  => '  users ',
        'extra' => ['a', '', ' b '],
    ], [
        'force' => '1',
        'step'  => '2',
        'tag'   => 'solo',
        'maybe' => true,
    ]);

    expect($input->arguments)->toBe(['name' => 'users', 'extra' => ['a', 'b']])
        ->and($input->options)->toBe(['force' => true, 'step' => '2', 'tag' => ['solo'], 'maybe' => null])
        ->and($input->toParameters())->toBe([
            'name'    => 'users',
            'extra'   => ['a', 'b'],
            '--force' => true,
            '--step'  => '2',
            '--tag'   => ['solo'],
            '--maybe' => null,
        ]);
});

it('omits unticked flags and empty values', function (): void {
    $input = app(InputValidator::class)->validate(definitionForValidation(), ['name' => 'x'], ['force' => '0', 'step' => '']);

    expect($input->options)->toBe([]);
});

it('requires required arguments', function (): void {
    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), ['name' => '  '], []));

    expect($errors)->toHaveKey('arguments.name');
});

it('refuses keys the command does not define', function (): void {
    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), ['name' => 'x', 'nope' => 'y'], ['bogus' => '1']));

    expect($errors)->toHaveKeys(['arguments.nope', 'options.bogus']);
});

it('refuses global options such as --env, with or without dashes', function (string $key): void {
    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), ['name' => 'x'], [$key => 'production']));

    // Refused as a global option specifically, not merely as an unknown one: the definition
    // strips them too, but the explicit refusal is what survives a definition that does not.
    expect($errors['options.' . ltrim($key, '-')][0] ?? '')->toContain('The global [--' . ltrim($key, '-') . '] option');
})->with(['env', '--env', 'no-interaction', 'verbose', 'quiet', 'ansi']);

it('refuses a value on a flag', function (): void {
    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), ['name' => 'x'], ['force' => 'maybe']));

    expect($errors)->toHaveKey('options.force');
});

it('refuses non-text values', function (): void {
    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), ['name' => ['nested' => 'array']], ['step' => ['a']]));

    expect($errors)->toHaveKeys(['arguments.name', 'options.step']);
});

it('refuses a list in place of the input object', function (): void {
    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), ['x', 'y'], []));

    expect($errors)->toHaveKey('arguments');
});

it('bounds value length and list size', function (): void {
    config()->set('laranail.artisan-ui.limits.max_value_length', 5);
    config()->set('laranail.artisan-ui.limits.max_array_items', 2);

    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), [
        'name'  => 'toolong',
        'extra' => ['a', 'b', 'c'],
    ], []));

    expect($errors)->toHaveKeys(['arguments.name', 'arguments.extra']);
});

it('turns a negatable flag set to "no" into --no-name, and accepts no-name directly', function (): void {
    $definition = new CommandDefinition('demo:neg', '', '', '', false, [], [], [
        new OptionDefinition('cache', '', false, false, null, null, false, true),
    ]);

    $validator = app(InputValidator::class);

    expect($validator->validate($definition, [], ['cache' => 'no'])->toParameters())->toBe(['--no-cache' => true])
        ->and($validator->validate($definition, [], ['no-cache' => '1'])->toParameters())->toBe(['--no-cache' => true])
        ->and($validator->validate($definition, [], ['cache' => '1'])->toParameters())->toBe(['--cache' => true]);
});

it('refuses no-name for an option that is not negatable', function (): void {
    $errors = validationErrors(fn () => app(InputValidator::class)->validate(definitionForValidation(), ['name' => 'x'], ['no-force' => '1']));

    expect($errors)->toHaveKey('options.no-force');
});
