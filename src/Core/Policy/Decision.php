<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Policy;

/**
 * The outcome of an access or run check, with the reason a denial happened.
 *
 * The reason is for logs and tests; the HTTP layer maps it to a status and never shows the
 * detail to the person who was denied.
 */
final readonly class Decision
{
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public int $status = 200,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, int $status = 403): self
    {
        return new self(false, $reason, $status);
    }
}
