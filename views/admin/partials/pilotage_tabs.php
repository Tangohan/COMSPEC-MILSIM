<?php
declare(strict_types=1);

/**
 * Onglets de la page de pilotage du site : vue d'ensemble puis exploitation (double vérification).
 *
 * @var string $pilotTab onglet courant : vue | sante | chiffres | activite | etat | migrations | sauvegardes | donnees
 * @var array{bad: int, warn: int}|null $opsSummary bilan technique (onglets d'exploitation déverrouillés)
 */
$ptH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ptCurrent = (string) ($pilotTab ?? 'vue');
$ptSummary = isset($opsSummary) && is_array($opsSummary) ? $opsSummary : null;
$ptTabs = [
    'vue' => 'Vue d’ensemble',
    'sante' => 'Santé',
    'chiffres' => 'Chiffres',
    'activite' => 'Activité',
    'etat' => 'État technique',
    'migrations' => 'Migrations',
    'sauvegardes' => 'Sauvegardes',
    'donnees' => 'Comptes et données',
];
?>
<nav class="ops-tabs" aria-label="Sections du pilotage">
    <?php foreach ($ptTabs as $key => $label): ?>
        <a class="ops-tab<?= $ptCurrent === $key ? ' is-active' : '' ?>" href="<?= $ptH($key === 'vue' ? url('admin') : url('admin') . '?onglet=' . $key) ?>"<?= $ptCurrent === $key ? ' aria-current="page"' : '' ?><?= in_array($key, \App\Controllers\Admin\System\SystemOpsConsoleController::TABS, true) ? ' title="Double vérification demandée"' : '' ?>>
            <?= $ptH($label) ?>
            <?php if ($key === 'etat' && $ptSummary !== null && ($ptSummary['bad'] + $ptSummary['warn']) > 0): ?>
                <span class="pa-pill pa-pill--<?= $ptSummary['bad'] > 0 ? 'rose' : 'amber' ?>"><?= (int) ($ptSummary['bad'] + $ptSummary['warn']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
