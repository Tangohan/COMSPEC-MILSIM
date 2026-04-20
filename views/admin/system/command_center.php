<?php
declare(strict_types=1);

$kpis = is_array($commandCenterKpis ?? null) ? $commandCenterKpis : [];
$actions = is_array($commandCenterActions ?? null) ? $commandCenterActions : [];
$undoQueue = is_array($commandCenterUndoQueue ?? null) ? $commandCenterUndoQueue : [];
$securityEvents = is_array($commandCenterSecurityEvents ?? null) ? $commandCenterSecurityEvents : [];
$recentAudit = is_array($commandCenterRecentAudit ?? null) ? $commandCenterRecentAudit : [];
$filters = is_array($commandCenterFilters ?? null) ? $commandCenterFilters : [];
$page = max(1, (int) ($commandCenterPage ?? 1));
$perPage = max(1, (int) ($commandCenterPerPage ?? 25));
$total = max(0, (int) ($commandCenterTotal ?? 0));
$pages = max(1, (int) ceil($total / $perPage));
?>

<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">Administration centrale</p>
                    <h1 class="mt-2 text-2xl font-black text-slate-900">Centre de commandement admin</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-3xl">Vue consolidée : actions critiques, file d’annulation, incidents sécurité et audit transverse.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?= url('admin') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Dashboard</a>
                    <a href="<?= url('admin/audit') ?>" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Audit global</a>
                </div>
            </div>
        </header>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Signalements forum</p><p class="text-2xl font-black text-slate-900"><?= (int) ($kpis['forum_pending'] ?? 0) ?></p></article>
            <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Actions admin récentes</p><p class="text-2xl font-black text-slate-900"><?= (int) ($kpis['admin_actions_24h'] ?? 0) ?></p></article>
            <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Événements sécurité</p><p class="text-2xl font-black text-slate-900"><?= (int) ($kpis['security_events_recent'] ?? 0) ?></p></article>
            <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Sanctions plateforme</p><p class="text-2xl font-black text-slate-900"><?= (int) ($kpis['sanctions_recent'] ?? 0) ?></p></article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Historique actions administratives</h2>
            <form method="get" action="<?= url('admin/command-center') ?>" class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-2">
                <input type="text" name="action_type" value="<?= htmlspecialchars((string) ($filters['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Type d'action" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="target_type" value="<?= htmlspecialchars((string) ($filters['target_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Type cible" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="number" min="0" name="actor_id" value="<?= (int) ($filters['actor_id'] ?? 0) ?>" placeholder="ID acteur" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Filtrer</button>
            </form>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Action</th><th class="px-3 py-2 text-left">Acteur</th><th class="px-3 py-2 text-left">Cible</th><th class="px-3 py-2 text-left">Statut</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($actions as $row): ?>
                        <tr>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['actor_email'] ?? '#'.$row['actor_user_id']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['target_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?> #<?= htmlspecialchars((string) ($row['target_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500">Page <?= $page ?> / <?= $pages ?> — total <?= $total ?> action(s).</p>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">File d’actions annulables</h2>
                <div class="mt-3 space-y-3">
                    <?php foreach ($undoQueue as $row): ?>
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['action_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — #<?= (int) ($row['id'] ?? 0) ?></p>
                            <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($row['actor_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <form method="post" action="<?= url('admin/undo/' . (int) ($row['id'] ?? 0)) ?>" class="mt-2 flex gap-2">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="text" name="reason" required maxlength="255" placeholder="Motif d'annulation" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                                <button type="submit" class="rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-bold text-white">Annuler</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($undoQueue === []): ?><p class="text-sm text-slate-500">Aucune action annulable disponible.</p><?php endif; ?>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">Événements sécurité récents</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($securityEvents as $event): ?>
                        <li class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($event['event_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($event['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($event['tenant_name'] ?? 'global'), ENT_QUOTES, 'UTF-8') ?></p>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($securityEvents === []): ?><li class="text-slate-500">Aucun événement sécurité récent.</li><?php endif; ?>
                </ul>
            </article>
        </section>
    </div>
</div>
