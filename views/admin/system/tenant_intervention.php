<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Support\Audit\AuditSnapshotPresenter;

$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$tenantId = (int) ($tenant['id'] ?? 0);
$actionLabels = [
    'create' => 'Création',
    'update' => 'Modification',
    'delete' => 'Suppression',
    'rollback' => 'Restauration',
    'request_started' => 'Requête démarrée',
    'request_completed' => 'Requête terminée',
];
$formatDate = static function (mixed $value): string {
    $raw = trim((string) $value);
    $timestamp = $raw !== '' ? strtotime($raw) : false;

    return $timestamp === false ? ($raw !== '' ? $raw : 'Date inconnue') : date('d/m/Y à H:i:s', $timestamp);
};
$filter = trim((string) ($_GET['type'] ?? ''));
?>
<section class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <header class="space-y-1">
        <p class="text-xs font-bold uppercase tracking-widest text-amber-600">Support / gestion des changements</p>
        <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Intervention — <?= $h($tenant['name'] ?? 'Organisation') ?></h1>
        <p class="text-sm text-slate-600">Organisation n°<?= $tenantId ?> · <?= $h(AuditSnapshotPresenter::displayScalar($tenant['subscription_status'] ?? null, 'subscription_status')) ?></p>
    </header>

    <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach (['Organisation' => $tenant['name'] ?? '—', 'Modules actifs' => $tenant['enabled_modules_count'] ?? '—', 'Membres' => $tenant['members_count'] ?? '—', 'Santé de l’organisation' => 'À contrôler'] as $label => $value): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= $h($label) ?></dt>
                <dd class="mt-2 font-bold text-slate-900"><?= $h($value) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>

    <?php if (!$activeIntervention): ?>
        <form method="post" action="<?= $h(url('admin/system/tenants/' . $tenantId . '/intervention/enter')) ?>" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <input type="hidden" name="_csrf_token" value="<?= $h(Csrf::token()) ?>">
            <label class="grid gap-2 text-sm font-bold text-slate-800">Motif
                <select name="reason" class="rounded-lg border border-slate-300 bg-white p-3 font-normal">
                    <?php foreach (['Support', 'Maintenance', 'Correction', 'Incident', 'Audit', 'Autre'] as $reason): ?>
                        <option><?= $h($reason) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="rounded-lg bg-amber-500 p-3 font-bold text-slate-950 hover:bg-amber-400">Entrer dans l’organisation</button>
        </form>
    <?php endif; ?>

    <?php if ($history): ?>
        <nav class="flex flex-wrap gap-2" aria-label="Filtrer le journal">
            <?php foreach (['' => 'Toutes', 'create' => 'Créations', 'update' => 'Modifications', 'delete' => 'Suppressions', 'rollback' => 'Restaurations'] as $key => $label): ?>
                <a href="<?= $key === '' ? '?' : '?type=' . $h($key) ?>" class="rounded-full border px-3 py-1.5 text-sm font-semibold <?= $filter === $key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (($history['errors'] ?? []) === [] && ($history['actions'] ?? []) === []): ?>
            <p class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">Aucun événement ne correspond à ce filtre.</p>
        <?php endif; ?>

        <div class="space-y-3">
            <?php foreach (($history['errors'] ?? []) as $error): ?>
                <article class="rounded-xl border border-red-200 bg-red-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-bold text-red-950">Erreur<?= trim((string) ($error['module'] ?? '')) !== '' ? ' · ' . $h($error['module']) : '' ?></h2>
                        <time class="text-xs font-medium text-red-800"><?= $h($formatDate($error['created_at'] ?? null)) ?></time>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm text-red-900"><?= $h($error['message'] ?? 'Erreur sans détail.') ?></p>
                    <?php if (trim((string) ($error['request_id'] ?? '')) !== ''): ?><p class="mt-2 text-xs text-red-700">Requête <?= $h($error['request_id']) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php foreach (($history['actions'] ?? []) as $action): ?>
                <?php
                $actionType = strtolower(trim((string) ($action['action_type'] ?? '')));
                $diffRows = AuditSnapshotPresenter::diffRows(
                    isset($action['before_state']) ? (string) $action['before_state'] : null,
                    isset($action['after_state']) ? (string) $action['after_state'] : null
                );
                $entityType = AuditSnapshotPresenter::entityTypeLabel((string) ($action['entity_type'] ?? ''));
                $entityId = trim((string) ($action['entity_id'] ?? ''));
                $title = $actionLabels[$actionType] ?? AuditSnapshotPresenter::fieldLabel($actionType !== '' ? $actionType : 'événement');
                ?>
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-slate-950"><?= $h($title) ?></h2>
                            <p class="mt-1 text-sm text-slate-600">
                                <?= $h($entityType) ?><?= $entityId !== '' ? ' · référence ' . $h($entityId) : '' ?>
                            </p>
                        </div>
                        <div class="text-right text-xs leading-5 text-slate-500">
                            <time class="block font-medium text-slate-700"><?= $h($formatDate($action['created_at'] ?? null)) ?></time>
                            <?php if (trim((string) ($action['module'] ?? '')) !== ''): ?><span><?= $h($action['module']) ?></span><?php endif; ?>
                        </div>
                    </header>

                    <?php if ($diffRows === []): ?>
                        <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">Aucune donnée n’a été modifiée par cet événement.</p>
                    <?php else: ?>
                        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-4 py-3">Champ</th><th class="px-4 py-3">Avant</th><th class="px-4 py-3">Après</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($diffRows as $row): ?>
                                        <tr>
                                            <th class="px-4 py-3 font-semibold text-slate-800"><?= $h($row['label']) ?></th>
                                            <td class="max-w-md whitespace-pre-wrap break-words px-4 py-3 text-slate-600"><?= $h($row['before']) ?></td>
                                            <td class="max-w-md whitespace-pre-wrap break-words px-4 py-3 font-medium text-slate-900"><?= $h($row['after']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <footer class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <?php if (trim((string) ($action['request_id'] ?? '')) !== ''): ?><span class="text-xs text-slate-400">Requête <?= $h($action['request_id']) ?></span><?php endif; ?>
                        <?php if (!empty($action['is_reversible']) && ($action['rollback_status'] ?? '') === 'not_requested'): ?>
                            <form method="post" action="<?= $h(url('admin/system/tenants/' . $tenantId . '/intervention/actions/' . (int) $action['id'] . '/rollback')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h(Csrf::token()) ?>">
                                <button class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-900 hover:bg-amber-100">Restaurer cette modification</button>
                            </form>
                        <?php endif; ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
