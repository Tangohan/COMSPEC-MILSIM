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
$actorPrimary = AuditSnapshotPresenter::actorPrimaryLabel($row);
$actorSecondary = AuditSnapshotPresenter::actorSecondaryLabel($row);
$target = AuditSnapshotPresenter::entityTargetLabels($row);
$browser = AuditSnapshotPresenter::browserHint(isset($row['user_agent']) ? (string) $row['user_agent'] : null);
$ipMasked = AuditSnapshotPresenter::maskIpForDisplay(isset($row['ip']) ? (string) $row['ip'] : null);
$canManage = !empty($auditCanManageActions);
$assessment = is_array($auditRollbackAssessment ?? null) ? $auditRollbackAssessment : [
    'can_rollback' => false,
    'reason' => '',
    'summary' => '',
    'restore_fields' => [],
];
$canRollback = $canManage && !empty($assessment['can_rollback']);
$rollbackReason = trim((string) ($assessment['reason'] ?? ''));
$rollbackSummary = trim((string) ($assessment['summary'] ?? ''));
$restoreFields = is_array($assessment['restore_fields'] ?? null) ? $assessment['restore_fields'] : [];

$createdAt = (string) ($row['created_at'] ?? '');
$createdLabel = $createdAt;
if ($createdAt !== '' && ($ts = strtotime($createdAt)) !== false) {
    $createdLabel = date('d/m/Y H:i', $ts);
}
$eventLabel = audit_action_label_fr($act);
$tenantName = trim((string) ($row['tenant_name'] ?? ''));
$emptyCls = 'text-slate-400';
$dash = static function (string $v) use ($emptyCls): string {
    $t = trim($v);
    if ($t === '' || $t === '—') {
        return '<span class="' . $emptyCls . '">—</span>';
    }

    return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
};
?>
<div class="audit-detail bg-slate-50 min-h-0">
    <div class="mx-auto max-w-[1100px] px-4 py-8 sm:px-6 lg:px-8 space-y-8">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Journal d’activité</p>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl"><?= htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-sm text-slate-600">
                    <span class="font-medium text-slate-800"><?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="<?= $emptyCls ?>"> · </span>
                    Réf. <?= $id > 0 ? (int) $id : '—' ?>
                </p>
            </div>
            <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Retour au journal</a>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6" aria-labelledby="audit-who-heading">
            <h2 id="audit-who-heading" class="text-lg font-bold text-slate-900">Contexte</h2>
            <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quand</dt>
                    <dd class="mt-1.5 font-medium text-slate-900"><?= $dash($createdLabel) ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Qui</dt>
                    <dd class="mt-1.5 text-slate-900">
                        <span class="font-medium"><?= $dash($actorPrimary) ?></span>
                        <?php if ($actorSecondary !== ''): ?>
                            <span class="mt-0.5 block text-xs text-slate-500"><?= htmlspecialchars($actorSecondary, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if ($scope === 'system'): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Communauté</dt>
                    <dd class="mt-1.5 font-medium text-slate-900"><?= $dash($tenantName !== '' ? $tenantName : '—') ?></dd>
                </div>
                <?php endif; ?>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Élément concerné</dt>
                    <dd class="mt-1.5 text-slate-900">
                        <span class="font-medium"><?= $dash($target['primary']) ?></span>
                        <?php if ($target['secondary'] !== ''): ?>
                            <span class="mt-0.5 block text-xs text-slate-500"><?= htmlspecialchars($target['secondary'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Origine</dt>
                    <dd class="mt-1.5">
                        <?php if ($ipMasked === '—' && $browser === ''): ?>
                            <span class="<?= $emptyCls ?>">—</span>
                        <?php else: ?>
                            <span class="text-xs tabular-nums text-slate-500"><?= htmlspecialchars($ipMasked, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($browser !== ''): ?>
                                <span class="mt-0.5 block text-xs text-slate-500"><?= htmlspecialchars($browser, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6" aria-labelledby="audit-diff-heading">
            <div class="space-y-1">
                <h2 id="audit-diff-heading" class="text-lg font-bold text-slate-900">Modifications</h2>
                <p class="text-sm text-slate-600">Comparaison de l’état <strong class="font-semibold text-slate-800">avant</strong> et <strong class="font-semibold text-slate-800">après</strong> l’événement.</p>
            </div>
            <?php if ($diffRows === []): ?>
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Aucun détail de changement pour cette entrée (action ponctuelle ou données non versionnées).</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3">Champ</th>
                                <th class="px-4 py-3">Avant</th>
                                <th class="px-4 py-3">Après</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($diffRows as $dr): ?>
                                <?php
                                $before = (string) ($dr['before'] ?? '—');
                                $after = (string) ($dr['after'] ?? '—');
                                $changed = $before !== $after;
                                ?>
                                <tr class="<?= $changed ? 'bg-white' : 'bg-slate-50/60' ?>">
                                    <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($dr['label'] ?? 'Champ'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 whitespace-pre-wrap break-words max-w-md <?= $before === '—' ? $emptyCls : 'text-slate-700' ?>"><?= htmlspecialchars($before, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 whitespace-pre-wrap break-words max-w-md <?= $after === '—' ? $emptyCls : ($changed ? 'font-medium text-emerald-900' : 'text-slate-700') ?>"><?= htmlspecialchars($after, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php
            $rawOld = trim((string) ($row['old_value'] ?? ''));
            $rawNew = trim((string) ($row['new_value'] ?? ''));
            if ($rawOld !== '' || $rawNew !== ''):
            ?>
            <details class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700 select-none">Détail technique (réservé au diagnostic)</summary>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Avant</p>
                        <pre class="max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white p-3 text-[11px] leading-relaxed text-slate-600 whitespace-pre-wrap break-all"><?= htmlspecialchars($rawOld !== '' ? $rawOld : '—', ENT_QUOTES, 'UTF-8') ?></pre>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Après</p>
                        <pre class="max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white p-3 text-[11px] leading-relaxed text-slate-600 whitespace-pre-wrap break-all"><?= htmlspecialchars($rawNew !== '' ? $rawNew : '—', ENT_QUOTES, 'UTF-8') ?></pre>
                    </div>
                </div>
            </details>
            <?php endif; ?>
        </section>

        <?php if ($canManage): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-6" aria-labelledby="audit-actions-heading">
            <div class="space-y-1">
                <h2 id="audit-actions-heading" class="text-lg font-bold text-slate-900">Actions administrateur</h2>
                <p class="text-sm text-slate-600">Restaurer l’état précédent lorsque c’est sûr, ou prévenir les responsables.</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900">Revenir à l’ancienne donnée</h3>
                    <?php if ($canRollback): ?>
                        <?php if ($rollbackSummary !== ''): ?>
                            <p class="text-sm text-slate-700"><?= htmlspecialchars($rollbackSummary, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($restoreFields !== []): ?>
                            <ul class="space-y-1.5 text-sm text-slate-700">
                                <?php foreach ($restoreFields as $rf): ?>
                                    <li>
                                        <span class="font-medium"><?= htmlspecialchars((string) ($rf['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-slate-500"> → </span>
                                        <?= htmlspecialchars((string) ($rf['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <form
                            method="post"
                            action="<?= htmlspecialchars(url('admin/audit/' . $id . '/rollback'), ENT_QUOTES, 'UTF-8') ?>"
                            data-ui-confirm="1"
                            data-ui-confirm-title="Restaurer l’état précédent ?"
                            data-ui-confirm-body="Les valeurs listées ci-dessus seront réappliquées. Cette action est enregistrée dans le journal et les responsables sécurité sont prévenus."
                        >
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                Restaurer l’état précédent
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-sm text-slate-600"><?= htmlspecialchars($rollbackReason !== '' ? $rollbackReason : 'Restauration non disponible pour cet événement.', ENT_QUOTES, 'UTF-8') ?></p>
                        <button type="button" disabled class="inline-flex h-10 cursor-not-allowed items-center rounded-lg bg-slate-200 px-4 text-sm font-semibold text-slate-500">
                            Restaurer l’état précédent
                        </button>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900">Alerter les responsables</h3>
                    <p class="text-sm text-slate-600">Envoie une alerte aux adresses sécurité configurées, avec le contexte de cet événement.</p>
                    <form
                        method="post"
                        action="<?= htmlspecialchars(url('admin/audit/' . $id . '/alert'), ENT_QUOTES, 'UTF-8') ?>"
                        class="space-y-3"
                        data-ui-confirm="1"
                        data-ui-confirm-title="Envoyer une alerte ?"
                        data-ui-confirm-body="Les responsables configurés recevront un message au sujet de cet événement. L’envoi est lui-même consigné dans le journal."
                    >
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div>
                            <label for="audit-alert-note" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Message (optionnel)</label>
                            <textarea id="audit-alert-note" name="alert_note" rows="3" maxlength="500" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-400" placeholder="Précisez le contexte si besoin…"></textarea>
                        </div>
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-950 hover:bg-amber-100">
                            Alerter
                        </button>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
