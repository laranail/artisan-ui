<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Execution;

use Closure;

/**
 * Runs its callback at shutdown unless it was disarmed first.
 *
 * An object, not a by-reference flag in a closure: a refactoring tool cannot see a write
 * through a `use (&$flag)` capture, and one removed exactly that check here once, which made
 * every successful run get recorded as abandoned the moment the request ended.
 */
final class RunGuard
{
    private bool $armed = true;

    /**
     * @param Closure(): void $onAbandon
     */
    public function __construct(
        private readonly Closure $onAbandon,
    ) {}

    public function __invoke(): void
    {
        if ($this->armed) {
            $this->armed = false;
            ($this->onAbandon)();
        }
    }

    public function disarm(): void
    {
        $this->armed = false;
    }

    public function isArmed(): bool
    {
        return $this->armed;
    }
}
