<?php
declare(strict_types=1);
/** Page d'une app ou d'un sujet d'aide du wiki ATAK (sujet docs/steam/discussions/NN_<slug>.txt). */
use App\Support\Wiki\AtakWiki;

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$p = $wikiPage;
$wikiCurrent = (string) $p['slug'];
$L = $p[$wikiLang];
$catLabel = '';
$order = [];
foreach ($wikiCatalog['categories'] as $cat) {
    if ($cat['key'] === $p['category']) {
        $catLabel = (string) $cat[$wikiLang];
    }
    foreach ($cat['items'] as $it) {
        if ($it['slug'] !== null) {
            $order[] = ['slug' => (string) $it['slug'], 'name' => (string) $it[$wikiLang]['name']];
        }
    }
}
$pos = array_search($wikiCurrent, array_column($order, 'slug'), true);
$prev = $pos !== false && $pos > 0 ? $order[$pos - 1] : null;
$next = $pos !== false && $pos < count($order) - 1 ? $order[$pos + 1] : null;
$wikiCrumbs = [['Athena', url('')], ['Wiki ATAK', AtakWiki::url()]];
if ($catLabel !== '') {
    $wikiCrumbs[] = [$catLabel, AtakWiki::url('apps') . '#' . $p['category']];
}
$wikiCrumbs[] = [(string) $L['title'], AtakWiki::url($wikiCurrent)];
$toc = $doc['toc'];
ob_start();
?>
<article class="atw-article" lang="<?= $wikiLang === 'en' ? 'en' : 'fr' ?>">
    <header class="atw-head">
        <?php if ($catLabel !== ''): ?><p class="atw-kicker" translate="no"><?= $e($catLabel) ?></p><?php endif; ?>
        <h1 class="atw-title atw-title--page" translate="no"><?= $e((string) $L['title']) ?></h1>
        <?php if ($L['lead'] !== ''): ?><p class="atw-lead" translate="no"><?= $e((string) $L['lead']) ?></p><?php endif; ?>
        <ul class="atw-facts">
            <?php if ($p['class'] !== ''): ?><li><span>Nom de classe</span> <code><?= $e((string) $p['class']) ?></code></li><?php endif; ?>
            <?php if ($L['subtitle'] !== ''): ?><li translate="no"><?= $e((string) $L['subtitle']) ?></li><?php endif; ?>
        </ul>
    </header>

    <?php if (count($toc) > 1): ?>
    <nav class="atw-toc" aria-label="Sur cette page">
        <p class="atw-toc__title">Sur cette page</p>
        <ol translate="no">
            <?php foreach ($toc as $h): ?><li><a href="#<?= $e($h['id']) ?>"><?= $e($h['text']) ?></a></li><?php endforeach; ?>
        </ol>
    </nav>
    <?php endif; ?>

    <div class="atw-prose" translate="no">
        <?= $doc['html'] ?>
    </div>

    <aside class="atw-seealso-box" aria-label="Voir aussi">
        <p class="atw-toc__title">Voir aussi</p>
        <ul>
            <?php if ($wikiCurrent !== AtakWiki::HELP_CBA): ?><li><a href="<?= $e(AtakWiki::url(AtakWiki::HELP_CBA)) ?>">Réglages serveur (CBA)</a></li><?php endif; ?>
            <?php if ($wikiCurrent !== AtakWiki::HELP_INSTALL): ?><li><a href="<?= $e(AtakWiki::url(AtakWiki::HELP_INSTALL)) ?>">Installation, serveur et logs</a></li><?php endif; ?>
            <li><a href="<?= $e(AtakWiki::url('nouveautes')) ?>">Nouveautés de chaque version</a></li>
            <li><a href="<?= $e(AtakWiki::WORKSHOP_URL) ?>" target="_blank" rel="noopener noreferrer">COMSPEC ATAK sur le Workshop Steam</a></li>
        </ul>
    </aside>

    <nav class="atw-pager" aria-label="Apps voisines">
        <?php if ($prev !== null): ?><a class="atw-pager__prev" href="<?= $e(AtakWiki::url($prev['slug'])) ?>"><small>Précédent</small><span translate="no"><?= $e($prev['name']) ?></span></a><?php else: ?><span></span><?php endif; ?>
        <?php if ($next !== null): ?><a class="atw-pager__next" href="<?= $e(AtakWiki::url($next['slug'])) ?>"><small>Suivant</small><span translate="no"><?= $e($next['name']) ?></span></a><?php endif; ?>
    </nav>
</article>
<?php
$wikiMain = (string) ob_get_clean();
require __DIR__ . '/_layout.php';
