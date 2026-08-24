<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Einladung</title>
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
    <h1>&#x1F511; Du wurdest eingeladen</h1>
  </div>
  <div class="body">
    <p>Hallo,<br>
    du wurdest eingeladen, einen Account bei <strong><?= htmlspecialchars(site_title(), ENT_QUOTES) ?></strong> einzurichten.
    Klicke auf den folgenden Link, um ein Passwort festzulegen:</p>

    <a class="link-btn" href="<?= htmlspecialchars($inviteUrl, ENT_QUOTES) ?>">
      Account einrichten
    </a>

    <p style="margin-top:24px;font-size:.875rem;color:#666;">
      Dieser Link ist 24 Stunden gültig. Falls du diese Einladung nicht erwartet hast, kannst du diese E-Mail ignorieren.
    </p>
  </div>
  <div class="footer">
    Diese E-Mail wurde automatisch von <?= htmlspecialchars(site_title(), ENT_QUOTES) ?> gesendet.
  </div>
</div>
</body>
</html>
