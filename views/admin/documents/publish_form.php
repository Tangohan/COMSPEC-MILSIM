<?php
declare(strict_types=1);

$document = is_array($document ?? null) ? $document : null;
$doctrine = is_array($doctrine ?? null) ? $doctrine : null;
$types = is_array($types ?? null) ? $types : [];
$units = is_array($units ?? null) ? $units : [];
$jobRoles = is_array($jobRoles ?? null) ? $jobRoles : [];
$roles = is_array($roles ?? null) ? $roles : [];
$users = is_array($users ?? null) ? $users : [];
$publishedDocs = is_array($publishedDocs ?? null) ? $publishedDocs : [];
$audiences = is_array($audiences ?? null) ? $audiences : [];
$csrf_token = (string) ($csrf_token ?? '');
$docId = (int) ($document['id'] ?? 0);

$audienceMode = 'all_members';
$selectedUnits = [];
$selectedJobRoles = [];
$selectedRoles = [];
$selectedUsers = [];
$includeChildren = false;
foreach ($audiences as $a) {
    $t = (string) ($a['audience_type'] ?? '');
    if ($t === 'all_members') {
        $audienceMode = 'all_members';
    } elseif ($t === 'unit') {
        $audienceMode = 'targeted';
        $selectedUnits[] = (int) ($a['audience_value'] ?? 0);
        if (!empty($a['include_children'])) {
            $includeChildren = true;
        }
    } elseif ($t === 'job_role') {
        $audienceMode = 'targeted';
        $selectedJobRoles[] = (string) ($a['audience_value'] ?? '');
    } elseif ($t === 'role') {
        $audienceMode = 'targeted';
        $selectedRoles[] = (string) ($a['audience_value'] ?? '');
    } elseif ($t === 'user') {
        $audienceMode = 'targeted';
        $selectedUsers[] = (int) ($a['audience_value'] ?? 0);
    }
}

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/doctrine-referential.css'), ENT_QUOTES, 'UTF-8') ?>">
<div class="max-w-4xl mx-auto px-4 py-8">
    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Publication interne</p>
    <h1 class="mt-1 text-2xl font-black text-slate-900"><?= $docId > 0 ? 'Modifier le document' : 'Rédiger et diffuser un document' ?></h1>
    <p class="mt-2 text-sm text-slate-600">Instructions, notes de service, directives et consignes : rédaction, ciblage ORBAT, lecture obligatoire et suivi des accusés.</p>

    <form id="doc-publish-form" method="post" action="<?= url('back-office/documents/publier') ?>" class="mt-6 space-y-6">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf_token) ?>">
        <input type="hidden" name="document_id" value="<?= $docId ?>">
        <input type="hidden" name="action" id="doc-publish-action" value="save">

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Identité</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="text-xs font-bold uppercase text-slate-500 md:col-span-2">Titre
                    <input class="mt-1 w-full rounded-lg border px-3 py-2" name="title" required value="<?= $h($document['title'] ?? '') ?>">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">Type
                    <select class="mt-1 w-full rounded-lg border px-3 py-2" name="document_type_id" required>
                        <option value="">Choisir…</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) ($doctrine['document_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= $h($t['label'] ?? '') ?> (<?= $h($t['code_prefix'] ?? '') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">Référence (laisser vide pour génération auto)
                    <input class="mt-1 w-full rounded-lg border px-3 py-2 font-mono" name="reference_code" placeholder="NS-2026-014" value="<?= $h($doctrine['reference_code'] ?? '') ?>" <?= $docId > 0 ? 'readonly' : '' ?>>
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">Autorité émettrice
                    <input class="mt-1 w-full rounded-lg border px-3 py-2" name="issuing_label" value="<?= $h($doctrine['issuing_label'] ?? '') ?>" placeholder="État-major / Commandement">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">Unité émettrice
                    <select class="mt-1 w-full rounded-lg border px-3 py-2" name="issuing_unit_id">
                        <option value="">—</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (int) ($doctrine['issuing_unit_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= $h($u['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-bold uppercase text-slate-500 md:col-span-2">Objet
                    <textarea class="mt-1 w-full rounded-lg border px-3 py-2" name="object" rows="2"><?= $h($doctrine['summary'] ?? $document['short_description'] ?? '') ?></textarea>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Contenu</h2>
            <textarea id="doc-body-html" name="body_html" rows="16" class="w-full rounded-lg border px-3 py-2"><?= $h($doctrine['body_html'] ?? '') ?></textarea>
            <p class="text-xs text-slate-500">Titres, listes, tableaux et liens. Les pièces jointes PDF se gèrent ensuite depuis la fiche document si besoin.</p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Dates et exigences</h2>
            <div class="grid gap-3 md:grid-cols-3">
                <label class="text-xs font-bold uppercase text-slate-500">Prise d’effet
                    <input type="datetime-local" class="mt-1 w-full rounded-lg border px-3 py-2" name="effective_at" value="<?= $h(isset($doctrine['effective_at']) && $doctrine['effective_at'] ? date('Y-m-d\TH:i', strtotime((string) $doctrine['effective_at'])) : '') ?>">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">Fin d’application
                    <input type="datetime-local" class="mt-1 w-full rounded-lg border px-3 py-2" name="expires_at" value="<?= $h(isset($doctrine['expires_at']) && $doctrine['expires_at'] ? date('Y-m-d\TH:i', strtotime((string) $doctrine['expires_at'])) : '') ?>">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">Lecture exigée avant
                    <input type="datetime-local" class="mt-1 w-full rounded-lg border px-3 py-2" name="acknowledgment_deadline_at" value="<?= $h(isset($doctrine['acknowledgment_deadline_at']) && $doctrine['acknowledgment_deadline_at'] ? date('Y-m-d\TH:i', strtotime((string) $doctrine['acknowledgment_deadline_at'])) : '') ?>">
                </label>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-slate-700">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="reading_required" value="1" <?= !empty($doctrine['reading_required']) ? 'checked' : '' ?>> Lecture obligatoire</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="acknowledgment_required" value="1" <?= !empty($doctrine['acknowledgment_required']) ? 'checked' : '' ?>> Accusé de lecture obligatoire</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_permanent" value="1" <?= !empty($doctrine['is_permanent']) ? 'checked' : '' ?>> Document permanent</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_validation" value="1" <?= !empty($doctrine['require_validation']) ? 'checked' : '' ?>> Validation avant publication</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="reminder_on_publish" value="1" <?= !isset($doctrine['reminder_on_publish']) || !empty($doctrine['reminder_on_publish']) ? 'checked' : '' ?>> Notifier à la publication</label>
            </div>
            <label class="block text-xs font-bold uppercase text-slate-500">Texte de confirmation (accusé)
                <input class="mt-1 w-full rounded-lg border px-3 py-2" name="confirmation_text" value="<?= $h($doctrine['confirmation_text'] ?? '') ?>" placeholder="Je reconnais avoir pris connaissance de cette instruction.">
            </label>
            <label class="block text-xs font-bold uppercase text-slate-500">Visibilité
                <select class="mt-1 w-full rounded-lg border px-3 py-2" name="visibility_mode">
                    <option value="recipients_only" <?= ($doctrine['visibility_mode'] ?? 'recipients_only') === 'recipients_only' ? 'selected' : '' ?>>Visible uniquement par les destinataires</option>
                    <option value="library" <?= ($doctrine['visibility_mode'] ?? '') === 'library' ? 'selected' : '' ?>>Visible dans la bibliothèque générale</option>
                </select>
            </label>
            <label class="block text-xs font-bold uppercase text-slate-500">Remplace le document
                <select class="mt-1 w-full rounded-lg border px-3 py-2" name="replaces_document_id">
                    <option value="">— Aucun —</option>
                    <?php foreach ($publishedDocs as $pd): ?>
                    <?php if ((int) ($pd['document_id'] ?? 0) === $docId) continue; ?>
                    <option value="<?= (int) ($pd['document_id'] ?? 0) ?>" <?= (int) ($doctrine['replaces_document_id'] ?? 0) === (int) ($pd['document_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= $h(($pd['reference_code'] ?? '') . ' — ' . ($pd['title'] ?? '')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-xs font-bold uppercase text-slate-500">Niveau de confidentialité
                <select class="mt-1 w-full rounded-lg border px-3 py-2" name="classification_level">
                    <?php
                    $levels = [
                        'interne' => 'Normal',
                        'restreint' => 'Restreint',
                        'commandement' => 'Commandement',
                        'confidentiel' => 'Confidentiel interne',
                    ];
                    $curLevel = (string) ($document['classification_level'] ?? 'interne');
                    foreach ($levels as $val => $lab):
                    ?>
                    <option value="<?= $h($val) ?>" <?= $curLevel === $val ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Destinataires</h2>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="radio" name="audience_mode" value="all_members" <?= $audienceMode === 'all_members' ? 'checked' : '' ?>> Toute l’organisation</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="audience_mode" value="targeted" <?= $audienceMode === 'targeted' ? 'checked' : '' ?>> Ciblage précis</label>
            </div>
            <div id="audience-targeted" class="<?= $audienceMode === 'targeted' ? '' : 'hidden' ?> space-y-3">
                <label class="block text-xs font-bold uppercase text-slate-500">Unités
                    <select class="mt-1 w-full rounded-lg border px-3 py-2" name="audience_units[]" multiple size="6">
                        <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= in_array((int) $u['id'], $selectedUnits, true) ? 'selected' : '' ?>><?= $h($u['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="include_children" value="1" <?= $includeChildren ? 'checked' : '' ?>> Inclure les sous-unités</label>
                <label class="block text-xs font-bold uppercase text-slate-500">Fonctions
                    <select class="mt-1 w-full rounded-lg border px-3 py-2" name="audience_job_roles[]" multiple size="6">
                        <?php foreach ($jobRoles as $jr): ?>
                        <option value="<?= (int) ($jr['id'] ?? 0) ?>" <?= in_array((string) ($jr['id'] ?? ''), $selectedJobRoles, true) || in_array((int) ($jr['id'] ?? 0), array_map('intval', $selectedJobRoles), true) ? 'selected' : '' ?>><?= $h($jr['label'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-xs font-bold uppercase text-slate-500">Rôles Athena
                    <select class="mt-1 w-full rounded-lg border px-3 py-2" name="audience_roles[]" multiple size="4">
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $h($r['slug'] ?? '') ?>" <?= in_array((string) ($r['slug'] ?? ''), $selectedRoles, true) ? 'selected' : '' ?>><?= $h($r['name'] ?? $r['slug'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-xs font-bold uppercase text-slate-500">Personnels
                    <select class="mt-1 w-full rounded-lg border px-3 py-2" name="audience_users[]" multiple size="6">
                        <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= in_array((int) $u['id'], $selectedUsers, true) ? 'selected' : '' ?>><?= $h($u['display_name'] ?? $u['email'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-black uppercase text-slate-800" onclick="document.getElementById('doc-publish-action').value='save'">Enregistrer le brouillon</button>
            <button type="submit" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-black uppercase text-amber-900" onclick="document.getElementById('doc-publish-action').value='submit_validation'">Soumettre à validation</button>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white" onclick="document.getElementById('doc-publish-action').value='publish'">Publier</button>
            <?php if ($docId > 0): ?>
            <a href="<?= url('documents/doctrine/' . $docId) ?>" class="rounded-lg px-4 py-2 text-xs font-bold uppercase text-slate-600">Voir la fiche</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<script>
(function () {
  var radios = document.querySelectorAll('input[name="audience_mode"]');
  var box = document.getElementById('audience-targeted');
  function sync() {
    var mode = document.querySelector('input[name="audience_mode"]:checked');
    if (!box || !mode) return;
    box.classList.toggle('hidden', mode.value !== 'targeted');
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
<script src="<?= htmlspecialchars(asset_url('assets/js/document_rich_editor.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
