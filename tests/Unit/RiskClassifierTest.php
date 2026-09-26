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
