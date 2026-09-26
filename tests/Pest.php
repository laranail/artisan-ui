<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Let everyone through the panel and the run ability, as an application's admin check would.
 */
function allowEverything(): void
{
    Gate::define(Ability::Access->value, static fn (): bool => true);
    Gate::define(Ability::Run->value, static fn (): bool => true);
    Gate::define(Ability::ViewHistory->value, static fn (): bool => true);
    Gate::define(Ability::ViewEnvironment->value, static fn (): bool => true);
}

/**
 * Compile Blade from scratch once per run: a template compiled against an earlier version of
 * a view would otherwise keep passing locally while CI (empty cache) fails.
 */
$compiled = __DIR__ . '/../vendor/orchestra/testbench-core/laravel/storage/framework/views';

if (is_dir($compiled)) {
    foreach (glob($compiled . '/*.php') ?: [] as $template) {
        @unlink($template);
    }
}
