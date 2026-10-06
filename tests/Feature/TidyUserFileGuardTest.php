<?php

declare(strict_types=1);

// These tests delete files under storage/logs, laravel.log included, while Monolog holds it open. On
// Windows a file with a pending delete cannot be reopened, so the next log write throws. No test here
// asserts on log output, so logging goes to the null channel.
beforeEach(function (): void {
    config()->set('logging.default', 'null');
});

/*
 * `tidy storage` sweeps `app/public`, `app/uploads` and `app/exports`. The
 * first of those is the disk behind `storage:link` — user uploads.
 *
 * With no `--days`/`--size` every file matched, and `--force` skipped the
 * prompt, so `tidy storage --force` and `tidy all --force` deleted the lot.
 * The containment guard had nothing to say about it: those roots are inside
 * `storage_path()`, which is the only question containment answers.
 *
 * `--force` cannot be the gate. It is in every CI invocation and in most of
 * this command's own tests, so it is typed by habit. The gate is a filter, or
 * a flag that exists for nothing else.
 *
 * Ported unchanged in substance from laranail/toolkit's
 * tests/Unit/Console/TidyUserFileGuardTest.php: this file is the regression for that
 * data-loss incident, and it moved with the command.
 */

function tidyUpload(string $relative = 'app/public/invoice.pdf'): string
{
    return tidyStorageFile($relative, 'a user uploaded this');
}

/**
 * Pretend to be production for one test. Returns the environment to restore.
 */
function tidyPretendProduction(): string
{
    $original = (string) app()['env'];
    app()['env'] = 'production';

    return $original;
}

afterEach(function (): void {
    tidyCleanUp();

    // Restore before the parent tearDown: Testbench rolls migrations back
    // there, and `migrate:rollback` runs confirmToProceed(), which prompts
    // in production against a mocked output that expects no questions.
    if (property_exists($this, 'originalEnv') && $this->originalEnv !== null && is_string($this->originalEnv)) {
        $this->app['env'] = $this->originalEnv;
    }
});

it('refuses an unfiltered storage sweep even with --force', function (): void {
    $upload = tidyUpload();

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'storage', '--force' => true])
        ->expectsOutputToContain('Refusing to sweep')
        ->assertExitCode(1);

    expect($upload)->toBeFile();
});

it('names the roots it declined', function (): void {
    tidyUpload();

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'storage', '--force' => true])
        ->expectsOutputToContain('storage/app/public')
        ->assertExitCode(1);
});

it('treats an age filter as making the sweep intentional', function (): void {
    $stale = tidyUpload();
    touch($stale, time() - (60 * 86400));

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'storage', '--days' => '30', '--force' => true])
        ->assertExitCode(0);

    expect($stale)->not->toBeFile();
});

it('treats a size filter as making the sweep intentional', function (): void {
    $big = tidyUpload('app/exports/report.csv');
    file_put_contents($big, str_repeat('y', 3 * 1024 * 1024));

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'storage', '--size' => '2', '--force' => true])
        ->assertExitCode(0);

    expect($big)->not->toBeFile();
});

it('accepts --unfiltered as the explicit way through', function (): void {
    // Deleting everything remains reachable — it just cannot be reached by
    // a flag anyone types for another reason.
    $upload = tidyUpload();

    $this->artisan('laranail::artisan-ui.tidy', [
        'action'       => 'storage',
        '--unfiltered' => true,
        '--force'      => true,
    ])->assertExitCode(0);

    expect($upload)->not->toBeFile();
});

it('refuses --unfiltered outright in production', function (): void {
    // No override, deliberately. --force bypasses Laravel's own
    // confirmToProceed() by design, so a prompt would not be a gate here.
    $this->originalEnv = tidyPretendProduction();

    $upload = tidyUpload();

    $this->artisan('laranail::artisan-ui.tidy', [
        'action'       => 'storage',
        '--unfiltered' => true,
        '--force'      => true,
    ])
        ->expectsOutputToContain('in production')
        ->assertExitCode(1);

    expect($upload)->toBeFile();
});

it('still runs a scoped sweep in production', function (): void {
    $this->originalEnv = tidyPretendProduction();

    $stale = tidyUpload();
    touch($stale, time() - (60 * 86400));

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'storage', '--days' => '30', '--force' => true])
        ->assertExitCode(0);

    expect($stale)->not->toBeFile();
});

it('skips storage in tidy all rather than failing', function (): void {
    // `all` exists for logs and temp. Failing the whole run over storage
    // would make the useful part unreachable, so it skips — and says so,
    // because an omission this consequential should not have to be
    // inferred from a file count.
    $upload = tidyUpload();
    $log = tidyStorageFile('logs/tidy-all-guard.log', 'noise');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'all', '--force' => true])
        ->expectsOutputToContain('storage skipped')
        ->assertExitCode(0);

    expect($upload)->toBeFile('tidy all --force deleted a user upload.')
        ->and($log)->not->toBeFile('tidy all stopped sweeping logs.');
});

it('sweeps storage in tidy all when scoped', function (): void {
    $stale = tidyUpload();
    touch($stale, time() - (60 * 86400));

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'all', '--days' => '30', '--force' => true])
        ->assertExitCode(0);

    expect($stale)->not->toBeFile();
});

it('leaves logs and temp unaffected by the guard', function (): void {
    // They hold regenerable data, which is what makes an unfiltered sweep
    // the whole point of the command there.
    $log = tidyStorageFile('logs/regenerable.log', 'noise');

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'logs', '--force' => true])
        ->assertExitCode(0);

    expect($log)->not->toBeFile();
});

it('refuses a dry run too rather than previewing the lot', function (): void {
    // A preview is harmless, but it would print every file in app/public as
    // "would delete" — which reads as a plan the next --force will carry
    // out, and that plan is the bug.
    $upload = tidyUpload();

    $this->artisan('laranail::artisan-ui.tidy', ['action' => 'storage', '--dry-run' => true])
        ->expectsOutputToContain('Refusing to sweep')
        ->assertExitCode(1);

    expect($upload)->toBeFile();
});
