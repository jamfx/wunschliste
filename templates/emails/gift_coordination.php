<?php
/**
 * Gift Mode: Koordinationsmail an alle Gäste, die sich für ein
 * Multi-Personen-Mitbringsel opt-in eingetragen haben, sobald es vollständig
 * übernommen ist. Enthält nur Vornamen/Gastnamen — keine weiteren Daten.
 * Variables: $event, $item, $participantNames (array<string>), $guestUrl
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Geschenk-Koordination: <?= htmlspecialchars($item['name'], ENT_QUOTES) ?></title>
<style>
  body{margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#333}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  .head{background:<?= htmlspecialchars($primaryColor ?? '#4F46E5', ENT_QUOTES) ?>;padding:24px 32px}
  .head h1{margin:0;color:#fff;font-size:1.125rem;line-height:1.4}
  .body{padding:24px 32px}
  .item-box{background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:16px 20px;margin:16px 0}
  .item-name{font-size:1.125rem;font-weight:bold;margin:0 0 8px;color:#14532d}
  .meta{font-size:.875rem;color:#555;margin:0 0 4px}
  .participants{margin:0;padding-left:20px}
  .participants li{margin:2px 0}
  .link-btn{display:inline-block;margin-top:20px;padding:10px 20px;background:<?= htmlspecialchars($primaryColor ?? '#4F46E5', ENT_QUOTES) ?>;color:#fff;text-decoration:none;border-radius:6px;font-size:.9375rem}
  .footer{padding:16px 32px;border-top:1px solid #eee;font-size:.8125rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>&#x1F381; Alle Plätze für "<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>" sind besetzt!</h1>
  </div>
  <div class="body">
    <p>Gute Nachrichten! Das gemeinsame Mitbringsel
    <strong><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></strong>
    für <strong><?= htmlspecialchars($event['title'], ENT_QUOTES) ?></strong>
    <?php if (!empty($event['event_date'])): ?>
    am <strong><?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES) ?></strong>
    <?php endif; ?>
    ist jetzt vollständig übernommen. Zeit, sich mit den anderen abzustimmen!
    </p>

    <div class="item-box">
      <p class="item-name">&#x1F4E6; <?= htmlspecialchars($item['name'], ENT_QUOTES) ?></p>
      <p class="meta">Ihr seid:</p>
      <ul class="participants">
        <?php foreach ($participantNames as $name): ?>
        <li><?= htmlspecialchars($name, ENT_QUOTES) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <p class="meta">Sprecht euch am besten direkt untereinander ab, wer was übernimmt.</p>

    <a class="link-btn" href="<?= htmlspecialchars($guestUrl, ENT_QUOTES) ?>">
      Zur Liste
    </a>
  </div>
  <div class="footer">
    Diese E-Mail wurde automatisch von <?= htmlspecialchars(site_title(), ENT_QUOTES) ?> gesendet. Deine E-Mail-Adresse
    wurde ausschließlich für diese Benachrichtigung verwendet und wird nun gelöscht.
  </div>
</div>
</body>
</html>
