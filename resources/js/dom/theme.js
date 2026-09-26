import { listen } from './listen.js'

export const THEME_KEY = 'laranail-artisan-ui:theme'

/**
 * Dark mode: the operator's saved choice, else the system preference. Storage is optional;
 * a browser that refuses it just forgets the choice.
 */
export function mountTheme(button, { document, window, storage }) {
  const root = document.documentElement

  const saved = () => {
    try {
      return storage?.getItem(THEME_KEY) ?? null
    } catch {
      return null
    }
  }

  const systemDark = () => Boolean(window.matchMedia?.('(prefers-color-scheme: dark)').matches)

  const isDark = () => root.classList.contains('dark') || (!root.classList.contains('light') && systemDark())

  // With no saved choice neither class is set, and the CSS follows the system on its own.
  const apply = (choice) => {
    root.classList.toggle('dark', choice === 'dark')
    root.classList.toggle('light', choice === 'light')
    button?.setAttribute('aria-pressed', isDark() ? 'true' : 'false')
  }

  apply(saved())

  const teardown = []

  if (button) {
    teardown.push(
      listen(button, 'click', () => {
        const choice = isDark() ? 'light' : 'dark'
        apply(choice)

        try {
          storage?.setItem(THEME_KEY, choice)
        } catch {
          // Storage refused; the choice lasts for this page only.
        }
      }),
    )
  }

  return {
    destroy() {
      for (const off of teardown.splice(0)) off()
    },
  }
}
