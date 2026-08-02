<?php
/**
 * Passwortsperre für ein Event mit access_password_hash.
 * Variables: $guestToken (string), $errors (array)
 */
$errors = $errors ?? [];
?>

<div style="max-width:400px;margin-inline:auto;text-align:center;padding:3rem 1rem;">
    <div style="font-size:3.5rem;margin-bottom:1rem;">
        <i class="fa-solid fa-lock" aria-hidden="true" style="color:var(--primary-color);"></i>
    </div>
    <h1 style="font-size:1.5rem;margin-bottom:.75rem;color:var(--text);">
        <?= h(__('guest.locked_title')) ?>
    </h1>
    <p class="text-muted" style="margin-bottom:1.5rem;">
        <?= h(__('guest.locked_desc')) ?>
    </p>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert" style="text-align:left;margin-bottom:1rem;" tabindex="-1" data-autofocus>
        <ul style="margin:0;padding-left:1.25rem;">
            <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= h(base_url('g/' . $guestToken . '/unlock')) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="access_password" style="text-align:left;display:block;">
                <?= h(__('guest.locked_password_label')) ?>
            </label>
            <input class="form-input" type="password" id="access_password" name="access_password"
                   autofocus autocomplete="off" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">
            <i class="fa-solid fa-unlock" aria-hidden="true"></i>
            <?= h(__('guest.locked_submit')) ?>
        </button>
    </form>
</div>
