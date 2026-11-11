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
                <?= h(__('admin.users_invite_title')) ?>
            </h2>
            <form method="post" action="<?= h(base_url('admin/users/invite')) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="invite_email"><?= h(__('admin.users_email_label')) ?></label>
                    <input class="form-input" type="email" id="invite_email" name="email"
                           autocomplete="off" required>
                    <p class="form-hint">
                        <?= h(__('admin.users_invite_hint')) ?>
                    </p>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <?= h(__('admin.users_invite_send')) ?>
                </button>
            </form>
        </div>

        <?php if (empty($users)): ?>
        <div class="card" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <i class="fa-solid fa-user-slash" aria-hidden="true" style="font-size:2rem;display:block;margin-bottom:.75rem;"></i>
            <?= h(__('admin.users_none')) ?>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= h(__('admin.users_col_email')) ?></th>
                        <th><?= h(__('admin.users_col_account')) ?></th>
                        <th><?= h(__('admin.col_status')) ?></th>
                        <th><?= h(__('admin.users_col_invited')) ?></th>
                        <th><?= h(__('admin.col_actions')) ?></th>
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
                        <span class="badge badge-claimed"><?= h(__('admin.users_activated')) ?></span>
                        <?php elseif ($inviteExpired): ?>
                        <span class="badge badge-neutral"><?= h(__('admin.users_invite_expired')) ?></span>
                        <?php else: ?>
                        <span class="badge badge-pending"><?= h(__('admin.users_invite_pending')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['status'] === 'active'): ?>
                        <span class="badge badge-claimed"><?= h(__('common.active')) ?></span>
                        <?php else: ?>
                        <span class="badge badge-neutral"><?= h(__('admin.users_locked')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?= h(format_date($u['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:.375rem;flex-wrap:wrap;">
                            <?php if (!$activated): ?>
                            <form method="post" action="<?= h(base_url('admin/users/' . $u['id'] . '/resend')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm" title="<?= h(__('admin.users_resend')) ?>" aria-label="<?= h(__('admin.users_resend')) ?>">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?= h(base_url('admin/users/' . $u['id'] . '/toggle')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm"
                                        title="<?= h($u['status'] === 'active' ? __('admin.users_lock') : __('admin.users_unlock')) ?>"
                                        aria-label="<?= h($u['status'] === 'active' ? __('admin.users_lock') : __('admin.users_unlock')) ?>">
                                    <i class="fa-solid <?= $u['status'] === 'active' ? 'fa-lock' : 'fa-lock-open' ?>" aria-hidden="true"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= h(base_url('admin/users/' . $u['id'] . '/delete')) ?>" style="margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm"
                                        style="color:var(--danger);border-color:var(--danger);"
                                        data-confirm="<?= h(__('admin.users_delete_confirm', ['email' => $u['email']])) ?>"
                                        title="<?= h(__('common.delete')) ?>" aria-label="<?= h(__('common.delete')) ?>">
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
