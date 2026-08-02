<?php
/**
 * Admin sidebar / navigation partial.
 * Used inside the admin layout (admin-layout wrapper in each admin template).
 * $currentAdminPage is set by the controller to highlight the active link.
 */
$currentAdminPage = $currentAdminPage ?? '';

$navItems = [
    'dashboard'    => ['url' => base_url('admin/dashboard'),     'icon' => 'fa-solid fa-gauge-high',       'label' => __('admin.dashboard_title')],
    'design'       => ['url' => base_url('admin/settings/design'),'icon' => 'fa-solid fa-palette',          'label' => __('admin.settings_design')],
    'email'        => ['url' => base_url('admin/email'),          'icon' => 'fa-solid fa-envelope',         'label' => __('admin.settings_email')],
    'pdf'          => ['url' => base_url('admin/pdf-settings'),   'icon' => 'fa-solid fa-file-pdf',         'label' => __('admin.settings_pdf')],
    'security'     => ['url' => base_url('admin/settings/security'), 'icon' => 'fa-solid fa-lock',          'label' => __('admin.settings_security')],
    'cleanup'      => ['url' => base_url('admin/settings/cleanup'),  'icon' => 'fa-solid fa-broom',         'label' => __('admin.settings_cleanup')],
];
?>
<aside class="admin-sidebar">
    <nav aria-label="Admin-Navigation">
        <?php foreach ($navItems as $key => $item): ?>
        <a href="<?= h($item['url']) ?>"
           class="<?= $currentAdminPage === $key ? 'active' : '' ?>">
            <i class="<?= h($item['icon']) ?>" aria-hidden="true" style="width:1.1em;text-align:center;margin-right:.4em;"></i>
            <?= h($item['label']) ?>
        </a>
        <?php endforeach; ?>

        <hr style="border:none;border-top:1px solid var(--border);margin:.5rem 1rem;">

        <form method="post" action="<?= h(base_url('admin/logout')) ?>" style="padding:0 .75rem .25rem;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;justify-content:flex-start;">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true" style="width:1.1em;text-align:center;margin-right:.4em;"></i>
                <?= h(__('admin.logout')) ?>
            </button>
        </form>
    </nav>
</aside>
