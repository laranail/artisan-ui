# Contributing

Where this file is silent, the [laranail contributing guide](https://github.com/laranail/.github/blob/HEAD/CONTRIBUTING.md) applies.

Thanks for considering a contribution.

## Getting set up

```bash
composer install
npm install
```

The package depends on sibling laranail packages resolved through git, not Packagist, so `composer install` needs network access to GitHub. After a laranail tag moves, run `composer clear-cache` first: the dist cache is keyed on the tag name, so a stale archive installs silently.

## Running the checks

```bash
composer test          # Pest: unit, feature and architecture suites
composer lint          # laranail-pint, PHPStan level 8, Rector (dry run)
npm test               # Vitest, for the browser client
npm run lint           # Biome, for resources/js, resources/css and tests/js
npm run build          # rebuild resources/dist
```

CI runs the PHP suite on 8.4 and 8.5.

## The built assets are committed

`resources/dist/` holds the browser client and the stylesheet and **is** tracked, because consumers install through Composer and never run npm. If you change anything under `resources/js`, `resources/css` or a view (Tailwind scans the views for classes), run `npm run build` and commit the result. CI fails on drift.

## Trying the panel

```bash
composer serve
```

`composer serve` builds the workbench application (`workbench/`, never shipped) and serves it. Sign in at `/login` as `admin@example.test` with the workbench fixture password `workbench-password`, or as `member@example.test` to see a denial.

## Conventions

- PHP `^8.4.1`, `declare(strict_types=1)` everywhere, the family's shared Pint ruleset.
- PHPStan level 8 with no baseline. A baseline is permission to be wrong.
- Every public name carries the vendor: `laranail/artisan-ui::` for views and translations, `laranail-artisan-ui` for Blade tags, middleware, routes, rate limiters and abilities, `laranail::artisan-ui.*` for commands. `tests/Feature/NamingConventionTest.php` reads them back from the live registries.
- `Core` never uses the HTTP layer; the architecture tests enforce the boundary.
- Failures follow the family's failure-handling standard: classify each one as critical or degradable, report through the exception handler, never branch on the environment, never show internal detail to users.
- Anything touching authorization, the command policy, risk classification or the execute endpoint needs a test that fails without the change. Say which one in the pull request.

## Pull requests

Branch from `main` and open a pull request. `main` only moves through pull requests.
