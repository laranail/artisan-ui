<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Support;

use Simtabi\Laranail\ArtisanUI\Enums\AssetMode;
use Simtabi\Laranail\ArtisanUI\Enums\AuditDriver;
use Illuminate\Contracts\Config\Repository as Config;
use Simtabi\Laranail\Package\Tools\Validation\Predicates\Pattern;

/**
 * Typed access to `config('laranail.artisan-ui.*')`.
 *
 * Every read goes through here, so the registered key is written down exactly once and a
 * bare `config('artisan-ui.*')` read, which silently returns null and leaves the package
 * running on its defaults, cannot creep in. Values are read live on each call rather than
 * captured at construction, so a test or a runtime `config()->set()` is honoured.
 *
 * Every accessor fails closed: a malformed value resolves to the most restrictive reading.
 */
final readonly class ArtisanUIConfig
{
    public const string KEY = 'laranail.artisan-ui';

    public function __construct(
        private Config $config,
    ) {}

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get(self::KEY . '.' . $key, $default);
    }

    public function enabled(): bool
    {
        return $this->get('enabled') === true;
    }

    public function domain(): ?string
    {
        $domain = $this->get('domain');

        return is_string($domain) && $domain !== '' ? $domain : null;
    }

    public function path(): string
    {
        $path = $this->get('path', 'artisan');

        $path = is_string($path) ? trim($path, '/') : '';

        return $path === '' ? 'artisan' : $path;
    }

    /** @return list<string> */
    public function middleware(): array
    {
        return self::stringList($this->get('middleware', ['web']));
    }

    public function guard(): ?string
    {
        $guard = $this->get('guard');

        return is_string($guard) && $guard !== '' ? $guard : null;
    }

    /** @return list<string> */
    public function environments(): array
    {
        return self::stringList($this->get('environments', ['local']));
    }

    /** @return list<string> */
    public function allowedIps(): array
    {
        return self::stringList($this->get('allowed_ips', []));
    }

    public function logChannel(): ?string
    {
        $channel = $this->get('log_channel');

        return is_string($channel) && $channel !== '' ? $channel : null;
    }

    /**
     * Null means "every command"; an empty list means "no command".
     *
     * @return list<string>|null
     */
    public function allowPatterns(): ?array
    {
        $allow = $this->get('commands.allow');

        return $allow === null ? null : self::stringList($allow);
    }

    /** @return list<string> */
    public function denyPatterns(): array
    {
        return self::stringList($this->get('commands.deny', []));
    }

    public function includeHidden(): bool
    {
        return $this->get('commands.include_hidden') === true;
    }

    /** @return list<string> */
    public function riskPatterns(string $class): array
    {
        return self::stringList($this->get('risk.' . $class, []));
    }

    /** @return list<string> */
    public function writesFilesEnvironments(): array
    {
        return self::stringList($this->get('risk.writes_files_environments', ['local']));
    }

    public function requiresPassword(): bool
    {
        return $this->get('confirmation.require_password', true) !== false;
    }

    public function passwordTimeout(): int
    {
        $timeout = $this->get('confirmation.password_timeout')
            ?? $this->config->get('auth.password_timeout', 10800);

        return is_numeric($timeout) ? max(0, (int) $timeout) : 0;
    }

    public function limit(string $name, int $default): int
    {
        $value = $this->get('limits.' . $name, $default);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }

    public function ratePerMinute(): int
    {
        $value = $this->get('rate_limit.per_minute', 30);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : 30;
    }

    public function auditDriver(): AuditDriver
    {
        $driver = $this->get('audit.driver', AuditDriver::Log->value);

        if ($driver instanceof AuditDriver) {
            return $driver;
        }

        return AuditDriver::tryFrom(is_string($driver) ? $driver : '') ?? AuditDriver::Log;
    }

    public function auditChannel(): ?string
    {
        $channel = $this->get('audit.channel');

        return is_string($channel) && $channel !== '' ? $channel : null;
    }

    public function auditConnection(): ?string
    {
        $connection = $this->get('audit.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public function retentionDays(): int
    {
        $days = $this->get('audit.retention_days', 90);

        return is_numeric($days) && (int) $days > 0 ? (int) $days : 90;
    }

    public function schedulesPrune(): bool
    {
        return $this->get('audit.schedule_prune', true) !== false;
    }

    public function storesOutput(): bool
    {
        return $this->get('audit.store_output', true) !== false;
    }

    /** @return list<string> */
    public function redactionKeys(): array
    {
        return self::stringList($this->get('redaction.keys', []));
    }

    public function redactionMask(): string
    {
        $mask = $this->get('redaction.mask');

        return is_string($mask) && $mask !== '' ? $mask : '••••••••';
    }

    public function minScrubLength(): int
    {
        $length = $this->get('redaction.min_scrub_length', 6);

        return is_numeric($length) && (int) $length > 0 ? (int) $length : 6;
    }

    /** @return array<string, string> pattern => class */
    public function decorators(): array
    {
        $decorators = $this->get('decorators', []);

        if (! is_array($decorators)) {
            return [];
        }

        $valid = [];

        foreach ($decorators as $pattern => $class) {
            if (is_string($pattern) && is_string($class) && $class !== '') {
                $valid[$pattern] = $class;
            }
        }

        return $valid;
    }

    public function presetsEnabled(): bool
    {
        return $this->get('presets.enabled', true) !== false;
    }

    /** @return array<array-key, mixed> */
    public function presetGroups(): array
    {
        $groups = $this->get('presets.groups', []);

        return is_array($groups) ? $groups : [];
    }

    /**
     * The theme directory name, constrained to a slug so it can never walk out of the
     * themes directory when interpolated into a view name.
     */
    public function theme(): string
    {
        $theme = $this->get('theme', 'default');

        return is_string($theme) && Pattern::matches(Pattern::SLUG, $theme) ? $theme : 'default';
    }

    public function assetMode(): AssetMode
    {
        $mode = $this->get('assets.mode', AssetMode::Route->value);

        if ($mode instanceof AssetMode) {
            return $mode;
        }

        return AssetMode::tryFrom(is_string($mode) ? $mode : '') ?? AssetMode::Route;
    }

    public function assetRoute(): string
    {
        $route = $this->get('assets.route');

        $route = is_string($route) ? trim($route, '/') : '';

        return $route === '' ? 'vendor/laranail-artisan-ui' : $route;
    }
}
