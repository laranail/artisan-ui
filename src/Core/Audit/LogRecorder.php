<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Audit;

use Throwable;
use Psr\Log\LoggerInterface;
use Illuminate\Log\LogManager;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * One structured log line when a run starts and one when it ends. The default recorder,
 * because it needs no migration and every application already ships its logs somewhere.
 *
 * Output is not logged, only its size: a log line is the wrong place for a page of text,
 * and the redacted output is what the database recorder is for.
 */
final readonly class LogRecorder implements RunRecorder
{
    public function __construct(
        private LogManager $log,
        private ArtisanUIConfig $config,
    ) {}

    public function started(RunContext $context): void
    {
        $this->write('info', 'laranail/artisan-ui: run started', $this->base($context));
    }

    public function finished(RunContext $context, RunResult $result, string $output): void
    {
        $this->write($result->succeeded() ? 'info' : 'warning', 'laranail/artisan-ui: run finished', [
            ...$this->base($context),
            'status'       => $result->status->value,
            'exit_code'    => $result->exitCode,
            'duration_ms'  => $result->durationMs,
            'output_bytes' => strlen($output),
            'truncated'    => $result->truncated,
            'error'        => $result->exception instanceof Throwable ? $result->exception::class : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function base(RunContext $context): array
    {
        return [
            'run_id'  => $context->runId,
            'command' => $context->command->name,
            'input'   => $context->redactedInput,
            'risk'    => $context->risk->value,
            'user'    => $context->actorId(),
            'ip'      => $context->ip,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        try {
            $this->logger()->log($level, $message, $context);
        } catch (Throwable $e) {
            // The logging substrate itself: it cannot report through itself (standard,
            // exemptions), so the last resort is the PHP error log.
            error_log("laranail/artisan-ui: audit log write failed ({$message}): " . $e->getMessage());
        }
    }

    private function logger(): LoggerInterface
    {
        $channel = $this->config->auditChannel();

        return $channel === null ? $this->log->driver() : $this->log->channel($channel);
    }
}
