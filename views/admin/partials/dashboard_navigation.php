<?php

declare(strict_types=1);

$tools = [
    'Pilotage' => [
        'admin?onglet=sante' => 'Santé et communication',
        'admin?onglet=chiffres' => 'Comptes et communautés',
        'admin?onglet=activite' => 'Activité et modération',
        'admin/analytics' => 'Analyses d’usage',
    ],
    'Exploitation protégée' => [
        'admin?onglet=etat' => 'État technique',
        'admin?onglet=migrations' => 'Migrations',
        'admin?onglet=sauvegardes' => 'Sauvegardes',
        'admin?onglet=donnees' => 'Comptes et données',
    ],
    'Gestion' => [],
];
foreach ($homeQuickLinks as $quickLink) {
    $tools['Gestion'][$quickLink['href']] = $quickLabels[$quickLink['key']] ?? $quickLink['key'];
}
?>
<nav class="ph-console-nav" aria-label="Console d’exploitation">
    <a href="#hub-moderation">À traiter</a>
    <a href="#hub-sante">Santé</a>
    <a href="#hub-plateforme">Indicateurs</a>
    <a href="#hub-statistiques">Statistiques et PDF</a>
    <button type="button" class="pa-btn pa-btn--solid" data-dashboard-open="ph-tools-dialog" aria-haspopup="dialog">Outils <kbd aria-hidden="true">Ctrl K</kbd></button>
</nav>
<noscript><?php require base_path('views/admin/partials/pilotage_tabs.php'); ?></noscript>
<dialog class="ph-dialog" id="ph-tools-dialog" aria-labelledby="ph-tools-title">
    <div class="ph-dialog__head">
        <h2 id="ph-tools-title">Outils d’administration</h2>
        <form method="dialog"><button class="pa-btn pa-btn--ghost">Fermer</button></form>
    </div>
    <label for="ph-tool-filter">Rechercher un outil</label>
    <input type="search" id="ph-tool-filter" autocomplete="off" data-dashboard-tool-filter autofocus>
    <p data-dashboard-tool-empty hidden>Aucun outil ne correspond.</p>
    <?php foreach ($tools as $group => $links): ?>
        <?php if ($links === []) { continue; } ?>
        <section data-dashboard-tool-group>
            <h3><?= $h($group) ?></h3>
            <div class="ph-dialog__links">
                <?php foreach ($links as $path => $label): ?>
                    <a href="<?= $h(url($path)) ?>" data-dashboard-tool><?= $h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</dialog>
