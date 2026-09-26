/**
 * Search matching for the command list (dev-arindam-roy's filter, over name and description).
 * Every whitespace-separated term must appear somewhere, case-insensitively.
 */
export function matches(query, name, description = '') {
  const terms = String(query).toLowerCase().trim().split(/\s+/).filter(Boolean)

  if (terms.length === 0) {
    return true
  }

  const haystack = `${name} ${description}`.toLowerCase()

  return terms.every((term) => haystack.includes(term))
}

/**
 * The group a location hash points at (upstream issue #3): `#group-make` → `make`.
 */
export function groupFromHash(hash) {
  const match = /^#group-([A-Za-z0-9:_.-]+)$/.exec(String(hash))

  return match ? match[1] : null
}
