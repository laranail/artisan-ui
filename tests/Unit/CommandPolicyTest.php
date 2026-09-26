<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Core\Policy\CommandPolicy;

it('lists every non-forbidden command by default', function (): void {
    $policy = app(CommandPolicy::class);

    expect($policy->isListed('about'))->toBeTrue()
        ->and($policy->isListed('migrate'))->toBeTrue()
        ->and($policy->isListed('serve'))->toBeFalse();
});

it('restricts to the allow list when one is set', function (): void {
    config()->set('laranail.artisan-ui.commands.allow', ['cache:*', 'about']);

    $policy = app(CommandPolicy::class);

    expect($policy->isListed('about'))->toBeTrue()
        ->and($policy->isListed('cache:clear'))->toBeTrue()
        ->and($policy->isListed('migrate'))->toBeFalse();
});

it('treats an empty allow list as allowing nothing', function (): void {
    config()->set('laranail.artisan-ui.commands.allow', []);

    expect(app(CommandPolicy::class)->isListed('about'))->toBeFalse();
});

it('lets the deny list win over the allow list', function (): void {
    config()->set('laranail.artisan-ui.commands.allow', ['cache:*']);
    config()->set('laranail.artisan-ui.commands.deny', ['cache:clear']);

    expect(app(CommandPolicy::class)->isListed('cache:clear'))->toBeFalse();
});

it('denies the bare command with a namespace pattern', function (): void {
    config()->set('laranail.artisan-ui.commands.deny', ['migrate:*']);

    expect(app(CommandPolicy::class)->isListed('migrate'))->toBeFalse();
});

it('hides hidden commands unless configured otherwise', function (): void {
    expect(app(CommandPolicy::class)->isListed('about', hidden: true))->toBeFalse();

    config()->set('laranail.artisan-ui.commands.include_hidden', true);

    expect(app(CommandPolicy::class)->isListed('about', hidden: true))->toBeTrue();
});
