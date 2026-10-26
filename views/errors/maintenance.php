<?php

declare(strict_types=1);

use App\Support\MaintenanceMarkdown;
use App\Support\MaintenanceThemes;

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
// Apparence choisie dans l’administration (Photo de terrain, Clair et sobre, Nuit radar, Tableau de situation).
$uiVariant = MaintenanceThemes::normalize($uiVariant ?? null);
$uiAnimation = !isset($uiAnimation) || (bool) $uiAnimation;
$previewMode = !empty($previewMode);
$bodyClasses = 'theme-' . $uiVariant . ($uiAnimation ? '' : ' no-anim') . ($previewMode ? ' is-preview' : '');
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
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/icons/athena-180.png">
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
        .notice {
            margin: 0 0 1.1rem;
            padding: 0.7rem 0.9rem;
            border-left: 3px solid #f2c66b;
            background: rgba(0, 0, 0, 0.35);
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .admin-link {
            color: var(--dim);
            font-size: 0.82rem;
            text-decoration: underline;
            text-underline-offset: 3px;
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
            .sheet, .headline, .copy, .actions, .stage__photo, .stage__radar, .status__dot {
                animation: none !important;
            }
        }
        .no-anim *, .no-anim *::before, .no-anim *::after {
            animation: none !important;
            transition: none !important;
        }
        .preview-bar {
            position: fixed;
            z-index: 20;
            top: 0.75rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            background: #fbbf24;
            color: #1c1300;
            font-size: 0.78rem;
            font-weight: 700;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
            white-space: nowrap;
        }
        .stage__radar, .status { display: none; }

        /* Clair et sobre : fond clair, carte centrée, sans photo. */
        .theme-minimal { background: #f3f5f4; color: #0c1116; }
        .theme-minimal .stage { align-items: center; justify-content: center; padding: 1.25rem; }
        .theme-minimal .stage__photo, .theme-minimal .stage__veil { display: none; }
        .theme-minimal .panel {
            flex: 0 0 auto;
            margin: 0;
            width: min(38rem, 100%);
            padding: 2.4rem clamp(1.4rem, 5vw, 3rem);
            border: 1px solid #e3e8ea;
            border-radius: 1.1rem;
            background: #fff;
            box-shadow: 0 24px 60px -30px rgba(15, 23, 42, 0.35);
        }
        .theme-minimal .panel::before { display: none; }
        .theme-minimal .headline { max-width: none; color: #0c1116; text-shadow: none; }
        .theme-minimal .copy :where(p, li) { color: #3b4549; text-shadow: none; }
        .theme-minimal .copy :where(h2, h3, h4), .theme-minimal .copy strong { color: #0c1116; text-shadow: none; }
        .theme-minimal .copy .lang { color: #0b8a5c; }
        .theme-minimal .copy hr { border-top-color: #e3e8ea; }
        .theme-minimal .notice { background: #fdf6e3; color: #4a3b10; }
        .theme-minimal .cta { background: #0c1116; color: #fff; border-radius: 0.6rem; }
        .theme-minimal .cta:hover { background: #0b8a5c; color: #fff; }
        .theme-minimal .hint { color: #6d7a80; text-shadow: none; }

        /* Nuit radar : fond noir, quadrillage, balayage vert. */
        .theme-neon { background: #020806; }
        .theme-neon .stage__photo { display: none; }
        .theme-neon .stage__veil {
            background:
                radial-gradient(circle at 72% 38%, rgba(52, 211, 153, 0.16), transparent 42%),
                repeating-linear-gradient(0deg, rgba(52, 211, 153, 0.07) 0 1px, transparent 1px 48px),
                repeating-linear-gradient(90deg, rgba(52, 211, 153, 0.07) 0 1px, transparent 1px 48px),
                #020806;
        }
        .theme-neon .stage__radar {
            display: block;
            position: absolute;
            top: 38%;
            left: 72%;
            width: min(70vmin, 36rem);
            aspect-ratio: 1;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            border: 1px solid rgba(52, 211, 153, 0.35);
            background:
                radial-gradient(circle, transparent 0 32%, rgba(52, 211, 153, 0.22) 32.3%, transparent 32.8% 65%, rgba(52, 211, 153, 0.22) 65.3%, transparent 65.8%),
                conic-gradient(from 0deg, rgba(52, 211, 153, 0.42), rgba(52, 211, 153, 0) 22%, transparent 100%);
            animation: sweep 5s linear infinite;
            pointer-events: none;
        }
        .theme-neon .panel::before { background: linear-gradient(180deg, transparent 0%, rgba(2, 8, 6, 0.75) 35%, rgba(2, 8, 6, 0.96) 100%); }
        .theme-neon .headline { color: #d1fae5; text-shadow: 0 0 18px rgba(52, 211, 153, 0.55); }
        .theme-neon .copy :where(p, li) { color: rgba(209, 250, 229, 0.82); text-shadow: none; }
        .theme-neon .cta { background: transparent; color: #34d399; border: 1px solid #34d399; box-shadow: 0 0 16px rgba(52, 211, 153, 0.35); }
        .theme-neon .cta:hover { background: #34d399; color: #02140d; }
        .theme-neon .hint { color: rgba(209, 250, 229, 0.55); }

        /* Tableau de situation : carte d’état des services. */
        .theme-status { background: #0b1014; }
        .theme-status .stage { align-items: center; justify-content: center; padding: 1.25rem; }
        .theme-status .stage__photo { filter: grayscale(1) brightness(0.22); }
        .theme-status .panel {
            flex: 0 0 auto;
            margin: 0;
            width: min(44rem, 100%);
            padding: 2.2rem clamp(1.3rem, 4.5vw, 2.6rem);
            border: 1px solid rgba(247, 247, 243, 0.12);
            border-radius: 1rem;
            background: rgba(14, 20, 25, 0.92);
            box-shadow: 0 30px 80px -40px rgba(0, 0, 0, 0.9);
        }
        .theme-status .panel::before { display: none; }
        .theme-status .headline { max-width: none; font-size: clamp(1.6rem, 5vw, 2.2rem); }
        .theme-status .status {
            display: grid;
            gap: 0;
            margin: 1.35rem 0 0.25rem;
            border: 1px solid rgba(247, 247, 243, 0.1);
            border-radius: 0.7rem;
            overflow: hidden;
        }
        .status__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.7rem 0.95rem;
            font-size: 0.92rem;
            color: var(--muted);
        }
        .status__row + .status__row { border-top: 1px solid rgba(247, 247, 243, 0.08); }
        .status__state { display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 650; color: #fff; }
        .status__dot { width: 0.55rem; height: 0.55rem; border-radius: 50%; background: #fbbf24; box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.6); animation: pulse 1.8s ease-out infinite; }
        .status__dot--ok { background: #34d399; animation: none; }
        .status__dot--wait { background: #60a5fa; animation: none; }
        @keyframes sweep { to { transform: translate(-50%, -50%) rotate(360deg); } }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.55); }
            100% { box-shadow: 0 0 0 0.6rem rgba(251, 191, 36, 0); }
        }
    </style>
</head>
<body class="<?= htmlspecialchars($bodyClasses, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($previewMode): ?>
        <p class="preview-bar" role="status">Aperçu : rien n’est en ligne, le site reste ouvert.</p>
    <?php endif; ?>
    <main class="stage">
        <div class="stage__photo" aria-hidden="true"></div>
        <div class="stage__veil" aria-hidden="true"></div>
        <div class="stage__radar" aria-hidden="true"></div>
        <div class="panel">
            <div class="sheet">
                <h1 class="headline"><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($uiVariant === 'status'): ?>
                    <div class="status" aria-label="État des services">
                        <div class="status__row"><span>Site Athena</span><span class="status__state"><span class="status__dot" aria-hidden="true"></span>En maintenance</span></div>
                        <div class="status__row"><span>Téléphone ATAK en jeu</span><span class="status__state"><span class="status__dot status__dot--wait" aria-hidden="true"></span>En attente du site</span></div>
                        <div class="status__row"><span>Vos données</span><span class="status__state"><span class="status__dot status__dot--ok" aria-hidden="true"></span>Protégées</span></div>
                        <div class="status__row"><span>Réouverture</span><span class="status__state"><?= htmlspecialchars($endsLabel !== '' ? $endsLabel : 'Dès que possible', ENT_QUOTES, 'UTF-8') ?></span></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($loggedOut)): ?>
                    <p class="notice" role="status">Vous avez été déconnecté pour la durée de la maintenance. Reconnectez-vous une fois le site rouvert.</p>
                <?php endif; ?>
                <div class="copy">
                    <?= $bodyHtml ?>
                    <?php if ($endsLabel !== ''): ?>
                        <p>Réouverture prévue le <strong><?= htmlspecialchars($endsLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <a class="cta" href="<?= htmlspecialchars((string) $homeUrl, ENT_QUOTES, 'UTF-8') ?>">Réessayer</a>
                    <p class="hint">Aucune action n’est demandée de votre part.</p>
                    <?php if (!empty($adminLoginUrl)): ?>
                        <a class="admin-link" href="<?= htmlspecialchars((string) $adminLoginUrl, ENT_QUOTES, 'UTF-8') ?>">Connexion administrateur</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
