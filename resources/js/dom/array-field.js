import { listen } from './listen.js'

/**
 * A list-valued argument or option: add a row, remove a row. One empty row always remains,
 * so there is somewhere to type.
 */
export function mountArrayField(root) {
  const rows = root.querySelector('[data-lau-array-rows]')
  const teardown = []

  const template = () => rows.querySelector('[data-lau-array-row]')

  const add = () => {
    const source = template()

    if (!source) {
      return
    }

    const row = source.cloneNode(true)
    const input = row.querySelector('input')

    if (input) {
      input.value = ''
      input.removeAttribute('id')
    }

    rows.append(row)
    input?.focus()
  }

  const remove = (event) => {
    const button = event.target.closest('[data-lau-array-remove]')

    if (!button || !root.contains(button)) {
      return
    }

    const row = button.closest('[data-lau-array-row]')
    const all = [...rows.querySelectorAll('[data-lau-array-row]')]

    if (all.length === 1) {
      const input = row.querySelector('input')

      if (input) {
        input.value = ''
        input.focus()
      }

      return
    }

    const index = all.indexOf(row)
    const removed = row.querySelector('input')
    const id = removed?.id

    row.remove()

    const remaining = [...rows.querySelectorAll('[data-lau-array-row] input')]

    // The first input carries the id the <label for> points at; hand it on so the label keeps
    // a target.
    if (id && remaining[0] && !remaining[0].id) {
      remaining[0].id = id
    }

    // Keep focus in the list instead of dropping it on <body>.
    remaining[Math.min(index, remaining.length - 1)]?.focus()
  }

  const addButton = root.querySelector('[data-lau-array-add]')

  if (addButton) {
    teardown.push(listen(addButton, 'click', add))
  }

  teardown.push(listen(root, 'click', remove))

  return {
    add,
    destroy() {
      for (const off of teardown.splice(0)) off()
    },
  }
}
