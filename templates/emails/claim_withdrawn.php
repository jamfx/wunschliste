<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($claim['guest_name'], ENT_QUOTES) ?> hat <?= htmlspecialchars($item['name'], ENT_QUOTES) ?> zurückgezogen</title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  .head{background:#B45309;padding:24px 32px}
  .head h1{margin:0;color:#fff;font-size:1.125rem;line-height:1.4}
  .body{padding:24px 32px}
  .item-box{background:#fff7ed;border:1px solid #fdba74;border-radius:6px;padding:16px 20px;margin:16px 0}
  .item-name{font-size:1.125rem;font-weight:bold;margin:0 0 8px;color:#7c2d12}
  .meta{font-size:.875rem;color:#555;margin:0 0 4px}
  .link-btn{display:inline-block;margin-top:20px;padding:10px 20px;background:<?= htmlspecialchars($primaryColor ?? '#4F46E5', ENT_QUOTES) ?>;color:#fff;text-decoration:none;border-radius:6px;font-size:.9375rem}
  .footer{padding:16px 32px;border-top:1px solid #eee;font-size:.8125rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>&#x21A9;&#xFE0F; <?= htmlspecialchars($claim['guest_name'], ENT_QUOTES) ?> hat die Übernahme von <?= htmlspecialchars($item['name'], ENT_QUOTES) ?> bei <?= htmlspecialchars($event['title'], ENT_QUOTES) ?> zurückgezogen</h1>
  </div>
  <div class="body">
    <p>Eine zuvor gemeldete Übernahme für deine Liste
    <strong><?= htmlspecialchars($event['title'], ENT_QUOTES) ?></strong>
    <?php if (!empty($event['event_date'])): ?>
    am <strong><?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES) ?></strong>
    <?php endif; ?>
    wurde soeben wieder zurückgenommen:</p>

    <div class="item-box">
      <p class="item-name">&#x1F4E6; <?= htmlspecialchars($item['name'], ENT_QUOTES) ?></p>
      <p class="meta">&#x1F464; <strong><?= htmlspecialchars($claim['guest_name'], ENT_QUOTES) ?></strong> bringt es nun doch nicht mit</p>
      <?php if ((int)$claim['quantity'] > 1): ?>
      <p class="meta">Menge: <?= (int)$claim['quantity'] ?></p>
      <?php endif; ?>
    </div>

    <p>Das Mitbringsel ist jetzt wieder offen und kann von jemand anderem übernommen werden.</p>

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
