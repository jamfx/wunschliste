<?php
/**
 * Admin user management — invite, list, resend, lock/unlock, delete
 * registered users. The SuperAdmin account itself never appears here.
 * Variables: $users (array)
 */
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.25rem;">
            <i class="fa-solid fa-users" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_users')) ?>
        </h1>

        <div class="card" style="max-width:520px;margin-bottom:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.75rem;">
                <i class="fa-solid fa-user-plus" aria-hidden="true" style="color:var(--primary-color);margin-right:.4em;"></i>
                Neuen Benutzer einladen
            </h2>
            <form method="post" action="<?= h(base_url('admin/users/invite')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="invite_email">E-Mail-Adresse</label>
                    <input class="form-input" type="email" id="invite_email" name="email"
                           autocomplete="off" required>
                    <p class="form-hint">
                        Erhält einen Link, der 24 Stunden gültig ist, um ein Passwort festzulegen.
                    </p>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    Einladung senden
                </button>
            </form>
        </div>

        <?php if (empty($users)): ?>
        <div class="card" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <i class="fa-solid fa-user-slash" aria-hidden="true" style="font-size:2rem;display:block;margin-bottom:.75rem;"></i>
            Noch keine Benutzer eingeladen.
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>E-Mail</th>
                        <th>Konto</th>
                        <th>Status</th>
                        <th>Eingeladen</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <?php
                    $activated = $u['password_hash'] !== null;
                    $inviteExpired = !$activated
                        && !empty($u['latest_invite_expires'])
                        && strtotime($u['latest_invite_expires']) < time();
                ?>
                <tr>
                    <td><?= h($u['email']) ?></td>
                    <td>
                        <?php if ($activated): ?>
                        <span class="badge badge-claimed">Aktiviert</span>
                        <?php elseif ($inviteExpired): ?>
                        <span class="badge badge-neutral">Einladung abgelaufen</span>
                        <?php else: ?>
                        <span class="badge badge-pending">Einladung ausstehend</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['status'] === 'active'): ?>
                        <span class="badge badge-claimed">Aktiv</span>
                        <?php else: ?>
                        <span class="badge badge-neutral">Gesperrt</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?= h(date('d.m.Y', strtotime($u['created_at']))) ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:.375rem;flex-wrap:wrap;">
                            <?php if (!$activated): ?>
                            <form method="post" action="<?= h(base_url('admin/users/' . $u['id'] . '/resend')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm" title="Einladung erneut senden">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?= h(base_url('admin/users/' . $u['id'] . '/toggle')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm"
                                        title="<?= $u['status'] === 'active' ? 'Sperren' : 'Entsperren' ?>">
                                    <i class="fa-solid <?= $u['status'] === 'active' ? 'fa-lock' : 'fa-lock-open' ?>" aria-hidden="true"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= h(base_url('admin/users/' . $u['id'] . '/delete')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm"
                                        style="color:var(--danger);border-color:var(--danger);"
                                        data-confirm="Account &quot;<?= h($u['email']) ?>&quot; wirklich löschen? Vorhandene Listen bleiben erhalten, sind danach aber nur noch über den Admin-Link erreichbar.">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
