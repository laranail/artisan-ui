<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Execution;

use Throwable;
use ArrayObject;
use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Symfony\Component\Console\Output\OutputInterface;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\CommandRunner;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Runs a command in the current process, through the console kernel.
 *
 * `--no-interaction` is always set: a command that prompts (a confirmation, a `choice()`, the
 * production guard on `migrate`) would otherwise block on STDIN, which under a web server
 * either hangs the worker or fails on an undefined constant. This is the intent of upstream
 * PR #10's `setInteractive(false)`; the flag is the kernel-level spelling of the same thing.
 * Commands that want confirmation in production then refuse to proceed unless `--force` is
 * given, which is the correct outcome for a web panel.
 */
final readonly class SyncRunner implements CommandRunner
{
    public function __construct(
        private Kernel $artisan,
        private ArtisanUIConfig $config,
    ) {}

    public function run(CommandDefinition $command, ValidatedInput $input): RunResult
    {
        $output = new CappedOutput($this->config->limit('max_output_bytes', 1_000_000));

        $previousLimit = (int) ini_get('max_execution_time');
        set_time_limit($this->config->limit('time_limit', 120));

        $started = hrtime(true);

        // Text a command prints with echo/print/var_dump never reaches the console output. Left
        // alone it would go straight to the HTTP response in front of the JSON body: uncapped,
        // unredacted, unaudited. Capture it into the same capped buffer, in chunks so the cap
        // also bounds memory.
        $bufferLevel = ob_get_level();

        // Shared with the handler through an object, not a by-reference capture, which a
        // refactoring tool cannot see being written.
        $capture = new ArrayObject(['stopping' => false, 'lost' => false]);

        // 4 KB chunks bound memory for a command that echoes a lot; an ob_clean() of recent text
        // still works. Order against $this->line() is kept by flushing before every console write
        // (below), not by flushing every byte.
        ob_start(static function (string $chunk, int $phase) use ($output, $capture): string {
            // ob_clean()/ob_end_clean() by the command discards, as it would have without us.
            if (($phase & PHP_OUTPUT_HANDLER_CLEAN) === 0 && $chunk !== '') {
                $output->append($chunk);
            }

            // Our buffer ending when we did not end it: the command removed it, and anything it
            // prints from now on goes to the host's buffer, around the capture.
            if (($phase & PHP_OUTPUT_HANDLER_FINAL) !== 0 && ! $capture['stopping']) {
                $capture['lost'] = true;
            }

            return '';
        }, 4096);

        $output->beforeWrite(static function () use ($bufferLevel): void {
            if (ob_get_level() === $bufferLevel + 1) {
                ob_flush();
            }
        });

        // Idempotent: the result must be built after the last chunk is flushed, and `finally`
        // runs after a `return` expression has already been evaluated.
        $stopCapturing = static function () use ($bufferLevel, $capture, $output, $command): void {
            $capture['stopping'] = true;

            while (ob_get_level() > $bufferLevel) {
                ob_end_flush();
            }

            if ($capture['lost'] === true) {
                $capture['lost'] = false; // reported once; stopCapturing() runs more than once
                $output->writeln('');
                $output->writeln('[laranail/artisan-ui: the command removed the output buffer; output after that point was not captured]', OutputInterface::OUTPUT_RAW);
                FailurePolicy::warn('laranail/artisan-ui:run.capture', [
                    'command'  => $command->name,
                    'expected' => 'the capture buffer to stay in place for the whole run',
                    'actual'   => 'the command ended it (ob_end_clean / ob_get_clean)',
                    'decision' => 'output after that point bypassed capture and redaction',
                ]);
            }
        };

        try {
            $exitCode = $this->artisan->call(
                $command->name,
                [...$input->toParameters(), '--no-interaction' => true],
                $output,
            );

            $stopCapturing();

            return new RunResult(
                status: RunStatus::fromExitCode($exitCode),
                exitCode: $exitCode,
                output: $output->fetch(),
                durationMs: $this->elapsed($started),
                truncated: $output->truncated(),
            );
        } catch (Throwable $exception) {
            $stopCapturing();

            return new RunResult(
                status: RunStatus::Errored,
                exitCode: null,
                output: $output->fetch(),
                durationMs: $this->elapsed($started),
                truncated: $output->truncated(),
                exception: $exception,
            );
        } finally {
            $stopCapturing();
            set_time_limit($previousLimit);
        }
    }

    private function elapsed(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
