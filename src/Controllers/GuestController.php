<?php
declare(strict_types=1);

class GuestController
{
    // ------------------------------------------------------------------
    // GET /g/{guest_token}
    // ------------------------------------------------------------------
    public function view(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['guest_token'] ?? '');

        if (!guest_is_unlocked($event)) {
            render('guest/event_locked', [
                'pageTitle' => __('guest.locked_title'),
                'guestToken' => $event['guest_token'],
                'errors'    => [],
            ]);
            return;
        }

        // Expired check
        if ($event['expires_at'] !== null && strtotime($event['expires_at']) < time()) {
            render('guest/event_expired', [
                'pageTitle' => __('guest.event_expired'),
                'event'     => $event,
            ]);
            return;
        }

        // Load approved items ordered by sort_order
        $items = $db->fetchAll(
            'SELECT * FROM items WHERE event_id = ? AND status = ? ORDER BY sort_order ASC, created_at ASC',
            [$event['id'], 'approved']
        );

        // Attach claims and compute totals
        foreach ($items as &$item) {
            $item['claims']       = $db->fetchAll(
                'SELECT * FROM claims WHERE item_id = ? ORDER BY claimed_at ASC',
                [$item['id']]
            );
            $item['claims_total'] = array_sum(array_column($item['claims'], 'quantity'));
            $item['remaining']    = max(0, $item['quantity'] - $item['claims_total']);
        }
        unset($item);

        // Weighted by quantity, not by item count: an item needing 20 and
        // having 5 claimed should move the bar, not wait until all 20 are
        // gone (see GuestController::view / progress calculation notes).
        $progressTotal   = array_sum(array_column($items, 'quantity'));
        $progressClaimed = array_sum(array_map(
            fn($i) => min($i['claims_total'], $i['quantity']),
            $items
        ));

        // Gift Mode: fully-claimed items are hidden from everyone except the
        // guest session that claimed them. There is no guest login, so
        // ownership is tracked per browser session (guest_owns_claim() in
        // bootstrap.php), not per claimed name.
        $heartbeatClaimIds  = [];
        $allClaimedAndHidden = false;
        if (!empty($event['gift_mode'])) {
            $guestToken   = $event['guest_token'];
            $visibleItems = [];
            foreach ($items as $item) {
                if ($item['remaining'] > 0) {
                    $item['gift_state'] = 'open';
                    $visibleItems[]     = $item;
                    continue;
                }
                $ownClaimIds = array_values(array_filter(
                    array_column($item['claims'], 'id'),
                    fn($claimId) => guest_owns_claim($guestToken, $claimId)
                ));
                if (empty($ownClaimIds)) {
                    continue; // fully claimed by someone else: hidden
                }
                $item['gift_state']    = 'own';
                $item['own_claim_ids'] = $ownClaimIds;
                $heartbeatClaimIds     = array_merge($heartbeatClaimIds, $ownClaimIds);
                $visibleItems[]        = $item;
            }
            // Distinguish "no items configured" from "everything got claimed
            // and hidden" so the empty state can show the right message.
            $allClaimedAndHidden = empty($visibleItems) && $progressTotal > 0;
            $items = $visibleItems;
        }

        render('guest/event_view', [
            'pageTitle'            => event_page_title($event),
            'event'                => $event,
            'items'                => $items,
            'progressTotal'        => $progressTotal,
            'progressClaimed'      => $progressClaimed,
            'heartbeatClaimIds'    => $heartbeatClaimIds,
            'allClaimedAndHidden'  => $allClaimedAndHidden,
        ]);
    }

    // ------------------------------------------------------------------
    // POST /g/{guest_token}/claim
    // ------------------------------------------------------------------
    public function claim(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['guest_token'] ?? '');
        $this->denyIfLocked($event);
        $this->denyIfExpired($event);

        if (!csrf_verify()) {
            abort(403);
        }

        $guestToken = $event['guest_token'];

        if (!rate_limit_check('claim', 30, 60)) {
            flash('error', __('validation.rate_limit'));
            redirect(base_url('g/' . $guestToken));
        }
        $itemId     = (int) ($_POST['item_id']    ?? 0);
        $guestName  = trim($_POST['guest_name']   ?? '');
        $quantity   = max(1, (int) ($_POST['quantity'] ?? 1));

        // Validate guest name
        if ($guestName === '') {
            flash('error', __('validation.required') . ' (' . __('claim.guest_name_label') . ')');
            redirect(base_url('g/' . $guestToken));
        }
        if (mb_strlen($guestName) > 100) {
            $guestName = mb_substr($guestName, 0, 100);
        }

        // Load and verify item belongs to this event with status approved
        $item = $db->fetch(
            'SELECT * FROM items WHERE id = ? AND event_id = ? AND status = ?',
            [$itemId, $event['id'], 'approved']
        );
        if (!$item) {
            flash('error', __('claim.already_claimed'));
            redirect(base_url('g/' . $guestToken));
        }

        $isMultiPersonGift = !empty($event['gift_mode']) && $item['quantity'] > 1;

        // Gift Mode: one claim = one person's slot on multi-person items, so the
        // "X of Y people" progress stays accurate regardless of what the client sent.
        if ($isMultiPersonGift) {
            $quantity = 1;
        }

        // Cap requested quantity to what is actually possible
        if ($quantity > $item['quantity']) {
            $quantity = $item['quantity'];
        }

        // Gift Mode opt-in notification e-mail (multi-person items only)
        $notificationEmail = null;
        if ($isMultiPersonGift) {
            $rawEmail = trim($_POST['notification_email'] ?? '');
            if ($rawEmail !== '' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                $notificationEmail = $rawEmail;
            }
        }

        // --- Transaction with row-level lock to prevent race conditions ---
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        $newClaimId  = null;
        $becameFull  = false;
        try {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(quantity), 0) AS total FROM claims WHERE item_id = ? FOR UPDATE'
            );
            $stmt->execute([$item['id']]);
            $currentTotal = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            if ($currentTotal >= $item['quantity']) {
                $pdo->rollBack();
                flash('error', __('claim.already_claimed'));
                redirect(base_url('g/' . $guestToken));
            }

            // Clamp quantity to remaining space
            $available = $item['quantity'] - $currentTotal;
            if ($quantity > $available) {
                $quantity = $available;
            }

            // finalize_at starts the reconsideration window: the creator's
            // "item claimed" e-mail is deferred until then (see
            // process_due_claim_notifications() in bootstrap.php), giving the
            // guest time to undo via undoClaim() before the organiser is told.
            // Delay length is superadmin-configurable, see claim_finalize_delay_seconds().
            $finalizeAt = date('Y-m-d H:i:s', time() + claim_finalize_delay_seconds());
            $pdo->prepare(
                'INSERT INTO claims (item_id, guest_name, quantity, notification_email, finalize_at) VALUES (?, ?, ?, ?, ?)'
            )->execute([$item['id'], $guestName, $quantity, $notificationEmail, $finalizeAt]);
            $newClaimId = (int) $pdo->lastInsertId();
            $becameFull = ($currentTotal + $quantity) >= $item['quantity'];

            $pdo->commit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', __('claim.race_condition'));
            redirect(base_url('g/' . $guestToken));
        }

        guest_register_claim($guestToken, $newClaimId);

        rate_limit_record('claim');
        flash('success', __('claim.success'));
        if ($event['notify_on_claim'] && !empty($event['creator_email'])) {
            flash('info', __('claim.success_notify_hint', [
                'minutes' => (int) round(claim_finalize_delay_seconds() / 60),
            ]));
        }

        // Gift Mode: item just became fully claimed — send the opt-in
        // coordination e-mail to everyone who provided one (best-effort).
        // This is a separate, unrelated notification (to co-claimers, not the
        // creator) and is intentionally still sent immediately.
        if ($isMultiPersonGift && $becameFull) {
            @gift_mode_send_coordination_emails($event, $item);
        }

        // The creator's "item claimed"/"list complete" e-mails are NOT sent
        // here — they're deferred until the claim's reconsideration window
        // elapses (see finalize_at above and process_due_claim_notifications()
        // in bootstrap.php).

        redirect(base_url('g/' . $guestToken));
    }

    // ------------------------------------------------------------------
    // POST /g/{guest_token}/suggest
    // ------------------------------------------------------------------
    public function suggest(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['guest_token'] ?? '');
        $this->denyIfLocked($event);
        $this->denyIfExpired($event);

        if (!$event['guests_can_suggest']) {
            abort(403);
        }

        if (!csrf_verify()) {
            abort(403);
        }

        $guestToken = $event['guest_token'];

        if (!rate_limit_check('suggest', 10, 3600)) {
            flash('error', __('validation.rate_limit'));
            redirect(base_url('g/' . $guestToken));
        }
        $name       = trim($_POST['name']       ?? '');
        $guestName  = trim($_POST['guest_name'] ?? '');

        if ($name === '') {
            flash('error', __('validation.required') . ' (' . __('guest.suggest_name') . ')');
            redirect(base_url('g/' . $guestToken));
        }
        if ($guestName === '') {
            flash('error', __('validation.required') . ' (' . __('claim.guest_name_label') . ')');
            redirect(base_url('g/' . $guestToken));
        }

        $name      = mb_substr($name, 0, 255);
        $guestName = mb_substr($guestName, 0, 100);

        // Get next sort_order
        $maxOrder  = $db->fetch(
            'SELECT MAX(sort_order) AS max_order FROM items WHERE event_id = ?',
            [$event['id']]
        );
        $sortOrder = (int) ($maxOrder['max_order'] ?? 0) + 1;

        $db->insert('items', [
            'event_id'           => $event['id'],
            'name'               => $name,
            'quantity'           => 1,
            'sort_order'         => $sortOrder,
            'status'             => 'pending',
            'suggested_by_guest' => $guestName,
        ]);

        rate_limit_record('suggest');

        // Notify organiser about new suggestion (best-effort)
        if (!empty($event['creator_email'])) {
            $emailSettings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
            $mailer        = new Mailer($emailSettings);
            $html = Mailer::renderTemplate('item_suggested', [
                'event'    => $event,
                'item'     => ['name' => $name, 'suggested_by_guest' => $guestName],
                'adminUrl' => base_url('e/' . $event['admin_token'] . '/manage'),
            ]);
            @$mailer->send(
                $event['creator_email'],
                'Neuer Vorschlag: ' . $name,
                $html
            );
        }

        flash('success', __('guest.suggest_success'));
        redirect(base_url('g/' . $guestToken));
    }

    // ------------------------------------------------------------------
    // POST /g/{guest_token}/claim/{claim_id}/undo
    // Both modes: lets a guest release their own claim at any time (Gift
    // Mode: their own fully-claimed slot). This never blocks on
    // finalize_at — the reconsideration window only delays the creator
    // e-mail, not the ability to change your mind.
    // ------------------------------------------------------------------
    public function undoClaim(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['guest_token'] ?? '');
        $this->denyIfLocked($event);
        $this->denyIfExpired($event);

        if (!csrf_verify()) {
            abort(403);
        }

        $guestToken = $event['guest_token'];

        $claimId = (int) ($params['claim_id'] ?? 0);

        $claim = $db->fetch(
            'SELECT c.* FROM claims c
             INNER JOIN items i ON i.id = c.item_id
             WHERE c.id = ? AND i.event_id = ?',
            [$claimId, $event['id']]
        );
        if (!$claim) {
            abort(404);
        }

        // Session-bound ownership: only the guest who created the claim may undo it.
        if (!guest_owns_claim($guestToken, $claimId)) {
            abort(403);
        }

        $item = $db->fetch('SELECT * FROM items WHERE id = ?', [$claim['item_id']]);

        // Gift Mode only: heuristic for "the other co-claimers were already
        // notified" — the coordination mail is sent synchronously exactly
        // when the item first reaches remaining=0 (see claim()), so an
        // already-full multi-person item at undo time means it almost
        // certainly already went out.
        $totalRow           = $db->fetch(
            'SELECT COALESCE(SUM(quantity), 0) AS total FROM claims WHERE item_id = ?',
            [$claim['item_id']]
        );
        $coClaimersNotified = !empty($event['gift_mode']) && $item && $item['quantity'] > 1
            && ((int) ($totalRow['total'] ?? 0)) >= $item['quantity'];

        // Both modes, exact (not a heuristic): whether the creator's "item
        // claimed" e-mail already went out for this specific claim.
        $creatorNotified = $claim['creator_notified_at'] !== null;

        // If the creator already knows about this claim, tell them it was
        // withdrawn again too — coupled to the same notify_on_claim setting
        // (no separate toggle), best-effort like every other notification here.
        if ($creatorNotified && $event['notify_on_claim'] && !empty($event['creator_email']) && $item) {
            $emailSettings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
            $mailer        = new Mailer($emailSettings);
            $html          = Mailer::renderTemplate('claim_withdrawn', [
                'event'        => $event,
                'item'         => $item,
                'claim'        => $claim,
                'adminUrl'     => base_url('e/' . $event['admin_token'] . '/manage'),
                'primaryColor' => primary_color(),
            ]);
            @$mailer->send(
                $event['creator_email'],
                $claim['guest_name'] . ' hat die Übernahme von ' . $item['name'] . ' zurückgezogen',
                $html
            );
        }

        $db->delete('claims', ['id' => $claimId]);
        guest_forget_claim($guestToken, $claimId);

        flash('success', __('claim.undo_success'));
        if ($coClaimersNotified) {
            flash('warning', __('claim.undo_notified_hint'));
        }
        if ($creatorNotified) {
            flash('warning', __('claim.undo_creator_notified_hint'));
        }

        redirect(base_url('g/' . $guestToken));
    }

    // ------------------------------------------------------------------
    // POST /g/{guest_token}/heartbeat
    // Gift Mode only: presence signal from a guest who currently owns one or
    // more fully-claimed items, sent via fetch()/sendBeacon() as JSON.
    // ------------------------------------------------------------------
    public function heartbeat(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['guest_token'] ?? '');

        if (empty($event['gift_mode']) || !guest_is_unlocked($event)) {
            http_response_code(204);
            return;
        }
        if ($event['expires_at'] !== null && strtotime($event['expires_at']) < time()) {
            http_response_code(204);
            return;
        }

        $guestToken = $event['guest_token'];
        $body       = json_decode((string) file_get_contents('php://input'), true);
        $body       = is_array($body) ? $body : [];

        // CSRF: fetch()/sendBeacon() send a JSON body, not $_POST, so verify manually.
        $token = (string) ($body['csrf_token'] ?? '');
        if ($token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            return;
        }

        if (!rate_limit_check('heartbeat', 6, 10)) {
            http_response_code(204);
            return;
        }
        rate_limit_record('heartbeat');

        $requestedIds = array_map('intval', (array) ($body['claim_ids'] ?? []));
        $ownedIds     = array_values(array_intersect(
            $requestedIds,
            guest_my_claim_ids_for_event($guestToken)
        ));

        if (!empty($ownedIds)) {
            $placeholders = implode(',', array_fill(0, count($ownedIds), '?'));
            $params2      = array_merge($ownedIds, [$event['id']]);
            $isLeave      = ($body['action'] ?? '') === 'leave';

            $db->query(
                "UPDATE claims c
                 INNER JOIN items i ON i.id = c.item_id
                 SET c.heartbeat_at = " . ($isLeave ? 'NULL' : 'NOW()') . "
                 WHERE c.id IN ({$placeholders}) AND i.event_id = ?",
                $params2
            );

            if (!$isLeave) {
                // Opportunistic retry for any coordination mail that failed earlier.
                $itemIds = array_unique(array_column(
                    $db->fetchAll(
                        "SELECT DISTINCT item_id FROM claims WHERE id IN ({$placeholders})",
                        $ownedIds
                    ),
                    'item_id'
                ));
                foreach ($itemIds as $itemId) {
                    $item = $db->fetch('SELECT * FROM items WHERE id = ?', [$itemId]);
                    if ($item) {
                        @gift_mode_send_coordination_emails($event, $item);
                    }
                }
            }
        }

        http_response_code(204);
    }

    // ------------------------------------------------------------------
    // POST /g/{guest_token}/unlock
    // ------------------------------------------------------------------
    public function unlock(array $params = []): void
    {
        $event      = $this->loadEvent($params['guest_token'] ?? '');
        $guestToken = $event['guest_token'];

        if (!csrf_verify()) {
            abort(403);
        }

        if (empty($event['access_password_hash'])) {
            redirect(base_url('g/' . $guestToken));
        }

        if (!rate_limit_check('guest_unlock', 20, 900)) {
            render('guest/event_locked', [
                'pageTitle'  => __('guest.locked_title'),
                'guestToken' => $guestToken,
                'errors'     => [__('validation.rate_limit')],
            ]);
            return;
        }

        $password = (string) ($_POST['access_password'] ?? '');

        if ($password !== '' && password_verify($password, $event['access_password_hash'])) {
            $_SESSION['guest_unlocked'][$guestToken] = true;
            redirect(base_url('g/' . $guestToken));
        }

        rate_limit_record('guest_unlock');
        render('guest/event_locked', [
            'pageTitle'  => __('guest.locked_title'),
            'guestToken' => $guestToken,
            'errors'     => [__('guest.locked_wrong_password')],
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function loadEvent(string $guestToken): array
    {
        global $db;
        $event = $db->fetch('SELECT * FROM events WHERE guest_token = ?', [$guestToken]);
        if (!$event) {
            abort(404);
        }
        return $event;
    }

    private function denyIfExpired(array $event): void
    {
        if ($event['expires_at'] !== null && strtotime($event['expires_at']) < time()) {
            flash('error', __('guest.event_expired'));
            redirect(base_url());
        }
    }

    private function denyIfLocked(array $event): void
    {
        if (!guest_is_unlocked($event)) {
            redirect(base_url('g/' . $event['guest_token']));
        }
    }
}
