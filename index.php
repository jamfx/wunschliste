<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

// Redirect to installer if not yet set up
if (!file_exists(CONFIG_PATH)) {
    redirect(base_url('install.php'));
}

// Load all controllers
require_once BASE_DIR . '/src/Controllers/InstallController.php';
require_once BASE_DIR . '/src/Controllers/EventController.php';
require_once BASE_DIR . '/src/Controllers/ItemController.php';
require_once BASE_DIR . '/src/Controllers/GuestController.php';
require_once BASE_DIR . '/src/Controllers/AdminController.php';
require_once BASE_DIR . '/src/Controllers/PdfExportController.php';
require_once BASE_DIR . '/src/Controllers/PageController.php';

$router = new Router();

// -----------------------------------------------------------------------
// Public: Event lifecycle
// -----------------------------------------------------------------------
$router->get('/',                                  [EventController::class,     'create']);
$router->post('/',                                 [EventController::class,     'store']);
$router->get('/event/created/{admin_token}',       [EventController::class,     'created']);
$router->get('/e/{admin_token}/manage',            [EventController::class,     'manage']);
$router->get('/e/{admin_token}/edit',              [EventController::class,     'editForm']);
$router->post('/e/{admin_token}/edit',             [EventController::class,     'update']);
$router->post('/e/{admin_token}/delete',           [EventController::class,     'delete']);

// -----------------------------------------------------------------------
// Public: Items (admin token required)
// -----------------------------------------------------------------------
$router->post('/e/{admin_token}/items',                      [ItemController::class, 'store']);
$router->post('/e/{admin_token}/items/{id}/update',          [ItemController::class, 'update']);
$router->post('/e/{admin_token}/items/{id}/delete',          [ItemController::class, 'delete']);
$router->post('/e/{admin_token}/items/{id}/approve',         [ItemController::class, 'approve']);
$router->post('/e/{admin_token}/items/{id}/reject',          [ItemController::class, 'reject']);
$router->post('/e/{admin_token}/items/reorder',               [ItemController::class, 'reorder']);

// -----------------------------------------------------------------------
// Public: PDF (admin view)
// -----------------------------------------------------------------------
$router->get('/e/{admin_token}/pdf',               [PdfExportController::class, 'adminPdf']);

// -----------------------------------------------------------------------
// Guest: view, claim, suggest, PDF
// -----------------------------------------------------------------------
$router->get('/g/{guest_token}',                   [GuestController::class,     'view']);
$router->post('/g/{guest_token}/unlock',           [GuestController::class,     'unlock']);
$router->post('/g/{guest_token}/claim',            [GuestController::class,     'claim']);
$router->post('/g/{guest_token}/claim/{claim_id}/undo', [GuestController::class, 'undoClaim']);
$router->post('/g/{guest_token}/heartbeat',        [GuestController::class,     'heartbeat']);
$router->post('/g/{guest_token}/suggest',          [GuestController::class,     'suggest']);
$router->get('/g/{guest_token}/pdf',               [PdfExportController::class, 'guestPdf']);

// -----------------------------------------------------------------------
// Admin backend: auth
// -----------------------------------------------------------------------
$router->get('/admin',                             [AdminController::class,     'adminRedirect']);
$router->get('/admin/login',                       [AdminController::class,     'loginForm']);
$router->post('/admin/login',                      [AdminController::class,     'login']);
$router->get('/admin/totp',                        [AdminController::class,     'totpForm']);
$router->post('/admin/totp',                       [AdminController::class,     'totpVerify']);
$router->post('/admin/logout',                     [AdminController::class,     'logout']);

// -----------------------------------------------------------------------
// Admin backend: dashboard + event management
// -----------------------------------------------------------------------
$router->get('/admin/dashboard',                   [AdminController::class,     'dashboard']);
$router->post('/admin/event/{id}/delete',          [AdminController::class,     'deleteEvent']);
$router->post('/admin/cleanup',                    [AdminController::class,     'cleanup']);

// -----------------------------------------------------------------------
// Admin backend: settings
// -----------------------------------------------------------------------
$router->get('/admin/settings',                    [AdminController::class,     'settings']);
$router->get('/admin/settings/design',             [AdminController::class,     'designSettings']);
$router->post('/admin/settings/design',            [AdminController::class,     'saveDesignSettings']);
$router->post('/admin/settings/logo',              [AdminController::class,     'uploadLogo']);
$router->post('/admin/settings/logo/delete',       [AdminController::class,     'deleteLogo']);

// -----------------------------------------------------------------------
// Admin backend: 2FA
// -----------------------------------------------------------------------
$router->post('/admin/settings/2fa',               [AdminController::class,     'totpEnable']);
$router->get('/admin/settings/security',           [AdminController::class,     'securitySettings']);
$router->post('/admin/settings/security',          [AdminController::class,     'saveSecuritySettings']);
$router->get('/admin/settings/cleanup',            [AdminController::class,     'cleanupSettings']);
$router->post('/admin/settings/cleanup',           [AdminController::class,     'saveCleanupSettings']);
$router->post('/admin/settings/2fa/disable',       [AdminController::class,     'totpDisable']);
$router->post('/admin/settings/2fa/passkey',          [AdminController::class, 'passkeyApi']);
$router->post('/admin/settings/2fa/passkey/confirm',  [AdminController::class, 'confirmPasskey']);
$router->post('/admin/settings/2fa/passkey/cancel',   [AdminController::class, 'cancelPendingPasskey']);
$router->post('/admin/settings/2fa/passkey/delete',   [AdminController::class, 'deletePasskey']);
$router->post('/admin/settings/2fa/passkey-auth',  [AdminController::class,     'passkeyAuthApi']);
$router->post('/admin/settings/2fa/recovery',      [AdminController::class,     'regenerateRecoveryCodes']);

// -----------------------------------------------------------------------
// Public: token-protected web-cron cleanup endpoint (see security settings)
// -----------------------------------------------------------------------
$router->get('/cron/cleanup',                      [AdminController::class,     'cronCleanup']);

// -----------------------------------------------------------------------
// Admin backend: email
// -----------------------------------------------------------------------
$router->get('/admin/email',                       [AdminController::class,     'emailSettings']);
$router->post('/admin/email',                      [AdminController::class,     'saveEmailSettings']);
$router->post('/admin/email/test',                 [AdminController::class,     'testEmail']);

// -----------------------------------------------------------------------
// Admin backend: PDF settings
// -----------------------------------------------------------------------
$router->get('/admin/pdf-settings',                [AdminController::class,     'pdfSettings']);
$router->post('/admin/pdf-settings',               [AdminController::class,     'savePdfSettings']);

// -----------------------------------------------------------------------
// Legal pages
// -----------------------------------------------------------------------
$router->get('/hilfe',                             [PageController::class,      'help']);
$router->get('/legal/imprint',                     [PageController::class,      'imprint']);
$router->get('/legal/privacy',                     [PageController::class,      'privacy']);

// -----------------------------------------------------------------------
// Install redirect (standalone install.php is the primary entry point)
// -----------------------------------------------------------------------
$router->get('/install',                           [InstallController::class,   'index']);

$router->dispatch();
