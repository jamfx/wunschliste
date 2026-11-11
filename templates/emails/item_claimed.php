<?php
$e    = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES);
$b    = static fn($s): string => '<strong>' . htmlspecialchars((string) $s, ENT_QUOTES) . '</strong>';
$date = static fn($d): string => date($t('format.date'), strtotime((string) $d));
?>
<!DOCTYPE html>
<html lang="<?= $e($locale) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($t('mail.claimed_subject', ['guest' => $claim['guest_name'], 'item' => $item['name'], 'event' => $event['title']])) ?></title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  .head{background:<?= htmlspecialchars($primaryColor ?? '#4F46E5', ENT_QUOTES) ?>;padding:24px 32px}
  .head h1{margin:0;color:#fff;font-size:1.125rem;line-height:1.4}
  .body{padding:24px 32px}
  .item-box{background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:16px 20px;margin:16px 0}
  .item-name{font-size:1.125rem;font-weight:bold;margin:0 0 8px;color:#14532d}
  .meta{font-size:.875rem;color:#555;margin:0 0 4px}
  .link-btn{display:inline-block;margin-top:20px;padding:10px 20px;background:<?= htmlspecialchars($primaryColor ?? '#4F46E5', ENT_QUOTES) ?>;color:#fff;text-decoration:none;border-radius:6px;font-size:.9375rem}
  .footer{padding:16px 32px;border-top:1px solid #eee;font-size:.8125rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>&#x2705; <?= $e($t('mail.claimed_subject', ['guest' => $claim['guest_name'], 'item' => $item['name'], 'event' => $event['title']])) ?></h1>
  </div>
  <div class="body">
    <?php if (!empty($event['event_date'])): ?>
    <p><?= $t('mail.claimed_intro_dated', ['event' => $b($event['title']), 'date' => $b($date($event['event_date']))]) ?></p>
    <?php else: ?>
    <p><?= $t('mail.claimed_intro', ['event' => $b($event['title'])]) ?></p>
    <?php endif; ?>

    <div class="item-box">
      <p class="item-name">&#x1F4E6; <?= $e($item['name']) ?></p>
      <p class="meta">&#x1F464; <?= $t('mail.claimed_brings', ['guest' => $b($claim['guest_name'])]) ?></p>
      <?php if ((int)$claim['quantity'] > 1): ?>
      <p class="meta"><?= $e($t('mail.quantity', ['quantity' => (int)$claim['quantity']])) ?></p>
      <?php endif; ?>
    </div>

    <a class="link-btn" href="<?= $e($adminUrl) ?>">
      <?= $e($t('mail.manage_event')) ?>
    </a>
  </div>
  <div class="footer">
    <?= $e($t('mail.auto_footer', ['site' => site_title()])) ?>
  </div>
</div>
</body>
</html>
