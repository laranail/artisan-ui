# Allow only specific commands

Restrict the panel to a short list of commands, so everything else is neither listed nor runnable.

## Set an allow list

```php
// config/laranail/artisan-ui.php
'commands' => [
    'allow' => ['about', 'optimize', 'optimize:clear', '*:clear', 'migrate:status', 'queue:retry'],
    'deny'  => ['view:clear'],   // deny always wins, even over a matching allow pattern
],
```

An empty `allow` list allows nothing; `null` allows everything. Check the result before anyone opens the panel:

```bash
php artisan laranail::artisan-ui.policy --listed
```

See [Command policy](../tools/command-policy.md).

---

[← Docs index](../../README.md#documentation)
