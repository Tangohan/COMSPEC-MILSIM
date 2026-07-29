<?php
declare(strict_types=1);
$owGuideRevision = (int) ($owGuideRevision ?? 1);
$owGuideRevisionLabel = (string) ($owGuideRevisionLabel ?? '');
$owPackVersion = (string) ($owPackVersion ?? '');
$atakModUrl = (string) ($atakModUrl ?? url('atak/mod'));
$atakFormationUrl = (string) ($atakFormationUrl ?? url('atak/mod/formation'));
$atakUrl = (string) ($atakUrl ?? url('atak'));

$tocGroups = [
    [
        'label' => 'Démarrage',
        'items' => [
            ['id' => 'introduction', 'title' => 'Introduction'],
            ['id' => 'installation', 'title' => 'Installation & liaison'],
            ['id' => 'premiere-mission', 'title' => 'Première mission'],
        ],
    ],
    [
        'label' => 'Utilisation',
        'items' => [
            ['id' => 'hub-overwatch', 'title' => 'Hub Overwatch'],
            ['id' => 'carte-marqueurs', 'title' => 'Carte & marqueurs'],
            ['id' => 'rapports-tactiques', 'title' => 'Rapports tactiques'],
            ['id' => 'photos-renseignement', 'title' => 'Photos & renseignement'],
            ['id' => 'sse-personnes', 'title' => 'Renseignement interpersonnel'],
            ['id' => 'medical-alertes', 'title' => 'Medical & alertes'],
        ],
    ],
    [
        'label' => 'Avancé',
        'items' => [
            ['id' => 'realisme-liaison', 'title' => 'Réalisme liaison (1.3.0)'],
            ['id' => 'chef-mission', 'title' => 'Chef de mission & Zeus'],
            ['id' => 'depannage', 'title' => 'Dépannage'],
        ],
    ],
];
?>
<div class="site-docs ow-mod-docs">
    <div class="site-docs__shell">
        <header class="site-docs__hero">
            <p class="ow-mod-docs__eyebrow">ATAK · Overwatch · pack <?= htmlspecialchars($owPackVersion, ENT_QUOTES, 'UTF-8') ?></p>
            <h1>Guide du mod Overwatch</h1>
            <?php if ($owGuideRevisionLabel !== ''): ?>
            <p class="site-docs__revision" role="note">
                Dernière mise à jour : <strong><?= htmlspecialchars($owGuideRevisionLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                — révision n°&nbsp;<?= $owGuideRevision ?>.
            </p>
            <?php endif; ?>
            <p>
                Manuel complet pour utiliser le terminal tactique <strong>COMSPEC Overwatch</strong> en mission Arma :
                installation, liaison avec Athena, hub, cartographie, rapports, réalisme liaison et pilotage OP.
                Ce guide s’adresse aux opérateurs et aux cadres ; les réglages exacts dépendent de votre communauté.
            </p>
            <div class="ow-mod-docs__hero-links">
                <a href="<?= htmlspecialchars($atakModUrl, ENT_QUOTES, 'UTF-8') ?>">Télécharger le pack</a>
                <a href="<?= htmlspecialchars($atakFormationUrl, ENT_QUOTES, 'UTF-8') ?>">Parcours de formation</a>
                <a href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir ATAK</a>
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
                <span class="site-docs__toc-group-label"><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <ul class="site-docs__toc">
                    <?php foreach ($group['items'] as $item): ?>
                    <li>
                        <a href="#<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') ?>" data-site-docs-label="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
            <p class="site-docs__ref-link">
                <a href="<?= htmlspecialchars(url('documentation/marqueurs'), ENT_QUOTES, 'UTF-8') ?>">Bibliothèque de marqueurs</a>
            </p>
            <p class="site-docs__ref-link">
                <a href="<?= htmlspecialchars($atakFormationUrl, ENT_QUOTES, 'UTF-8') ?>">Formation pas à pas →</a>
            </p>
        </aside>

        <div class="site-docs__main">
            <?php require __DIR__ . '/guide-content.php'; ?>
        </div>
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
