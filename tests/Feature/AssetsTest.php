<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets;

it('serves the built script and stylesheet immutably, with an ETag', function (string $file, string $type): void {
    $response = $this->get(route('laranail-artisan-ui.asset', ['file' => $file]));

    $response->assertOk()
        ->assertHeader('Content-Type', $type)
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect((string) $response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->headers->get('ETag'))->not->toBeNull();
})->with([
    [Assets::SCRIPT, 'application/javascript; charset=utf-8'],
    [Assets::STYLE, 'text/css; charset=utf-8'],
]);

it('serves nothing but the two known files', function (): void {
    $this->get('/vendor/laranail-artisan-ui/..%2F..%2Fcomposer.json')->assertNotFound();
    $this->get('/vendor/laranail-artisan-ui/other.js')->assertNotFound();
});

it('puts a content hash in the URL so a new build is a new URL', function (): void {
    $url = app(Assets::class)->url(Assets::SCRIPT);

    expect($url)->toMatch('/[?&]id=[0-9a-f]{12}/');
});

it('ships a bundle with no inline-HTML sink', function (): void {
    $bundle = (string) file_get_contents(app(Assets::class)->path(Assets::SCRIPT));

    expect($bundle)->not->toContain('innerHTML')->not->toContain('insertAdjacentHTML')->not->toContain('eval(');
});
