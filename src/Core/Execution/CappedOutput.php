<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Execution;

use Closure;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A buffered output that stops accepting text past a byte limit.
 *
 * Symfony's BufferedOutput grows without bound, so a command that prints a large table (or
 * loops) could exhaust the worker's memory. Past the cap the rest is counted and dropped, and
 * the result is flagged as truncated.
 *
 * Decorated, so ANSI colour survives to AnsiFormatter.
 */
final class CappedOutput extends Output
{
    private string $buffer = '';

    private bool $truncated = false;

    /** @var (Closure(): void)|null */
    private ?Closure $beforeWrite = null;

    private bool $inBeforeWrite = false;

    public function __construct(
        private readonly int $maxBytes,
    ) {
        parent::__construct(OutputInterface::VERBOSITY_NORMAL, true);
    }

    /**
     * Run before every console write. SyncRunner uses it to flush the echo capture first, so
     * text a command echoed and text it wrote through the console keep their true order. A
     * flush calls back into write(), so re-entry is guarded.
     *
     * @param Closure(): void $callback
     */
    public function beforeWrite(Closure $callback): void
    {
        $this->beforeWrite = $callback;
    }

    /**
     * Append text captured from PHP's output buffer: raw, and without re-running the
     * before-write hook, since the buffer is being flushed right now.
     */
    public function append(string $text): void
    {
        // Called from inside PHP's output handler, where ob_flush() is fatal.
        $previous = $this->inBeforeWrite;
        $this->inBeforeWrite = true;

        try {
            $this->doWrite($text, false);
        } finally {
            $this->inBeforeWrite = $previous;
        }
    }

    public function fetch(): string
    {
        return $this->buffer;
    }

    public function truncated(): bool
    {
        return $this->truncated;
    }

    protected function doWrite(string $message, bool $newline): void
    {
        if ($this->beforeWrite instanceof Closure && ! $this->inBeforeWrite) {
            $this->inBeforeWrite = true;

            try {
                ($this->beforeWrite)();
            } finally {
                $this->inBeforeWrite = false;
            }
        }

        if ($newline) {
            $message .= PHP_EOL;
        }

        $remaining = $this->maxBytes - strlen($this->buffer);

        if ($remaining <= 0) {
            $this->truncated = $this->truncated || $message !== '';

            return;
        }

        if (strlen($message) > $remaining) {
            $message = mb_strcut($message, 0, $remaining);
            $this->truncated = true;
        }

        $this->buffer .= $message;
    }
}
