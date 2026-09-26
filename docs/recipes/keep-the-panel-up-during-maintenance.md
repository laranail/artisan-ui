# Keep the panel up during maintenance

Exempt the panel from maintenance mode, so running `down` from it does not lock out whoever then needs to run `up`.

## Exempt the path

In `bootstrap/app.php`:

```php
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->preventRequestsDuringMaintenance(except: ['artisan*']);
})
```

Use your `path` if it is not `artisan`, and include the asset route (`vendor/laranail-artisan-ui*`) if you want the panel styled while the application is down. `php artisan laranail::artisan-ui.doctor` then reports "/artisan is exempt from maintenance mode."

## Or bypass with a secret

Run `down` with `--secret` (the Maintenance quick action opens the form with that field ready) and open `/<secret>` once to receive the bypass cookie. Without either, `down` from the panel takes the panel down too; it is classed [destructive](../tools/risk-levels.md) for that reason.

---

[← Docs index](../../README.md#documentation)
