/**
 * The headless transport: talks to the panel's endpoints and returns plain results. No DOM.
 *
 * Every method resolves (never rejects) to `{ status, body }`, with status 0 for a network
 * failure, so a caller cannot forget to handle a failure path. POSTs are never retried here:
 * a run is not idempotent (engineering reference, Part I §1).
 */
export class RunClient {
  #fetch
  #token

  /**
   * @param {{ fetch: typeof fetch, token: () => string, setToken: (token: string) => void }} deps
   */
  constructor({ fetch, token, setToken }) {
    this.#fetch = fetch
    this.#token = { get: token, set: setToken }
  }

  run(url, body, { signal } = {}) {
    return this.#post(url, body, signal)
  }

  confirmPassword(url, password, { signal } = {}) {
    return this.#post(url, { password }, signal)
  }

  /** Fetches a fresh CSRF token and applies it. Resolves true when it worked. */
  async refreshToken(url) {
    const { status, body } = await this.#request(url, { method: 'GET', headers: this.#headers() })

    if (status === 200 && body && typeof body.token === 'string') {
      this.#token.set(body.token)

      return true
    }

    return false
  }

  #post(url, body, signal) {
    return this.#request(url, {
      method: 'POST',
      headers: { ...this.#headers(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.#token.get() },
      body: JSON.stringify(body),
      credentials: 'same-origin',
      signal,
    })
  }

  #headers() {
    return { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  }

  async #request(url, init) {
    try {
      const response = await this.#fetch(url, { credentials: 'same-origin', ...init })
      let body = null

      try {
        body = await response.json()
      } catch {
        body = null
      }

      return { status: response.status, body }
    } catch (error) {
      if (error && error.name === 'AbortError') {
        return { status: -1, body: null }
      }

      return { status: 0, body: null }
    }
  }
}
