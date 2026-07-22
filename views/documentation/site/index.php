<?php
declare(strict_types=1);
$siteDocsRevisionNumber = (int) ($siteDocsRevisionNumber ?? 5);
$siteDocsRevisionDateLabel = (string) ($siteDocsRevisionDateLabel ?? '');
$tocGroups = [
    [
        'label' => 'Fondamentaux',
        'items' => [
            ['id' => 'introduction', 'title' => 'Introduction'],
            ['id' => 'compte-et-securite', 'title' => 'Compte & sécurité'],
            ['id' => 'communaute-et-contexte', 'title' => 'Communauté & contexte'],
            ['id' => 'etapes-de-mise-en-service', 'title' => 'Étapes de mise en service'],
            ['id' => 'droits-permissions-et-roles', 'title' => 'Droits, permissions & rôles'],
            ['id' => 'roles-communaute-et-metiers', 'title' => 'Rôles communauté & métiers'],
        ],
    ],
    [
        'label' => 'Vie quotidienne',
        'items' => [
            ['id' => 'navigation-et-recherche', 'title' => 'Navigation & recherche'],
            ['id' => 'tableau-de-bord', 'title' => 'Tableau de bord'],
            ['id' => 'mur-operationnel', 'title' => 'Mur & tableau opérationnel'],
            ['id' => 'personnel-et-orbat', 'title' => 'Personnel, ORBAT & profils métier'],
            ['id' => 'dossier-operateur', 'title' => 'Dossier opérateur'],
        ],
    ],
    [
        'label' => 'Échanges & doctrine',
        'items' => [
            ['id' => 'forum-et-briefings', 'title' => 'Forum & briefings'],
            ['id' => 'cooperations-inter-unites', 'title' => 'Coopérations inter-unités'],
            ['id' => 'moderation-forum', 'title' => 'Modération & signalements'],
            ['id' => 'formations', 'title' => 'Formations (LMS & Studio)'],
            ['id' => 'documents', 'title' => 'Documents'],
            ['id' => 'courrier-officiel', 'title' => 'Courrier officiel'],
        ],
    ],
    [
        'label' => 'Opérations & terrain',
        'items' => [
            ['id' => 'evenements-messages-pointage', 'title' => 'Événements, messages & pointage'],
            ['id' => 'equipement-et-modpacks', 'title' => 'Équipement & modpacks'],
            ['id' => 'outils-cartes-et-tactique', 'title' => 'Outils, cartes & tactique'],
            ['id' => 'diapositives-briefing-eden', 'title' => 'Diapositives de briefing (Eden)'],
            ['id' => 'recrutement-et-enrolement', 'title' => 'Recrutement & enrôlement'],
        ],
    ],
    [
        'label' => 'Pilotage',
        'items' => [
            ['id' => 'alertes-et-annonces', 'title' => 'Alertes & annonces'],
            ['id' => 'analytics-et-integrations', 'title' => 'Analytics & intégrations'],
            ['id' => 'abonnements-et-limites-plan', 'title' => 'Abonnements, quotas & limites'],
            ['id' => 'pilotage-organisation', 'title' => 'Pilotage d’organisation'],
            ['id' => 'bonnes-pratiques', 'title' => 'Bonnes pratiques'],
        ],
    ],
];
?>
<div class="site-docs">
    <div class="site-docs__shell">
        <header class="site-docs__hero">
            <h1>Guide du portail</h1>
            <?php if ($siteDocsRevisionDateLabel !== ''): ?>
            <p class="site-docs__revision" role="note">
                Dernière mise à jour du guide : <strong><?= htmlspecialchars($siteDocsRevisionDateLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                — révision n°&nbsp;<?= $siteDocsRevisionNumber ?>.
            </p>
            <?php endif; ?>
            <p>
                Manuel complet du portail <strong>Athena</strong> : compte et sécurité, droits et rôles, mur opérationnel,
                effectifs et ORBAT, forum, formations, documents, courrier, outils tactiques (ATAK, Overwatch, TACMAP),
                recrutement, coopérations entre communautés et pilotage d’organisation.
                Chaque chapitre se lit indépendamment ; les écrans exacts dépendent de vos habilitations et du plan de votre unité.
            </p>
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
    var sidebar = document.getElementById('site-docs-sidebar');
    var tocToggle = document.getElementById('site-docs-toc-toggle');
    if (!sidebar) return;

    function norm(s) {
        return (s || '').toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
    }
    function applyFilter() {
        if (!input) return;
        var q = norm(input.value.trim());
        sidebar.querySelectorAll('a[data-site-docs-label]').forEach(function (a) {
            var label = norm(a.getAttribute('data-site-docs-label') || '');
            var match = !q || label.indexOf(q) !== -1;
            a.classList.toggle('site-docs__toc--hidden', !match);
        });
        sidebar.querySelectorAll('[data-site-docs-group]').forEach(function (group) {
            var visible = group.querySelectorAll('a[data-site-docs-label]:not(.site-docs__toc--hidden)').length > 0;
            group.style.display = visible ? '' : 'none';
        });
    }
    if (input) {
        input.addEventListener('input', applyFilter);
        applyFilter();
    }

    var sections = document.querySelectorAll('.site-docs__section[id]');
    var tocLinks = Array.prototype.slice.call(sidebar.querySelectorAll('a[href^="#"]'));

    function setCurrentSection(id) {
        tocLinks.forEach(function (a) {
            var href = a.getAttribute('href') || '';
            if (href === '#' + id) {
                a.setAttribute('aria-current', 'true');
            } else {
                a.removeAttribute('aria-current');
            }
        });
    }

    function pickActiveSection() {
        if (!sections.length) return null;
        var limit = 130;
        var best = null;
        var bestTop = -Infinity;
        for (var i = 0; i < sections.length; i++) {
            var sec = sections[i];
            var r = sec.getBoundingClientRect();
            if (r.top <= limit && r.top > bestTop) {
                bestTop = r.top;
                best = sec.id;
            }
        }
        if (best) return best;
        var last = sections[sections.length - 1];
        if (last.getBoundingClientRect().top < window.innerHeight * 0.5) {
            return last.id;
        }
        return sections[0].id;
    }

    var ticking = false;
    function onScrollOrResize() {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                ticking = false;
                var id = pickActiveSection();
                if (id) setCurrentSection(id);
            });
            ticking = true;
        }
    }
    window.addEventListener('scroll', onScrollOrResize, { passive: true });
    window.addEventListener('resize', onScrollOrResize);
    onScrollOrResize();

    if (tocToggle && sidebar) {
        tocToggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('site-docs__sidebar--open');
            tocToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        tocLinks.forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    sidebar.classList.remove('site-docs__sidebar--open');
                    tocToggle.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }
})();
</script>
