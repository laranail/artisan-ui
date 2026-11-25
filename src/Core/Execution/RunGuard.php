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
 *
 * The callback is dropped as soon as the guard is spent. PHP keeps every registered shutdown
 * function until the process exits, and in a long-lived worker that is every run's guard; one
 * that kept its callback kept the executor, the run's unredacted input and its lock reachable
 * for the life of the worker.
 */
final class RunGuard
{
    /** @var (Closure(): void)|null */
    private ?Closure $onAbandon;

    /**
     * @param Closure(): void $onAbandon
     */
    public function __construct(Closure $onAbandon)
    {
        $this->onAbandon = $onAbandon;
    }

    public function __invoke(): void
    {
        $callback = $this->onAbandon;
        $this->onAbandon = null;

        if ($callback instanceof Closure) {
            $callback();
        }
    }

    public function disarm(): void
    {
        $this->onAbandon = null;
    }

    public function isArmed(): bool
    {
        return $this->onAbandon instanceof Closure;
    }
}
