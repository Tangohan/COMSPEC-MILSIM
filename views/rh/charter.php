<?php
declare(strict_types=1);

$doc = is_array($hrCharterDocument ?? null) ? $hrCharterDocument : [];
$accepted = !empty($hrCharterAccepted);
$redirect = isset($hrCharterRedirect) ? (string) $hrCharterRedirect : '';
$csrf = (string) ($hrCharterCsrf ?? '');
$docId = (int) ($doc['id'] ?? 0);
$titleDoc = trim((string) ($doc['title'] ?? ''));
$body = (string) ($doc['body_html'] ?? '');
$baseUrl = url('');
?>
<div class="max-w-3xl mx-auto px-6 py-10">
    <h1 class="text-2xl font-black text-slate-900 tracking-tight mb-2"><?= htmlspecialchars($titleDoc !== '' ? $titleDoc : 'Charte — formations') ?></h1>
    <p class="text-sm text-slate-600 mb-8">Merci de lire le texte ci-dessous. Vous pourrez confirmer votre prise en compte en bas de page.</p>

    <?php if ($accepted): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 px-5 py-4 text-sm text-emerald-900 mb-8">
            Vous avez déjà enregistré votre prise en compte pour cette version. Vous pouvez fermer cette page ou retourner au catalogue des formations.
        </div>
        <p>
            <a href="<?= htmlspecialchars(url('formations')) ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 text-white text-sm font-bold px-5 py-3 hover:bg-slate-800">Retour aux formations</a>
        </p>
    <?php else: ?>
        <div
            id="hr-charter-scroll"
            class="prose prose-slate max-w-none border border-slate-200 rounded-2xl bg-white p-6 md:p-8 max-h-[min(28rem,55vh)] overflow-y-auto mb-6 shadow-sm"
            tabindex="0"
        >
            <?= $body ?>
        </div>

        <form method="post" action="<?= htmlspecialchars(url('account/charte-formations/accept')) ?>" class="space-y-4" id="hr-charter-form">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="document_id" value="<?= $docId ?>">
            <?php if ($redirect !== ''): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="confirm" id="hr-charter-confirm" value="1" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" disabled>
                <span class="text-sm text-slate-700">Je confirme avoir lu et pris connaissance de cette charte.</span>
            </label>
            <p class="text-xs text-slate-500" id="hr-charter-hint" role="status">Faites défiler le texte jusqu’en bas pour activer la case.</p>
            <button type="submit" id="hr-charter-submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 text-white text-sm font-bold px-6 py-3 hover:bg-emerald-700 disabled:opacity-40 disabled:pointer-events-none" disabled>
                Enregistrer ma prise en compte
            </button>
        </form>
        <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/rh-charter.js"></script>
    <?php endif; ?>
</div>
