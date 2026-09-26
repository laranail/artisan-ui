import { emit } from '../core/events.js'
import { groupFromHash, matches } from '../core/filter.js'
import { listen } from './listen.js'

/**
 * The home screen: search over commands, and the open group mirrored into the URL hash so a
 * link or a reload returns to the same place (upstream issue #3). Groups are native
 * `<details>`, which are keyboard- and screen-reader-accessible without any help.
 */
export function mountCommandList(root, { window }) {
  const search = root.querySelector('[data-lau-search]')
  const empty = root.querySelector('[data-lau-no-matches]')
  const groups = [...root.querySelectorAll('[data-lau-group]')]
  const teardown = []

  const openFromHash = () => {
    const wanted = groupFromHash(window.location.hash)

    if (wanted === null) {
      return
    }

    for (const group of groups) {
      if (group.dataset.lauGroup === wanted) {
        group.open = true
      }
    }
  }

  // The open state before searching began, restored when the search is cleared, so a search
  // does not leave every group it touched expanded.
  let before = null

  const filter = () => {
    const query = search ? search.value : ''
    const searching = query.trim() !== ''
    let visible = 0

    if (searching && before === null) {
      before = new Map(groups.map((group) => [group, group.open]))
    }

    for (const group of groups) {
      let groupVisible = 0

      for (const item of group.querySelectorAll('[data-lau-command]')) {
        const hit = matches(query, item.dataset.name ?? '', item.dataset.description ?? '')
        item.hidden = !hit
        groupVisible += hit ? 1 : 0
      }

      group.hidden = groupVisible === 0

      if (query.trim() !== '' && groupVisible > 0) {
        group.open = true
      }

      visible += groupVisible
    }

    if (!searching && before !== null) {
      for (const [group, wasOpen] of before) {
        group.open = wasOpen
      }

      before = null
    }

    if (empty) {
      empty.hidden = visible !== 0
    }

    emit(root, 'search', { query, visible })
  }

  for (const group of groups) {
    teardown.push(
      listen(group, 'toggle', () => {
        if (group.open && search?.value.trim() === '') {
          window.history.replaceState(null, '', `#group-${group.dataset.lauGroup}`)
        }
      }),
    )
  }

  if (search) {
    teardown.push(listen(search, 'input', filter))
  }

  teardown.push(listen(window, 'hashchange', openFromHash))
  openFromHash()

  return {
    filter,
    destroy() {
      for (const off of teardown.splice(0)) off()
    },
  }
}
