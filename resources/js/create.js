import { emit } from './core/events.js'
import { HookRegistry } from './core/hooks.js'
import { nullNotifier } from './core/notifier.js'
import { RunClient } from './core/run-client.js'
import { mountArrayField } from './dom/array-field.js'
import { mountCommandList } from './dom/command-list.js'
import { mountRunForm } from './dom/run-form.js'
import { mountTheme } from './dom/theme.js'

/**
 * The composition root (engineering reference, Part IV pattern 1): every collaborator is a
 * parameter with a default, nothing reaches for a global it was not handed. That is what lets
 * the tests inject a fake `fetch` and a host swap the notifier or add hooks.
 *
 *     const ui = createArtisanUi({ notifier: myToasts })
 *     ui.hooks.add('beforeRun', ({ command, body }) => command === 'down' ? false : undefined)
 *     ui.mount()
 */
export function createArtisanUi({
  document = globalThis.document,
  window = globalThis.window,
  fetch = globalThis.fetch?.bind(globalThis),
  storage = safeStorage(globalThis.window),
  notifier = nullNotifier,
  hooks = new HookRegistry(),
} = {}) {
  const meta = () => document.querySelector('meta[name="csrf-token"]')

  const client = new RunClient({
    fetch,
    token: () => meta()?.getAttribute('content') ?? '',
    setToken: (token) => {
      meta()?.setAttribute('content', token)

      for (const input of document.querySelectorAll('input[name="_token"]')) {
        input.value = token
      }
    },
  })

  const mounted = []

  const ui = {
    hooks,
    client,

    mount() {
      const body = document.querySelector('[data-laranail-artisan-ui]')

      if (!body) {
        return ui
      }

      mounted.push(mountTheme(document.querySelector('[data-lau-theme-toggle]'), { document, window, storage }))

      const list = document.querySelector('[data-lau-command-list]')

      if (list) {
        mounted.push(mountCommandList(list, { window }))
      }

      for (const field of document.querySelectorAll('[data-lau-array]')) {
        mounted.push(mountArrayField(field))
      }

      const form = document.querySelector('form[data-lau-run]')

      if (form) {
        mounted.push(mountRunForm(form, { document, client, hooks, notifier, csrfUrl: body.dataset.csrfUrl }))
      }

      emit(body, 'ready', { ui })

      return ui
    },

    destroy() {
      for (const controller of mounted.splice(0)) {
        controller.destroy()
      }
    },
  }

  return ui
}

function safeStorage(window) {
  try {
    return window?.localStorage ?? null
  } catch {
    return null
  }
}
