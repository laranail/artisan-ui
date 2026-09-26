<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
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
