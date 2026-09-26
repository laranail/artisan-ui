<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;

/**
 * Every public name the package claims carries the vendor and the slug, read back from the
 * LIVE registries rather than grepped from the provider, so the guard holds whatever the
 * registration code looks like.
 *
 * Views and translations use the composer name (`laranail/artisan-ui`), because Laravel
 * interpolates it into the override path. Blade tags cannot contain a slash, so the
 * component prefix is `laranail-artisan-ui`, registered over the same paths. Commands use
 * `laranail::artisan-ui.*`; middleware aliases cannot contain `:`, so they use the hyphen.
 */
const SLASH = 'laranail/artisan-ui';
const HYPHEN = 'laranail-artisan-ui';

it('registers views under the composer name, plus the hyphen alias for Blade tags, and no bare name', function (): void {
    $hints = View::getFinder()->getHints();

    expect($hints)->toHaveKey(SLASH)
        ->toHaveKey(HYPHEN)
        ->not->toHaveKey('artisan-ui')
        ->not->toHaveKey('artisan');
});

it('registers translations under the composer name', function (): void {
    $namespaces = Lang::getLoader()->namespaces();

    expect($namespaces)->toHaveKey(SLASH)->not->toHaveKey('artisan-ui')
        ->and(__(SLASH . '::messages.run'))->toBe('Run');
});

it('reads its config at the vendor key', function (): void {
    expect(config('laranail.artisan-ui'))->toBeArray()
        ->and(config('artisan-ui'))->toBeNull();
});

it('names commands laranail::artisan-ui.*, with no bare alias', function (): void {
    $ours = array_values(array_filter(array_keys(Artisan::all()), static fn (string $n): bool => str_contains($n, 'artisan-ui')));

    expect($ours)->not->toBeEmpty();

    foreach ($ours as $name) {
        expect($name)->toStartWith('laranail::artisan-ui.');
    }
});

it('publishes under vendor-scoped tags only', function (): void {
    $ours = array_values(array_filter(ServiceProvider::publishableGroups(), static fn (string $g): bool => str_contains($g, 'artisan-ui')));

    expect($ours)->not->toBeEmpty();

    foreach ($ours as $tag) {
        expect($tag)->toStartWith('laranail::artisan-ui-');
    }
});

it('registers the middleware alias, route names, rate limiters and abilities with the hyphen prefix', function (): void {
    expect(app('router')->getMiddleware())->toHaveKey(HYPHEN)->not->toHaveKey('artisan-ui');

    $names = array_keys(Route::getRoutes()->getRoutesByName());
    $ours = array_values(array_filter($names, static fn (string $n): bool => str_contains($n, 'artisan')));

    expect($ours)->not->toBeEmpty();

    foreach ($ours as $name) {
        expect($name)->toStartWith(HYPHEN . '.');
    }

    expect(RateLimiter::limiter(HYPHEN))->not->toBeNull()
        ->and(RateLimiter::limiter(HYPHEN . '-confirm'))->not->toBeNull();

    foreach (Ability::cases() as $ability) {
        expect($ability->value)->toStartWith(HYPHEN . '.')
            ->and(Gate::has($ability->value))->toBeTrue();
    }
});

it('registers no global facade alias', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

    expect($composer['extra']['laravel'])->not->toHaveKey('aliases');
});

it('keeps Blade tags resolvable under the hyphen prefix, since a slash truncates the tag', function (): void {
    preg_match('/<\s*x[-\:]([\w\-\:\.]*)/x', '<x-laranail/artisan-ui::card />', $m);

    // Blade's own tag pattern stops at the slash: why the component prefix is hyphenated.
    expect($m[1])->toBe('laranail');
});
