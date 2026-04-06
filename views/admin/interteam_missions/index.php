<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $interteamMissions */
$rows = $interteamMissions ?? [];
$canManage = function_exists('can') && can('interteam.missions.manage');
$gate = \App\Core\Gate::getInstance();
$canManage = $canManage || $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('admin.system');
?>
<div class="max-w-4xl mx-auto px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Missions inter-unités</h1>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Coordination entre communautés : invitations, validation mutuelle, puis partage ciblé de sujets du brief.</p>
        </div>
        <?php if ($canManage): ?>
        <a href="<?= url('admin/interteam-missions/create') ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Nouvelle mission</a>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
    <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-600">
        <p>Aucune mission pour l’instant.</p>
        <?php if ($canManage): ?>
        <a href="<?= url('admin/interteam-missions/create') ?>" class="mt-4 inline-block text-sm font-semibold text-emerald-800 underline">Créer une mission</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <ul class="space-y-3">
        <?php foreach ($rows as $m): ?>
        <li class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-500 mt-1">État : <span class="font-semibold text-slate-700"><?= htmlspecialchars((string) ($m['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></p>
            </div>
            <a href="<?= url('admin/interteam-missions/' . (int) ($m['id'] ?? 0)) ?>" class="text-sm font-semibold text-emerald-800 hover:text-emerald-950">Ouvrir →</a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
