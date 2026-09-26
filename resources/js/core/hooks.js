/**
 * Ordered, removable hooks that may rewrite a payload or veto the action.
 *
 * A hook receives the payload and returns: `undefined` (or `null`) to leave it as is, a replacement
 * payload, or `false` to veto. Hooks run in registration order and may be async. A hook
 * that throws vetoes the action rather than being skipped: a hook is often a guard, and a
 * broken guard must not wave the action through (fail closed).
 */
export class HookRegistry {
  #hooks = new Map()

  /**
   * @param {string} name
   * @param {(payload: any) => any} fn
   * @returns {() => void} removes the hook
   */
  add(name, fn) {
    if (typeof fn !== 'function') {
      throw new TypeError(`laranail-artisan-ui: hook "${name}" must be a function`)
    }

    const list = this.#hooks.get(name) ?? []
    list.push(fn)
    this.#hooks.set(name, list)

    return () => {
      const current = this.#hooks.get(name) ?? []
      this.#hooks.set(
        name,
        current.filter((hook) => hook !== fn),
      )
    }
  }

  /**
   * @returns {Promise<{ vetoed: boolean, payload: any, error?: unknown }>}
   */
  async run(name, payload) {
    let current = payload

    for (const hook of this.#hooks.get(name) ?? []) {
      let result

      try {
        result = await hook(current)
      } catch (error) {
        return { vetoed: true, payload: current, error }
      }

      if (result === false) {
        return { vetoed: true, payload: current }
      }

      // null, like undefined, means "no change": a hook that returns nothing by accident must
      // not replace the payload with nothing.
      if (result !== undefined && result !== null) {
        current = result
      }
    }

    return { vetoed: false, payload: current }
  }
}
