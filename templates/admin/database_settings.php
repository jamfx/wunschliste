<?php
/**
 * Admin: database schema migrations (SuperAdmin only).
 * Variables: $pending (string[]), $executed (array filename => executed_at), $error (?string)
 */
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.5rem;">
            <i class="fa-solid fa-database" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_database')) ?>
        </h1>

        <div class="card">
            <p class="text-muted text-sm" style="margin-bottom:1rem;"><?= h(__('admin.db_intro')) ?></p>

            <?php if ($error !== null): ?>
                <div class="alert alert-error" role="alert">
                    <?= h(__('admin.db_check_failed', ['error' => $error])) ?>
                </div>
            <?php elseif (empty($pending)): ?>
                <div class="alert alert-success" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <?= h(__('admin.db_up_to_date')) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <?= h(__('admin.db_pending', ['count' => count($pending)])) ?>
                </div>
                <ul style="padding-left:1.25rem;line-height:1.75;margin:.75rem 0;font-family:monospace;font-size:.875rem;">
                    <?php foreach ($pending as $file): ?>
                        <li><?= h($file) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="text-muted text-sm" style="margin-bottom:1rem;">
                    <i class="fa-solid fa-life-ring" aria-hidden="true"></i>
                    <?= h(__('admin.db_backup_hint')) ?>
                </p>
                <form method="post" action="<?= h(base_url('admin/settings/database')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"
                            data-confirm="<?= h(__('admin.db_run_confirm')) ?>">
                        <i class="fa-solid fa-play" aria-hidden="true"></i>
                        <?= h(__('admin.db_run_button')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($executed)): ?>
        <div class="card" style="margin-top:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.75rem;"><?= h(__('admin.db_applied')) ?></h2>
            <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:.25rem .5rem;"><?= h(__('admin.db_col_file')) ?></th>
                        <th style="text-align:left;padding:.25rem .5rem;"><?= h(__('admin.db_col_date')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($executed as $file => $when): ?>
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:.375rem .5rem;font-family:monospace;"><?= h($file) ?></td>
                        <td style="padding:.375rem .5rem;" class="text-muted"><?= h($when) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
