<?php
declare(strict_types=1);
/** @var array<string,mixed> $format */
/** @var string $prospectionDocumentRef */
/** @var string $previewReference */
$format = is_array($format ?? null) ? $format : [];
$prospectionDocumentRef = $prospectionDocumentRef ?? '';
$previewReference = $previewReference ?? '';
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<div class="max-w-2xl mx-auto px-6 py-10">
    <div class="mb-8">
        <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-sky-700 hover:underline">← Offres publiées</a>
        <h1 class="mt-4 text-2xl font-black text-slate-900">Format des références</h1>
        <p class="mt-1 text-sm text-slate-600">Ces réglages servent à générer la référence affichée sur chaque avis au moment de la publication.</p>
    </div>
    <?php if ($flashOk): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-900"><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <p class="mb-4 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-900"><?= htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form action="<?= htmlspecialchars(url('back-office/recruitment/reference-format'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Référence affichée en tête du tableau des offres (vitrine)</label>
            <input type="text" name="prospection_document_ref" value="<?= htmlspecialchars($prospectionDocumentRef, ENT_QUOTES, 'UTF-8') ?>" placeholder="ex. DRH / Bureau recrutement" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" maxlength="120" />
            <p class="mt-1 text-xs text-slate-500">Texte court, lisible par les visiteurs. Pas d’identifiants techniques.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Séparateur entre segments</label>
            <input type="text" name="separator" value="<?= htmlspecialchars((string) ($format['separator'] ?? '/'), ENT_QUOTES, 'UTF-8') ?>" maxlength="4" class="w-24 rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" />
        </div>
        <div class="space-y-3">
            <label class="flex items-center gap-2 text-sm text-slate-800">
                <input type="hidden" name="include_organization_tag" value="0" />
                <input type="checkbox" name="include_organization_tag" value="1" <?= !empty($format['include_organization_tag']) ? 'checked' : '' ?> class="rounded border-slate-300" />
                Inclure l’identifiant organisationnel
            </label>
            <div class="ml-6">
                <label class="block text-xs font-medium text-slate-600 mb-1">Libellé court organisation (si vide : code communauté ou dérivé du nom)</label>
                <input type="text" name="organization_tag" value="<?= htmlspecialchars((string) ($format['organization_tag'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="32" class="w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-800">
                <input type="hidden" name="include_unit_code" value="0" />
                <input type="checkbox" name="include_unit_code" value="1" <?= !empty($format['include_unit_code']) ? 'checked' : '' ?> class="rounded border-slate-300" />
                Inclure le code de l’unité porteuse
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-800">
                <input type="hidden" name="include_rec_segment" value="0" />
                <input type="checkbox" name="include_rec_segment" value="1" <?= !empty($format['include_rec_segment']) ? 'checked' : '' ?> class="rounded border-slate-300" />
                Inclure le segment « recrutement »
            </label>
            <div class="ml-6">
                <label class="block text-xs font-medium text-slate-600 mb-1">Texte du segment recrutement</label>
                <input type="text" name="rec_segment" value="<?= htmlspecialchars((string) ($format['rec_segment'] ?? 'REC'), ENT_QUOTES, 'UTF-8') ?>" maxlength="16" class="w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono uppercase" />
            </div>
        </div>
        <div class="rounded-lg bg-slate-50 p-4 border border-slate-100">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Exemple (données fictives)</p>
            <p class="font-mono text-sm text-slate-900"><?= htmlspecialchars($previewReference, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-black text-white hover:bg-slate-800">Enregistrer</button>
    </form>
</div>
