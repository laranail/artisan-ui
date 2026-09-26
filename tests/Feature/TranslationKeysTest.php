<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

/**
 * Every `laranail/artisan-ui::messages.*` key the package asks for exists in the English file.
 *
 * A missing key does not fail: `__()` returns the key itself, so the UI quietly shows
 * `laranail/artisan-ui::messages.validation.required` to the operator. Scanning the source is
 * the only way to catch it, since most of those lines only run on an error path.
 */
it('defines every messages key used in src and the views', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [
        ...glob($root . '/src/**/*.php') ?: [],
        ...array_map(static fn (SplFileInfo $f): string => $f->getPathname(), iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)))),
        ...array_map(static fn (SplFileInfo $f): string => $f->getPathname(), iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views', FilesystemIterator::SKIP_DOTS)))),
    ];

    $keys = [];

    foreach (array_unique($files) as $file) {
        preg_match_all("/laranail\\/artisan-ui::messages\\.([a-z_.]+)(?:'|\" \\. )/", (string) file_get_contents($file), $m);

        foreach ($m[1] as $key) {
            $keys[rtrim($key, '.')] = true;
        }
    }

    // Dynamic prefixes (`messages.validation.' . $key`, `messages.denied.' . ...`) are checked
    // through their parent array.
    expect(count($keys))->toBeGreaterThan(20);

    $missing = array_values(array_filter(array_keys($keys), static fn (string $key): bool => ! Lang::has('laranail/artisan-ui::messages.' . $key, 'en', false)));

    expect($missing)->toBe([]);
});

it('defines every validator message the validator can produce', function (): void {
    foreach (['not_an_object', 'unknown_argument', 'unknown_option', 'required', 'global_option', 'flag_with_value', 'not_a_list', 'list_item_text', 'item_too_long', 'too_many_items', 'not_text', 'too_long'] as $key) {
        expect(Lang::has('laranail/artisan-ui::messages.validation.' . $key, 'en', false))->toBeTrue($key);
    }

    foreach (['forbidden', 'not_found'] as $key) {
        expect(Lang::has('laranail/artisan-ui::messages.denied.' . $key, 'en', false))->toBeTrue($key);
    }
});
