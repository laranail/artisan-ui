import { describe, expect, it, vi } from 'vitest'
import { createArtisanUi } from '../../resources/js/create.js'
import { mountArrayField } from '../../resources/js/dom/array-field.js'
import { mountCommandList } from '../../resources/js/dom/command-list.js'
import { mountTheme } from '../../resources/js/dom/theme.js'

const tick = () => new Promise((resolve) => setTimeout(resolve, 0))
const response = (status, body) => ({ status, json: async () => body })
const ok = () => response(200, { success: true, status: 'succeeded', output: [] })

function page({ withDialog = true } = {}) {
  document.head.innerHTML = '<meta name="csrf-token" content="tok">'
  document.body.innerHTML = `
    <div data-laranail-artisan-ui data-csrf-url="/csrf">
      <form data-lau-run action="/run" data-command="demo" data-confirm-url="/confirm">
        <button type="submit" data-lau-run-button></button><p data-lau-status></p>
      </form>
      <section data-lau-output-panel hidden><pre data-lau-output></pre><p data-lau-truncated hidden></p></section>
      ${
        withDialog
          ? '<dialog data-lau-password><form data-lau-password-form><input type="password"><p data-lau-password-error hidden></p><button type="submit"></button></form></dialog>'
          : ''
      }
    </div>`
}

const submit = () =>
  document.querySelector('form[data-lau-run]').dispatchEvent(new Event('submit', { cancelable: true }))
const status = () => document.querySelector('[data-lau-status]').textContent

describe('UI review regressions', () => {
  it('sends one run for two submits in the same tick (#1)', async () => {
    page()
    const fetch = vi.fn(async () => ok())
    createArtisanUi({ document, window, fetch, storage: null }).mount()

    submit()
    submit()
    await tick()
    await tick()

    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('confirms the password once however often the dialog is submitted (#2)', async () => {
    page()
    const calls = []
    const fetch = vi.fn(async (url) => {
      const path = new URL(url, 'http://localhost').pathname
      calls.push(path)
      if (path === '/confirm') return response(200, { confirmed: true })
      return calls.filter((c) => c === '/run').length === 1 ? response(423, { requires: 'password' }) : ok()
    })
    const dialog = document.querySelector('dialog')
    dialog.showModal = () => dialog.setAttribute('open', '')
    dialog.close = () => dialog.removeAttribute('open')
    createArtisanUi({ document, window, fetch, storage: null }).mount()

    submit()
    await tick()
    await tick()

    const passwordForm = document.querySelector('[data-lau-password-form]')
    passwordForm.dispatchEvent(new Event('submit', { cancelable: true }))
    passwordForm.dispatchEvent(new Event('submit', { cancelable: true }))
    await tick()
    await tick()
    await tick()

    expect(calls).toEqual(['/run', '/confirm', '/run'])
  })

  it('leaves no "Running..." behind on 423 and after the dialog closes (#3)', async () => {
    page()
    const dialog = document.querySelector('dialog')
    dialog.showModal = () => dialog.setAttribute('open', '')
    dialog.close = () => {
      dialog.removeAttribute('open')
      dialog.dispatchEvent(new Event('close'))
    }
    createArtisanUi({ document, window, fetch: async () => response(423, {}), storage: null }).mount()

    submit()
    await tick()
    await tick()
    expect(status()).toContain('Confirm your password')

    dialog.close()
    expect(status()).toContain('Nothing was run')
  })

  it('treats a 200 that is not a run payload as a failure, not a crash (#3)', async () => {
    page()
    createArtisanUi({ document, window, fetch: async () => response(200, null), storage: null }).mount()

    submit()
    await tick()
    await tick()

    expect(status()).toContain('Something went wrong')
    expect(document.querySelector('[data-lau-run-button]').hasAttribute('disabled')).toBe(false)
  })

  it('opens the password prompt without showModal (#4)', async () => {
    page()
    const dialog = document.querySelector('dialog')
    dialog.showModal = undefined
    createArtisanUi({ document, window, fetch: async () => response(423, {}), storage: null }).mount()

    submit()
    await tick()
    await tick()

    expect(dialog.hasAttribute('open')).toBe(true)
  })

  it('says so when the session could not be refreshed (#10)', async () => {
    page()
    const fetch = async (url) =>
      new URL(url, 'http://localhost').pathname === '/csrf' ? response(500, null) : response(419, {})
    createArtisanUi({ document, window, fetch, storage: null }).mount()

    submit()
    await tick()
    await tick()
    await tick()

    expect(status()).toContain('could not be refreshed')
  })

  it('runs no afterRun hook for an aborted request (#11)', async () => {
    page()
    let release
    const fetch = (_url, init) =>
      new Promise((resolve, reject) => {
        release = resolve
        init.signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' })))
      })
    const ui = createArtisanUi({ document, window, fetch, storage: null }).mount()
    const after = vi.fn()
    ui.hooks.add('afterRun', after)

    submit()
    await tick()
    ui.destroy()
    await tick()
    await tick()

    expect(after).not.toHaveBeenCalled()
    expect(release).toBeTypeOf('function')
  })
})

describe('array fields (#8)', () => {
  it('hands the label id to the next input and keeps focus in the list', () => {
    document.body.innerHTML = `
      <div data-lau-array>
        <div data-lau-array-rows>
          <div data-lau-array-row><input id="f" value="a"><button type="button" data-lau-array-remove>x</button></div>
          <div data-lau-array-row><input value="b"><button type="button" data-lau-array-remove>x</button></div>
        </div>
        <button type="button" data-lau-array-add>+</button>
      </div>`
    mountArrayField(document.querySelector('[data-lau-array]'))

    document.querySelector('[data-lau-array-remove]').click()

    const inputs = document.querySelectorAll('[data-lau-array-row] input')
    expect(inputs).toHaveLength(1)
    expect(inputs[0].id).toBe('f')
    expect(document.activeElement).toBe(inputs[0])
  })
})

describe('command list (#9)', () => {
  it('restores each group to how it was before the search', () => {
    document.body.innerHTML = `
      <section data-lau-command-list>
        <input data-lau-search>
        <p data-lau-no-matches hidden></p>
        <details data-lau-group="make"><summary>make</summary><ul><li data-lau-command data-name="make:model" data-description="a"></li></ul></details>
        <details data-lau-group="db" open><summary>db</summary><ul><li data-lau-command data-name="db:show" data-description="b"></li></ul></details>
      </section>`
    const list = mountCommandList(document.querySelector('[data-lau-command-list]'), { window })
    const search = document.querySelector('[data-lau-search]')
    const [make, db] = document.querySelectorAll('details')

    search.value = 'model'
    list.filter()
    expect(make.open).toBe(true)

    search.value = ''
    list.filter()
    expect([make.open, db.open]).toEqual([false, true])
  })
})

describe('theme (#12)', () => {
  it('sets no class without a saved choice, so the CSS follows the system; the toggle writes a choice', () => {
    document.documentElement.className = ''
    const store = new Map()
    const storage = { getItem: (k) => store.get(k) ?? null, setItem: (k, v) => store.set(k, v) }
    const button = document.createElement('button')
    const win = { matchMedia: () => ({ matches: true }) }

    mountTheme(button, { document, window: win, storage })
    expect(document.documentElement.className).toBe('')
    expect(button.getAttribute('aria-pressed')).toBe('true')

    button.click()
    expect(document.documentElement.classList.contains('light')).toBe(true)
    expect(store.get('laranail-artisan-ui:theme')).toBe('light')
  })
})

describe('password dialog close after a successful confirmation', () => {
  it('does not claim the run was cancelled while it runs', async () => {
    page()
    const calls = []
    const statuses = []
    const fetch = async (url) => {
      const path = new URL(url, 'http://localhost').pathname
      calls.push(path)
      if (path === '/confirm') return response(200, { confirmed: true })
      if (calls.filter((c) => c === '/run').length === 1) return response(423, {})
      // Mid-run: let the dialog's queued close event fire, then look at the status.
      await tick()
      statuses.push(document.querySelector('[data-lau-status]').textContent)
      return ok()
    }
    const dialog = document.querySelector('dialog')
    dialog.showModal = () => dialog.setAttribute('open', '')
    // Like a real <dialog>, the close event is queued, not dispatched synchronously.
    dialog.close = () => {
      dialog.removeAttribute('open')
      setTimeout(() => dialog.dispatchEvent(new Event('close')), 0)
    }
    createArtisanUi({ document, window, fetch, storage: null }).mount()

    submit()
    await tick()
    await tick()
    document.querySelector('[data-lau-password-form]').dispatchEvent(new Event('submit', { cancelable: true }))
    await tick()
    await tick()
    await tick()

    expect(statuses.join(' ')).not.toContain('Nothing was run')
    expect(status()).not.toContain('Nothing was run')
  })
})

describe('round 3', () => {
  it('does not run when the dialog is cancelled while the password is being checked', async () => {
    page()
    const calls = []
    let answerConfirm
    const fetch = (url) => {
      const path = new URL(url, 'http://localhost').pathname
      calls.push(path)
      if (path === '/confirm') return new Promise((resolve) => (answerConfirm = resolve))
      return Promise.resolve(response(423, {}))
    }
    const dialog = document.querySelector('dialog')
    dialog.showModal = () => dialog.setAttribute('open', '')
    dialog.close = () => {
      dialog.removeAttribute('open')
      setTimeout(() => dialog.dispatchEvent(new Event('close')), 0)
    }
    createArtisanUi({ document, window, fetch, storage: null }).mount()

    submit()
    await tick()
    await tick()
    document.querySelector('[data-lau-password-form]').dispatchEvent(new Event('submit', { cancelable: true }))
    await tick()

    // Esc while the check is pending.
    dialog.close()
    await tick()

    answerConfirm(response(200, { confirmed: true }))
    await tick()
    await tick()
    await tick()

    expect(calls).toEqual(['/run', '/confirm'])
    expect(status()).toContain('Nothing was run')
  })

  it('refuses, and frees the button, when a beforeRun hook returns something that is not a payload', async () => {
    page()
    const fetch = vi.fn(async () => ok())
    const ui = createArtisanUi({ document, window, fetch, storage: null }).mount()
    ui.hooks.add('beforeRun', () => ({ command: 'demo' }))

    submit()
    await tick()
    await tick()

    expect(fetch).not.toHaveBeenCalled()
    expect(status()).toContain('Something went wrong')
    expect(document.querySelector('[data-lau-run-button]').hasAttribute('disabled')).toBe(false)
  })

  it('treats a hook returning null as "no change"', async () => {
    page()
    const fetch = vi.fn(async () => ok())
    const ui = createArtisanUi({ document, window, fetch, storage: null }).mount()
    ui.hooks.add('beforeRun', () => null)

    submit()
    await tick()
    await tick()

    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('frees the button when a run:before listener throws', async () => {
    page()
    const fetch = vi.fn(async () => ok())
    createArtisanUi({ document, window, fetch, storage: null }).mount()
    const form = document.querySelector('form[data-lau-run]')
    const boom = () => {
      throw new Error('listener broke')
    }
    form.addEventListener('laranail-artisan-ui:run:before', boom)

    submit()
    await tick()
    await tick()

    form.removeEventListener('laranail-artisan-ui:run:before', boom)
    expect(document.querySelector('[data-lau-run-button]').hasAttribute('disabled')).toBe(false)
    expect(status()).toContain('Something went wrong')
    expect(fetch).not.toHaveBeenCalled()
  })
})
