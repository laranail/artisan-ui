<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;

it('classifies the framework defaults', function (string $command, CommandRisk $risk): void {
    expect(app(RiskClassifier::class)->classify($command))->toBe($risk);
})->with([
    ['about', CommandRisk::Safe],
    ['cache:clear', CommandRisk::Safe],
    ['migrate:status', CommandRisk::Safe],
    ['migrate', CommandRisk::Destructive],
    ['migrate:fresh', CommandRisk::Destructive],
    ['db:wipe', CommandRisk::Destructive],
    ['key:generate', CommandRisk::Destructive],
    ['down', CommandRisk::Destructive],
    ['make:model', CommandRisk::WritesFiles],
    ['vendor:publish', CommandRisk::WritesFiles],
    ['storage:link', CommandRisk::WritesFiles],
    ['serve', CommandRisk::Forbidden],
    ['tinker', CommandRisk::Forbidden],
    ['queue:work', CommandRisk::Forbidden],
    ['octane:start', CommandRisk::Forbidden],
    ['config:show', CommandRisk::Forbidden],
]);

it('merges configuration over the defaults rather than replacing them', function (): void {
    config()->set('laranail.artisan-ui.risk.destructive', ['app:purge*']);

    $classifier = app(RiskClassifier::class);

    expect($classifier->classify('app:purge-users'))->toBe(CommandRisk::Destructive)
        ->and($classifier->classify('db:wipe'))->toBe(CommandRisk::Destructive);
});

it('lets a safe entry carve an exception out of a broader pattern', function (): void {
    config()->set('laranail.artisan-ui.risk.safe', ['make:test']);

    expect(app(RiskClassifier::class)->classify('make:test'))->toBe(CommandRisk::Safe)
        ->and(app(RiskClassifier::class)->classify('make:model'))->toBe(CommandRisk::WritesFiles);
});

it('lets an application forbid its own commands', function (): void {
    config()->set('laranail.artisan-ui.risk.forbidden', ['app:daemon']);

    expect(app(RiskClassifier::class)->classify('app:daemon'))->toBe(CommandRisk::Forbidden);
});

it('classifies the tidy command destructive, as it deletes files and its db action runs migrate:fresh', function (): void {
    expect(app(RiskClassifier::class)->classify('laranail::artisan-ui.tidy'))->toBe(CommandRisk::Destructive)
        ->and(app(RiskClassifier::class)->classify('laranail::artisan-ui.tidy'))->toBe(app(RiskClassifier::class)->classify('migrate:fresh'))
        ->and(app(RiskClassifier::class)->classify('clear-compiled'))->toBe(CommandRisk::Safe);
});

it('keeps tidy destructive even when an application lists it as writes-files', function (): void {
    config()->set('laranail.artisan-ui.risk.writes_files', ['laranail::artisan-ui.*']);

    expect(app(RiskClassifier::class)->classify('laranail::artisan-ui.tidy'))->toBe(CommandRisk::Destructive);
});

/*
 * AUI-02 and AUI-03: anything on no list is Safe, so a command that destroys data and was
 * simply never listed runs with no confirmation at all. `schema:dump --prune` deletes every
 * migration file. Each row below names a command that destroys data, keys or files, and
 * must never fall through to the Safe default.
 */
it('never lets a data-destroying command fall through to Safe', function (string $command): void {
    expect(app(RiskClassifier::class)->classify($command))->toBe(CommandRisk::Destructive);
})->with([
    'schema:dump (--prune deletes migrations)'   => 'schema:dump',
    'this package\'s installer'                  => 'laranail::artisan-ui.install',
    'another package\'s vendor-scoped installer' => 'acme::billing.install',
    'a colon-style installer'                    => 'filament:install',
    'telescope:clear'                            => 'telescope:clear',
    'telescope:prune'                            => 'telescope:prune',
    'pulse:clear'                                => 'pulse:clear',
    'pulse:purge'                                => 'pulse:purge',
    'scout:flush'                                => 'scout:flush',
    'scout:delete-index'                         => 'scout:delete-index',
    'scout:delete-all-indexes'                   => 'scout:delete-all-indexes',
    'passport:keys (--force replaces the keys)'  => 'passport:keys',
    'test (can refresh a database)'              => 'test',
]);

it('still leaves the read-only and regenerable framework commands Safe', function (string $command): void {
    expect(app(RiskClassifier::class)->classify($command))->toBe(CommandRisk::Safe);
})->with(['about', 'route:list', 'cache:clear', 'optimize:clear', 'migrate:status', 'schedule:list']);
