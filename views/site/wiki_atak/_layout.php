<?php
declare(strict_types=1);
/**
 * Cadre commun du wiki ATAK : thème jour / nuit (même choix que /atak-natif), fil d'Ariane, recherche,
 * sommaire latéral par catégorie. Attendu : $wikiLang, $wikiCatalog, $wikiCrumbs (liste [libellé, url|null]),
 * $wikiMain (HTML du contenu), $wikiCurrent (slug de la page ou '').
 * Le contenu tiré des fichiers du mod porte translate="no" : il est déjà dans la langue du visiteur.
 */
use App\Support\Wiki\AtakWiki;

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$wikiLang = (string) ($wikiLang ?? 'fr');
$wikiCurrent = (string) ($wikiCurrent ?? '');
$wikiCrumbs = is_array($wikiCrumbs ?? null) ? $wikiCrumbs : [];
$wikiQuery = (string) ($wikiQuery ?? '');
$cats = $wikiCatalog['categories'];
$currentCat = $wikiCurrent !== '' ? (string) ($wikiCatalog['pages'][$wikiCurrent]['category'] ?? '') : '';
$siteRoot = \App\Support\Seo::baseUrl();
$crumbLd = [];
foreach ($wikiCrumbs as $n => [$label, $href]) {
    $crumbLd[] = ['@type' => 'ListItem', 'position' => $n + 1, 'name' => $label] + ($href !== null ? ['item' => $href] : []);
}
?>
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbLd], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<div class="atw" id="atw" data-theme="night">
    <script>
        // Thème avant le premier rendu : choix mémorisé (partagé avec la page ATAK), sinon celui du système.
        (function () {
            var el = document.getElementById('atw'), pref = null;
            try { pref = localStorage.getItem('athena.atak.theme'); } catch (e) {}
            if (pref !== 'day' && pref !== 'night') {
                pref = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'day' : 'night';
            }
            el.setAttribute('data-theme', pref);
        })();
    </script>

    <div class="atw-bar">
        <div class="atw-wrap atw-bar__inner">
            <a class="atw-brand" href="<?= $e(AtakWiki::url()) ?>">
                <span class="atw-brand__mark" aria-hidden="true"></span>
                <span>Wiki ATAK</span>
            </a>
            <form class="atw-search" action="<?= $e(AtakWiki::url('recherche')) ?>" method="get" role="search">
                <label class="atw-sr" for="atw-q">Rechercher dans le wiki</label>
                <input id="atw-q" type="search" name="q" value="<?= $e($wikiQuery) ?>" placeholder="Rechercher une app, un réglage, une touche…" maxlength="120" autocomplete="off">
                <button type="submit" aria-label="Rechercher"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg></button>
            </form>
            <nav class="atw-bar__links" aria-label="Wiki ATAK">
                <a href="<?= $e(AtakWiki::url('apps')) ?>"<?= $wikiCurrent === 'apps' ? ' aria-current="page"' : '' ?>>Toutes les apps</a>
                <a href="<?= $e(AtakWiki::url('nouveautes')) ?>"<?= $wikiCurrent === 'nouveautes' ? ' aria-current="page"' : '' ?>>Nouveautés</a>
                <a class="atw-bar__steam" href="<?= $e(AtakWiki::WORKSHOP_URL) ?>" target="_blank" rel="noopener noreferrer">Workshop Steam</a>
                <button type="button" class="atw-theme" data-atw-theme aria-label="Mode jour ou nuit" title="Mode jour ou nuit">
                    <svg class="atw-theme__sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <svg class="atw-theme__moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
                </button>
            </nav>
        </div>
    </div>

    <div class="atw-wrap atw-grid">
        <aside class="atw-side">
            <details class="atw-side__box" data-atw-side>
                <summary>Toutes les apps par catégorie</summary>
                <nav aria-label="Toutes les apps par catégorie">
                    <?php foreach ($cats as $cat): ?>
                    <details class="atw-side__cat" translate="no"<?= $currentCat === $cat['key'] || $currentCat === '' ? ' open' : '' ?>>
                        <summary><?= $e((string) $cat[$wikiLang]) ?></summary>
                        <ul>
                            <?php foreach ($cat['items'] as $it): ?>
                            <?php if ($it['slug'] === null): ?>
                            <li><span class="atw-side__soon"><?= $e((string) $it[$wikiLang]['name']) ?></span></li>
                            <?php else: ?>
                            <li><a href="<?= $e(AtakWiki::url((string) $it['slug'])) ?>"<?= $it['slug'] === $wikiCurrent ? ' aria-current="page"' : '' ?>><?= $e((string) $it[$wikiLang]['name']) ?></a></li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php endforeach; ?>
                </nav>
            </details>
        </aside>

        <main class="atw-main">
            <nav class="atw-crumbs" aria-label="Fil d’Ariane">
                <ol>
                    <?php foreach ($wikiCrumbs as $n => [$label, $href]): ?>
                    <li><?php if ($href !== null && $n < count($wikiCrumbs) - 1): ?><a href="<?= $e($href) ?>"><?= $label === 'Athena' ? 'Athena' : '<span translate="no">' . $e($label) . '</span>' ?></a><?php else: ?><span aria-current="page" translate="no"><?= $e($label) ?></span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?= $wikiMain ?>
        </main>
    </div>

    <script>
        (function () {
            var root = document.getElementById('atw');
            var btn = root && root.querySelector('[data-atw-theme]');
            if (btn) {
                btn.addEventListener('click', function () {
                    var next = root.getAttribute('data-theme') === 'day' ? 'night' : 'day';
                    root.setAttribute('data-theme', next);
                    try { localStorage.setItem('athena.atak.theme', next); } catch (e) {}
                });
            }
            // Sommaire latéral déplié sur grand écran, replié sur téléphone.
            var side = root && root.querySelector('[data-atw-side]');
            if (side && window.matchMedia && window.matchMedia('(min-width: 960px)').matches) {
                side.setAttribute('open', '');
            }
        })();
    </script>
</div>
