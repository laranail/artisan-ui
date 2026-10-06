<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Let everyone through the panel and the run ability, as an application's admin check would.
 */
function allowEverything(): void
{
    Gate::define(Ability::Access->value, static fn (): bool => true);
    Gate::define(Ability::Run->value, static fn (): bool => true);
    Gate::define(Ability::ViewHistory->value, static fn (): bool => true);
    Gate::define(Ability::ViewEnvironment->value, static fn (): bool => true);
}

/**
 * The files a tidy test created, so they are removed whatever the command did with them.
 *
 * @return ArrayObject<int, string>
 */
function tidyCreated(): ArrayObject
{
    /** @var ArrayObject<int, string>|null $created */
    static $created = null;

    return $created ??= new ArrayObject;
}

/**
 * Remember a path for cleanup.
 */
function tidyTrack(string $path): string
{
    tidyCreated()->append($path);

    return $path;
}

/**
 * Write a file under storage_path() and remember it for cleanup.
 */
function tidyStorageFile(string $relative, string $contents = 'x'): string
{
    $path = storage_path($relative);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $contents);

    return tidyTrack($path);
}

/**
 * Remove every file a tidy test created that the command did not.
 */
function tidyCleanUp(): void
{
    foreach (tidyCreated() as $path) {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }

    tidyCreated()->exchangeArray([]);
}

/**
 * Return a file's source with comments/docblocks stripped, so assertions
 * reflect executable code (not prose that mentions a pattern).
 */
function tidyExecutableSource(string $path): string
{
    $code = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= $token[1];

            continue;
        }

        $code .= $token;
    }

    return $code;
}

/**
 * Compile Blade from scratch once per run: a template compiled against an earlier version of
 * a view would otherwise keep passing locally while CI (empty cache) fails.
 */
$compiled = __DIR__ . '/../vendor/orchestra/testbench-core/laravel/storage/framework/views';

if (is_dir($compiled)) {
    foreach (glob($compiled . '/*.php') ?: [] as $template) {
        @unlink($template);
    }
}
