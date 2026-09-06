<?php
declare(strict_types=1);

$active = (string) ($effectifsNav ?? 'roster');
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$total = (int) ($counts['total'] ?? 0);
$activeCount = (int) ($counts['active'] ?? 0);
$attentionCount = (int) ($counts['no_unit'] ?? 0) + (int) ($counts['no_role'] ?? 0) + (int) ($rhAlertTotalCount ?? 0);
$innerContent = (string) ($effectifsContent ?? 'admin.effectifs_workspace.roster');

$tabs = [
    ['roster', 'Tableur', '', 0],
    ['roles', 'Accès', 'roles', 0],
    ['fonctions', 'Emplois', 'fonctions', 0],
    ['affectations', 'Affectations', 'affectations', (int) ($counts['no_unit'] ?? 0)],
    ['qualifications', 'Qualifications', 'qualifications', (int) ($qualificationsExpiringCount ?? 0)],
    ['elevations', 'Élévations', 'elevations', (int) ($elevationOpenCount ?? 0)],
    ['rh_documents', 'Documents', 'documents-rh', 0],
    ['rh_mobility', 'Mobilité', 'mobilite', (int) ($mobilityPendingCount ?? 0)],
    ['rh_succession', 'Vivier', 'vivier', 0],
    ['rh_alerts', 'Alertes', 'alertes', (int) ($rhAlertTotalCount ?? 0)],
    ['duplicates', 'Fiches jumelles', 'doublons', (int) ($personnelDuplicateScan['group_count'] ?? 0)],
    ['departures', 'Anciens membres', 'departs', 0],
];
?>
<div class="bo-eff-workspace" data-effectifs-workspace>
    <header class="bo-eff-head">
        <div class="bo-eff-head__copy">
            <h1 id="bo-eff-title">Effectifs</h1>
            <p>Dossiers des membres : tableur, accès, emplois et suivi.</p>
            <p class="bo-eff-head__meta" aria-label="État des effectifs">
                <span><strong><?= $activeCount ?></strong> actifs</span>
                <span><strong><?= $total ?></strong> dossiers</span>
                <a href="<?= htmlspecialchars(effectifs_workspace_url('alertes'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $attentionCount > 0 ? 'is-alert' : '' ?>">
                    <strong><?= $attentionCount ?></strong> à traiter
                </a>
            </p>
        </div>
        <div class="bo-eff-head__actions">
            <a class="ath-btn ath-btn--solid" href="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>">Ajouter un membre</a>
            <a class="ath-btn" href="<?= htmlspecialchars(effectifs_workspace_url('export'), ENT_QUOTES, 'UTF-8') ?>">Exporter</a>
        </div>
    </header>

    <nav class="bo-eff-tabs" aria-label="Sections des effectifs">
        <?php foreach ($tabs as [$id, $label, $suffix, $badge]): ?>
            <a href="<?= htmlspecialchars(effectifs_workspace_url($suffix), ENT_QUOTES, 'UTF-8') ?>" class="<?= $id === $active ? 'is-active' : '' ?>" <?= $id === $active ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($badge > 0): ?><span class="bo-eff-badge"><?= (int) $badge ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
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
