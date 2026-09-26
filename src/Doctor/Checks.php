<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Doctor;

use Closure;
use Throwable;
use ReflectionProperty;
use Illuminate\Support\Str;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Enums\AssetMode;
use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\ArtisanUI\Enums\AuditDriver;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Core\Policy\DefaultAbilities;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\CallbackCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

/**
 * Diagnostics for the ways this package can be configured into a state that is either
 * unusable without an error (every Gate still denying) or exposed without anyone noticing
 * (reachable in production).
 *
 * Rendered by `laranail::artisan-ui.doctor` and, grouped under the package, by
 * package-tools' `laranail::package-tools.doctor`. Every check is built lazily and never
 * throws, per the DoctorCheck contract.
 */
final readonly class Checks
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * @return list<DoctorCheck>
     */
    public function all(): array
    {
        return [
            $this->check('Environments', 'The panel is not allowed in production.', $this->environments(...)),
            $this->check('Enabled', 'Whether the panel is switched on, and where.', $this->enabled(...)),
            $this->check('Gate abilities', 'The application has defined the abilities the panel asks about.', $this->abilities(...)),
            $this->check('Auth guard', 'The guard the panel authenticates with keeps a session.', $this->guard(...)),
            $this->check('Maintenance mode', 'The panel stays reachable while the application is down.', $this->maintenance(...)),
            $this->check('Audit trail', 'Runs are recorded where the configuration says.', $this->audit(...)),
            $this->check('Audit pruning', 'Old run records are pruned on a schedule.', $this->pruning(...)),
            $this->check('Run locks', 'The cache store can lock, so a command cannot run twice at once.', $this->locks(...)),
            $this->check('Assets', 'The panel stylesheet and script are built and reachable.', $this->assets(...)),
            $this->check('Octane', 'Commands run inside a long-lived worker share its state.', $this->octane(...)),
        ];
    }

    /**
     * @param Closure(): DoctorResult $run
     */
    private function check(string $name, string $description, Closure $run): DoctorCheck
    {
        return new CallbackCheck($name, $description, static function () use ($run): DoctorResult {
            try {
                return $run();
            } catch (Throwable $e) {
                return DoctorResult::fail('The check itself failed: ' . $e->getMessage());
            }
        });
    }

    private function environments(): DoctorResult
    {
        $environments = $this->config()->environments();

        if (in_array('production', $environments, true) || in_array('*', $environments, true)) {
            return DoctorResult::fail(
                'environments allows production. Remove it; if the panel is genuinely needed there, '
                . 'restrict it with allowed_ips and a narrow Access ability instead.',
                ['environments' => $environments],
            );
        }

        return DoctorResult::pass('Allowed in: ' . ($environments === [] ? 'nowhere' : implode(', ', $environments)) . '.');
    }

    private function enabled(): DoctorResult
    {
        $config = $this->config();

        if (! $config->enabled()) {
            return DoctorResult::skip('Disabled. Set LARANAIL_ARTISAN_UI_ENABLED=true to switch it on.');
        }

        if ($this->app()->environment('production')) {
            return DoctorResult::warn('Enabled in a production environment. The environment allowlist still has to admit it.');
        }

        return DoctorResult::pass('Enabled at /' . $config->path() . '.');
    }

    private function abilities(): DoctorResult
    {
        $defaults = $this->container->make(DefaultAbilities::class);

        $missing = array_values(array_map(
            static fn (Ability $a): string => $a->value,
            array_filter(Ability::cases(), $defaults->isDefault(...)),
        ));

        if (in_array(Ability::Access->value, $missing, true) || in_array(Ability::Run->value, $missing, true)) {
            return DoctorResult::warn(
                'Nobody can use the panel yet: define ' . implode(', ', $missing) . ' in a service provider, '
                . "e.g. Gate::define('" . Ability::Access->value . "', fn (\$user) => \$user->isAdmin()).",
                ['undefined' => $missing],
            );
        }

        if ($missing !== []) {
            return DoctorResult::pass('Access and Run are defined. Still denying: ' . implode(', ', $missing) . '.');
        }

        return DoctorResult::pass('Every ability is defined by the application.');
    }

    private function guard(): DoctorResult
    {
        $config = $this->config();
        $guard = $config->guard() ?? $this->configValue('auth.defaults.guard');
        $driver = $this->configValue("auth.guards.{$guard}.driver");

        if ($driver === null) {
            return DoctorResult::fail("The guard [{$guard}] is not configured in auth.guards.");
        }

        if ($driver !== 'session') {
            return DoctorResult::warn("The guard [{$guard}] uses the [{$driver}] driver. The panel needs a session guard for its forms and password confirmation.");
        }

        return DoctorResult::pass("Authenticating with the [{$guard}] session guard.");
    }

    private function maintenance(): DoctorResult
    {
        if (! $this->config()->enabled()) {
            return DoctorResult::skip('The panel is disabled.');
        }

        $path = $this->config()->path();
        $except = [];

        if (class_exists(PreventRequestsDuringMaintenance::class) && property_exists(PreventRequestsDuringMaintenance::class, 'neverPrevent')) {
            $value = new ReflectionProperty(PreventRequestsDuringMaintenance::class, 'neverPrevent')->getValue();
            $except = is_array($value) ? array_filter($value, is_string(...)) : [];
        }

        foreach ($except as $pattern) {
            $pattern = trim($pattern, '/');

            if (Str::is($pattern, $path) && Str::is($pattern, $path . '/commands/about')) {
                return DoctorResult::pass("/{$path} is exempt from maintenance mode.");
            }
        }

        return DoctorResult::warn(
            'Running `down` from the panel will lock the panel out too. Exempt it with '
            . "PreventRequestsDuringMaintenance::except('{$path}*'), or always use `down --secret`.",
        );
    }

    private function audit(): DoctorResult
    {
        $config = $this->config();
        $driver = $config->auditDriver();

        if ($driver === AuditDriver::None) {
            return DoctorResult::warn('Auditing is off. Runs leave no record.');
        }

        if ($driver === AuditDriver::Log) {
            return DoctorResult::pass('Runs are logged to ' . ($config->auditChannel() ?? 'the default channel') . '.');
        }

        $connection = $config->auditConnection();
        $schema = $this->container->make('db')->connection($connection)->getSchemaBuilder();

        if (! $schema->hasTable(CommandRun::TABLE)) {
            return DoctorResult::fail(
                'The audit driver is database but ' . CommandRun::TABLE . ' does not exist. '
                . 'Publish it with `vendor:publish --tag=laranail::artisan-ui-migrations` and migrate.',
            );
        }

        return DoctorResult::pass('Runs are recorded in ' . CommandRun::TABLE . ($connection === null ? '' : " on [{$connection}]") . '.');
    }

    private function pruning(): DoctorResult
    {
        if ($this->config()->auditDriver() !== AuditDriver::Database) {
            return DoctorResult::skip('Only applies to the database audit driver.');
        }

        foreach ($this->container->make(Schedule::class)->events() as $event) {
            $command = (string) $event->command;

            if (str_contains($command, 'model:prune')
                && (! str_contains($command, '--model') || str_contains($command, 'CommandRun'))) {
                return DoctorResult::pass('model:prune is scheduled.');
            }
        }

        return DoctorResult::warn(
            'Nothing prunes old runs. Schedule `model:prune --model="' . CommandRun::class . '"`; '
            . 'retention is ' . $this->config()->retentionDays() . ' days.',
        );
    }

    private function locks(): DoctorResult
    {
        $repository = $this->container->make('cache')->store();

        // The per-user rate limiter and the run locks both go through the cache, so a store that
        // cannot be reached turns every run into a 500 before the package is consulted.
        try {
            $repository->get('laranail-artisan-ui:doctor-probe');
        } catch (Throwable $e) {
            return DoctorResult::fail(
                'The cache store cannot be reached, so rate limiting and run locks fail: ' . $e->getMessage()
                . '. With the database store, run `php artisan make:cache-table` and migrate.',
            );
        }

        return $repository->getStore() instanceof LockProvider
            ? DoctorResult::pass('The cache store is reachable and supports locks.')
            : DoctorResult::warn('The cache store cannot lock, so the same command can run twice at once.');
    }

    private function assets(): DoctorResult
    {
        $assets = $this->container->make(Assets::class);

        $missing = array_values(array_filter(Assets::filenames(), static fn (string $f): bool => ! $assets->exists($f)));

        if ($missing !== []) {
            return DoctorResult::fail('Not built: ' . implode(', ', $missing) . '. Run `npm install && npm run build` in the package.');
        }

        if ($this->config()->assetMode() === AssetMode::Published) {
            $published = $this->app()->publicPath('vendor/artisan-ui/' . Assets::SCRIPT);

            if (! is_file($published) || hash_file('xxh128', $published) !== hash_file('xxh128', (string) $assets->path(Assets::SCRIPT))) {
                return DoctorResult::warn('The published assets are missing or stale. Re-run `vendor:publish --tag=laranail::artisan-ui-assets --force`.');
            }
        }

        return DoctorResult::pass('Built, and served in ' . $this->config()->assetMode()->value . ' mode.');
    }

    private function octane(): DoctorResult
    {
        if (! class_exists('Laravel\\Octane\\Octane')) {
            return DoctorResult::skip('Octane is not installed.');
        }

        return DoctorResult::warn(
            'Octane is installed. Commands run from the panel share the worker, so a command that '
            . 'changes configuration or caches can leave the worker in a different state from its siblings.',
        );
    }

    private function config(): ArtisanUIConfig
    {
        return $this->container->make(ArtisanUIConfig::class);
    }

    private function app(): Application
    {
        return $this->container->make(Application::class);
    }

    private function configValue(string $key): ?string
    {
        $value = $this->container->make('config')->get($key);

        return is_string($value) ? $value : null;
    }
}
