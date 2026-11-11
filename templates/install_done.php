<div class="card" style="max-width:480px;margin-inline:auto;text-align:center;padding:2.5rem 2rem;">
    <h1 style="margin-bottom:1rem;"><?= h(__('install.done_title')) ?></h1>
    <p class="text-muted">
        <?= h(__('install.already_installed')) ?><br>
        <?= __('install.done_hint', ['file' => '<code>config/config.php</code>']) ?>
    </p>
    <div style="margin-top:1.5rem;">
        <a href="<?= h(base_url('admin/login')) ?>" class="btn btn-primary"><?= h(__('install.to_admin_login')) ?></a>
    </div>
</div>
