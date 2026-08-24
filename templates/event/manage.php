<?php
/**
 * Event-Verwaltungsseite (Admin-Ansicht).
 * Variables:
 *   $event           array
 *   $approvedItems   array  (with 'claims' and 'claims_total' attached)
 *   $pendingItems    array
 *   $progressTotal   int
 *   $progressClaimed int
 *   $guestUrl        string
 */

$progressPct = $progressTotal > 0
    ? round(($progressClaimed / $progressTotal) * 100)
    : 0;
$adminToken  = $event['admin_token'];
?>

<?php if (!empty($_SESSION['user_logged_in'])): ?>
<!-- Shown when viewing as a logged-in account (SuperAdmin or the list's -->
<!-- owner) — organisers without an account only ever have their         -->
<!-- admin_token link and have no dashboard to return to.                -->
<div style="margin-bottom:.75rem;">
    <a href="<?= h(base_url(($_SESSION['user_role'] ?? '') === 'superadmin' ? 'admin/dashboard' : 'my/lists')) ?>"
       class="text-sm" style="color:var(--text-muted);">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <?= h(__('admin.back_to_overview')) ?>
    </a>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- Event header                                                      -->
<!-- ================================================================ -->
<div class="card" style="margin-bottom:1rem;">
    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:.75rem;justify-content:space-between;">
        <div>
            <h1 style="font-size:1.5rem;margin-bottom:.25rem;">
                <?= h($event['title']) ?>
                <?php if (!empty($event['gift_mode'])): ?>
                <span class="badge badge-success" title="<?= h(__('event.gift_mode_description')) ?>">
                    <i class="fa-solid fa-gift" aria-hidden="true"></i>
                    <?= h(__('event.gift_mode_label')) ?>
                </span>
                <?php endif; ?>
            </h1>
            <?php if (!empty($event['event_date'])): ?>
            <p class="text-muted text-sm">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                <?= h(date('d.m.Y', strtotime($event['event_date']))) ?>
            </p>
            <?php endif; ?>
            <?php if (!empty($event['description'])): ?>
            <p style="margin-top:.5rem;color:var(--text-muted);font-size:.9375rem;"><?= h($event['description']) ?></p>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a href="<?= h(base_url('e/' . $adminToken . '/edit')) ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                <?= h(__('event.edit_settings')) ?>
            </a>
            <a href="<?= h(base_url('e/' . $adminToken . '/pdf')) ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                <?= h(__('pdf.download')) ?>
            </a>
            <a href="<?= h($guestUrl) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                Gäste-Ansicht
            </a>
        </div>
    </div>

    <!-- Guest link to copy/share -->
    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
        <label style="font-size:.8125rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:.375rem;">
            <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
            <?= h(__('event.guest_link_label')) ?>
            <?php if (!empty($event['access_password_hash'])): ?>
            <span class="text-muted" style="text-transform:none;letter-spacing:normal;font-weight:400;">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <?= h(__('event.access_password_active')) ?>
            </span>
            <?php endif; ?>
        </label>
        <div class="link-display">
            <input type="text" id="guestLinkShare" readonly value="<?= h($guestUrl) ?>"
                   data-select-on-click style="font-size:.8125rem;">
            <button class="btn btn-primary btn-sm"
                    data-copy-target="guestLinkShare"
                    data-success-msg="<?= h(__('event.copy_success')) ?>">
                <i class="fa-solid fa-copy" aria-hidden="true"></i>
                Kopieren
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- Progress bar                                                      -->
<!-- ================================================================ -->
<?php if ($progressTotal > 0): ?>
<div class="progress-wrap">
    <div class="progress-label">
        <span>
            <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
            <?= h(__('guest.progress', ['claimed' => $progressClaimed, 'total' => $progressTotal])) ?>
        </span>
        <span><?= $progressPct ?>%</span>
    </div>
    <div class="progress-bar-outer" role="progressbar"
         aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar-inner <?= $progressPct >= 100 ? 'progress-bar-inner--complete' : '' ?>"
             style="width:<?= $progressPct ?>%;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- Add item form                                                     -->
<!-- ================================================================ -->
<div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:1rem;margin-bottom:.875rem;">
        <i class="fa-solid fa-plus-circle" aria-hidden="true" style="color:var(--primary-color);"></i>
        <?= h(__('item.add')) ?>
    </h2>
    <form method="post" action="<?= h(base_url('e/' . $adminToken . '/items')) ?>">
        <?= csrf_field() ?>
        <div style="display:flex;gap:.625rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                <label class="form-label" for="new_item_name"><?= h(__('item.name')) ?></label>
                <input class="form-input" type="text" id="new_item_name" name="name"
                       placeholder="z.B. Salat" maxlength="255" required>
            </div>
            <div class="form-group" style="width:6rem;margin-bottom:0;">
                <label class="form-label" for="new_item_qty"><?= h(__('item.quantity')) ?></label>
                <input class="form-input" type="number" id="new_item_qty" name="quantity"
                       value="1" min="1" max="999" required>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:0;">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <?= h(__('item.add')) ?>
            </button>
        </div>
    </form>
</div>

<!-- ================================================================ -->
<!-- Approved items list                                               -->
<!-- ================================================================ -->
<h2 style="font-size:1.125rem;margin-bottom:.75rem;">
    <i class="fa-solid fa-list-check" aria-hidden="true" style="color:var(--primary-color);"></i>
    <?= h(__('event.manage_items_title')) ?>
    <?php if (!empty($approvedItems)): ?>
    <span class="badge badge-info" style="vertical-align:middle;"><?= count($approvedItems) ?></span>
    <?php endif; ?>
</h2>

<?php if (empty($approvedItems)): ?>
<div class="card" style="text-align:center;padding:2rem;color:var(--text-muted);">
    <i class="fa-solid fa-box-open" aria-hidden="true" style="font-size:2rem;margin-bottom:.75rem;display:block;"></i>
    <?= h(__('event.no_items')) ?>
</div>
<?php else: ?>
<!-- Screen-reader-only status announcement after keyboard reordering -->
<div class="sr-only" aria-live="polite" data-reorder-live></div>

<div class="item-list" style="margin-bottom:1.5rem;"
     data-sortable-items
     data-reorder-url="<?= h(base_url('e/' . $adminToken . '/items/reorder')) ?>"
     data-csrf-token="<?= h($_SESSION['csrf_token']) ?>"
     data-reorder-error-message="<?= h(__('item.reorder_failed')) ?>"
     data-reorder-announce-template="<?= h(__('item.reorder_announcement')) ?>">
<?php
$approvedItemCount = count($approvedItems);
foreach ($approvedItems as $itemIndex => $item):
    $isFull = $item['claims_total'] >= $item['quantity'];
    $remaining = $item['quantity'] - $item['claims_total'];
?>

    <!-- Drag wrapper: keeps display + edit row together while reordering -->
    <div data-item-wrapper data-item-id="<?= $item['id'] ?>">

    <!-- Display row -->
    <div id="display-<?= $item['id'] ?>" data-item-display
         class="item-row <?= $isFull ? 'item-row--claimed' : '' ?>">

        <!-- Keyboard-operable alternative to the pointer-only drag handle below -->
        <div class="item-reorder-controls">
            <button type="button" class="item-reorder-btn" data-move-up
                    <?= $itemIndex === 0 ? 'disabled' : '' ?>
                    aria-label="<?= h(__('item.move_up')) . ' ' . h($item['name']) ?>">
                <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="item-reorder-btn" data-move-down
                    <?= $itemIndex === $approvedItemCount - 1 ? 'disabled' : '' ?>
                    aria-label="<?= h(__('item.move_down')) . ' ' . h($item['name']) ?>">
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
        </div>

        <span class="item-drag-handle" data-drag-handle
              title="<?= h(__('item.drag_handle')) ?>"
              aria-hidden="true">
            <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
        </span>

        <span class="item-name <?= $isFull ? 'item-claimed' : '' ?>">
            <?= h($item['name']) ?>
        </span>

        <span class="badge <?= $isFull ? 'badge-claimed' : ($item['claims_total'] > 0 ? 'badge-pending' : 'badge-neutral') ?>">
            <?= $item['claims_total'] ?>/<?= $item['quantity'] ?>
        </span>

        <?php if (!$isFull && $remaining < $item['quantity']): ?>
        <span class="text-sm text-muted">
            (<?= h(__('guest.items_free', ['count' => $remaining])) ?>)
        </span>
        <?php elseif ($isFull): ?>
        <span class="badge badge-claimed">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <?= h(__('guest.items_claimed')) ?>
        </span>
        <?php endif; ?>

        <div class="item-actions" style="margin-left:auto;">
            <button class="btn btn-secondary btn-sm"
                    data-edit-toggle="edit-<?= $item['id'] ?>"
                    aria-label="<?= h(__('item.edit')) . ' ' . h($item['name']) ?>">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                <?= h(__('item.edit')) ?>
            </button>
            <form method="post"
                  action="<?= h(base_url('e/' . $adminToken . '/items/' . $item['id'] . '/delete')) ?>"
                  style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="<?= h(__('item.confirm_delete')) ?>"
                        aria-label="<?= h(__('item.delete')) . ' ' . h($item['name']) ?>">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    <?= h(__('item.delete')) ?>
                </button>
            </form>
        </div>

        <!-- Claims below item name -->
        <?php if (!empty($item['claims'])): ?>
        <div class="item-claims-info" style="width:100%;">
            <?php foreach ($item['claims'] as $claim): ?>
            <span class="badge badge-neutral">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <?= h($claim['guest_name']) ?>
                <?= $claim['quantity'] > 1 ? '×' . $claim['quantity'] : '' ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Inline edit form (hidden by default) -->
    <div id="edit-<?= $item['id'] ?>" data-item-edit-form hidden class="item-row"
         style="background:var(--info-bg);border-color:var(--info);">
        <form method="post"
              action="<?= h(base_url('e/' . $adminToken . '/items/' . $item['id'] . '/update')) ?>"
              style="display:flex;gap:.5rem;flex-wrap:wrap;flex:1;align-items:flex-end;">
            <?= csrf_field() ?>
            <div class="form-group" style="flex:1;min-width:140px;margin-bottom:0;">
                <label class="form-label" style="font-size:.8125rem;"><?= h(__('item.name')) ?></label>
                <input class="form-input" type="text" name="name"
                       value="<?= h($item['name']) ?>" maxlength="255" required>
            </div>
            <div class="form-group" style="width:5.5rem;margin-bottom:0;">
                <label class="form-label" style="font-size:.8125rem;"><?= h(__('item.quantity')) ?></label>
                <input class="form-input" type="number" name="quantity"
                       value="<?= $item['quantity'] ?>" min="1" max="999" required>
            </div>
            <div style="display:flex;gap:.375rem;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <?= h(__('item.save')) ?>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-edit-cancel>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    <?= h(__('item.cancel')) ?>
                </button>
            </div>
        </form>
    </div>

    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- Pending items (suggestions from guests)                          -->
<!-- ================================================================ -->
<?php if ($event['guests_can_suggest'] && !empty($pendingItems)): ?>
<div style="margin-top:1.5rem;">
    <h2 style="font-size:1.125rem;margin-bottom:.75rem;">
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true" style="color:var(--warning);"></i>
        Vorschläge von Gästen
        <span class="badge badge-pending" style="vertical-align:middle;"><?= count($pendingItems) ?></span>
    </h2>

    <div class="item-list">
    <?php foreach ($pendingItems as $item): ?>
    <div class="item-row" style="border-color:var(--warning);background:var(--warning-bg);">
        <div>
            <span class="item-name"><?= h($item['name']) ?></span>
            <?php if (!empty($item['suggested_by_guest'])): ?>
            <span class="text-sm text-muted" style="display:block;">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <?= h(__('item.suggested_by', ['name' => $item['suggested_by_guest']])) ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="item-actions" style="margin-left:auto;">
            <form method="post"
                  action="<?= h(base_url('e/' . $adminToken . '/items/' . $item['id'] . '/approve')) ?>"
                  style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    <?= h(__('item.approve')) ?>
                </button>
            </form>
            <form method="post"
                  action="<?= h(base_url('e/' . $adminToken . '/items/' . $item['id'] . '/reject')) ?>"
                  style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Vorschlag &quot;<?= h($item['name']) ?>&quot; ablehnen und löschen?">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    <?= h(__('item.reject')) ?>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php elseif ($event['guests_can_suggest'] && empty($pendingItems)): ?>
<p class="text-muted text-sm" style="margin-top:.5rem;">
    <i class="fa-solid fa-inbox" aria-hidden="true"></i>
    Keine offenen Vorschläge.
</p>
<?php endif; ?>
