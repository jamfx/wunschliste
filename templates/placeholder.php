<div class="card" style="max-width:560px;margin-inline:auto;text-align:center;padding:3rem 2rem;">
    <h1 style="color:var(--primary-color);margin-bottom:1rem;"><?= h(__('app.title')) ?></h1>
    <p style="color:var(--text-muted);">
        <?= h(__('install.ready')) ?>
    </p>
    <div style="margin-top:2rem;display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
        <a href="<?= h(base_url('admin/login')) ?>" class="btn btn-primary"><?= h(__('install.admin_login')) ?></a>
    </div>
</div>
