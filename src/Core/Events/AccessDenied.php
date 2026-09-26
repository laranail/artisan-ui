<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Events;

/**
 * Someone was refused the panel or a run. Scalars only, so a listener can queue it.
 *
 * `$reason` is one of: disabled, unauthenticated, environment, ip, gate, policy, forbidden,
 * writes_files_environment.
 */
final readonly class AccessDenied
{
    public function __construct(
        public string $reason,
        public ?string $ip,
        public string $path,
        public ?string $userId,
        public ?string $command = null,
    ) {}
}
