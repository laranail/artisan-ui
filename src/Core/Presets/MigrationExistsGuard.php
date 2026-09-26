<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Presets;

use Illuminate\Contracts\Foundation\Application;

/**
 * dev-arindam-roy's duplicate-migration guard, for the framework's table generators.
 *
 * Each `make:*-table` command writes a migration for a table the framework uses. Running one
 * twice produces two migrations for the same table and a failing `migrate`. Recent Laravel
 * refuses on its own, but only after the click; this greys the action out before it.
 */
final readonly class MigrationExistsGuard
{
    /** Generator command => the table its migration creates. */
    public const array TABLES = [
        'make:cache-table'         => 'cache',
        'make:notifications-table' => 'notifications',
        'make:queue-table'         => 'jobs',
        'make:queue-failed-table'  => 'failed_jobs',
        'make:queue-batches-table' => 'job_batches',
        'make:session-table'       => 'sessions',
    ];

    public function __construct(
        private Application $app,
    ) {}

    public function guards(string $command): bool
    {
        return isset(self::TABLES[$command]);
    }

    /** Null when the action is fine to offer, or the reason it is not. */
    public function reasonFor(string $command): ?string
    {
        $table = self::TABLES[$command] ?? null;

        if ($table === null) {
            return null;
        }

        $matches = glob($this->app->databasePath('migrations') . '/*_create_' . $table . '_table.php') ?: [];

        return $matches === [] ? null : "A migration for the [{$table}] table already exists.";
    }
}
