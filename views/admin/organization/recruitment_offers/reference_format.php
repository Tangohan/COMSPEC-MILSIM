<?php
declare(strict_types=1);
/** @var array<string,mixed> $format */
/** @var string $prospectionDocumentRef */
/** @var string $previewReference */
/** @var int $previewYear */
/** @var int $previewSeq */
/** @var int $previewLastSeq */
/** @var string $previewUnitLabel */
/** @var string $previewTenantName */
/** @var bool $previewHasUnits */
$format = is_array($format ?? null) ? $format : [];
$prospectionDocumentRef = $prospectionDocumentRef ?? '';
$previewReference = $previewReference ?? '';
$previewYear = (int) ($previewYear ?? (int) date('Y'));
$previewSeq = (int) ($previewSeq ?? 1);
$previewLastSeq = (int) ($previewLastSeq ?? 0);
$previewUnitLabel = trim((string) ($previewUnitLabel ?? ''));
$previewTenantName = trim((string) ($previewTenantName ?? ''));
$previewHasUnits = (bool) ($previewHasUnits ?? false);
?>
<div class="max-w-2xl w-full space-y-6">
    <div class="lms-panel rounded-[2rem] p-6 md:p-8 border border-slate-200/80 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-sky-500/80 via-sky-500/20 to-transparent" aria-hidden="true"></div>
        <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-sky-700 hover:underline">← Offres publiées</a>
        <h1 class="mt-4 text-2xl md:text-3xl font-black uppercase tracking-tight text-slate-900">Format des références</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed">Ces réglages servent à générer la référence affichée sur chaque avis au moment de la publication.</p>
    </div>

    <form action="<?= htmlspecialchars(url('back-office/recruitment/reference-format'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="lms-panel rounded-[2rem] border border-slate-200/80 p-6 md:p-8 space-y-6">
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
        <div class="rounded-lg bg-slate-50 p-4 border border-slate-100 space-y-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Aperçu (état actuel)</p>
                <p class="font-mono text-base font-semibold text-slate-900 tracking-tight"><?= htmlspecialchars($previewReference, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <ul class="text-xs text-slate-600 space-y-1.5 list-disc list-inside leading-relaxed">
                <li>Communauté : <?= htmlspecialchars($previewTenantName !== '' ? $previewTenantName : '—', ENT_QUOTES, 'UTF-8') ?> — l’identifiant organisationnel du format suit vos réglages (ou le code / le nom court dérivé du portail si le champ est vide).</li>
                <li>Unité servant d’exemple : <?= htmlspecialchars($previewUnitLabel, ENT_QUOTES, 'UTF-8') ?> (première unité dans l’ordre d’affichage administration). La référence réelle utilisera l’unité porteuse choisie sur chaque avis.</li>
                <li>Numéro de fin : <?= (int) $previewSeq ?> pour <?= (int) $previewYear ?> (prochain numéro prévu pour une nouvelle publication cette année<?= $previewLastSeq === 0 ? ' — aucune publication enregistrée sur cette année pour l’instant' : '' ?>).</li>
            </ul>
            <?php if (!$previewHasUnits && !empty($format['include_unit_code'])): ?>
                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">Aucune unité n’est encore créée : le segment « code unité » est reconstruit à partir d’un libellé de secours tant qu’aucune unité réelle n’existe.</p>
            <?php endif; ?>
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-black text-white hover:bg-slate-800">Enregistrer</button>
    </form>
</div>
