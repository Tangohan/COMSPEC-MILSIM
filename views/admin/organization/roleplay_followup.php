<?php
$rpFeatureEnabled = !empty($rpFeatureEnabled);
$rpRows = is_array($rpRows ?? null) ? $rpRows : [];
$rpConfig = is_array($rpConfig ?? null) ? $rpConfig : [];
$rpTrackedCount = (int) ($rpTrackedCount ?? 0);
$rpEligibleCount = (int) ($rpEligibleCount ?? 0);
$rpTotalActiveMembers = (int) ($rpTotalActiveMembers ?? count($rpRows));
$rpTimelineTableReady = !empty($rpTimelineTableReady);
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <header class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 p-6 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700">Module roleplay</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Suivi individuel & tutorat</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl">Vue back-office dédiée pour piloter les dossiers roleplay : affectations de tuteur, avancement réel, échéances critiques et statut d’éligibilité.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(url('back-office/configuration'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Configurer le module</a>
            <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Gérer les membres</a>
        </div>
    </header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Statut module</p>
            <p class="mt-2 text-lg font-black <?= $rpFeatureEnabled ? 'text-emerald-700' : 'text-rose-700' ?>"><?= $rpFeatureEnabled ? 'Actif' : 'Désactivé' ?></p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Membres actifs</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= $rpTotalActiveMembers ?></p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Dossiers suivis RP</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= $rpTrackedCount ?></p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Éligibles</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= $rpEligibleCount ?></p>
        </article>
    </section>

    <?php if (!$rpTimelineTableReady): ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        La table timeline RP n’est pas encore disponible. Exécutez les migrations pour activer l’historisation avancée.
    </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Suivi détaillé des membres</h2>
            <p class="mt-1 text-xs text-slate-500">Trié par échéance la plus proche (retards en priorité).</p>
        </div>
        <?php if ($rpRows === []): ?>
        <p class="px-5 py-8 text-sm text-slate-500">Aucun membre actif pour ce tenant.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Membre</th>
                        <th class="px-4 py-3 text-left">Tutorat</th>
                        <th class="px-4 py-3 text-left">Avancement</th>
                        <th class="px-4 py-3 text-left">Filière</th>
                        <th class="px-4 py-3 text-left">Échéance</th>
                        <th class="px-4 py-3 text-left">Éligibilité</th>
                        <th class="px-4 py-3 text-left">Dernier événement</th>
                        <th class="px-4 py-3 text-left">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rpRows as $row):
                        $name = trim((string) ($row['display_name'] ?? ''));
                        if ($name === '') {
                            $name = trim((string) ($row['callsign'] ?? ''));
                        }
                        $nextDue = $row['next_due'] ? date('d/m/Y', strtotime((string) $row['next_due'])) : '—';
                        $latest = is_array($row['latest_timeline'] ?? null) ? $row['latest_timeline'] : null;
                    ?>
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars($name !== '' ? $name : ('Compte #' . (int) $row['user_id'])) ?></p>
                            <p class="mt-1 text-xs text-slate-500">Étape: <span class="font-semibold text-slate-700"><?= htmlspecialchars((string) ($row['stage'] !== '' ? $row['stage'] : '—')) ?></span><?php if (!empty($row['status'])): ?> · <?= htmlspecialchars((string) $row['status']) ?><?php endif; ?></p>
                        </td>
                        <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars((string) ($row['tutor_label'] ?: '—')) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($row['progress'] !== null): ?>
                            <div class="w-36">
                                <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-500" style="width: <?= max(0, min(100, (int) $row['progress'])) ?>%"></div></div>
                                <p class="mt-1 text-xs font-semibold text-slate-700"><?= (int) $row['progress'] ?>%</p>
                            </div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars((string) ($row['track'] !== '' ? $row['track'] : '—')) ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-bold <?= !empty($row['next_due_is_overdue']) ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($nextDue) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-bold <?= !empty($row['eligible']) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><?= !empty($row['eligible']) ? 'Eligible' : 'À compléter' ?></span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            <?php if ($latest): ?>
                            <p class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($latest['title'] ?? '—')) ?></p>
                            <p class="mt-1"><?= htmlspecialchars((string) ($latest['status'] ?? 'planned')) ?><?php if (!empty($latest['event_date'])): ?> · <?= htmlspecialchars(date('d/m/Y', strtotime((string) $latest['event_date']))) ?><?php endif; ?></p>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="<?= htmlspecialchars(url('personnel/' . (int) $row['user_id'] . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">Ouvrir dossier</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
