<?php
declare(strict_types=1);

class EventController
{
    // ------------------------------------------------------------------
    // GET /
    // ------------------------------------------------------------------
    public function create(array $params = []): void
    {
        if (list_creation_mode() === 'restricted' && current_user_id() === null) {
            $_SESSION['login_redirect'] = current_url();
            flash('error', 'Bitte einloggen, um eine neue Liste zu erstellen.');
            redirect(base_url('admin/login'));
        }

        // Pre-fill the notification email for logged-in users — it's already
        // known, so the "no email = no notifications" hint has no reason to
        // fire. Still just a default: the field stays editable/clearable.
        $old = [];
        $userId = current_user_id();
        if ($userId !== null) {
            global $db;
            $user = $db->fetch('SELECT email FROM users WHERE id = ?', [$userId]);
            if ($user) {
                $old['creator_email'] = $user['email'];
            }
        }

        render('event/create', [
            'pageTitle' => __('event.create_title'),
            'errors'    => [],
            'old'       => $old,
        ]);
    }

    // ------------------------------------------------------------------
    // POST /
    // ------------------------------------------------------------------
    public function store(array $params = []): void
    {
        global $db;

        if (!csrf_verify()) {
            abort(403);
        }

        // Belt-and-suspenders: the GET form already redirects anonymous
        // visitors to login in restricted mode, but a direct POST must be
        // blocked too.
        if (list_creation_mode() === 'restricted' && current_user_id() === null) {
            abort(403);
        }

        $old = [
            'title'              => trim($_POST['title']              ?? ''),
            'description'        => trim($_POST['description']        ?? ''),
            'event_date'         => trim($_POST['event_date']         ?? ''),
            'creator_email'      => trim($_POST['creator_email']      ?? ''),
            'guest_sees_names'   => !empty($_POST['guest_sees_names']),
            'guests_can_suggest' => !empty($_POST['guests_can_suggest']),
            'notify_on_claim'    => !empty($_POST['notify_on_claim']),
            'notify_on_complete' => !empty($_POST['notify_on_complete']),
            'gift_mode'          => !empty($_POST['gift_mode']),
            'auto_delete_days'   => (int) ($_POST['auto_delete_days'] ?? 30),
        ];
        $accessPassword = (string) ($_POST['access_password'] ?? '');

        $errors = $this->validateEventInput($old);

        $passwordError = $this->validateAccessPassword($accessPassword);
        if ($passwordError !== null) {
            $errors[] = $passwordError;
        }

        if (empty($errors)) {
            // Rate limiting: limits configurable in admin security settings
            $rlMax    = max(1, (int) ($db->fetch("SELECT setting_value FROM site_settings WHERE setting_key='rate_event_max_count'")['setting_value'] ?? 10));
            $rlWindow = max(60, (int) ($db->fetch("SELECT setting_value FROM site_settings WHERE setting_key='rate_event_window_minutes'")['setting_value'] ?? 60) * 60);
            if (!rate_limit_check('create_event', $rlMax, $rlWindow)) {
                $errors[] = 'Zu viele Events erstellt. Bitte warte etwas und versuche es erneut.';
            }
        }

        if (!empty($errors)) {
            render('event/create', [
                'pageTitle' => __('event.create_title'),
                'errors'    => $errors,
                'old'       => $old,
            ]);
            return;
        }

        // Generate unique tokens
        do {
            $adminToken = bin2hex(random_bytes(32));
        } while ($db->fetch('SELECT id FROM events WHERE admin_token = ?', [$adminToken]));

        do {
            $guestToken = bin2hex(random_bytes(32));
        } while ($db->fetch('SELECT id FROM events WHERE guest_token = ?', [$guestToken]));

        $autoDeleteDays = max(1, min(365, $old['auto_delete_days']));

        // Base date for expiry: event date (if set) or today.
        // This ensures a party on 2025-12-24 with 30 days isn't deleted before it happens.
        $eventDate = ($old['event_date'] !== '') ? $old['event_date'] : null;
        $expiresAt = date('Y-m-d H:i:s', strtotime(($eventDate ?? 'today') . ' +' . $autoDeleteDays . ' days'));

        $db->insert('events', [
            'admin_token'          => $adminToken,
            'guest_token'          => $guestToken,
            'user_id'              => current_user_id(),
            'title'                => $old['title'],
            'description'          => $old['description'] !== '' ? $old['description'] : null,
            'event_date'           => $eventDate,
            'guest_sees_names'     => $old['guest_sees_names']   ? 1 : 0,
            'guests_can_suggest'   => $old['guests_can_suggest'] ? 1 : 0,
            'notify_on_claim'      => $old['notify_on_claim']    ? 1 : 0,
            'notify_on_complete'   => $old['notify_on_complete'] ? 1 : 0,
            'gift_mode'            => $old['gift_mode']          ? 1 : 0,
            'creator_email'        => $old['creator_email'] !== '' ? $old['creator_email'] : null,
            'auto_delete_days'     => $autoDeleteDays,
            'access_password_hash' => $accessPassword !== '' ? password_hash($accessPassword, PASSWORD_BCRYPT) : null,
            'expires_at'           => $expiresAt,
        ]);

        rate_limit_record('create_event');

        redirect(base_url('event/created/' . $adminToken));
    }

    // ------------------------------------------------------------------
    // GET /event/created/{admin_token}
    // ------------------------------------------------------------------
    public function created(array $params = []): void
    {
        global $db;

        $event = $db->fetch(
            'SELECT * FROM events WHERE admin_token = ?',
            [$params['admin_token'] ?? '']
        );

        if (!$event) {
            abort(404);
        }

        render('event/created_success', [
            'pageTitle' => __('event.created_success'),
            'event'     => $event,
            'adminUrl'  => base_url('e/' . $event['admin_token'] . '/manage'),
            'guestUrl'  => base_url('g/' . $event['guest_token']),
        ]);
    }

    // ------------------------------------------------------------------
    // GET /my/lists — a logged-in user's own lists (any role)
    // ------------------------------------------------------------------
    public function myLists(array $params = []): void
    {
        $userId = current_user_id();
        if ($userId === null) {
            redirect(base_url('admin/login'));
        }

        global $db;

        // Same weighting logic as AdminController::dashboard(), just scoped
        // to the current user's own lists.
        $events = $db->fetchAll(
            'SELECT e.*,
                (SELECT COALESCE(SUM(quantity), 0) FROM items
                 WHERE event_id = e.id AND status = "approved") AS item_count,
                (SELECT COALESCE(SUM(
                    LEAST(i2.quantity, (SELECT COALESCE(SUM(c.quantity), 0)
                                        FROM claims c WHERE c.item_id = i2.id))
                 ), 0)
                 FROM items i2
                 WHERE i2.event_id = e.id AND i2.status = "approved"
                ) AS claimed_count
             FROM events e
             WHERE e.user_id = ?
             ORDER BY e.created_at DESC',
            [$userId]
        );

        $now = time();
        foreach ($events as &$event) {
            $event['is_expired']   = $event['expires_at'] !== null
                                   && strtotime($event['expires_at']) < $now;
            $event['progress_pct'] = $event['item_count'] > 0
                ? round(($event['claimed_count'] / $event['item_count']) * 100)
                : 0;
        }
        unset($event);

        render('event/my_lists', [
            'pageTitle' => 'Meine Listen',
            'events'    => $events,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /e/{admin_token}/manage
    // ------------------------------------------------------------------
    public function manage(array $params = []): void
    {
        global $db;

        $event = $db->fetch(
            'SELECT * FROM events WHERE admin_token = ?',
            [$params['admin_token'] ?? '']
        );

        if (!$event) {
            abort(404);
        }
        require_event_owner_access($event);

        // Load all items for this event
        $allItems = $db->fetchAll(
            'SELECT * FROM items WHERE event_id = ? ORDER BY sort_order ASC, created_at ASC',
            [$event['id']]
        );

        // Attach claims to each item
        foreach ($allItems as &$item) {
            $item['claims']       = $db->fetchAll(
                'SELECT * FROM claims WHERE item_id = ? ORDER BY claimed_at ASC',
                [$item['id']]
            );
            $item['claims_total'] = array_sum(array_column($item['claims'], 'quantity'));
        }
        unset($item);

        $approvedItems = array_values(array_filter($allItems, fn($i) => $i['status'] === 'approved'));
        $pendingItems  = array_values(array_filter($allItems, fn($i) => $i['status'] === 'pending'));

        // Weighted by quantity, not by item count: an item needing 20 and
        // having 5 claimed should move the bar, not wait until all 20 are
        // gone (see GuestController::view for the same calculation).
        $progressTotal   = array_sum(array_column($approvedItems, 'quantity'));
        $progressClaimed = array_sum(array_map(
            fn($i) => min($i['claims_total'], $i['quantity']),
            $approvedItems
        ));

        render('event/manage', [
            'pageTitle'       => event_page_title($event),
            'event'           => $event,
            'approvedItems'   => $approvedItems,
            'pendingItems'    => $pendingItems,
            'progressTotal'   => $progressTotal,
            'progressClaimed' => $progressClaimed,
            'guestUrl'        => base_url('g/' . $event['guest_token']),
        ]);
    }

    // ------------------------------------------------------------------
    // GET /e/{admin_token}/edit
    // ------------------------------------------------------------------
    public function editForm(array $params = []): void
    {
        global $db;

        $event = $db->fetch(
            'SELECT * FROM events WHERE admin_token = ?',
            [$params['admin_token'] ?? '']
        );

        if (!$event) {
            abort(404);
        }
        require_event_owner_access($event);

        render('event/edit', [
            'pageTitle' => event_page_title($event),
            'event'     => $event,
            'errors'    => [],
            'old'       => [],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/edit
    // ------------------------------------------------------------------
    public function update(array $params = []): void
    {
        global $db;

        if (!csrf_verify()) {
            abort(403);
        }

        $adminToken = $params['admin_token'] ?? '';
        $event = $db->fetch(
            'SELECT * FROM events WHERE admin_token = ?',
            [$adminToken]
        );

        if (!$event) {
            abort(404);
        }
        require_event_owner_access($event);

        $old = [
            'title'              => trim($_POST['title']              ?? ''),
            'description'        => trim($_POST['description']        ?? ''),
            'event_date'         => trim($_POST['event_date']         ?? ''),
            'creator_email'      => trim($_POST['creator_email']      ?? ''),
            'guest_sees_names'   => !empty($_POST['guest_sees_names']),
            'guests_can_suggest' => !empty($_POST['guests_can_suggest']),
            'notify_on_claim'    => !empty($_POST['notify_on_claim']),
            'notify_on_complete' => !empty($_POST['notify_on_complete']),
            'gift_mode'          => !empty($_POST['gift_mode']),
            'auto_delete_days'   => (int) ($_POST['auto_delete_days'] ?? 30),
        ];
        $accessPassword       = (string) ($_POST['access_password'] ?? '');
        $removeAccessPassword = !empty($_POST['remove_access_password']);

        $errors = $this->validateEventInput($old);

        // Only validate the password if it's actually being set (blank = keep current).
        if (!$removeAccessPassword && $accessPassword !== '') {
            $passwordError = $this->validateAccessPassword($accessPassword);
            if ($passwordError !== null) {
                $errors[] = $passwordError;
            }
        }

        if (!empty($errors)) {
            render('event/edit', [
                'pageTitle' => event_page_title($event),
                'event'     => $event,
                'errors'    => $errors,
                'old'       => $old,
            ]);
            return;
        }

        $autoDeleteDays = max(1, min(365, $old['auto_delete_days']));
        $eventDate = ($old['event_date'] !== '') ? $old['event_date'] : null;

        // Base for expires_at: new event date if set, else original creation date
        $baseDate  = $eventDate ?? date('Y-m-d', strtotime($event['created_at']));
        $expiresAt = date('Y-m-d H:i:s', strtotime($baseDate . ' +' . $autoDeleteDays . ' days'));

        // Password: remove wins, then a freshly-typed one, otherwise keep the existing hash unchanged.
        if ($removeAccessPassword) {
            $accessPasswordHash = null;
        } elseif ($accessPassword !== '') {
            $accessPasswordHash = password_hash($accessPassword, PASSWORD_BCRYPT);
        } else {
            $accessPasswordHash = $event['access_password_hash'];
        }

        $db->update('events', [
            'title'                => $old['title'],
            'description'          => $old['description'] !== '' ? $old['description'] : null,
            'event_date'           => $eventDate,
            'creator_email'        => $old['creator_email'] !== '' ? $old['creator_email'] : null,
            'guest_sees_names'     => $old['guest_sees_names']   ? 1 : 0,
            'guests_can_suggest'   => $old['guests_can_suggest'] ? 1 : 0,
            'notify_on_claim'      => $old['notify_on_claim']    ? 1 : 0,
            'notify_on_complete'   => $old['notify_on_complete'] ? 1 : 0,
            'gift_mode'            => $old['gift_mode']          ? 1 : 0,
            'auto_delete_days'     => $autoDeleteDays,
            'access_password_hash' => $accessPasswordHash,
            'expires_at'           => $expiresAt,
        ], ['admin_token' => $adminToken]);

        flash('success', __('event.edit_success'));
        redirect(base_url('e/' . $adminToken . '/manage'));
    }

    // ------------------------------------------------------------------
    // POST /e/{admin_token}/delete
    // ------------------------------------------------------------------
    public function delete(array $params = []): void
    {
        global $db;

        if (!csrf_verify()) {
            abort(403);
        }

        $adminToken = $params['admin_token'] ?? '';
        $event = $db->fetch('SELECT * FROM events WHERE admin_token = ?', [$adminToken]);

        if (!$event) {
            abort(404);
        }
        require_event_owner_access($event);

        // Cascades to items and claims via FK ON DELETE CASCADE.
        $db->delete('events', ['id' => $event['id']]);

        flash('success', __('event.delete_success'));
        redirect(base_url());
    }

    // ------------------------------------------------------------------
    // Validation helper
    // ------------------------------------------------------------------
    private function validateEventInput(array $data): array
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors[] = __('validation.required') . ' (' . __('event.title_label') . ')';
        } elseif (mb_strlen($data['title']) > 255) {
            $errors[] = __('event.title_label') . ': Maximal 255 Zeichen.';
        }

        if (mb_strlen($data['description'] ?? '') > 5000) {
            $errors[] = __('event.description_label') . ': Maximal 5000 Zeichen.';
        }

        if ($data['creator_email'] !== '' && !filter_var($data['creator_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('validation.email');
        }

        if ($data['event_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['event_date'])) {
            $errors[] = __('event.date_label') . ': Ungültiges Datumsformat.';
        }

        $days = $data['auto_delete_days'];
        if ($days < 1 || $days > 365) {
            $errors[] = __('event.auto_delete_days') . ': Wert zwischen 1 und 365 erforderlich.';
        }

        return $errors;
    }

    /** Returns an error message if the (non-empty) access password is invalid, otherwise null. */
    private function validateAccessPassword(string $password): ?string
    {
        if ($password === '') {
            return null;
        }
        $len = mb_strlen($password);
        if ($len < 4 || $len > 50) {
            return __('event.access_password_label') . ': ' . __('validation.length_range', ['min' => 4, 'max' => 50]);
        }
        return null;
    }
}
