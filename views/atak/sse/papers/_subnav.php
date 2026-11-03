<?php
declare(strict_types=1);
/** Sous-navigation RENS › Identités & documents, et bandeau « simulation ». */
$papersTab = (string) ($papersTab ?? 'documents');
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$papersTabs = [
    'documents' => ['', 'Papiers'],
    'identites' => ['identites', 'Identités'],
    'controles' => ['controles', 'Contrôles reçus'],
    'pays' => ['pays', 'Pays émetteurs'],
];
if (!empty($canMemberPapers)) {
    $papersTabs['membres'] = ['membres', 'Papiers des membres'];
}
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '">';
?>
<div class="idp-sim" role="note">
    <strong>SIMULATION</strong>
    <span>Papiers fictifs pour le jeu, émis par des pays imaginaires. Aucune valeur réelle.</span>
</div>
<nav class="lab-subnav" aria-label="Identités et documents">
    <div class="lab-subnav__group">
        <span class="lab-subnav__group-label">Registre</span>
        <div class="lab-subnav__links" role="list">
            <?php foreach ($papersTabs as $key => [$path, $label]): ?>
                <?php $active = $papersTab === $key; ?>
                <a role="listitem" class="lab-subnav__link<?= $active ? ' is-active' : '' ?>"
                   href="<?= $h(url('atak/sse/papiers' . ($path !== '' ? '/' . $path : ''))) ?>"
                   <?= $active ? 'aria-current="page"' : '' ?>><?= $h($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
