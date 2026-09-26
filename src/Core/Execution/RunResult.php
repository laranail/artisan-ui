<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Execution;

use Throwable;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;

/**
 * What a runner reports back: the raw output, before decoration and redaction.
 */
final readonly class RunResult
{
    public function __construct(
        public RunStatus $status,
        public ?int $exitCode,
        public string $output,
        public int $durationMs,
        public bool $truncated = false,
        public ?Throwable $exception = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status->isSuccessful();
    }
}
