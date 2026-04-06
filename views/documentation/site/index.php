<?php
declare(strict_types=1);
$toc = [
    ['id' => 'introduction', 'title' => 'Introduction'],
    ['id' => 'compte-et-securite', 'title' => 'Compte & sécurité'],
    ['id' => 'communaute-et-contexte', 'title' => 'Communauté & contexte'],
    ['id' => 'etapes-de-mise-en-service', 'title' => 'Étapes de mise en service'],
    ['id' => 'droits-permissions-et-roles', 'title' => 'Droits, permissions & rôles'],
    ['id' => 'roles-communaute-et-metiers', 'title' => 'Rôles communauté & métiers'],
    ['id' => 'navigation-et-recherche', 'title' => 'Navigation & recherche'],
    ['id' => 'tableau-de-bord', 'title' => 'Tableau de bord'],
    ['id' => 'personnel-et-orbat', 'title' => 'Personnel, ORBAT & profils métier'],
    ['id' => 'dossier-operateur', 'title' => 'Dossier opérateur'],
    ['id' => 'forum-et-briefings', 'title' => 'Forum & briefings'],
    ['id' => 'moderation-forum', 'title' => 'Modération & signalements'],
    ['id' => 'formations', 'title' => 'Formations (LMS & Studio)'],
    ['id' => 'documents', 'title' => 'Documents'],
    ['id' => 'courrier-officiel', 'title' => 'Courrier officiel'],
    ['id' => 'evenements-messages-pointage', 'title' => 'Événements, messages & pointage'],
    ['id' => 'equipement-et-modpacks', 'title' => 'Équipement & modpacks'],
    ['id' => 'outils-cartes-et-tactique', 'title' => 'Outils, cartes & tactique'],
    ['id' => 'recrutement-et-enrolement', 'title' => 'Recrutement & enrôlement'],
    ['id' => 'alertes-et-annonces', 'title' => 'Alertes & annonces'],
    ['id' => 'pilotage-organisation', 'title' => 'Pilotage d’organisation'],
    ['id' => 'bonnes-pratiques', 'title' => 'Bonnes pratiques'],
];
?>
<div class="site-docs">
    <div class="site-docs__shell">
        <header class="site-docs__hero">
            <h1>Guide du portail</h1>
            <p>
                Ce guide décrit le fonctionnement du portail <strong>Athena</strong> : parcours de création, droits et rôles, formations (brouillon, publication, catalogue),
                modération, fiches personnel et profils métier. Il est destiné aux membres connectés ; les écrans exacts dépendent de vos habilitations.
            </p>
            <div class="site-docs__search">
                <label for="site-docs-filter" class="sr-only">Filtrer les rubriques</label>
                <input type="search" id="site-docs-filter" autocomplete="off" placeholder="Filtrer les rubriques…" />
            </div>
        </header>

        <aside class="site-docs__sidebar" aria-label="Sommaire">
            <p class="site-docs__sidebar-title">Sommaire</p>
            <ul class="site-docs__toc" id="site-docs-toc">
                <?php foreach ($toc as $item): ?>
                    <li>
                        <a href="#<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') ?>" data-site-docs-label="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="site-docs__ref-link">
                <a href="<?= htmlspecialchars(url('documentation/references'), ENT_QUOTES, 'UTF-8') ?>">Références projet (équipe)</a>
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
    var toc = document.getElementById('site-docs-toc');
    if (!input || !toc) return;
    function norm(s) {
        return (s || '').toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
    }
    function apply() {
        var q = norm(input.value.trim());
        toc.querySelectorAll('a[data-site-docs-label]').forEach(function (a) {
            var label = norm(a.getAttribute('data-site-docs-label') || '');
            var match = !q || label.indexOf(q) !== -1;
            a.classList.toggle('site-docs__toc--hidden', !match);
        });
    }
    input.addEventListener('input', apply);
    apply();
})();
</script>
