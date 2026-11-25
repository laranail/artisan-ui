<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Testing\AssertsNoFacadeCalls;

uses(AssertsNoFacadeCalls::class);

/*
 * AUI-25: package classes take the framework's contracts in their constructors (laranail's
 * "Packages are framework-native" rule), so every dependency is visible to a reader and a test.
 * Service providers, the composition root, are exempt; a call that cannot be injected (an
 * Eloquent model, which the framework builds without the container) is marked on its line.
 *
 * The floor is the 75 files src/ held when this landed (2026-11-25): a scan that stops matching
 * fails rather than reporting a clean package.
 */
it('keeps the package classes off facades and service-locating helpers', function (): void {
    $this->assertNoFacadeCallsIn(dirname(__DIR__, 2) . '/src', minFiles: 75);
});
