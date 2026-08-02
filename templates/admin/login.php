<?php
/**
 * Admin login page — no sidebar needed.
 */
?>
<div style="max-width:420px;margin-inline:auto;padding-top:3rem;">

    <div style="text-align:center;margin-bottom:2rem;">
        <i class="fa-solid fa-shield-halved"
           aria-hidden="true"
           style="font-size:2.5rem;color:var(--primary-color);"></i>
        <h1 style="font-size:1.5rem;margin-top:.5rem;"><?= h(__('admin.login_title')) ?></h1>
    </div>

    <div class="card">
        <form method="post" action="<?= h(base_url('admin/login')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email"><?= h(__('admin.email')) ?></label>
                <input class="form-input"
                       type="email" id="email" name="email"
                       value="<?= h($_POST['email'] ?? '') ?>"
                       autocomplete="username"
                       required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password"><?= h(__('admin.password')) ?></label>
                <input class="form-input"
                       type="password" id="password" name="password"
                       autocomplete="current-password"
                       required>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                <?= h(__('admin.login_button')) ?>
            </button>
        </form>
    </div>

</div>
