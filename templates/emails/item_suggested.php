<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Neuer Vorschlag</title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  .head{background:#d97706;padding:24px 32px}
  .head h1{margin:0;color:#fff;font-size:1.25rem}
  .body{padding:24px 32px}
  .item-box{background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:16px 20px;margin:16px 0}
  .item-name{font-size:1.125rem;font-weight:bold;margin:0 0 6px}
  .meta{font-size:.875rem;color:#666;margin:0}
  .link-btn{display:inline-block;margin-top:20px;padding:10px 20px;background:#d97706;color:#fff;text-decoration:none;border-radius:6px;font-size:.9375rem}
  .footer{padding:16px 32px;border-top:1px solid #eee;font-size:.8125rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>&#x1F4A1; Neuer Mitbringsel-Vorschlag</h1>
  </div>
  <div class="body">
    <p>Hallo,<br>
    ein Gast hat ein neues Mitbringsel für deine Liste
    <strong><?= htmlspecialchars($event['title'], ENT_QUOTES) ?></strong> vorgeschlagen:</p>

    <div class="item-box">
      <p class="item-name"><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></p>
      <p class="meta">&#x1F464; Vorgeschlagen von: <?= htmlspecialchars($item['suggested_by_guest'], ENT_QUOTES) ?></p>
    </div>

    <p>Du kannst den Vorschlag in der Event-Verwaltung freigeben oder ablehnen.</p>

    <a class="link-btn"
       href="<?= htmlspecialchars($adminUrl, ENT_QUOTES) ?>">
      Vorschlag prüfen
    </a>
  </div>
  <div class="footer">
    Diese E-Mail wurde automatisch von <?= htmlspecialchars(site_title(), ENT_QUOTES) ?> gesendet.
  </div>
</div>
</body>
</html>
