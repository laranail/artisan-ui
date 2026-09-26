/**
 * The seam for routing panel messages to a toast library. The default does nothing; the
 * panel already shows every message inline.
 *
 * @typedef {{ notify(level: 'info'|'success'|'warning'|'error', event: string, detail?: object): void }} Notifier
 */
export const nullNotifier = Object.freeze({
  notify() {},
})
