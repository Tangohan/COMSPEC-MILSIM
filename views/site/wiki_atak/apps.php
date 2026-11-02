<?php
declare(strict_types=1);
/** Sommaire du wiki ATAK (docs/steam/discussions/00_INDEX.txt) : les apps par catégorie avec leur résumé. */
use App\Support\Wiki\AtakWiki;

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$wikiCurrent = 'apps';
$wikiCrumbs = [['Athena', url('')], ['Wiki ATAK', AtakWiki::url()], [$wikiLang === 'en' ? 'All apps' : 'Toutes les apps', AtakWiki::url('apps')]];
ob_start();
?>
<header class="atw-head">
    <h1 class="atw-title atw-title--page">Toutes les apps</h1>
    <p class="atw-lead">Ctrl+U porte le téléphone dans un coin de l’écran, Ctrl+Maj+U le prend en main. Entre parenthèses : le nom de classe à saisir dans les réglages serveur Apps retirées, obligatoires ou interdites.</p>
    <nav class="atw-chips" aria-label="Catégories">
        <?php foreach ($wikiCatalog['categories'] as $cat): ?>
        <a href="#<?= $e($cat['key']) ?>" translate="no"><?= $e((string) $cat[$wikiLang]) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<?php foreach ($wikiCatalog['categories'] as $cat): ?>
<section class="atw-section" id="<?= $e($cat['key']) ?>">
    <h2 class="atw-h2" translate="no"><?= $e((string) $cat[$wikiLang]) ?></h2>
    <ul class="atw-applist">
        <?php foreach ($cat['items'] as $it): ?>
        <li class="<?= $it['slug'] === null ? 'is-soon' : '' ?>">
            <?php if ($it['slug'] !== null): ?>
            <a class="atw-applist__name" href="<?= $e(AtakWiki::url((string) $it['slug'])) ?>" translate="no"><?= $e((string) $it[$wikiLang]['name']) ?></a>
            <?php else: ?>
            <span class="atw-applist__name" translate="no"><?= $e((string) $it[$wikiLang]['name']) ?></span>
            <span class="atw-tag">En préparation</span>
            <?php endif; ?>
            <?php if ($it['class'] !== ''): ?><code class="atw-applist__class" title="Nom de classe"><?= $e((string) $it['class']) ?></code><?php endif; ?>
            <?php if ($it[$wikiLang]['desc'] !== ''): ?><span class="atw-applist__desc" translate="no"><?= $e((string) $it[$wikiLang]['desc']) ?></span><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
<?php
$wikiMain = (string) ob_get_clean();
require __DIR__ . '/_layout.php';
