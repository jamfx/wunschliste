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
<title><?= $e($t('mail.reset_title')) ?></title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  .head{background:#4f46e5;padding:24px 32px}
  .head h1{margin:0;color:#fff;font-size:1.25rem}
  .body{padding:24px 32px}
  .link-btn{display:inline-block;margin-top:20px;padding:10px 20px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:6px;font-size:.9375rem}
  .footer{padding:16px 32px;border-top:1px solid #eee;font-size:.8125rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>&#x1F511; <?= $e($t('mail.reset_title')) ?></h1>
  </div>
  <div class="body">
    <p><?= $e($t('mail.hello')) ?><br>
    <?= $t('mail.reset_intro', ['site' => $b(site_title())]) ?></p>

    <a class="link-btn" href="<?= $e($resetUrl) ?>">
      <?= $e($t('mail.reset_button')) ?>
    </a>

    <p style="margin-top:24px;font-size:.875rem;color:#666;">
      <?= $e($t('mail.reset_note')) ?>
    </p>
  </div>
  <div class="footer">
    <?= $e($t('mail.auto_footer', ['site' => site_title()])) ?>
  </div>
</div>
</body>
</html>
