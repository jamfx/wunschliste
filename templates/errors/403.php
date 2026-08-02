<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 – Zugriff verweigert</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f9fafb; color: #111827;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { text-align: center; padding: 2rem; }
        h1 { font-size: 4rem; color: #d1d5db; margin: 0; }
        p  { color: #6b7280; margin: .5rem 0 1.5rem; }
        a  { color: #4F46E5; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="box">
    <h1>403</h1>
    <p>Zugriff verweigert.</p>
    <a href="<?= function_exists('base_url') ? base_url() : '/' ?>">← Zur Startseite</a>
</div>
</body>
</html>
