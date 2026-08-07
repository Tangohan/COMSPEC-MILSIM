<?php
declare(strict_types=1);
$sseGuideRevision = (int) ($sseGuideRevision ?? 1);
$sseGuideRevisionLabel = (string) ($sseGuideRevisionLabel ?? '');
$ssePortalUrl = (string) ($ssePortalUrl ?? url('atak/sse/operations'));
$sseLabUrl = (string) ($sseLabUrl ?? url('atak/sse/exploitation-numerique'));
$sseAccessUrl = (string) ($sseAccessUrl ?? url('atak/sse/acces'));
$canGrant = (bool) ($canGrant ?? false);
$classifBanner = function_exists('sse_ui_classification_label')
    ? sse_ui_classification_label()
    : 'Confidentiel';
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$tocGroups = [
    [
        'label' => 'Fondamentaux',
        'items' => [
            ['id' => 'introduction', 'title' => 'Introduction'],
            ['id' => 'acces-confidentialite', 'title' => 'Accès & confidentialité'],
            ['id' => 'terminologie', 'title' => 'Terminologie'],
            ['id' => 'bureau', 'title' => 'Le bureau SSE'],
        ],
    ],
    [
        'label' => 'Travail quotidien',
        'items' => [
            ['id' => 'dossiers-interet', 'title' => 'Dossiers d’intérêt'],
            ['id' => 'dossiers-valides', 'title' => 'Dossiers validés'],
            ['id' => 'identites-objets', 'title' => 'Identités & objets'],
            ['id' => 'investigations', 'title' => 'Investigations & graphe'],
            ['id' => 'collecte-validation', 'title' => 'Collecte & validation'],
        ],
    ],
    [
        'label' => 'Exploitation',
        'items' => [
            ['id' => 'exploitation-numerique', 'title' => 'Exploitation numérique'],
            ['id' => 'redaction-rapports', 'title' => 'Rédaction & rapports'],
            ['id' => 'diffusion-caviardage', 'title' => 'Diffusion & caviardage'],
        ],
    ],
    [
        'label' => 'Pilotage',
        'items' => [
            ['id' => 'administration', 'title' => 'Administration des accès'],
            ['id' => 'terrain-arma', 'title' => 'Terrain & Arma'],
            ['id' => 'bonnes-pratiques', 'title' => 'Bonnes pratiques'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Manuel SSE — Bureau de renseignement</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/site-docs.css')) ?>?v=202608062200">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-docs.css')) ?>?v=202608062200">
</head>
<body class="site-docs sse-docs">

<div class="sse-docs__banner" role="status">
    <div>
        <strong><?= $h(mb_strtoupper($classifBanner)) ?></strong>
        — Manuel opérateur · diffusion restreinte · consultation tracée
    </div>
    <a href="<?= $h($ssePortalUrl) ?>">← Retour au bureau SSE</a>
</div>

<div class="site-docs__shell">
    <header class="site-docs__hero">
        <p class="sse-docs__eyebrow">Athena · Bureau SSE · Manuel opérateur</p>
        <h1>Manuel du renseignement interpersonnel</h1>
        <?php if ($sseGuideRevisionLabel !== ''): ?>
        <p class="site-docs__revision" role="note">
            Dernière mise à jour : <strong><?= $h($sseGuideRevisionLabel) ?></strong>
            — révision n°&nbsp;<?= $sseGuideRevision ?>.
        </p>
        <?php endif; ?>
        <p>
            Guide complet pour travailler dans le <strong>bureau SSE</strong> :
            dossiers d’intérêt, dossiers validés, identités, investigations,
            exploitation numérique, rédaction et diffusion.
            Ce manuel s’adresse aux opérateurs et aux cadres habilités.
            Les propositions du système restent des aides à l’analyse — jamais des preuves.
        </p>
        <div class="sse-docs__hero-links">
            <a href="<?= $h($ssePortalUrl) ?>">Ouvrir la vue opérationnelle</a>
            <a href="<?= $h($sseLabUrl) ?>">Exploitation numérique</a>
            <?php if ($canGrant): ?>
            <a href="<?= $h($sseAccessUrl) ?>">Administration des accès</a>
            <?php endif; ?>
        </div>
        <div class="site-docs__toolbar">
            <button type="button" class="site-docs__toc-toggle" id="site-docs-toc-toggle" aria-expanded="false" aria-controls="site-docs-sidebar">
                Sommaire
            </button>
            <div class="site-docs__search">
                <label for="site-docs-filter" class="sr-only">Filtrer les rubriques</label>
                <input type="search" id="site-docs-filter" autocomplete="off" placeholder="Filtrer les rubriques…" />
            </div>
        </div>
    </header>

    <aside class="site-docs__sidebar" id="site-docs-sidebar" aria-label="Sommaire">
        <p class="site-docs__sidebar-title">Sommaire</p>
        <?php foreach ($tocGroups as $group): ?>
        <div class="site-docs__toc-group" data-site-docs-group>
            <span class="site-docs__toc-group-label"><?= $h($group['label']) ?></span>
            <ul class="site-docs__toc">
                <?php foreach ($group['items'] as $item): ?>
                <li>
                    <a href="#<?= $h($item['id']) ?>" data-site-docs-label="<?= $h($item['title']) ?>">
                        <?= $h($item['title']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
        <p class="site-docs__ref-link">
            <a href="<?= $h($ssePortalUrl) ?>">Retour au bureau →</a>
        </p>
    </aside>

    <div class="site-docs__main">
        <?php require __DIR__ . '/guide-content.php'; ?>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('site-docs-filter');
    var sidebar = document.getElementById('site-docs-sidebar');
    var tocToggle = document.getElementById('site-docs-toc-toggle');
    if (!sidebar) return;

    function norm(s) {
        return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    if (input) {
        input.addEventListener('input', function () {
            var q = norm(input.value.trim());
            sidebar.querySelectorAll('[data-site-docs-group]').forEach(function (group) {
                var visible = 0;
                group.querySelectorAll('a[data-site-docs-label]').forEach(function (link) {
                    var label = norm(link.getAttribute('data-site-docs-label'));
                    var show = q === '' || label.indexOf(q) !== -1;
                    link.parentElement.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                group.style.display = visible > 0 ? '' : 'none';
            });
        });
    }

    if (tocToggle) {
        tocToggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('site-docs__sidebar--open');
            tocToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    var sections = document.querySelectorAll('.site-docs__section[id]');
    var links = sidebar.querySelectorAll('a[href^="#"]');
    if (sections.length && links.length && 'IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var id = entry.target.id;
                    links.forEach(function (a) {
                        a.classList.toggle('is-active', a.getAttribute('href') === '#' + id);
                    });
                }
            });
        }, { rootMargin: '-20% 0px -60% 0px', threshold: 0 });
        sections.forEach(function (s) { observer.observe(s); });
    }
})();
</script>
</body>
</html>
