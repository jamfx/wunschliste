<?php
/**
 * Mail an den Vorschlagenden: Entscheidung über einen Mitbringsel-Vorschlag.
 * Variablen: $event, $item, $approved, $note, $guestUrl, $t (Übersetzer), $locale
 */
$e     = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$color = $approved ? '#16a34a' : '#6b7280';
?>
<!DOCTYPE html>
<html lang="<?= $e($locale) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($t($approved ? 'mail.decision_title_approved' : 'mail.decision_title_rejected')) ?></title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  .head{background:<?= $color ?>;padding:24px 32px}
  .head h1{margin:0;color:#fff;font-size:1.25rem}
  .body{padding:24px 32px}
  .item-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:16px 20px;margin:16px 0}
  .item-name{font-size:1.125rem;font-weight:bold;margin:0 0 6px}
  .meta{font-size:.875rem;color:#666;margin:0}
  .note{background:#fffbeb;border-left:3px solid #d97706;padding:10px 14px;margin:16px 0;white-space:pre-wrap}
  .link-btn{display:inline-block;margin-top:20px;padding:10px 20px;background:<?= $color ?>;color:#fff;text-decoration:none;border-radius:6px;font-size:.9375rem}
  .footer{padding:16px 32px;border-top:1px solid #eee;font-size:.8125rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1><?= $approved ? '&#x2705; ' : '' ?><?= $e($t($approved ? 'mail.decision_title_approved' : 'mail.decision_title_rejected')) ?></h1>
  </div>
  <div class="body">
    <p><?= $e($t('mail.decision_hello')) ?><br>
    <?= $t($approved ? 'mail.decision_intro_approved' : 'mail.decision_intro_rejected', ['event' => '<strong>' . $e($event['title']) . '</strong>']) ?></p>

    <div class="item-box">
      <p class="item-name"><?= $e($item['name']) ?></p>
      <?php if ($approved && (int) $item['quantity'] > 1): ?>
      <p class="meta"><?= $e($t('mail.decision_quantity', ['quantity' => (int) $item['quantity']])) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($note !== ''): ?>
    <p style="margin-bottom:4px;"><strong><?= $e($t('mail.decision_note')) ?></strong></p>
    <div class="note"><?= $e($note) ?></div>
    <?php endif; ?>

    <?php if ($approved): ?>
    <a class="link-btn" href="<?= $e($guestUrl) ?>"><?= $e($t('mail.decision_open_list')) ?></a>
    <?php endif; ?>

    <p class="meta" style="margin-top:24px;"><?= $e($t('mail.decision_data_note')) ?></p>
  </div>
  <div class="footer">
    <?= $e($t('mail.decision_footer', ['site' => site_title()])) ?>
  </div>
</div>
</body>
</html>
