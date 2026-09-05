<?php
declare(strict_types=1);

$active = (string) ($effectifsNav ?? 'roster');
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$total = (int) ($counts['total'] ?? 0);
$activeCount = (int) ($counts['active'] ?? 0);
$attentionCount = (int) ($counts['no_unit'] ?? 0) + (int) ($counts['no_role'] ?? 0) + (int) ($rhAlertTotalCount ?? 0);
$innerContent = (string) ($effectifsContent ?? 'admin.effectifs_workspace.roster');

$groups = [
    'Pilotage quotidien' => [
        ['roster', 'Vue d’ensemble', '', $total],
        ['affectations', 'Affectations', 'affectations', (int) ($counts['no_unit'] ?? 0)],
        ['qualifications', 'Qualifications', 'qualifications', (int) ($qualificationsExpiringCount ?? 0)],
        ['elevations', 'Élévations', 'elevations', (int) ($elevationOpenCount ?? 0)],
    ],
    'Organisation & accès' => [
        ['roles', 'Rôles', 'roles', 0],
        ['droits', 'Droits d’accès', 'droits', 0],
        ['fonctions', 'Fonctions', 'fonctions', 0],
        ['duplicates', 'Fiches jumelles', 'doublons', (int) ($personnelDuplicateScan['group_count'] ?? 0)],
    ],
    'Parcours RH' => [
        ['rh_documents', 'Documents RH', 'documents-rh', 0],
        ['rh_mobility', 'Mobilité', 'mobilite', (int) ($mobilityPendingCount ?? 0)],
        ['rh_succession', 'Vivier', 'vivier', 0],
        ['rh_alerts', 'Alertes RH', 'alertes', (int) ($rhAlertTotalCount ?? 0)],
        ['departures', 'Anciens membres', 'departs', 0],
    ],
];

$activeLabel = 'Vue d’ensemble';
foreach ($groups as $items) {
    foreach ($items as $item) {
        if ($item[0] === $active) {
            $activeLabel = $item[1];
        }
    }
}
?>
<div class="bo-eff-workspace" data-effectifs-workspace>
    <section class="bo-eff-hero ath-rise" aria-labelledby="bo-eff-title">
        <div class="bo-eff-hero__copy">
            <p class="bo-eff-hero__eyebrow">Personnel · centre de conduite RH</p>
            <h1 id="bo-eff-title">Effectifs</h1>
            <p>Une vue opérationnelle pour traiter les dossiers, anticiper les écarts et garder une organisation immédiatement exploitable.</p>
            <div class="bo-eff-hero__badges" aria-label="État des effectifs">
                <span><strong><?= $activeCount ?></strong> actifs</span>
                <span><strong><?= $total ?></strong> dossiers</span>
                <a href="<?= htmlspecialchars(effectifs_workspace_url('alertes'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $attentionCount > 0 ? 'is-alert' : '' ?>"><strong><?= $attentionCount ?></strong> à traiter</a>
            </div>
        </div>
        <div class="bo-eff-hero__actions">
            <a class="ath-btn ath-btn--solid" href="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>">Ajouter un membre</a>
            <button class="ath-btn" type="button" data-eff-modal-open="eff-quick-actions">Actions rapides</button>
            <a class="ath-btn" href="<?= htmlspecialchars(effectifs_workspace_url('export'), ENT_QUOTES, 'UTF-8') ?>">Exporter</a>
        </div>
    </section>

    <nav class="bo-eff-subnav ath-rise" aria-label="Sections des effectifs">
        <?php foreach ($groups as $groupLabel => $items): ?>
            <div class="bo-eff-subnav__group">
                <span class="bo-eff-subnav__label"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <div class="bo-eff-subnav__links">
                    <?php foreach ($items as [$id, $label, $suffix, $badge]): ?>
                        <a href="<?= htmlspecialchars(effectifs_workspace_url($suffix), ENT_QUOTES, 'UTF-8') ?>" class="<?= $id === $active ? 'is-active' : '' ?>" <?= $id === $active ? 'aria-current="page"' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($badge > 0): ?><span class="bo-eff-badge"><?= (int) $badge ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="bo-eff-context ath-rise">
        <div><span>Section active</span><strong><?= htmlspecialchars($activeLabel, ENT_QUOTES, 'UTF-8') ?></strong></div>
        <p>Les badges signalent les files qui demandent une décision. Les actions sensibles restent confirmées dans une fenêtre dédiée.</p>
    </div>

    <section class="bo-eff-content ath-rise">
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

<dialog class="bo-eff-modal" id="eff-quick-actions" aria-labelledby="eff-quick-actions-title">
    <form method="dialog" class="bo-eff-modal__panel">
        <div class="bo-eff-modal__head">
            <div><span>Raccourcis</span><h2 id="eff-quick-actions-title">Que souhaitez-vous traiter ?</h2></div>
            <button value="cancel" aria-label="Fermer">×</button>
        </div>
        <div class="bo-eff-modal__grid">
            <a href="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>"><strong>Nouveau dossier</strong><span>Créer et préparer un membre</span></a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>"><strong>Sans affectation</strong><span><?= (int) ($counts['no_unit'] ?? 0) ?> dossier(s) à orienter</span></a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations'), ENT_QUOTES, 'UTF-8') ?>"><strong>Décider les élévations</strong><span><?= (int) ($elevationOpenCount ?? 0) ?> demande(s) ouverte(s)</span></a>
            <a href="<?= htmlspecialchars(url('back-office/organisation-effectifs'), ENT_QUOTES, 'UTF-8') ?>"><strong>Structure & grades</strong><span>Ouvrir les référentiels</span></a>
        </div>
        <div class="bo-eff-modal__foot"><button class="ath-btn" value="cancel">Fermer</button></div>
    </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-eff-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = document.getElementById(button.dataset.effModalOpen || '');
            if (modal && typeof modal.showModal === 'function') modal.showModal();
        });
    });
    document.querySelectorAll('.bo-eff-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) modal.close();
        });
    });
});
</script>
