<?php
/**
 * Event-Erstellungsformular.
 * Variables: $errors (array), $old (array — previous POST values on validation failure)
 */
$old    = $old    ?? [];
$errors = $errors ?? [];

// Restore previous values or set sensible defaults
$v = [
    'title'              => $old['title']              ?? '',
    'description'        => $old['description']        ?? '',
    'event_date'         => $old['event_date']         ?? '',
    'creator_email'      => $old['creator_email']      ?? '',
    'guest_sees_names'   => $old['guest_sees_names']   ?? false,
    'guests_can_suggest' => $old['guests_can_suggest'] ?? false,
    'notify_on_claim'    => $old['notify_on_claim']    ?? true,
    'notify_on_complete' => $old['notify_on_complete'] ?? true,
    'gift_mode'           => $old['gift_mode']           ?? false,
    'auto_delete_days'   => $old['auto_delete_days']   ?? 30,
];
?>

<div style="max-width:640px;margin-inline:auto;">

    <?php if (!empty($_SESSION['user_logged_in'])): ?>
    <div style="margin-bottom:.75rem;">
        <a href="<?= h(base_url(($_SESSION['user_role'] ?? '') === 'superadmin' ? 'admin/dashboard' : 'my/lists')) ?>"
           class="text-sm" style="color:var(--text-muted);">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <?= h(__('admin.back_to_overview')) ?>
        </a>
    </div>
    <?php endif; ?>

    <h1 style="margin-bottom:1.5rem;">
        <i class="fa-solid fa-calendar-plus" aria-hidden="true" style="color:var(--primary-color);margin-right:.4rem;"></i>
        <?= h(__('event.create_title')) ?>
    </h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert" tabindex="-1" data-autofocus>
        <strong><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> <?= h(__('install.error_heading')) ?></strong>
        <ul>
            <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= h(base_url()) ?>" novalidate data-optional-email-check>
        <?= csrf_field() ?>

        <!-- Core fields -->
        <div class="card">
            <div class="form-group">
                <label class="form-label" for="title"><?= h(__('event.title_label')) ?> *</label>
                <input class="form-input" type="text" id="title" name="title"
                       value="<?= h($v['title']) ?>" maxlength="255" required autofocus
                       placeholder="z.B. Grillparty bei Max">
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= h(__('event.description_label')) ?></label>
                <textarea class="form-textarea" id="description" name="description"
                          rows="3" placeholder="Kurze Beschreibung (optional)"><?= h($v['description']) ?></textarea>
            </div>

            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:180px;">
                    <label class="form-label" for="event_date">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <?= h(__('event.date_label')) ?>
                    </label>
                    <input class="form-input" type="date" id="event_date" name="event_date"
                           value="<?= h($v['event_date']) ?>">
                </div>

                <div class="form-group" style="flex:1;min-width:180px;">
                    <label class="form-label" for="creator_email">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                        <?= h(__('event.creator_email_label')) ?>
                    </label>
                    <input class="form-input" type="email" id="creator_email" name="creator_email"
                           value="<?= h($v['creator_email']) ?>"
                           placeholder="deine@email.de" data-optional-email-input>
                    <p class="form-hint"><?= h(__('event.creator_email_hint')) ?></p>
                </div>
            </div>
        </div>

        <!-- Settings (collapsible) -->
        <details class="card" style="padding:0;" <?= !empty($errors) ? 'open' : '' ?>>
            <summary style="padding:1rem 1.25rem;cursor:pointer;font-weight:600;list-style:none;display:flex;align-items:center;gap:.5rem;">
                <i class="fa-solid fa-sliders" aria-hidden="true" style="color:var(--primary-color);"></i>
                <?= h(__('event.settings_title')) ?>
                <i class="fa-solid fa-chevron-down" aria-hidden="true" style="margin-left:auto;font-size:.75rem;color:var(--text-muted);"></i>
            </summary>
            <div style="padding:0 1.25rem 1.25rem;">

                <div class="form-check">
                    <input type="checkbox" id="guest_sees_names" name="guest_sees_names"
                           <?= $v['guest_sees_names'] ? 'checked' : '' ?>>
                    <label for="guest_sees_names"><?= h(__('event.guest_sees_names')) ?></label>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="guests_can_suggest" name="guests_can_suggest"
                           <?= $v['guests_can_suggest'] ? 'checked' : '' ?>>
                    <label for="guests_can_suggest"><?= h(__('event.guests_can_suggest')) ?></label>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="notify_on_claim" name="notify_on_claim"
                           <?= $v['notify_on_claim'] ? 'checked' : '' ?>>
                    <label for="notify_on_claim">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        <?= h(__('event.notify_on_claim')) ?>
                    </label>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="notify_on_complete" name="notify_on_complete"
                           <?= $v['notify_on_complete'] ? 'checked' : '' ?>>
                    <label for="notify_on_complete">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        <?= h(__('event.notify_on_complete')) ?>
                    </label>
                </div>

                <div class="form-group" style="margin-top:.75rem;margin-bottom:0;">
                    <label class="form-label" for="auto_delete_days">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <?= h(__('event.auto_delete_days')) ?>
                    </label>
                    <div style="display:flex;align-items:center;gap:.5rem;max-width:220px;">
                        <input class="form-input" type="number" id="auto_delete_days" name="auto_delete_days"
                               value="<?= (int) $v['auto_delete_days'] ?>"
                               min="1" max="365" required style="width:6rem;">
                        <span class="text-muted text-sm">Tage</span>
                    </div>
                    <p class="form-hint"><?= h(__('event.auto_delete_hint')) ?></p>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="gift_mode" name="gift_mode"
                           <?= $v['gift_mode'] ? 'checked' : '' ?>>
                    <label for="gift_mode">
                        <i class="fa-solid fa-gift" aria-hidden="true"></i>
                        <?= h(__('event.gift_mode_label')) ?>
                    </label>
                    <p class="form-hint"><?= h(__('event.gift_mode_description')) ?></p>
                </div>

                <div class="form-group" style="margin-top:.75rem;margin-bottom:0;">
                    <label class="form-label" for="access_password">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <?= h(__('event.access_password_label')) ?>
                    </label>
                    <input class="form-input" type="password" id="access_password" name="access_password"
                           autocomplete="new-password" minlength="4" maxlength="50" style="max-width:220px;">
                    <p class="form-hint"><?= h(__('event.access_password_hint')) ?></p>
                </div>
            </div>
        </details>

        <button type="submit" class="btn btn-primary btn-full" style="font-size:1.0625rem;padding:.8rem;">
            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
            <?= h(__('event.submit_create')) ?>
        </button>
    </form>

    <?php include BASE_DIR . '/templates/partials/optional_email_modal.php'; ?>
</div>
