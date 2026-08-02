<?php
/**
 * Install form template.
 * Variables: $errors (array), $post (array of previous field values).
 * __() falls back to JSON (de.json) even when DB isn't available yet.
 */
$post = $post ?? [];
?>
<div style="max-width:560px;margin-inline:auto;">

    <h1 style="margin-bottom:1.5rem;">
        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true" style="color:var(--primary-color);margin-right:.5rem;"></i>
        <?= h(__('install.title')) ?>
    </h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert" tabindex="-1" data-autofocus>
        <strong><?= h(__('install.error_heading')) ?></strong>
        <ul>
            <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <!-- Database connection -->
        <div class="card">
            <h2 style="font-size:1.125rem;margin-bottom:1rem;">
                <i class="fa-solid fa-database" aria-hidden="true" style="color:var(--primary-color);margin-right:.4rem;"></i>
                <?= h(__('install.db_section')) ?>
            </h2>

            <div class="form-group">
                <label class="form-label" for="db_host"><?= h(__('install.db_host')) ?></label>
                <input class="form-input" type="text" id="db_host" name="db_host"
                       value="<?= h($post['db_host'] ?? 'localhost') ?>"
                       placeholder="<?= h(__('install.db_host_placeholder')) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="db_name"><?= h(__('install.db_name')) ?></label>
                <input class="form-input" type="text" id="db_name" name="db_name"
                       value="<?= h($post['db_name'] ?? '') ?>"
                       placeholder="mitbringliste" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="db_user"><?= h(__('install.db_user')) ?></label>
                <input class="form-input" type="text" id="db_user" name="db_user"
                       value="<?= h($post['db_user'] ?? '') ?>" required autocomplete="username">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="db_pass"><?= h(__('install.db_pass')) ?></label>
                <div style="display:flex;gap:.375rem;">
                    <input class="form-input" type="password" id="db_pass" name="db_pass"
                           autocomplete="off"
                           spellcheck="false"
                           autocorrect="off"
                           autocapitalize="off"
                           style="flex:1;font-family:monospace;">
                    <button type="button"
                            style="flex-shrink:0;padding:.5rem .75rem;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg);cursor:pointer;"
                            aria-label="<?= h(__('common.password_show')) ?>"
                            data-toggle-password="db_pass"
                            data-show-label="<?= h(__('common.password_show')) ?>"
                            data-hide-label="<?= h(__('common.password_hide')) ?>">👁</button>
                </div>
                <p class="form-hint">
                    Kann leer bleiben, falls kein Passwort gesetzt ist.
                    Alle Sonderzeichen werden unterstützt.
                </p>
            </div>
        </div>

        <!-- Admin account -->
        <div class="card">
            <h2 style="font-size:1.125rem;margin-bottom:1rem;">
                <i class="fa-solid fa-user-shield" aria-hidden="true" style="color:var(--primary-color);margin-right:.4rem;"></i>
                <?= h(__('install.admin_section')) ?>
            </h2>

            <div class="form-group">
                <label class="form-label" for="admin_email"><?= h(__('install.admin_email')) ?></label>
                <input class="form-input" type="email" id="admin_email" name="admin_email"
                       value="<?= h($post['admin_email'] ?? '') ?>"
                       placeholder="admin@example.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label class="form-label" for="admin_password"><?= h(__('install.admin_password')) ?></label>
                <input class="form-input" type="password" id="admin_password" name="admin_password"
                       required minlength="8" autocomplete="new-password">
                <p class="form-hint"><?= h(__('validation.min_length', ['min' => '8'])) ?></p>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="admin_password_confirm"><?= h(__('install.admin_password_confirm')) ?></label>
                <input class="form-input" type="password" id="admin_password_confirm"
                       name="admin_password_confirm" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="font-size:1rem;padding:.75rem;">
            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
            <?= h(__('install.submit')) ?>
        </button>
    </form>
</div>
