import { describe, expect, it, vi } from 'vitest'
import { EVENT_PREFIX, emit } from '../../resources/js/core/events.js'
import { groupFromHash, matches } from '../../resources/js/core/filter.js'
import { HookRegistry } from '../../resources/js/core/hooks.js'
import { RunClient } from '../../resources/js/core/run-client.js'
import { renderSegments } from '../../resources/js/core/segments.js'
import { serializeForm } from '../../resources/js/core/serialize.js'

describe('serializeForm', () => {
  it('maps the field naming convention to the endpoint body and drops the token', () => {
    document.body.innerHTML = `
      <form>
        <input name="_token" value="t">
        <input name="arguments[name]" value="users">
        <input name="arguments[extra][]" value="a">
        <input name="arguments[extra][]" value="b">
        <input type="checkbox" name="options[force]" value="1" checked>
        <input type="checkbox" name="options[seed]" value="1">
        <input name="options[tag][]" value="x">
        <input name="confirm" value="migrate">
      </form>`

    expect(serializeForm(document.querySelector('form'))).toEqual({
      arguments: { name: 'users', extra: ['a', 'b'] },
      options: { force: '1', tag: ['x'] },
      confirm: 'migrate',
    })
  })
})

describe('renderSegments', () => {
  it('renders text as text: markup in command output never becomes elements', () => {
    const pre = document.createElement('pre')

    renderSegments(pre, [
      { text: '<img src=x onerror=alert(1)>', classes: '' },
      { text: 'red', classes: 'lau-fg-red lau-bold' },
    ])

    expect(pre.querySelector('img')).toBeNull()
    expect(pre.textContent).toBe('<img src=x onerror=alert(1)>red')
    expect(pre.querySelector('span').className).toBe('lau-fg-red lau-bold')
  })

  it('refuses class lists outside the server vocabulary', () => {
    const pre = document.createElement('pre')

    renderSegments(pre, [
      { text: 'x', classes: 'lau-fg-red" onclick="x' },
      { text: 'y', classes: 'evil' },
    ])

    expect(pre.querySelectorAll('span')).toHaveLength(0)
    expect(pre.textContent).toBe('xy')
  })

  it('tolerates a malformed payload', () => {
    const pre = document.createElement('pre')

    renderSegments(pre, null)
    renderSegments(pre, [null, { text: 5 }])

    expect(pre.textContent).toBe('')
  })
})

describe('HookRegistry', () => {
  it('runs hooks in order, lets them rewrite, veto and be removed', async () => {
    const hooks = new HookRegistry()
    const remove = hooks.add('beforeRun', (p) => ({ ...p, a: 1 }))
    hooks.add('beforeRun', (p) => ({ ...p, b: p.a + 1 }))

    expect(await hooks.run('beforeRun', {})).toEqual({ vetoed: false, payload: { a: 1, b: 2 } })

    remove()
    hooks.add('beforeRun', () => false)

    expect((await hooks.run('beforeRun', {})).vetoed).toBe(true)
  })

  it('treats a throwing hook as a veto (fail closed)', async () => {
    const hooks = new HookRegistry()
    hooks.add('beforeRun', () => {
      throw new Error('guard broke')
    })

    const outcome = await hooks.run('beforeRun', { x: 1 })

    expect(outcome.vetoed).toBe(true)
    expect(outcome.error).toBeInstanceOf(Error)
  })

  it('rejects a non-function hook', () => {
    expect(() => new HookRegistry().add('x', 'nope')).toThrow(TypeError)
  })
})

describe('events', () => {
  it('dispatches vendor-prefixed, bubbling, optionally cancelable events', () => {
    const child = document.createElement('div')
    document.body.append(child)
    const seen = vi.fn((event) => event.preventDefault())
    document.addEventListener(`${EVENT_PREFIX}run:before`, seen)

    expect(emit(child, 'run:before', { a: 1 }, { cancelable: true })).toBe(false)
    expect(seen.mock.calls[0][0].detail).toEqual({ a: 1 })

    document.removeEventListener(`${EVENT_PREFIX}run:before`, seen)
  })
})

describe('filter', () => {
  it('matches every term against name and description', () => {
    expect(matches('make mod', 'make:model', 'Create a new Eloquent model')).toBe(true)
    expect(matches('make queue', 'make:model', 'Create a new Eloquent model')).toBe(false)
    expect(matches('   ', 'anything')).toBe(true)
  })

  it('reads the group from the hash (upstream issue #3)', () => {
    expect(groupFromHash('#group-make')).toBe('make')
    expect(groupFromHash('#group-lau-fixture')).toBe('lau-fixture')
    expect(groupFromHash('#group-<script>')).toBeNull()
    expect(groupFromHash('')).toBeNull()
  })
})

describe('RunClient', () => {
  it('posts JSON with the CSRF token and never rejects', async () => {
    const fetch = vi.fn(async () => ({ status: 200, json: async () => ({ ok: true }) }))
    const client = new RunClient({ fetch, token: () => 'tok', setToken: () => {} })

    expect(await client.run('/run', { arguments: {} })).toEqual({ status: 200, body: { ok: true } })

    const [, init] = fetch.mock.calls[0]
    expect(init.method).toBe('POST')
    expect(init.headers['X-CSRF-TOKEN']).toBe('tok')
    expect(init.headers.Accept).toBe('application/json')
  })

  it('reports a network failure as status 0 instead of throwing', async () => {
    const client = new RunClient({
      fetch: async () => {
        throw new TypeError('offline')
      },
      token: () => '',
      setToken: () => {},
    })

    expect(await client.run('/run', {})).toEqual({ status: 0, body: null })
  })

  it('applies a refreshed token', async () => {
    const setToken = vi.fn()
    const client = new RunClient({
      fetch: async () => ({ status: 200, json: async () => ({ token: 'new' }) }),
      token: () => 'old',
      setToken,
    })

    expect(await client.refreshToken('/csrf')).toBe(true)
    expect(setToken).toHaveBeenCalledWith('new')
  })
})

describe('serializeForm, valueless options', () => {
  it('sends true for a value-optional option ticked with no value, and the value when one is typed', () => {
    document.body.innerHTML = `
      <form>
        <input name="options[step]" value="">
        <input type="checkbox" name="options-present[step]" value="1" checked>
        <input name="options[seed]" value="Users">
        <input type="checkbox" name="options-present[seed]" value="1" checked>
        <select name="options[ansi]"><option value="no" selected>--no-ansi</option></select>
      </form>`

    expect(serializeForm(document.querySelector('form'))).toEqual({
      arguments: {},
      options: { step: true, seed: 'Users', ansi: 'no' },
    })
  })
})
