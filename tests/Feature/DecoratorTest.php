<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\ThrowingDecorator;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\UppercaseDecorator;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;

beforeEach(function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
});

it('applies decorators from configuration to matching commands only', function (): void {
    config()->set('laranail.artisan-ui.decorators', ['lau-fixture:echo' => UppercaseDecorator::class]);

    $echo = $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'quiet']])->json('output');
    $other = $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:ask'))->json('output');

    expect(implode('', array_column($echo, 'text')))->toContain('QUIET')
        ->and($other)->toBe([]);
});

it('applies decorators registered at runtime through the fluent facade', function (): void {
    ArtisanUI::decorate('lau-fixture:*', UppercaseDecorator::class)
        ->quickActions('ops', 'Ops', []);

    $out = $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'quiet']])->json('output');

    expect(implode('', array_column($out, 'text')))->toContain('QUIET');
});

it('withholds the output, reports, and marks degraded when a decorator throws', function (): void {
    Exceptions::fake();
    config()->set('laranail.artisan-ui.decorators', ['lau-fixture:echo' => ThrowingDecorator::class]);

    $out = $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'secret-ish']])
        ->assertOk()
        ->json('output');

    expect(implode('', array_column($out, 'text')))->not->toContain('secret-ish')->toContain('output withheld')
        ->and(array_keys(app(BootReport::class)->degraded()))->toContain('laranail/artisan-ui:output.decorate');

    Exceptions::assertReported(static fn (PackageBootException $e): bool => $e->getPrevious() instanceof RuntimeException);
});

it('still redacts after decoration, so a decorator cannot leak a secret', function (): void {
    $_ENV['LAU_FIXTURE_PASSWORD'] = 'decorated-secret';
    config()->set('laranail.artisan-ui.decorators', ['lau-fixture:secret' => UppercaseDecorator::class]);
    $_ENV['LAU_FIXTURE_UPPER_PASSWORD'] = 'DECORATED-SECRET';

    $out = $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:secret'))->json('output');

    unset($_ENV['LAU_FIXTURE_PASSWORD'], $_ENV['LAU_FIXTURE_UPPER_PASSWORD']);

    expect(implode('', array_column($out, 'text')))->not->toContain('DECORATED-SECRET');
});
