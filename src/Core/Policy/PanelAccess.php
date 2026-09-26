<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Policy;

use Throwable;
use Illuminate\Contracts\Auth\Access\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Symfony\Component\HttpFoundation\IpUtils;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * Whether a user may open the panel at all, independent of how they arrived.
 *
 * Kept free of the HTTP layer so a future API or TUI module asks exactly the same question.
 * The checks run cheapest first and all of them must pass: enabled, environment, IP, a signed-in
 * user, then the Access ability. Every malformed input fails closed.
 */
final readonly class PanelAccess
{
    public function __construct(
        private ArtisanUIConfig $config,
        private Application $app,
        private Gate $gate,
    ) {}

    /**
     * @param list<string> $environments `*` matches every environment
     */
    public static function environmentMatches(Application $app, array $environments): bool
    {
        if (in_array('*', $environments, true)) {
            return true;
        }

        return $environments !== [] && $app->environment($environments);
    }

    public function inspect(?Authenticatable $user, ?string $ip): Decision
    {
        return match (true) {
            ! $this->config->enabled() => Decision::deny('disabled', 404),
            // Environment and network first: a guest from outside them must get a plain 403,
            // not a login redirect that confirms the panel is there.
            ! $this->environmentAllowed()                                 => Decision::deny('environment'),
            ! $this->ipAllowed($ip)                                       => Decision::deny('ip'),
            ! $user instanceof Authenticatable                            => Decision::deny('unauthenticated', 401),
            ! $this->gate->forUser($user)->allows(Ability::Access->value) => Decision::deny('gate'),
            default                                                       => Decision::allow(),
        };
    }

    public function environmentAllowed(): bool
    {
        return self::environmentMatches($this->app, $this->config->environments());
    }

    public function ipAllowed(?string $ip): bool
    {
        $allowed = $this->config->allowedIps();

        if ($allowed === []) {
            return true;
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        try {
            return IpUtils::checkIp($ip, $allowed);
        } catch (Throwable) {
            // A malformed allowlist entry must not open the panel.
            return false;
        }
    }
}
