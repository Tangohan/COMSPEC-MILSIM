<?php
declare(strict_types=1);
use App\Support\Audit\AuditSnapshotPresenter;

$row = is_array($auditDetailRow ?? null) ? $auditDetailRow : [];
$scope = (string) ($auditScope ?? 'system');
$backUrl = $scope === 'organization' ? url('back-office/audit') : url('admin/audit');
$id = (int) ($row['id'] ?? 0);
$act = (string) ($row['action'] ?? '');
$diffRows = AuditSnapshotPresenter::diffRows(
    isset($row['old_value']) ? (string) $row['old_value'] : null,
    isset($row['new_value']) ? (string) $row['new_value'] : null
);
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Détail de l’événement</h1>
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour au journal</a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
        <dl class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date</dt>
                <dd class="mt-1 text-slate-900"><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Référence</dt>
                <dd class="mt-1 font-mono text-slate-800"><?= $id ?></dd>
            </div>
            <?php if ($scope === 'system'): ?>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Communauté</dt>
                <dd class="mt-1 text-slate-900"><?= htmlspecialchars((string) ($row['tenant_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Compte acteur</dt>
                <dd class="mt-1 text-slate-900"><?= htmlspecialchars((string) ($row['actor_email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Action</dt>
                <dd class="mt-1 text-slate-900 font-medium"><?= htmlspecialchars(audit_action_label_fr($act), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cible</dt>
                <dd class="mt-1 text-slate-700"><?= htmlspecialchars(trim((string) ($row['entity_type'] ?? '') . ' #' . (string) ($row['entity_id'] ?? '')), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Réseau (extrait)</dt>
                <dd class="mt-1 text-slate-800"><?= htmlspecialchars(AuditSnapshotPresenter::maskIpForDisplay(isset($row['ip']) ? (string) $row['ip'] : null), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Navigateur (extrait)</dt>
                <dd class="mt-1 text-xs text-slate-600 break-all"><?php
                    $ua = isset($row['user_agent']) ? (string) $row['user_agent'] : '';
                    echo htmlspecialchars($ua === '' ? '—' : (strlen($ua) > 120 ? substr($ua, 0, 117) . '…' : $ua), ENT_QUOTES, 'UTF-8');
                ?></dd>
            </div>
        </dl>
    </div>

    <section class="mt-8" aria-labelledby="audit-diff-heading">
        <h2 id="audit-diff-heading" class="text-lg font-bold text-slate-900 mb-3">État avant / après</h2>
        <?php if ($diffRows === []): ?>
            <p class="text-sm text-slate-600 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">Aucun détail structuré pour cette entrée (action ponctuelle ou données non versionnées).</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">Champ</th>
                            <th class="px-3 py-2">Avant</th>
                            <th class="px-3 py-2">Après</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($diffRows as $dr): ?>
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-900"><?= htmlspecialchars($dr['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-slate-700 whitespace-pre-wrap break-words max-w-md"><?= htmlspecialchars($dr['before'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-slate-700 whitespace-pre-wrap break-words max-w-md"><?= htmlspecialchars($dr['after'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
