/**
 * Mitbringliste — app.js (Phase 1 base)
 * Vanilla JS, no frameworks, no jQuery.
 */

'use strict';

/* ------------------------------------------------------------------ */
/* Copy to clipboard                                                    */
/* ------------------------------------------------------------------ */
function copyToClipboard(text, button) {
    if (!navigator.clipboard) {
        // Fallback for older browsers
        const el = document.createElement('textarea');
        el.value = text;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        try {
            document.execCommand('copy');
            _copySuccess(button);
        } catch (e) {
            _copyError(button);
        }
        document.body.removeChild(el);
        return;
    }

    navigator.clipboard.writeText(text).then(
        function () { _copySuccess(button); },
        function () { _copyError(button); }
    );
}

function _copySuccess(button) {
    if (!button) return;
    const original = button.textContent;
    const successMsg = button.dataset.successMsg || 'Kopiert!';
    button.textContent = successMsg;
    button.disabled = true;
    setTimeout(function () {
        button.textContent = original;
        button.disabled = false;
    }, 2000);
}

function _copyError(button) {
    if (!button) return;
    const original = button.textContent;
    button.textContent = '✕ Fehler';
    setTimeout(function () { button.textContent = original; }, 2000);
}

/* ------------------------------------------------------------------ */
/* Shared modal focus-trap helper                                      */
/* Keeps Tab/Shift+Tab cycling inside an open modal instead of leaking  */
/* focus into the (visually blocked) page behind it.                   */
/* ------------------------------------------------------------------ */
function getFocusable(container) {
    return Array.from(container.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(function (el) { return el.offsetParent !== null; });
}

function trapTabKey(e, container) {
    if (e.key !== 'Tab') return;
    const focusable = getFocusable(container);
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last  = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

/* ------------------------------------------------------------------ */
/* Confirm dialogs for destructive actions (custom modal, replaces     */
/* the native confirm() popup). Put [data-confirm="message"] on a      */
/* <form> or on its submit button.                                     */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-confirm-modal]');
    if (!modal) return;

    const textEl     = modal.querySelector('[data-confirm-modal-text]');
    const cancelBtn  = modal.querySelector('[data-confirm-modal-cancel]');
    const proceedBtn = modal.querySelector('[data-confirm-modal-proceed]');

    let pendingForm      = null;
    let pendingSubmitter = null;
    let triggerEl         = null;

    function closeConfirmModal() {
        modal.hidden      = true;
        pendingForm      = null;
        pendingSubmitter = null;
        if (triggerEl) {
            triggerEl.focus();
            triggerEl = null;
        }
    }

    // Delegated so it also covers forms rendered after this listener runs.
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // Already confirmed via the modal — let it through once.
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        const submitter = e.submitter;
        const message = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
        if (!message) return;

        e.preventDefault();
        if (textEl) textEl.textContent = message;
        pendingForm      = form;
        pendingSubmitter = submitter || null;
        triggerEl         = submitter || document.activeElement;
        modal.hidden     = false;
        // Focus the least destructive action first (Cancel), not Proceed.
        (cancelBtn || proceedBtn)?.focus();
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeConfirmModal);
    }

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeConfirmModal();
    });

    modal.addEventListener('keydown', function (e) {
        trapTabKey(e, modal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeConfirmModal();
    });

    if (proceedBtn) {
        proceedBtn.addEventListener('click', function () {
            if (!pendingForm) return;
            const form      = pendingForm;
            const submitter = pendingSubmitter;
            form.dataset.confirmed = '1';
            modal.hidden = true;
            pendingForm      = null;
            pendingSubmitter = null;
            triggerEl         = null;
            if (form.requestSubmit) {
                form.requestSubmit(submitter || undefined);
            } else {
                form.submit();
            }
        });
    }
});

/* ------------------------------------------------------------------ */
/* Event form: warn before submitting without an organiser e-mail      */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-optional-email-check]').forEach(function (form) {
        const emailInput = form.querySelector('[data-optional-email-input]');
        const modal       = document.querySelector('[data-optional-email-modal]');
        if (!emailInput || !modal) return;

        let confirmed = false;

        const backBtn      = modal.querySelector('[data-optional-email-back]');
        const continueBtn  = modal.querySelector('[data-optional-email-continue]');

        form.addEventListener('submit', function (e) {
            if (confirmed) return;
            if (emailInput.value.trim() === '') {
                e.preventDefault();
                modal.hidden = false;
                // Focus the least destructive action first (Back), not Continue.
                (backBtn || continueBtn)?.focus();
            }
        });

        if (backBtn) {
            backBtn.addEventListener('click', function () {
                modal.hidden = true;
                emailInput.focus();
            });
        }

        if (continueBtn) {
            continueBtn.addEventListener('click', function () {
                confirmed = true;
                modal.hidden = true;
                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        }

        // Clicking the dimmed backdrop behaves like "back" (safer default).
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.hidden = true;
                emailInput.focus();
            }
        });

        modal.addEventListener('keydown', function (e) {
            trapTabKey(e, modal);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) {
                modal.hidden = true;
                emailInput.focus();
            }
        });
    });
});

/* ------------------------------------------------------------------ */
/* Event form: default "Benachrichtigung bei Übernahme" off while gift  */
/* mode is enabled (still just a default — the admin can re-enable it). */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const giftModeCheckbox      = document.getElementById('gift_mode');
    const notifyOnClaimCheckbox = document.getElementById('notify_on_claim');
    if (!giftModeCheckbox || !notifyOnClaimCheckbox) return;

    giftModeCheckbox.addEventListener('change', function () {
        notifyOnClaimCheckbox.checked = !giftModeCheckbox.checked;
    });
});

/* ------------------------------------------------------------------ */
/* Inline item edit forms                                              */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    // Toggle edit form visibility when "Bearbeiten" is clicked
    document.querySelectorAll('[data-edit-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.editToggle);
            if (!target) return;
            const isHidden = target.hidden;
            // Close all open edit forms first
            document.querySelectorAll('[data-item-edit-form]').forEach(function (f) {
                f.hidden = true;
            });
            document.querySelectorAll('[data-item-display]').forEach(function (d) {
                d.hidden = false;
            });
            if (isHidden) {
                target.hidden = false;
                const displayEl = document.getElementById(btn.dataset.editToggle.replace('edit-', 'display-'));
                if (displayEl) displayEl.hidden = true;
                target.querySelector('input, textarea')?.focus();
            }
        });
    });

    // Cancel button in edit form
    document.querySelectorAll('[data-edit-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = btn.closest('[data-item-edit-form]');
            if (form) {
                form.hidden = true;
                const displayEl = document.getElementById(
                    form.id.replace('edit-', 'display-')
                );
                if (displayEl) displayEl.hidden = false;
            }
        });
    });
});

/* ------------------------------------------------------------------ */
/* Claim form: basic validation + disable after submit                 */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-claim-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const nameInput = form.querySelector('[data-claim-name]');
            if (nameInput && !nameInput.value.trim()) {
                e.preventDefault();
                nameInput.focus();
                nameInput.classList.add('input-error');
                return;
            }
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = submitBtn.dataset.loadingText || 'Wird gesendet…';
            }
        });
    });
});

/* ------------------------------------------------------------------ */
/* Admin: Design settings — sync color picker, hex input & hidden field */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const picker  = document.getElementById('primaryColor');
    const textInp = document.getElementById('colorText');
    const hidden  = document.getElementById('primaryColorHidden');
    if (!picker) return;

    const warningEl   = document.getElementById('colorContrastWarning');
    const warningTmpl = warningEl ? warningEl.dataset.contrastWarningTemplate : '';

    // WCAG 2.x relative luminance / contrast ratio (against white #fff button text).
    function contrastAgainstWhite(hex) {
        const rgb = [1, 3, 5].map(function (i) { return parseInt(hex.slice(i, i + 2), 16) / 255; });
        const lum = rgb.reduce(function (acc, c, idx) {
            const linear = c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
            const weight = [0.2126, 0.7152, 0.0722][idx];
            return acc + linear * weight;
        }, 0);
        return (1.05) / (lum + 0.05);
    }

    function checkContrast(hex6) {
        if (!warningEl) return;
        const ratio = contrastAgainstWhite(hex6);
        if (ratio < 4.5) {
            warningEl.textContent = warningTmpl.replace('{ratio}', ratio.toFixed(1));
            warningEl.hidden = false;
        } else {
            warningEl.hidden = true;
        }
    }

    function preview(val) {
        document.documentElement.style.setProperty('--primary-color', val);
        const swatch = document.getElementById('colorPreview');
        if (swatch) swatch.style.background = val;
        checkContrast(val);
    }

    // The <input type="color"> picker always produces a valid lowercase 6-digit hex value.
    picker.addEventListener('input', function () {
        if (textInp) textInp.value = picker.value;
        if (hidden)  hidden.value  = picker.value;
        preview(picker.value);
    });

    checkContrast(picker.value);

    // The hex text field is free-form (case, 3- or 6-digit), so it always drives the
    // hidden field directly — it does not depend on the picker accepting the value.
    textInp.addEventListener('input', function () {
        const val = textInp.value.trim();
        if (hidden) hidden.value = val;

        const match = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(val);
        if (match) {
            let hex = match[1];
            if (hex.length === 3) hex = hex.split('').map((c) => c + c).join('');
            const normalized = '#' + hex.toLowerCase();
            picker.value = normalized;
            preview(normalized);
        }
    });
});

/* ------------------------------------------------------------------ */
/* Language switcher                                                    */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const langSelect = document.getElementById('langSwitcherSelect');
    if (!langSelect) return;

    langSelect.addEventListener('change', function () {
        window.location.href = '?lang=' + encodeURIComponent(langSelect.value);
    });
});

/* ------------------------------------------------------------------ */
/* Admin: Email transport selector — show/hide relevant fields         */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const transportSelect = document.getElementById('emailTransport');
    if (!transportSelect) return;

    function updateEmailFields() {
        const val = transportSelect.value;
        document.querySelectorAll('[data-transport]').forEach(function (el) {
            const show = el.dataset.transport.split(',').includes(val);
            el.hidden = !show;
        });
    }

    transportSelect.addEventListener('change', updateEmailFields);
    updateEmailFields();
});

/* ------------------------------------------------------------------ */
/* Admin: Logo file preview                                            */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const logoInput = document.getElementById('logoUpload');
    const logoPreview = document.getElementById('logoPreview');
    if (!logoInput || !logoPreview) return;

    logoInput.addEventListener('change', function () {
        const file = logoInput.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            logoPreview.src = e.target.result;
            logoPreview.hidden = false;
        };
        reader.readAsDataURL(file);
    });
});

/* ------------------------------------------------------------------ */
/* Copy-to-clipboard buttons + select-on-click / show-password fields  */
/* (CSP is script-src 'self' with no 'unsafe-inline', so these can't   */
/* use onclick="" attributes — data-* + addEventListener instead)      */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-select-on-click]').forEach(function (input) {
        input.addEventListener('click', function () { input.select(); });
    });

    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.copyTarget);
            if (input) copyToClipboard(input.value, btn);
        });
    });

    document.querySelectorAll('[data-copy-value]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            copyToClipboard(btn.dataset.copyValue, btn);
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const field = document.getElementById(btn.dataset.togglePassword);
            if (!field) return;
            const show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            btn.textContent = show ? '🙈' : '👁';
            // aria-label mirrors the current action (what the button does next),
            // not the icon — must flip along with the field type.
            const label = show ? btn.dataset.hideLabel : btn.dataset.showLabel;
            if (label) btn.setAttribute('aria-label', label);
        });
    });
});

/* ------------------------------------------------------------------ */
/* Gift Mode: presence heartbeat for the guest's own fully-claimed items */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-gift-mode="1"]');
    if (!root) return;

    const claimIds = [];
    root.querySelectorAll('[data-own-claim-id]').forEach(function (el) {
        el.dataset.ownClaimId.split(',').forEach(function (raw) {
            const id = parseInt(raw, 10);
            if (!isNaN(id)) claimIds.push(id);
        });
    });
    if (claimIds.length === 0) return;

    const url      = root.dataset.heartbeatUrl;
    const csrf     = root.dataset.csrfToken;
    const interval = parseInt(root.dataset.heartbeatInterval, 10) || 30000;

    function sendHeartbeat(action) {
        const payload = JSON.stringify({ claim_ids: claimIds, csrf_token: csrf, action: action });
        if (action === 'leave' && navigator.sendBeacon) {
            navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));
            return;
        }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload,
            keepalive: true,
        }).catch(function () { /* best-effort, ignore network errors */ });
    }

    sendHeartbeat('ping');
    const timer = setInterval(function () { sendHeartbeat('ping'); }, interval);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            sendHeartbeat('leave');
        } else {
            sendHeartbeat('ping');
        }
    });

    window.addEventListener('pagehide', function () {
        clearInterval(timer);
        sendHeartbeat('leave');
    });
});

/* ------------------------------------------------------------------ */
/* Admin: item reordering                                              */
/* Two input paths share the same state/persist logic:                 */
/*  - pointer drag (mouse/touch/pen) via the grip handle (pointer-only,*/
/*    so purely decorative/aria-hidden for keyboard & screen readers)  */
/*  - Move up/down buttons, fully keyboard- and screen-reader-operable */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const list = document.querySelector('[data-sortable-items]');
    if (!list) return;

    const url          = list.dataset.reorderUrl;
    const csrf         = list.dataset.csrfToken;
    const announceEl   = document.querySelector('[data-reorder-live]');
    const announceTmpl = list.dataset.reorderAnnounceTemplate || '';

    let dragEl        = null;
    let orderBeforeDrag = null;

    function wrappers() {
        return Array.from(list.querySelectorAll('[data-item-wrapper]'));
    }

    function currentIds() {
        return wrappers().map(function (el) { return el.dataset.itemId; });
    }

    function restoreOrder(ids) {
        ids.forEach(function (id) {
            const el = list.querySelector('[data-item-wrapper][data-item-id="' + id + '"]');
            if (el) list.appendChild(el);
        });
        updateReorderButtonStates();
    }

    function showToast(type, message) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.setAttribute('role', 'region');
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        const el = document.createElement('div');
        el.className = 'alert alert-' + type;
        el.setAttribute('role', 'alert');
        el.textContent = message;
        container.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 6000);
    }

    // Keep the disabled state of the up/down buttons in sync with the
    // current position (first item: "up" disabled, last item: "down"
    // disabled), no matter whether the reorder happened via drag or buttons.
    function updateReorderButtonStates() {
        const items = wrappers();
        items.forEach(function (el, index) {
            const upBtn   = el.querySelector('[data-move-up]');
            const downBtn = el.querySelector('[data-move-down]');
            if (upBtn)   upBtn.disabled   = index === 0;
            if (downBtn) downBtn.disabled = index === items.length - 1;
        });
    }

    function announcePosition(wrapper) {
        if (!announceEl || !announceTmpl) return;
        const items = wrappers();
        const index = items.indexOf(wrapper);
        const name  = wrapper.querySelector('.item-name');
        announceEl.textContent = announceTmpl
            .replace('{name}', name ? name.textContent.trim() : '')
            .replace('{position}', String(index + 1))
            .replace('{total}', String(items.length));
    }

    function onPointerMove(e) {
        if (!dragEl) return;
        const y = e.clientY;
        let next = null;
        for (const sib of wrappers()) {
            if (sib === dragEl) continue;
            const rect = sib.getBoundingClientRect();
            if (y < rect.top + rect.height / 2) { next = sib; break; }
        }
        if (next) {
            list.insertBefore(dragEl, next);
        } else {
            list.appendChild(dragEl);
        }
    }

    function onPointerUp() {
        if (!dragEl) return;
        dragEl.classList.remove('item-dragging');
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        updateReorderButtonStates();
        if (currentIds().join(',') !== orderBeforeDrag.join(',')) {
            persistOrder(orderBeforeDrag);
        }
        dragEl = null;
        orderBeforeDrag = null;
    }

    function persistOrder(previousIds) {
        const ids = wrappers().map(function (el) { return parseInt(el.dataset.itemId, 10); });
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_ids: ids, csrf_token: csrf }),
        }).then(function (res) {
            if (!res.ok) throw new Error('reorder failed');
        }).catch(function () {
            restoreOrder(previousIds);
            showToast('error', list.dataset.reorderErrorMessage);
        });
    }

    list.addEventListener('pointerdown', function (e) {
        const handle = e.target.closest('[data-drag-handle]');
        if (!handle) return;
        dragEl = handle.closest('[data-item-wrapper]');
        if (!dragEl) return;
        e.preventDefault();
        orderBeforeDrag = currentIds();
        dragEl.classList.add('item-dragging');
        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
    });

    // Keyboard-operable move up/down buttons — same persist/restore logic as drag.
    list.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-move-up], [data-move-down]');
        if (!btn || btn.disabled) return;

        const wrapper = btn.closest('[data-item-wrapper]');
        if (!wrapper) return;

        const items = wrappers();
        const index = items.indexOf(wrapper);
        const targetIndex = index + (btn.hasAttribute('data-move-up') ? -1 : 1);
        if (targetIndex < 0 || targetIndex >= items.length) return;

        const previousIds = currentIds();
        if (targetIndex < index) {
            list.insertBefore(wrapper, items[targetIndex]);
        } else {
            list.insertBefore(wrapper, items[targetIndex].nextSibling);
        }

        updateReorderButtonStates();
        announcePosition(wrapper);
        persistOrder(previousIds);
        btn.focus();
    });
});

/* ------------------------------------------------------------------ */
/* Error summary: move focus to [role="alert"][data-autofocus] blocks  */
/* after a failed form submit, so keyboard/screen-reader users land on */
/* the error list immediately instead of having to find it manually.   */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    const el = document.querySelector('[data-autofocus]');
    if (el) el.focus();
});

/* ------------------------------------------------------------------ */
/* Flash message auto-dismiss                                          */
/* ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        const delay = parseInt(el.dataset.autoDismiss, 10) || 5000;
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, delay);
    });
});
