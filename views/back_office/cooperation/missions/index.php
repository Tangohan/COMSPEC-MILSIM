<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $interteamMissions */
$rows = $interteamMissions ?? [];
$kpis = $cooperationKpis ?? [];
$actions = $cooperationActionsRequired ?? [];
$canManage = function_exists('can') && (can('interteam.missions.manage') || can('cooperation.missions.manage') || can('cooperation.missions.create'));
$gate = \App\Core\Gate::getInstance();
$canManage = $canManage || $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('admin.system');
?>
<div class="max-w-4xl mx-auto px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Coopérations inter-unités</h1>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Propositions conjointes, validation mutuelle, espace d’échange sur le brief et coordination opérationnelle.</p>
        </div>
        <?php if ($canManage): ?>
        <a href="<?= htmlspecialchars(cooperation_mission_create_url(), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Nouvelle coopération</a>
        <?php endif; ?>
    </div>

    <?php if ($kpis !== []): ?>
    <section class="mb-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">En cours</p>
            <p class="mt-1 text-2xl font-black text-emerald-800"><?= (int) ($kpis['active'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Propositions</p>
            <p class="mt-1 text-2xl font-black text-amber-800"><?= (int) ($kpis['pending'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Brouillons</p>
            <p class="mt-1 text-2xl font-black text-slate-800"><?= (int) ($kpis['draft'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Clôturées</p>
            <p class="mt-1 text-2xl font-black text-slate-600"><?= (int) ($kpis['archived'] ?? 0) ?></p>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($actions !== []): ?>
    <section class="mb-8 rounded-xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-wider text-amber-950">Actions attendues</h2>
        <ul class="mt-3 space-y-2 text-sm text-amber-950">
            <?php foreach ($actions as $a): ?>
            <li class="flex flex-wrap items-baseline justify-between gap-2">
                <span><?= htmlspecialchars((string) ($a['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <strong><?= htmlspecialchars((string) ($a['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></span>
                <a href="<?= htmlspecialchars(cooperation_mission_show_url((int) ($a['mission_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-amber-900 underline shrink-0">Ouvrir</a>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
    <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-600">
        <p>Aucune coopération pour l’instant.</p>
        <?php if ($canManage): ?>
        <a href="<?= htmlspecialchars(cooperation_mission_create_url(), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-block text-sm font-semibold text-emerald-800 underline">En proposer une</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($rows as $m): ?>
        <li class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-500 mt-1">État : <span class="font-semibold text-slate-700"><?= htmlspecialchars(cooperation_mission_display_label($m), ENT_QUOTES, 'UTF-8') ?></span></p>
            </div>
            <a href="<?= htmlspecialchars(cooperation_mission_show_url((int) ($m['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-emerald-800 hover:text-emerald-950">Ouvrir →</a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
