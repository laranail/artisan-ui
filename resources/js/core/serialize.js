/**
 * Turns the command form into the JSON body the execute endpoint expects:
 * `{ arguments: {name: value|list}, options: {name: value|list|true}, confirm? }`.
 *
 * Field names follow the server's convention: `arguments[name]`, `arguments[name][]`,
 * `options[name]`, `options[name][]`, `confirm`, and `options-present[name]` for a
 * value-optional option sent with no value (it becomes `true`). Everything else (the CSRF
 * token) is left out.
 * Empty values are kept as empty strings; the server decides what empty means.
 *
 * @param {HTMLFormElement} form
 * @param {typeof FormData} [FormDataImpl]
 */
export function serializeForm(form, FormDataImpl = FormData) {
  const body = { arguments: {}, options: {} }
  const data = new FormDataImpl(form)
  const present = []

  for (const [key, raw] of data.entries()) {
    if (typeof raw !== 'string') {
      continue
    }

    const presence = /^options-present\[([^\]]+)\]$/.exec(key)

    if (presence) {
      present.push(presence[1])
      continue
    }

    if (key === 'confirm') {
      body.confirm = raw
      continue
    }

    const match = /^(arguments|options)\[([^\]]+)\](\[\])?$/.exec(key)

    if (!match) {
      continue
    }

    const [, section, name, isList] = match

    if (isList) {
      body[section][name] = [...(body[section][name] ?? []), raw]
    } else {
      body[section][name] = raw
    }
  }

  // "Send --name without a value": only when no value was typed.
  for (const name of present) {
    if (body.options[name] === undefined || body.options[name] === '') {
      body.options[name] = true
    }
  }

  return body
}
