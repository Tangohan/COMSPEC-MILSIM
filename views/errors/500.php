<?php

declare(strict_types=1);

/**
 * Page 500 — autonome (pas de BDD / session / CSRF / CDN requis).
 *
 * Variables optionnelles :
 *  - $errorReference : identifiant de corrélation
 *  - $errorHint      : aide contextualisée (ex. base injoignable)
 */

$base = function_exists('url') ? rtrim((string) url(''), '/') : '';
$lang = function_exists('html_lang') ? html_lang() : 'fr';
$isEnglish = str_starts_with(strtolower($lang), 'en');

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
$heroImg = ($base !== '' ? $base : '') . '/assets/images/fog-team.jpg';
$homeHref = ($base !== '' ? $base : '') . '/';
$loginHref = ($base !== '' ? $base : '') . '/login';
$legalHref = ($base !== '' ? $base : '') . '/legal/site';
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$stamp = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($t('errors.500_title', 'Incident technique — Athena', 'Technical incident — Athena')) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            background: #06090e;
            color: #e8eef4;
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }
        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(232, 238, 244, 0.08);
            background: rgba(6, 9, 14, 0.92);
        }
        .brand__mark {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            text-decoration: none;
            color: inherit;
        }
        .brand__name {
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .brand__sub {
            font-size: 0.62rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(232, 238, 244, 0.45);
        }
        .brand__meta {
            text-align: right;
            font-size: 0.68rem;
            color: rgba(232, 238, 244, 0.45);
            font-variant-numeric: tabular-nums;
        }
        .stage {
            position: relative;
            display: grid;
            place-items: center;
            padding: 2rem 1.25rem 2.5rem;
            overflow: hidden;
        }
        .stage__photo {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(1) brightness(0.28) contrast(1.05);
            transform: scale(1.04);
        }
        .stage__veil {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 55% at 20% 15%, rgba(220, 38, 38, 0.16), transparent 55%),
                radial-gradient(ellipse 50% 40% at 85% 10%, rgba(5, 150, 105, 0.08), transparent 45%),
                linear-gradient(180deg, rgba(6, 9, 14, 0.35) 0%, rgba(6, 9, 14, 0.72) 48%, #06090e 100%);
        }
        .stage__grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: linear-gradient(180deg, transparent, #000 18%, #000 70%, transparent);
            pointer-events: none;
        }
        .card {
            position: relative;
            z-index: 1;
            width: min(40rem, 100%);
            padding: 1.6rem 1.4rem 1.5rem;
            border: 1px solid rgba(232, 238, 244, 0.12);
            background: rgba(10, 14, 20, 0.78);
            backdrop-filter: blur(10px);
            box-shadow: 0 28px 60px rgba(0, 0, 0, 0.45);
        }
        @media (min-width: 720px) {
            .brand { padding: 1.15rem 2rem; }
            .stage { padding: 3rem 2rem 3.5rem; }
            .card { padding: 2rem 2rem 1.75rem; }
        }
        .alert {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 0 1rem;
            color: #fca5a5;
            font-size: 0.68rem;
            font-weight: 750;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }
        .alert__dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.18);
        }
        @media (prefers-reduced-motion: no-preference) {
            .alert__dot { animation: pulse 2s ease-in-out infinite; }
            @keyframes pulse { 50% { opacity: 0.45; } }
        }
        .code {
            margin: 0;
            font-size: clamp(3.2rem, 12vw, 5.5rem);
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 0.92;
            color: #fff;
        }
        .title {
            margin: 0.55rem 0 0;
            font-size: clamp(1.35rem, 3.5vw, 1.85rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #fff;
        }
        .rule {
            width: 3.5rem;
            height: 2px;
            margin: 1.15rem 0 1rem;
            background: #ef4444;
        }
        .lead {
            margin: 0;
            max-width: 38rem;
            color: rgba(232, 238, 244, 0.78);
            font-size: 0.98rem;
            line-height: 1.65;
        }
        .hint {
            margin: 1.15rem 0 0;
            padding: 0.9rem 1rem;
            border: 1px solid rgba(245, 158, 11, 0.28);
            border-left: 3px solid #f59e0b;
            background: rgba(245, 158, 11, 0.08);
            color: rgba(254, 243, 199, 0.95);
            font-size: 0.9rem;
            line-height: 1.55;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 1.4rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.6rem;
            padding: 0.65rem 1.05rem;
            border-radius: 0.2rem;
            border: 1px solid transparent;
            font: inherit;
            font-size: 0.78rem;
            font-weight: 750;
            letter-spacing: 0.04em;
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .btn--primary {
            background: #fff;
            color: #0b1220;
        }
        .btn--primary:hover { background: #e2e8f0; }
        .btn--ghost {
            background: transparent;
            border-color: rgba(232, 238, 244, 0.22);
            color: #e8eef4;
        }
        .btn--ghost:hover {
            border-color: rgba(232, 238, 244, 0.4);
            background: rgba(232, 238, 244, 0.06);
        }
        .ref {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem 0.85rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(232, 238, 244, 0.1);
            color: rgba(232, 238, 244, 0.55);
            font-size: 0.78rem;
        }
        .ref code {
            padding: 0.2rem 0.45rem;
            border: 1px solid rgba(232, 238, 244, 0.14);
            background: rgba(0, 0, 0, 0.35);
            color: #cbd5e1;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.78rem;
        }
        .ref button {
            border: 0;
            background: none;
            color: #34d399;
            font: inherit;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 0.15em;
            padding: 0;
        }
        .foot {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.85rem 1.5rem;
            padding: 1rem 1.25rem 1.35rem;
            border-top: 1px solid rgba(232, 238, 244, 0.08);
            background: #06090e;
            font-size: 0.72rem;
        }
        .foot a {
            color: rgba(232, 238, 244, 0.5);
            text-decoration: none;
        }
        .foot a:hover { color: #fff; }
        @media (max-width: 520px) {
            .actions { flex-direction: column; }
            .btn { width: 100%; }
            .brand__meta { display: none; }
        }
    </style>
</head>
<body>
<div class="shell">
    <header class="brand">
        <a class="brand__mark" href="<?= $e($homeHref) ?>">
            <span class="brand__name">Athena</span>
            <span class="brand__sub"><?= $e($t('errors.500_brand_sub', 'Portail milsim', 'Milsim portal')) ?></span>
        </a>
        <div class="brand__meta">
            <div id="err500-timestamp"><?= $e($stamp) ?></div>
            <div><?= $e($t('errors.500_sector', 'Secteur portail', 'Portal sector')) ?></div>
        </div>
    </header>

    <main class="stage">
        <img class="stage__photo" src="<?= $e($heroImg) ?>" alt="" aria-hidden="true" decoding="async" onerror="this.remove()">
        <div class="stage__veil" aria-hidden="true"></div>
        <div class="stage__grid" aria-hidden="true"></div>

        <section class="card" aria-labelledby="err500-title">
            <p class="alert">
                <span class="alert__dot" aria-hidden="true"></span>
                <?= $e($t('errors.500_alert', 'Alerte système', 'System alert')) ?>
            </p>
            <p class="code" aria-hidden="true">500</p>
            <h1 class="title" id="err500-title"><?= $e($t('errors.500_heading', 'Incident technique', 'Technical incident')) ?></h1>
            <div class="rule" aria-hidden="true"></div>
            <p class="lead"><?= $e($t('errors.500_body', 'Le serveur n’a pas pu traiter votre demande. L’équipe technique a été prévenue. Réessayez dans quelques instants.', 'The server could not process your request. The technical team has been notified. Please try again shortly.')) ?></p>

            <?php if ($hint !== ''): ?>
                <p class="hint"><?= $e($hint) ?></p>
            <?php endif; ?>

            <div class="actions">
                <a class="btn btn--primary" href="<?= $e($homeHref) ?>"><?= $e($t('errors.500_home', 'Retour à l’accueil', 'Back to home')) ?></a>
                <button type="button" class="btn btn--ghost" onclick="location.reload()"><?= $e($t('errors.500_retry', 'Réessayer', 'Try again')) ?></button>
                <button type="button" class="btn btn--ghost" onclick="history.back()"><?= $e($t('errors.500_back', 'Page précédente', 'Go back')) ?></button>
            </div>

            <?php if ($reference !== ''): ?>
                <div class="ref">
                    <span><?= $e($t('errors.500_reference', 'Référence à communiquer', 'Reference to share')) ?></span>
                    <code id="err500-ref"><?= $e($reference) ?></code>
                    <button type="button" id="err500-copy"><?= $e($t('errors.500_copy', 'Copier', 'Copy')) ?></button>
                </div>
            <?php else: ?>
                <p class="ref"><?= $e($t('errors.500_alert_sub', 'Aucune action sur votre session. Vos données ne sont pas perdues pour autant.', 'No action on your session. Your data is not lost.')) ?></p>
            <?php endif; ?>
        </section>
    </main>

    <nav class="foot" aria-label="<?= $e($t('errors.500_footer_aria', 'Liens de secours', 'Fallback links')) ?>">
        <a href="<?= $e($loginHref) ?>"><?= $e($t('common.login', 'Connexion', 'Log in')) ?></a>
        <a href="<?= $e($legalHref) ?>"><?= $e($t('errors.500_legal', 'Mentions légales', 'Legal notice')) ?></a>
    </nav>
</div>

<script>
(function () {
    var el = document.getElementById('err500-timestamp');
    if (el) {
        var tag = <?= json_encode($isEnglish ? 'en-GB' : 'fr-FR', JSON_UNESCAPED_UNICODE) ?>;
        function tick() {
            try { el.textContent = new Date().toLocaleString(tag, { hour12: false }); } catch (e) {}
        }
        tick();
        setInterval(tick, 30000);
    }
    var copyBtn = document.getElementById('err500-copy');
    var refEl = document.getElementById('err500-ref');
    if (copyBtn && refEl && navigator.clipboard) {
        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(refEl.textContent || '').then(function () {
                copyBtn.textContent = <?= json_encode($isEnglish ? 'Copied' : 'Copié', JSON_UNESCAPED_UNICODE) ?>;
            }).catch(function () {});
        });
    }
})();
</script>
</body>
</html>
