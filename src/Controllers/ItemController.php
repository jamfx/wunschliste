<?php
declare(strict_types=1);

class ItemController
{
    // ------------------------------------------------------------------
    // POST /e/{admin_token}/items
    // ------------------------------------------------------------------
    public function store(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['admin_token'] ?? '');
        require_event_owner_access($event);
        if (!csrf_verify()) {
            abort(403);
        }

        $name     = trim($_POST['name']     ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 1);

        if ($name === '') {
            flash('error', __('validation.required') . ' (' . __('item.name') . ')');
            redirect(base_url('e/' . $event['admin_token'] . '/manage'));
        }
        if (mb_strlen($name) > 255) {
            flash('error', __('validation.max_length', ['field' => __('item.name'), 'max' => 255]));
            redirect(base_url('e/' . $event['admin_token'] . '/manage'));
        }
        if ($quantity < 1 || $quantity > 999) {
            flash('error', __('validation.range', ['field' => __('item.quantity'), 'min' => 1, 'max' => 999]));
            redirect(base_url('e/' . $event['admin_token'] . '/manage'));
        }

        $maxOrder = $db->fetch(
            'SELECT MAX(sort_order) AS max_order FROM items WHERE event_id = ?',
            [$event['id']]
        );
        $sortOrder = (int) ($maxOrder['max_order'] ?? 0) + 1;

        $db->insert('items', [
            'event_id'   => $event['id'],
            'name'       => $name,
            'quantity'   => $quantity,
            'sort_order' => $sortOrder,
            'status'     => 'approved',
        ]);

        flash('success', __('item.added', ['name' => $name]));
        redirect(base_url('e/' . $event['admin_token'] . '/manage'));
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/items/{id}/update
    // ------------------------------------------------------------------
    public function update(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['admin_token'] ?? '');
        require_event_owner_access($event);
        $item  = $this->loadItem((int) ($params['id'] ?? 0), $event['id']);

        if (!csrf_verify()) {
            abort(403);
        }

        $name     = trim($_POST['name']     ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 1);

        if ($name === '' || mb_strlen($name) > 255) {
            flash('error', __('validation.required') . ' (' . __('item.name') . ')');
            redirect(base_url('e/' . $event['admin_token'] . '/manage'));
        }
        if ($quantity < 1 || $quantity > 999) {
            flash('error', __('validation.range', ['field' => __('item.quantity'), 'min' => 1, 'max' => 999]));
            redirect(base_url('e/' . $event['admin_token'] . '/manage'));
        }

        // Guard: reducing quantity must not drop below already-claimed total
        if ($quantity < $item['quantity']) {
            $claimedRow = $db->fetch(
                'SELECT COALESCE(SUM(quantity), 0) AS total FROM claims WHERE item_id = ?',
                [$item['id']]
            );
            $claimed = (int) ($claimedRow['total'] ?? 0);
            if ($claimed > $quantity) {
                flash('error', __('item.quantity_below_claimed', ['quantity' => $quantity, 'claimed' => $claimed]));
                redirect(base_url('e/' . $event['admin_token'] . '/manage'));
            }
        }

        $db->update('items', ['name' => $name, 'quantity' => $quantity], ['id' => $item['id']]);

        flash('success', __('item.updated'));
        redirect(base_url('e/' . $event['admin_token'] . '/manage'));
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/items/{id}/delete
    // ------------------------------------------------------------------
    public function delete(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['admin_token'] ?? '');
        require_event_owner_access($event);
        $item  = $this->loadItem((int) ($params['id'] ?? 0), $event['id']);

        if (!csrf_verify()) {
            abort(403);
        }

        $db->delete('items', ['id' => $item['id']]);

        flash('success', __('item.deleted', ['name' => $item['name']]));
        redirect(base_url('e/' . $event['admin_token'] . '/manage'));
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/items/{id}/approve
    // ------------------------------------------------------------------
    public function approve(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['admin_token'] ?? '');
        require_event_owner_access($event);
        $item  = $this->loadItem((int) ($params['id'] ?? 0), $event['id']);

        if (!csrf_verify()) {
            abort(403);
        }

        $quantity = (int) ($_POST['quantity'] ?? $item['quantity']);
        if ($quantity < 1 || $quantity > 999) {
            flash('error', __('validation.range', ['field' => __('item.quantity'), 'min' => 1, 'max' => 999]));
            redirect(base_url('e/' . $event['admin_token'] . '/manage'));
        }

        $wasPending = ($item['status'] === 'pending');
        $db->update('items', ['status' => 'approved', 'quantity' => $quantity], ['id' => $item['id']]);

        // The suggester pledged to bring it: becomes a claim of min(pledge, final quantity).
        $claimed = 0;
        if ($wasPending && (int) ($item['suggester_brings_qty'] ?? 0) > 0) {
            $claimed = $this->createSuggesterClaim($event, $item, $quantity);
        }

        $this->notifySuggester($event, array_merge($item, ['quantity' => $quantity]), true, $claimed);

        if ($claimed > 0) {
            flash('success', __('item.approved_with_claim', [
                'name' => $item['name'], 'guest' => $item['suggested_by_guest'], 'quantity' => $claimed,
            ]));
        } else {
            flash('success', __('item.approved', ['name' => $item['name']]));
        }
        redirect(base_url('e/' . $event['admin_token'] . '/manage'));
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/claims/{id}/delete
    // Organiser removes a single claim (e.g. a pledge that was taken over
    // from a suggestion and can no longer be undone by the guest).
    // ------------------------------------------------------------------
    public function removeClaim(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['admin_token'] ?? '');
        require_event_owner_access($event);

        if (!csrf_verify()) {
            abort(403);
        }

        $claim = $db->fetch(
            'SELECT c.* FROM claims c JOIN items i ON i.id = c.item_id WHERE c.id = ? AND i.event_id = ?',
            [(int) ($params['id'] ?? 0), $event['id']]
        );
        if (!$claim) {
            abort(404);
        }

        $db->delete('claims', ['id' => $claim['id']]);

        flash('success', __('item.claim_removed', ['name' => $claim['guest_name']]));
        redirect(base_url('e/' . $event['admin_token'] . '/manage'));
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/items/{id}/reject
    // ------------------------------------------------------------------
    public function reject(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['admin_token'] ?? '');
        require_event_owner_access($event);
        $item  = $this->loadItem((int) ($params['id'] ?? 0), $event['id']);

        if (!csrf_verify()) {
            abort(403);
        }

        // Notify before deleting; the address is gone with the row.
        $this->notifySuggester($event, $item, false);
        $db->delete('items', ['id' => $item['id']]);

        flash('success', __('item.rejected', ['name' => $item['name']]));
        redirect(base_url('e/' . $event['admin_token'] . '/manage'));
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/items/reorder
    // Persists drag-and-drop reordering from the manage page. Fired via
    // fetch() as a JSON body (not a form submit), so CSRF is verified
    // manually like GuestController::heartbeat().
    // ------------------------------------------------------------------
    public function reorder(array $params = []): void
    {
        global $db;

        $event = $this->loadEvent($params['admin_token'] ?? '');
        if (!has_event_owner_access($event)) {
            http_response_code(403);
            return;
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];

        $token = (string) ($body['csrf_token'] ?? '');
        if ($token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            return;
        }

        $itemIds = array_map('intval', (array) ($body['item_ids'] ?? []));
        if (empty($itemIds)) {
            http_response_code(400);
            return;
        }

        // Only ever touch items that actually belong to this event — never
        // trust the client-supplied id list on its own.
        $ownedIds = array_map('intval', array_column(
            $db->fetchAll('SELECT id FROM items WHERE event_id = ?', [$event['id']]),
            'id'
        ));

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE items SET sort_order = ? WHERE id = ? AND event_id = ?');
            foreach ($itemIds as $position => $id) {
                if (!in_array($id, $ownedIds, true)) {
                    continue;
                }
                $stmt->execute([$position, $id, $event['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            return;
        }

        http_response_code(204);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Create the claim for a suggester who pledged to bring the item. Returns the
     * claimed quantity. Marked as already notified: the organiser just approved
     * it themselves, so no "item claimed" mail is wanted.
     */
    private function createSuggesterClaim(array $event, array $item, int $finalQuantity): int
    {
        global $db;

        $qty = min((int) $item['suggester_brings_qty'], $finalQuantity);
        // Gift mode: a claim is one person's place on a multi-person item.
        if (!empty($event['gift_mode']) && $finalQuantity > 1) {
            $qty = 1;
        }
        if ($qty < 1) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $db->insert('claims', [
            'item_id'             => $item['id'],
            'guest_name'          => (string) $item['suggested_by_guest'],
            'quantity'            => $qty,
            'finalize_at'         => $now,
            'creator_notified_at' => $now,
        ]);
        $db->update('items', ['suggester_brings_qty' => null], ['id' => $item['id']]);

        return $qty;
    }

    /**
     * Tell the suggester (if they left an address) how the organiser decided.
     * Best-effort: a mail failure never blocks the organiser's action. The
     * address is wiped afterwards; on rejection the row is deleted anyway.
     */
    private function notifySuggester(array $event, array $item, bool $approved, int $claimed = 0): void
    {
        global $db;

        $email = trim((string) ($item['suggester_email'] ?? ''));
        if ($email === '') {
            return;
        }

        $note = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 1000);

        $available = available_languages();
        $locale    = (string) ($item['suggester_locale'] ?? '');
        $tr        = new Translator(isset($available[$locale]) ? $locale : 'de');
        $t         = static fn(string $key, array $r = []): string => $tr->t($key, $r);

        try {
            $emailSettings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
            $mailer        = new Mailer($emailSettings);
            $html = Mailer::renderTemplate('suggestion_decision', [
                'event'    => $event,
                'item'     => $item,
                'approved' => $approved,
                'claimed'  => $claimed,
                'note'     => $note,
                'guestUrl' => base_url('g/' . $event['guest_token']),
                't'        => $t,
                'locale'   => $tr->getLocale(),
            ]);
            @$mailer->send(
                $email,
                $t($approved ? 'mail.decision_subject_approved' : 'mail.decision_subject_rejected', ['name' => $item['name']]),
                $html
            );
        } catch (\Throwable $e) {
            error_log('Suggestion notification failed: ' . $e->getMessage());
        }

        if ($approved) {
            $db->update('items', ['suggester_email' => null, 'suggester_locale' => null], ['id' => $item['id']]);
        }
    }

    private function loadEvent(string $adminToken): array
    {
        global $db;
        $event = $db->fetch('SELECT * FROM events WHERE admin_token = ?', [$adminToken]);
        if (!$event) {
            abort(404);
        }
        return $event;
    }

    private function loadItem(int $itemId, int $eventId): array
    {
        global $db;
        $item = $db->fetch(
            'SELECT * FROM items WHERE id = ? AND event_id = ?',
            [$itemId, $eventId]
        );
        if (!$item) {
            abort(404);
        }
        return $item;
    }
}
