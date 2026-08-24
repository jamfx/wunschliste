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
require_once BASE_DIR . '/src/Controllers/AccountController.php';
require_once BASE_DIR . '/src/Controllers/AccountSecurityController.php';
require_once BASE_DIR . '/src/Controllers/PdfExportController.php';
require_once BASE_DIR . '/src/Controllers/PageController.php';

$router = new Router();

// -----------------------------------------------------------------------
// Public: Event lifecycle
// -----------------------------------------------------------------------
$router->get('/',                                  [EventController::class,     'create']);
$router->post('/',                                 [EventController::class,     'store']);
$router->get('/event/created/{admin_token}',       [EventController::class,     'created']);
$router->get('/my/lists',                          [EventController::class,     'myLists']);
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
// Public: account lifecycle (invite acceptance, forgot/reset password)
// -----------------------------------------------------------------------
$router->get('/invite/{token}',                    [AccountController::class,   'acceptInviteForm']);
$router->post('/invite/{token}',                   [AccountController::class,   'acceptInvite']);
$router->get('/forgot-password',                   [AccountController::class,   'forgotPasswordForm']);
$router->post('/forgot-password',                  [AccountController::class,   'forgotPassword']);
$router->get('/reset-password/{token}',            [AccountController::class,   'resetPasswordForm']);
$router->post('/reset-password/{token}',           [AccountController::class,   'resetPassword']);

// -----------------------------------------------------------------------
// Admin backend: dashboard + event management
// -----------------------------------------------------------------------
$router->get('/admin/dashboard',                   [AdminController::class,     'dashboard']);
$router->post('/admin/event/{id}/delete',          [AdminController::class,     'deleteEvent']);
$router->post('/admin/cleanup',                    [AdminController::class,     'cleanup']);

// -----------------------------------------------------------------------
// Admin backend: user management (SuperAdmin invites registered users)
// -----------------------------------------------------------------------
$router->get('/admin/users',                       [AdminController::class,     'users']);
$router->post('/admin/users/invite',               [AdminController::class,     'inviteUser']);
$router->post('/admin/users/{id}/resend',          [AdminController::class,     'resendInvite']);
$router->post('/admin/users/{id}/toggle',          [AdminController::class,     'toggleUserStatus']);
$router->post('/admin/users/{id}/delete',          [AdminController::class,     'deleteUser']);

// -----------------------------------------------------------------------
// Admin backend: settings
// -----------------------------------------------------------------------
$router->get('/admin/settings',                    [AdminController::class,     'settings']);
$router->get('/admin/settings/design',             [AdminController::class,     'designSettings']);
$router->post('/admin/settings/design',            [AdminController::class,     'saveDesignSettings']);
$router->post('/admin/settings/logo',              [AdminController::class,     'uploadLogo']);
$router->post('/admin/settings/logo/delete',       [AdminController::class,     'deleteLogo']);

// -----------------------------------------------------------------------
// Admin backend: site-wide security settings (SuperAdmin only)
// -----------------------------------------------------------------------
$router->get('/admin/settings/security',           [AdminController::class,     'securitySettings']);
$router->post('/admin/settings/security',          [AdminController::class,     'saveSecuritySettings']);
$router->get('/admin/settings/cleanup',            [AdminController::class,     'cleanupSettings']);
$router->post('/admin/settings/cleanup',           [AdminController::class,     'saveCleanupSettings']);
// Login-time passkey verification stays here — it runs before a session exists.
$router->post('/admin/settings/2fa/passkey-auth',  [AdminController::class,     'passkeyAuthApi']);

// -----------------------------------------------------------------------
// Account: personal security (2FA/Passkeys) — any logged-in account, not
// just the SuperAdmin
// -----------------------------------------------------------------------
$router->get('/account/security',                          [AccountSecurityController::class, 'index']);
$router->post('/account/security/2fa',                      [AccountSecurityController::class, 'totpStart']);
$router->post('/account/security/2fa/confirm',               [AccountSecurityController::class, 'totpConfirm']);
$router->post('/account/security/2fa/cancel',                [AccountSecurityController::class, 'cancelTotpSetup']);
$router->post('/account/security/2fa/disable',                [AccountSecurityController::class, 'totpDisable']);
$router->post('/account/security/2fa/passkey',                [AccountSecurityController::class, 'passkeyApi']);
$router->post('/account/security/2fa/passkey/confirm',        [AccountSecurityController::class, 'confirmPasskey']);
$router->post('/account/security/2fa/passkey/cancel',         [AccountSecurityController::class, 'cancelPendingPasskey']);
$router->post('/account/security/2fa/passkey/delete',         [AccountSecurityController::class, 'deletePasskey']);
$router->post('/account/security/2fa/recovery',                [AccountSecurityController::class, 'regenerateRecoveryCodes']);

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
