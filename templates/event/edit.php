<?php
/**
 * Event-Bearbeitungsformular (für Ersteller via Admin-Token).
 * Variables: $event (array), $errors (array), $old (array — POST values on validation failure)
 */
$errors = $errors ?? [];
$old    = $old    ?? [];

// On validation failure use POST values; otherwise fall back to DB values
$v = [
    'title'              => $old['title']              ?? $event['title'],
    'description'        => $old['description']        ?? ($event['description'] ?? ''),
    'event_date'         => $old['event_date']         ?? ($event['event_date']  ?? ''),
    'creator_email'      => $old['creator_email']      ?? ($event['creator_email'] ?? ''),
    'guest_sees_names'   => array_key_exists('guest_sees_names',   $old) ? $old['guest_sees_names']   : (bool) $event['guest_sees_names'],
    'guests_can_suggest' => array_key_exists('guests_can_suggest', $old) ? $old['guests_can_suggest'] : (bool) $event['guests_can_suggest'],
    'notify_on_claim'    => array_key_exists('notify_on_claim',    $old) ? $old['notify_on_claim']    : (bool) $event['notify_on_claim'],
    'notify_on_complete' => array_key_exists('notify_on_complete', $old) ? $old['notify_on_complete'] : (bool) $event['notify_on_complete'],
    'gift_mode'           => array_key_exists('gift_mode',           $old) ? $old['gift_mode']           : (bool) $event['gift_mode'],
    'auto_delete_days'   => $old['auto_delete_days']   ?? $event['auto_delete_days'],
];

$adminToken         = $event['admin_token'];
$hasAccessPassword  = !empty($event['access_password_hash']);
?>

<div style="max-width:640px;margin-inline:auto;">

    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
        <a href="<?= h(base_url('e/' . $adminToken . '/manage')) ?>"
           class="btn btn-secondary btn-sm" style="flex-shrink:0;">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <?= h(__('common.back')) ?>
        </a>
        <h1 style="font-size:1.5rem;margin:0;">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true" style="color:var(--primary-color);margin-right:.4rem;"></i>
            <?= h(__('event.edit_title')) ?>
        </h1>
    </div>

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

    <form method="post" action="<?= h(base_url('e/' . $adminToken . '/edit')) ?>" novalidate data-optional-email-check>
        <?= csrf_field() ?>

        <!-- Core fields -->
        <div class="card">
            <div class="form-group">
                <label class="form-label" for="title"><?= h(__('event.title_label')) ?> *</label>
                <input class="form-input" type="text" id="title" name="title"
                       value="<?= h($v['title']) ?>" maxlength="255" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="description"><?= h(__('event.description_label')) ?></label>
                <textarea class="form-textarea" id="description" name="description"
                          rows="3"><?= h($v['description']) ?></textarea>
                <p class="form-hint"><?= h(__('event.description_hint')) ?></p>
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
                           value="<?= h($v['creator_email']) ?>" data-optional-email-input>
                    <p class="form-hint"><?= h(__('event.creator_email_hint')) ?></p>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <details class="card" style="padding:0;" open>
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

                <div class="form-group" style="margin-top:.75rem;margin-bottom:0;">
                    <label class="form-label" for="access_password">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <?= h(__('event.access_password_label')) ?>
                    </label>
                    <p class="text-muted text-sm" style="margin:0 0 .5rem;">
                        <?= $hasAccessPassword
                            ? h(__('event.access_password_active'))
                            : h(__('event.access_password_inactive')) ?>
                    </p>
                    <input class="form-input" type="password" id="access_password" name="access_password"
                           autocomplete="new-password" minlength="4" maxlength="50" style="max-width:220px;">
                    <p class="form-hint"><?= h(__('event.access_password_hint_edit')) ?></p>

                    <?php if ($hasAccessPassword): ?>
                    <div class="form-check" style="margin-top:.5rem;">
                        <input type="checkbox" id="remove_access_password" name="remove_access_password">
                        <label for="remove_access_password"><?= h(__('event.access_password_remove')) ?></label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </details>

        <div style="display:flex;gap:.75rem;">
            <a href="<?= h(base_url('e/' . $adminToken . '/manage')) ?>"
               class="btn btn-secondary" style="flex:1;text-align:center;">
                <?= h(__('common.cancel')) ?>
            </a>
            <button type="submit" class="btn btn-primary" style="flex:2;font-size:1.0625rem;padding:.8rem;">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <?= h(__('common.save')) ?>
            </button>
        </div>
    </form>

    <div class="card" style="margin-top:1.5rem;border-color:var(--danger);background:var(--danger-bg);">
        <h3 style="color:#991b1b;">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <?= h(__('event.danger_zone_title')) ?>
        </h3>
        <p class="text-sm" style="margin-bottom:.875rem;color:#991b1b;">
            <?= h(__('event.danger_zone_hint')) ?>
        </p>
        <form method="post" action="<?= h(base_url('e/' . $adminToken . '/delete')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"
                    data-confirm="<?= h(__('event.delete_confirm', ['title' => $event['title']])) ?>">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                <?= h(__('event.delete_button')) ?>
            </button>
        </form>
    </div>

    <?php include BASE_DIR . '/templates/partials/optional_email_modal.php'; ?>
</div>
