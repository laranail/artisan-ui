<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\OptionDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\ArgumentDefinition;

it('lists Laravel commands and leaves out the console-level ones and hidden ones', function (): void {
    $names = array_keys(app(CommandRegistry::class)->all());

    expect($names)->toContain('about', 'lau-fixture:echo', 'migrate')
        ->not->toContain('list', 'help', 'completion', 'lau-fixture:hidden', 'serve', 'tinker');
});

it('describes arguments and options without the global ones', function (): void {
    $echo = app(CommandRegistry::class)->find('lau-fixture:echo');

    expect($echo)->toBeInstanceOf(CommandDefinition::class)
        ->and(array_map(static fn (ArgumentDefinition $a): string => $a->name, $echo->arguments))->toBe(['text'])
        ->and(array_map(static fn (OptionDefinition $o): string => $o->name, $echo->options))->toBe(['shout', 'tag'])
        ->and($echo->hasRequiredArguments())->toBeTrue()
        ->and($echo->option('tag')->array)->toBeTrue()
        ->and($echo->option('shout')->isBoolean())->toBeTrue();

    foreach (CommandDefinition::GLOBAL_OPTIONS as $global) {
        expect($echo->option($global))->toBeNull();
    }
});

it('groups by namespace, putting a bare command with its namespace', function (): void {
    $groups = app(CommandRegistry::class)->grouped();

    expect($groups)->toHaveKey('lau-fixture')
        ->and(array_map(static fn (CommandDefinition $c): string => $c->name, $groups['migrate']))->toContain('migrate', 'migrate:fresh')
        ->and(array_map(static fn ($c) => $c->name, $groups[''] ?? []))->not->toContain('migrate');
});

it('renders the arguments panel open only when an argument is required (upstream PR #2, fixed)', function (): void {
    allowEverything();
    $user = $this->makeUser();

    $withRequired = $this->actingAs($user)->get(route('laranail-artisan-ui.detail', 'lau-fixture:echo'))->assertOk()->getContent();
    $without = $this->actingAs($user)->get(route('laranail-artisan-ui.detail', 'lau-fixture:secret'))->assertOk()->getContent();

    // Upstream rendered `{ open:  }` for false, which is a JavaScript syntax error.
    expect($withRequired)->toMatch('/<details[^>]*open[^>]*>\s*<summary[^>]*>Arguments/')
        ->and($without)->not->toMatch('/<details[^>]*open[^>]*>\s*<summary[^>]*>Arguments/')
        ->and($withRequired . $without)->not->toContain('open:  }');
});

it('404s a command the policy does not list, however it is reached', function (string $command): void {
    allowEverything();
    config()->set('laranail.artisan-ui.commands.deny', ['lau-fixture:secret']);

    $this->actingAs($this->makeUser())->get(route('laranail-artisan-ui.detail', $command))->assertNotFound();
})->with(['serve', 'lau-fixture:hidden', 'lau-fixture:secret', 'no-such-command']);

it('pre-fills the form from the query string, only for keys the command defines', function (): void {
    allowEverything();

    $html = $this->actingAs($this->makeUser())
        ->get(route('laranail-artisan-ui.detail', ['command' => 'lau-fixture:echo', 'arguments' => ['text' => 'hello', 'bogus' => 'x'], 'options' => ['shout' => '1']]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="hello"')->not->toContain('bogus')
        ->and($html)->toMatch('/name="options\[shout\]"[^>]*checked/');
});

it('does not pre-fill a redacted secret from a history rerun', function (): void {
    allowEverything();

    $html = $this->actingAs($this->makeUser())
        ->get(route('laranail-artisan-ui.detail', ['command' => 'lau-fixture:wipe', 'options' => ['password' => '••••••••']]))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('••••••••');
});
