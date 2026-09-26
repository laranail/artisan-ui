# Test with the fake

Drive the panel from an application's own feature tests without running any command, while every check around the runner stays real.

## Install the fake and assert

```php
use App\Models\User;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

it('lets admins clear the cache from the panel', function (): void {
    config()->set('laranail.artisan-ui.environments', ['testing']);

    $fake = ArtisanUI::fake()->respondWith('cache:clear', 'Application cache cleared.');

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('laranail-artisan-ui.execute', 'cache:clear'))
        ->assertOk()
        ->assertJson(['success' => true]);

    $fake->assertRan('cache:clear');
    $fake->assertNotRan('migrate:fresh');
});

it('refuses support staff a migration', function (): void {
    config()->set('laranail.artisan-ui.environments', ['testing']);

    $fake = ArtisanUI::fake();

    $this->actingAs(User::factory()->support()->create())
        ->postJson(route('laranail-artisan-ui.execute', 'migrate'))
        ->assertForbidden();

    $fake->assertNothingRan();
});
```

The routes are registered at boot only while `enabled` is true, so enable the panel in the test environment's configuration (for example `LARANAIL_ARTISAN_UI_ENABLED=true` in `phpunit.xml`); setting it inside a test is too late. Everything else is read live and can be set per test, as `environments` is here. See [Extending](../tools/extending.md#fakerunner).

---

[← Docs index](../../README.md#documentation)
