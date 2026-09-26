<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Audit;

use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;

final class NullRecorder implements RunRecorder
{
    public function started(RunContext $context): void {}

    public function finished(RunContext $context, RunResult $result, string $output): void {}
}
