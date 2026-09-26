import { emit } from '../core/events.js'
import { renderSegments } from '../core/segments.js'
import { serializeForm } from '../core/serialize.js'
import { listen } from './listen.js'

/**
 * The command form: run, show output, handle every refusal the endpoint can give.
 *
 *   200  finished (succeeded, failed or errored): render output segments and status
 *   422  field errors, or the typed confirmation did not match
 *   423  a destructive command needs a password confirmation: ask, confirm, run again
 *   419  the session expired: refresh the token and ask the operator to run again;
 *        never replayed automatically, because a run is not idempotent
 *   409  the command is already running
 *   403 / 404 / other: a generic message, since the server says no more
 *
 * Lifecycle events (bubbling, on the form): run:before (cancelable), run:finished,
 * run:failed, run:refused. Hooks: beforeRun may rewrite the payload or veto; afterRun sees
 * the response.
 */
export function mountRunForm(form, { document, client, hooks, notifier, csrfUrl }) {
  const panel = document.querySelector('[data-lau-output-panel]')
  const output = document.querySelector('[data-lau-output]')
  const truncated = document.querySelector('[data-lau-truncated]')
  const status = form.querySelector('[data-lau-status]')
  const button = form.querySelector('[data-lau-run-button]')
  const runLabel = form.querySelector('[data-lau-run-label]')
  const runningLabel = form.querySelector('[data-lau-running-label]')
  const dialog = document.querySelector('[data-lau-password]')
  const passwordForm = document.querySelector('[data-lau-password-form]')
  const passwordError = document.querySelector('[data-lau-password-error]')
  const messages = readMessages(document)
  const teardown = []

  let running = false
  let controller = null

  const setRunning = (value) => {
    running = value
    button?.toggleAttribute('disabled', value)
    form.setAttribute('aria-busy', value ? 'true' : 'false')

    if (runLabel && runningLabel) {
      runLabel.hidden = value
      runningLabel.hidden = !value
    }
  }

  const say = (text, tone = 'info') => {
    if (!status) return
    status.textContent = text
    status.dataset.tone = tone
  }

  const clearErrors = () => {
    for (const el of form.querySelectorAll('[data-lau-error-for]')) {
      el.textContent = ''
      el.hidden = true
    }

    for (const el of form.querySelectorAll('[aria-invalid]')) {
      el.removeAttribute('aria-invalid')
    }
  }

  const showErrors = (errors) => {
    let first = null

    for (const [key, list] of Object.entries(errors ?? {})) {
      const target = form.querySelector(`[data-lau-error-for="${CSS.escape(key)}"]`)

      if (target) {
        target.textContent = Array.isArray(list) ? list.join(' ') : String(list)
        target.hidden = false

        const input = target.id ? form.querySelector(`[aria-describedby~="${CSS.escape(target.id)}"]`) : null
        input?.setAttribute('aria-invalid', 'true')
        first ??= input
      }
    }

    // A field inside a collapsed panel cannot take focus or be seen: open its panel first.
    first?.closest('details')?.setAttribute('open', '')
    first?.focus()
  }

  const showResult = (body) => {
    if (panel) panel.hidden = false
    if (output) renderSegments(output, body.output, document)
    if (truncated) truncated.hidden = !body.truncated

    const parts = [body.status_label ?? body.status]

    if (Number.isInteger(body.exit_code)) parts.push(messages.exit_code.replace(':code', String(body.exit_code)))
    if (Number.isInteger(body.duration_ms)) parts.push(messages.duration.replace(':ms', String(body.duration_ms)))
    if (body.error) parts.push(body.error)

    say(parts.join(' · '), body.success ? 'success' : 'error')
  }

  const execute = async () => {
    // Claimed synchronously, before the first await: two submits in the same tick must not both
    // get through, because a run is not idempotent.
    if (running) return
    setRunning(true)

    let response

    try {
      clearErrors()

      const outcome = await hooks.run('beforeRun', { command: form.dataset.command, body: serializeForm(form) })
      const payload = outcome.payload

      if (outcome.vetoed || !emit(form, 'run:before', payload, { cancelable: true })) {
        return
      }

      // A hook that returned something other than a payload: refuse rather than guess.
      if (!payload || typeof payload.body !== 'object' || payload.body === null) {
        say(messages.unexpected, 'error')
        return
      }

      say(messages.running)
      controller = new AbortController()

      response = await client.run(form.action, payload.body, { signal: controller.signal })
    } finally {
      // Every exit path, including a hook or listener that throws, frees the button.
      setRunning(false)
    }

    // An aborted request (destroy()) is not a response: no hook, no event.
    if (!response || response.status === -1) {
      return
    }

    await hooks.run('afterRun', { command: form.dataset.command, response })

    handle(response)
  }

  const handle = ({ status: code, body }) => {
    switch (code) {
      case 200:
        // A 200 that is not the run payload (a proxy page, an HTML error) is not a success.
        if (!body || !Array.isArray(body.output)) {
          say(messages.unexpected, 'error')
          break
        }

        showResult(body)
        emit(form, body.success ? 'run:finished' : 'run:failed', body)
        notifier.notify(body.success ? 'success' : 'error', 'run', body)
        return
      case 422:
        showErrors(body?.errors)
        say(body?.message ?? messages.invalid_input, 'error')
        break
      case 423:
        say(messages.password_required, 'warning')
        askForPassword()
        break
      case 419:
        say(messages.session_expired_pending, 'warning')
        client
          .refreshToken(csrfUrl)
          .then((refreshed) =>
            say(refreshed ? messages.session_expired : messages.session_lost, refreshed ? 'warning' : 'error'),
          )
        break
      case 409:
        say(body?.message ?? messages.unexpected, 'warning')
        break
      case 403:
        say(messages.forbidden, 'error')
        break
      case 404:
        say(messages.not_found, 'error')
        break
      default:
        say(messages.unexpected, 'error')
    }

    emit(form, 'run:refused', { status: code, body })
    notifier.notify('warning', 'run:refused', { status: code })
  }

  const askForPassword = () => {
    if (!dialog || !passwordForm) {
      say(messages.unexpected, 'error')
      return
    }

    passwordForm.reset()
    if (passwordError) passwordError.hidden = true

    // Older browsers lack showModal(); a non-modal open dialog still works.
    if (typeof dialog.showModal === 'function') {
      dialog.showModal()
    } else {
      dialog.setAttribute('open', '')
    }

    passwordForm.querySelector('input[type="password"]')?.focus()
  }

  const closeDialog = () => {
    if (typeof dialog?.close === 'function') {
      dialog.close()
    } else {
      dialog?.removeAttribute('open')
    }
  }

  let confirming = false
  // The operator closed the dialog (Esc, Cancel) while the password was being checked.
  let cancelledWhileConfirming = false
  // Our own close after a successful confirmation, which is not a cancel.
  let closingAfterConfirm = false

  const confirmPassword = async (event) => {
    event.preventDefault()

    // One confirmation at a time: a second submit would chain a second run.
    if (confirming) return
    confirming = true
    cancelledWhileConfirming = false

    const submit = passwordForm.querySelector('button[type="submit"]')
    submit?.setAttribute('disabled', '')

    try {
      const input = passwordForm.querySelector('input[type="password"]')
      const response = await client.confirmPassword(form.dataset.confirmUrl, input?.value ?? '')

      if (input) input.value = ''

      if (response.status === 200) {
        if (cancelledWhileConfirming || (dialog && !dialog.hasAttribute('open'))) {
          say(messages.cancelled, 'info')
          return
        }

        closingAfterConfirm = true
        closeDialog()
        // The run was refused before anything executed, so submitting again is safe.
        await execute()
        return
      }

      if (response.status === 419) {
        await client.refreshToken(csrfUrl)
      }

      if (passwordError) {
        passwordError.textContent =
          response.body?.errors?.password?.[0] ?? response.body?.message ?? messages.unexpected
        passwordError.hidden = false
      }
    } finally {
      confirming = false
      submit?.removeAttribute('disabled')
    }
  }

  teardown.push(
    listen(form, 'submit', (event) => {
      event.preventDefault()
      execute()
    }),
  )

  const clear = document.querySelector('[data-lau-clear]')

  if (clear) {
    teardown.push(
      listen(clear, 'click', () => {
        if (output) output.replaceChildren()
        if (panel) panel.hidden = true
        say('')
      }),
    )
  }

  if (passwordForm) {
    teardown.push(listen(passwordForm, 'submit', confirmPassword))
  }

  const cancel = document.querySelector('[data-lau-password-cancel]')

  if (cancel && dialog) {
    teardown.push(listen(cancel, 'click', closeDialog))
  }

  if (dialog) {
    // Esc or Cancel: nothing ran, so say so rather than leaving the password prompt message.
    teardown.push(
      listen(dialog, 'close', () => {
        // Closed by a successful confirmation, the run is under way: only a real cancel says so.
        if (closingAfterConfirm) {
          closingAfterConfirm = false
          return
        }

        if (confirming) {
          cancelledWhileConfirming = true
          return
        }

        if (!running) say(messages.cancelled, 'info')
      }),
    )
  }

  return {
    execute,
    destroy() {
      controller?.abort()
      for (const off of teardown.splice(0)) off()
    },
  }
}

const DEFAULT_MESSAGES = {
  running: 'Running…',
  exit_code: 'Exit code :code',
  duration: ':ms ms',
  invalid_input: 'Check the highlighted fields.',
  session_expired: 'Your session was refreshed. Run the command again.',
  session_expired_pending: 'Your session expired. Refreshing it…',
  session_lost: 'Your session expired and could not be refreshed. Reload the page.',
  password_required: 'Confirm your password to run this command.',
  cancelled: 'Cancelled. Nothing was run.',
  forbidden: 'You are not allowed to run this command.',
  not_found: 'That command is not available here.',
  unexpected: 'Something went wrong. Try again.',
}

function readMessages(document) {
  const el = document.querySelector('script[type="application/json"][data-lau-messages]')

  if (!el) return DEFAULT_MESSAGES

  try {
    return { ...DEFAULT_MESSAGES, ...JSON.parse(el.textContent ?? '{}') }
  } catch {
    return DEFAULT_MESSAGES
  }
}
