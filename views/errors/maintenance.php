<?php

declare(strict_types=1);

$appLabel = $appName ?? (function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena');
$variant = (string) ($maintenance['ui_variant'] ?? 'military');
$animated = ((int) ($maintenance['ui_animation'] ?? 1)) === 1;
$variantClass = in_array($variant, ['military', 'minimal', 'neon', 'status'], true) ? $variant : 'military';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Maintenance en cours', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { --bg:#0b1220; --card:#101a2f; --fg:#e2e8f0; --accent:#34d399; }
        body.variant-minimal { --bg:#f8fafc; --card:#ffffff; --fg:#0f172a; --accent:#2563eb; }
        body.variant-neon { --bg:#07070f; --card:#130a24; --fg:#f5d0fe; --accent:#22d3ee; }
        body.variant-status { --bg:#0f172a; --card:#111827; --fg:#d1fae5; --accent:#f59e0b; }
        *{box-sizing:border-box} body{margin:0;min-height:100vh;background:var(--bg);color:var(--fg);font-family:Inter,Segoe UI,Arial,sans-serif;display:grid;place-items:center;padding:24px}
        .card{width:min(900px,100%);background:var(--card);border:1px solid color-mix(in srgb, var(--fg) 18%, transparent);border-radius:22px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35)}
        .head{padding:28px 30px;border-bottom:1px solid color-mix(in srgb, var(--fg) 20%, transparent);}
        .kicker{font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--accent);font-weight:800}
        .title{margin:10px 0 0;font-size:clamp(1.6rem,4vw,2.2rem);font-weight:900}
        .content{padding:28px 30px 32px;line-height:1.6}
        .alert{padding:14px 16px;border-radius:12px;background:color-mix(in srgb, var(--accent) 20%, transparent);border:1px solid color-mix(in srgb, var(--accent) 50%, transparent)}
        .meta{margin-top:18px;font-size:.9rem;opacity:.9}
        .pulse{display:inline-block;width:10px;height:10px;border-radius:999px;background:var(--accent);margin-right:8px}
        .animated .pulse{animation:pulse 1.2s infinite}
        .animated .scan{position:relative;overflow:hidden}
        .animated .scan:after{content:"";position:absolute;inset:-120% -30%;background:linear-gradient(120deg,transparent,rgba(255,255,255,.15),transparent);animation:scan 3.5s linear infinite}
        @keyframes pulse{0%{transform:scale(.9);opacity:.45}50%{transform:scale(1.35);opacity:1}100%{transform:scale(.9);opacity:.45}}
        @keyframes scan{from{transform:translateX(-60%)}to{transform:translateX(60%)}}
    </style>
</head>
<body class="variant-<?= htmlspecialchars($variantClass, ENT_QUOTES, 'UTF-8') ?> <?= $animated ? 'animated' : '' ?>">
<div class="card scan">
    <div class="head">
        <div class="kicker"><?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?> · Maintenance</div>
        <h1 class="title"><span class="pulse" aria-hidden="true"></span><?= htmlspecialchars($title ?? 'Maintenance en cours', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <div class="content">
        <div class="alert">
            <?= nl2br(htmlspecialchars($message ?? 'Le service est momentanément indisponible.', ENT_QUOTES, 'UTF-8')) ?>
        </div>

        <?php if (!empty($endsAt)): ?>
            <p class="meta"><strong>Fin prévisionnelle :</strong> <?= htmlspecialchars((string) $endsAt, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if (!empty($code)): ?>
            <p class="meta"><strong>Code maintenance :</strong> <code><?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?></code></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
