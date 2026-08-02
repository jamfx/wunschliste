<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($claim['guest_name'], ENT_QUOTES) ?> bringt <?= htmlspecialchars($item['name'], ENT_QUOTES) ?> mit</title>
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
    <h1>&#x2705; <?= htmlspecialchars($claim['guest_name'], ENT_QUOTES) ?> bringt <?= htmlspecialchars($item['name'], ENT_QUOTES) ?> zu <?= htmlspecialchars($event['title'], ENT_QUOTES) ?> mit</h1>
  </div>
  <div class="body">
    <p>Jemand hat gerade ein Mitbringsel für deine Liste
    <strong><?= htmlspecialchars($event['title'], ENT_QUOTES) ?></strong>
    <?php if (!empty($event['event_date'])): ?>
    am <strong><?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES) ?></strong>
    <?php endif; ?>
    übernommen:</p>

    <div class="item-box">
      <p class="item-name">&#x1F4E6; <?= htmlspecialchars($item['name'], ENT_QUOTES) ?></p>
      <p class="meta">&#x1F464; <strong><?= htmlspecialchars($claim['guest_name'], ENT_QUOTES) ?></strong> bringt es mit</p>
      <?php if ((int)$claim['quantity'] > 1): ?>
      <p class="meta">Menge: <?= (int)$claim['quantity'] ?></p>
      <?php endif; ?>
    </div>

    <a class="link-btn" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES) ?>">
      Veranstaltung verwalten
    </a>
  </div>
  <div class="footer">
    Diese E-Mail wurde automatisch von <?= htmlspecialchars(site_title(), ENT_QUOTES) ?> gesendet.
  </div>
</div>
</body>
</html>
