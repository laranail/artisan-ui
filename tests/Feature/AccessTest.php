<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\RouteCollection;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Core\Events\AccessDenied;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Providers\WebUIServiceProvider;

it('denies everyone until the application defines the Access ability', function (): void {
    $this->actingAs($this->makeUser())
        ->get(route('laranail-artisan-ui.home'))
        ->assertForbidden();
});

it('opens for a user the Access ability allows', function (): void {
    allowEverything();

    $this->actingAs($this->makeUser())
        ->get(route('laranail-artisan-ui.home'))
        ->assertOk()
        ->assertSee('lau-fixture:echo');
});

it('asks the Gate about the signed-in user object, not a stored credential', function (): void {
    $allowed = $this->makeUser('admin@example.com');
    $denied = $this->makeUser('guest@example.com');

    Gate::define(Ability::Access->value, static fn ($user): bool => $user->email === 'admin@example.com');

    $this->actingAs($allowed)->get(route('laranail-artisan-ui.home'))->assertOk();
    $this->actingAs($denied)->get(route('laranail-artisan-ui.home'))->assertForbidden();
});

it('answers a guest with 401, or redirects to a login route when the app has one', function (): void {
    allowEverything();

    $this->getJson(route('laranail-artisan-ui.home'))->assertUnauthorized();
    $this->get(route('laranail-artisan-ui.home'))->assertUnauthorized();

    Route::get('/login', static fn (): string => 'login')->name('login');
    Route::getRoutes()->refreshNameLookups();

    $this->get(route('laranail-artisan-ui.home'))->assertRedirect('/login');
});

it('refuses environments outside the allowlist', function (): void {
    allowEverything();
    config()->set('laranail.artisan-ui.environments', ['local']);

    $this->actingAs($this->makeUser())->get(route('laranail-artisan-ui.home'))->assertForbidden();
});

it('refuses addresses outside the IP allowlist, and fails closed on a malformed entry', function (): void {
    allowEverything();
    $user = $this->makeUser();

    config()->set('laranail.artisan-ui.allowed_ips', ['10.0.0.0/8']);
    $this->actingAs($user)->get(route('laranail-artisan-ui.home'))->assertForbidden();

    config()->set('laranail.artisan-ui.allowed_ips', ['127.0.0.0/8']);
    $this->actingAs($user)->get(route('laranail-artisan-ui.home'))->assertOk();

    config()->set('laranail.artisan-ui.allowed_ips', ['not-an-ip/99']);
    $this->actingAs($user)->get(route('laranail-artisan-ui.home'))->assertForbidden();
});

it('answers 404 once disabled, even with the routes still registered (a stale route cache)', function (): void {
    allowEverything();
    config()->set('laranail.artisan-ui.enabled', false);

    $this->actingAs($this->makeUser())->get(route('laranail-artisan-ui.home'))->assertNotFound();
});

it('registers no routes at all while disabled', function (): void {
    Route::setRoutes(new RouteCollection);
    config()->set('laranail.artisan-ui.enabled', false);
    new WebUIServiceProvider(app())->boot();
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('laranail-artisan-ui.home'))->toBeFalse();

    config()->set('laranail.artisan-ui.enabled', true);
    new WebUIServiceProvider(app())->boot();
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('laranail-artisan-ui.home'))->toBeTrue();
});

it('records every denial as an event with its reason, without telling the user the reason', function (): void {
    Event::fake([AccessDenied::class]);

    $response = $this->actingAs($this->makeUser())->getJson(route('laranail-artisan-ui.home'));

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('gate');

    Event::assertDispatched(AccessDenied::class, static fn (AccessDenied $e): bool => $e->reason === 'gate');
});

it('lets an application definition win over the package default, whichever registers first', function (): void {
    Gate::define(Ability::Access->value, static fn (): bool => true);

    expect(Gate::forUser($this->makeUser())->allows(Ability::Access->value))->toBeTrue();
});

it('sends strict security headers, on denials too', function (): void {
    $denied = $this->actingAs($this->makeUser())->get(route('laranail-artisan-ui.home'));

    allowEverything();
    $allowed = $this->actingAs($this->makeUser('b@example.com'))->get(route('laranail-artisan-ui.home'));

    foreach ([$denied, $allowed] as $response) {
        $csp = (string) $response->headers->get('Content-Security-Policy');

        expect($csp)->toContain("script-src 'self'")
            ->not->toContain('unsafe-inline')
            ->not->toContain('unsafe-eval')
            ->toContain("frame-ancestors 'none'")
            ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    }
});

it('applies the guard as the default guard for the rest of the request', function (): void {
    allowEverything();
    config()->set('auth.guards.panel', ['driver' => 'session', 'provider' => 'users']);
    config()->set('laranail.artisan-ui.guard', 'panel');

    $user = $this->makeUser();

    $this->actingAs($user, 'panel')->get(route('laranail-artisan-ui.home'))->assertOk();
    $this->actingAs($user, 'web');
    auth()->guard('panel')->logout();
    $this->get(route('laranail-artisan-ui.home'))->assertUnauthorized();
});

it('renders denials with the panel\'s own page, which carries no inline style for the CSP to block', function (): void {
    $html = (string) $this->actingAs($this->makeUser())->get(route('laranail-artisan-ui.home'))->assertForbidden()->getContent();

    expect($html)->toContain('data-lau-denied')
        ->toContain('You do not have access to this page.')
        ->not->toContain('style=')
        ->not->toContain('<style')
        ->not->toContain('gate');
});

it('keeps the bare status for JSON clients', function (): void {
    $this->actingAs($this->makeUser())->getJson(route('laranail-artisan-ui.home'))
        ->assertForbidden()
        ->assertJsonMissing(['reason' => 'gate']);
});
