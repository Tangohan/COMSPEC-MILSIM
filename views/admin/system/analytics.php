<?php
declare(strict_types=1);
/** @var array{tenants_with_events: int, events_24h: int, top_tenants: list<array{tenant_id: int, name: string, events: int}>} $platformAnalyticsSnapshot */
$snap = $platformAnalyticsSnapshot ?? ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
?>
<div class="max-w-5xl mx-auto px-6 py-10">
    <div class="flex items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Indicateurs transverses</h1>
            <p class="text-sm text-slate-600 mt-1">Agrégats anonymisés (comptages uniquement, 7 derniers jours pour le classement).</p>
        </div>
        <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-500 hover:text-slate-800">Tableau de bord</a>
    </div>

    <dl class="grid sm:grid-cols-2 gap-4 mb-10">
        <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
            <dt class="text-[10px] uppercase tracking-wider text-slate-500">Événements enregistrés (24 h)</dt>
            <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $snap['events_24h'] ?></dd>
        </div>
        <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
            <dt class="text-[10px] uppercase tracking-wider text-slate-500">Communautés avec activité mesurée (30 j.)</dt>
            <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $snap['tenants_with_events'] ?></dd>
        </div>
    </dl>

    <section>
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Communautés les plus actives (7 j.)</h2>
        <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <th class="px-4 py-3 font-bold">Communauté</th>
                        <th class="px-4 py-3 font-bold text-right">Événements</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($snap['top_tenants'] ?? []) === []): ?>
                    <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">Aucune donnée agrégée pour cette fenêtre.</td></tr>
                <?php else: ?>
                    <?php foreach ($snap['top_tenants'] as $t): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($t['events'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
