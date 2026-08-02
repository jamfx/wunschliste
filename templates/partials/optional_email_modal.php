<?php
/**
 * Warn-Modal: wird per JS (app.js) eingeblendet, wenn das Event-Formular
 * ohne Mailadresse abgeschickt wird. Eingebunden von event/create.php und
 * event/edit.php.
 */
?>
<div class="modal-overlay" data-optional-email-modal hidden>
    <div class="modal-box card" role="dialog" aria-modal="true" aria-labelledby="optional-email-modal-title">
        <h2 id="optional-email-modal-title">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" style="color:var(--warning);"></i>
            <?= h(__('event.no_email_modal_title')) ?>
        </h2>
        <p><?= h(__('event.no_email_modal_text')) ?></p>
        <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:1.25rem;">
            <button type="button" class="btn btn-primary btn-full" data-optional-email-back>
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <?= h(__('event.no_email_modal_back')) ?>
            </button>
            <button type="button" class="btn btn-secondary btn-full" data-optional-email-continue>
                <?= h(__('event.no_email_modal_continue')) ?>
            </button>
        </div>
    </div>
</div>
