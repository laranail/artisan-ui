<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Environment;

use Throwable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * dev-arindam-roy's environment panel: PHP, Laravel and database versions, plus the drivers
 * that decide how most maintenance commands behave.
 *
 * Gated by the ViewEnvironment ability, because version numbers are reconnaissance. Never
 * throws: a database that cannot be reached is reported as such rather than breaking the page.
 */
final readonly class EnvironmentInfo
{
    public function __construct(
        private Application $app,
        private DatabaseManager $db,
        private Config $config,
    ) {}

    /**
     * @return list<array{label: string, value: string}>
     */
    public function snapshot(): array
    {
        return [
            ['label' => 'PHP', 'value' => PHP_VERSION],
            ['label' => 'Laravel', 'value' => $this->app->version()],
            ['label' => 'Environment', 'value' => (string) $this->app->environment()],
            ['label' => 'Debug mode', 'value' => $this->config->get('app.debug') === true ? 'on' : 'off'],
            ['label' => 'Maintenance mode', 'value' => $this->app->isDownForMaintenance() ? 'down' : 'up'],
            ['label' => 'Database', 'value' => $this->database()],
            ['label' => 'Cache store', 'value' => $this->string('cache.default')],
            ['label' => 'Queue connection', 'value' => $this->string('queue.default')],
            ['label' => 'Session driver', 'value' => $this->string('session.driver')],
        ];
    }

    private function database(): string
    {
        try {
            $connection = $this->db->connection();

            return $connection->getDriverName() . ' ' . $connection->getServerVersion();
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function string(string $key): string
    {
        $value = $this->config->get($key);

        return is_scalar($value) ? (string) $value : 'not set';
    }
}
