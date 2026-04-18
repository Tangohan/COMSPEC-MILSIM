<?php
declare(strict_types=1);
/** @var string $recruitmentAdminNav */
$active = $recruitmentAdminNav ?? '';
$is = static fn (string $k): string => $active === $k ? ' is-active' : '';
$gateNav = \App\Core\Gate::getInstance();
$canRecOffers = $gateNav->allows('organization.recruitment.openings.manage') || $gateNav->allows('organization.recruitment.manage');
$canStructureHub = $gateNav->allows('organization.orbat.view')
    || $gateNav->allows('organization.orbat.manage')
    || $gateNav->allows('admin.organization')
    || $gateNav->allows('admin.access')
    || $gateNav->allows('site.support');
$rwBase = function_exists('recruitment_workspace_url') ? recruitment_workspace_url() : url('back-office/ressources/recrutement');
$rwAnalyses = function_exists('recruitment_workspace_url') ? recruitment_workspace_url('analyses') : url('back-office/ressources/recrutement/analyses');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/recruitment_admin_command.css'), ENT_QUOTES, 'UTF-8') ?>">
<div class="recruitment-cmd relative overflow-hidden rounded-2xl border border-stone-300/90 shadow-md shadow-[#1c2d41]/[0.06] mb-10">
    <div class="recruitment-cmd__grain" aria-hidden="true"></div>
    <div class="recruitment-cmd-shell relative z-[1]">
        <div class="recruitment-cmd-toolbar-wrap">
            <p class="recruitment-cmd-toolbar-eyebrow">Bureau recrutement</p>
            <nav class="recruitment-cmd-toolbar" aria-label="Sections bureau recrutement">
                <a href="<?= htmlspecialchars($rwBase, ENT_QUOTES, 'UTF-8') ?>" class="<?= trim($is('dashboard')) ?>">Vue d’ensemble</a>
                <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="<?= trim($is('queue')) ?>">File des dossiers</a>
                <a href="<?= htmlspecialchars($rwAnalyses, ENT_QUOTES, 'UTF-8') ?>" class="<?= trim($is('analytics')) ?>">Analyses</a>
                <?php if ($canRecOffers): ?>
                    <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="<?= trim($is('offers')) ?>">Offres</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="<?= trim($is('sla')) ?>">Délais</a>
                <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits'), ENT_QUOTES, 'UTF-8') ?>" class="<?= trim($is('messages')) ?>">Messages préfaits</a>
                <?php if ($canStructureHub): ?>
                    <a href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>" class="recruitment-cmd-toolbar__ext">Structure &amp; recrutement</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="recruitment-cmd-main min-w-0">
            <div class="recruitment-cmd-main-inner space-y-8">
