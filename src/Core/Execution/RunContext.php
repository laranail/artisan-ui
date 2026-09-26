<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Execution;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * Everything known about a run before it starts: who, what, from where, and its id.
 *
 * The id is generated here rather than by the audit store, so every recorder and every event
 * listener sees the same id whether or not a database is involved.
 */
final readonly class RunContext
{
    /**
     * @param array{arguments: array<array-key, mixed>, options: array<array-key, mixed>} $redactedInput
     */
    public function __construct(
        public string $runId,
        public CommandDefinition $command,
        public ValidatedInput $input,
        public array $redactedInput,
        public CommandRisk $risk,
        public ?Authenticatable $actor,
        public ?string $ip,
        public CarbonImmutable $startedAt,
    ) {}

    public function actorId(): ?string
    {
        $id = $this->actor?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : null;
    }

    public function actorType(): ?string
    {
        return $this->actor instanceof Authenticatable ? $this->actor::class : null;
    }
}
