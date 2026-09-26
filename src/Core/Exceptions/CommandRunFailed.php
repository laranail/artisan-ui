<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Exceptions;

use Throwable;
use RuntimeException;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;

/**
 * A command threw while running from the panel.
 *
 * Reported through the application's exception handler so it reaches monitoring, not only
 * the audit trail (failure-handling standard, rule 3). The original throwable is kept as
 * `previous`, never flattened into this message (rule 13), and `context()` carries names and
 * ids only, never the input values (rule 15).
 *
 * The run itself is not re-thrown: the operator asked for the command, the command failed,
 * and the panel records and shows that. The decision taken is always `recorded-errored`.
 */
final class CommandRunFailed extends RuntimeException
{
    public function __construct(
        public readonly string $runId,
        public readonly string $command,
        public readonly string $risk,
        Throwable $previous,
    ) {
        parent::__construct(
            "laranail/artisan-ui run [{$runId}] of [{$command}] threw " . ($previous instanceof RedactedException ? $previous->originalClass : $previous::class),
            previous: $previous,
        );
    }

    public static function from(RunContext $context, Throwable $previous): self
    {
        return new self($context->runId, $context->command->name, $context->risk->value, $previous);
    }

    /** The class the command actually threw, looking through a RedactedException. */
    public function causeClass(): string
    {
        $previous = $this->getPrevious();

        return match (true) {
            $previous instanceof RedactedException => $previous->originalClass,
            $previous instanceof Throwable         => $previous::class,
            default                                => 'unknown',
        };
    }

    /**
     * @return array<string, string|null>
     */
    public function context(): array
    {
        return [
            'operation' => 'laranail/artisan-ui:run',
            'run_id'    => $this->runId,
            'command'   => $this->command,
            'risk'      => $this->risk,
            'expected'  => 'the command to return an exit code',
            'actual'    => 'threw ' . $this->causeClass(),
            'decision'  => 'recorded-errored',
        ];
    }
}
