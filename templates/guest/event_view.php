<?php
/**
 * Gäste-Ansicht eines Events.
 * Variables:
 *   $event             array
 *   $items             array  (each with 'claims', 'claims_total', 'remaining';
 *                               in gift mode also 'gift_state' [open|own] and
 *                               'own_claim_ids' for own items)
 *   $progressTotal        int
 *   $progressClaimed      int
 *   $heartbeatClaimIds    array (gift mode only: own claim IDs to send heartbeats for)
 *   $allClaimedAndHidden  bool  (gift mode only: items existed but all got claimed & hidden)
 */

$guestToken  = $event['guest_token'];
$giftMode    = !empty($event['gift_mode']);
$progressPct = $progressTotal > 0
    ? round(($progressClaimed / $progressTotal) * 100)
    : 0;
?>

<!-- ================================================================ -->
<!-- Event header                                                      -->
<!-- ================================================================ -->
<div class="card" style="margin-bottom:1.25rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:0;">
            <h1 style="font-size:1.5rem;margin-bottom:.375rem;"><?= h($event['title']) ?></h1>

            <?php if (!empty($event['event_date'])): ?>
            <p class="text-muted text-sm" style="margin-bottom:.375rem;">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                <?= h(date('d.m.Y', strtotime($event['event_date']))) ?>
            </p>
            <?php endif; ?>

            <?php if (!empty($event['description'])): ?>
            <p style="color:var(--text-muted);font-size:.9375rem;margin-top:.5rem;white-space:pre-line;">
                <?= h($event['description']) ?>
            </p>
            <?php endif; ?>
        </div>

        <?php if (!$giftMode): ?>
        <a href="<?= h(base_url('g/' . $guestToken . '/pdf')) ?>"
           class="btn btn-secondary btn-sm"
           title="<?= h(__('pdf.download')) ?>"
           style="flex-shrink:0;white-space:nowrap;">
            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
            <?= h(__('pdf.download')) ?>
        </a>
        <?php endif; ?>
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
<!-- Item list                                                         -->
<!-- ================================================================ -->
<?php if (empty($items) && $allClaimedAndHidden): ?>
<div class="card" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
    <i class="fa-solid fa-gift" aria-hidden="true" style="font-size:2rem;margin-bottom:.75rem;display:block;"></i>
    <p><?= h(__('guest.gift_mode_all_claimed')) ?></p>
</div>

<?php elseif (empty($items)): ?>
<div class="card" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
    <i class="fa-solid fa-box-open" aria-hidden="true" style="font-size:2rem;margin-bottom:.75rem;display:block;"></i>
    <p><?= h(__('event.no_items')) ?></p>
</div>

<?php else: ?>
<div class="item-list" style="margin-bottom:1.5rem;"
     <?php if ($giftMode): ?>
     data-gift-mode="1"
     data-heartbeat-url="<?= h(base_url('g/' . $guestToken . '/heartbeat')) ?>"
     data-heartbeat-interval="<?= (int) GIFT_MODE_HEARTBEAT_INTERVAL_MS ?>"
     data-csrf-token="<?= h($_SESSION['csrf_token']) ?>"
     <?php endif; ?>>
<?php foreach ($items as $item):
    $isFull      = $item['remaining'] === 0;
    $hasClaims   = !empty($item['claims']);
    $giftState   = $item['gift_state'] ?? null;
    $isGiftOwn   = $giftMode && $giftState === 'own';
    $isMultiGift = $giftMode && $item['quantity'] > 1;

    // Gift Mode: does the creator already know about this own claim? Exact
    // (via creator_notified_at), unlike the co-claimer heuristic below.
    $ownClaimRow = null;
    if ($isGiftOwn) {
        $ownClaimId = $item['own_claim_ids'][0] ?? null;
        foreach ($item['claims'] as $c) {
            if ((int) $c['id'] === (int) $ownClaimId) { $ownClaimRow = $c; break; }
        }
    }
    $creatorNotified = $ownClaimRow !== null && $ownClaimRow['creator_notified_at'] !== null;

    // Normal mode: which of this item's claims belong to the current guest
    // session (own claims are always shown/undoable, regardless of
    // guest_sees_names — a guest must always recognise their own entry).
    $ownClaims = (!$giftMode && $hasClaims)
        ? array_values(array_filter($item['claims'], fn($c) => guest_owns_claim($guestToken, $c['id'])))
        : [];
?>

    <div class="item-row <?= $isFull ? 'item-row--claimed' : '' ?> <?= $isGiftOwn ? 'gift-claimed-own' : '' ?>"
         style="flex-wrap:wrap;gap:.5rem;"
         <?php if ($isGiftOwn): ?>data-own-claim-id="<?= implode(',', $item['own_claim_ids']) ?>"<?php endif; ?>>

        <!-- Item name & status -->
        <div style="flex:1;min-width:160px;">
            <span class="item-name <?= $isFull ? 'item-claimed' : '' ?>">
                <?= h($item['name']) ?>
            </span>

            <?php if (!$giftMode && $event['guest_sees_names'] && $hasClaims): ?>
            <div style="margin-top:.3rem;">
                <?php foreach ($item['claims'] as $claim): ?>
                <span class="badge badge-neutral">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <?= h($claim['guest_name']) ?>
                    <?= $claim['quantity'] > 1 ? '×' . (int)$claim['quantity'] : '' ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php elseif (!$giftMode && !$event['guest_sees_names'] && $hasClaims): ?>
            <div style="margin-top:.3rem;">
                <span class="badge badge-neutral">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    <?= count($item['claims']) ?> <?= h(__('guest.claimed_by_count')) ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if ($isMultiGift && !$isFull): ?>
            <div class="gift-progress-dots" aria-hidden="true">
                <?php for ($i = 0; $i < $item['quantity']; $i++): ?>
                <span class="gift-dot <?= $i < $item['claims_total'] ? 'gift-dot--filled' : '' ?>"></span>
                <?php endfor; ?>
            </div>
            <p class="text-sm text-muted" style="margin-top:.25rem;">
                <?= h(__('guest.gift_mode_remaining', [
                    'remaining' => $item['quantity'] - $item['claims_total'],
                    'total'     => $item['quantity'],
                ])) ?>
            </p>
            <?php endif; ?>
        </div>

        <!-- Quantity badge -->
        <div style="display:flex;align-items:center;gap:.5rem;">
            <?php if (!$isMultiGift): ?>
            <span class="badge <?= $isFull ? 'badge-claimed' : ($hasClaims ? 'badge-pending' : 'badge-neutral') ?>">
                <?= $item['claims_total'] ?>/<?= $item['quantity'] ?>
            </span>
            <?php endif; ?>

            <?php if ($isGiftOwn): ?>
            <span class="badge badge-claimed">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <?= h(__('guest.gift_mode_own')) ?>
            </span>
            <?php elseif ($isFull): ?>
            <span class="badge badge-claimed">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <?= h(__('guest.items_claimed')) ?>
            </span>
            <?php elseif (!$giftMode && $item['remaining'] < $item['quantity']): ?>
            <span class="text-sm text-muted">
                <?= h(__('guest.items_free', ['count' => $item['remaining']])) ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- Undo form: the current guest session's own fully-claimed gift-mode item -->
        <?php if ($isGiftOwn): ?>
        <div style="width:100%;padding-top:.5rem;border-top:1px solid var(--border);">
            <form method="post"
                  action="<?= h(base_url('g/' . $guestToken . '/claim/' . $item['own_claim_ids'][0] . '/undo')) ?>"
                  <?php if ($creatorNotified): ?>data-confirm="<?= h(__('claim.undo_confirm_creator_notified')) ?>"
                  <?php elseif ($isMultiGift): ?>data-confirm="<?= h(__('claim.undo_confirm_notified')) ?>"<?php endif; ?>>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm" style="margin-bottom:0;">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    <?= h(__('claim.undo_button')) ?>
                </button>
            </form>
        </div>

        <!-- Claim form (only when not fully claimed) -->
        <?php elseif (!$isFull): ?>
        <div style="width:100%;padding-top:.5rem;border-top:1px solid var(--border);">
            <form method="post"
                  action="<?= h(base_url('g/' . $guestToken . '/claim')) ?>"
                  data-claim-form
                  style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">
                <?= csrf_field() ?>
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

                <div class="form-group" style="flex:1;min-width:150px;margin-bottom:0;">
                    <label class="form-label" style="font-size:.8125rem;" for="guest_name_<?= $item['id'] ?>">
                        <?= h(__('claim.guest_name_label')) ?> *
                    </label>
                    <input class="form-input" type="text"
                           id="guest_name_<?= $item['id'] ?>"
                           name="guest_name"
                           placeholder="<?= h(__('claim.guest_name_placeholder')) ?>"
                           maxlength="100"
                           required
                           data-claim-name>
                </div>

                <?php if ($isMultiGift): ?>
                <input type="hidden" name="quantity" value="1">
                <?php elseif ($item['quantity'] > 1): ?>
                <div class="form-group" style="width:6rem;margin-bottom:0;">
                    <label class="form-label" style="font-size:.8125rem;" for="qty_<?= $item['id'] ?>">
                        <?= h(__('claim.quantity_label')) ?>
                    </label>
                    <input class="form-input" type="number"
                           id="qty_<?= $item['id'] ?>"
                           name="quantity"
                           value="1"
                           min="1" max="<?= $item['remaining'] ?>"
                           required>
                </div>
                <?php else: ?>
                <input type="hidden" name="quantity" value="1">
                <?php endif; ?>

                <?php if ($isMultiGift): ?>
                <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0;">
                    <label class="form-label" style="font-size:.8125rem;" for="notification_email_<?= $item['id'] ?>">
                        <?= h(__('claim.gift_mode_email_label')) ?>
                    </label>
                    <input class="form-input" type="email"
                           id="notification_email_<?= $item['id'] ?>"
                           name="notification_email"
                           placeholder="<?= h(__('claim.gift_mode_email_placeholder')) ?>"
                           maxlength="255">
                    <p class="form-hint"><?= h(__('claim.gift_mode_email_consent')) ?></p>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-sm" style="margin-bottom:0;">
                    <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>
                    <?= h(__('claim.submit')) ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Undo form(s): normal mode, the current guest session's own claim(s)
             on this item. Shown in addition to (not instead of) the claim form
             above, since the item can still have remaining slots. -->
        <?php if (!empty($ownClaims)): ?>
        <div style="width:100%;padding-top:.5rem;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:.4rem;">
            <?php foreach ($ownClaims as $ownClaim): ?>
            <form method="post"
                  action="<?= h(base_url('g/' . $guestToken . '/claim/' . $ownClaim['id'] . '/undo')) ?>"
                  style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;"
                  <?php if ($ownClaim['creator_notified_at'] !== null): ?>data-confirm="<?= h(__('claim.undo_confirm_creator_notified')) ?>"<?php endif; ?>>
                <?= csrf_field() ?>
                <span class="text-sm text-muted">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <?= h(__('guest.own_claim_label')) ?><?= $ownClaim['quantity'] > 1 ? ' ×' . (int) $ownClaim['quantity'] : '' ?>
                </span>
                <button type="submit" class="btn btn-secondary btn-sm" style="margin-bottom:0;">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    <?= h(__('claim.undo_button')) ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- Suggest an item (if enabled)                                      -->
<!-- ================================================================ -->
<?php if ($event['guests_can_suggest']): ?>
<div class="card" style="margin-top:1.5rem;">
    <h2 style="font-size:1rem;margin-bottom:.875rem;">
        <i class="fa-solid fa-lightbulb" aria-hidden="true" style="color:var(--warning);"></i>
        <?= h(__('guest.suggest_title')) ?>
    </h2>
    <p class="text-muted text-sm" style="margin-bottom:.875rem;">
        <?= h(__('guest.suggest_description')) ?>
    </p>

    <form method="post" action="<?= h(base_url('g/' . $guestToken . '/suggest')) ?>">
        <?= csrf_field() ?>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:150px;margin-bottom:0;">
                <label class="form-label" style="font-size:.8125rem;" for="suggest_name">
                    <?= h(__('guest.suggest_name')) ?> *
                </label>
                <input class="form-input" type="text" id="suggest_name" name="name"
                       placeholder="z.B. Baguette" maxlength="255" required>
            </div>
            <div class="form-group" style="flex:1;min-width:150px;margin-bottom:0;">
                <label class="form-label" style="font-size:.8125rem;" for="suggest_guest_name">
                    <?= h(__('claim.guest_name_label')) ?> *
                </label>
                <input class="form-input" type="text" id="suggest_guest_name" name="guest_name"
                       placeholder="<?= h(__('claim.guest_name_placeholder')) ?>"
                       maxlength="100" required>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm" style="margin-bottom:0;">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                <?= h(__('guest.suggest_submit')) ?>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>
