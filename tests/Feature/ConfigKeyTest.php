<?php

declare(strict_types=1);

/**
 * The defect this guards is in lines a test never executes: a bare `config('artisan-ui.x')`
 * read returns null and the package quietly runs on its defaults. So it scans the source.
 */
it('reads configuration only at the registered key', function (): void {
    $this->assertReadsConfigAtRegisteredKey(dirname(__DIR__, 2) . '/src', 'artisan-ui');
});
