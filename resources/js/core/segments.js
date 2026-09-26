/**
 * Renders the server's ANSI segments into an element.
 *
 * `textContent` only, never innerHTML: the text is command output and may contain anything.
 * Class names are accepted only in the shape the server produces (`lau-…`), so even a
 * tampered response cannot inject an arbitrary class list.
 */
const SAFE_CLASSES = /^(lau-[a-z-]+)( lau-[a-z-]+)*$/

/**
 * @param {Element} container
 * @param {Array<{ text: string, classes: string }>} segments
 * @param {Document} [doc]
 */
export function renderSegments(container, segments, doc = container.ownerDocument) {
  container.replaceChildren()

  for (const segment of Array.isArray(segments) ? segments : []) {
    if (!segment || typeof segment.text !== 'string') {
      continue
    }

    if (typeof segment.classes === 'string' && SAFE_CLASSES.test(segment.classes)) {
      const span = doc.createElement('span')
      span.className = segment.classes
      span.textContent = segment.text
      container.append(span)
    } else {
      container.append(doc.createTextNode(segment.text))
    }
  }
}
