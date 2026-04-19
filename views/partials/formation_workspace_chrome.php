<?php
declare(strict_types=1);
$formationHubUrl = function_exists('training_lms_admin_url') ? training_lms_admin_url() : url('formation');
$portalDashboardUrl = url('dashboard');
$backOfficeUrl = url('back-office');
?>
<div class="formation-ws-chrome sticky top-0 z-[95] border-b border-slate-200/90 bg-white/95 shadow-sm backdrop-blur-sm">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm">
        <a href="<?= htmlspecialchars($formationHubUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-black uppercase tracking-[0.2em] text-emerald-700 hover:text-emerald-900">Pilotage des formations</a>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold text-slate-600">
            <a href="<?= htmlspecialchars($portalDashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="transition hover:text-emerald-800">Tableau de bord</a>
            <a href="<?= htmlspecialchars($backOfficeUrl, ENT_QUOTES, 'UTF-8') ?>" class="transition hover:text-emerald-800">Administration communauté</a>
        </div>
    </div>
</div>
