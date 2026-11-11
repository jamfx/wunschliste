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
        <h1 style="font-size:1.5rem;margin-top:.5rem;"><?= h(__('account.setup_title')) ?></h1>
        <p class="text-muted text-sm"><?= __('account.setup_intro', ['email' => '<strong>' . h($email) . '</strong>']) ?></p>
    </div>

    <div class="card">
        <form method="post" action="<?= h(base_url('invite/' . $token)) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="password"><?= h(__('admin.password')) ?></label>
                <input class="form-input" type="password" id="password" name="password"
                       autocomplete="new-password" minlength="8" required autofocus>
                <p class="form-hint"><?= h(__('account.password_min_hint')) ?></p>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm"><?= h(__('account.password_confirm')) ?></label>
                <input class="form-input" type="password" id="password_confirm" name="password_confirm"
                       autocomplete="new-password" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <?= h(__('account.activate')) ?>
            </button>
        </form>
    </div>

</div>
