# Add a browser hook

Veto or rewrite a run in the browser before it is sent, from a script served by the application.

## Write the script

```js
// public/js/artisan-ui-hooks.js
const install = (ui) => {
  ui.hooks.add('beforeRun', ({ command, body }) => {
    if (command === 'down' && !body.options.secret) {
      return false // refuse to go down without a bypass secret
    }
  })
}

if (window.laranailArtisanUi) {
  install(window.laranailArtisanUi)
} else {
  document.addEventListener('laranail-artisan-ui:ready', (event) => install(event.detail.ui), { once: true })
}
```

## Load it

Add it to a themed `layout` view, after the package's script. It must come from the application's own origin, as a file, because the panel's CSP refuses inline and third-party scripts:

```blade
<script type="module" src="{{ asset('js/artisan-ui-hooks.js') }}"></script>
```

A hook that throws also vetoes the run. A browser hook is a convenience, not a control: enforce anything that matters in the `run` ability. See [Events and hooks](../tools/events.md#browser-hooks) and [Publish and theme the views](publish-and-theme-views.md).

---

[← Docs index](../../README.md#documentation)
