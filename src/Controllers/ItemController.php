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
            flash('error', __('item.name') . ': Maximal 255 Zeichen.');
            redirect(base_url('e/' . $event['admin_token'] . '/manage'));
        }
        if ($quantity < 1 || $quantity > 999) {
            flash('error', __('item.quantity') . ': Wert zwischen 1 und 999 erforderlich.');
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

        flash('success', '"' . $name . '" wurde hinzugefügt.');
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
            flash('error', __('item.quantity') . ': Wert zwischen 1 und 999 erforderlich.');
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
                flash('error', sprintf(
                    'Menge kann nicht auf %d reduziert werden — bereits %d Exemplar(e) beansprucht.',
                    $quantity,
                    $claimed
                ));
                redirect(base_url('e/' . $event['admin_token'] . '/manage'));
            }
        }

        $db->update('items', ['name' => $name, 'quantity' => $quantity], ['id' => $item['id']]);

        flash('success', 'Item aktualisiert.');
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

        flash('success', '"' . $item['name'] . '" wurde gelöscht.');
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

        $db->update('items', ['status' => 'approved'], ['id' => $item['id']]);

        flash('success', '"' . $item['name'] . '" wurde freigegeben.');
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

        $db->delete('items', ['id' => $item['id']]);

        flash('success', 'Vorschlag "' . $item['name'] . '" wurde abgelehnt und gelöscht.');
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
