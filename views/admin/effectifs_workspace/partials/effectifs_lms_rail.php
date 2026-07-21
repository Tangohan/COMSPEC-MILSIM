<?php
declare(strict_types=1);
$active = (string) ($effectifsNav ?? 'roster');
$viewerName = (string) ($viewerName ?? '');
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$nTotal = (int) ($counts['total'] ?? 0);
$nActive = (int) ($counts['active'] ?? 0);
$nNoUnit = (int) ($counts['no_unit'] ?? 0);
$nNoRole = (int) ($counts['no_role'] ?? 0);
$nElevationOpen = (int) ($elevationOpenCount ?? 0);

$navClass = static function (string $id) use ($active): string {
    return 'eff-nav-btn' . ($id === $active ? ' active' : '');
};
?>
<aside class="eff-rail" aria-label="Navigation du bureau effectifs">
    <div class="eff-rail-compact"><span>Bureau effectifs</span></div>
    <div class="eff-rail-panel">
        <?php if ($viewerName !== ''): ?>
        <p class="eff-rail-hello">Bonjour, <?= htmlspecialchars($viewerName, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="eff-rail-hero">
            <div>
                <p class="eff-rail-kicker">Pilotage RH</p>
                <p class="eff-rail-label">Effectifs suivis</p>
            </div>
            <strong class="eff-rail-count"><?= $nTotal ?></strong>
        </div>
        <div class="eff-rail-meter" aria-hidden="true">
            <i style="width:<?= $nTotal > 0 ? (int) round(($nActive / $nTotal) * 100) : 0 ?>%"></i>
        </div>
        <p class="eff-rail-meter-caption"><?= $nActive ?> compte<?= $nActive > 1 ? 's' : '' ?> actif<?= $nActive > 1 ? 's' : '' ?></p>

        <p class="eff-section-label">Pilotage</p>
        <nav class="eff-rail-nav" aria-label="Sections du bureau effectifs">
            <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('roster'), ENT_QUOTES, 'UTF-8') ?>">
                <b>01</b>
                <span>Tableur effectifs<em>Liste complète et actions rapides</em></span>
            </a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('roles'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('roles'), ENT_QUOTES, 'UTF-8') ?>">
                <b>02</b>
                <span>Rôles<em>Profils de gouvernance</em></span>
            </a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('droits'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('droits'), ENT_QUOTES, 'UTF-8') ?>">
                <b>03</b>
                <span>Droits d’accès<em>Habilitations et profils prêts</em></span>
            </a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('fonctions'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('fonctions'), ENT_QUOTES, 'UTF-8') ?>">
                <b>04</b>
                <span>Fonctions<em>Emplois métier des dossiers</em></span>
            </a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('affectations'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('affectations'), ENT_QUOTES, 'UTF-8') ?>">
                <b>05</b>
                <span>Affectations<em>Unités et rattachements</em></span>
            </a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('elevations'), ENT_QUOTES, 'UTF-8') ?>">
                <b>06</b>
                <span>Élévations<?= $nElevationOpen > 0 ? ' <i class="eff-nav-badge">' . $nElevationOpen . '</i>' : '' ?><em>Grade, rôle, droits</em></span>
            </a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('departs'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('departures'), ENT_QUOTES, 'UTF-8') ?>">
                <b>07</b>
                <span>Anciens membres<em>Historique des départs</em></span>
            </a>
        </nav>

        <p class="eff-section-label">Alertes</p>
        <div class="eff-rail-alerts">
            <a href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>" class="eff-alert-chip">
                <strong><?= $nNoUnit ?></strong>
                <span>Sans affectation</span>
            </a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_role=1', ENT_QUOTES, 'UTF-8') ?>" class="eff-alert-chip">
                <strong><?= $nNoRole ?></strong>
                <span>Sans rôle</span>
            </a>
        </div>

        <p class="eff-section-label">Raccourcis</p>
        <div class="eff-rail-tools">
            <a href="<?= htmlspecialchars(url('back-office/organisation-effectifs'), ENT_QUOTES, 'UTF-8') ?>" class="eff-link">
                <b>·</b><span>Hub organisation<em>Grades, structure, ancienneté</em></span>
            </a>
            <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="eff-link">
                <b>·</b><span>Comptes membres<em>Création et édition détaillée</em></span>
            </a>
            <a href="<?= htmlspecialchars(url('orbat'), ENT_QUOTES, 'UTF-8') ?>" class="eff-link">
                <b>·</b><span>ORBAT<em>Vue hiérarchique</em></span>
            </a>
        </div>
    </div>
</aside>
<style>
.eff-nav-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.1rem;
    height: 1.1rem;
    padding: 0 0.3rem;
    margin-left: 0.35rem;
    border-radius: 999px;
    background: #2dd4bf;
    color: #022c22;
    font-size: 0.625rem;
    font-weight: 900;
    font-style: normal;
    vertical-align: middle;
}
</style>
