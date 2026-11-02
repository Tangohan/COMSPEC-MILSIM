<?php
declare(strict_types=1);
/** Recherche plein texte du wiki ATAK (?q=…). */
use App\Support\Wiki\AtakWiki;
use App\Support\Wiki\Search;

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$wikiCurrent = 'recherche';
$wikiQuery = (string) ($query ?? '');
$wikiCrumbs = [['Athena', url('')], ['Wiki ATAK', AtakWiki::url()], [$wikiLang === 'en' ? 'Search' : 'Recherche', AtakWiki::url('recherche')]];
$results = is_array($results ?? null) ? $results : [];
$terms = is_array($terms ?? null) ? $terms : [];
ob_start();
?>
<header class="atw-head">
    <h1 class="atw-title atw-title--page">Recherche</h1>
    <?php if ($wikiQuery === ''): ?>
    <p class="atw-lead">Saisissez un nom d’app, un bouton, un réglage ou une touche dans le champ de recherche.</p>
    <?php elseif ($results === []): ?>
    <p class="atw-lead">Aucune page ne contient tous ces mots. Essayez un mot plus court ou parcourez toutes les apps.</p>
    <p><a class="atw-btn" href="<?= $e(AtakWiki::url('apps')) ?>">Toutes les apps</a></p>
    <?php else: ?>
    <p class="atw-lead"><span translate="no"><?= count($results) ?></span> <?= count($results) > 1 ? 'pages trouvées' : 'page trouvée' ?></p>
    <?php endif; ?>
</header>

<?php if ($results !== []): ?>
<ol class="atw-results" translate="no">
    <?php foreach ($results as $r): ?>
    <li>
        <a href="<?= $e(AtakWiki::url((string) $r['slug'])) ?>"><?= Search::highlight((string) $r['title'], $terms) ?></a>
        <?php if ($r['summary'] !== ''): ?><p class="atw-results__sum"><?= $e((string) $r['summary']) ?></p><?php endif; ?>
        <p class="atw-results__snip"><?= Search::highlight((string) $r['snippet'], $terms) ?></p>
    </li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>
<?php
$wikiMain = (string) ob_get_clean();
require __DIR__ . '/_layout.php';
