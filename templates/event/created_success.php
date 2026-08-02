<?php
/**
 * Erfolgsseite nach Event-Erstellung.
 * Variables: $event (array), $adminUrl (string), $guestUrl (string)
 */
?>

<div style="max-width:620px;margin-inline:auto;">

    <div class="alert alert-success" style="font-size:1.0625rem;">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <strong><?= h(__('event.created_success')) ?></strong>
        — <?= h($event['title']) ?>
    </div>

    <!-- WARNING: Save admin link -->
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <strong><?= h(__('event.admin_link_warning')) ?></strong>
    </div>

    <!-- Admin link -->
    <div class="card card--highlight">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
            <i class="fa-solid fa-lock" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('event.admin_link_label')) ?>
        </h2>
        <div class="link-display">
            <input type="text" id="adminLinkInput" readonly
                   value="<?= h($adminUrl) ?>"
                   data-select-on-click
                   style="font-size:.8125rem;">
            <button class="btn btn-primary btn-sm"
                    data-copy-target="adminLinkInput"
                    data-success-msg="<?= h(__('event.copy_success')) ?>">
                <i class="fa-solid fa-copy" aria-hidden="true"></i>
                Kopieren
            </button>
        </div>
        <p class="form-hint" style="margin-top:.5rem;">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Nur du hast diesen Link. Verlierst du ihn, gibt es keinen Zugang zur Verwaltung.
        </p>
    </div>

    <!-- Guest link -->
    <div class="card">
        <h2 style="font-size:1rem;margin-bottom:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">
            <i class="fa-solid fa-share-nodes" aria-hidden="true" style="color:var(--success);"></i>
            <?= h(__('event.guest_link_label')) ?>
        </h2>
        <div class="link-display">
            <input type="text" id="guestLinkInput" readonly
                   value="<?= h($guestUrl) ?>"
                   data-select-on-click
                   style="font-size:.8125rem;">
            <button class="btn btn-success btn-sm"
                    data-copy-target="guestLinkInput"
                    data-success-msg="<?= h(__('event.copy_success')) ?>">
                <i class="fa-solid fa-copy" aria-hidden="true"></i>
                Kopieren
            </button>
        </div>
        <p class="form-hint" style="margin-top:.5rem;">
            Diesen Link teilst du mit deinen Gästen.
        </p>
        <?php if (!empty($event['access_password_hash'])): ?>
        <p class="form-hint" style="margin-top:.375rem;">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <?= h(__('event.access_password_active')) ?>
            Vergiss nicht, das Passwort separat an deine Gäste weiterzugeben.
        </p>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;margin-top:.5rem;">
        <a href="<?= h(base_url('e/' . $event['admin_token'] . '/manage')) ?>" class="btn btn-primary">
            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
            <?= h(__('event.created_manage_link')) ?>
        </a>
        <a href="<?= h(base_url()) ?>" class="btn btn-secondary">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Weiteres Event erstellen
        </a>
    </div>

</div>
