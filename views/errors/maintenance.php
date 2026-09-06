<?php

declare(strict_types=1);

use App\Support\MaintenanceMarkdown;

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

$defaultMarkdown = <<<'MD'
Athena est momentanément fermé le temps d’une mise à jour. Nous simplifions la gestion des communautés : un accès plus clair par membre, des rôles mieux alignés, des dossiers plus simples à suivre.

Vos données restent en sécurité. Vous n’avez rien à faire de votre côté. Pendant cette période, le site et les outils associés restent inaccessibles.

Merci de votre patience, et de la confiance que vous accordez à Athena.
MD;

$messageMarkdown = ($rawMessage !== '' && !in_array($rawMessage, $genericMessages, true))
    ? $rawMessage
    : $defaultMarkdown;

$bodyHtml = MaintenanceMarkdown::toHtml($messageMarkdown);

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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --void: #050505;
            --ink: #f7f7f3;
            --muted: rgba(247, 247, 243, 0.88);
            --dim: rgba(247, 247, 243, 0.62);
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
            background: url('<?= htmlspecialchars($heroSrc, ENT_QUOTES, 'UTF-8') ?>') center / cover no-repeat;
            filter: grayscale(0.45) brightness(0.42) contrast(1.05);
            transform: scale(1.04);
            animation: drift 28s ease-in-out infinite alternate;
            pointer-events: none;
        }
        .stage__veil {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(5, 5, 5, 0.62) 0%, rgba(5, 5, 5, 0.48) 32%, rgba(5, 5, 5, 0.86) 68%, rgba(5, 5, 5, 0.96) 100%),
                radial-gradient(ellipse 80% 55% at 18% 78%, rgba(11, 61, 46, 0.28), transparent 62%);
            pointer-events: none;
        }
        .panel {
            position: relative;
            z-index: 2;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            width: min(42rem, 100%);
            padding: 2.25rem 1.25rem 2.75rem;
            margin-top: auto;
        }
        .panel::before {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: min(78%, 42rem);
            background: linear-gradient(180deg, transparent 0%, rgba(5, 5, 5, 0.55) 28%, rgba(5, 5, 5, 0.88) 100%);
            pointer-events: none;
            z-index: -1;
        }
        @media (min-width: 768px) {
            .panel {
                padding: 3.5rem clamp(2rem, 6vw, 5rem) 4.5rem;
            }
        }
        .sheet {
            position: relative;
            animation: rise 0.85s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .headline {
            margin: 0;
            max-width: 18ch;
            font-size: clamp(1.85rem, 6.5vw, 2.65rem);
            font-weight: 650;
            line-height: 1.15;
            letter-spacing: -0.03em;
            color: #fff;
            text-shadow: 0 1px 12px rgba(0, 0, 0, 0.35);
        }
        .copy {
            margin-top: 1.15rem;
            display: grid;
            gap: 0.95rem;
            max-width: 36rem;
            animation: rise 0.85s 0.14s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .copy :where(p, li) {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 450;
            line-height: 1.7;
            color: var(--muted);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
        }
        .copy :where(h2, h3, h4) {
            margin: 0.35rem 0 0;
            color: #fff;
            font-weight: 650;
            letter-spacing: -0.02em;
            text-shadow: 0 1px 10px rgba(0, 0, 0, 0.35);
        }
        .copy h2 { font-size: 1.15rem; }
        .copy h3, .copy h4 { font-size: 1.05rem; }
        .copy .lang {
            margin: 0.15rem 0 0;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: var(--accent);
            text-shadow: none;
        }
        .copy .lang + p { margin-top: -0.35rem; }
        .copy hr {
            margin: 0.35rem 0;
            border: 0;
            border-top: 1px solid rgba(247, 247, 243, 0.22);
        }
        .copy ul, .copy ol {
            margin: 0;
            padding-left: 1.2rem;
            display: grid;
            gap: 0.35rem;
        }
        .copy strong { color: #fff; font-weight: 650; }
        .copy a {
            color: var(--accent);
            text-decoration: underline;
            text-underline-offset: 0.15em;
        }
        .copy code {
            font-size: 0.92em;
            padding: 0.1em 0.35em;
            border-radius: 0.3em;
            background: rgba(255, 255, 255, 0.08);
        }
        .actions {
            margin-top: 1.55rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.9rem 1.35rem;
            animation: rise 0.85s 0.22s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.85rem;
            padding: 0.75rem 1.35rem;
            border: 0;
            background: #fff;
            color: #0a0c0b;
            font-size: 0.9rem;
            font-weight: 650;
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
            font-size: 0.9rem;
            color: var(--dim);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes drift {
            from { transform: scale(1.04) translate3d(0, 0, 0); }
            to { transform: scale(1.08) translate3d(-1.2%, -0.6%, 0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sheet, .headline, .copy, .actions, .stage__photo {
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <main class="stage">
        <div class="stage__photo" aria-hidden="true"></div>
        <div class="stage__veil" aria-hidden="true"></div>
        <div class="panel">
            <div class="sheet">
                <h1 class="headline"><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="copy">
                    <?= $bodyHtml ?>
                    <?php if ($endsLabel !== ''): ?>
                        <p>Réouverture prévue le <strong><?= htmlspecialchars($endsLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <a class="cta" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>">Réessayer</a>
                    <p class="hint">Aucune action n’est demandée de votre part.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
