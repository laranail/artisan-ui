/**
 * addEventListener that returns its own teardown, so every controller can collect them and
 * `destroy()` leaves nothing attached.
 */
export function listen(target, type, handler, options) {
  target.addEventListener(type, handler, options)

  return () => target.removeEventListener(type, handler, options)
}
