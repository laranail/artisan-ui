<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Support;

use Throwable;

/**
 * `report()`, guarded (failure-handling standard, rule 8): a broken monitoring integration
 * must never turn a handled failure into a crash, so the last resort is the PHP error log.
 */
final class Reporter
{
    public static function report(Throwable $failure): void
    {
        try {
            report($failure);
        } catch (Throwable $reportingFailure) {
            error_log((string) $failure);
            error_log((string) $reportingFailure);
        }
    }
}
