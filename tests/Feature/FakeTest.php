<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\Commands\WipeCommand;

it('lets an application test the panel without running anything', function (): void {
    allowEverything();
    WipeCommand::$runs = 0;

    $fake = ArtisanUI::fake()->respondWith('lau-fixture:echo', 'faked output');

    $out = $this->actingAs($this->makeUser())
        ->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'x']])
        ->assertOk()
        ->json('output');

    expect(implode('', array_column($out, 'text')))->toBe('faked output');

    $fake->assertRan('lau-fixture:echo', static fn (array $input): bool => $input['arguments']['text'] === 'x');
    $fake->assertNotRan('lau-fixture:wipe');
});

it('still enforces every check around the fake runner', function (): void {
    $fake = ArtisanUI::fake();

    $this->actingAs($this->makeUser())
        ->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'x']])
        ->assertForbidden();

    $fake->assertNothingRan();
});
