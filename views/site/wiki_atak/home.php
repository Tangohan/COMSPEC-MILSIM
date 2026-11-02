<?php
declare(strict_types=1);
/** Accueil du wiki ATAK : présentation du mod (description longue), catégories, aide et nouveautés. */
use App\Support\Wiki\AtakWiki;

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$wikiCurrent = '';
$wikiCrumbs = [['Athena', url('')], ['Wiki ATAK', AtakWiki::url()]];
$pagesCount = count(array_filter($wikiCatalog['pages'], static fn (array $p): bool => $p['kind'] === 'app'));
$latest = $wikiCatalog['releases'][0] ?? null;
$intro = is_array($intro ?? null) ? $intro : [];
ob_start();
?>
<header class="atw-hero">
    <p class="atw-kicker">COMSPEC ATAK · Arma 3</p>
    <h1 class="atw-title">Wiki ATAK</h1>
    <p class="atw-lead">Le smartphone tactique du mod COMSPEC ATAK, app par app : à quoi chacune sert, comment l’ouvrir, ses fonctions, un pas-à-pas et ses réglages serveur.</p>
    <div class="atw-cta">
        <a class="atw-btn atw-btn--solid" href="<?= $e(AtakWiki::url('apps')) ?>">Toutes les apps</a>
        <a class="atw-btn" href="<?= $e(AtakWiki::url(AtakWiki::HELP_INSTALL)) ?>">Installer le mod</a>
        <a class="atw-btn" href="<?= $e(AtakWiki::WORKSHOP_URL) ?>" target="_blank" rel="noopener noreferrer">S’abonner sur Steam</a>
    </div>
    <ul class="atw-stats">
        <li><b><?= $pagesCount ?></b> <span>apps documentées</span></li>
        <?php if ($wikiCatalog['version'] !== ''): ?><li><b translate="no"><?= $e($wikiCatalog['version']) ?></b> <span>version décrite</span></li><?php endif; ?>
        <?php if ($latest !== null && $latest['version'] !== ''): ?><li><a href="<?= $e(AtakWiki::url('nouveautes')) ?>"><b translate="no"><?= $e($latest['version']) ?></b> <span>dernière version</span></a></li><?php endif; ?>
    </ul>
</header>

<section class="atw-section" aria-labelledby="atw-cats">
    <h2 class="atw-h2" id="atw-cats">Les apps par catégorie</h2>
    <div class="atw-cards" translate="no">
        <?php foreach ($wikiCatalog['categories'] as $cat): ?>
        <?php $links = array_values(array_filter($cat['items'], static fn (array $it): bool => $it['slug'] !== null)); ?>
        <article class="atw-card">
            <h3><a href="<?= $e(AtakWiki::url('apps') . '#' . $cat['key']) ?>"><?= $e((string) $cat[$wikiLang]) ?></a></h3>
            <p><?php foreach ($links as $k => $it): ?><?= $k > 0 ? ' · ' : '' ?><a href="<?= $e(AtakWiki::url((string) $it['slug'])) ?>"><?= $e((string) $it[$wikiLang]['name']) ?></a><?php endforeach; ?></p>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($latest !== null): ?>
<section class="atw-section" aria-labelledby="atw-latest">
    <h2 class="atw-h2" id="atw-latest">Dernière version</h2>
    <article class="atw-release">
        <p class="atw-release__meta" translate="no"><?= $e($latest['version'] !== '' ? 'ATAK ' . $latest['version'] : $latest['label']) ?></p>
        <h3 translate="no"><?= $e((string) $latest[$wikiLang]['title']) ?></h3>
        <ul translate="no"><?php foreach ($latest[$wikiLang]['bullets'] as $b): ?><li><?= $e((string) $b) ?></li><?php endforeach; ?></ul>
        <a class="atw-more" href="<?= $e(AtakWiki::url('nouveautes')) ?>">Toutes les nouveautés →</a>
    </article>
</section>
<?php endif; ?>

<section class="atw-section atw-doc" aria-labelledby="atw-mod">
    <h2 class="atw-h2" id="atw-mod">Présentation du mod</h2>
    <div class="atw-prose" translate="no">
        <?php foreach ($intro as $sec): ?>
        <h3 id="<?= $e(\App\Support\Wiki\BbcodeRenderer::slug((string) $sec['title'])) ?>"><?= $e((string) $sec['title']) ?></h3>
        <?= $sec['html'] ?>
        <?php endforeach; ?>
    </div>
    <p class="atw-seealso">
        <a href="<?= $e(AtakWiki::url(AtakWiki::HELP_INSTALL)) ?>">Installation, serveur et logs</a>
        · <a href="<?= $e(AtakWiki::url(AtakWiki::HELP_CBA)) ?>">Réglages serveur (CBA)</a>
        · <a href="<?= $e(url('atak-natif')) ?>">Page du mod COMSPEC ATAK</a>
    </p>
</section>
<?php
$wikiMain = (string) ob_get_clean();
require __DIR__ . '/_layout.php';
