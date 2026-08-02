<?php
/**
 * Admin settings overview.
 */
$cards = [
    ['url' => base_url('admin/settings/design'), 'icon' => 'fa-solid fa-palette',       'color' => '#8B5CF6',
     'title' => __('admin.settings_design'),       'desc' => 'Primärfarbe, Logo, Impressum, Datenschutz'],
    ['url' => base_url('admin/email'),            'icon' => 'fa-solid fa-envelope',      'color' => '#3B82F6',
     'title' => __('admin.settings_email'),        'desc' => 'SMTP, Brevo API, Absender-Adresse'],
    ['url' => base_url('admin/pdf-settings'),     'icon' => 'fa-solid fa-file-pdf',      'color' => '#EF4444',
     'title' => __('admin.settings_pdf'),          'desc' => 'Seitengröße, Schrift, Logo, Fußzeile'],
    ['url' => base_url('admin/settings/security'), 'icon' => 'fa-solid fa-lock',          'color' => '#EC4899',
     'title' => __('admin.settings_security'),       'desc' => 'Sperr-Limits, Bedenkzeit, Zwei-Faktor-Authentifizierung'],
];
?>
<div class="admin-layout">
    <?php include BASE_DIR . '/templates/partials/admin_sidebar.php'; ?>

    <div class="admin-content">
        <h1 style="font-size:1.5rem;margin-bottom:1.25rem;">
            <i class="fa-solid fa-sliders" aria-hidden="true" style="color:var(--primary-color);"></i>
            <?= h(__('admin.settings_title')) ?>
        </h1>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;">
            <?php foreach ($cards as $card): ?>
            <a href="<?= h($card['url']) ?>"
               class="card"
               style="text-decoration:none;color:var(--text);transition:transform .15s,box-shadow .15s;"
               onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'"
               onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div style="font-size:1.75rem;margin-bottom:.75rem;">
                    <i class="<?= h($card['icon']) ?>" aria-hidden="true"
                       style="color:<?= h($card['color']) ?>;"></i>
                </div>
                <h2 style="font-size:1rem;margin-bottom:.375rem;"><?= h($card['title']) ?></h2>
                <p class="text-muted text-sm" style="margin:0;"><?= h($card['desc']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
