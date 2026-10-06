<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Core\Presets\PresetCatalog;
use Simtabi\Laranail\ArtisanUI\Core\Presets\MigrationExistsGuard;

it('offers the built-in groups, dropping actions whose command is not listed', function (): void {
    config()->set('laranail.artisan-ui.commands.deny', ['view:*']);

    $groups = app(PresetCatalog::class)->groups();
    $commands = collect($groups)->flatMap(static fn ($g): array => array_map(static fn (QuickAction $a): string => $a->command, $g->actions));

    expect(collect($groups)->pluck('key'))->toContain('caches', 'database')
        ->and($commands)->toContain('optimize', 'migrate')
        ->not->toContain('view:clear', 'view:cache');
});

it('adds groups from configuration and from the facade', function (): void {
    config()->set('laranail.artisan-ui.presets.groups', [
        'app' => ['label' => 'App', 'actions' => [['label' => 'Say hi', 'command' => 'lau-fixture:echo', 'arguments' => ['text' => 'hi']]]],
    ]);

    ArtisanUI::quickActions('more', 'More', [new QuickAction('Ask', 'lau-fixture:ask')]);

    $keys = collect(app(PresetCatalog::class)->groups())->pluck('key');

    expect($keys)->toContain('app', 'more');
});

it('greys out a table generator whose migration already exists (dev-arindam-roy guard)', function (): void {
    $dir = database_path('migrations');
    File::ensureDirectoryExists($dir);
    $file = $dir . '/2026_01_01_000000_create_jobs_table.php';
    File::put($file, '<?php');

    try {
        expect(app(MigrationExistsGuard::class)->reasonFor('make:queue-table'))->toContain('jobs')
            ->and(app(MigrationExistsGuard::class)->reasonFor('make:session-table'))->toBeNull();
    } finally {
        File::delete($file);
    }
});

it('links quick actions to a pre-filled form, never to a run', function (): void {
    allowEverything();

    $html = $this->actingAs($this->makeUser())->get(route('laranail-artisan-ui.home'))->assertOk()->getContent();

    expect($html)->toContain(route('laranail-artisan-ui.detail', ['command' => 'migrate', 'options' => ['seed' => '1']]))
        ->not->toContain('/run"');
});

it('can be switched off', function (): void {
    config()->set('laranail.artisan-ui.presets.enabled', false);

    expect(app(PresetCatalog::class)->groups())->toBe([]);
});

it('offers clear-compiled in the caches group, the one cache command the old toolkit routes had that the group lacked', function (): void {
    $caches = collect(app(PresetCatalog::class)->groups())->firstWhere('key', 'caches');

    expect(array_map(static fn (QuickAction $a): string => $a->command, $caches->actions))
        ->toContain('clear-compiled', 'optimize', 'route:cache', 'cache:clear', 'view:clear', 'config:cache');
});

it('offers a tidy group of regenerable actions only, never storage or db', function (): void {
    $tidy = collect(app(PresetCatalog::class)->groups())->firstWhere('key', 'tidy');

    expect($tidy)->not->toBeNull();

    $actions = collect($tidy->actions);

    expect($actions->pluck('command')->unique()->all())->toBe(['laranail::artisan-ui.tidy'])
        ->and($actions->map(static fn (QuickAction $a): mixed => $a->arguments['action'] ?? null)->unique()->sort()->values()->all())
        ->toBe(['cache', 'logs', 'temp'])
        // Every destructive action has a preview twin.
        ->and($actions->filter(static fn (QuickAction $a): bool => ($a->options['dry-run'] ?? false) === true))->toHaveCount(3)
        // Log deletion is always scoped by age.
        ->and($actions->filter(static fn (QuickAction $a): bool => $a->arguments['action'] === 'logs')->every(static fn (QuickAction $a): bool => isset($a->options['days'])))->toBeTrue()
        // --unfiltered is the user-file escape hatch; no quick action may carry it.
        ->and($actions->contains(static fn (QuickAction $a): bool => array_key_exists('unfiltered', $a->options)))->toBeFalse();
});

it('pre-fills only options the tidy command defines', function (): void {
    $definition = app(Kernel::class)->all()['laranail::artisan-ui.tidy']->getDefinition();

    foreach (PresetCatalog::defaults() as $group) {
        foreach ($group->actions as $action) {
            if ($action->command !== 'laranail::artisan-ui.tidy') {
                continue;
            }

            foreach (array_keys($action->options) as $option) {
                expect($definition->hasOption($option))->toBeTrue("tidy has no --{$option}");
            }

            foreach (array_keys($action->arguments) as $argument) {
                expect($definition->hasArgument($argument))->toBeTrue("tidy has no {$argument} argument");
            }
        }
    }
});
