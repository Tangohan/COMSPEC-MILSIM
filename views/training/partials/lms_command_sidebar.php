<?php
declare(strict_types=1);
/** @var string $lmsBase */
/** @var int $totalModules */
/** @var string $activeNav one of overview|catalogue|mine|sessions|qualifications|publications|docs_html|staff_hub */
/** @var bool $lmsSidebarShowPilotageLinks si true (ex. page catalogue), affiche les liens pilotage sous la nav */
/** @var string $lmsSidebarContext 'catalogue' | 'staff' — staff : accent sur le pilotage encadrement */
$lmsBase = $lmsBase ?? url('');
$totalModules = (int) ($totalModules ?? 0);
$activeNav = $activeNav ?? 'overview';
$lmsSidebarContext = (string) ($lmsSidebarContext ?? 'catalogue');
$isStaffSidebar = $lmsSidebarContext === 'staff';
$trainingAdminNav = (string) ($trainingAdminNav ?? '');
$staffTopActive = '';
if ($isStaffSidebar) {
    $staffTopActive = match (true) {
        $trainingAdminNav === 'studio' => 'studio',
        $trainingAdminNav === 'enrollments' => 'enrollments',
        $trainingAdminNav === 'dashboard' || $trainingAdminNav === '' => 'staff_hub',
        default => '',
    };
}

$tileClass = static function (string $id) use ($activeNav, $isStaffSidebar, $staffTopActive): string {
    $on = $isStaffSidebar ? ($staffTopActive !== '' && $id === $staffTopActive) : ($id === $activeNav);

    return 'lms-cmd-tile' . ($on ? ' is-active' : '');
};

$renderTile = static function (
    string $href,
    string $idx,
    string $label,
    string $hint,
    string $meta,
    string $class
): void {
    ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>">
        <span class="lms-cmd-tile__idx" aria-hidden="true"><?= htmlspecialchars($idx, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="lms-cmd-tile__body">
            <strong class="lms-cmd-tile__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($hint !== ''): ?>
            <em class="lms-cmd-tile__hint"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></em>
            <?php endif; ?>
        </span>
        <?php if ($meta !== ''): ?>
        <span class="lms-cmd-tile__meta"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </a>
    <?php
};

$sidebarTitle = $isStaffSidebar ? 'Pilotage formation' : 'Commandement formation';
$sidebarLead = $isStaffSidebar
    ? 'Contenus, inscriptions, attestations et compétences — navigation d’encadrement.'
    : 'Catalogue, cycles de qualification, sessions planifiées et suivi de disponibilité opérationnelle.';
$modulesMeta = $totalModules > 0 ? (string) $totalModules : '—';
?>
<aside class="lms-dark-panel lms-cmd-aside text-white p-6 lg:p-8 flex flex-col">
    <div class="lms-cmd-aside__brand pb-8 border-b border-white/10">
        <p class="text-[9px] font-black tracking-[0.35em] uppercase text-emerald-400 mb-3">Athena / COMSPEC</p>
        <h1 class="text-2xl font-black tracking-tight uppercase leading-none"><?= htmlspecialchars($sidebarTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-[11px] text-white/35 font-medium mt-3 leading-relaxed">
            <?= htmlspecialchars($sidebarLead, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <nav class="lms-cmd-aside__nav pt-8 space-y-2.5" aria-label="<?= $isStaffSidebar ? 'Navigation pilotage' : 'Navigation catalogue' ?>">
        <?php if ($isStaffSidebar): ?>
        <?php
        $renderTile(training_lms_admin_url(), '01', 'Vue d’ensemble', 'Synthèse du pilotage', 'Synthèse', $tileClass('staff_hub'));
        $renderTile($lmsBase . '/formations', '02', 'Catalogue public', 'Parcours visibles aux membres', $modulesMeta, 'lms-cmd-tile');
        $renderTile(training_studio_url(), '03', 'Studio', 'Créer et éditer les parcours', 'Créer', $tileClass('studio'));
        $renderTile(training_lms_admin_url('enrollments'), '04', 'Inscriptions', 'Suivi des apprenants', 'Suivi', $tileClass('enrollments'));
        ?>
        <?php else: ?>
        <?php
        $renderTile($lmsBase . '/formations#overview', '01', 'Vue d’ensemble', 'Repère et continuité opérationnelle', 'Actif', $tileClass('overview'));
        $renderTile($lmsBase . '/formations#catalogue', '02', 'Catalogue', 'Parcours disponibles à l’inscription', $modulesMeta, $tileClass('catalogue'));
        $renderTile($lmsBase . '/formations/mes-formations', '03', 'Mes formations', 'Inscriptions et progression', 'Suivi', $tileClass('mine'));
        $renderTile($lmsBase . '/formations/sessions', '04', 'Sessions', 'Rendez-vous et cycles planifiés', '—', $tileClass('sessions'));
        $renderTile($lmsBase . '/formations/sessions#qualifications', '05', 'Qualifications', 'Préparation et attestations', 'Grille', $tileClass('qualifications'));
        $renderTile(training_lms_admin_url('publications'), '06', 'Publications', 'Annonces et contenus diffusés', 'Éditer', $tileClass('publications'));
        $renderTile(training_lms_admin_url('pages-html'), '07', 'Pages pédagogiques', 'Supports et pages de parcours', 'Éditer', $tileClass('docs_html'));
        ?>
        <?php endif; ?>
    </nav>

    <?php
    $mode = 'sidebar';
    require base_path('views/training/partials/lms_pilotage_staff_nav.php');
    ?>

    <div class="lms-cmd-aside__stats mt-10 pt-8 border-t border-white/10 space-y-4">
        <div class="lms-cmd-aside__stat">
            <p class="lms-cmd-aside__stat-kicker">Catalogue</p>
            <p class="lms-cmd-aside__stat-value"><?= $totalModules ?> module<?= $totalModules > 1 ? 's' : '' ?></p>
            <p class="lms-cmd-aside__stat-text">Formations et parcours opérationnels disponibles.</p>
        </div>
        <?php if (!$isStaffSidebar): ?>
        <div class="lms-cmd-aside__stat">
            <p class="lms-cmd-aside__stat-kicker">Accès</p>
            <p class="lms-cmd-aside__stat-value">Mes formations</p>
            <p class="lms-cmd-aside__stat-accent">Progression</p>
        </div>
        <?php else: ?>
        <div class="lms-cmd-aside__stat">
            <p class="lms-cmd-aside__stat-kicker">Public</p>
            <a href="<?= htmlspecialchars($lmsBase . '/formations', ENT_QUOTES, 'UTF-8') ?>" class="lms-cmd-aside__stat-link">Voir le catalogue</a>
            <p class="lms-cmd-aside__stat-accent">Ouvert aux membres</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="lms-cmd-aside__foot mt-auto pt-8 border-t border-white/10 space-y-4">
        <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="lms-cmd-aside__dash">
            <span class="lms-cmd-aside__dash-copy">
                <span class="lms-cmd-aside__dash-label">Tableau de bord</span>
                <span class="lms-cmd-aside__dash-sub">Retour Athena</span>
            </span>
            <span class="lms-cmd-aside__dash-hint" aria-hidden="true">←</span>
        </a>
        <div class="lms-cmd-aside__status">
            <p class="lms-cmd-aside__status-kicker">Espace</p>
            <div class="lms-cmd-aside__status-row">
                <span class="lms-cmd-aside__status-name">Formations</span>
                <span class="lms-cmd-aside__status-live">Opérationnel</span>
            </div>
        </div>
    </div>
</aside>
