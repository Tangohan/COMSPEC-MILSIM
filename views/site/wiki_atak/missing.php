<?php
declare(strict_types=1);
/** Adresse du wiki ATAK sans page (404). */
use App\Support\Wiki\AtakWiki;

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$wikiCurrent = '';
$wikiCrumbs = [['Athena', url('')], ['Wiki ATAK', AtakWiki::url()]];
ob_start();
?>
<header class="atw-head">
    <h1 class="atw-title atw-title--page">Page introuvable</h1>
    <p class="atw-lead">Cette page du wiki n’existe pas ou a changé d’adresse. Cherchez l’app dans la liste ou avec le champ de recherche.</p>
    <p class="atw-cta"><a class="atw-btn atw-btn--solid" href="<?= $e(AtakWiki::url('apps')) ?>">Toutes les apps</a> <a class="atw-btn" href="<?= $e(AtakWiki::url()) ?>">Accueil du wiki</a></p>
</header>
<?php
$wikiMain = (string) ob_get_clean();
require __DIR__ . '/_layout.php';
