<?php
/**
 * Admin cleanup settings — manual cleanup, CLI crontab, and web-cron configuration.
 */
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.5rem;">
            <i class="fa-solid fa-broom" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_cleanup')) ?>
        </h1>

        <!-- ============================================================ -->
        <!-- Manuelle Bereinigung                                          -->
        <!-- ============================================================ -->
        <div class="card" style="margin-top:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.375rem;">
                <i class="fa-solid fa-broom" aria-hidden="true" style="color:var(--primary-color);"></i>
                <?= h(__('admin.cron_manual_title')) ?>
            </h2>
            <p class="text-muted text-sm" style="margin-bottom:1rem;"><?= h(__('admin.cron_manual_desc')) ?></p>
            <form method="post" action="<?= h(base_url('admin/settings/cleanup')) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cron_run">
                <button type="submit" class="btn btn-secondary">
                    <i class="fa-solid fa-broom" aria-hidden="true"></i>
                    <?= h(__('admin.cron_manual_button')) ?>
                </button>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- Crontab-Eintrag (CLI)                                         -->
        <!-- ============================================================ -->
        <div class="card" style="margin-top:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.375rem;">
                <i class="fa-solid fa-terminal" aria-hidden="true" style="color:var(--primary-color);"></i>
                <?= h(__('admin.cron_tab_title')) ?>
            </h2>
            <p class="text-muted text-sm" style="margin-bottom:1rem;"><?= h(__('admin.cron_tab_desc')) ?></p>

            <div class="form-group">
                <label class="form-label" for="cron_tab"><?= h(__('admin.cron_tab_label')) ?></label>
                <?php $cronCliCmd = '0 3 * * * php ' . BASE_DIR . '/cleanup.php'; ?>
                <div style="display:flex;gap:.5rem;max-width:640px;">
                    <input class="form-input" type="text" id="cron_tab" readonly
                           value="<?= h($cronCliCmd) ?>" data-select-on-click
                           style="font-family:monospace;font-size:.85rem;">
                    <button type="button" class="btn btn-secondary" style="flex-shrink:0;"
                            data-success-msg="<?= h(__('admin.cron_copy_success')) ?>"
                            data-copy-target="cron_tab">
                        <i class="fa-solid fa-copy" aria-hidden="true"></i>
                        <?= h(__('admin.cron_copy')) ?>
                    </button>
                </div>
                <p class="form-hint"><?= h(__('admin.cron_tab_php_hint')) ?></p>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Web-Cron (Token-basierter Aufruf)                             -->
        <!-- ============================================================ -->
        <?php $cronToken = $cronToken ?? ''; $cronUrl = $cronUrl ?? ''; ?>
        <div class="card" style="margin-top:1.5rem;">
            <h2 style="font-size:1rem;margin-bottom:.375rem;">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true" style="color:var(--primary-color);"></i>
                <?= h(__('admin.cron_webcron_title')) ?>
            </h2>

            <?php if ($cronToken === ''): ?>
                <p class="text-muted text-sm" style="margin-bottom:1rem;"><?= h(__('admin.cron_webcron_inactive_desc')) ?></p>
                <form method="post" action="<?= h(base_url('admin/settings/cleanup')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cron_generate">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <?= h(__('admin.cron_generate')) ?>
                    </button>
                </form>
            <?php else: ?>
                <p class="text-muted text-sm" style="margin-bottom:.75rem;"><?= h(__('admin.cron_webcron_active_desc')) ?></p>

                <div class="form-group">
                    <label class="form-label" for="cron_url"><?= h(__('admin.cron_url_label')) ?></label>
                    <div style="display:flex;gap:.5rem;max-width:640px;">
                        <input class="form-input" type="text" id="cron_url" readonly
                               value="<?= h($cronUrl) ?>" data-select-on-click>
                        <button type="button" class="btn btn-secondary" style="flex-shrink:0;"
                                data-success-msg="<?= h(__('admin.cron_copy_success')) ?>"
                                data-copy-target="cron_url">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i>
                            <?= h(__('admin.cron_copy')) ?>
                        </button>
                    </div>
                    <p class="form-hint"><?= h(__('admin.cron_url_hint')) ?></p>
                </div>

                <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem;">
                    <form method="post" action="<?= h(base_url('admin/settings/cleanup')) ?>"
                          onsubmit="return confirm('<?= h(__('admin.cron_regenerate_confirm')) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cron_generate">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                            <?= h(__('admin.cron_regenerate')) ?>
                        </button>
                    </form>
                    <form method="post" action="<?= h(base_url('admin/settings/cleanup')) ?>"
                          onsubmit="return confirm('<?= h(__('admin.cron_disable_confirm')) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cron_disable">
                        <button type="submit" class="btn btn-secondary" style="color:#dc2626;border-color:#dc2626;">
                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            <?= h(__('admin.cron_disable')) ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
