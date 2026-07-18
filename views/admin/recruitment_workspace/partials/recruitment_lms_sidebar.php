<?php
declare(strict_types=1);
$lmsBase = $lmsBase ?? url('');
$active = (string) ($recruitmentAdminNav ?? '');
if ($active === '') {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_contains($requestUri, '/back-office/recruitments/messages-prefaits')) {
        $active = 'messages';
    } elseif (str_contains($requestUri, '/back-office/recruitments/settings')) {
        $active = 'sla';
    } elseif (str_contains($requestUri, '/back-office/recruitment/reference-format')) {
        $active = 'reference';
    } elseif (str_contains($requestUri, '/back-office/recruitment/offers')) {
        $active = 'offers';
    } elseif (str_contains($requestUri, '/back-office/ressources/recrutement/analyses')) {
        $active = 'analytics';
    } elseif (str_contains($requestUri, '/back-office/ressources/recrutement')) {
        $active = 'dashboard';
    } elseif (str_contains($requestUri, '/back-office/recruitments/equipe')) {
        $active = 'teamwall';
    } elseif (str_contains($requestUri, '/back-office/recruitments')) {
        $active = 'queue';
    }
}
$gateNav = \App\Core\Gate::getInstance();
$canRecOffers = $gateNav->allows('organization.recruitment.openings.manage') || $gateNav->allows('organization.recruitment.manage');
$canStructureHub = $gateNav->allows('organization.orbat.view')
    || $gateNav->allows('organization.orbat.manage')
    || $gateNav->allows('admin.organization')
    || $gateNav->allows('admin.access')
    || $gateNav->allows('site.support');
$rwBase = function_exists('recruitment_workspace_url') ? recruitment_workspace_url() : url('back-office/ressources/recrutement');
$rwAnalyses = function_exists('recruitment_workspace_url') ? recruitment_workspace_url('analyses') : url('back-office/ressources/recrutement/analyses');
$counts = is_array($recruitmentSidebarCounts ?? null) ? $recruitmentSidebarCounts : [];
$nSubmitted = (int) ($counts['submitted'] ?? 0);
$nTotal = $counts !== [] ? array_sum($counts) : 0;
$numSla = $canRecOffers ? '06' : '04';
$numMsg = $canRecOffers ? '07' : '05';
$numStruct = $canRecOffers ? '08' : '06';

$navClass = static function (string $id) use ($active): string {
    return $id === $active
        ? 'lms-active-nav flex items-center justify-between rounded-2xl border px-4 py-3 transition-all'
        : 'flex items-center justify-between rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 transition-all hover:border-sky-500/25';
};
?>
<aside class="lms-dark-panel recruitment-lms-sidebar text-white p-6 lg:p-8 flex flex-col">
    <div class="pb-8 border-b border-white/10">
        <p class="text-[9px] font-black tracking-[0.35em] uppercase text-sky-400 mb-3">Athena / COMSPEC</p>
        <h1 class="text-2xl font-black tracking-tight uppercase leading-none">Bureau recrutement</h1>
        <p class="text-[11px] text-white/35 font-medium mt-3 leading-relaxed">
            Candidatures, décisions, délais et offres publiées — pilotage sur un écran dédié, comme le module formations.
        </p>
    </div>

    <nav class="pt-8 space-y-3" aria-label="Sections recrutement">
        <a href="<?= htmlspecialchars($rwBase, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('dashboard'), ENT_QUOTES, 'UTF-8') ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'dashboard' ? 'text-sky-400' : 'text-white/25' ?>">01</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Vue d’ensemble</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/40">Pilotage</span>
        </a>
        <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('queue'), ENT_QUOTES, 'UTF-8') ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'queue' ? 'text-sky-400' : 'text-white/25' ?>">02</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">File des dossiers</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25"><?= $nSubmitted > 0 ? (string) $nSubmitted : '—' ?></span>
        </a>
        <a href="<?= htmlspecialchars(url('back-office/recruitments/equipe'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('teamwall'), ENT_QUOTES, 'UTF-8') ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'teamwall' ? 'text-sky-400' : 'text-white/25' ?>">—</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Fil recruteurs</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Global</span>
        </a>
        <a href="<?= htmlspecialchars($rwAnalyses, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('analytics'), ENT_QUOTES, 'UTF-8') ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'analytics' ? 'text-sky-400' : 'text-white/25' ?>">03</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Analyses</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">KPI</span>
        </a>
        <?php if ($canRecOffers): ?>
            <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('offers'), ENT_QUOTES, 'UTF-8') ?>">
                <span>
                    <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'offers' ? 'text-sky-400' : 'text-white/25' ?>">04</span>
                    <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Offres</span>
                </span>
                <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Vitrine</span>
            </a>
            <a href="<?= htmlspecialchars(url('back-office/recruitment/reference-format'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('reference'), ENT_QUOTES, 'UTF-8') ?>">
                <span>
                    <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'reference' ? 'text-sky-400' : 'text-white/25' ?>">05</span>
                    <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Références offres</span>
                </span>
                <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Format</span>
            </a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('sla'), ENT_QUOTES, 'UTF-8') ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'sla' ? 'text-sky-400' : 'text-white/25' ?>"><?= htmlspecialchars($numSla, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Délais (SLA)</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Réglages</span>
        </a>
        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits'), ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($navClass('messages'), ENT_QUOTES, 'UTF-8') ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $active === 'messages' ? 'text-sky-400' : 'text-white/25' ?>"><?= htmlspecialchars($numMsg, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Messages préfaits</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Modèles</span>
        </a>
        <?php if ($canStructureHub): ?>
            <a href="<?= htmlspecialchars(url('back-office/organisation/structure'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-between rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 transition-all hover:border-emerald-500/20">
                <span>
                    <span class="block text-[8px] font-black tracking-[0.3em] uppercase text-white/25"><?= htmlspecialchars($numStruct, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Structure</span>
                </span>
                <span class="text-[10px] font-black tracking-widest uppercase text-white/25">ORBAT</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="mt-10 pt-8 border-t border-white/10 space-y-5">
        <div class="rounded-2xl bg-white/[0.03] border border-white/5 p-4">
            <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">File</p>
            <p class="text-sm font-black uppercase tracking-[0.14em]"><?= $nSubmitted ?> à traiter</p>
            <p class="text-[11px] text-white/35 mt-2">Sur <?= $nTotal ?> dossier<?= $nTotal > 1 ? 's' : '' ?> enregistré<?= $nTotal > 1 ? 's' : '' ?>.</p>
        </div>
        <div class="rounded-2xl bg-white/[0.03] border border-white/5 p-4">
            <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">Formation</p>
            <a href="<?= htmlspecialchars(url('formations/parcours-bureau-recrutement'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-black uppercase tracking-[0.14em] text-emerald-300 hover:text-white transition-colors">Bureau recrutement</a>
            <p class="text-[11px] text-white/35 mt-2">Parcours certifiant pour instruire une candidature de bout en bout.</p>
        </div>
        <div class="rounded-2xl bg-white/[0.03] border border-white/5 p-4">
            <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">Portail</p>
            <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-black uppercase tracking-[0.14em] text-sky-300 hover:text-white transition-colors">Retour back-office</a>
            <p class="text-[11px] text-white/35 mt-2">Menu communauté, modules et administration générale.</p>
        </div>
    </div>

    <div class="mt-auto pt-8 border-t border-white/10">
        <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/20">Module</p>
        <div class="flex items-center justify-between mt-3">
            <span class="text-[10px] font-mono text-white/35 tracking-[0.22em] uppercase">Recrutement</span>
            <span class="text-[10px] font-black uppercase text-sky-400">LMS</span>
        </div>
    </div>
</aside>
