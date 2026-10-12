<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($event['title'], ENT_QUOTES) ?></title>
<style>
<?php
$fontSize = max(8, min(24, (int) ($s['pdf_font_size'] ?? 12)));
?>
  * { box-sizing: border-box; }
  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: <?= $fontSize ?>pt;
    color: #1a1a1a;
    margin: 0;
    padding: 0;
  }
  .page {
    padding: 28px 32px;
  }
  .logo-wrap {
    margin-bottom: 12px;
  }
  .logo-wrap img {
    max-height: 48px;
    max-width: 180px;
  }
  h1 {
    font-size: <?= $fontSize + 6 ?>pt;
    margin: 0 0 4px;
    color: #1a1a1a;
  }
  .md p, .md ul, .md ol { margin: 0 0 3px; }
  .md ul, .md ol { padding-left: 14px; }
  .meta {
    font-size: <?= max(7, $fontSize - 2) ?>pt;
    color: #666;
    margin: 0 0 18px;
  }
  .progress-bar-outer {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    margin-bottom: 18px;
  }
  .progress-bar-inner {
    height: 8px;
    background: #4F46E5;
    border-radius: 4px;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
  }
  th {
    background: #f3f4f6;
    font-size: <?= max(7, $fontSize - 1) ?>pt;
    text-align: left;
    padding: 6px 8px;
    border-bottom: 2px solid #d1d5db;
    color: #374151;
  }
  td {
    padding: 6px 8px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: top;
  }
  tr:last-child td {
    border-bottom: none;
  }
  .item-full {
    background: #f0fdf4;
  }
  .check-icon {
    color: #059669;
    font-weight: bold;
  }
  .qty-badge {
    display: inline-block;
    font-size: <?= max(7, $fontSize - 2) ?>pt;
    padding: 1px 6px;
    border-radius: 3px;
    background: #e5e7eb;
    color: #374151;
  }
  .qty-badge-full {
    background: #d1fae5;
    color: #065f46;
  }
  .claimed-names {
    font-size: <?= max(7, $fontSize - 2) ?>pt;
    color: #6b7280;
    margin-top: 3px;
  }
  .footer {
    margin-top: 24px;
    font-size: <?= max(6, $fontSize - 3) ?>pt;
    color: #9ca3af;
    text-align: center;
    border-top: 1px solid #e5e7eb;
    padding-top: 8px;
  }
  .section-header {
    font-size: <?= max(8, $fontSize - 1) ?>pt;
    font-weight: bold;
    color: #4b5563;
    margin: 16px 0 6px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 4px;
  }
</style>
</head>
<body>
<div class="page">

<?php
$showLogo   = ($s['pdf_show_logo'] ?? '1') === '1';
$logoPath   = $s['logo_path'] ?? '';
$footerText = $s['pdf_footer_text'] ?? '';
$isAdmin    = $isAdmin ?? false;
$guestSeesNames = $isAdmin || ($event['guest_sees_names'] ?? false);

if ($showLogo && $logoPath !== '') {
    $absPath = BASE_DIR . '/' . $logoPath;
}
?>

<?php if ($showLogo && !empty($absPath) && file_exists($absPath)): ?>
<div class="logo-wrap">
    <?php
    $mime = mime_content_type($absPath);
    $b64  = base64_encode(file_get_contents($absPath));
    ?>
    <img src="data:<?= $mime ?>;base64,<?= $b64 ?>" alt="Logo">
</div>
<?php endif; ?>

<h1><?= htmlspecialchars($event['title'], ENT_QUOTES) ?></h1>

<div class="meta">
<?php if (!empty($event['event_date'])): ?>
    Datum: <?= htmlspecialchars(date('d.m.Y', strtotime($event['event_date'])), ENT_QUOTES) ?> &nbsp;|&nbsp;
<?php endif; ?>
    Erstellt: <?= htmlspecialchars(date('d.m.Y', strtotime($event['created_at'])), ENT_QUOTES) ?>
<?php if (!empty($event['description'])): ?>
    <div class="md"><?= md($event['description']) ?></div>
<?php endif; ?>
</div>

<?php
$approved = array_filter($items, fn($i) => $i['status'] === 'approved');
$pending  = array_filter($items, fn($i) => $i['status'] === 'pending');
// Weighted by quantity, not by item count: an item needing 20 and having 5
// claimed should move the bar, not wait until all 20 are gone.
$total    = array_sum(array_column($approved, 'quantity'));
$claimed  = array_sum(array_map(fn($i) => min($i['claims_total'], $i['quantity']), $approved));
$pct      = $total > 0 ? round(($claimed / $total) * 100) : 0;
?>

<div class="progress-bar-outer">
    <div class="progress-bar-inner" style="width:<?= $pct ?>%;"></div>
</div>
<div class="meta" style="margin-top:-14px;margin-bottom:16px;">
    <?= htmlspecialchars(__('pdf.item_progress', ['claimed' => $claimed, 'total' => $total, 'pct' => $pct]), ENT_QUOTES) ?>
</div>

<?php if (!empty($approved)): ?>
<table>
    <thead>
        <tr>
            <th style="width:45%;"><?= htmlspecialchars(__('pdf.item_column'), ENT_QUOTES) ?></th>
            <th style="width:12%;">Menge</th>
            <?php if ($guestSeesNames): ?>
            <th>Mitgebracht von</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($approved as $item):
        $isFull = $item['remaining'] === 0;
    ?>
        <tr class="<?= $isFull ? 'item-full' : '' ?>">
            <td>
                <?php if ($isFull): ?><span class="check-icon">&#10003;</span> <?php endif; ?>
                <?= htmlspecialchars($item['name'], ENT_QUOTES) ?>
            </td>
            <td>
                <span class="qty-badge <?= $isFull ? 'qty-badge-full' : '' ?>">
                    <?= (int)$item['claims_total'] ?>/<?= (int)$item['quantity'] ?>
                </span>
            </td>
            <?php if ($guestSeesNames): ?>
            <td>
            <?php foreach ($item['claims'] as $claim): ?>
                <div class="claimed-names">
                    <?= htmlspecialchars($claim['guest_name'], ENT_QUOTES) ?>
                    <?= (int)$claim['quantity'] > 1 ? ' ×' . (int)$claim['quantity'] : '' ?>
                </div>
            <?php endforeach; ?>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($isAdmin && !empty($pending)): ?>
<div class="section-header">Vorschläge (ausstehend)</div>
<table>
    <thead>
        <tr>
            <th><?= htmlspecialchars(__('pdf.item_column'), ENT_QUOTES) ?></th>
            <th>Vorgeschlagen von</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pending as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($item['suggested_by_guest'] ?? '—', ENT_QUOTES) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($footerText !== ''): ?>
<div class="footer"><?= htmlspecialchars($footerText, ENT_QUOTES) ?></div>
<?php endif; ?>

</div>
</body>
</html>
