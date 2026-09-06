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
    'Sécurisation en cours',
    'Intervention réseau',
    'Correctif en cours',
    'Nous revenons bientôt',
];
$genericMessages = [
    '',
    'Le service est momentanément indisponible.',
    'Maintenance en cours. Merci de réessayer dans quelques minutes.',
    "Nos équipes déploient une mise à jour stratégique.\nLe service revient très bientôt.",
];

$useBrandTitle = in_array($rawTitle, $genericTitles, true);
$pageHeading = $useBrandTitle ? 'Nous revenons bientôt' : $rawTitle;

$defaultParagraphs = [
    'Athena est momentanément fermé le temps d’une mise à jour. Nous simplifions la gestion des communautés : un accès plus clair par membre, des rôles mieux alignés, des dossiers plus simples à suivre.',
    'Vos données restent en sécurité. Vous n’avez rien à faire de votre côté. Pendant cette période, le site et les outils associés restent inaccessibles.',
    'Merci de votre patience, et de la confiance que vous accordez à Athena.',
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
$pageTitle = $useBrandTitle ? 'Maintenance' : $rawTitle;
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --void: #050505;
            --ink: #f4f4f0;
            --muted: rgba(244, 244, 240, 0.68);
            --dim: rgba(244, 244, 240, 0.42);
            --accent: #34d399;
            --field: #0b3d2e;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100svh;
            background: var(--void);
            color: var(--ink);
            font-family: "DM Sans", "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .stage {
            position: relative;
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .stage__photo {
            position: absolute;
            inset: 0;
            background:
                url('<?= htmlspecialchars($heroSrc, ENT_QUOTES, 'UTF-8') ?>') center / cover no-repeat;
            filter: grayscale(0.35) brightness(0.62);
            transform: scale(1.04);
            animation: drift 28s ease-in-out infinite alternate;
            pointer-events: none;
        }
        .stage__veil {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(5, 5, 5, 0.55) 0%, rgba(5, 5, 5, 0.35) 35%, rgba(5, 5, 5, 0.88) 100%),
                radial-gradient(ellipse 70% 55% at 20% 80%, rgba(11, 61, 46, 0.35), transparent 60%);
            pointer-events: none;
        }
        .bar {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 3.5rem;
            padding: 0 1.25rem;
        }
        .bar__mark {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: #fff;
            text-decoration: none;
        }
        .panel {
            position: relative;
            z-index: 2;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            width: min(40rem, 100%);
            padding: 2rem 1.35rem 3.25rem;
            margin-top: auto;
        }
        @media (min-width: 768px) {
            .panel {
                padding: 3rem clamp(2rem, 6vw, 5rem) 4.5rem;
            }
        }
        .brand {
            margin: 0 0 1rem;
            font-family: "Instrument Serif", Georgia, serif;
            font-size: clamp(3.75rem, 14vw, 7.5rem);
            font-weight: 400;
            line-height: 0.9;
            letter-spacing: -0.02em;
            color: #fff;
            animation: rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .brand em {
            font-style: italic;
            color: var(--accent);
        }
        .headline {
            margin: 0;
            max-width: 22ch;
            font-size: clamp(1.35rem, 3.2vw, 1.85rem);
            font-weight: 600;
            line-height: 1.25;
            letter-spacing: -0.02em;
            color: #fff;
            animation: rise 0.9s 0.1s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .copy {
            margin-top: 1.35rem;
            display: grid;
            gap: 0.9rem;
            max-width: 34rem;
            animation: rise 0.9s 0.2s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .copy p {
            margin: 0;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.65;
            color: var(--muted);
        }
        .copy strong {
            color: #fff;
            font-weight: 600;
        }
        .actions {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem 1.5rem;
            animation: rise 0.9s 0.3s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.85rem;
            padding: 0.75rem 1.35rem;
            border: 0;
            background: #fff;
            color: var(--void);
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            text-decoration: none;
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }
        .cta:hover {
            background: var(--accent);
            color: var(--field);
            transform: translateY(-1px);
        }
        .hint {
            margin: 0;
            font-size: 0.875rem;
            color: var(--dim);
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes drift {
            from { transform: scale(1.04) translate3d(0, 0, 0); }
            to { transform: scale(1.08) translate3d(-1.2%, -0.6%, 0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .brand, .headline, .copy, .actions, .stage__photo {
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <main class="stage">
        <div class="stage__photo" aria-hidden="true"></div>
        <div class="stage__veil" aria-hidden="true"></div>
        <header class="bar">
            <a class="bar__mark" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>">Athena</a>
        </header>
        <div class="panel">
            <p class="brand" aria-label="<?= htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8') ?>">Athena<em>.</em></p>
            <h1 class="headline"><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="copy">
                <?php foreach ($paragraphs as $paragraph): ?>
                    <p><?= nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endforeach; ?>
                <?php if ($endsLabel !== ''): ?>
                    <p>Réouverture prévue le <strong><?= htmlspecialchars($endsLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                <?php endif; ?>
            </div>
            <div class="actions">
                <a class="cta" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>">Réessayer</a>
                <p class="hint">Aucune action n’est demandée de votre part.</p>
            </div>
        </div>
    </main>
</body>
</html>
