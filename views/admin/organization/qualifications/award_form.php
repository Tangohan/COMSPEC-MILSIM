<?php
$definitions = $definitions ?? [];
$issuers = $issuers ?? [];
$levels = $levels ?? [];
$customFields = $customFields ?? [];
$userId = (int) ($userId ?? 0);
$definitionId = (int) ($definitionId ?? 0);
$renewalOf = (int) ($renewalOf ?? 0);
$prefill = $prefill ?? null;
$adminStatuses = $adminStatuses ?? [];
$visibilityLevels = $visibilityLevels ?? [];
$suggestedExpires = $suggestedExpires ?? null;
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <a href="<?= url('back-office/referentiels/qualifications') ?>" class="text-sm text-slate-500 hover:text-slate-800">← Référentiel</a>
    <h1 class="text-2xl font-black text-slate-900 mt-2 mb-6"><?= $renewalOf > 0 ? 'Renouveler une qualification' : 'Attribuer une qualification' ?></h1>
    <?php if ($flashError): ?><p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

    <form method="post" action="<?= url('back-office/referentiels/qualifications/attribuer') ?>" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
        <?= \App\Core\Csrf::field() ?>
        <?php if ($renewalOf > 0): ?>
            <input type="hidden" name="renewal_of_id" value="<?= $renewalOf ?>">
        <?php endif; ?>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Identifiant du membre</label>
            <input type="number" name="user_id" required value="<?= $userId > 0 ? $userId : '' ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            <p class="text-xs text-slate-500 mt-1">Identifiant interne du personnel (depuis la fiche Effectifs).</p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Qualification</label>
            <select name="definition_id" required class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="">Choisir…</option>
                <?php foreach ($definitions as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $definitionId === (int) $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $d['name']) ?> (<?= htmlspecialchars((string) $d['code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($levels !== []): ?>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Niveau</label>
            <select name="qualification_level_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($levels as $lvl): ?>
                    <option value="<?= (int) $lvl['id'] ?>"><?= htmlspecialchars((string) $lvl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Organisme émetteur</label>
            <select name="issuer_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($issuers as $i): ?>
                    <option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Statut initial</label>
                <select name="admin_status" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <?php foreach ($adminStatuses as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= $st === 'obtained' ? 'selected' : '' ?>><?= htmlspecialchars(\App\Support\QualificationAdminStatus::label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Visibilité</label>
                <select name="visibility_level" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                    <?php foreach ($visibilityLevels as $vl): ?>
                        <option value="<?= htmlspecialchars($vl) ?>"><?= htmlspecialchars(\App\Support\VisibilityLevel::label($vl)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Date d’obtention</label>
                <input type="date" name="obtained_at" value="<?= htmlspecialchars((string) ($prefill['obtained_at'] ?? date('Y-m-d'))) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Date d’expiration</label>
                <input type="date" name="expires_at" value="<?= htmlspecialchars((string) ($suggestedExpires ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">N° de brevet (optionnel)</label>
                <input name="certificate_number" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="Généré automatiquement si vide">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Référence</label>
                <input name="reference" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
            <textarea name="notes" rows="3" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"></textarea>
        </div>
        <?php if ($customFields !== []): ?>
        <div class="space-y-3 border-t border-slate-100 pt-4">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">Champs spécifiques</h2>
            <?php foreach ($customFields as $cf): ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1"><?= htmlspecialchars((string) $cf['name']) ?><?= !empty($cf['is_required']) ? ' *' : '' ?></label>
                    <?php if (($cf['field_type'] ?? '') === 'text_long'): ?>
                        <textarea name="custom_values[<?= (int) $cf['id'] ?>]" rows="2" class="w-full border border-slate-300 rounded px-3 py-2 text-sm"><?= htmlspecialchars((string) ($cf['default_value'] ?? '')) ?></textarea>
                    <?php elseif (($cf['field_type'] ?? '') === 'boolean'): ?>
                        <select name="custom_values[<?= (int) $cf['id'] ?>]" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    <?php else: ?>
                        <input name="custom_values[<?= (int) $cf['id'] ?>]" value="<?= htmlspecialchars((string) ($cf['default_value'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" <?= ($cf['field_type'] ?? '') === 'date' ? 'type="date"' : (($cf['field_type'] ?? '') === 'number' ? 'type="number"' : 'type="text"') ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_primary" value="1"> Qualification principale</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_retrospective" value="1"> Saisie rétroactive (sans notification)</label>
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer l’attribution</button>
        </div>
    </form>
</div>
