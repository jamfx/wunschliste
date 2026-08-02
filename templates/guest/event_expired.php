<?php
/**
 * Abgelaufenes Event.
 * Variables: $event (array)
 */
?>

<div style="max-width:500px;margin-inline:auto;text-align:center;padding:3rem 1rem;">
    <div style="font-size:3.5rem;margin-bottom:1rem;">
        <i class="fa-solid fa-calendar-xmark" aria-hidden="true" style="color:var(--text-muted);"></i>
    </div>
    <h1 style="font-size:1.5rem;margin-bottom:.75rem;color:var(--text);">
        <?= h(__('guest.event_expired_title')) ?>
    </h1>
    <p style="color:var(--text-muted);margin-bottom:.5rem;">
        <?= h(__('guest.event_expired_desc')) ?>
    </p>
    <?php if (!empty($event['title'])): ?>
    <p class="text-muted text-sm" style="margin-bottom:2rem;">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
        <?= h($event['title']) ?>
    </p>
    <?php endif; ?>
    <a href="<?= h(base_url()) ?>" class="btn btn-primary">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <?= h(__('guest.create_own_event')) ?>
    </a>
</div>
