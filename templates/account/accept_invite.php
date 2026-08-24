<?php
/**
 * Invite acceptance — sets the initial password for an invited account.
 * Variables: $email (string), $token (string)
 */
?>
<div style="max-width:420px;margin-inline:auto;padding-top:3rem;">

    <div style="text-align:center;margin-bottom:2rem;">
        <i class="fa-solid fa-user-check"
           aria-hidden="true"
           style="font-size:2.5rem;color:var(--primary-color);"></i>
        <h1 style="font-size:1.5rem;margin-top:.5rem;">Account einrichten</h1>
        <p class="text-muted text-sm">Lege ein Passwort für <strong><?= h($email) ?></strong> fest.</p>
    </div>

    <div class="card">
        <form method="post" action="<?= h(base_url('invite/' . $token)) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="password"><?= h(__('admin.password')) ?></label>
                <input class="form-input" type="password" id="password" name="password"
                       autocomplete="new-password" minlength="8" required autofocus>
                <p class="form-hint">Mindestens 8 Zeichen.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Passwort bestätigen</label>
                <input class="form-input" type="password" id="password_confirm" name="password_confirm"
                       autocomplete="new-password" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                Account aktivieren
            </button>
        </form>
    </div>

</div>
