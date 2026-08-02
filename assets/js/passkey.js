/**
 * Passkey (WebAuthn) support – registration + authentication.
 * No inline scripts: PHP values are passed via data-* attributes on #passkey-config
 * and #passkey-auth-config to comply with the site's script-src 'self' CSP.
 */

'use strict';

/* ------------------------------------------------------------------ */
/* Shared helpers                                                       */
/* ------------------------------------------------------------------ */

function _pkB64d(str) {
    var s = str.replace(/-/g, '+').replace(/_/g, '/');
    var p = (4 - s.length % 4) % 4;
    for (var i = 0; i < p; i++) s += '=';
    var bin = atob(s), buf = new Uint8Array(bin.length);
    for (var j = 0; j < bin.length; j++) buf[j] = bin.charCodeAt(j);
    return buf.buffer;
}

function _pkB64e(buf) {
    var bytes = new Uint8Array(buf), str = '';
    for (var i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

function _pkApiPost(url, action, csrf, extra) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action: action, _csrf: csrf }, extra || {}))
    }).then(function (r) { return r.json(); });
}

/* ------------------------------------------------------------------ */
/* Registration page (admin/settings/security)                         */
/* Requires: <div id="passkey-config" data-csrf="..." data-api-url="..."
/*             data-copy-success="...">                                */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    var cfg = document.getElementById('passkey-config');
    if (!cfg) return;

    var CSRF       = cfg.dataset.csrf;
    var API_URL    = cfg.dataset.apiUrl;
    var cpySuccess = cfg.dataset.copySuccess || 'Kopiert!';
    var notSupported = cfg.dataset.notSupported || 'Passkeys werden von deinem Browser nicht unterstützt.';

    /* ---- Recovery codes copy / download ---- */
    var codesGrid = document.getElementById('new-codes-grid');
    if (codesGrid && codesGrid.dataset.codes) {
        try {
            var codes = JSON.parse(codesGrid.dataset.codes);

            var btnCopyNew = document.getElementById('btn-copy-new-codes');
            if (btnCopyNew) {
                var origHtml = btnCopyNew.innerHTML;
                btnCopyNew.addEventListener('click', function () {
                    navigator.clipboard.writeText(codes.join('\n')).then(function () {
                        btnCopyNew.textContent = cpySuccess;
                        setTimeout(function () { btnCopyNew.innerHTML = origHtml; }, 1800);
                    }).catch(function () {});
                });
            }

            var btnDlNew = document.getElementById('btn-dl-new-codes');
            if (btnDlNew) {
                btnDlNew.addEventListener('click', function () {
                    var blob = new Blob([codes.join('\n')], { type: 'text/plain' });
                    var url  = URL.createObjectURL(blob);
                    var a    = document.createElement('a');
                    a.href = url; a.download = 'werbringtwas-recovery-codes.txt'; a.click();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
                });
            }
        } catch (e) {}
    }

    /* ---- Passkey registration ---- */
    var btnAdd  = document.getElementById('btn-add-passkey');
    var errEl   = document.getElementById('passkey-error');
    var unsupEl = document.getElementById('passkey-unsupported');
    var modal   = document.getElementById('passkey-name-modal');
    var nameIn  = document.getElementById('pk-name-input');
    var btnOk   = document.getElementById('pk-name-ok');
    var btnCncl = document.getElementById('pk-name-cancel');

    if (!window.PublicKeyCredential) {
        if (btnAdd)  btnAdd.disabled = true;
        if (unsupEl) unsupEl.style.display = '';
        return;
    }

    function openModal() {
        if (!modal) return;
        modal.style.display = 'flex';
        if (nameIn) { nameIn.value = ''; setTimeout(function () { nameIn.focus(); }, 0); }
    }

    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        if (btnAdd) btnAdd.focus();
    }

    if (btnAdd)  btnAdd.addEventListener('click', openModal);
    if (btnCncl) btnCncl.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        modal.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    }
    if (nameIn) {
        nameIn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); if (btnOk) btnOk.click(); }
        });
    }
    if (btnOk) {
        btnOk.addEventListener('click', function () {
            var name = nameIn ? nameIn.value.trim() : '';
            if (!name) name = 'Passkey';
            closeModal();
            doRegister(name);
        });
    }

    function showError(msg) {
        if (!errEl) return;
        errEl.textContent = msg;
        errEl.style.display = '';
    }

    function doRegister(deviceName) {
        if (errEl) errEl.style.display = 'none';
        if (btnAdd) btnAdd.disabled = true;

        _pkApiPost(API_URL, 'register_challenge', CSRF).then(function (opts) {
            if (opts.error) { if (btnAdd) btnAdd.disabled = false; showError(opts.error); return Promise.reject(null); }
            opts.publicKey.challenge = _pkB64d(opts.publicKey.challenge);
            opts.publicKey.user.id   = _pkB64d(opts.publicKey.user.id);
            if (opts.publicKey.excludeCredentials) {
                opts.publicKey.excludeCredentials = opts.publicKey.excludeCredentials.map(function (c) {
                    return Object.assign({}, c, { id: _pkB64d(c.id) });
                });
            }
            return navigator.credentials.create(opts);
        }).then(function (cred) {
            if (!cred) { if (btnAdd) btnAdd.disabled = false; return Promise.reject(null); }
            return _pkApiPost(API_URL, 'register_complete', CSRF, {
                deviceName:        deviceName,
                id:                cred.id,
                clientDataJSON:    _pkB64e(cred.response.clientDataJSON),
                attestationObject: _pkB64e(cred.response.attestationObject)
            });
        }).then(function (res) {
            if (!res) return;
            if (res.ok && res.redirect) {
                window.location.href = res.redirect;
            } else {
                if (btnAdd) btnAdd.disabled = false;
                showError(res.error || 'Registrierung fehlgeschlagen.');
            }
        }).catch(function (err) {
            if (btnAdd) btnAdd.disabled = false;
            if (err && err.name !== 'NotAllowedError' && err !== null) {
                showError(err.message || String(err));
            }
        });
    }
});

/* ------------------------------------------------------------------ */
/* Authentication page (admin/totp – the 2FA login step)              */
/* Requires: <div id="passkey-auth-config" data-csrf="..."
/*             data-auth-url="..." data-not-supported="...">           */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    var cfg = document.getElementById('passkey-auth-config');
    if (!cfg) return;

    var CSRF     = cfg.dataset.csrf;
    var AUTH_URL = cfg.dataset.authUrl;
    var notSupported = cfg.dataset.notSupported || 'Passkeys werden von deinem Browser nicht unterstützt.';

    var btn   = document.getElementById('btn-passkey-auth');
    var errEl = document.getElementById('passkey-auth-error');

    if (!window.PublicKeyCredential) {
        if (btn) btn.disabled = true;
        if (errEl) { errEl.textContent = notSupported; errEl.style.display = ''; }
        return;
    }

    if (!btn) return;

    btn.addEventListener('click', function () {
        if (errEl) errEl.style.display = 'none';
        btn.disabled = true;

        _pkApiPost(AUTH_URL, 'auth_challenge', CSRF).then(function (opts) {
            if (opts.error) { btn.disabled = false; throw new Error(opts.error); }
            opts.publicKey.challenge = _pkB64d(opts.publicKey.challenge);
            if (opts.publicKey.allowCredentials && opts.publicKey.allowCredentials.length) {
                opts.publicKey.allowCredentials = opts.publicKey.allowCredentials.map(function (c) {
                    return Object.assign({}, c, { id: _pkB64d(c.id) });
                });
            }
            return navigator.credentials.get(opts);
        }).then(function (assertion) {
            if (!assertion) throw new Error('no assertion');
            return _pkApiPost(AUTH_URL, 'auth_complete', CSRF, {
                id:                assertion.id,
                clientDataJSON:    _pkB64e(assertion.response.clientDataJSON),
                authenticatorData: _pkB64e(assertion.response.authenticatorData),
                signature:         _pkB64e(assertion.response.signature),
                userHandle:        assertion.response.userHandle ? _pkB64e(assertion.response.userHandle) : null
            });
        }).then(function (res) {
            if (res && res.ok && res.redirect) {
                window.location.href = res.redirect;
            } else {
                btn.disabled = false;
                throw new Error((res && res.error) || 'Authentifizierung fehlgeschlagen.');
            }
        }).catch(function (err) {
            btn.disabled = false;
            if (err && err.name !== 'NotAllowedError' && err !== null) {
                if (errEl) { errEl.textContent = err.message || String(err); errEl.style.display = ''; }
            }
        });
    });
});
