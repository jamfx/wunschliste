<?php
declare(strict_types=1);

class PdfExportController
{
    // ------------------------------------------------------------------
    // Shared helper: load event + items with claim totals
    // ------------------------------------------------------------------

    private function loadEventWithItems(string $token, bool $byAdmin): array
    {
        global $db;
        $field = $byAdmin ? 'admin_token' : 'guest_token';
        $event = $db->fetch("SELECT * FROM events WHERE {$field} = ?", [$token]);
        if (!$event) {
            abort(404);
        }

        $items = $db->fetchAll(
            'SELECT * FROM items WHERE event_id = ? ORDER BY sort_order ASC, created_at ASC LIMIT 500',
            [$event['id']]
        );

        foreach ($items as &$item) {
            $item['claims']       = $db->fetchAll(
                'SELECT * FROM claims WHERE item_id = ? ORDER BY claimed_at ASC',
                [$item['id']]
            );
            $item['claims_total'] = array_sum(array_column($item['claims'], 'quantity'));
            $item['remaining']    = max(0, $item['quantity'] - $item['claims_total']);
        }
        unset($item);

        return [$event, $items];
    }

    private function getSiteSettings(): array
    {
        global $db;
        $rows = $db->fetchAll('SELECT setting_key, setting_value FROM site_settings');
        $s    = [];
        foreach ($rows as $row) {
            $s[$row['setting_key']] = $row['setting_value'];
        }
        return $s;
    }

    private function streamPdf(string $bytes, string $filename): void
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $bytes;
        exit;
    }

    // ------------------------------------------------------------------
    // GET /e/{admin_token}/pdf  — full admin view (includes pending, names)
    // ------------------------------------------------------------------

    public function adminPdf(array $params = []): void
    {
        global $db;
        $adminToken = $params['admin_token'] ?? '';

        [$event, $items] = $this->loadEventWithItems($adminToken, true);
        require_event_owner_access($event);

        if (!class_exists('Dompdf\Dompdf')) {
            flash('error', 'PDF-Export nicht verfügbar (Composer-Paket dompdf fehlt).');
            redirect(base_url('e/' . $adminToken . '/manage'));
        }

        $s    = $this->getSiteSettings();
        $bytes = PdfExport::generate($event, $items, $s, isAdmin: true);

        $filename = 'event-' . preg_replace('/[^a-z0-9]+/i', '-', $event['title']) . '.pdf';
        $this->streamPdf($bytes, strtolower($filename));
    }

    // ------------------------------------------------------------------
    // GET /g/{guest_token}/pdf  — guest view (no pending, names depend on setting)
    // ------------------------------------------------------------------

    public function guestPdf(array $params = []): void
    {
        global $db;
        $guestToken = $params['guest_token'] ?? '';

        [$event, $items] = $this->loadEventWithItems($guestToken, false);

        if (!guest_is_unlocked($event)) {
            redirect(base_url('g/' . $guestToken));
        }

        // Expired events: still allow PDF (user has the link, let them download)
        if (!class_exists('Dompdf\Dompdf')) {
            flash('error', 'PDF-Export nicht verfügbar (Composer-Paket dompdf fehlt).');
            redirect(base_url('g/' . $guestToken));
        }

        // Filter to approved only for guests
        $approvedItems = array_values(array_filter($items, fn($i) => $i['status'] === 'approved'));

        // Gift Mode: the PDF must respect the same "surprise" hiding as the
        // guest page — otherwise the download link bypasses it entirely.
        if (!empty($event['gift_mode'])) {
            $approvedItems = array_values(array_filter($approvedItems, function (array $item) use ($guestToken) {
                if ($item['remaining'] > 0) {
                    return true;
                }
                foreach ($item['claims'] as $claim) {
                    if (guest_owns_claim($guestToken, $claim['id'])) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $s    = $this->getSiteSettings();
        $bytes = PdfExport::generate($event, $approvedItems, $s, isAdmin: false);

        $filename = 'mitbringliste-' . preg_replace('/[^a-z0-9]+/i', '-', $event['title']) . '.pdf';
        $this->streamPdf($bytes, strtolower($filename));
    }
}
