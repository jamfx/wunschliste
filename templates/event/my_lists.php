<?php
/**
 * "My lists" — a logged-in user's own lists (any role, not just SuperAdmin).
 * Variables: $events (array)
 */
?>
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;">
    <h1 style="font-size:1.5rem;margin:0;">
        <i class="fa-solid fa-list-check" aria-hidden="true" style="color:var(--primary-color);"></i>
        Meine Listen
    </h1>
    <div style="display:flex;gap:.5rem;">
        <a href="<?= h(base_url('account/security')) ?>" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <?= h(__('admin.account_security')) ?>
        </a>
        <form method="post" action="<?= h(base_url('admin/logout')) ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <?= h(__('admin.logout')) ?>
            </button>
        </form>
    </div>
</div>

<div style="margin-bottom:1.25rem;">
    <a href="<?= h(base_url()) ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Neue Liste erstellen
    </a>
</div>

<?php if (empty($events)): ?>
<div class="card" style="text-align:center;padding:3rem;color:var(--text-muted);">
    <i class="fa-solid fa-calendar-xmark" aria-hidden="true" style="font-size:2rem;display:block;margin-bottom:.75rem;"></i>
    Noch keine eigenen Listen vorhanden.
</div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Datum</th>
                <th>Fortschritt</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
        <tr style="<?= $event['is_expired'] ? 'opacity:.6;' : '' ?>">
            <td>
                <a href="<?= h(base_url('e/' . $event['admin_token'] . '/manage')) ?>" style="font-weight:600;">
                    <?= h($event['title']) ?>
                </a>
            </td>
            <td class="text-sm text-muted">
                <?= !empty($event['event_date']) ? h(date('d.m.Y', strtotime($event['event_date']))) : '—' ?>
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
            <td>
                <div style="display:flex;gap:.375rem;flex-wrap:wrap;">
                    <a href="<?= h(base_url('e/' . $event['admin_token'] . '/manage')) ?>" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    </a>
                    <a href="<?= h(base_url('g/' . $event['guest_token'])) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
