import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createArtisanUi } from '../../resources/js/create.js'

const page = () => `
  <meta name="csrf-token" content="tok">
  <div data-laranail-artisan-ui data-csrf-url="/artisan/csrf-token">
    <form data-lau-run action="/artisan/commands/demo/run" data-command="demo" data-confirm-url="/artisan/confirm-password">
      <input name="_token" value="tok">
      <input id="f-name" name="arguments[name]" value="users" aria-describedby="f-name-error">
      <p id="f-name-error" data-lau-error-for="arguments.name" hidden></p>
      <p data-lau-error-for="confirm" hidden></p>
      <button type="submit" data-lau-run-button><span data-lau-run-label>Run</span><span data-lau-running-label hidden>...</span></button>
      <p data-lau-status></p>
    </form>
    <section data-lau-output-panel hidden><pre data-lau-output></pre><p data-lau-truncated hidden></p></section>
    <dialog data-lau-password><form data-lau-password-form><input type="password"><p data-lau-password-error hidden></p><button type="button" data-lau-password-cancel></button></form></dialog>
  </div>`

const response = (status, body) => ({ status, json: async () => body })
const path = (url) => new URL(url, 'http://localhost').pathname

function mount(fetch) {
  document.head.innerHTML = ''
  document.body.innerHTML = page()
  const dialog = document.querySelector('dialog')
  dialog.showModal ??= () => {}
  dialog.close ??= () => {}

  return createArtisanUi({ document, window, fetch, storage: null }).mount()
}

async function submit() {
  document.querySelector('form[data-lau-run]').dispatchEvent(new Event('submit', { cancelable: true }))
  await new Promise((resolve) => setTimeout(resolve, 0))
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('run form', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('renders a successful run from segments', async () => {
    const fetch = vi.fn(async () =>
      response(200, {
        success: true,
        status: 'succeeded',
        status_label: 'Succeeded',
        exit_code: 0,
        duration_ms: 12,
        output: [{ text: 'done <b>', classes: 'lau-fg-green' }],
      }),
    )
    mount(fetch)

    await submit()

    expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ arguments: { name: 'users' }, options: {} })
    expect(document.querySelector('[data-lau-output]').textContent).toBe('done <b>')
    expect(document.querySelector('[data-lau-output] b')).toBeNull()
    expect(document.querySelector('[data-lau-output-panel]').hidden).toBe(false)
    expect(document.querySelector('[data-lau-status]').textContent).toContain('Succeeded')
  })

  it('shows field errors on 422 and marks the input invalid', async () => {
    mount(async () => response(422, { message: 'Check', errors: { 'arguments.name': ['Required.'] } }))

    await submit()

    const error = document.querySelector('[data-lau-error-for="arguments.name"]')
    expect(error.hidden).toBe(false)
    expect(error.textContent).toBe('Required.')
    expect(document.getElementById('f-name').getAttribute('aria-invalid')).toBe('true')
  })

  it('asks for the password on 423, confirms, then runs again', async () => {
    const calls = []
    const fetch = vi.fn(async (url) => {
      calls.push(path(url))
      if (url.endsWith('/confirm-password')) return response(200, { confirmed: true })
      return calls.filter((c) => c.endsWith('/run')).length === 1
        ? response(423, { requires: 'password' })
        : response(200, { success: true, status: 'succeeded', output: [] })
    })
    mount(fetch)
    const shown = vi.spyOn(document.querySelector('dialog'), 'showModal')

    await submit()
    expect(shown).toHaveBeenCalled()

    document.querySelector('[data-lau-password-form] input').value = 'secret'
    document.querySelector('[data-lau-password-form]').dispatchEvent(new Event('submit', { cancelable: true }))
    await new Promise((resolve) => setTimeout(resolve, 0))
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(calls).toEqual(['/artisan/commands/demo/run', '/artisan/confirm-password', '/artisan/commands/demo/run'])
    expect(document.querySelector('[data-lau-password-form] input').value).toBe('')
  })

  it('refreshes the token on 419 and does NOT replay the run', async () => {
    const calls = []
    const fetch = vi.fn(async (url) => {
      calls.push(path(url))
      return url.endsWith('/csrf-token') ? response(200, { token: 'fresh' }) : response(419, {})
    })
    mount(fetch)

    await submit()
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(calls).toEqual(['/artisan/commands/demo/run', '/artisan/csrf-token'])
    expect(document.querySelector('meta[name="csrf-token"]').getAttribute('content')).toBe('fresh')
    expect(document.querySelector('input[name="_token"]').value).toBe('fresh')
  })

  it('lets a hook veto the run before anything is sent', async () => {
    const fetch = vi.fn()
    const ui = mount(fetch)
    ui.hooks.add('beforeRun', () => false)

    await submit()

    expect(fetch).not.toHaveBeenCalled()
  })

  it('lets a page listener cancel the run through the DOM event', async () => {
    const fetch = vi.fn()
    mount(fetch)
    document.addEventListener('laranail-artisan-ui:run:before', (e) => e.preventDefault(), { once: true })

    await submit()

    expect(fetch).not.toHaveBeenCalled()
  })

  it('leaves nothing attached after destroy()', async () => {
    const fetch = vi.fn()
    const ui = mount(fetch)
    ui.destroy()

    await submit()

    expect(fetch).not.toHaveBeenCalled()
  })
})

describe('run form, errors in collapsed panels', () => {
  it('opens the collapsed panel holding the first invalid field', async () => {
    document.head.innerHTML = ''
    document.body.innerHTML = `
      <div data-laranail-artisan-ui data-csrf-url="/c">
        <form data-lau-run action="/run" data-command="demo">
          <details><summary>Options</summary>
            <input id="o-step" name="options[step]" aria-describedby="o-step-error">
            <p id="o-step-error" data-lau-error-for="options.step" hidden></p>
          </details>
          <button type="submit" data-lau-run-button></button><p data-lau-status></p>
        </form>
      </div>`
    createArtisanUi({
      document,
      window,
      storage: null,
      fetch: async () => ({ status: 422, json: async () => ({ errors: { 'options.step': ['Too long.'] } }) }),
    }).mount()

    document.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
    await new Promise((resolve) => setTimeout(resolve, 0))
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(document.querySelector('details').open).toBe(true)
    expect(document.activeElement.id).toBe('o-step')
  })
})
