<?php
declare(strict_types=1);

/**
 * Page publique de C.V. du joueur (sans connexion) — « dossier opérateur ».
 * Reçoit uniquement $cv, déjà filtré par App\Support\PublicCv\PublicCvPresenter (liste blanche + sections choisies) :
 * une section masquée arrive vide et n’est donc pas rendue.
 * Styles autonomes (préfixe pcv-) : la page ne dépend ni du portail connecté ni de la feuille Tailwind.
 */
$cv = is_array($cv ?? null) ? $cv : [];
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$list = static fn (string $k): array => is_array($cv[$k] ?? null) ? array_values($cv[$k]) : [];
$str = static fn (string $k): ?string => is_string($cv[$k] ?? null) && $cv[$k] !== '' ? $cv[$k] : null;

$callsign = (string) ($cv['callsign'] ?? 'Opérateur');
$grade = $str('grade');
$gradeShort = $str('grade_short');
$unit = $str('unit');
$community = $str('community');
$communityUrl = $str('community_url');
$avatar = $str('avatar_url');
$banner = $str('banner_url');
$bio = $str('bio');
$counters = $list('counters');
$decorations = $list('decorations');
$qualifications = $list('qualifications');
$trainings = $list('trainings');
$operations = $list('operations');
$photos = $list('photos');
$job = $str('job');
$otherJobs = $list('other_jobs');
$post = $str('post');
$since = $str('member_since');
$career = $list('career');
$evalRaw = is_array($cv['evaluations'] ?? null) ? $cv['evaluations'] : [];
$evalItems = is_array($evalRaw['items'] ?? null) ? $evalRaw['items'] : [];
$evalAvg = isset($evalRaw['average']) ? (float) $evalRaw['average'] : null;
$mutations = $list('mutations');
$discord = is_array($cv['discord'] ?? null) ? $cv['discord'] : null;
$distanceModes = $list('distance_modes');
$gameRoles = $list('game_roles');
$pageUrl = (string) ($cv['page_url'] ?? '');
$slug = (string) ($cv['slug'] ?? '');
$og = is_array($cv['og'] ?? null) ? $cv['og'] : [];

$hasFiche = $grade !== null || $unit !== null || $community !== null || $job !== null || $post !== null || $since !== null;
$hasMain = $bio !== null || $career !== [] || $evalItems !== [] || $mutations !== [] || $trainings !== [] || $operations !== [] || $photos !== [];
$hasAside = $hasFiche || $qualifications !== [] || $decorations !== [] || $distanceModes !== [] || $gameRoles !== [];
/* Pastille de fonction dans l’en-tête : l’emploi principal, sinon le poste tenu dans l’unité. */
$jobChip = $job ?? $post;
$jobBadgeHtml = $job !== null && is_array($cv['job_badge'] ?? null) ? \App\Support\JobRoleBadge::html($cv['job_badge'], false) : '';

/* Teinte de l’étiquette d’une opération selon son type. */
$typeTone = ['Opération' => 'op', 'Formation' => 'form'];

/* Métadonnées de partage (Open Graph / Twitter) via le partial commun. */
$seo_og_title = (string) ($og['title'] ?? $callsign);
$meta_description = (string) ($og['description'] ?? '');
$og_image = (string) ($og['image'] ?? '');
$og_image_alt = 'Portrait de ' . $callsign;
$seo_og_type = 'profile';
$seo_robots = 'noindex,follow';
if ($pageUrl !== '') {
    $seo_canonical = $pageUrl;
}
$seo_json_ld = [
    '@type' => 'ProfilePage',
    'mainEntity' => array_filter([
        '@type' => 'Person',
        'name' => $callsign,
        'jobTitle' => $grade,
        'memberOf' => $community !== null ? ['@type' => 'Organization', 'name' => $community] : null,
    ], static fn ($v): bool => $v !== null),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#050505">
    <title><?= $h($seo_og_title) ?></title>
<?php require base_path('views/partials/seo_meta.php'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;0,800;0,900;1,800;1,900&family=JetBrains+Mono:wght@400;500&family=Source+Serif+4:opsz,wght@8..60,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --void: #050505;
            --ink: #f4f4f0;
            --ink-muted: rgba(244, 244, 240, .6);
            --rule: rgba(244, 244, 240, .14);
            --accent: #34d399;
            --accent-strong: #6ee7b7;
            --accent-ink: #052e1c;
            --field: #0b3d2e;
            --field-deep: #06261c;
            --sans: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
            --serif: 'Source Serif 4', Georgia, 'Times New Roman', serif;
            --mono: 'JetBrains Mono', ui-monospace, Consolas, monospace;
            --ease-out: cubic-bezier(.22, 1, .36, 1);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; background: var(--void); color: var(--ink); font-family: var(--sans); -webkit-font-smoothing: antialiased; }
        body { min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }
        img { max-width: 100%; display: block; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { color: var(--accent-strong); }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        .pcv-skip { position: absolute; left: -9999px; top: 8px; z-index: 30; padding: 8px 14px; background: var(--accent); color: var(--accent-ink); font-weight: 800; }
        .pcv-skip:focus { left: 8px; color: var(--accent-ink); }

        .pcv-k { margin: 0; font-size: .6875rem; font-weight: 600; letter-spacing: .28em; text-transform: uppercase; color: var(--ink-muted); }
        .pcv-mono { font-family: var(--mono); }
        .pcv-wrap { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 clamp(16px, 4vw, 48px); }

        /* Barre du haut */
        .pcv-top { position: sticky; top: 0; z-index: 20; background: rgba(5, 5, 5, .72); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-bottom: 1px solid var(--rule); }
        .pcv-top .pcv-wrap { display: flex; align-items: center; justify-content: space-between; gap: 24px; height: 60px; }
        .pcv-brand { font-size: 20px; font-weight: 900; font-style: italic; letter-spacing: -.02em; text-transform: uppercase; color: var(--ink); }
        .pcv-brand:hover { color: var(--ink); }
        .pcv-brand span { color: var(--accent); }

        /* Boutons */
        .pcv-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; height: 44px; padding: 0 20px; border: 1px solid var(--rule); background: transparent; color: var(--ink); font: 900 11px/1 var(--sans); letter-spacing: .22em; text-transform: uppercase; white-space: nowrap; cursor: pointer; transition: border-color .14s, background-color .14s, color .14s; }
        .pcv-btn:hover { border-color: var(--ink); color: var(--ink); }
        .pcv-btn--sm { height: 36px; padding: 0 16px; }
        .pcv-btn--pri { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); }
        .pcv-btn--pri:hover { background: var(--accent-strong); border-color: var(--accent-strong); color: var(--accent-ink); }
        .pcv-btn svg { flex: none; width: 15px; height: 15px; }

        /* En-tête : portrait, indicatif, identité */
        .pcv-hero { position: relative; display: flex; align-items: flex-end; min-height: 520px; overflow: hidden; border-bottom: 1px solid var(--rule); background: radial-gradient(120% 90% at 18% 100%, rgba(11, 61, 46, .55), transparent 60%), var(--void); }
        .pcv-hero-bg { position: absolute; inset: 0; width: 100%; height: 100%; max-width: none; object-fit: cover; object-position: center 35%; filter: grayscale(.35) contrast(1.05); transform: scale(1.04); }
        .pcv-hero::after { content: ""; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(180deg, rgba(5, 5, 5, .35) 0%, rgba(5, 5, 5, .55) 45%, var(--void) 100%); }
        .pcv-hero--plain::after { background-image: linear-gradient(rgba(244, 244, 240, .035) 1px, transparent 1px), linear-gradient(90deg, rgba(244, 244, 240, .035) 1px, transparent 1px); background-size: 40px 40px; -webkit-mask-image: linear-gradient(180deg, #000 0%, transparent 85%); mask-image: linear-gradient(180deg, #000 0%, transparent 85%); }
        .pcv-hero .pcv-wrap { position: relative; z-index: 1; display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 36px; align-items: end; padding-top: 72px; padding-bottom: 48px; }
        .pcv-avatar { position: relative; display: grid; place-items: center; width: 168px; height: 200px; overflow: hidden; border: 1px solid var(--rule); background: linear-gradient(160deg, var(--field), var(--field-deep)); }
        .pcv-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .pcv-avatar b { font-size: 84px; font-weight: 900; font-style: italic; letter-spacing: -.05em; line-height: 1; color: var(--accent); }
        .pcv-avatar-tag { position: absolute; left: 0; right: 0; bottom: 0; padding: 6px 10px; overflow: hidden; background: rgba(5, 5, 5, .72); font: 500 10px/1.4 var(--mono); letter-spacing: .12em; text-transform: uppercase; color: var(--ink-muted); white-space: nowrap; text-overflow: ellipsis; }
        .pcv-callsign { margin: 10px 0 18px; padding-right: .06em; font-size: clamp(2.5rem, 9vw, 6.5rem); font-weight: 900; font-style: italic; letter-spacing: -.045em; line-height: .88; text-transform: uppercase; overflow-wrap: anywhere; }
        .pcv-ident { display: flex; flex-wrap: wrap; gap: 10px; margin: 0; padding: 0; list-style: none; }
        .pcv-chip { display: inline-flex; align-items: center; gap: 8px; min-height: 30px; padding: 4px 12px; border: 1px solid var(--rule); background: rgba(5, 5, 5, .4); font-size: 11px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--ink); }
        .pcv-chip--acc { border-color: var(--accent); color: var(--accent); }
        .pcv-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px rgba(52, 211, 153, .18); }
        .pcv-cta { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }

        /* Compteurs */
        /* Bandeau de 1 à 6 compteurs : filets intérieurs seulement (bords extérieurs masqués par le débord). */
        .pcv-stats-wrap { overflow: hidden; border-bottom: 1px solid var(--rule); }
        .pcv-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin: 0 -1px -1px 0; padding: 0; list-style: none; }
        .pcv-stat { min-width: 0; padding: 28px 24px; border-right: 1px solid var(--rule); border-bottom: 1px solid var(--rule); }
        .pcv-stat-v { margin: 10px 0 0; font-size: clamp(32px, 3.8vw, 54px); font-weight: 900; letter-spacing: -.04em; line-height: 1; font-variant-numeric: tabular-nums; }
        .pcv-stats--dense .pcv-stat { padding: 26px 18px; }
        .pcv-stats--dense .pcv-stat-v { font-size: clamp(28px, 2.9vw, 42px); }
        .pcv-stat-v small { margin-left: 4px; font-size: .4em; font-weight: 700; letter-spacing: 0; color: var(--ink-muted); }
        .pcv-stat-s { margin: 8px 0 0; font-size: 12px; color: var(--ink-muted); }

        /* Corps : colonne principale + fiche latérale */
        .pcv-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr); gap: 56px; padding: 64px 0; }
        .pcv-grid--solo { grid-template-columns: minmax(0, 1fr); }
        .pcv-main > section + section { margin-top: 56px; }
        .pcv-h2 { margin: 8px 0 24px; font-size: 28px; font-weight: 900; font-style: italic; letter-spacing: -.03em; line-height: 1.05; text-transform: uppercase; }
        .pcv-bio { max-width: 62ch; margin: 0; font-family: var(--serif); font-size: 20px; line-height: 1.6; color: var(--ink); white-space: pre-line; text-wrap: pretty; }
        .pcv-bio::first-letter { color: var(--accent); }

        .pcv-tl { margin: 0; padding: 0; list-style: none; border-left: 1px solid var(--rule); }
        .pcv-tl li { position: relative; padding: 0 0 28px 28px; }
        .pcv-tl li:last-child { padding-bottom: 4px; }
        .pcv-tl li::before { content: ""; position: absolute; left: -5px; top: 6px; width: 9px; height: 9px; border: 1px solid var(--accent); background: var(--void); }
        .pcv-tl li:first-child::before { background: var(--accent); }
        .pcv-tl-d { font: 400 11px/1.4 var(--mono); letter-spacing: .08em; color: var(--ink-muted); }
        .pcv-tl-t { margin: 4px 0 2px; font-size: 17px; font-weight: 800; }
        .pcv-tl-u { font-size: 14px; color: var(--ink-muted); }
        .pcv-eval-avg { margin: -8px 0 20px; font: 800 15px/1.4 var(--sans); }
        .pcv-eval-avg small { color: var(--ink-muted); font-weight: 400; }
        .pcv-stars { letter-spacing: .12em; color: #e8a33a; }
        .pcv-eval-c { margin: 6px 0 0; font-size: 14px; line-height: 1.55; color: var(--ink); white-space: pre-line; }

        .pcv-ops { margin: 0; padding: 0; list-style: none; border-top: 1px solid var(--rule); }
        .pcv-op { display: grid; grid-template-columns: 96px minmax(0, 1fr) auto; gap: 20px; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--rule); }
        .pcv-op time { font: 400 12px/1.4 var(--mono); color: var(--ink-muted); }
        .pcv-op-n { font-size: 16px; font-weight: 800; overflow-wrap: anywhere; }
        .pcv-tag { padding: 5px 8px; border: 1px solid rgba(244, 244, 240, .3); font-size: 10px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: var(--ink-muted); }
        .pcv-tag--op { border-color: rgba(52, 211, 153, .4); color: var(--accent); }
        .pcv-tag--form { border-color: rgba(252, 211, 77, .35); color: #fcd34d; }

        .pcv-photos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 0; padding: 0; list-style: none; }
        .pcv-photos li { aspect-ratio: 1; overflow: hidden; border: 1px solid var(--rule); background: var(--field-deep); }
        .pcv-photos img { width: 100%; height: 100%; object-fit: cover; }

        .pcv-card { padding: 24px; border: 1px solid var(--rule); background: rgba(244, 244, 240, .02); }
        .pcv-card + .pcv-card { margin-top: 24px; }
        .pcv-dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 12px 20px; margin: 16px 0 0; font-size: 14px; }
        .pcv-dl dt { color: var(--ink-muted); }
        .pcv-dl dd { margin: 0; text-align: right; font-weight: 600; overflow-wrap: anywhere; }
        .pcv-quals { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0 0; padding: 0; list-style: none; }
        .pcv-bars { display: flex; flex-direction: column; gap: 14px; margin: 16px 0 0; padding: 0; list-style: none; }
        .pcv-bar-r { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; font-weight: 600; }
        .pcv-bar-r em { flex: none; font: normal 400 12px/1.5 var(--mono); color: var(--ink-muted); }
        .pcv-bar-r small { margin-left: 6px; font-size: 12px; font-weight: 400; color: var(--ink-muted); }
        .pcv-bar { height: 4px; margin-top: 8px; background: var(--rule); }
        .pcv-bar i { display: block; height: 100%; background: var(--accent); }
        .pcv-q { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border: 1px solid var(--rule); font-size: 12px; font-weight: 700; }
        .pcv-q i { font: normal 500 10px/1 var(--mono); color: var(--accent); }
        .pcv-q img { width: 18px; height: 18px; object-fit: contain; }
        .pcv-q small { margin-left: 4px; font-size: 12px; font-weight: 600; color: var(--ink-muted); }
        .pcv-medals { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 16px 0 0; padding: 0; list-style: none; }
        .pcv-medal { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; min-width: 0; aspect-ratio: 1; padding: 8px; border: 1px solid var(--rule); text-align: center; }
        .pcv-medal img { max-width: 56%; max-height: 46%; object-fit: contain; }
        .pcv-ribbon { width: 38px; height: 12px; background: repeating-linear-gradient(90deg, var(--field) 0 8px, var(--accent) 8px 12px, var(--field) 12px 26px, var(--accent) 26px 30px, var(--field) 30px 38px); }
        .pcv-medal-t { display: -webkit-box; overflow: hidden; -webkit-line-clamp: 3; -webkit-box-orient: vertical; font-size: 10px; letter-spacing: .08em; line-height: 1.3; text-transform: uppercase; color: var(--ink-muted); overflow-wrap: anywhere; }
        .pcv-aside--wide { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr)); gap: 24px; align-items: start; }
        .pcv-aside--wide .pcv-card + .pcv-card { margin-top: 0; }

        .pcv-empty { margin: 64px 0; padding: 48px 24px; border: 1px dashed var(--rule); text-align: center; }
        .pcv-empty p { margin: 0; }
        .pcv-empty p + p { margin-top: 6px; font-size: 14px; color: var(--ink-muted); }

        .pcv-foot { margin-top: auto; padding: 24px 0; border-top: 1px solid var(--rule); font-size: 12px; color: var(--ink-muted); }
        .pcv-foot .pcv-wrap { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px; }
        .pcv-foot a { color: var(--ink-muted); text-decoration: underline; text-underline-offset: 3px; }
        .pcv-foot a:hover { color: var(--ink); }

        /* Une seule entrée orchestrée au chargement ; rien si l’utilisateur limite les animations. */
        @media (prefers-reduced-motion: no-preference) {
            .pcv-rise { animation: pcv-rise .9s var(--ease-out) both; }
            .pcv-rise-2 { animation-delay: .08s; }
            .pcv-rise-3 { animation-delay: .16s; }
            .pcv-rise-4 { animation-delay: .26s; }
            .pcv-hero-bg { animation: pcv-settle 1.6s var(--ease-out) both; }
        }
        @keyframes pcv-rise { from { opacity: 0; transform: translateY(1.25rem); } to { opacity: 1; transform: none; } }
        @keyframes pcv-settle { from { opacity: 0; transform: scale(1.1); } to { opacity: 1; transform: scale(1.04); } }

        @media (max-width: 900px) {
            .pcv-hero { min-height: 0; }
            .pcv-hero .pcv-wrap { grid-template-columns: minmax(0, 1fr); gap: 24px; padding-top: 48px; padding-bottom: 36px; }
            .pcv-cta { flex-direction: row; flex-wrap: wrap; align-items: flex-start; }
            .pcv-avatar { width: 120px; height: 144px; }
            .pcv-avatar b { font-size: 60px; }
            .pcv-grid { grid-template-columns: minmax(0, 1fr); padding: 48px 0; }
        }
        @media (max-width: 560px) {
            .pcv-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .pcv-stat { padding: 20px 14px; }
            .pcv-op { grid-template-columns: minmax(0, 1fr) auto; gap: 4px 12px; }
            .pcv-op time { grid-column: 1 / -1; }
            .pcv-cta .pcv-btn { flex: 1 1 auto; }
            .pcv-card { padding: 20px; }
            .pcv-bio { font-size: 18px; }
        }
    </style>
</head>
<body>
<a class="pcv-skip" href="#main">Aller au contenu</a>

<header class="pcv-top">
    <div class="pcv-wrap">
        <a class="pcv-brand" href="<?= $h(url('/')) ?>" aria-label="Athena, accueil">Athena<span>.</span></a>
        <a class="pcv-btn pcv-btn--sm" href="<?= $h(url('login')) ?>">Connexion</a>
    </div>
</header>

<main id="main">
    <section class="pcv-hero<?= $banner === null ? ' pcv-hero--plain' : '' ?>" aria-labelledby="pcv-callsign">
        <?php if ($banner !== null): ?>
        <img class="pcv-hero-bg" src="<?= $h($banner) ?>" alt="">
        <?php endif; ?>
        <div class="pcv-wrap">
            <div class="pcv-avatar pcv-rise">
                <?php if ($avatar !== null): ?>
                <img src="<?= $h($avatar) ?>" alt="Portrait de <?= $h($callsign) ?>" width="168" height="200">
                <?php else: ?>
                <b aria-hidden="true"><?= $h(mb_substr($callsign, 0, 1)) ?></b>
                <?php endif; ?>
                <?php if ($gradeShort !== null): ?>
                <span class="pcv-avatar-tag" aria-hidden="true"><?= $h($gradeShort) ?></span>
                <?php endif; ?>
            </div>

            <div class="pcv-rise pcv-rise-2">
                <p class="pcv-k">Dossier opérateur<?= $community !== null ? ' · ' . $h($community) : '' ?></p>
                <h1 id="pcv-callsign" class="pcv-callsign"><?= $h($callsign) ?></h1>
                <ul class="pcv-ident" aria-label="Identité">
                    <li class="pcv-chip pcv-chip--acc"><span class="pcv-dot" aria-hidden="true"></span>Membre actif</li>
                    <?php if ($grade !== null): ?><li class="pcv-chip"><?= $h($grade) ?></li><?php endif; ?>
                    <?php if ($jobChip !== null): ?><li class="pcv-chip"><?= $jobBadgeHtml ?><?= $h($jobChip) ?></li><?php endif; ?>
                    <?php if ($unit !== null): ?><li class="pcv-chip"><?= $h($unit) ?></li><?php endif; ?>
                </ul>
            </div>

            <div class="pcv-cta pcv-rise pcv-rise-3">
                <?php if ($communityUrl !== null): ?>
                <a class="pcv-btn pcv-btn--pri" href="<?= $h($communityUrl) ?>">Découvrir la communauté</a>
                <?php endif; ?>
                <?php if ($discord !== null): ?>
                <a class="pcv-btn" href="<?= $h($discord['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">Discord · <?= $h($discord['name']) ?></a>
                <?php endif; ?>
                <button type="button" class="pcv-btn" data-pcv-share>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v14"/></svg>
                    <span data-pcv-share-label aria-live="polite">Partager le profil</span>
                </button>
            </div>
        </div>
    </section>

    <div class="pcv-wrap">
        <?php if ($counters !== []): ?>
        <div class="pcv-stats-wrap pcv-rise pcv-rise-4">
        <ul class="pcv-stats<?= count($counters) >= 5 ? ' pcv-stats--dense' : '' ?>" aria-label="Compteurs">
            <?php foreach ($counters as $c): ?>
            <li class="pcv-stat">
                <p class="pcv-k"><?= $h($c['label'] ?? '') ?></p>
                <p class="pcv-stat-v"><?= $h($c['number'] ?? ($c['value'] ?? '0')) ?><?php if (!empty($c['unit'])): ?><small><?= $h($c['unit']) ?></small><?php endif; ?></p>
                <?php if (!empty($c['hint'])): ?><p class="pcv-stat-s"><?= $h($c['hint']) ?></p><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        </div>
        <?php endif; ?>

        <?php if ($hasMain || $hasAside): ?>
        <div class="pcv-grid<?= ($hasMain && $hasAside) ? '' : ' pcv-grid--solo' ?>">
            <?php if ($hasMain): ?>
            <div class="pcv-main">
                <?php if ($bio !== null): ?>
                <section id="pcv-bio" aria-labelledby="pcv-h-bio">
                    <p class="pcv-k">Présentation</p>
                    <h2 id="pcv-h-bio" class="pcv-h2">Profil</h2>
                    <p class="pcv-bio"><?= $h($bio) ?></p>
                </section>
                <?php endif; ?>

                <?php if ($career !== []): ?>
                <section id="pcv-career" aria-labelledby="pcv-h-career">
                    <p class="pcv-k">États de service</p>
                    <h2 id="pcv-h-career" class="pcv-h2">Parcours</h2>
                    <ol class="pcv-tl">
                        <?php foreach ($career as $c): ?>
                        <li>
                            <?php if (!empty($c['date'])): ?><div class="pcv-tl-d"><?= $h($c['date']) ?></div><?php endif; ?>
                            <div class="pcv-tl-t"><?= $h($c['title'] ?? '') ?></div>
                            <?php if (!empty($c['subtitle'])): ?><div class="pcv-tl-u"><?= $h($c['subtitle']) ?></div><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <?php endif; ?>

                <?php if ($mutations !== []): ?>
                <section id="pcv-mutations" aria-labelledby="pcv-h-mutations">
                    <h2 id="pcv-h-mutations" class="pcv-h2">Mutations</h2>
                    <ol class="pcv-tl">
                        <?php foreach ($mutations as $m): ?>
                        <li>
                            <?php if (!empty($m['date'])): ?><div class="pcv-tl-d"><?= $h($m['date']) ?></div><?php endif; ?>
                            <div class="pcv-tl-t"><?= $h($m['text'] ?? '') ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <?php endif; ?>

                <?php if ($evalItems !== []): ?>
                <section id="pcv-evaluations" aria-labelledby="pcv-h-evaluations">
                    <h2 id="pcv-h-evaluations" class="pcv-h2">Évaluations</h2>
                    <?php if ($evalAvg !== null): ?><p class="pcv-eval-avg">Moyenne <?= $h(number_format($evalAvg, 1, ',', '')) ?> <small>sur 5</small></p><?php endif; ?>
                    <ol class="pcv-tl">
                        <?php foreach ($evalItems as $e): ?>
                        <li>
                            <?php if (!empty($e['date'])): ?><div class="pcv-tl-d"><?= $h($e['date']) ?><?= !empty($e['stage']) ? ' · ' . $h($e['stage']) : '' ?></div><?php endif; ?>
                            <div class="pcv-tl-t"><?= $h($e['title'] ?? '') ?></div>
                            <?php if (!empty($e['rating'])): ?><div class="pcv-tl-u"><span class="pcv-stars" role="img" aria-label="<?= (int) $e['rating'] ?> sur 5"><?= str_repeat('★', (int) $e['rating']) . str_repeat('☆', 5 - (int) $e['rating']) ?></span></div><?php endif; ?>
                            <?php if (!empty($e['comment'])): ?><p class="pcv-eval-c"><?= $h($e['comment']) ?></p><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <?php endif; ?>

                <?php if ($trainings !== []): ?>
                <section id="pcv-trainings" aria-labelledby="pcv-h-trainings">
                    <p class="pcv-k">Instruction</p>
                    <h2 id="pcv-h-trainings" class="pcv-h2">Formations validées</h2>
                    <ol class="pcv-tl">
                        <?php foreach ($trainings as $t): ?>
                        <li>
                            <?php if (!empty($t['date'])): ?><div class="pcv-tl-d"><?= $h($t['date']) ?></div><?php endif; ?>
                            <div class="pcv-tl-t"><?= $h($t['title'] ?? '') ?></div>
                            <?php if (!empty($t['subtitle'])): ?><div class="pcv-tl-u"><?= $h($t['subtitle']) ?></div><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <?php endif; ?>

                <?php if ($operations !== []): ?>
                <section id="pcv-operations" aria-labelledby="pcv-h-operations">
                    <p class="pcv-k">Journal</p>
                    <h2 id="pcv-h-operations" class="pcv-h2">Dernières opérations</h2>
                    <ul class="pcv-ops">
                        <?php foreach ($operations as $o): ?>
                        <li class="pcv-op">
                            <?php if (!empty($o['day'])): ?>
                            <time datetime="<?= $h($o['iso'] ?? '') ?>"><?= $h($o['day']) ?></time>
                            <?php else: ?><span></span><?php endif; ?>
                            <div class="pcv-op-n"><?= $h($o['title'] ?? '') ?></div>
                            <?php if (!empty($o['type'])): ?>
                            <span class="pcv-tag<?= isset($typeTone[$o['type']]) ? ' pcv-tag--' . $typeTone[$o['type']] : '' ?>"><?= $h($o['type']) ?></span>
                            <?php else: ?><span></span><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <?php if ($photos !== []): ?>
                <section id="pcv-photos" aria-labelledby="pcv-h-photos">
                    <p class="pcv-k">Sur le terrain</p>
                    <h2 id="pcv-h-photos" class="pcv-h2">Photos</h2>
                    <ul class="pcv-photos">
                        <?php foreach ($photos as $p): ?>
                        <li><img src="<?= $h($p['url'] ?? '') ?>" alt="<?= $h(($p['title'] ?? '') !== '' ? $p['title'] : 'Photo publiée par ' . $callsign) ?>" loading="lazy"></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($hasAside): ?>
            <aside<?= $hasMain ? '' : ' class="pcv-aside--wide"' ?> aria-label="Fiche du joueur">
                <?php if ($hasFiche): ?>
                <div class="pcv-card" id="pcv-unit">
                    <h2 class="pcv-k">Fiche</h2>
                    <dl class="pcv-dl">
                        <?php if ($grade !== null): ?><dt>Grade</dt><dd><?= $h($grade) ?></dd><?php endif; ?>
                        <?php if ($job !== null): ?><dt>Fonction</dt><dd><?= $jobBadgeHtml ?><?= $h($job) ?></dd><?php endif; ?>
                        <?php if ($otherJobs !== []): ?><dt>Autres fonctions</dt><dd><?= $h(implode(', ', $otherJobs)) ?></dd><?php endif; ?>
                        <?php if ($post !== null): ?><dt>Poste</dt><dd><?= $h($post) ?></dd><?php endif; ?>
                        <?php if ($community !== null): ?>
                        <dt>Communauté</dt>
                        <dd><?php if ($communityUrl !== null): ?><a href="<?= $h($communityUrl) ?>"><?= $h($community) ?></a><?php else: ?><?= $h($community) ?><?php endif; ?></dd>
                        <?php endif; ?>
                        <?php if ($unit !== null): ?><dt>Affectation</dt><dd><?= $h($unit) ?></dd><?php endif; ?>
                        <?php if ($since !== null): ?><dt>Membre depuis</dt><dd><?= $h(mb_strtoupper(mb_substr($since, 0, 1)) . mb_substr($since, 1)) ?></dd><?php endif; ?>
                    </dl>
                </div>
                <?php endif; ?>

                <?php if ($qualifications !== []): ?>
                <div class="pcv-card" id="pcv-qualifications">
                    <h2 class="pcv-k">Qualifications</h2>
                    <ul class="pcv-quals">
                        <?php foreach ($qualifications as $q): ?>
                        <?php $qTip = !empty($q['date']) ? 'Obtenue en ' . $q['date'] : ''; ?>
                        <li class="pcv-q"<?= $qTip !== '' ? ' title="' . $h($qTip) . '"' : '' ?>>
                            <?php if (!empty($q['code'])): ?><i><?= $h($q['code']) ?></i><?php elseif (!empty($q['image'])): ?><img src="<?= $h($q['image']) ?>" alt="" loading="lazy"><?php endif; ?>
                            <span><?= $h($q['title'] ?? '') ?><?php if (!empty($q['subtitle'])): ?><small><?= $h($q['subtitle']) ?></small><?php endif; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($gameRoles !== []): ?>
                <div class="pcv-card" id="pcv-roles">
                    <h2 class="pcv-k">Rôles tenus en mission</h2>
                    <ul class="pcv-bars">
                        <?php foreach ($gameRoles as $r): ?>
                        <li>
                            <div class="pcv-bar-r"><span><?= $h($r['label'] ?? '') ?></span><em><?= $h($r['hours'] ?? '') ?></em></div>
                            <div class="pcv-bar" aria-hidden="true"><i style="width:<?= (int) ($r['share'] ?? 0) ?>%"></i></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($distanceModes !== []): ?>
                <div class="pcv-card" id="pcv-activity">
                    <h2 class="pcv-k">Kilomètres parcourus</h2>
                    <ul class="pcv-bars">
                        <?php foreach ($distanceModes as $d): ?>
                        <li>
                            <div class="pcv-bar-r"><span><?= $h($d['label'] ?? '') ?><?php if (!empty($d['note'])): ?><small><?= $h($d['note']) ?></small><?php endif; ?></span><em><?= $h($d['km'] ?? '') ?> km</em></div>
                            <div class="pcv-bar" aria-hidden="true"><i style="width:<?= (int) ($d['share'] ?? 0) ?>%"></i></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($decorations !== []): ?>
                <div class="pcv-card" id="pcv-decorations">
                    <h2 class="pcv-k">Décorations</h2>
                    <ul class="pcv-medals">
                        <?php foreach ($decorations as $d): ?>
                        <?php $dTip = implode(', ', array_filter([$d['subtitle'] ?? null, !empty($d['date']) ? 'remise en ' . $d['date'] : null])); ?>
                        <li class="pcv-medal"<?= $dTip !== '' ? ' title="' . $h($dTip) . '"' : '' ?>>
                            <?php if (!empty($d['image'])): ?>
                            <img src="<?= $h($d['image']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                            <span class="pcv-ribbon" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="pcv-medal-t"><?= $h($d['title'] ?? '') ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </aside>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="pcv-empty">
            <p>Le parcours de <?= $h($callsign) ?> commence.</p>
            <p>Formations, qualifications et opérations apparaîtront ici au fil des parties.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<footer class="pcv-foot">
    <div class="pcv-wrap">
        <span>Page publiée par <?= $h($callsign) ?> sur <a href="<?= $h(url('/')) ?>">Athena</a></span>
        <?php if ($slug !== ''): ?><span class="pcv-mono">/joueur/<?= $h($slug) ?></span><?php endif; ?>
    </div>
</footer>

<script>
(function () {
    var btn = document.querySelector('[data-pcv-share]');
    if (!btn) return;
    var label = btn.querySelector('[data-pcv-share-label]');
    var initial = label.textContent;
    var url = <?= json_encode($pageUrl !== '' ? $pageUrl : null, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?> || window.location.href;
    var title = <?= json_encode($seo_og_title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    btn.addEventListener('click', function () {
        if (navigator.share) {
            navigator.share({ title: title, url: url }).catch(function () {});
            return;
        }
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                label.textContent = 'Lien copié';
                setTimeout(function () { label.textContent = initial; }, 2000);
            }, function () {});
        }
    });
})();
</script>
</body>
</html>
