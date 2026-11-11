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
<title><?= $e($t('mail.complete_tag')) ?></title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  .head{background:#16a34a;padding:24px 32px}
  .head h1{margin:0;color:#fff;font-size:1.25rem}
  .body{padding:24px 32px}
  .big-check{font-size:3rem;text-align:center;margin:8px 0 16px}
  .link-btn{display:inline-block;margin-top:20px;padding:10px 20px;background:#16a34a;color:#fff;text-decoration:none;border-radius:6px;font-size:.9375rem}
  .footer{padding:16px 32px;border-top:1px solid #eee;font-size:.8125rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>&#x1F389; <?= $e($t('mail.complete_title')) ?></h1>
  </div>
  <div class="body">
    <div class="big-check">&#x2705;</div>
    <p><?= $t('mail.complete_intro', ['event' => $b($event['title'])]) ?></p>

    <?php if (!empty($event['event_date'])): ?>
    <p><?= $t('mail.date_line', ['date' => $b($date($event['event_date']))]) ?></p>
    <?php endif; ?>

    <a class="link-btn"
       href="<?= $e($adminUrl) ?>">
      <?= $e($t('mail.view_event')) ?>
    </a>
  </div>
  <div class="footer">
    <?= $e($t('mail.auto_footer', ['site' => site_title()])) ?>
  </div>
</div>
</body>
</html>
