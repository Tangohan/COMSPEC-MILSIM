<?php

declare(strict_types=1);

$rules = $maintenanceRules ?? [];
$missing = !empty($maintenanceTableMissing);
$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
$canManagePlatform = \App\Core\Gate::getInstance()->allows('admin.system');
$toggleOnCount = count(array_filter($rules, static fn (array $r): bool => (int) ($r['is_enabled'] ?? 0) === 1));
$enforcedNowCount = count(array_filter($rules, static fn (array $r): bool => \App\Support\MaintenanceService::isWithinEnabledSchedule($r)));
?>
<div class="mx-auto max-w-7xl px-6 py-10">
    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">System Ops</p>
            <h1 class="mt-1 text-3xl font-black text-slate-900">Centre de maintenance</h1>
            <p class="mt-2 text-sm text-slate-600">Pilotage global, accès autorisés, communication e-mail, UI maintenance animée.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('admin') ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Admin système</a>
            <?php if ($canManagePlatform && !$missing): ?>
                <a href="<?= url('admin/maintenance/create') ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">Nouvelle maintenance</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Règles</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= count($rules) ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Règles activées</p>
            <p class="mt-1 text-2xl font-black text-emerald-900"><?= $toggleOnCount ?></p>
            <p class="mt-1 text-xs text-emerald-800/80">Bascule « Règle active » cochée.</p>
        </div>
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-sky-800">Blocage public en cours</p>
            <p class="mt-1 text-2xl font-black text-sky-950"><?= $enforcedNowCount ?></p>
            <p class="mt-1 text-xs text-sky-900/80">Selon l’horloge serveur et le créneau début / fin.</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Notifications mail prévues</p>
            <p class="mt-1 text-2xl font-black text-amber-900"><?= count(array_filter($rules, static fn (array $r): bool => (int) ($r['notify_members_by_email'] ?? 0) === 1)) ?></p>
        </div>
    </div>

    <?php if ($s): ?><p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($s) ?></p><?php endif; ?>
    <?php if ($e): ?><p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($e) ?></p><?php endif; ?>

    <?php if ($missing): ?>
        <p class="text-slate-600">Tables maintenance absentes. Lancez la migration du schéma pour activer le module.</p>
    <?php elseif ($rules === []): ?>
        <p class="text-slate-600">Aucune règle enregistrée.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">État réel sur le site</th>
                    <th class="px-4 py-3">Message/UI</th>
                    <th class="px-4 py-3">Accès autorisés</th>
                    <th class="px-4 py-3">Mail membre</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php $compareNow = (new DateTimeImmutable())->format('Y-m-d H:i:s'); ?>
                <?php foreach ($rules as $r): ?>
                    <?php
                    $id = (int) ($r['id'] ?? 0);
                    $enabled = (int) ($r['is_enabled'] ?? 0) === 1;
                    $starts = isset($r['starts_at']) ? trim((string) $r['starts_at']) : '';
                    $ends = isset($r['ends_at']) ? trim((string) $r['ends_at']) : '';
                    if (!$enabled) {
                        $stateLabel = 'Règle désactivée';
                        $stateClass = 'bg-slate-100 text-slate-600';
                    } elseif (\App\Support\MaintenanceService::isWithinEnabledSchedule($r)) {
                        $stateLabel = 'Blocage public en cours';
                        $stateClass = 'bg-sky-100 text-sky-900';
                    } elseif ($starts !== '' && $starts > $compareNow) {
                        $stateLabel = 'Programmée (pas encore appliquée)';
                        $stateClass = 'bg-amber-100 text-amber-900';
                    } elseif ($ends !== '' && $ends < $compareNow) {
                        $stateLabel = 'Créneau terminé';
                        $stateClass = 'bg-slate-100 text-slate-500';
                    } else {
                        $stateLabel = 'En attente';
                        $stateClass = 'bg-slate-100 text-slate-600';
                    }
                    ?>
                    <tr class="align-top hover:bg-slate-50/70">
                        <td class="px-4 py-3">
                            <p class="font-mono text-xs text-slate-700"><?= htmlspecialchars((string) ($r['scope'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-xs text-slate-500">Priorité <?= (int) ($r['priority'] ?? 0) ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold <?= htmlspecialchars($stateClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <p class="mt-1 text-xs text-slate-500">Créneau affiché : <?= htmlspecialchars($starts !== '' ? $starts : '—', ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($ends !== '' ? $ends : '—', ENT_QUOTES, 'UTF-8') ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-xs text-slate-500">preset: <?= htmlspecialchars((string) ($r['message_preset'] ?? '—'), ENT_QUOTES, 'UTF-8') ?> · ui: <?= htmlspecialchars((string) ($r['ui_variant'] ?? 'military'), ENT_QUOTES, 'UTF-8') ?><?= ((int) ($r['ui_animation'] ?? 1)) === 1 ? ' (animé)' : '' ?></p>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            <p><strong>IPs:</strong> <?= htmlspecialchars((string) ($r['allowed_ips'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p><strong>Rôles:</strong> <?= htmlspecialchars((string) ($r['allowed_roles'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p><strong>Users IDs:</strong> <?= htmlspecialchars((string) ($r['allowed_user_ids'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            <?= ((int) ($r['notify_members_by_email'] ?? 0)) === 1 ? '<span class="font-semibold text-amber-700">Prévu</span>' : 'Non' ?>
                            <p class="mt-1 truncate max-w-[20ch]"><?= htmlspecialchars((string) ($r['notify_email_subject'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <?php if ($canManagePlatform): ?>
                                <a href="<?= url('admin/maintenance/' . $id . '/edit') ?>" class="mr-2 text-emerald-700 hover:underline">Modifier</a>
                                <a href="<?= url('admin/maintenance/' . $id . '/audit') ?>" class="mr-2 text-slate-600 hover:underline">Historique</a>
                                <form action="<?= url('admin/maintenance/' . $id . '/notify') ?>" method="post" class="inline mr-2" onsubmit="return confirm('Envoyer l’e-mail à tous les comptes actifs avec une adresse valide (toutes communautés) ? Cette opération peut prendre plusieurs minutes.');"><?= \App\Core\Csrf::field() ?><button type="submit" class="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-bold text-amber-950 hover:bg-amber-100">Notifier</button></form>
                                <form action="<?= url('admin/maintenance/' . $id . '/toggle') ?>" method="post" class="inline"><?= \App\Core\Csrf::field() ?><input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>"><button class="mr-2 text-slate-700 hover:underline" type="submit"><?= $enabled ? 'Désactiver' : 'Activer' ?></button></form>
                                <form action="<?= url('admin/maintenance/' . $id . '/delete') ?>" method="post" class="inline" onsubmit="return confirm('Supprimer cette règle ?');"><?= \App\Core\Csrf::field() ?><button class="text-red-600 hover:underline" type="submit">Supprimer</button></form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Lecture seule</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
