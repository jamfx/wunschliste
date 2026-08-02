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

        <!-- Stats row -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin-bottom:1.5rem;">
            <div class="card" style="text-align:center;padding:1rem;">
                <div style="font-size:2rem;font-weight:700;color:var(--primary-color);"><?= $totalEvents ?></div>
                <div class="text-muted text-sm">Events gesamt</div>
            </div>
            <div class="card" style="text-align:center;padding:1rem;">
                <div style="font-size:2rem;font-weight:700;color:var(--success);"><?= $activeEvents ?></div>
                <div class="text-muted text-sm">Aktiv</div>
            </div>
            <div class="card" style="text-align:center;padding:1rem;">
                <div style="font-size:2rem;font-weight:700;color:var(--text-muted);"><?= $expiredEvents ?></div>
                <div class="text-muted text-sm">Abgelaufen</div>
            </div>
        </div>

        <!-- Action buttons -->
        <div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;">
            <a href="<?= h(base_url()) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Neues Event (öffnet Website)
            </a>
            <form method="post" action="<?= h(base_url('admin/cleanup')) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-broom" aria-hidden="true"></i>
                    Abgelaufene jetzt löschen
                </button>
            </form>
        </div>

        <?php if (empty($events)): ?>
        <div class="card" style="text-align:center;padding:3rem;color:var(--text-muted);">
            <i class="fa-solid fa-calendar-xmark" aria-hidden="true" style="font-size:2rem;display:block;margin-bottom:.75rem;"></i>
            Noch keine Events vorhanden.
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Datum</th>
                        <th><?= h(__('admin.items_column')) ?></th>
                        <th>Fortschritt</th>
                        <th>Status</th>
                        <th>Erstellt</th>
                        <th>Aktionen</th>
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
                        <?= !empty($event['event_date']) ? h(date('d.m.Y', strtotime($event['event_date']))) : '—' ?>
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
                        <span class="badge badge-neutral">Abgelaufen</span>
                        <?php else: ?>
                        <span class="badge badge-claimed">Aktiv</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?= h(date('d.m.Y', strtotime($event['created_at']))) ?>
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
