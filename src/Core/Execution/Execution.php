<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Execution;

/**
 * A finished run as the caller sees it: the result, and the output after decoration and
 * redaction. The raw output never leaves the executor.
 */
final readonly class Execution
{
    public function __construct(
        public RunContext $context,
        public RunResult $result,
        public string $output,
    ) {}
}
