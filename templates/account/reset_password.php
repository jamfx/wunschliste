<?php
/**
 * Sets a new password from a valid password-reset token.
 * Variables: $token (string)
 */
?>
<div style="max-width:420px;margin-inline:auto;padding-top:3rem;">

    <div style="text-align:center;margin-bottom:2rem;">
        <i class="fa-solid fa-key"
           aria-hidden="true"
           style="font-size:2.5rem;color:var(--primary-color);"></i>
        <h1 style="font-size:1.5rem;margin-top:.5rem;">Neues Passwort festlegen</h1>
    </div>

    <div class="card">
        <form method="post" action="<?= h(base_url('reset-password/' . $token)) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="password">Neues Passwort</label>
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
                Passwort speichern
            </button>
        </form>
    </div>

</div>
