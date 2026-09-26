<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Policy;

use Closure;
use ReflectionFunction;
use Illuminate\Contracts\Auth\Access\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;

/**
 * Defines each Ability as deny-all, but only where the application has not defined it.
 *
 * package-tools' `registerGate()` overwrites unconditionally, which would let this package
 * silently replace an application's own definition if the application's provider happened to
 * boot first. `Gate::has()` first means the application always wins, whatever the order.
 *
 * Remembers which abilities it defined, so doctor can say "nobody can open the panel yet"
 * instead of the operator discovering it as a 403.
 */
final class DefaultAbilities
{
    /** @var array<string, true> */
    private array $defaulted = [];

    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function define(): void
    {
        foreach (Ability::cases() as $ability) {
            if ($this->gate->has($ability->value)) {
                continue;
            }

            $this->gate->define($ability->value, static fn (): bool => false);
            $this->defaulted[$ability->value] = true;
        }
    }

    /**
     * True while the ability is still the package's deny-all fallback. An application
     * definition made after boot (a test, a late provider) replaces the closure but not this
     * record, so it is re-checked against the registered callback.
     */
    public function isDefault(Ability $ability): bool
    {
        return isset($this->defaulted[$ability->value])
            && $this->gate->has($ability->value)
            && $this->isStillOurs($ability);
    }

    private function isStillOurs(Ability $ability): bool
    {
        $abilities = $this->gate->abilities();
        $callback = $abilities[$ability->value] ?? null;

        if (! $callback instanceof Closure) {
            return false;
        }

        $reflection = new ReflectionFunction($callback);

        return $reflection->getClosureScopeClass()?->getName() === self::class;
    }
}
