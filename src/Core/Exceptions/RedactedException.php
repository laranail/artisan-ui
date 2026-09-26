<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Exceptions;

use Throwable;
use RuntimeException;
use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;

/**
 * A stand-in for an exception a command threw, with secrets scrubbed from its message.
 *
 * The failure-handling standard wants the cause preserved in the chain (rule 13) AND secrets
 * kept out of reports (rule 15). A driver's exception message routinely carries a DSN with a
 * password, and the exception handler logs the whole chain to logs and to monitoring. So the
 * chain is rebuilt from redacted copies: the original class, code, file and line are kept, the
 * message and the trace are scrubbed, and the same happens to every `previous` below it.
 */
final class RedactedException extends RuntimeException
{
    public function __construct(
        public readonly string $originalClass,
        string $message,
        int $code,
        public readonly string $originalFile,
        public readonly int $originalLine,
        public readonly string $originalTrace,
        ?Throwable $previous = null,
    ) {
        parent::__construct("[{$originalClass}] {$message}", $code, $previous);
    }

    public static function from(Throwable $exception, SecretRedactor $redactor, int $depth = 0): self
    {
        $previous = $exception->getPrevious();

        return new self(
            originalClass: $exception::class,
            message: $redactor->scrub($exception->getMessage()),
            code: (int) $exception->getCode(),
            originalFile: $exception->getFile(),
            originalLine: $exception->getLine(),
            originalTrace: $redactor->scrub($exception->getTraceAsString()),
            // Bounded, so a pathological cycle cannot recurse forever.
            previous: $previous instanceof Throwable && $depth < 10 ? self::from($previous, $redactor, $depth + 1) : null,
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function context(): array
    {
        return [
            'original_class' => $this->originalClass,
            'original_file'  => $this->originalFile,
            'original_line'  => $this->originalLine,
            'original_trace' => $this->originalTrace,
        ];
    }
}
