<?php

declare(strict_types=1);

$isEn = function_exists('locale') && locale() === 'en';
$appLabel = function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena';
$pageTitle = $isEn ? 'The forum is temporarily unavailable' : 'Le forum est temporairement indisponible';
$lead = $isEn
    ? 'Discussions are paused for the time being. Nothing is required of you.'
    : 'Les discussions sont en pause pour le moment. Rien n’est demandé de votre part.';
$detail = $isEn
    ? 'You can return to the portal and continue your other activities. Existing conversations are kept and will be available again when the forum reopens.'
    : 'Vous pouvez revenir au portail et poursuivre vos autres activités. Les conversations déjà écrites sont conservées et redeviendront accessibles à la réouverture.';
$ctaLabel = $isEn ? 'Back to the portal' : 'Retour au portail';
$kicker = $isEn ? 'Community space' : 'Espace communautaire';
$homeUrl = function_exists('url') ? url('dashboard') : '/';
$portalUrl = function_exists('url') ? url('') : '/';
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="<?= $isEn ? 'en' : 'fr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --void:#050505; --paper:#f7f7f4; --ink:#141414; --muted:#5c5c5c; --accent:#0d9b6b; --rule:rgba(20,20,20,.12); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { min-height: 100svh; background: var(--void); color: var(--ink); font-family: Inter, "Segoe UI", system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .bar { position: fixed; inset: 0 0 auto; z-index: 10; display: flex; align-items: center; justify-content: center; height: 3.5rem; border-bottom: 1px solid rgba(255,255,255,.05); background: rgba(5,5,5,.72); backdrop-filter: blur(12px); }
        .bar a { font-size: 11px; font-weight: 900; letter-spacing: .32em; text-transform: uppercase; color: #fff; text-decoration: none; }
        .stage { min-height: 100svh; display: flex; align-items: center; justify-content: center; padding: 6.5rem 1.25rem 3rem; }
        .sheet { width: min(40rem, 100%); padding: clamp(2rem, 5vw, 3.25rem) clamp(1.6rem, 4.5vw, 3rem); background: var(--paper); box-shadow: 0 30px 80px rgba(0,0,0,.45); }
        .kicker { margin: 0 0 1.35rem; font-size: .6875rem; font-weight: 600; letter-spacing: .28em; text-transform: uppercase; color: #8a8a8a; }
        h1 { margin: 0; font-weight: 900; text-transform: uppercase; letter-spacing: -.045em; line-height: .95; font-size: clamp(2rem, 6vw, 3.4rem); }
        .copy { margin-top: 1.85rem; display: grid; gap: 1.05rem; }
        .copy p { margin: 0; font-size: .98rem; font-weight: 500; line-height: 1.7; color: var(--muted); }
        .foot { margin-top: 2.35rem; padding-top: 1.35rem; border-top: 1px solid var(--rule); display: flex; flex-wrap: wrap; gap: 1rem; }
        .cta { display: inline-flex; align-items: center; min-height: 2.75rem; padding: .7rem 1.2rem; border: 1px solid rgba(20,20,20,.28); color: var(--ink); font-size: .6875rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; text-decoration: none; }
        .cta:hover { background: rgba(20,20,20,.04); }
        .cta--fill { background: var(--ink); color: #fff; border-color: var(--ink); }
        .cta--fill:hover { background: #2a2a2a; }
    </style>
</head>
<body>
    <header class="bar">
        <a href="<?= htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></a>
    </header>
    <main class="stage">
        <article class="sheet">
            <p class="kicker"><?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?></p>
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="copy">
                <p><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="foot">
                <a class="cta cta--fill" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?></a>
                <a class="cta" href="<?= htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $isEn ? 'Home' : 'Accueil' ?></a>
            </div>
        </article>
    </main>
</body>
</html>
