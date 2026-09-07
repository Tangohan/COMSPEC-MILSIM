<?php

declare(strict_types=1);

$appLabel = $appName ?? (function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena');
$rawTitle = trim((string) ($title ?? ''));
$rawMessage = trim((string) ($message ?? ''));
$genericTitles = [
    '', 'Maintenance en cours', 'Maintenance globale en cours', 'Maintenance sécurité en cours',
    'Maintenance opérationnelle', 'Intervention infrastructure', 'Intervention en cours',
    'Déploiement correctif urgent',
];
$genericMessages = [
    '', 'Le service est momentanément indisponible.',
    'Maintenance en cours. Merci de réessayer dans quelques minutes.',
    "Nos équipes déploient une mise à jour stratégique.\nLe service revient très bientôt.",
];

$useBrandTitle = in_array($rawTitle, $genericTitles, true);
if ($useBrandTitle) {
    $titleLead = 'Maintenance';
    $titleAccent = 'opérationnelle';
} elseif (preg_match('/^(.*)\s+en cours$/iu', $rawTitle, $titleMatch) === 1) {
    $titleLead = trim((string) ($titleMatch[1] ?? '')) ?: 'Maintenance';
    $titleAccent = 'opérationnelle';
} else {
    $parts = array_values(array_filter(preg_split('/\s+/u', $rawTitle) ?: []));
    $titleAccent = count($parts) > 1 ? (string) array_pop($parts) : '';
    $titleLead = $parts === [] ? 'Maintenance' : implode(' ', $parts);
}

$defaultParagraphs = [
    'Le portail Athena est fermé le temps d’une intervention de fond. Nous remettons à plat la gestion des communautés : un seul niveau d’accès par membre, des emplois qui suivent l’organigramme, des dossiers plus simples à tenir.',
    'Tous les accès au site, y compris Arma 3, le téléphone ATAK, la carte tactique et les API, restent fermés jusqu’à la réouverture.',
    'Nous rouvrons dès que les contrôles sont terminés. Merci de votre patience, et de la confiance que vous accordez à COMSPEC.',
];
$paragraphs = [];
if ($rawMessage !== '' && !in_array($rawMessage, $genericMessages, true)) {
    foreach (preg_split("/\R{2,}|\R/", $rawMessage) ?: [] as $line) {
        if (($line = trim((string) $line)) !== '') {
            $paragraphs[] = $line;
        }
    }
}
$paragraphs = $paragraphs ?: $defaultParagraphs;

$endsLabel = '';
if (!empty($endsAt) && ($timestamp = strtotime((string) $endsAt)) !== false) {
    $endsLabel = date('d/m/Y · H:i', $timestamp);
}

$homeUrl = function_exists('url') ? url('') : '/';
$base = function_exists('url') ? rtrim((string) url(''), '/') : '';
$heroSrc = $base . '/assets/images/fog-team.jpg';
$pageTitle = $useBrandTitle ? 'Maintenance opérationnelle' : $rawTitle;
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#080b0a">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($base !== ''): ?>
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/icons/athena-192.png">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;family=Space+Mono:wght@400;700&amp;display=swap" rel="stylesheet">
    <style>
        :root { --night:#080b0a; --paper:#f2f0e9; --ink:#111513; --muted:#59605c; --green:#6cffb0; --green-dark:#087345; --line:rgba(255,255,255,.14); }
        * { box-sizing: border-box; }
        html, body { margin:0; min-width:0; min-height:100%; overflow-x:hidden; }
        body { min-height:100svh; background:var(--night); color:#fff; font-family:Inter,"Segoe UI",sans-serif; -webkit-font-smoothing:antialiased; }
        .shell { position:relative; min-height:100svh; isolation:isolate; overflow:hidden; }
        .shell::before { content:""; position:absolute; inset:0; z-index:-3; background:url('<?= htmlspecialchars($heroSrc, ENT_QUOTES, 'UTF-8') ?>') center 38%/cover no-repeat; filter:grayscale(1) contrast(1.08); transform:scale(1.015); }
        .shell::after { content:""; position:absolute; inset:0; z-index:-2; background:linear-gradient(90deg,rgba(5,8,7,.97) 0%,rgba(5,8,7,.88) 42%,rgba(5,8,7,.4) 100%),linear-gradient(0deg,rgba(5,8,7,.95),transparent 55%); }
        .grid { position:absolute; inset:0; z-index:-1; opacity:.18; background-image:linear-gradient(rgba(255,255,255,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.08) 1px,transparent 1px); background-size:64px 64px; mask-image:linear-gradient(90deg,#000,transparent 78%); }
        .topbar { height:76px; padding:0 clamp(24px,5vw,76px); display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--line); }
        .brand { display:flex; align-items:center; gap:13px; color:#fff; text-decoration:none; font-size:12px; font-weight:800; letter-spacing:.3em; text-transform:uppercase; }
        .brand__sigil { display:grid; place-items:center; width:29px; height:29px; border:1px solid rgba(108,255,176,.7); color:var(--green); font:700 13px/1 "Space Mono",monospace; transform:rotate(45deg); }
        .brand__sigil span { transform:rotate(-45deg); }
        .classification { color:rgba(255,255,255,.52); font:400 10px/1 "Space Mono",monospace; letter-spacing:.18em; text-transform:uppercase; }
        .layout { width:min(1450px,100%); min-height:calc(100svh - 76px); margin:auto; padding:clamp(42px,8vh,100px) clamp(24px,5vw,76px) 48px; display:grid; grid-template-columns:minmax(0,1.12fr) minmax(390px,.72fr); gap:clamp(54px,8vw,130px); align-items:center; }
        .layout > * { min-width:0; }
        .eyebrow { display:flex; align-items:center; gap:12px; margin:0 0 28px; color:var(--green); font:700 11px/1 "Space Mono",monospace; letter-spacing:.17em; text-transform:uppercase; }
        .eyebrow::before { content:""; width:38px; height:1px; background:var(--green); }
        h1 { max-width:820px; margin:0; font-size:clamp(3.6rem,7.7vw,7.8rem); font-weight:800; line-height:.84; letter-spacing:-.072em; text-transform:uppercase; }
        h1 span { display:block; color:transparent; -webkit-text-stroke:1.5px rgba(255,255,255,.68); }
        .lede { max-width:610px; margin:38px 0 0; color:rgba(255,255,255,.7); font-size:clamp(15px,1.15vw,18px); line-height:1.75; }
        .meta { display:flex; gap:32px; margin-top:48px; }
        .meta__item { padding-left:14px; border-left:1px solid rgba(108,255,176,.55); }
        .meta__label { display:block; margin-bottom:8px; color:rgba(255,255,255,.4); font:400 9px/1 "Space Mono",monospace; letter-spacing:.18em; text-transform:uppercase; }
        .meta__value { font:700 12px/1.3 "Space Mono",monospace; letter-spacing:.04em; text-transform:uppercase; }
        .panel { position:relative; width:100%; max-width:100%; min-width:0; color:var(--ink); background:rgba(242,240,233,.96); box-shadow:0 32px 90px rgba(0,0,0,.38); }
        .panel::before { content:""; position:absolute; top:0; left:0; width:72px; height:4px; background:var(--green-dark); }
        .panel__head { padding:30px 34px 24px; display:flex; justify-content:space-between; gap:20px; border-bottom:1px solid rgba(17,21,19,.12); }
        .status { display:flex; align-items:center; gap:10px; color:var(--green-dark); font:700 10px/1.3 "Space Mono",monospace; letter-spacing:.12em; text-transform:uppercase; }
        .status__dot { width:8px; height:8px; border-radius:50%; background:#10b66a; box-shadow:0 0 0 5px rgba(16,182,106,.12); animation:pulse 2s ease-out infinite; }
        .panel__ref { color:#8a8f8c; font:400 9px/1.3 "Space Mono",monospace; letter-spacing:.1em; }
        .copy { padding:30px 34px 20px; display:grid; gap:17px; }
        .copy p { min-width:0; margin:0; overflow-wrap:anywhere; color:var(--muted); font-size:14px; font-weight:500; line-height:1.72; }
        .copy p:first-child { color:#303633; }
        .reopen { margin:3px 34px 30px; padding:19px 20px; background:#e4e4dc; border-left:3px solid var(--green-dark); }
        .reopen__label { display:block; margin-bottom:8px; color:#747a76; font:700 9px/1 "Space Mono",monospace; letter-spacing:.15em; text-transform:uppercase; }
        .reopen strong { font:700 14px/1.3 "Space Mono",monospace; }
        .panel__foot { padding:22px 34px; display:flex; align-items:center; justify-content:space-between; gap:20px; border-top:1px solid rgba(17,21,19,.12); }
        .note { color:#7a807c; font:400 9px/1.5 "Space Mono",monospace; text-transform:uppercase; }
        .cta { display:inline-flex; align-items:center; gap:17px; padding:14px 17px; background:var(--ink); color:#fff; text-decoration:none; font:700 10px/1 "Space Mono",monospace; letter-spacing:.1em; text-transform:uppercase; transition:transform .2s,background .2s; }
        .cta:hover { background:var(--green-dark); transform:translateY(-2px); }
        .cta span { color:var(--green); font-size:15px; }
        @keyframes pulse { 70% { box-shadow:0 0 0 11px rgba(16,182,106,0); } 100% { box-shadow:0 0 0 0 rgba(16,182,106,0); } }
        @media (max-width:900px) { .layout { grid-template-columns:minmax(0,1fr); align-items:start; } .intro { width:100%; max-width:700px; } .panel { max-width:650px; } h1 { font-size:clamp(3.4rem,14vw,7rem); } }
        @media (max-width:560px) { .topbar { height:64px; padding-inline:20px; } .classification { display:none; } .layout { width:100%; min-height:calc(100svh - 64px); padding:36px 20px 32px; gap:34px; } .eyebrow { margin-bottom:22px; font-size:9px; letter-spacing:.12em; white-space:nowrap; } .eyebrow::before { width:26px; flex:0 0 26px; } h1 { max-width:100%; font-size:clamp(2.65rem,12.7vw,4rem); line-height:.88; letter-spacing:-.065em; } h1 span { max-width:100%; font-size:.73em; letter-spacing:-.055em; -webkit-text-stroke-width:1px; } .lede { margin-top:24px; font-size:14px; line-height:1.65; } .meta { margin-top:28px; gap:18px; flex-direction:column; } .panel__head,.copy,.panel__foot { padding-left:20px; padding-right:20px; } .panel__head { padding-top:25px; padding-bottom:21px; } .status { font-size:9px; letter-spacing:.08em; } .panel__ref { display:none; } .copy { padding-top:24px; } .copy p { font-size:13px; line-height:1.65; } .reopen { margin-left:20px; margin-right:20px; padding:16px; } .panel__foot { align-items:flex-start; flex-direction:column; } .cta { width:100%; justify-content:space-between; } }
        @media (prefers-reduced-motion:reduce) { .status__dot { animation:none; } .cta { transition:none; } }
    </style>
</head>
<body>
<div class="shell">
    <div class="grid" aria-hidden="true"></div>
    <header class="topbar">
        <a class="brand" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>"><span class="brand__sigil"><span>A</span></span><?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></a>
        <span class="classification">COMSPEC-MILSIM // PORTAIL OPÉRATIONNEL</span>
    </header>
    <main class="layout">
        <section class="intro">
            <p class="eyebrow">Intervention planifiée</p>
            <h1><?= htmlspecialchars($titleLead, ENT_QUOTES, 'UTF-8') ?><span><?= htmlspecialchars($titleAccent, ENT_QUOTES, 'UTF-8') ?></span></h1>
            <p class="lede">Athena évolue. Nos équipes sécurisent la plateforme et préparent sa remise en ligne.</p>
            <div class="meta">
                <div class="meta__item"><span class="meta__label">État du service</span><span class="meta__value">Accès suspendu</span></div>
                <div class="meta__item"><span class="meta__label">Données</span><span class="meta__value">Protégées</span></div>
            </div>
        </section>
        <article class="panel">
            <header class="panel__head"><span class="status"><span class="status__dot" aria-hidden="true"></span>Intervention en cours</span><span class="panel__ref">ATH-<?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></span></header>
            <div class="copy"><?php foreach ($paragraphs as $paragraph): ?><p><?= nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) ?></p><?php endforeach; ?></div>
            <?php if ($endsLabel !== ''): ?><div class="reopen"><span class="reopen__label">Réouverture estimée</span><strong><?= htmlspecialchars($endsLabel, ENT_QUOTES, 'UTF-8') ?></strong></div><?php endif; ?>
            <footer class="panel__foot"><span class="note">Aucune action requise<br>de votre part</span><a class="cta" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>">Réessayer <span aria-hidden="true">↗</span></a></footer>
        </article>
    </main>
</div>
</body>
</html>
