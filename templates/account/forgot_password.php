<?php
/**
 * Forgot-password request form.
 */
?>
<div style="max-width:420px;margin-inline:auto;padding-top:3rem;">

    <div style="text-align:center;margin-bottom:2rem;">
        <i class="fa-solid fa-key"
           aria-hidden="true"
           style="font-size:2.5rem;color:var(--primary-color);"></i>
        <h1 style="font-size:1.5rem;margin-top:.5rem;">Passwort vergessen</h1>
        <p class="text-muted text-sm">Gib deine E-Mail-Adresse ein, um einen Link zum Zurücksetzen zu erhalten.</p>
    </div>

    <div class="card">
        <form method="post" action="<?= h(base_url('forgot-password')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email"><?= h(__('admin.email')) ?></label>
                <input class="form-input" type="email" id="email" name="email"
                       autocomplete="username" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Link anfordern
            </button>
        </form>
    </div>

    <p class="text-center text-sm" style="margin-top:1rem;">
        <a href="<?= h(base_url('admin/login')) ?>" style="color:var(--text-muted);">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Zurück zum Login
        </a>
    </p>

</div>
