<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Core\Support\CommandPattern;

it('matches wildcards the way Str::is does', function (string $pattern, string $name, bool $expected): void {
    expect(CommandPattern::matches($pattern, $name))->toBe($expected);
})->with([
    'exact'                 => ['about', 'about', true],
    'namespace wildcard'    => ['make:*', 'make:model', true],
    'other namespace'       => ['make:*', 'migrate', false],
    'star'                  => ['*', 'anything:at-all', true],
    'prefix is not a match' => ['queue', 'queue:work', false],
]);

it('lets a namespace pattern cover the bare command of the same name (upstream PR #9 bug)', function (): void {
    expect(CommandPattern::matches('migrate:*', 'migrate'))->toBeTrue()
        ->and(CommandPattern::matches('db:*', 'db'))->toBeTrue()
        ->and(CommandPattern::matches('db:*', 'dbx'))->toBeFalse();
});

it('matches any of a list', function (): void {
    expect(CommandPattern::any(['a', 'make:*'], 'make:job'))->toBeTrue()
        ->and(CommandPattern::any([], 'make:job'))->toBeFalse();
});
