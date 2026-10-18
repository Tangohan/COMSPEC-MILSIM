<?php
declare(strict_types=1);

$active = (string) ($effectifsNav ?? 'roster');
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$total = (int) ($counts['total'] ?? 0);
$activeCount = (int) ($counts['active'] ?? 0);
$attentionCount = (int) ($counts['no_unit'] ?? 0) + (int) ($counts['no_role'] ?? 0) + (int) ($rhInboxActionCount ?? $rhAlertTotalCount ?? 0) + (int) ($phaseAutoErrorCount ?? 0);
$innerContent = (string) ($effectifsContent ?? 'admin.effectifs_workspace.roster');

$tabs = [
    ['rh_inbox', 'À traiter', 'a-traiter', (int) ($rhInboxActionCount ?? 0)],
    ['roster', 'Tableur', '', 0],
    ['roles', 'Accès', 'roles', 0],
    ['fonctions', 'Emplois', 'fonctions', 0],
    ['affectations', 'Affectations', 'affectations', (int) ($counts['no_unit'] ?? 0)],
    ['chaine', 'Chaîne', 'chaine', (int) ($commandChainMissingCount ?? 0)],
    ['qualifications', 'Qualifications', 'qualifications', (int) ($qualificationsExpiringCount ?? 0)],
    ['elevations', 'Élévations', 'elevations', (int) ($elevationOpenCount ?? 0)],
    ['rh_documents', 'Documents', 'documents-rh', 0],
    ['rh_mobility', 'Mobilité', 'mobilite', (int) ($mobilityPendingCount ?? 0)],
    ['rh_succession', 'Vivier', 'vivier', 0],
    ['rh_alerts', 'Alertes', 'alertes', (int) ($rhAlertTotalCount ?? 0)],
    ['rh_roleplay', 'Roleplay', 'roleplay', (int) ($roleplayDueCount ?? 0) + (int) ($phaseGateCount ?? 0)],
    ['rh_integration', 'Intégration', 'integration', (int) ($integrationOpenCount ?? 0)],
    ['duplicates', 'Fiches jumelles', 'doublons', (int) ($personnelDuplicateScan['group_count'] ?? 0)],
    ['departures', 'Anciens membres', 'departs', 0],
    ['rh_settings', 'Réglages', 'reglages', 0],
];
// Onglets rangés sous « Plus » : utiles mais moins fréquents. L’onglet actif reste toujours visible.
$moreTabs = ['roles', 'fonctions', 'rh_documents', 'rh_mobility', 'rh_succession', 'rh_roleplay', 'duplicates', 'departures', 'rh_settings'];
$moreBadge = 0;
$moreActive = false;
foreach ($tabs as [$id, , , $badge]) {
    if (in_array($id, $moreTabs, true)) {
        $moreBadge += (int) $badge;
        $moreActive = $moreActive || $id === $active;
    }
}
$isMember = $innerContent === 'admin.effectifs_workspace.member';
$lead = match ($active) {
    'roster' => 'Qui est là, où il est affecté, quel accès il a et ce qu’il reste à compléter.',
    'rh_inbox' => 'Corrections, élévations, échéances et dossiers incomplets : le système propose, vous décidez.',
    default => 'Dossiers des membres : tableur, accès, emplois et suivi.',
};
?>
<div class="bo-eff-workspace bo-eff-workspace--v2" data-effectifs-workspace>
    <header class="bo-eff-head<?= $isMember ? ' bo-eff-head--compact' : '' ?>">
        <div class="bo-eff-head__copy">
            <h1 id="bo-eff-title">Effectifs</h1>
            <?php if (!$isMember): ?><p><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </div>
        <p class="bo-eff-head__meta" aria-label="État des effectifs">
            <span><strong><?= $activeCount ?></strong> actifs</span>
            <span><strong><?= $total ?></strong> dossiers</span>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('a-traiter'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $attentionCount > 0 ? 'is-alert' : '' ?>">
                <strong><?= $attentionCount ?></strong> à traiter
            </a>
        </p>
        <div class="bo-eff-head__actions">
            <a class="ath-btn ath-btn--solid" href="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>">Ajouter un membre</a>
            <a class="ath-btn" href="<?= htmlspecialchars(effectifs_workspace_url('export'), ENT_QUOTES, 'UTF-8') ?>">Exporter</a>
        </div>
    </header>

    <nav class="bo-eff-tabs" aria-label="Sections des effectifs">
        <?php foreach ($tabs as [$id, $label, $suffix, $badge]): ?>
            <?php if (in_array($id, $moreTabs, true) && $id !== $active) {
                continue;
            } ?>
            <a href="<?= htmlspecialchars(effectifs_workspace_url($suffix), ENT_QUOTES, 'UTF-8') ?>" class="<?= $id === $active ? 'is-active' : '' ?>" <?= $id === $active ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($badge > 0): ?><span class="bo-eff-badge"><?= (int) $badge ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <details class="bo-eff-tabs__more">
            <summary>Plus<?php if ($moreBadge > 0 && !$moreActive): ?> <span class="bo-eff-badge"><?= $moreBadge ?></span><?php endif; ?></summary>
            <div class="bo-eff-tabs__menu">
                <?php foreach ($tabs as [$id, $label, $suffix, $badge]): ?>
                    <?php if (!in_array($id, $moreTabs, true)) {
                        continue;
                    } ?>
                    <a href="<?= htmlspecialchars(effectifs_workspace_url($suffix), ENT_QUOTES, 'UTF-8') ?>" class="<?= $id === $active ? 'is-active' : '' ?>">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($badge > 0): ?><span class="bo-eff-badge"><?= (int) $badge ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    </nav>

    <section class="bo-eff-content<?= $innerContent === 'admin.effectifs_workspace.member' ? ' bo-eff-content--member' : '' ?>">
        <?php
        $innerPath = base_path('views/' . str_replace('.', '/', $innerContent) . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="eff-panel"><p>Vue non trouvée.</p></div>';
        }
        ?>
    </section>
</div>
