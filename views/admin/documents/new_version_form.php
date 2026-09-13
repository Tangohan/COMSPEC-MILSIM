<?php
declare(strict_types=1);

$document = is_array($document ?? null) ? $document : [];
$doctrine = is_array($doctrine ?? null) ? $doctrine : [];
$csrf_token = (string) ($csrf_token ?? '');
$docId = (int) ($document['id'] ?? 0);
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900">Nouvelle version</h1>
    <p class="mt-2 text-sm text-slate-600">
        <?= $h(($doctrine['reference_code'] ?? '') . ' — ' . ($document['title'] ?? '')) ?>
    </p>
    <p class="mt-1 text-sm text-slate-500">Un document publié n’est pas modifié silencieusement : chaque changement important crée une version datée.</p>

    <form id="doc-version-form" method="post" action="<?= url('back-office/documents/' . $docId . '/nouvelle-version') ?>" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf_token) ?>">
        <label class="block text-xs font-bold uppercase text-slate-500">Motif / résumé du changement
            <textarea class="mt-1 w-full rounded-lg border px-3 py-2" name="change_summary" rows="2" required></textarea>
        </label>
        <label class="block text-xs font-bold uppercase text-slate-500">Objet (optionnel)
            <textarea class="mt-1 w-full rounded-lg border px-3 py-2" name="object" rows="2"><?= $h($doctrine['summary'] ?? '') ?></textarea>
        </label>
        <label class="block text-xs font-bold uppercase text-slate-500">Contenu
            <textarea id="doc-body-html" name="body_html" rows="14" class="mt-1 w-full rounded-lg border px-3 py-2"><?= $h($doctrine['body_html'] ?? '') ?></textarea>
        </label>
        <label class="block text-xs font-bold uppercase text-slate-500">Texte de confirmation
            <input class="mt-1 w-full rounded-lg border px-3 py-2" name="confirmation_text" value="<?= $h($doctrine['confirmation_text'] ?? '') ?>">
        </label>
        <fieldset class="space-y-2 text-sm text-slate-700">
            <legend class="text-xs font-bold uppercase text-slate-500">Accusés de lecture</legend>
            <label class="flex items-center gap-2"><input type="radio" name="ack_policy" value="keep"> Conserver les accusés de lecture</label>
            <label class="flex items-center gap-2"><input type="radio" name="ack_policy" value="reset" checked> Exiger une nouvelle lecture</label>
        </fieldset>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="bump_major" value="1"> Incrément majeur (ex. v1.x → v2.0)</label>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Publier la version</button>
    </form>
</div>
<script src="<?= htmlspecialchars(asset_url('assets/js/document_rich_editor.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
