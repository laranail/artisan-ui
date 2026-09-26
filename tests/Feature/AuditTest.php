<?php

declare(strict_types=1);

use Monolog\LogRecord;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Exceptions;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\Package\Tools\Exceptions\PackageBootException;

/**
 * Route the audit log to a Monolog TestHandler and return it.
 */
function auditLog(): TestHandler
{
    $handler = new TestHandler;

    config()->set('logging.channels.lau-audit-test', ['driver' => 'monolog', 'handler' => NullHandler::class]);
    config()->set('laranail.artisan-ui.audit.channel', 'lau-audit-test');

    Log::channel('lau-audit-test')->getLogger()->setHandlers([$handler]);

    return $handler;
}

/**
 * @return list<string>
 */
function auditMessages(TestHandler $handler): array
{
    return array_map(static fn (LogRecord $record): string => $record->message, $handler->getRecords());
}

function migrateRunTable(): void
{
    $migration = require dirname(__DIR__, 2) . '/database/migrations/create_laranail_artisan_ui_runs_table.php.stub';
    $migration->up();
}

beforeEach(function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
});

it('logs a line when a run starts and when it ends, with redacted input', function (): void {
    $log = auditLog();

    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'x']])->assertOk();

    $records = $log->getRecords();

    expect(auditMessages($log))->toBe(['laranail/artisan-ui: run started', 'laranail/artisan-ui: run finished'])
        ->and($records[0]->context['command'])->toBe('lau-fixture:echo')
        ->and($records[1]->context['status'])->toBe('succeeded')
        ->and($records[1]->context['run_id'])->toBe($records[0]->context['run_id']);
});

it('records a row per run with the database driver, masking secret input', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    migrateRunTable();

    $id = $this->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:wipe'), [
            'confirm' => 'lau-fixture:wipe',
            'options' => ['password' => 'hunter2'],
        ])->assertOk()->json('run_id');

    $run = CommandRun::query()->findOrFail($id);

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->options)->toBe(['password' => '••••••••'])
        ->and($run->output)->toContain('wiped')
        ->and($run->finished_at)->not->toBeNull();
});

it('falls back to the log, reports, and marks the audit store degraded when its table disappears', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    Exceptions::fake();
    $log = auditLog();

    // No migration: the table does not exist, as after `migrate:fresh`.
    expect(Schema::hasTable(CommandRun::TABLE))->toBeFalse();

    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'x']])->assertOk();

    expect(auditMessages($log))->toContain('laranail/artisan-ui: run finished');

    Exceptions::assertReported(static fn (PackageBootException $e): bool => str_contains($e->getMessage(), 'laranail/artisan-ui:audit.database'));

    expect(app(BootReport::class)->isHealthy())->toBeFalse()
        ->and(array_keys(app(BootReport::class)->degraded()))->toContain('laranail/artisan-ui:audit.database');
});

it('shows history only with the database driver and the ViewHistory ability', function (): void {
    $this->get(route('laranail-artisan-ui.history'))->assertNotFound();

    config()->set('laranail.artisan-ui.audit.driver', 'database');
    migrateRunTable();

    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'recorded']])->assertOk();

    $this->get(route('laranail-artisan-ui.history'))
        ->assertOk()
        ->assertSee('lau-fixture:echo')
        ->assertSee('recorded')
        ->assertSee(route('laranail-artisan-ui.detail', ['command' => 'lau-fixture:echo', 'arguments' => ['text' => 'recorded']]), false);

    Gate::define('laranail-artisan-ui.view-history', static fn (): bool => false);

    $this->get(route('laranail-artisan-ui.history'))->assertForbidden();
});

it('filters history by command and status', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    migrateRunTable();

    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:echo'), ['arguments' => ['text' => 'x']]);
    $this->postJson(route('laranail-artisan-ui.execute', 'lau-fixture:ask'));

    $this->get(route('laranail-artisan-ui.history', ['status' => 'failed']))
        ->assertOk()
        ->assertSee('lau-fixture:ask')
        ->assertDontSee('lau-fixture:echo</span>', false);
});

it('prunes runs past the retention window', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');
    config()->set('laranail.artisan-ui.audit.retention_days', 30);
    migrateRunTable();

    CommandRun::query()->create(['id' => '01OLD000000000000000000000', 'command' => 'about', 'risk' => 'safe', 'status' => 'succeeded', 'created_at' => now()->subDays(31)]);
    CommandRun::query()->create(['id' => '01NEW000000000000000000000', 'command' => 'about', 'risk' => 'safe', 'status' => 'succeeded']);

    $this->artisan('model:prune', ['--model' => [CommandRun::class]])->assertSuccessful();

    expect(CommandRun::query()->pluck('id')->all())->toBe(['01NEW000000000000000000000']);
});

it('links to history from every page, not only the home screen', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');

    $this->get(route('laranail-artisan-ui.detail', 'about'))
        ->assertOk()
        ->assertSee(route('laranail-artisan-ui.history'), false);
});
