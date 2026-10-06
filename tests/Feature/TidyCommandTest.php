<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

/*
 * Ported from laranail/toolkit's tests/Unit/Console/TidyCommandTest.php with the command, when
 * it moved here from `laranail::toolkit.tidy`. The assertions are the same; only the command
 * name and the test style changed.
 */

afterEach(function (): void {
    tidyCleanUp();
});

// -----------------------------------------------------------------------
// Registration
// -----------------------------------------------------------------------

it('registers the command under its namespaced name', function (): void {
    expect(array_keys(Artisan::all()))->toContain('laranail::artisan-ui.tidy');
});

it('fails on an invalid action', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'nope'])
        ->expectsOutputToContain('Invalid action')
        ->assertExitCode(1);
});

// -----------------------------------------------------------------------
// logs / temp / storage
// -----------------------------------------------------------------------

it('deletes log files in storage with the logs action', function (): void {
    // A uniquely-named log file so the console lifecycle logger (which may
    // (re)write laravel.log during the run) cannot mask the deletion.
    $log = tidyStorageFile('logs/tidy_' . uniqid() . '.log', 'old log');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
        ->assertExitCode(0);

    expect($log)->not->toBeFile();
});

it('never deletes a .gitignore', function (): void {
    $keep = tidyStorageFile('logs/.gitignore', '*');
    tidyStorageFile('logs/app.log', 'log');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
        ->assertExitCode(0);

    expect($keep)->toBeFile();
});

it('keeps recent files under an age filter', function (): void {
    $recent = tidyStorageFile('logs/recent.log', 'recent');

    // Keep only files older than 30 days — the just-created file survives.
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--days' => '30', '--force' => true])
        ->assertExitCode(0);

    expect($recent)->toBeFile();
});

it('deletes temp files with the temp action', function (): void {
    $tmp = tidyStorageFile('app/temp/old.tmp', 'tmp');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'temp', '--force' => true])
        ->assertExitCode(0);

    expect($tmp)->not->toBeFile();
});

it('deletes uploaded files with the storage action when scoped', function (): void {
    $upload = tidyStorageFile('app/uploads/photo.bin', 'bytes');
    touch($upload, time() - (60 * 86400));

    // Scoped by age. This assertion used to pass with no filter at all,
    // which is the defect: --force plus a housekeeping verb emptied
    // app/public, app/uploads and app/exports in one non-interactive run.
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'storage', '--days' => '30', '--force' => true])
        ->assertExitCode(0);

    expect($upload)->not->toBeFile();
});

it('deletes only large files under a size filter', function (): void {
    $small = tidyStorageFile('logs/small.log', 'x');                                // 1 byte
    $big = tidyStorageFile('logs/big.log', str_repeat('y', 3 * 1024 * 1024)); // 3 MB

    // Delete only files >= 2 MB.
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--size' => '2', '--force' => true])
        ->assertExitCode(0);

    expect($small)->toBeFile()
        ->and($big)->not->toBeFile();
});

it('deletes nothing and reports on a dry run', function (): void {
    $log = tidyStorageFile('logs/dry.log', 'log');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--dry-run' => true])
        ->expectsOutputToContain('[dry-run]')
        ->assertExitCode(0);

    expect($log)->toBeFile();
});

// -----------------------------------------------------------------------
// db gating
// -----------------------------------------------------------------------

it('requires --force for the db action', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'db'])
        ->expectsOutputToContain('re-run with --force')
        ->assertExitCode(1);
});

it('makes the db action a no-op on a dry run', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'db', '--dry-run' => true])
        ->expectsOutputToContain('[dry-run]')
        ->assertExitCode(0);
});

it('runs migrate:fresh for the db action with --force', function (): void {
    // The in-memory sqlite test DB makes migrate:fresh safe to actually run.
    // --force satisfies the gate and confirmToProceed() is a no-op outside
    // production, so the real refresh path executes and reports success.
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'db', '--force' => true])
        ->expectsOutputToContain('Database refreshed')
        ->assertExitCode(0);

    // migrate:fresh recreates the migration repository it then fills.
    expect(Schema::hasTable('migrations'))->toBeTrue();
});

it('excludes the db action from all', function (): void {
    // `all` must not drop tables: the users table (from loadLaravelMigrations)
    // still exists afterwards.
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'all', '--force' => true])
        ->expectsOutputToContain('db excluded')
        ->assertExitCode(0);

    expect(Schema::hasTable('users'))->toBeTrue();
});

// -----------------------------------------------------------------------
// cache
// -----------------------------------------------------------------------

it('succeeds with the cache action', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'cache', '--force' => true])
        ->expectsOutputToContain('Cache tidied.')
        ->assertExitCode(0);
});

it('previews the cache action on a dry run', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'cache', '--dry-run' => true])
        ->expectsOutputToContain('[dry-run]')
        ->assertExitCode(0);
});

it('also clears optimized caches with --optimize', function (): void {
    // --optimize runs optimize:clear alongside the cache flush.
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'cache', '--optimize' => true, '--force' => true])
        ->assertExitCode(0);
});

it('names optimize:clear in the dry-run preview', function (): void {
    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'cache', '--optimize' => true, '--dry-run' => true])
        ->expectsOutputToContain('optimize:clear')
        ->assertExitCode(0);
});

// -----------------------------------------------------------------------
// non-interactive confirmation (declines without --force)
// -----------------------------------------------------------------------

it('deletes nothing without --force in non-interactive mode', function (): void {
    // --no-interaction (no --force): the interaction service is put in
    // non-interactive mode and confirmAction() returns the default (false),
    // so the destructive sweep is declined and the file survives — a piped/CI
    // run never silently deletes. The panel always runs commands this way.
    $keep = tidyStorageFile('logs/noforce_' . uniqid() . '.log', 'keep');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--no-interaction' => true])
        ->assertExitCode(0);

    expect($keep)->toBeFile();
});

// -----------------------------------------------------------------------
// combined age + size filter
// -----------------------------------------------------------------------

it('deletes files older than the age threshold', function (): void {
    $old = tidyStorageFile('logs/aged_' . uniqid() . '.log', 'old');
    // Backdate well beyond the threshold so the age branch deletes it.
    touch($old, time() - (40 * 86400));

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--days' => '30', '--force' => true])
        ->assertExitCode(0);

    expect($old)->not->toBeFile();
});

it('reports the space freed in the summary', function (): void {
    tidyStorageFile('logs/sized_' . uniqid() . '.log', str_repeat('z', 2048));

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
        ->expectsOutputToContain('KB')
        ->assertExitCode(0);
});

// -----------------------------------------------------------------------
// Security
// -----------------------------------------------------------------------

it('never deletes a symlink target outside storage', function (): void {
    $outsideDir = sys_get_temp_dir() . '/laranail-tidy-out-' . uniqid();
    @mkdir($outsideDir, 0777, true);
    $secret = $outsideDir . '/secret.log';
    file_put_contents($secret, 'do-not-delete');

    // A symlink inside storage/logs pointing at the external secret.
    $escape = storage_path('logs/escape.log');
    @mkdir(dirname($escape), 0777, true);
    @symlink($secret, $escape);
    tidyTrack($escape);

    try {
        $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
            ->assertExitCode(0);

        // The external target survives; only in-tree files are removed.
        expect($secret)->toBeFile()
            ->and(file_get_contents($secret))->toBe('do-not-delete');
    } finally {
        @unlink($escape);
        @unlink($secret);
        @rmdir($outsideDir);
    }
})->group('security');

it('confines deletion to storage_path() in the source', function (): void {
    $code = tidyExecutableSource(dirname(__DIR__, 2) . '/src/Commands/TidyCommand.php');

    // Roots are storage-relative; deletion uses realpath containment and the
    // FilePathGuard; the executable code never sweeps sys_get_temp_dir().
    expect($code)->toContain('realpath(storage_path())')
        ->toContain('$this->isWithin($real, $root)')
        ->toContain('use FilePathGuard;')
        ->not->toContain('sys_get_temp_dir()');
})->group('security');
