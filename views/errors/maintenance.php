<?php

declare(strict_types=1);

$appLabel = $appName ?? (function_exists('config') ? (string) config('app.name', 'Athena') : 'Athena');
$rawTitle = trim((string) ($title ?? ''));
$rawMessage = trim((string) ($message ?? ''));
$genericTitles = [
    '',
    'Maintenance en cours',
    'Maintenance globale en cours',
    'Maintenance sécurité en cours',
    'Maintenance opérationnelle',
    'Intervention infrastructure',
    'Intervention en cours',
    'Déploiement correctif urgent',
];
$genericMessages = [
    '',
    'Le service est momentanément indisponible.',
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
    $parts = preg_split('/\s+/u', $rawTitle) ?: [];
    $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    if ($parts === []) {
        $titleLead = 'Maintenance';
        $titleAccent = 'opérationnelle';
    } elseif (count($parts) === 1) {
        $titleLead = $parts[0];
        $titleAccent = '';
    } else {
        $titleAccent = (string) array_pop($parts);
        $titleLead = implode(' ', $parts);
    }
}

$defaultParagraphs = [
    'Le portail Athena est fermé le temps d’une intervention de fond. Nous remettons à plat la gestion des communautés : un seul niveau d’accès par membre, des emplois qui suivent l’organigramme, des dossiers plus simples à tenir.',
    'Vos dossiers, vos cartes et vos liaisons déjà établies restent protégés. Rien n’est demandé de votre côté. Les opérateurs déjà en mission conservent leur dernière situation connue jusqu’à la réouverture.',
    'Nous rouvrons dès que les contrôles sont terminés. Merci de votre patience, et de la confiance que vous accordez à COMSPEC.',
];

$paragraphs = [];
if ($rawMessage !== '' && !in_array($rawMessage, $genericMessages, true)) {
    foreach (preg_split("/\R{2,}|\R/", $rawMessage) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $paragraphs[] = $line;
        }
    }
}
if ($paragraphs === []) {
    $paragraphs = $defaultParagraphs;
}

$endsLabel = '';
if (!empty($endsAt)) {
    $ts = strtotime((string) $endsAt);
    if ($ts !== false) {
        $endsLabel = date('d/m/Y à H:i', $ts);
    }
}

$homeUrl = function_exists('url') ? url('') : '/';
$base = function_exists('url') ? rtrim((string) url(''), '/') : '';
$heroSrc = ($base !== '' ? $base : '') . '/assets/images/fog-team.jpg';
$pageTitle = $useBrandTitle ? 'Maintenance opérationnelle' : $rawTitle;
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($base !== ''): ?>
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/icons/athena-192.png">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --void: #050505;
            --paper: #f7f7f4;
            --ink: #141414;
            --muted: #5c5c5c;
            --dim: #8a8a8a;
            --accent: #0d9b6b;
            --rule: rgba(20, 20, 20, 0.12);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100svh;
            background: var(--void);
            color: var(--ink);
            font-family: Inter, "Segoe UI", system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .stage {
            position: relative;
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6.5rem 1.25rem 3rem;
            overflow: hidden;
        }
        .stage__photo {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(5, 5, 5, 0.55) 0%, rgba(5, 5, 5, 0.82) 55%, #050505 100%),
                url('<?= htmlspecialchars($heroSrc, ENT_QUOTES, 'UTF-8') ?>') center / cover no-repeat,
                radial-gradient(1200px 520px at 110% -10%, rgba(52, 211, 153, 0.12), transparent 55%),
                radial-gradient(900px 480px at -15% 110%, rgba(244, 244, 240, 0.06), transparent 50%),
                var(--void);
            filter: grayscale(0.55) brightness(0.55);
            pointer-events: none;
        }
        .bar {
            position: fixed;
            inset: 0 0 auto;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 3.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(5, 5, 5, 0.72);
            backdrop-filter: blur(12px);
        }
        .bar__mark {
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.32em;
            text-transform: uppercase;
            color: #fff;
            text-decoration: none;
        }
        .sheet {
            position: relative;
            z-index: 1;
            width: min(40rem, 100%);
            padding: clamp(2rem, 5vw, 3.25rem) clamp(1.6rem, 4.5vw, 3rem);
            background: var(--paper);
            box-shadow:
                0 30px 80px rgba(0, 0, 0, 0.45),
                0 0 0 1px rgba(255, 255, 255, 0.04);
        }
        .kicker {
            margin: 0 0 1.35rem;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--dim);
        }
        .display {
            margin: 0;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.045em;
            line-height: 0.9;
            font-size: clamp(2.4rem, 8.5vw, 4.35rem);
        }
        .display__lead { color: var(--ink); }
        .display__accent { color: var(--accent); }
        .copy {
            margin-top: 1.85rem;
            display: grid;
            gap: 1.05rem;
        }
        .copy p {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 500;
            line-height: 1.7;
            letter-spacing: 0.01em;
            color: var(--muted);
        }
        .copy strong { color: var(--ink); font-weight: 700; }
        .foot {
            margin-top: 2.35rem;
            padding-top: 1.35rem;
            border-top: 1px solid var(--rule);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            flex-wrap: wrap;
        }
        .live {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--accent);
        }
        .live__dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 0 rgba(13, 155, 107, 0.45);
            animation: pulse 1.8s ease-out infinite;
        }
        @media (prefers-reduced-motion: reduce) {
            .live__dot { animation: none; }
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(13, 155, 107, 0.45); }
            70% { box-shadow: 0 0 0 10px rgba(13, 155, 107, 0); }
            100% { box-shadow: 0 0 0 0 rgba(13, 155, 107, 0); }
        }
        .cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: 0.7rem 1.2rem;
            border: 1px solid rgba(20, 20, 20, 0.28);
            background: transparent;
            color: var(--ink);
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            text-decoration: none;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .cta:hover {
            border-color: rgba(20, 20, 20, 0.55);
            background: rgba(20, 20, 20, 0.04);
        }
        .cta span { margin-left: 0.55rem; }
    </style>
</head>
<body>
    <header class="bar">
        <a class="bar__mark" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>">Athena</a>
    </header>
    <main class="stage">
        <div class="stage__photo" aria-hidden="true"></div>
        <article class="sheet">
            <p class="kicker"><?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?> // COMSPEC-MILSIM · <?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?></p>
            <h1 class="display">
                <span class="display__lead"><?= htmlspecialchars($titleLead, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($titleAccent !== ''): ?>
                    <span class="display__accent"><?= htmlspecialchars($titleAccent, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </h1>
            <div class="copy">
                <?php foreach ($paragraphs as $paragraph): ?>
                    <p><?= nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endforeach; ?>
                <?php if ($endsLabel !== ''): ?>
                    <p>Réouverture prévue le <strong><?= htmlspecialchars($endsLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                <?php endif; ?>
            </div>
            <div class="foot">
                <p class="live"><span class="live__dot" aria-hidden="true"></span>Intervention en cours.</p>
                <a class="cta" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>">Réessayer<span aria-hidden="true">→</span></a>
            </div>
        </article>
    </main>
</body>
</html>
