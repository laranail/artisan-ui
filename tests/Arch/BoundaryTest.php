<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\ArtisanUI\Core\Policy\Decision;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunContext;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

arch('the core is headless: no HTTP, routing, views or modules')
    ->expect('Simtabi\Laranail\ArtisanUI\Core')
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Routing',
        'Illuminate\View',
        Route::class,
        View::class,
        'Simtabi\Laranail\ArtisanUI\Modules',
    ]);

arch('the web module reaches the core, never the other way round')
    ->expect('Simtabi\Laranail\ArtisanUI\Core')
    ->not->toBeUsedIn('Simtabi\Laranail\ArtisanUI\Enums');

arch('every file declares strict types')
    ->expect('Simtabi\Laranail\ArtisanUI')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('value objects are immutable')
    ->expect([
        CommandDefinition::class,
        ValidatedInput::class,
        RunResult::class,
        RunContext::class,
        Decision::class,
    ])
    ->toBeReadonly();

arch('enums are enumerator enums')
    ->expect('Simtabi\Laranail\ArtisanUI\Enums')
    ->toImplement(Enumerator::class);
