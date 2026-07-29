<?php
declare(strict_types=1);
$markerIconsCdn = (string) ($markerIconsCdn ?? '');
$tocGroups = [
    [
        'label' => 'Catalogue',
        'items' => [
            ['id' => 'intro', 'title' => 'Présentation'],
            ['id' => 'markersplus', 'title' => 'MarkersPlus'],
            ['id' => 'metis-blu', 'title' => 'Metis — amis'],
            ['id' => 'metis-red', 'title' => 'Metis — adverses'],
            ['id' => 'metis-neu', 'title' => 'Metis — neutres'],
            ['id' => 'metis-unk', 'title' => 'Metis — inconnus'],
            ['id' => 'metis-com', 'title' => 'Metis — coalition'],
            ['id' => 'metis-special', 'title' => 'Metis — spéciaux'],
            ['id' => 'ctab', 'title' => 'cTab'],
            ['id' => 'ctab-menu', 'title' => 'cTab — menu'],
            ['id' => 'a3-military', 'title' => 'Arma 3 — militaires'],
            ['id' => 'a3-nato', 'title' => 'Arma 3 — OTAN'],
            ['id' => 'a3-handdrawn', 'title' => 'Arma 3 — dessinés'],
            ['id' => 'a3-flags', 'title' => 'Arma 3 — drapeaux'],
            ['id' => 'a3-system', 'title' => 'Arma 3 — système'],
            ['id' => 'a3-other', 'title' => 'Arma 3 — autres'],
            ['id' => 'catalogue-logique', 'title' => 'Catalogue logique (noms techniques)'],
        ],
    ],
];
$assetV = '202607281920';
?>
<div class="site-docs site-docs--markers">
    <div class="site-docs__shell">
        <header class="site-docs__hero">
            <h1>Bibliothèque de marqueurs</h1>
            <p>
                Légende visuelle de <strong>tous les packs</strong> utilisés en mission :
                Arma 3 (vanilla), MarkersPlus, Metis Marker et cTab.
                Chaque fiche affiche l’icône réelle (PNG), un libellé métier, et le nom technique.
            </p>
            <div class="site-docs__toolbar">
                <button type="button" class="site-docs__toc-toggle" id="site-docs-toc-toggle" aria-expanded="false" aria-controls="site-docs-sidebar">
                    Sommaire
                </button>
                <div class="site-docs__search">
                    <label for="marker-lib-filter" class="sr-only">Filtrer les marqueurs</label>
                    <input type="search" id="marker-lib-filter" autocomplete="off" placeholder="Filtrer par nom, addon ou catégorie…" />
                </div>
            </div>
            <p class="site-docs__markers-count" id="marker-lib-count" role="status" aria-live="polite"></p>
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
                <a href="<?= htmlspecialchars(url('documentation'), ENT_QUOTES, 'UTF-8') ?>">← Guide du portail</a>
            </p>
        </aside>

        <div class="site-docs__main">
            <section id="intro" class="site-docs__section">
                <h2>Présentation</h2>
                <p class="site-docs__lead">
                    Ces symboles servent à lire la situation de la même façon sur la carte web et en jeu.
                    Les packs addons (MarkersPlus, Metis, cTab) complètent le jeu de base Arma&nbsp;3.
                </p>
                <div class="site-docs__callout site-docs__callout--tip">
                    <strong>Comment s’en servir.</strong> Filtrez par addon ou libellé. Le nom technique sert à la
                    synchronisation Overwatch / TACMAP. Les aperçus utilisent les textures converties (PNG) quand elles sont disponibles.
                </div>
                <div class="site-docs__markers-legend" aria-label="Légende des appartenances">
                    <span class="site-docs__markers-chip site-docs__markers-chip--friend">Ami</span>
                    <span class="site-docs__markers-chip site-docs__markers-chip--hostile">Adverse</span>
                    <span class="site-docs__markers-chip site-docs__markers-chip--neutral">Neutre</span>
                    <span class="site-docs__markers-chip site-docs__markers-chip--unknown">Inconnu</span>
                    <span class="site-docs__markers-chip site-docs__markers-chip--civil">Civil</span>
                </div>
            </section>

            <div id="marker-lib-root" class="site-docs__markers-root" aria-busy="true">
                <p class="site-docs__markers-loading">Chargement du catalogue…</p>
            </div>
        </div>
    </div>
</div>

<script>
window.ATAK_MARKER_ICONS_CDN = <?= json_encode($markerIconsCdn, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars(asset_url('assets/js/nato-sidc-icons.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/arma-marker-catalog.js'), ENT_QUOTES, 'UTF-8') ?>&amp;lib=<?= htmlspecialchars($assetV, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/arma-marker-library-index.js'), ENT_QUOTES, 'UTF-8') ?>&amp;lib=<?= htmlspecialchars($assetV, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/arma-map-markers.js'), ENT_QUOTES, 'UTF-8') ?>&amp;lib=<?= htmlspecialchars($assetV, ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
(function () {
    'use strict';

    var ROLE_FR = {
        inf: 'Infanterie', mech_inf: 'Infanterie mécanisée', motor_inf: 'Infanterie motorisée',
        armor: 'Blindé', recon: 'Reconnaissance', air: 'Hélicoptère', plane: 'Avion', uav: 'Drone',
        naval: 'Naval', art: 'Artillerie', mortar: 'Mortier', antiair: 'Défense antiaérienne',
        support: 'Soutien', maint: 'Maintenance', med: 'Santé', hq: 'Poste de commandement',
        ordnance: 'Munitions', installation: 'Installation', unknown: 'Inconnu', service: 'Service',
        car: 'Véhicule léger', ship: 'Navire'
    };
    var AFF_FR = { friend: 'Ami', hostile: 'Adverse', neutral: 'Neutre', unknown: 'Inconnu' };

    var CATEGORIES = [
        { id: 'markersplus', title: 'MarkersPlus', blurb: 'Repères tactiques doctrine (points, actions, soutien).' },
        { id: 'metis-blu', title: 'Metis — unités amies', blurb: 'Symboles Metis Marker (cadre ami).' },
        { id: 'metis-red', title: 'Metis — unités adverses', blurb: 'Symboles Metis Marker (cadre adverse).' },
        { id: 'metis-neu', title: 'Metis — unités neutres', blurb: 'Symboles Metis Marker (cadre neutre).' },
        { id: 'metis-unk', title: 'Metis — unités inconnues', blurb: 'Symboles Metis Marker (cadre inconnu).' },
        { id: 'metis-com', title: 'Metis — coalition', blurb: 'Symboles Metis Marker (cadre coalition).' },
        { id: 'metis-special', title: 'Metis — spéciaux', blurb: 'Symboles spéciaux Metis (hors modulaires).' },
        { id: 'ctab', title: 'cTab', blurb: 'Icônes de carte cTab (contacts, unités enrichies).' },
        { id: 'ctab-menu', title: 'cTab — menu', blurb: 'Symboles du menu marqueurs cTab (SIDC).' },
        { id: 'a3-military', title: 'Arma 3 — militaires', blurb: 'Textures militaires vanilla (mil_*).' },
        { id: 'a3-nato', title: 'Arma 3 — OTAN', blurb: 'Symboles d’unité OTAN vanilla (b_/o_/n_/…).' },
        { id: 'a3-handdrawn', title: 'Arma 3 — dessinés', blurb: 'Annotations hand-drawn (hd_*).' },
        { id: 'a3-flags', title: 'Arma 3 — drapeaux', blurb: 'Drapeaux vanilla.' },
        { id: 'a3-system', title: 'Arma 3 — système', blurb: 'Marqueurs système / spawns.' },
        { id: 'a3-other', title: 'Arma 3 — autres', blurb: 'Autres textures marqueurs vanilla.' },
        { id: 'catalogue-logique', title: 'Catalogue logique', blurb: 'Noms techniques du catalogue runtime (sans PNG dédié).' }
    ];

    function fixMojibake(s) {
        var str = String(s || '');
        if (!/Ã.|â€|Ã‰|Ã¨|Ã©|Ã /.test(str)) return str;
        try {
            var bytes = new Uint8Array(str.length);
            for (var i = 0; i < str.length; i++) bytes[i] = str.charCodeAt(i) & 0xff;
            return new TextDecoder('utf-8').decode(bytes);
        } catch (e) {
            return str;
        }
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
    }

    function cdnUrl(rel) {
        var base = String(window.ATAK_MARKER_ICONS_CDN || '').replace(/\/$/, '');
        if (!base || !rel) return '';
        return base + '/' + String(rel).replace(/^\/+/, '').split('/').map(encodeURIComponent).join('/');
    }

    function classifyCatalog(key, entry) {
        if (key.indexOf('mplus_') === 0) return 'markersplus';
        if (key.indexOf('mts_') === 0) {
            if (key.indexOf('_blu') >= 0 || key.indexOf('mts_blu') === 0) return 'metis-blu';
            if (key.indexOf('_red') >= 0 || key.indexOf('mts_red') === 0) return 'metis-red';
            if (key.indexOf('_neu') >= 0) return 'metis-neu';
            if (key.indexOf('_unk') >= 0) return 'metis-unk';
            if (key.indexOf('_com') >= 0) return 'metis-com';
            return 'metis-special';
        }
        if (key.indexOf('loc_') === 0) return 'a3-other';
        if (entry.kind === 'nato') {
            if (key.indexOf('b_') === 0) return 'a3-nato';
            if (key.indexOf('o_') === 0) return 'a3-nato';
            if (key.indexOf('u_') === 0) return 'a3-nato';
            return 'a3-nato';
        }
        if (key.indexOf('mil_') === 0) return 'a3-military';
        if (key.indexOf('hd_') === 0) return 'a3-handdrawn';
        return 'catalogue-logique';
    }

    function humanCatalogLabel(key, entry) {
        if (entry.kind === 'nato') {
            var affKey = entry.affiliation || 'unknown';
            var aff = AFF_FR[affKey] || 'Inconnu';
            if (key.indexOf('c_') === 0) aff = 'Civil';
            var role = ROLE_FR[entry.role] || 'Unité';
            return aff + ' — ' + role;
        }
        var raw = fixMojibake(entry.label || '');
        if (raw) return raw;
        return 'Repère';
    }

    function previewHtml(it) {
        if (it.pngUrl) {
            return '<img src="' + escapeHtml(it.pngUrl) + '" alt="" loading="lazy" width="40" height="40" style="object-fit:contain;max-width:40px;max-height:40px;" onerror="this.style.display=\'none\';this.nextElementSibling&&(this.nextElementSibling.hidden=false);" />' +
                '<span hidden class="site-docs__markers-fallback" aria-hidden="true">◆</span>';
        }
        if (window.ArmaMapMarkers && typeof window.ArmaMapMarkers.listBadgeHtml === 'function') {
            return window.ArmaMapMarkers.listBadgeHtml({ type: it.key, color: 'ColorWEST' });
        }
        return '<span class="site-docs__markers-fallback" aria-hidden="true">◆</span>';
    }

    function buildItems() {
        var items = [];
        var seen = {};
        var lib = window.ArmaMarkerLibraryIndex;
        if (lib && Array.isArray(lib.ITEMS)) {
            lib.ITEMS.forEach(function (row) {
                var key = String(row.key || '');
                if (!key || seen[key]) return;
                seen[key] = true;
                items.push({
                    key: key,
                    label: fixMojibake(row.label || key),
                    category: row.category || 'catalogue-logique',
                    source: row.source || '',
                    affiliation: row.affiliation || '',
                    pngUrl: cdnUrl(row.png || ''),
                    entry: { kind: row.source === 'metis' ? 'metis' : (row.source === 'markersplus' ? 'mplus' : 'handdrawn'), affiliation: row.affiliation || '' }
                });
            });
        }

        // Compléter avec le catalogue logique (noms techniques runtime) pour ce qui n’a pas de PNG.
        var catalog = window.ArmaMarkerCatalog;
        if (catalog && catalog.ENTRIES) {
            Object.keys(catalog.ENTRIES).forEach(function (key) {
                if (seen[key]) return;
                if (key.indexOf('hd_') === 0) {
                    var milKey = 'mil_' + key.slice(3);
                    if (catalog.ENTRIES[milKey] || seen[milKey]) return;
                }
                var entry = catalog.ENTRIES[key];
                if (!entry) return;
                seen[key] = true;
                items.push({
                    key: key,
                    label: humanCatalogLabel(key, entry),
                    category: classifyCatalog(key, entry),
                    source: entry.source || 'catalog',
                    affiliation: entry.affiliation || '',
                    pngUrl: '',
                    entry: entry
                });
            });
        }
        return items;
    }

    function render(items, query) {
        var root = document.getElementById('marker-lib-root');
        var countEl = document.getElementById('marker-lib-count');
        if (!root) return;

        var q = norm(query || '');
        var visible = items.filter(function (it) {
            if (!q) return true;
            return norm(it.label).indexOf(q) !== -1
                || norm(it.key).indexOf(q) !== -1
                || norm(it.category).indexOf(q) !== -1
                || norm(it.source).indexOf(q) !== -1;
        });

        var byCat = {};
        CATEGORIES.forEach(function (c) { byCat[c.id] = []; });
        visible.forEach(function (it) {
            if (!byCat[it.category]) byCat[it.category] = [];
            byCat[it.category].push(it);
        });

        var html = '';
        CATEGORIES.forEach(function (cat) {
            var list = byCat[cat.id] || [];
            if (!list.length && q) return;
            if (!list.length && !q && cat.id === 'catalogue-logique') return;
            html += '<section id="' + escapeHtml(cat.id) + '" class="site-docs__section site-docs__markers-section" data-marker-cat="' + escapeHtml(cat.id) + '">';
            html += '<h2>' + escapeHtml(cat.title) + ' <span class="site-docs__markers-catcount">(' + list.length + ')</span></h2>';
            html += '<p class="site-docs__lead">' + escapeHtml(cat.blurb) + '</p>';
            if (!list.length) {
                html += '<p class="site-docs__markers-empty">Aucun marqueur dans cette catégorie pour le filtre actuel.</p>';
            } else {
                html += '<ul class="site-docs__markers-grid">';
                list.forEach(function (it) {
                    var affClass = '';
                    if (it.affiliation === 'friend') affClass = ' is-friend';
                    else if (it.affiliation === 'hostile') affClass = ' is-hostile';
                    else if (it.affiliation === 'neutral') affClass = ' is-neutral';
                    else if (it.affiliation === 'unknown') affClass = ' is-unknown';
                    if (it.key.indexOf('c_') === 0) affClass = ' is-civil';
                    html += '<li class="site-docs__markers-card' + affClass + '" data-marker-key="' + escapeHtml(it.key) + '" data-marker-label="' + escapeHtml(it.label) + '">';
                    html += '<div class="site-docs__markers-preview" aria-hidden="true">' + previewHtml(it) + '</div>';
                    html += '<div class="site-docs__markers-meta">';
                    html += '<p class="site-docs__markers-title">' + escapeHtml(it.label) + '</p>';
                    html += '<p class="site-docs__markers-tech"><span class="sr-only">Nom technique : </span>' + escapeHtml(it.key) + '</p>';
                    if (it.source) {
                        html += '<p class="site-docs__markers-tech">' + escapeHtml(it.source) + '</p>';
                    }
                    html += '</div></li>';
                });
                html += '</ul>';
            }
            html += '</section>';
        });

        root.innerHTML = html || '<p>Aucun marqueur ne correspond à votre recherche.</p>';
        root.setAttribute('aria-busy', 'false');
        if (countEl) {
            countEl.textContent = visible.length + ' marqueur' + (visible.length > 1 ? 's' : '') +
                ' affiché' + (visible.length > 1 ? 's' : '') +
                (q ? ' (filtre actif)' : ' dans tous les packs');
        }
    }

    var items = buildItems();
    var filterInput = document.getElementById('marker-lib-filter');
    render(items, filterInput ? filterInput.value : '');

    if (filterInput) {
        filterInput.addEventListener('input', function () {
            render(items, filterInput.value);
        });
    }

    var sidebar = document.getElementById('site-docs-sidebar');
    var tocToggle = document.getElementById('site-docs-toc-toggle');
    if (tocToggle && sidebar) {
        tocToggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('site-docs__sidebar--open');
            tocToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        sidebar.querySelectorAll('a[href^="#"]').forEach(function (a) {
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
