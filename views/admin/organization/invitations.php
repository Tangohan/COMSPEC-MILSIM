<?php
/** @var list<array<string, mixed>> $invitations */
/** @var list<array<string, mixed>> $rolesOrganization */
/** @var list<array<string, mixed>> $inviteUnits */
/** @var list<array{id: int, label: string, name: string}> $inviteJobRoleOptions */
/** @var bool $canAdd */
/** @var string $inviteFilterStatus */
$inviteFilterStatus = $inviteFilterStatus ?? '';
$rolesOrganization = $rolesOrganization ?? [];
$inviteUnits = $inviteUnits ?? [];
$inviteJobRoleOptions = $inviteJobRoleOptions ?? [];

$layerLabel = static function (string $layer): string {
    return match ($layer) {
        'community' => 'Gouvernance & accès (communauté)',
        'intra' => 'Rôles opérationnels / métier (intra)',
        default => $layer,
    };
};

$payloadSummary = static function (?string $raw, array $unitsById, array $jobLabelsById): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return '';
    }
    $parts = [];
    $uid = isset($d['unit_id']) ? (int) $d['unit_id'] : 0;
    if ($uid > 0 && isset($unitsById[$uid])) {
        $lab = isset($d['assignment_label']) ? trim((string) $d['assignment_label']) : '';
        $parts[] = 'Unité : ' . $unitsById[$uid] . ($lab !== '' ? ' (' . $lab . ')' : '');
    }
    $jid = isset($d['personnel_job_role_id']) ? (int) $d['personnel_job_role_id'] : 0;
    if ($jid > 0 && isset($jobLabelsById[$jid])) {
        $parts[] = 'Rôle métier : ' . $jobLabelsById[$jid];
    }

    return $parts !== [] ? implode(' · ', $parts) : '';
};

$unitsById = [];
foreach ($inviteUnits as $u) {
    $unitsById[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? '');
}
$jobLabelsById = [];
foreach ($inviteJobRoleOptions as $jo) {
    $jobLabelsById[(int) ($jo['id'] ?? 0)] = (string) ($jo['label'] ?? $jo['name'] ?? '');
}

$rolesByLayer = ['community' => [], 'intra' => [], 'other' => []];
foreach ($rolesOrganization as $r) {
    $ly = (string) ($r['role_layer'] ?? 'community');
    if ($ly === 'community' || $ly === 'intra') {
        $rolesByLayer[$ly][] = $r;
    } else {
        $rolesByLayer['other'][] = $r;
    }
}
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Invitations</h1>
        <a href="<?= url('back-office') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <?php if (!$canAdd): ?>
        <p class="text-amber-700 text-sm mb-4">Limite de membres atteinte pour ce plan — passez à une offre supérieure pour inviter davantage.</p>
    <?php endif; ?>
    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s) ?></p><?php endif; ?>

    <?php if ($canAdd && empty($rolesOrganization)): ?>
        <p class="text-amber-800 text-sm mb-6 border border-amber-200 rounded-lg p-4">Aucun rôle organisation disponible. Exécutez les migrations ou vérifiez la configuration des rôles.</p>
    <?php endif; ?>

    <?php if ($canAdd && !empty($rolesOrganization)): ?>
    <form method="post" action="<?= url('back-office/invitations') ?>" class="mb-10 border border-slate-200 rounded-xl p-5 space-y-5 bg-white shadow-sm">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
            <input type="email" name="email" required autocomplete="email" class="w-full max-w-lg border border-slate-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Rôle organisation (obligatoire)</label>
            <p class="text-xs text-slate-500 mb-2">Tous les rôles <strong>communauté</strong> et <strong>intra</strong> du tenant sont listés. Les rôles « site » / plateforme globaux ne sont pas assignables ici.</p>
            <select name="role_id" required class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full max-w-2xl">
                <?php foreach (['community', 'intra'] as $ly): ?>
                    <?php if (empty($rolesByLayer[$ly])) continue; ?>
                    <optgroup label="<?= htmlspecialchars($layerLabel($ly)) ?>">
                        <?php foreach ($rolesByLayer[$ly] as $r): ?>
                            <?php
                            $optLabel = trim((string) ($r['name'] ?? ''));
                            if (!empty($r['slug'])) {
                                $optLabel .= ' — ' . (string) $r['slug'];
                            }
                            ?>
                            <option value="<?= (int) ($r['id'] ?? 0) ?>"><?= htmlspecialchars($optLabel) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
                <?php if (!empty($rolesByLayer['other'])): ?>
                    <optgroup label="Autres">
                        <?php foreach ($rolesByLayer['other'] as $r): ?>
                            <option value="<?= (int) ($r['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($r['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
        </div>

        <div class="rounded-lg border border-emerald-200/80 bg-emerald-50/40 p-4 space-y-3">
            <p class="text-xs font-bold text-emerald-950 uppercase tracking-wide">Affectations prévues (optionnel)</p>
            <p class="text-xs text-emerald-900/90">Appliquées automatiquement à l’acceptation de l’invitation (unité ORBAT / dossier personnel).</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Unité (ORBAT)</label>
                    <select name="unit_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="0">— Aucune —</option>
                        <?php foreach ($inviteUnits as $u): ?>
                            <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Libellé d’affectation</label>
                    <input type="text" name="assignment_label" maxlength="120" placeholder="ex. Membre, Opérateur, Officier…" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" value="Membre">
                </div>
            </div>
            <?php if (!empty($inviteJobRoleOptions)): ?>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Rôle métier (dossier personnel)</label>
                <select name="personnel_job_role_id" class="w-full max-w-2xl border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="0">— Aucun —</option>
                    <?php foreach ($inviteJobRoleOptions as $jo): ?>
                        <option value="<?= (int) ($jo['id'] ?? 0) ?>"><?= htmlspecialchars($jo['label'] ?? $jo['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <p class="text-[11px] text-slate-500">Aucun rôle métier configuré — créez-en dans le back-office « Rôles métier &amp; fiches » si besoin.</p>
            <?php endif; ?>
        </div>

        <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Envoyer</button>
    </form>
    <?php endif; ?>

    <form method="get" action="<?= url('back-office/invitations') ?>" class="flex flex-wrap items-end gap-2 mb-4">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Statut</label>
            <select name="status" class="border border-slate-300 rounded px-3 py-2 text-sm" onchange="this.form.submit()">
                <option value="">Tous</option>
                <option value="pending" <?= $inviteFilterStatus === 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="accepted" <?= $inviteFilterStatus === 'accepted' ? 'selected' : '' ?>>Acceptée</option>
                <option value="revoked" <?= $inviteFilterStatus === 'revoked' ? 'selected' : '' ?>>Révoquée</option>
                <option value="expired" <?= $inviteFilterStatus === 'expired' ? 'selected' : '' ?>>Expirée</option>
            </select>
        </div>
        <a href="<?= url('back-office/invitations') ?>" class="text-sm text-slate-600 hover:underline pb-2">Réinitialiser</a>
    </form>

    <ul class="divide-y divide-slate-200 border border-slate-200 rounded-lg">
        <?php foreach ($invitations as $i): ?>
            <?php
            $pay = $payloadSummary($i['invitation_payload'] ?? null, $unitsById, $jobLabelsById);
            ?>
            <li class="px-4 py-3 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 text-sm">
                <div class="min-w-0">
                    <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($i['email'] ?? '')) ?></span>
                    <span class="text-slate-500 ml-2"><?= htmlspecialchars((string) ($i['status'] ?? '')) ?></span>
                    <?php if ($pay !== ''): ?>
                        <p class="text-xs text-emerald-800 mt-1"><?= htmlspecialchars($pay) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (($i['status'] ?? '') === 'pending'): ?>
                <form method="post" action="<?= url('back-office/invitations/revoke') ?>" onsubmit="return confirm('Révoquer ?');" class="shrink-0">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) ($i['id'] ?? 0) ?>">
                    <button type="submit" class="text-red-600 text-xs underline">Révoquer</button>
                </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (empty($invitations)): ?>
            <li class="px-4 py-6 text-slate-500 text-sm">Aucune invitation.</li>
        <?php endif; ?>
    </ul>
</div>
