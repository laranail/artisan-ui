<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;
use Simtabi\Laranail\ArtisanUI\Providers\ArtisanUIServiceProvider;
use Simtabi\Laranail\ArtisanUI\Tests\Fixtures\FixtureServiceProvider;
use Simtabi\Laranail\Package\Tools\Providers\PackageToolsServiceProvider;

/**
 * Boots the package the way an application with the panel switched on would.
 *
 * `testing` is added to the environment allowlist, since that is the environment the suite
 * runs in; every test asserting the allowlist itself changes it explicitly. The Gate
 * abilities are NOT defined here: the package default (deny) is what a test starts from.
 */
abstract class TestCase extends IsolatedTestCase
{
    public const string PASSWORD = 'correct-horse-battery-staple';

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PackageToolsServiceProvider::class, ArtisanUIServiceProvider::class, FixtureServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Generated per run: a key committed to a public repository is a key in the open.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('laranail.artisan-ui.enabled', true);
        $app['config']->set('laranail.artisan-ui.environments', ['testing']);
        $app['config']->set('laranail.artisan-ui.risk.destructive', ['lau-fixture:wipe']);
        $app['config']->set('laranail.artisan-ui.risk.writes_files', ['lau-fixture:make']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    protected function makeUser(string $email = 'operator@example.com'): User
    {
        $user = new User;
        $user->forceFill([
            'name'     => 'Operator',
            'email'    => $email,
            'password' => Hash::make(self::PASSWORD),
        ])->save();

        return $user;
    }
}
