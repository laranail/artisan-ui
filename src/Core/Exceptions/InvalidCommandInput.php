<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Exceptions;

use RuntimeException;

/**
 * The submitted input does not fit the command's definition.
 *
 * Carries field errors keyed `arguments.<name>` / `options.<name>`, the same shape Laravel's
 * validator uses, so the web layer can hand them straight back to the form.
 */
final class InvalidCommandInput extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('The command input is invalid.');
    }
}
