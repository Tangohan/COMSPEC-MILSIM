<?php
declare(strict_types=1);
/** Nouveautés du wiki ATAK : le « Résumé joueur » de chaque version de CHANGELOG-ATAK.md. */
use App\Support\Wiki\AtakWiki;

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$wikiCurrent = 'nouveautes';
$wikiCrumbs = [['Athena', url('')], ['Wiki ATAK', AtakWiki::url()], [$wikiLang === 'en' ? 'Release notes' : 'Nouveautés', AtakWiki::url('nouveautes')]];
$releases = is_array($releases ?? null) ? $releases : [];
// Type du « Résumé joueur » (nouveauté, correctif, performance, patch), comparé sans accents.
$typeLabel = static fn (string $type): string => match (\App\Support\Wiki\BbcodeRenderer::slug($type)) {
    'nouveaute' => 'Nouveauté',
    'correctif' => 'Correctif',
    'performance' => 'Performance',
    'patch' => 'Patch',
    default => '',
};
ob_start();
?>
<header class="atw-head">
    <h1 class="atw-title atw-title--page">Nouveautés</h1>
    <p class="atw-lead">Ce qui change pour les joueurs à chaque version de COMSPEC ATAK, de la plus récente à la plus ancienne.</p>
</header>

<ol class="atw-releases">
    <?php foreach ($releases as $r): ?>
    <?php $tl = $typeLabel((string) $r['type']); ?>
    <li class="atw-release" id="<?= $e((string) $r['id']) ?>">
        <p class="atw-release__meta">
            <a href="#<?= $e((string) $r['id']) ?>" translate="no"><?= $e($r['version'] !== '' ? 'ATAK ' . $r['version'] : (string) $r['label']) ?></a>
            <?php if ($tl !== ''): ?><span class="atw-tag atw-tag--<?= $e(\App\Support\Wiki\BbcodeRenderer::slug((string) $r['type'])) ?>"><?= $e($tl) ?></span><?php endif; ?>
        </p>
        <h2 translate="no"><?= $e((string) $r[$wikiLang]['title']) ?></h2>
        <?php if ($r[$wikiLang]['bullets'] !== []): ?>
        <ul translate="no"><?php foreach ($r[$wikiLang]['bullets'] as $b): ?><li><?= $e((string) $b) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ol>
<?php
$wikiMain = (string) ob_get_clean();
require __DIR__ . '/_layout.php';
