# Publish and theme the views

Override a few of the panel's views, or build a named theme, while every view you do not touch keeps coming from the package.

## Create a theme

Views resolve as `themes.<theme>.<view>` and fall back to the `default` theme one view at a time, so a theme only needs the files it changes. Publishing gives you the originals to copy from:

```bash
php artisan vendor:publish --tag=laranail::artisan-ui-views
```

That writes `resources/views/vendor/laranail/artisan-ui/themes/default/…`. Copy the views you want to change into a sibling directory named for your theme, delete the published `default` copies you did not change (a published default view overrides the package's, and stops receiving upgrades), and select the theme:

```text
resources/views/vendor/laranail/artisan-ui/themes/acme/
├── layout.blade.php          your header, your stylesheet
└── partials/risk-badge.blade.php
```

```php
// config/laranail/artisan-ui.php
'theme' => 'acme',
```

The theme name must be a lowercase slug (`acme`, `ops-dark`), or the panel falls back to `default`. The overridable views are `layout`, `home`, `command`, `history`, `partials.field` and `partials.risk-badge`.

Resolve includes and layouts through the theme, as the default views do, so a theme's own `layout` is picked up:

```blade
@extends(app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView::class)->resolve('layout'))
```

Keep the `data-lau-*` attributes and the `<body data-laranail-artisan-ui>` marker the script looks for, and load any extra stylesheet or script from the application's own origin: the CSP refuses inline code and other hosts. See [Web UI](../tools/web-ui.md#theming).

---

[← Docs index](../../README.md#documentation)
