<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Testing\FakeRunner;
use Simtabi\Laranail\ArtisanUI\ArtisanUI as Manager;
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * Deliberately not registered as a global alias: a bare `ArtisanUI` alias is exactly the
 * kind of flat-registry name the family keeps out. Import the class.
 *
 * @method static array<string, CommandDefinition> commands()
 * @method static CommandDefinition|null find(string $name)
 * @method static CommandRisk risk(string $name)
 * @method static Manager quickActions(string $key, string $label, list<QuickAction> $actions, ?string $description = null)
 * @method static Manager decorate(string $pattern, class-string<OutputDecorator>|OutputDecorator $decorator)
 * @method static FakeRunner fake(?FakeRunner $runner = null)
 *
 * @see Manager
 */
final class ArtisanUI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
