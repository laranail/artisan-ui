/*
 * laranail/artisan-ui browser entry.
 *
 * Mounts on DOMContentLoaded and exposes the instance as `window.laranailArtisanUi` (vendor-
 * scoped, like every other name the package registers). Hosts that want the instance before
 * mount can listen for `laranail-artisan-ui:ready` on the document.
 */
import { createArtisanUi } from './create.js'

const boot = () => {
  window.laranailArtisanUi = createArtisanUi().mount()
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true })
} else {
  boot()
}

export { createArtisanUi }
