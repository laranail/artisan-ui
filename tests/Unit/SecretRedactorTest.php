<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

beforeEach(function (): void {
    $_ENV['LAU_TEST_API_TOKEN'] = 'tok_live_0123456789';
    $_ENV['LAU_TEST_SHORT_PASSWORD'] = 'abc';
});

afterEach(function (): void {
    unset($_ENV['LAU_TEST_API_TOKEN'], $_ENV['LAU_TEST_SHORT_PASSWORD']);
});

it('scrubs the value of every secret-named environment variable wherever it appears', function (): void {
    $out = new SecretRedactor(app(ArtisanUIConfig::class))
        ->scrub('the token is tok_live_0123456789, again tok_live_0123456789');

    expect($out)->not->toContain('tok_live_0123456789')->toContain('••••••••');
});

it('does not scrub values shorter than the minimum, so "abc" is not blanked everywhere', function (): void {
    expect(app(SecretRedactor::class)->scrub('abcdef'))->toBe('abcdef');
});

it('masks NAME=value lines whose name looks secret', function (): void {
    $out = app(SecretRedactor::class)->scrub("APP_NAME=Laravel\nDB_PASSWORD=redaction-canary-not-a-secret\nexport STRIPE_SECRET: sk_x");

    expect($out)->toBe("APP_NAME=Laravel\nDB_PASSWORD=••••••••\nexport STRIPE_SECRET: ••••••••");
});

it('masks the password in credentialed URLs', function (): void {
    // Built at run time so no credentialed URL literal sits in the source.
    $password = 'redaction-canary-not-a-secret';
    $mask = '••••••••';

    expect(app(SecretRedactor::class)->scrub('redis://default:' . $password . '@cache:6379'))
        ->toBe('redis://default:' . $mask . '@cache:6379');
});

it('masks recorded input by key, and leaves flags alone', function (): void {
    $input = app(SecretRedactor::class)->redactInput([
        'arguments' => ['name' => 'users'],
        'options'   => ['password' => 'redaction-canary-not-a-secret', 'api-token' => 'x', 'force' => true],
    ]);

    expect($input)->toBe([
        'arguments' => ['name' => 'users'],
        'options'   => ['password' => '••••••••', 'api-token' => '••••••••', 'force' => true],
    ]);
});
