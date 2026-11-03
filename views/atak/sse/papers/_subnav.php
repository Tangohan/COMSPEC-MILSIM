<?php
declare(strict_types=1);
/** Sous-navigation RENS › Identités & documents, et bandeau « simulation ». */
$papersTab = (string) ($papersTab ?? 'documents');
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$papersTabs = [
    'documents' => ['', 'Papiers'],
    'identites' => ['identites', 'Identités'],
    'controles' => ['controles', 'Contrôles reçus'],
    'tableau' => ['tableau', 'Tableau de bord'],
    'pays' => ['pays', 'Pays émetteurs'],
];
if (!empty($canMemberPapers)) {
    $papersTabs['membres'] = ['membres', 'Papiers des membres'];
}
// Atelier (COMSPEC IDENTITY, lot 6) : réservé aux responsables RENS (gestion SSE ou habilitation Encadrement).
$workshopTabs = !empty($canWorkshop) ? [
    'atelier' => ['atelier', 'Atelier de documents'],
    'kits' => ['kits-types', 'Kits types'],
    'preparation' => ['preparation', 'Préparation de mission'],
    'briefs' => ['briefs', 'Briefs de couverture'],
    'echanges' => ['echanges', 'Import et export'],
] : [];
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
    <?php if ($workshopTabs !== []): ?>
        <div class="lab-subnav__group">
            <span class="lab-subnav__group-label">Atelier</span>
            <div class="lab-subnav__links" role="list">
                <?php foreach ($workshopTabs as $key => [$path, $label]): ?>
                    <?php $active = $papersTab === $key; ?>
                    <a role="listitem" class="lab-subnav__link<?= $active ? ' is-active' : '' ?>"
                       href="<?= $h(url('atak/sse/papiers/' . $path)) ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</nav>
