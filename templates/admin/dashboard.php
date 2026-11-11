<?php
/**
 * Admin dashboard — event list with stats.
 * Variables: $events (array), $totalEvents, $activeEvents, $expiredEvents
 */
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">

        <h1 style="font-size:1.5rem;margin-bottom:1.25rem;">
            <i class="fa-solid fa-gauge-high" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.dashboard_title')) ?>
        </h1>

        <?php if (!empty($pendingMigrations)): ?>
        <div class="alert alert-warning" role="alert" style="margin-bottom:1.25rem;">
            <i class="fa-solid fa-database" aria-hidden="true"></i>
            <?= h(__('admin.db_banner', ['count' => (int) $pendingMigrations])) ?>
            <a href="<?= h(base_url('admin/settings/database')) ?>"><?= h(__('admin.db_banner_link')) ?></a>
        </div>
        <?php endif; ?>

        <!-- Stats row -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1.5rem;">
            <div class="card" style="text-align:center;padding:1rem;">
                <div style="font-size:2rem;font-weight:700;color:var(--primary-color);"><?= $totalEvents ?></div>
                <div class="text-muted text-sm"><?= h(__('admin.stat_total')) ?></div>
            </div>
            <div class="card" style="text-align:center;padding:1rem;">
                <div style="font-size:2rem;font-weight:700;color:var(--success);"><?= $activeEvents ?></div>
                <div class="text-muted text-sm"><?= h(__('common.active')) ?></div>
            </div>
            <div class="card" style="text-align:center;padding:1rem;">
                <div style="font-size:2rem;font-weight:700;color:var(--text-muted);"><?= $expiredEvents ?></div>
                <div class="text-muted text-sm"><?= h(__('common.expired')) ?></div>
            </div>
        </div>

        <!-- Action buttons -->
        <div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;">
            <a href="<?= h(base_url()) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <?= h(__('admin.new_event_opens_site')) ?>
            </a>
            <form method="post" action="<?= h(base_url('admin/cleanup')) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-broom" aria-hidden="true"></i>
                    <?= h(__('admin.delete_expired_now')) ?>
                </button>
            </form>
        </div>

        <?php if (empty($events)): ?>
        <div class="card" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <i class="fa-solid fa-calendar-xmark" aria-hidden="true" style="font-size:2rem;display:block;margin-bottom:.75rem;"></i>
            <?= h(__('admin.no_events')) ?>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= h(__('admin.col_title')) ?></th>
                        <th><?= h(__('admin.col_owner')) ?></th>
                        <th><?= h(__('admin.col_date')) ?></th>
                        <th><?= h(__('admin.items_column')) ?></th>
                        <th><?= h(__('admin.col_progress')) ?></th>
                        <th><?= h(__('admin.col_status')) ?></th>
                        <th><?= h(__('admin.col_created')) ?></th>
                        <th><?= h(__('admin.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                <tr style="<?= $event['is_expired'] ? 'opacity:.6;' : '' ?>">
                    <td>
                        <a href="<?= h(base_url('e/' . $event['admin_token'] . '/manage')) ?>"
                           style="font-weight:600;">
                            <?= h($event['title']) ?>
                        </a>
                    </td>
                    <td class="text-sm text-muted">
                        <?= !empty($event['owner_email']) ? h($event['owner_email']) : '— (Token-only)' ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?= !empty($event['event_date']) ? h(format_date($event['event_date'])) : '—' ?>
                    </td>
                    <td class="text-sm">
                        <?= (int) $event['claimed_count'] ?>/<?= (int) $event['item_count'] ?>
                    </td>
                    <td style="min-width:100px;">
                        <div class="progress-bar-outer" style="height:.5rem;" role="progressbar"
                             aria-valuenow="<?= $event['progress_pct'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar-inner <?= $event['progress_pct'] >= 100 ? 'progress-bar-inner--complete' : '' ?>"
                                 style="width:<?= $event['progress_pct'] ?>%;"></div>
                        </div>
                        <small class="text-muted"><?= $event['progress_pct'] ?>%</small>
                    </td>
                    <td>
                        <?php if ($event['is_expired']): ?>
                        <span class="badge badge-neutral"><?= h(__('common.expired')) ?></span>
                        <?php else: ?>
                        <span class="badge badge-claimed"><?= h(__('common.active')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?= h(format_date($event['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:.375rem;flex-wrap:wrap;">
                            <a href="<?= h(base_url('e/' . $event['admin_token'] . '/manage')) ?>"
                               class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            </a>
                            <a href="<?= h(base_url('g/' . $event['guest_token'])) ?>"
                               class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </a>
                            <form method="post"
                                  action="<?= h(base_url('admin/event/' . $event['id'] . '/delete')) ?>"
                                  style="display:inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="<?= h(__('admin.delete_event_confirm', ['title' => $event['title']])) ?>">
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
