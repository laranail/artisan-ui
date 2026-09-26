# Custom output decorator

Rewrite a command's output before it is shown and recorded, for example to prepend context or strip noise.

## Write and register it

```php
namespace App\ArtisanUI;

use Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;

final class StripDeprecationsDecorator implements OutputDecorator
{
    public function decorate(string $output, CommandDefinition $command, RunResult $result): string
    {
        return (string) preg_replace('/^.*Deprecated:.*\R/m', '', $output);
    }
}
```

```php
// config/laranail/artisan-ui.php
'decorators' => [
    'migrate:*' => App\ArtisanUI\StripDeprecationsDecorator::class,
],
```

Or at runtime: `ArtisanUI::decorate('migrate:*', StripDeprecationsDecorator::class);`. Redaction runs after every decorator, and a decorator that throws withholds the output. See [Output](../tools/output.md#decorators).

---

[← Docs index](../../README.md#documentation)
