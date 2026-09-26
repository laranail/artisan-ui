<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Support;

use Illuminate\Support\Str;

/**
 * Matches command names against the wildcard patterns used by the policy and the risk lists.
 *
 * Upstream PR #9 matched with `str_starts_with`, so `migrate:*` never matched the bare
 * `migrate` command and `db:*` never matched `db`. Here a namespace pattern (`name:*`) also
 * covers the command that shares the namespace's name, because that is what anyone writing
 * `migrate:*` in a deny list means.
 */
final class CommandPattern
{
    public static function matches(string $pattern, string $name): bool
    {
        if (Str::is($pattern, $name)) {
            return true;
        }

        return str_ends_with($pattern, ':*') && $name === substr($pattern, 0, -2);
    }

    /**
     * @param iterable<string> $patterns
     */
    public static function any(iterable $patterns, string $name): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
