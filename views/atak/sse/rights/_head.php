<?php
declare(strict_types=1);
/**
 * Droits d'accès SSE : feuilles de style (kit « SSE Fichiers » et celle des droits), onglets de la page et message de
 * migration absente. Aucun surtitre : le titre de la page est le premier texte.
 *
 * @var string $tab grille|membre|qui|historique
 * @var bool $ready les tables des règles existent
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseExtraStyles = ($sseExtraStyles ?? '')
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-files-kit.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-access-rights.css')) . '">';
$sarTabs = [
    'grille' => ['label' => 'Grille des droits', 'path' => $base],
    'membre' => ['label' => 'Droits d’un membre', 'path' => $base . '/membre'],
    'qui' => ['label' => 'Qui a accès', 'path' => $base . '/qui'],
    'historique' => ['label' => 'Historique', 'path' => $base . '/historique'],
];
?>
<nav class="sar-tabs fx" aria-label="Droits d’accès">
    <?php foreach ($sarTabs as $key => $t): ?>
        <a href="<?= $h(url($t['path'])) ?>"<?= ($tab ?? '') === $key ? ' aria-current="page"' : '' ?>><?= $h($t['label']) ?></a>
    <?php endforeach; ?>
</nav>
<?php if (isset($ready) && $ready === false): ?>
    <div class="fx"><div class="fx-note sar-warn" role="alert">
        <b>Droits d’accès non installés.</b>
        <p>Les tables des règles manquent dans cette communauté : un administrateur doit appliquer les migrations. Tant qu’elles manquent, rien n’est restreint ni étendu : les droits d’origine s’appliquent, et aucune règle ne peut être enregistrée.</p>
    </div></div>
<?php endif; ?>
