<?php
declare(strict_types=1);
/** @var string $lmsBase */
/** @var int $totalModules */
/** @var string $activeNav one of overview|catalogue|mine|sessions|qualifications|publications|docs_html */
/** @var bool $lmsSidebarShowPilotageLinks si true (ex. page catalogue), affiche les liens pilotage sous la nav */
$lmsBase = $lmsBase ?? url('');
$totalModules = (int) ($totalModules ?? 0);
$activeNav = $activeNav ?? 'overview';
$navClass = static function (string $id) use ($activeNav): string {
    return $id === $activeNav
        ? 'lms-active-nav flex items-center justify-between rounded-2xl border px-4 py-3 transition-all'
        : 'flex items-center justify-between rounded-2xl border border-white/5 bg-white/[0.02] px-4 py-3 transition-all hover:border-emerald-500/20';
};
?>
<aside class="lms-dark-panel text-white p-6 lg:p-8 flex flex-col">
    <div class="pb-8 border-b border-white/10">
        <p class="text-[9px] font-black tracking-[0.35em] uppercase text-emerald-400 mb-3">Athena / COMSPEC</p>
        <h1 class="text-2xl font-black tracking-tight uppercase leading-none">Commandement formation</h1>
        <p class="text-[11px] text-white/35 font-medium mt-3 leading-relaxed">
            Catalogue, cycles de qualification, sessions planifiées et suivi de disponibilité opérationnelle.
        </p>
    </div>

    <nav class="pt-8 space-y-3">
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations#overview" class="<?= htmlspecialchars($navClass('overview')) ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $activeNav === 'overview' ? 'text-emerald-400' : 'text-white/25' ?>">01</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Vue d’ensemble</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/40">Actif</span>
        </a>
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations#catalogue" class="<?= htmlspecialchars($navClass('catalogue')) ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $activeNav === 'catalogue' ? 'text-emerald-400' : 'text-white/25' ?>">02</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Catalogue</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25"><?= $totalModules ?></span>
        </a>
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations/mes-formations" class="<?= htmlspecialchars($navClass('mine')) ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $activeNav === 'mine' ? 'text-emerald-400' : 'text-white/25' ?>">03</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Mes formations</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Suivi</span>
        </a>
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations#sessions" class="<?= htmlspecialchars($navClass('sessions')) ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $activeNav === 'sessions' ? 'text-emerald-400' : 'text-white/25' ?>">04</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Sessions</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">—</span>
        </a>
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations#qualifications" class="<?= htmlspecialchars($navClass('qualifications')) ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $activeNav === 'qualifications' ? 'text-emerald-400' : 'text-white/25' ?>">05</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Qualifications</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Grille</span>
        </a>

        <a href="<?= htmlspecialchars(training_lms_admin_url('publications')) ?>" class="<?= htmlspecialchars($navClass('publications')) ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $activeNav === 'publications' ? 'text-emerald-400' : 'text-white/25' ?>">06</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Publications</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">BO</span>
        </a>
        <a href="<?= htmlspecialchars(training_lms_admin_url('pages-html')) ?>" class="<?= htmlspecialchars($navClass('docs_html')) ?>">
            <span>
                <span class="block text-[8px] font-black tracking-[0.3em] uppercase <?= $activeNav === 'docs_html' ? 'text-emerald-400' : 'text-white/25' ?>">07</span>
                <span class="block text-[12px] font-bold tracking-[0.14em] uppercase mt-1">Docs HTML</span>
            </span>
            <span class="text-[10px] font-black tracking-widest uppercase text-white/25">Éditer</span>
        </a>
    </nav>

    <?php
    $mode = 'sidebar';
    require base_path('views/training/partials/lms_pilotage_staff_nav.php');
    ?>

    <div class="mt-10 pt-8 border-t border-white/10 space-y-5">
        <div class="rounded-2xl bg-white/[0.03] border border-white/5 p-4">
            <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">Catalogue</p>
            <p class="text-sm font-black uppercase tracking-[0.14em]"><?= $totalModules ?> module<?= $totalModules > 1 ? 's' : '' ?></p>
            <p class="text-[11px] text-white/35 mt-2">Formations et parcours opérationnels disponibles.</p>
        </div>
        <div class="rounded-2xl bg-white/[0.03] border border-white/5 p-4">
            <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/30 mb-2">Accès</p>
            <p class="text-sm font-black uppercase tracking-[0.14em]">Mes formations</p>
            <p class="text-[11px] text-emerald-400 mt-2 font-bold uppercase tracking-[0.14em]">Progression</p>
        </div>
    </div>

    <div class="mt-auto pt-8 border-t border-white/10">
        <p class="text-[8px] font-black tracking-[0.3em] uppercase text-white/20">Version</p>
        <div class="flex items-center justify-between mt-3">
            <span class="text-[10px] font-mono text-white/35 tracking-[0.22em] uppercase">LMS formations</span>
            <span class="text-[10px] font-black uppercase text-emerald-400">Opérationnel</span>
        </div>
    </div>
</aside>
