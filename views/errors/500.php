<?php

declare(strict_types=1);

/**
 * Page 500 — même identité visuelle que la 404/403.
 *
 * Rendue aussi bien depuis le catch de public/index.php que depuis le shutdown handler
 * (erreur fatale) : elle ne dépend d’aucun service applicatif (BDD, session, CSRF) et
 * redéfinit localement chaque helper au cas où le bootstrap n’aurait pas abouti.
 *
 * Variables optionnelles :
 *  - $errorReference : identifiant de corrélation à communiquer au support
 *  - $errorHint      : message d’aide contextualisé (ex. base de données à mettre à jour)
 */

$base = function_exists('url') ? url('') : '';
$lang = function_exists('html_lang') ? html_lang() : 'fr';
$isEnglish = str_starts_with(strtolower($lang), 'en');

/** Traduction tolérante : le bootstrap i18n peut ne pas avoir abouti. */
$t = static function (string $key, string $fr, string $en) use ($isEnglish): string {
    if (function_exists('__')) {
        try {
            $value = __($key);
            if ($value !== '' && $value !== $key) {
                return $value;
            }
        } catch (\Throwable) {
        }
    }

    return $isEnglish ? $en : $fr;
};

$reference = trim((string) ($errorReference
    ?? (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''))));
$hint = trim((string) ($errorHint ?? ''));
$heroImg = $base . '/assets/images/fog-team.jpg';
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($t('errors.500_title', '500 — Athena', '500 — Athena')) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #050810;
            color: #fff;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .err-hero {
            position: relative;
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            padding: 2rem;
            background-color: #000;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 44px 44px;
        }
        @media (min-width: 768px) { .err-hero { padding: 3.5rem; } }
        .err-hero__img {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: cover; filter: grayscale(1) brightness(0.2); transform: scale(1.05);
        }
        .err-hero__veil {
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at top, rgba(239,68,68,0.10), transparent 38%),
                linear-gradient(to bottom, rgba(0,0,0,0.4), rgba(0,0,0,0.6) 55%, #050810);
        }
        .err-hero > * { position: relative; z-index: 1; }
        .err-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 2rem; flex-wrap: wrap; }
        .err-kicker { font-size: 9px; font-weight: 900; letter-spacing: 0.4em; text-transform: uppercase; margin: 0; }
        .err-sub { font-size: 7px; font-weight: 700; letter-spacing: 0.3em; text-transform: uppercase; color: rgba(255,255,255,0.3); max-width: 28rem; }
        .err-dot { display: inline-block; width: 6px; height: 6px; border-radius: 999px; background: #dc2626; margin-right: 0.5rem; animation: err-pulse 2s cubic-bezier(0.4,0,0.6,1) infinite; }
        @keyframes err-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }
        .err-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 9px; letter-spacing: 0.15em; color: rgba(255,255,255,0.4); text-transform: uppercase; }
        .err-body { max-width: 48rem; margin-top: 4rem; }
        .err-code {
            font-size: clamp(3rem, 13vw, 7rem); font-weight: 900; letter-spacing: -0.04em;
            line-height: 0.95; margin: 0 0 1.5rem;
        }
        .err-code span { color: rgba(255,255,255,0.9); font-size: clamp(1.75rem, 6vw, 3.5rem); display: block; }
        .err-rule { height: 1px; width: 6rem; background: rgba(255,255,255,0.2); margin-bottom: 1.5rem; }
        .err-text { color: rgba(255,255,255,0.55); font-size: 12px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; line-height: 1.9; max-width: 36rem; overflow-wrap: anywhere; }
        .err-hint {
            margin-top: 1.5rem; padding: 1rem 1.25rem; border-radius: 1rem; max-width: 40rem;
            border: 1px solid rgba(245,158,11,0.3); background: rgba(245,158,11,0.07);
            color: rgba(255,255,255,0.75); font-size: 12px; line-height: 1.7; letter-spacing: 0.02em;
        }
        .err-actions { margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .err-btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.85rem 1.35rem; border-radius: 1rem; font-size: 10px; font-weight: 900;
            letter-spacing: 0.22em; text-transform: uppercase; text-decoration: none;
            border: 1px solid transparent; cursor: pointer; transition: background-color .2s, color .2s;
            font-family: inherit;
        }
        .err-btn--primary { background: #fff; color: #020617; }
        .err-btn--primary:hover { background: #e2e8f0; }
        .err-btn--ghost { border-color: rgba(255,255,255,0.15); background: rgba(255,255,255,0.04); color: #fff; }
        .err-btn--ghost:hover { background: rgba(255,255,255,0.09); }
        .err-ref { margin-top: 2rem; font-size: 9px; letter-spacing: 0.25em; text-transform: uppercase; color: rgba(255,255,255,0.3); }
        .err-ref code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: rgba(255,255,255,0.6); letter-spacing: 0.1em; text-transform: none; }
        .err-top__meta { text-align: right; }
        .err-top__meta .err-sub { margin-top: 0.4rem; letter-spacing: 0.5em; }
        .err-foot {
            flex: 0 0 auto; background: #050810; border-top: 1px solid rgba(255,255,255,0.06);
            padding: 1.75rem 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem 2rem;
            align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase;
        }
        .err-foot a { color: rgba(255,255,255,0.5); text-decoration: none; }
        .err-foot a:hover { color: #fff; }
        @media (max-width: 640px) {
            .err-hero { padding: 1.5rem 1.25rem; }
            .err-body { margin-top: 2.5rem; }
            .err-sub { letter-spacing: 0.18em; }
            .err-text { font-size: 11px; letter-spacing: 0.12em; line-height: 1.8; }
            .err-ref { letter-spacing: 0.14em; }
            .err-btn { padding: 0.8rem 1rem; letter-spacing: 0.14em; }
            .err-foot { gap: 0.75rem 1.25rem; letter-spacing: 0.12em; padding: 1.5rem 1.25rem; }
        }
        @media (prefers-reduced-motion: reduce) { .err-dot { animation: none; } }
    </style>
</head>
<body>
    <main class="err-hero">
        <img class="err-hero__img" src="<?= $e($heroImg) ?>" alt="" aria-hidden="true" decoding="async" onerror="this.remove()">
        <div class="err-hero__veil" aria-hidden="true"></div>

        <div class="err-top">
            <div>
                <p class="err-kicker">
                    <span class="err-dot" aria-hidden="true"></span><?= $e($t('errors.500_alert', 'Alerte système', 'System alert')) ?>
                </p>
                <p class="err-sub"><?= $e($t('errors.500_alert_sub', 'Incident serveur — aucune action sur votre session', 'Server incident — no action on your session')) ?></p>
            </div>
            <div class="err-top__meta">
                <div id="err500-timestamp" class="err-mono"><?= $e(date('d/m/Y H:i:s')) ?></div>
                <div class="err-sub"><?= $e($t('errors.500_sector', 'Secteur portail', 'Portal sector')) ?></div>
            </div>
        </div>

        <div class="err-body">
            <p class="err-kicker" style="color:#f87171; margin-bottom:1rem;"><?= $e($t('errors.500_kicker', 'Erreur serveur', 'Server error')) ?></p>
            <h1 class="err-code">
                500
                <span><?= $e($t('errors.500_heading', 'Incident technique', 'Technical incident')) ?></span>
            </h1>
            <div class="err-rule" aria-hidden="true"></div>
            <p class="err-text"><?= $e($t('errors.500_body', 'Le serveur n’a pas pu traiter votre demande. L’incident a été signalé automatiquement à l’équipe technique — réessayez dans quelques instants.', 'The server could not process your request. The incident has been reported automatically to the technical team — please try again shortly.')) ?></p>

            <?php if ($hint !== ''): ?>
            <p class="err-hint"><?= $e($hint) ?></p>
            <?php endif; ?>

            <div class="err-actions">
                <a class="err-btn err-btn--primary" href="<?= $e($base . '/') ?>"><?= $e($t('errors.500_home', 'Retour accueil', 'Back to home')) ?></a>
                <button type="button" class="err-btn err-btn--ghost" onclick="location.reload()"><?= $e($t('errors.500_retry', 'Réessayer', 'Try again')) ?></button>
                <button type="button" class="err-btn err-btn--ghost" onclick="history.back()"><?= $e($t('errors.500_back', 'Retour arrière', 'Go back')) ?></button>
            </div>

            <?php if ($reference !== ''): ?>
            <p class="err-ref"><?= $e($t('errors.500_reference', 'Référence incident', 'Incident reference')) ?> : <code><?= $e($reference) ?></code></p>
            <?php endif; ?>
        </div>
    </main>

    <nav class="err-foot" aria-label="<?= $e($t('errors.500_footer_aria', 'Liens de secours', 'Fallback links')) ?>">
        <a href="<?= $e($base . '/') ?>"><?= $e($t('errors.500_home', 'Retour accueil', 'Back to home')) ?></a>
        <a href="<?= $e($base . '/login') ?>"><?= $e($t('common.login', 'Connexion', 'Log in')) ?></a>
        <a href="<?= $e($base . '/legal/site') ?>"><?= $e($t('errors.500_legal', 'Mentions légales', 'Legal notice')) ?></a>
    </nav>

    <script>
        (function () {
            var el = document.getElementById('err500-timestamp');
            if (!el) { return; }
            var tag = <?= json_encode($isEnglish ? 'en-GB' : 'fr-FR') ?>;
            function tick() { el.textContent = new Date().toLocaleString(tag, { hour12: false }); }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
