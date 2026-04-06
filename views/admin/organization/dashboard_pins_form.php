<?php
declare(strict_types=1);
/** @var ?array<string, mixed> $pin */
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $documents */
/** @var list<array<string, mixed>> $courrierDocs */
/** @var string $formAction */
$pin = $pin ?? null;
$categories = $categories ?? [];
$documents = $documents ?? [];
$courrierDocs = $courrierDocs ?? [];
$isEdit = $pin !== null;
$type = $isEdit ? (string) ($pin['pin_type'] ?? 'document') : 'document_category';
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900"><?= $isEdit ? 'Modifier un raccourci' : 'Ajouter un raccourci' ?></h1>
        <a href="<?= url('back-office/dashboard-pins') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>

    <?php $f = \App\Core\Session::getFlash('error'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-6 border border-slate-200 rounded-xl p-6 bg-white shadow-sm">
        <?= \App\Core\Csrf::field() ?>

        <div>
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Type</label>
            <select name="pin_type" id="pin_type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" required>
                <?php
                $opts = [
                    'document_category' => 'Dossier documentaire (catégorie GED)',
                    'document' => 'Document publié',
                    'courrier_document' => 'Courrier officiel',
                    'external_url' => 'Lien (URL)',
                    'notice' => 'Consigne / note texte',
                ];
                foreach ($opts as $val => $lab):
                ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $type === $val ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Titre affiché (optionnel)</label>
            <input type="text" name="title" value="<?= htmlspecialchars((string) ($pin['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="500" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Surcharge du libellé automatique">
        </div>

        <div id="block_document_category" class="space-y-1 <?= $type === 'document_category' ? '' : 'hidden' ?>">
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500">Dossier</label>
            <select name="document_category_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= $isEdit && (int) ($pin['document_category_id'] ?? 0) === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="block_document" class="space-y-1 <?= $type === 'document' ? '' : 'hidden' ?>">
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500">Document</label>
            <select name="document_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($documents as $d): ?>
                    <option value="<?= (int) ($d['id'] ?? 0) ?>" <?= $isEdit && (int) ($pin['document_id'] ?? 0) === (int) ($d['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($d['title'] ?? '') . ' (' . (string) ($d['status'] ?? '') . ')', ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="block_courrier_document" class="space-y-1 <?= $type === 'courrier_document' ? '' : 'hidden' ?>">
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500">Courrier</label>
            <select name="courrier_document_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <option value="">—</option>
                <?php foreach ($courrierDocs as $cd): ?>
                    <?php
                    $lab = trim((string) ($cd['reference_number'] ?? '') . ' ' . (string) ($cd['title'] ?? '') . ' [' . (string) ($cd['status'] ?? '') . ']');
                    ?>
                    <option value="<?= (int) ($cd['id'] ?? 0) ?>" <?= $isEdit && (int) ($pin['courrier_document_id'] ?? 0) === (int) ($cd['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="block_external_url" class="space-y-1 <?= $type === 'external_url' ? '' : 'hidden' ?>">
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500">URL (https)</label>
            <input type="url" name="external_url" value="<?= htmlspecialchars((string) ($pin['external_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="https://">
        </div>

        <div id="block_notice" class="space-y-1 <?= $type === 'notice' ? '' : 'hidden' ?>">
            <label class="block text-xs font-black uppercase tracking-wider text-slate-500">Texte de la consigne</label>
            <textarea name="notice_body" rows="6" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono"><?= htmlspecialchars((string) ($pin['notice_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            <p class="text-[10px] text-slate-500">Affiché tel quel (échappement HTML côté affichage).</p>
        </div>

        <div class="pt-2">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-slate-900 text-white text-sm font-black rounded-lg hover:bg-slate-800"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
        </div>
    </form>
</div>

<script>
(function () {
    var sel = document.getElementById('pin_type');
    if (!sel) return;
    var blocks = {
        document_category: document.getElementById('block_document_category'),
        document: document.getElementById('block_document'),
        courrier_document: document.getElementById('block_courrier_document'),
        external_url: document.getElementById('block_external_url'),
        notice: document.getElementById('block_notice')
    };
    function sync() {
        var v = sel.value;
        Object.keys(blocks).forEach(function (k) {
            var el = blocks[k];
            if (!el) return;
            el.classList.toggle('hidden', k !== v);
        });
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
