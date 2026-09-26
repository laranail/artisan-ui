<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\CommandRunner;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * A CommandRunner that records what it was asked to run and runs nothing.
 *
 * Installed with `ArtisanUI::fake()`. Responses are canned per command name with
 * `respondWith()`; anything without one succeeds with empty output.
 */
final class FakeRunner implements CommandRunner
{
    /** @var list<array{command: string, input: array{arguments: array<string, mixed>, options: array<string, mixed>}}> */
    private array $runs = [];

    /** @var array<string, RunResult|Closure(CommandDefinition, ValidatedInput): RunResult> */
    private array $responses = [];

    /**
     * @param RunResult|Closure(CommandDefinition, ValidatedInput): RunResult|string $response a string is
     *                                                                                         successful output
     */
    public function respondWith(string $command, RunResult|Closure|string $response): self
    {
        $this->responses[$command] = is_string($response)
            ? new RunResult(RunStatus::Succeeded, 0, $response, 0)
            : $response;

        return $this;
    }

    public function run(CommandDefinition $command, ValidatedInput $input): RunResult
    {
        $this->runs[] = ['command' => $command->name, 'input' => $input->toArray()];

        $response = $this->responses[$command->name] ?? new RunResult(RunStatus::Succeeded, 0, '', 0);

        return $response instanceof Closure ? $response($command, $input) : $response;
    }

    /**
     * @return list<array{command: string, input: array{arguments: array<string, mixed>, options: array<string, mixed>}}>
     */
    public function runs(): array
    {
        return $this->runs;
    }

    /**
     * @param (Closure(array{arguments: array<string, mixed>, options: array<string, mixed>}): bool)|null $matching
     */
    public function assertRan(string $command, ?Closure $matching = null): void
    {
        $found = array_filter(
            $this->runs,
            static fn (array $run): bool => $run['command'] === $command && (! $matching instanceof Closure || $matching($run['input'])),
        );

        Assert::assertNotEmpty($found, "Expected [{$command}] to have been run" . ($matching instanceof Closure ? ' with matching input.' : '.'));
    }

    public function assertNotRan(string $command): void
    {
        $found = array_filter($this->runs, static fn (array $run): bool => $run['command'] === $command);

        Assert::assertEmpty($found, "Expected [{$command}] not to have been run.");
    }

    public function assertNothingRan(): void
    {
        Assert::assertSame([], $this->runs, 'Expected nothing to have been run.');
    }
}
