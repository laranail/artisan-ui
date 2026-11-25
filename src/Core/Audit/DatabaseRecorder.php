<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Audit;

use Throwable;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * A row per run, written when the run starts and completed when it ends.
 *
 * The table can disappear under the recorder: `migrate:fresh` and `db:wipe` drop it, and they
 * are exactly the commands worth auditing. Whenever the row cannot be written or no longer
 * exists, the record goes to the log instead, so the run is never unaccounted for. Point
 * `audit.connection` at a connection those commands do not touch to keep the history itself.
 *
 * A write failure is degradable (the run is still recorded, in the log): it is reported
 * through package-tools' FailurePolicy and recorded in BootReport, so `boot:health` and
 * `laranail::package-tools.doctor` show the audit store as degraded instead of it failing
 * quietly.
 */
final class DatabaseRecorder implements RunRecorder
{
    /**
     * Runs whose start went to the log instead of the table, so the finish does not log the
     * start a second time (AUI-19). Lives as long as the recorder, which is bound per resolve,
     * never as a singleton, so it holds the runs of one execution at most.
     *
     * @var array<string, true>
     */
    private array $startLogged = [];

    public function __construct(
        private readonly LogRecorder $fallback,
        private readonly SecretRedactor $redactor,
        private readonly ArtisanUIConfig $config,
    ) {}

    public function started(RunContext $context): void
    {
        try {
            CommandRun::query()->create([
                'id'         => $context->runId,
                'user_type'  => $context->actorType(),
                'user_id'    => $context->actorId(),
                'command'    => $context->command->name,
                'arguments'  => $context->redactedInput['arguments'],
                'options'    => $context->redactedInput['options'],
                'risk'       => $context->risk,
                'status'     => RunStatus::Running,
                'ip'         => $context->ip,
                'started_at' => $context->startedAt,
            ]);
        } catch (Throwable $e) {
            FailurePolicy::handle($e, 'laranail/artisan-ui:audit.database', BootCriticality::Degradable);
            $this->fallback->started($context);
            $this->startLogged[$context->runId] = true;
        }
    }

    public function finished(RunContext $context, RunResult $result, string $output): void
    {
        try {
            $updated = CommandRun::query()->whereKey($context->runId)->update([
                'status'           => $result->status->value,
                'exit_code'        => $result->exitCode,
                'output'           => $this->config->storesOutput() ? $output : null,
                'output_truncated' => $result->truncated,
                'error'            => $result->exception instanceof Throwable
                    ? mb_substr($this->redactor->scrub($result->exception->getMessage()), 0, 2_000)
                    : null,
                'duration_ms' => $result->durationMs,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            FailurePolicy::handle($e, 'laranail/artisan-ui:audit.database', BootCriticality::Degradable);
            $updated = 0;
        }

        if ($updated === 0) {
            // The row went with the table (migrate:fresh), or the table was never there.
            FailurePolicy::warn('laranail/artisan-ui:audit.database', [
                'run_id'   => $context->runId,
                'command'  => $context->command->name,
                'expected' => 'the run row written at start',
                'actual'   => 'no row to complete',
                'decision' => 'recorded to the log channel instead',
            ]);
            // The start is logged here only when it went to the table: the log then has no record
            // of it. When it already fell back, logging it again made one run read as two.
            if (! isset($this->startLogged[$context->runId])) {
                $this->fallback->started($context);
            }

            $this->fallback->finished($context, $result, $output);
        }

        unset($this->startLogged[$context->runId]);
    }
}
