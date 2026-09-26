<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Tests\TestCase;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\WipeCommand;

beforeEach(function (): void {
    allowEverything();
    WipeCommand::$runs = 0;
    $this->actingAs($this->makeUser());
});

it('needs the command name typed back', function (): void {
    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), ['confirm' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['confirm']);

    expect(WipeCommand::$runs)->toBe(0);
});

it('needs a fresh password confirmation', function (): void {
    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), ['confirm' => 'lau-fixture:wipe'])
        ->assertStatus(423)
        ->assertJson(['requires' => 'password']);

    expect(WipeCommand::$runs)->toBe(0);
});

it('rejects a wrong password and accepts the right one, checked against the user provider', function (): void {
    $this->postJson(route('laranail-artisan-ui.confirm-password'), ['password' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $this->postJson(route('laranail-artisan-ui.confirm-password'), ['password' => TestCase::PASSWORD])
        ->assertOk()
        ->assertSessionHas('auth.password_confirmed_at');

    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), ['confirm' => 'lau-fixture:wipe'])
        ->assertOk()
        ->assertJson(['success' => true, 'risk' => 'destructive']);

    expect(WipeCommand::$runs)->toBe(1);
});

it('honours a confirmation Laravel\'s own password.confirm made', function (): void {
    $this->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), ['confirm' => 'lau-fixture:wipe'])
        ->assertOk();
});

it('expires the confirmation after the timeout', function (): void {
    config()->set('laranail.artisan-ui.confirmation.password_timeout', 60);

    $this->withSession(['auth.password_confirmed_at' => time() - 61])
        ->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), ['confirm' => 'lau-fixture:wipe'])
        ->assertStatus(423);
});

it('can drop the password step, but never the typed confirmation', function (): void {
    config()->set('laranail.artisan-ui.confirmation.require_password', false);

    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), [])->assertStatus(422);
    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), ['confirm' => 'lau-fixture:wipe'])->assertOk();
});

it('throttles password attempts', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('laranail-artisan-ui.confirm-password'), ['password' => 'nope'])->assertStatus(422);
    }

    $this->postJson(route('laranail-artisan-ui.confirm-password'), ['password' => 'nope'])->assertStatus(429);
});

it('hands out a fresh CSRF token for a long-open page', function (): void {
    $this->getJson(route('laranail-artisan-ui.csrf-token'))
        ->assertOk()
        ->assertJsonStructure(['token', 'expires_in']);
});
