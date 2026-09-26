/**
 * Events observe; hooks mutate (engineering reference, Part IV pattern 4).
 *
 * Every event is a bubbling DOM CustomEvent named `laranail-artisan-ui:<name>`, so a host
 * page can listen with plain delegation and never needs the instance. The vendor prefix is
 * the same flat-registry rule the PHP side follows: an unprefixed `run:finished` is a
 * collision waiting for the next library on the page.
 */
export const EVENT_PREFIX = 'laranail-artisan-ui:'

/**
 * @param {EventTarget} target
 * @param {string} name
 * @param {object} detail
 * @param {{ cancelable?: boolean }} [options]
 * @returns {boolean} false when a listener called preventDefault() on a cancelable event
 */
export function emit(target, name, detail = {}, { cancelable = false } = {}) {
  const event = new CustomEvent(EVENT_PREFIX + name, { bubbles: true, cancelable, detail })

  return target.dispatchEvent(event)
}
