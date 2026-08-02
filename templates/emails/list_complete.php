<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Liste vollständig</title>
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
    <h1>&#x1F389; Liste vollständig vergeben!</h1>
  </div>
  <div class="body">
    <div class="big-check">&#x2705;</div>
    <p>Super Neuigkeit! Alle Mitbringsel auf deiner Liste
    <strong><?= htmlspecialchars($event['title'], ENT_QUOTES) ?></strong>
    wurden vollständig beansprucht. Jeder bringt etwas mit!</p>

    <?php if (!empty($event['event_date'])): ?>
    <p>Datum: <strong><?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES) ?></strong></p>
    <?php endif; ?>

    <a class="link-btn"
       href="<?= htmlspecialchars($adminUrl, ENT_QUOTES) ?>">
      Event ansehen
    </a>
  </div>
  <div class="footer">
    Diese E-Mail wurde automatisch von <?= htmlspecialchars(site_title(), ENT_QUOTES) ?> gesendet.
  </div>
</div>
</body>
</html>
