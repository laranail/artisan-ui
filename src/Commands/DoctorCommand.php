<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Commands;

use Simtabi\Laranail\ArtisanUI\Doctor\Checks;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorReporter;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `laranail::artisan-ui.doctor` reports whether the panel is reachable, by whom, and whether
 * it is exposed anywhere it should not be.
 */
final class DoctorCommand extends Command
{
    // Symfony rejects the empty segment in `::` when validating a command name. The trait
    // writes the name past that check; dispatch still works because an exact match is
    // resolved before the `:`-splitting namespace lookup.
    use SupportsNamespacedNames;

    protected $description = 'Check the artisan-ui setup: exposure, abilities, auditing and assets';

    protected $signature = 'laranail::artisan-ui.doctor
        {--json : Emit the report as JSON}
        {--strict : Treat warnings as failures}';

    public function handle(Checks $checks): int
    {
        return DoctorReporter::render($this, $checks->all(), (bool) $this->option('json'), (bool) $this->option('strict'));
    }
}
