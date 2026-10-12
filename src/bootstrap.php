<?php
declare(strict_types=1);

// BASE_DIR = project root (one level above src/)
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_DIR . '/config/config.php');
}

// Bump on every release (see UPGRADE.md); shown in the public footer.
define('APP_VERSION', '1.1.0');

// Gift Mode (Geschenkmodus) tuning constants
define('GIFT_MODE_HEARTBEAT_INTERVAL_MS', 30000);  // JS heartbeat interval
define('GIFT_MODE_HEARTBEAT_TIMEOUT', 120);         // seconds until a guest counts as "gone"
define('GIFT_MODE_NOTIFICATION_MAX_ATTEMPTS', 3);   // coordination-mail retry cap

// Load configuration if it exists
$config = null;
if (file_exists(CONFIG_PATH)) {
    require CONFIG_PATH;
}

// Composer autoloader (endroid/qr-code etc.)
$_composerAutoload = BASE_DIR . '/vendor/autoload.php';
if (file_exists($_composerAutoload)) {
    require_once $_composerAutoload;
}
unset($_composerAutoload);

require_once BASE_DIR . '/src/Database.php';
require_once BASE_DIR . '/src/Router.php';
require_once BASE_DIR . '/src/Translator.php';
require_once BASE_DIR . '/src/Totp.php';
require_once BASE_DIR . '/src/Mailer.php';
require_once BASE_DIR . '/src/PdfExport.php';

// Production hardening
@ini_set('display_errors', '0');
@ini_set('expose_php', '0');
error_reporting(0);

// Prevent caching of dynamic HTML (ensures DB-driven CSS variables are always fresh)
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Security HTTP headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 0');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'");

// Secure session setup
$_isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $_isHttps,
    ]);
    session_start();
}
unset($_isHttps);

// CSRF token (generated once per session)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection (only if config is loaded)
$db = null;
if ($config !== null) {
    try {
        $db = new Database($config);
    } catch (PDOException) {
        $db = null;
    }
}

// ---------------------------------------------------------------------------
// Available languages (derived from lang/*.json — no DB table involved)
// ---------------------------------------------------------------------------

/**
 * Returns [locale_code => display_name] for every lang/*.json file present.
 * A new language is added simply by dropping in a new JSON file — no admin UI needed.
 */
function available_languages(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // Native display names for common locale codes; unknown codes fall back to their uppercased code.
    $names = [
        'de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español',
        'it' => 'Italiano', 'nl' => 'Nederlands', 'pl' => 'Polski', 'pt' => 'Português',
        'tr' => 'Türkçe', 'ru' => 'Русский', 'uk' => 'Українська', 'cs' => 'Čeština',
        'da' => 'Dansk', 'sv' => 'Svenska', 'no' => 'Norsk', 'fi' => 'Suomi',
        'ro' => 'Română', 'hu' => 'Magyar', 'el' => 'Ελληνικά', 'ar' => 'العربية',
        'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어',
    ];

    $cache = [];
    foreach (glob(BASE_DIR . '/lang/*.json') ?: [] as $file) {
        $code         = basename($file, '.json');
        $cache[$code] = $names[$code] ?? strtoupper($code);
    }
    ksort($cache);

    return $cache;
}

// ---------------------------------------------------------------------------
// Locale detection + Translator
// ---------------------------------------------------------------------------

$availableLocales = array_keys(available_languages());
$locale            = 'de'; // ultimate fallback

if ($db !== null) {
    try {
        $row = $db->fetch(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'default_language' LIMIT 1"
        );
        if ($row && in_array($row['setting_value'], $availableLocales, true)) {
            $locale = $row['setting_value'];
        }
    } catch (PDOException) {
        // Use fallback
    }
}

// Cookie overrides the configured default
if (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], $availableLocales, true)) {
    $locale = $_COOKIE['lang'];
}

// GET parameter overrides cookie + sets it for 30 days
if (!empty($_GET['lang']) && in_array($_GET['lang'], $availableLocales, true)) {
    $locale = $_GET['lang'];
    setcookie('lang', $locale, [
        'expires'  => time() + 30 * 86400,
        'path'     => '/',
        'httponly' => false, // JS may read for switcher hints
        'samesite' => 'Lax',
    ]);
}

$translator = new Translator($locale);

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Translate a key.
 * Returns the key itself when no translation is found.
 * Output is NOT HTML-escaped — use h(__('key')) in templates.
 */
function __(string $key, array $replacements = []): string
{
    global $translator;
    return $translator->t($key, $replacements);
}

function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Renders a restricted Markdown subset to safe HTML.
 *
 * Supported: **bold**, *italic* / _italic_, [text](https://…), bare http(s)
 * URLs, "- " / "* " bullet lists, "1. " numbered lists, paragraphs and line
 * breaks. Everything else — including raw HTML, images and other URL schemes —
 * stays plain (escaped) text. Input is escaped FIRST, so no user-supplied
 * markup can survive.
 */
function md(string $text): string
{
    $text = str_replace(["\0", "\r\n", "\r"], ['', "\n", "\n"], trim($text));
    if ($text === '') {
        return '';
    }

    $inline = static function (string $s): string {
        $stash = [];
        $keep = static function (string $html) use (&$stash): string {
            $stash[] = $html;
            return "\0" . (count($stash) - 1) . "\0";
        };
        $a = static fn(string $url, string $label): string =>
            '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $label . '</a>';
        $urlChars = '(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<>()])+';

        // [text](https://…)
        $s = preg_replace_callback(
            '~\[([^\]\n]+)\]\((https?://' . $urlChars . ')\)~i',
            static fn($m) => $keep($a($m[2], $m[1])),
            $s
        );
        // bare URLs (trailing punctuation stays outside the link)
        $s = preg_replace_callback(
            '~(?<![\w"=/])https?://' . $urlChars . '~i',
            static function ($m) use ($keep, $a) {
                $url = rtrim($m[0], '.,;:!?');
                return $keep($a($url, $url)) . substr($m[0], strlen($url));
            },
            $s
        );

        $s = preg_replace('~\*\*(?=\S)(.+?)(?<=\S)\*\*~s', '<strong>$1</strong>', $s);
        $s = preg_replace('~(?<![\w*])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![\w*])~s', '<em>$1</em>', $s);
        $s = preg_replace('~(?<![\w_])_(?=[^\s_])(.+?)(?<=[^\s_])_(?![\w_])~s', '<em>$1</em>', $s);

        return preg_replace_callback('~\0(\d+)\0~', static fn($m) => $stash[(int) $m[1]], $s);
    };

    $out = '';
    $para = [];
    $list = [];
    $listTag = '';

    $flushPara = static function () use (&$para, &$out, $inline): void {
        if ($para) {
            $out .= '<p>' . implode('<br>', array_map($inline, $para)) . '</p>';
            $para = [];
        }
    };
    $flushList = static function () use (&$list, &$listTag, &$out, $inline): void {
        if ($list) {
            $out .= "<$listTag>" . implode('', array_map(static fn($i) => '<li>' . $inline($i) . '</li>', $list)) . "</$listTag>";
            $list = [];
            $listTag = '';
        }
    };

    foreach (explode("\n", $text) as $line) {
        $line = h(rtrim($line));
        if (trim($line) === '') {
            $flushPara();
            $flushList();
        } elseif (preg_match('~^\s{0,3}[-*]\s+(.+)$~', $line, $m)) {
            $flushPara();
            if ($listTag !== 'ul') {
                $flushList();
                $listTag = 'ul';
            }
            $list[] = $m[1];
        } elseif (preg_match('~^\s{0,3}\d{1,3}[.)]\s+(.+)$~', $line, $m)) {
            $flushPara();
            if ($listTag !== 'ol') {
                $flushList();
                $listTag = 'ol';
            }
            $list[] = $m[1];
        } else {
            $flushList();
            $para[] = $line;
        }
    }
    $flushPara();
    $flushList();

    return $out;
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $url, int $code = 302): void
{
    http_response_code($code);
    header('Location: ' . $url);
    exit;
}

function base_url(string $path = ''): string
{
    global $config;
    if (!empty($config['app_url'])) {
        return rtrim($config['app_url'], '/') . '/' . ltrim($path, '/');
    }
    // Fallback: auto-detect scheme, host and subdirectory from current request.
    // SCRIPT_NAME is e.g. /subdir/index.php → dirname gives /subdir.
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script   = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = ($script !== '') ? rtrim(dirname($script), '/\\') : '';
    return $scheme . '://' . $host . $basePath . '/' . ltrim($path, '/');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * The site's configured display name (site_settings.site_title), falling
 * back to "Mitbringliste" if unset. Used everywhere the app refers to
 * itself by name — e.g. e-mail templates and the "From" name — so a
 * renamed instance doesn't keep sending mail under the wrong brand.
 */
function site_title(): string
{
    global $db;
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = 'Mitbringliste';
    if ($db !== null) {
        try {
            $row = $db->fetch(
                "SELECT setting_value FROM site_settings WHERE setting_key = 'site_title' LIMIT 1"
            );
            if (!empty($row['setting_value'])) {
                $cached = $row['setting_value'];
            }
        } catch (PDOException) {
            // keep default
        }
    }
    return $cached;
}

/** The logged-in user's id, or null if nobody is logged in (any role). */
function current_user_id(): ?int
{
    return !empty($_SESSION['user_logged_in']) ? (int) ($_SESSION['user_id'] ?? 0) : null;
}

/**
 * Whether creating a new list requires being logged in ('restricted') or is
 * open to anyone ('open', the default — preserves pre-multi-user behaviour
 * for existing installs). Configurable in admin security settings.
 */
function list_creation_mode(): string
{
    global $db;
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = 'open';
    if ($db !== null) {
        try {
            $row = $db->fetch(
                "SELECT setting_value FROM site_settings WHERE setting_key = 'list_creation_mode' LIMIT 1"
            );
            if (($row['setting_value'] ?? '') === 'restricted') {
                $cached = 'restricted';
            }
        } catch (PDOException) {
            // keep default
        }
    }
    return $cached;
}

/**
 * True if the current session may manage the given event: either the event
 * has no owner (a legacy, pre-multi-user list — the admin_token link alone
 * remains sufficient, unchanged from before), or the session is logged in
 * as that owner, or as the SuperAdmin.
 */
function has_event_owner_access(array $event): bool
{
    if ($event['user_id'] === null) {
        return true;
    }
    if (current_user_id() === (int) $event['user_id']) {
        return true;
    }
    return ($_SESSION['user_role'] ?? '') === 'superadmin';
}

/** Same rule as has_event_owner_access(), but redirects to login instead of returning a bool — for normal page routes. */
function require_event_owner_access(array $event): void
{
    if (has_event_owner_access($event)) {
        return;
    }
    $_SESSION['login_redirect'] = current_url();
    flash('error', 'Bitte einloggen, um diese Liste zu verwalten.');
    redirect(base_url('admin/login'));
}

/**
 * The site's configured brand colour (site_settings.primary_color), falling
 * back to the app default #4F46E5 if unset or invalid. Used both for the
 * front-end CSS variable (templates/layout.php) and for e-mail templates
 * that should reflect the admin's current design instead of a fixed colour.
 */
function primary_color(): string
{
    global $db;
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = '#4F46E5';
    if ($db !== null) {
        try {
            $row = $db->fetch(
                "SELECT setting_value FROM site_settings WHERE setting_key = 'primary_color' LIMIT 1"
            );
            if (!empty($row['setting_value']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $row['setting_value'])) {
                $cached = $row['setting_value'];
            }
        } catch (PDOException) {
            // keep default
        }
    }
    return $cached;
}

/**
 * Claim reconsideration window in seconds (applies to both normal list mode
 * and Gift Mode): the creator's "item claimed" e-mail is delayed until this
 * long after the claim, so the guest has time to change their mind via the
 * undo button (see process_due_claim_notifications()). Configurable by the
 * superadmin under /admin/settings/security (site_settings.claim_finalize_delay_minutes),
 * falling back to 10 minutes if unset. Only affects claims made from now on —
 * already-pending claims keep the finalize_at computed when they were created.
 */
function claim_finalize_delay_seconds(): int
{
    global $db;
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $minutes = 10;
    if ($db !== null) {
        try {
            $row = $db->fetch(
                "SELECT setting_value FROM site_settings WHERE setting_key = 'claim_finalize_delay_minutes' LIMIT 1"
            );
            if ($row && $row['setting_value'] !== '') {
                $minutes = max(1, min(1440, (int) $row['setting_value']));
            }
        } catch (PDOException) {
            // keep default
        }
    }
    $cached = $minutes * 60;
    return $cached;
}

/** Browser-tab title for an event page: "Titel am TT.MM.JJJJ" (date omitted if unset). */
function event_page_title(array $event): string
{
    $title = $event['title'];
    if (!empty($event['event_date'])) {
        $title .= ' am ' . date('d.m.Y', strtotime($event['event_date']));
    }
    return $title;
}

/** Whether the current browser session has unlocked this event's optional access password. */
function guest_is_unlocked(array $event): bool
{
    if (empty($event['access_password_hash'])) {
        return true;
    }
    return !empty($_SESSION['guest_unlocked'][$event['guest_token']]);
}

// ---------------------------------------------------------------------------
// Gift Mode: per-session guest identity
//
// There is no guest login. "Which claims are mine" is tracked purely in the
// current browser session (matches the app's existing session-only identity
// model, e.g. guest_is_unlocked() above) — no new persistent cookie.
// ---------------------------------------------------------------------------

/** Remembers that the current session created claim $claimId for $guestToken's event. */
function guest_register_claim(string $guestToken, int $claimId): void
{
    $_SESSION['my_claims'][$guestToken][] = $claimId;
}

/** Whether the current session owns the given claim. */
function guest_owns_claim(string $guestToken, int $claimId): bool
{
    return in_array($claimId, $_SESSION['my_claims'][$guestToken] ?? [], true);
}

/** All claim IDs the current session owns for a given event's guest_token. */
function guest_my_claim_ids_for_event(string $guestToken): array
{
    return $_SESSION['my_claims'][$guestToken] ?? [];
}

/** Forgets a claim (after it was undone). */
function guest_forget_claim(string $guestToken, int $claimId): void
{
    if (empty($_SESSION['my_claims'][$guestToken])) {
        return;
    }
    $_SESSION['my_claims'][$guestToken] = array_values(array_filter(
        $_SESSION['my_claims'][$guestToken],
        fn($id) => $id !== $claimId
    ));
}

// ---------------------------------------------------------------------------
// Gift Mode: coordination e-mail (multi-person items)
// ---------------------------------------------------------------------------

/**
 * Sends the gift-mode coordination e-mail to every claim of $item that still
 * has an opted-in notification_email, then clears it. On failure the attempt
 * is counted and retried later (by the next heartbeat or the lazy-cleanup
 * sweep below) up to GIFT_MODE_NOTIFICATION_MAX_ATTEMPTS, after which the
 * app gives up and clears the address anyway.
 */
function gift_mode_send_coordination_emails(array $event, array $item): void
{
    global $db;
    if ($db === null) {
        return;
    }

    $pending = $db->fetchAll(
        'SELECT * FROM claims WHERE item_id = ? AND notification_email IS NOT NULL',
        [$item['id']]
    );
    if (empty($pending)) {
        return;
    }

    $allClaims        = $db->fetchAll(
        'SELECT guest_name FROM claims WHERE item_id = ? ORDER BY claimed_at ASC',
        [$item['id']]
    );
    $participantNames = array_column($allClaims, 'guest_name');

    $emailSettings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
    $mailer        = new Mailer($emailSettings);

    $html = Mailer::renderTemplate('gift_coordination', [
        'event'            => $event,
        'item'             => $item,
        'participantNames' => $participantNames,
        'guestUrl'         => base_url('g/' . $event['guest_token']),
        'primaryColor'     => primary_color(),
    ]);
    $subject = 'Geschenk-Koordination: ' . $item['name'];

    foreach ($pending as $claim) {
        $ok = @$mailer->send($claim['notification_email'], $subject, $html);
        if ($ok) {
            $db->update(
                'claims',
                ['notification_email' => null, 'notification_attempts' => 0],
                ['id' => $claim['id']]
            );
            continue;
        }

        $attempts = (int) $claim['notification_attempts'] + 1;
        $db->update(
            'claims',
            $attempts >= GIFT_MODE_NOTIFICATION_MAX_ATTEMPTS
                ? ['notification_email' => null, 'notification_attempts' => $attempts]
                : ['notification_attempts' => $attempts],
            ['id' => $claim['id']]
        );
    }
}

/**
 * Opportunistic safety-net retry for stale pending gift-mode notifications:
 * an item can become "complete" and then every present guest leaves before
 * all send attempts are exhausted, so no further heartbeat will ever trigger
 * a retry for it. Called from the existing lazy-cleanup block (~1% of
 * requests) and from the heartbeat endpoint.
 */
function gift_mode_retry_pending_notifications(int $limit = 20): void
{
    global $db;
    if ($db === null) {
        return;
    }

    try {
        $rows = $db->fetchAll(
            'SELECT DISTINCT c.item_id FROM claims c
             INNER JOIN items i ON i.id = c.item_id
             INNER JOIN events e ON e.id = i.event_id
             WHERE c.notification_email IS NOT NULL AND e.gift_mode = 1
             LIMIT ' . max(1, $limit)
        );
    } catch (PDOException) {
        return;
    }

    foreach ($rows as $row) {
        $item = $db->fetch('SELECT * FROM items WHERE id = ?', [$row['item_id']]);
        if (!$item) {
            continue;
        }
        $event = $db->fetch('SELECT * FROM events WHERE id = ?', [$item['event_id']]);
        if (!$event) {
            continue;
        }
        gift_mode_send_coordination_emails($event, $item);
    }
}

// ---------------------------------------------------------------------------
// Claim reconsideration window (both modes): delayed creator notifications
// ---------------------------------------------------------------------------

/**
 * Sends the creator's "item claimed" (and, once the list becomes fully
 * claimed, "list complete") e-mails for claims whose claim_finalize_delay_seconds()
 * window has elapsed. Claims get their `finalize_at` set at insert time (see
 * GuestController::claim()); until then they already reserve their quantity
 * like before, but no e-mail goes out yet — the guest can still change their
 * mind via undoClaim(), which just deletes the row before it's ever picked
 * up here. Best-effort, no retries, matching the rest of the codebase.
 */
function process_due_claim_notifications(int $limit = 25): void
{
    global $db;
    if ($db === null) {
        return;
    }

    $due = $db->fetchAll(
        'SELECT c.*, i.event_id FROM claims c
         INNER JOIN items i ON i.id = c.item_id
         WHERE c.creator_notified_at IS NULL
           AND c.finalize_at IS NOT NULL AND c.finalize_at <= NOW()
         ORDER BY i.event_id ASC, c.claimed_at ASC
         LIMIT ' . max(1, $limit)
    );
    if (empty($due)) {
        return;
    }

    $byEvent = [];
    foreach ($due as $claim) {
        $byEvent[$claim['event_id']][] = $claim;
    }

    foreach ($byEvent as $eventId => $claims) {
        $event = $db->fetch('SELECT * FROM events WHERE id = ?', [$eventId]);

        // No event (shouldn't happen, FK cascade deletes claims with it) or no
        // creator address to mail — nothing to send, just mark as processed.
        if (!$event || empty($event['creator_email'])) {
            foreach ($claims as $claim) {
                $db->update('claims', ['creator_notified_at' => date('Y-m-d H:i:s')], ['id' => $claim['id']]);
            }
            continue;
        }

        $emailSettings = $db->fetch('SELECT * FROM email_settings LIMIT 1') ?: [];
        $mailer        = new Mailer($emailSettings);
        $adminUrl      = base_url('e/' . $event['admin_token'] . '/manage');

        foreach ($claims as $claim) {
            $item = $db->fetch('SELECT * FROM items WHERE id = ?', [$claim['item_id']]);
            if ($item && $event['notify_on_claim']) {
                $html = Mailer::renderTemplate('item_claimed', [
                    'event'        => $event,
                    'item'         => $item,
                    'claim'        => ['guest_name' => $claim['guest_name'], 'quantity' => $claim['quantity']],
                    'adminUrl'     => $adminUrl,
                    'primaryColor' => primary_color(),
                ]);
                @$mailer->send(
                    $event['creator_email'],
                    $claim['guest_name'] . ' bringt ' . $item['name'] . ' zu ' . $event['title'] . ' mit',
                    $html
                );
            }
            $db->update('claims', ['creator_notified_at' => date('Y-m-d H:i:s')], ['id' => $claim['id']]);
        }

        if ($event['notify_on_complete']) {
            $rem = $db->fetch(
                'SELECT SUM(i.quantity - COALESCE(c.total,0)) AS remaining
                 FROM items i
                 LEFT JOIN (SELECT item_id, SUM(quantity) AS total
                            FROM claims GROUP BY item_id) c ON c.item_id = i.id
                 WHERE i.event_id = ? AND i.status = "approved"',
                [$event['id']]
            );
            if ((int) ($rem['remaining'] ?? 1) === 0) {
                $html = Mailer::renderTemplate('list_complete', [
                    'event'    => $event,
                    'adminUrl' => $adminUrl,
                ]);
                @$mailer->send(
                    $event['creator_email'],
                    'Liste vollständig: ' . $event['title'],
                    $html
                );
            }
        }
    }
}

/**
 * Render a template inside layout.php.
 * Variables in $vars are extracted and available inside the template.
 */
function render(string $template, array $vars = [], bool $withLayout = true): void
{
    // Inner template captured in isolated scope; global functions (__/h/base_url/…) remain accessible
    $content = (static function (string $__path, array $__vars): string {
        extract($__vars);
        ob_start();
        include $__path;
        return (string) ob_get_clean();
    })(BASE_DIR . '/templates/' . $template . '.php', $vars);

    if ($withLayout) {
        extract($vars);
        include BASE_DIR . '/templates/layout.php';
    } else {
        echo $content;
    }
}

/**
 * Send an HTTP error page and exit.
 */
function abort(int $code): void
{
    http_response_code($code);
    $map = [403 => 'errors/403', 404 => 'errors/404'];
    $tpl = BASE_DIR . '/templates/' . ($map[$code] ?? 'errors/404') . '.php';
    if (file_exists($tpl)) {
        include $tpl;
    } else {
        echo "<h1>{$code}</h1>";
    }
    exit;
}

/**
 * The exact URL of the current request, reconstructed from scheme+host+
 * REQUEST_URI (not via base_url(), which would double up any subdirectory
 * prefix already present in REQUEST_URI). Used to send a user back to what
 * they were trying to reach once they've logged in.
 */
function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
}

/**
 * Splits a .sql file's contents into individual statements. A naive
 * explode(';', ...) breaks as soon as a semicolon appears inside a quoted
 * string (e.g. a column COMMENT with punctuation) or inside a `-- ...`
 * line comment — this tracks both, so embedded semicolons there aren't
 * mistaken for statement terminators.
 */
function split_sql_statements(string $sql): array
{
    $statements    = [];
    $current       = '';
    $inString      = false;
    $inLineComment = false;
    $len           = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $current .= $char;

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inString) {
            if ($char === "'" && $sql[$i - 1] !== '\\') {
                $inString = false;
            }
            continue;
        }

        if ($char === "'") {
            $inString = true;
        } elseif ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
            $inLineComment = true;
        } elseif ($char === ';') {
            $stmt = trim(substr($current, 0, -1));
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }

    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

/** Best-effort client IP (first trusted value found). */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

/**
 * Returns true when the action is still within limits (request is ALLOWED).
 * Silently allows all requests if the rate_limit table doesn't exist yet.
 */
function rate_limit_check(string $action, int $maxRequests = 10, int $windowSeconds = 3600): bool
{
    global $db;
    if ($db === null) {
        return true;
    }
    try {
        $ip    = client_ip();
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);
        $row   = $db->fetch(
            'SELECT COUNT(*) AS cnt FROM rate_limit WHERE ip_address = ? AND action = ? AND created_at > ?',
            [$ip, $action, $since]
        );
        return (int) ($row['cnt'] ?? 0) < $maxRequests;
    } catch (PDOException) {
        return true;
    }
}

/** Record one hit for rate limiting. */
function rate_limit_record(string $action): void
{
    global $db;
    if ($db === null) {
        return;
    }
    try {
        $db->insert('rate_limit', ['ip_address' => client_ip(), 'action' => $action]);
    } catch (PDOException) {
        // rate_limit table may not exist yet — non-critical, ignore
    }
}

// Claim reconsideration window: send creator notifications for claims whose
// claim_finalize_delay_seconds() window has elapsed. Deliberately NOT part of
// the 1%-sampled lazy cleanup below — with only a short window (default 10
// minutes, superadmin-configurable), creators expect the mail promptly, so
// this runs on every request (cheap indexed lookup, no-op in the common case).
if ($db !== null) {
    try {
        process_due_claim_notifications();
    } catch (PDOException) {
        // best-effort, ignore
    }
}

// Lazy cleanup on ~1 % of requests: rate_limit rows + expired events
if ($db !== null && random_int(1, 100) === 1) {
    try {
        $db->query('DELETE FROM rate_limit WHERE created_at < ?', [
            date('Y-m-d H:i:s', time() - 86400),
        ]);
    } catch (PDOException) {
        // Table missing (installer bug on first deploy) — create it now
        try {
            $db->query('CREATE TABLE IF NOT EXISTS `rate_limit` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `action`     VARCHAR(50) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_rate_limit_lookup` (`ip_address`, `action`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (PDOException) { }
    }
    try {
        $db->query(
            'DELETE FROM events WHERE expires_at IS NOT NULL AND expires_at < ?',
            [date('Y-m-d H:i:s')]
        );
    } catch (PDOException) { }
    try {
        gift_mode_retry_pending_notifications();
    } catch (PDOException) { }
}
