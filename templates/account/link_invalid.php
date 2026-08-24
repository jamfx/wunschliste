<?php
/**
 * Shown when an invite or password-reset token is missing, expired, or
 * already used.
 * Variables: $message (string)
 */
?>
<div style="max-width:420px;margin-inline:auto;padding-top:3rem;text-align:center;">
    <i class="fa-solid fa-link-slash"
       aria-hidden="true"
       style="font-size:2.5rem;color:var(--text-muted);"></i>
    <h1 style="font-size:1.5rem;margin-top:.5rem;"><?= h($pageTitle ?? 'Link ungültig') ?></h1>
    <p class="text-muted" style="margin:1rem 0 1.5rem;"><?= h($message ?? 'Dieser Link ist ungültig oder abgelaufen.') ?></p>
    <a href="<?= h(base_url('admin/login')) ?>" class="btn btn-primary">
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
        Zum Login
    </a>
</div>
