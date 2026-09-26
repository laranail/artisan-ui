<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Execution;

use Closure;
use Throwable;
use RuntimeException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Illuminate\Contracts\Cache\Repository as Cache;
use Simtabi\Laranail\ArtisanUI\Core\Support\Reporter;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandFailed;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandExecuted;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandBusy;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\CommandRunner;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandExecuting;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Output\DecoratorRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandRunFailed;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\RedactedException;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandNotRunnable;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Runs an authorized, validated command and everything that has to happen around it:
 * the lock, the audit record, the events, decoration and redaction.
 *
 * Failure handling follows the family standard, with no environment branch anywhere:
 *
 *  - a command that throws is recorded as Errored AND reported (CommandRunFailed);
 *  - a decorator that throws is a fail-closed degrade: the output is withheld rather than
 *    shown undecorated, because a decorator may exist precisely to hide something;
 *  - truncated output and a cache store that cannot lock are tolerated anomalies, logged
 *    at warning.
 *
 * Authorization is deliberately NOT here. The caller (the web module today) has already
 * asked CommandAuthorizer; this only re-checks the one thing that must never slip through,
 * the Forbidden class, because an executor that could be pointed at `serve` by a buggy
 * caller is worth a second line of defence.
 */
final readonly class CommandExecutor
{
    public function __construct(
        private CommandRunner $runner,
        private RiskClassifier $risk,
        private RunRecorder $recorder,
        private SecretRedactor $redactor,
        private DecoratorRegistry $decorators,
        private Dispatcher $events,
        private Cache $cache,
        private ArtisanUIConfig $config,
        // Tests capture the shutdown callback here; null means PHP's register_shutdown_function.
        private ?Closure $registerShutdown = null,
    ) {}

    /**
     * @throws CommandBusy when the command's lock is held
     * @throws CommandNotRunnable when the command is Forbidden
     */
    public function execute(
        CommandDefinition $command,
        ValidatedInput $input,
        ?Authenticatable $actor = null,
        ?string $ip = null,
    ): Execution {
        $risk = $this->risk->classify($command->name);

        if (! $risk->isRunnable()) {
            throw CommandNotRunnable::for($command->name);
        }

        $lock = $this->lock($command, $risk);

        if (! $lock instanceof Lock) {
            FailurePolicy::warn('laranail/artisan-ui:run.lock', [
                'command'  => $command->name,
                'expected' => 'a cache store implementing LockProvider',
                'actual'   => $this->cache->getStore()::class,
                'decision' => 'ran unlocked',
            ]);
        }

        if ($lock instanceof Lock && ! $lock->get()) {
            throw CommandBusy::for($command->name);
        }

        try {
            $context = new RunContext(
                runId: (string) Str::ulid(),
                command: $command,
                input: $input,
                redactedInput: $this->redactor->redactRunInput($input),
                risk: $risk,
                actor: $actor,
                ip: $ip,
                startedAt: CarbonImmutable::now(),
            );

            $this->recorder->started($context);

            // A fatal error, the time limit included, skips `finally`: without this the lock stays
            // held until it expires and the audit record stays "running" for ever. Registered per
            // run; it does nothing once the run has finished normally.
            $guard = new RunGuard(fn () => $this->abandon($context, $lock));
            ($this->registerShutdown ?? register_shutdown_function(...))($guard);

            $this->events->dispatch(new CommandExecuting($context));

            $result = $this->runner->run($command, $input);

            if ($result->exception instanceof Throwable) {
                // Reported with a redacted copy of the cause: the handler logs the whole chain,
                // and a driver's message routinely carries a DSN with its password.
                Reporter::report(CommandRunFailed::from($context, RedactedException::from($result->exception, $this->redactor)));
            }

            if ($result->truncated) {
                FailurePolicy::warn('laranail/artisan-ui:run.output', [
                    'run_id'   => $context->runId,
                    'command'  => $command->name,
                    'expected' => 'output within limits.max_output_bytes',
                    'actual'   => 'output exceeded the cap',
                    'decision' => 'truncated',
                ]);
            }

            $output = $this->redactor->scrub($this->decorate($command, $result), $result->truncated);

            $this->recorder->finished($context, $result, $output);

            // Recorded: from here on nothing is abandoned, even if a listener below throws.
            $guard->disarm();

            $this->events->dispatch($result->succeeded()
                ? new CommandExecuted($context, $result, $output)
                : new CommandFailed($context, $result, $output));

            return new Execution($context, $result, $output);
        } finally {
            // An exception escaped before the run was recorded as finished (a listener or the
            // recorder threw): close it out now rather than at worker exit, which in a
            // long-lived worker could be days away.
            if (isset($guard) && $guard->isArmed()) {
                $guard();
            }

            $lock?->release();
        }
    }

    /**
     * Close out a run the process did not survive: record it as errored and free its lock.
     * Called from the shutdown handler, so it must never throw.
     */
    public function abandon(RunContext $context, ?Lock $lock): void
    {
        try {
            $this->recorder->finished($context, new RunResult(
                status: RunStatus::Errored,
                exitCode: null,
                output: '',
                durationMs: (int) $context->startedAt->diffInMilliseconds(CarbonImmutable::now()),
                exception: new RuntimeException('The run was terminated before it finished (time limit or fatal error).'),
            ), '');
        } catch (Throwable $e) {
            error_log('laranail/artisan-ui: could not record abandoned run ' . $context->runId . ': ' . $e->getMessage());
        } finally {
            try {
                $lock?->release();
            } catch (Throwable) {
                // The lock expires on its own; see lockSeconds().
            }
        }
    }

    private function decorate(CommandDefinition $command, RunResult $result): string
    {
        try {
            return $this->decorators->apply($command, $result, $result->output);
        } catch (Throwable $e) {
            FailurePolicy::handle($e, 'laranail/artisan-ui:output.decorate', BootCriticality::Degradable);

            return '[laranail/artisan-ui: output withheld because an output decorator failed; see the application log]';
        }
    }

    /**
     * One lock per command, so two operators cannot run `migrate` at once. Destructive
     * commands share a single lock, so `migrate:fresh` cannot interleave with `db:wipe`.
     * A cache store without lock support runs unlocked; doctor reports it.
     */
    private function lock(CommandDefinition $command, CommandRisk $risk): ?Lock
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return null;
        }

        $key = 'laranail-artisan-ui:run:' . ($risk === CommandRisk::Destructive ? '*destructive*' : $command->name);

        return $store->lock($key, $this->lockSeconds());
    }

    /**
     * At least the time limit plus a margin, otherwise what the operator configured.
     *
     * Not capped from above: `set_time_limit()` counts CPU time on Linux, so a command waiting
     * on the database can run far past it by the clock, and a lock that expired under it would
     * let a second destructive run start alongside. A run killed outright releases its lock
     * from the shutdown guard instead of waiting for expiry.
     */
    private function lockSeconds(): int
    {
        $timeLimit = $this->config->limit('time_limit', 120);

        return max($timeLimit + 10, $this->config->limit('lock_seconds', 300));
    }
}
