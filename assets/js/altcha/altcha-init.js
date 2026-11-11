/**
 * ALTCHA glue for Mitbringliste (loaded after altcha.min.js and i18n-de.js).
 *
 * - registers the PBKDF2 worker (same-origin file; the app's CSP forbids blob: workers)
 * - shows an accessible hint (role="alert") if the browser cannot run the check
 *   (no Web Crypto, e.g. page not served via HTTPS) or if verification fails
 */
const workerUrl = new URL('pbkdf2.worker.js', import.meta.url).href;
globalThis.$altcha.algorithms.set('PBKDF2/SHA-256', () => new Worker(workerUrl));

function showHint(root, key) {
    const el = root.querySelector('[data-altcha-hint="' + key + '"]');
    if (el) el.hidden = false;
}

document.querySelectorAll('[data-altcha-field]').forEach((field) => {
    if (!(window.crypto && window.crypto.subtle)) {
        showHint(field, 'unsupported');
        return;
    }
    const widget = field.querySelector('altcha-widget');
    if (!widget) return;
    widget.addEventListener('statechange', (ev) => {
        const state = ev.detail && ev.detail.state;
        const err = field.querySelector('[data-altcha-hint="error"]');
        if (err) err.hidden = state !== 'error';
    });
});
