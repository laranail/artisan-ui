<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;

it('reports the doctor checks, warning while nobody can use the panel', function (): void {
    expect(Artisan::call('laranail::artisan-ui.doctor'))->toBe(0)
        ->and(Artisan::output())->toContain('Gate abilities')->toContain('Nobody can use the panel yet');
});

it('fails the doctor when production is allowed', function (): void {
    config()->set('laranail.artisan-ui.environments', ['production']);

    $this->artisan('laranail::artisan-ui.doctor')->assertFailed();
});

it('emits doctor JSON', function (): void {
    Gate::define(Ability::Access->value, static fn (): bool => true);
    Gate::define(Ability::Run->value, static fn (): bool => true);

    $this->artisan('laranail::artisan-ui.doctor', ['--json' => true])->assertSuccessful();
});

it('shows the effective policy for every command', function (): void {
    config()->set('laranail.artisan-ui.commands.deny', ['lau-fixture:secret']);

    expect(Artisan::call('laranail::artisan-ui.policy', ['--json' => true]))->toBe(0);

    $rows = collect(json_decode(Artisan::output(), true))->keyBy('command');

    expect($rows['lau-fixture:secret'])->toMatchArray(['listed' => false, 'reason' => 'allow/deny list'])
        ->and($rows['serve'])->toMatchArray(['listed' => false, 'risk' => 'forbidden', 'reason' => 'forbidden'])
        ->and($rows['lau-fixture:hidden'])->toMatchArray(['listed' => false, 'reason' => 'hidden'])
        ->and($rows['about'])->toMatchArray(['listed' => true, 'risk' => 'safe']);
});

it('filters the policy by risk and rejects an unknown class', function (): void {
    $this->artisan('laranail::artisan-ui.policy', ['--risk' => 'destructive'])
        ->expectsOutputToContain('lau-fixture:wipe')
        ->doesntExpectOutputToContain('lau-fixture:echo')
        ->assertSuccessful();

    $this->artisan('laranail::artisan-ui.policy', ['--risk' => 'spicy'])->assertFailed();
});

it('registers the install command under the family name', function (): void {
    expect(array_keys(Artisan::all()))->toContain('laranail::artisan-ui.install');
});

it('fails the doctor when the cache store cannot be reached', function (): void {
    config()->set('cache.default', 'database');
    config()->set('cache.stores.database', ['driver' => 'database', 'table' => 'no_such_cache_table', 'connection' => null]);

    expect(Artisan::call('laranail::artisan-ui.doctor'))->not->toBe(0)
        ->and(Artisan::output())->toContain('cannot be reached');
});
