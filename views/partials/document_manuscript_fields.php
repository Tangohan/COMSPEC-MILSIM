<?php
use App\Support\DocumentManuscript;

$manuscript = $manuscript ?? DocumentManuscript::defaults((string) ($document['title'] ?? ''), (string) ($issuingAuthorityDefault ?? ''));
$signatures = $manuscript['signatures'] ?? [];
if (!is_array($signatures) || $signatures === []) {
    $signatures = DocumentManuscript::defaults()['signatures'];
}
$codesText = DocumentManuscript::codesAsText($manuscript);
$bodyText = DocumentManuscript::bodyAsPlainText($manuscript);
?>
<div class="space-y-4">
    <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="manuscript_codes">Numéros de publication</label>
        <textarea name="manuscript_codes" id="manuscript_codes" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" placeholder="Un numéro par ligne"><?= htmlspecialchars($codesText) ?></textarea>
        <p class="mt-1 text-[11px] text-slate-500">Ils s’affichent en haut à gauche de la page de garde.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="manuscript_issue_date">Date de publication</label>
            <input type="text" name="manuscript_issue_date" id="manuscript_issue_date" value="<?= htmlspecialchars((string) ($manuscript['issue_date'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" placeholder="APRIL 2026" />
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="manuscript_issuing_authority">Autorité émettrice</label>
            <input type="text" name="manuscript_issuing_authority" id="manuscript_issuing_authority" value="<?= htmlspecialchars((string) ($manuscript['issuing_authority'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" />
        </div>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="manuscript_distribution">Restriction de diffusion</label>
        <textarea name="manuscript_distribution" id="manuscript_distribution" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><?= htmlspecialchars((string) ($manuscript['distribution_restriction'] ?? '')) ?></textarea>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="manuscript_destruction">Mention de destruction</label>
        <textarea name="manuscript_destruction" id="manuscript_destruction" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><?= htmlspecialchars((string) ($manuscript['destruction_notice'] ?? '')) ?></textarea>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="manuscript_foreword">Avant-propos</label>
        <textarea name="manuscript_foreword" id="manuscript_foreword" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><?= htmlspecialchars((string) ($manuscript['foreword'] ?? '')) ?></textarea>
    </div>
    <div>
        <div class="mb-2 flex items-center justify-between gap-2">
            <label class="text-xs font-bold uppercase tracking-wide text-slate-600">Signataires</label>
            <button type="button" id="fm-add-signature" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:border-emerald-400">Ajouter un signataire</button>
        </div>
        <p class="mb-2 text-[11px] text-slate-500">Nom, grade ou fonction, puis commandement. Jusqu’à huit blocs, présentés deux par deux.</p>
        <div id="fm-signature-list" class="space-y-2">
            <?php foreach ($signatures as $sig): ?>
            <div class="fm-sig-row grid gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 sm:grid-cols-3">
                <input type="text" name="manuscript_sig_name[]" value="<?= htmlspecialchars((string) ($sig['name'] ?? '')) ?>" placeholder="Nom" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                <input type="text" name="manuscript_sig_rank[]" value="<?= htmlspecialchars((string) ($sig['rank'] ?? '')) ?>" placeholder="Grade ou fonction" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                <input type="text" name="manuscript_sig_command[]" value="<?= htmlspecialchars((string) ($sig['command'] ?? '')) ?>" placeholder="Commandement" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-600" for="manuscript_body">Corps du document</label>
        <textarea name="manuscript_body" id="manuscript_body" rows="10" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm leading-relaxed"><?= htmlspecialchars($bodyText) ?></textarea>
        <p class="mt-1 text-[11px] text-slate-500">Un paragraphe par saut de ligne double. Ce texte suit la page des signatures.</p>
    </div>
</div>
