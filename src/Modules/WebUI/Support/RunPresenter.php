<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support;

use Throwable;
use Simtabi\Laranail\ArtisanUI\Core\Execution\Execution;
use Simtabi\Laranail\ArtisanUI\Core\Output\AnsiFormatter;
use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleInputException;

/**
 * The JSON a run returns to the browser.
 *
 * Output travels as ANSI segments, never HTML, and the client renders them with
 * `textContent`. An exception is described by run id only, in every environment
 * (failure-handling standard, rules 1 and 11), with one by-contract exception: Symfony's
 * console input errors ("Not enough arguments", "The option does not exist") are validation
 * messages about what the operator typed, so they are shown, redacted.
 */
final readonly class RunPresenter
{
    public function __construct(
        private AnsiFormatter $ansi,
        private SecretRedactor $redactor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(Execution $execution): array
    {
        $result = $execution->result;

        return [
            'run_id'       => $execution->context->runId,
            'command'      => $execution->context->command->name,
            'risk'         => $execution->context->risk->value,
            'status'       => $result->status->value,
            'status_label' => $result->status->label(),
            'success'      => $result->succeeded(),
            'exit_code'    => $result->exitCode,
            'duration_ms'  => $result->durationMs,
            'truncated'    => $result->truncated,
            'output'       => $this->ansi->segments($execution->output),
            'error'        => $this->error($execution),
        ];
    }

    private function error(Execution $execution): ?string
    {
        $exception = $execution->result->exception;

        if (! $exception instanceof Throwable) {
            return null;
        }

        if ($exception instanceof ConsoleInputException) {
            return $this->redactor->scrub($exception->getMessage());
        }

        return __('laranail/artisan-ui::messages.run_errored', ['run' => $execution->context->runId]);
    }
}
