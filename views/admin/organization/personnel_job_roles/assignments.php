<?php
$assignmentRows = $assignmentRows ?? [];
$assignmentPivot = $assignmentPivot ?? [];
$jobRoleOptions = $jobRoleOptions ?? [];
$pjrAssignSettings = $pjrAssignSettings ?? [];
$pivotEnabled = !empty($pivotEnabled);
$filters = $filters ?? [];
$assignmentsPage = (int) ($assignmentsPage ?? 1);
$assignmentsTotal = (int) ($assignmentsTotal ?? 0);
$assignmentsPerPage = (int) ($assignmentsPerPage ?? 30);
$assignmentsTotalPages = (int) ($assignmentsTotalPages ?? 1);
$activeTab = $activeTab ?? 'assignments';
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$maxRoles = (int) ($pjrAssignSettings['max_roles_per_member'] ?? 5);
$defaultExpand = (int) ($pjrAssignSettings['default_expand_role_rows'] ?? 3);

$returnQuery = http_build_query(array_filter([
    'search' => $filters['search'] ?? '',
    'job_role_id' => !empty($filters['job_role_id']) ? (int) $filters['job_role_id'] : null,
    'unassigned' => !empty($filters['unassigned']) ? '1' : null,
    'page' => $assignmentsPage > 1 ? $assignmentsPage : null,
], static fn ($v) => $v !== null && $v !== ''));

$baseUrl = url('back-office/personnel-job-roles/assignments');
?>
<div class="mx-auto max-w-7xl px-6 py-12">
    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Attributions rôles métier</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">
                <?php if ($pivotEnabled): ?>
                Attribuez un ou plusieurs emplois du référentiel à chaque membre. Indiquez le rôle <strong class="font-semibold">principal</strong> : il sert de référence pour le dossier et l’ORBAT. Les rôles additionnels restent visibles sur la fiche et dans les filtres.
                <?php else: ?>
                Attribuez le référentiel de fonction (dossier personnel) à chaque membre. Pour activer plusieurs rôles par personne, exécutez les migrations (table de liaison).
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= url('back-office/personnel-job-roles') ?>" class="text-sm font-medium text-slate-600 hover:underline">Référentiel &amp; catégories</a>
    </div>

    <?php if ($flashSuccess): ?>
    <p class="mb-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <p class="mb-4 rounded bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>

    <details class="mb-8 rounded-xl border border-amber-200 bg-amber-50/40 p-4 shadow-sm open:bg-amber-50/60">
        <summary class="cursor-pointer text-sm font-bold text-amber-950">Paramètres d’attribution (organisation)</summary>
        <p class="mt-2 text-xs text-amber-900/90">Ces réglages s’appliquent à toute votre communauté : taille de page, nombre maximal de rôles par membre, affichage des listes et du libellé dossier.</p>
        <form method="post" action="<?= url('back-office/personnel-job-roles/assignments/settings') ?>" class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700">Rôles max. par membre</label>
                <input type="number" name="max_roles_per_member" min="1" max="12" value="<?= (int) ($pjrAssignSettings['max_roles_per_member'] ?? 5) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700">Lignes vides affichées au chargement</label>
                <input type="number" name="default_expand_role_rows" min="1" max="12" value="<?= (int) ($pjrAssignSettings['default_expand_role_rows'] ?? 3) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                <p class="mt-0.5 text-[10px] text-slate-500">Nombre de lignes de saisie (emplois) proposées par défaut, sans dépasser le maximum ci-dessus.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700">Membres par page</label>
                <input type="number" name="assignments_page_size" min="10" max="100" value="<?= (int) ($pjrAssignSettings['assignments_page_size'] ?? 30) ?>" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2 md:col-span-2 lg:col-span-3">
                <input type="hidden" name="require_primary_when_multiple" value="0">
                <input type="checkbox" name="require_primary_when_multiple" id="req_pri" value="1" <?= !empty($pjrAssignSettings['require_primary_when_multiple']) ? 'checked' : '' ?>>
                <label for="req_pri" class="text-sm text-slate-800">Exiger un rôle principal lorsque plusieurs emplois sont renseignés (recommandé).</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="show_english_labels" value="0">
                <input type="checkbox" name="show_english_labels" id="show_en" value="1" <?= !empty($pjrAssignSettings['show_english_labels']) ? 'checked' : '' ?>>
                <label for="show_en" class="text-sm text-slate-800">Afficher le libellé anglais dans les listes</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="show_category_in_role_picklist" value="0">
                <input type="checkbox" name="show_category_in_role_picklist" id="show_cat" value="1" <?= !empty($pjrAssignSettings['show_category_in_role_picklist']) ? 'checked' : '' ?>>
                <label for="show_cat" class="text-sm text-slate-800">Afficher la famille (catégorie) dans les listes</label>
            </div>
            <div class="flex items-center gap-2 md:col-span-2">
                <input type="hidden" name="append_secondaries_to_primary_display" value="0">
                <input type="checkbox" name="append_secondaries_to_primary_display" id="append_sec" value="1" <?= !empty($pjrAssignSettings['append_secondaries_to_primary_display']) ? 'checked' : '' ?>>
                <label for="append_sec" class="text-sm text-slate-800">Fusionner les emplois secondaires dans le champ « libellé principal » du dossier (sinon ils vont dans « rôle secondaire »).</label>
            </div>
            <div class="md:col-span-2 lg:col-span-3">
                <button type="submit" class="rounded-lg bg-amber-800 px-4 py-2 text-sm font-bold text-white hover:bg-amber-900">Enregistrer les paramètres</button>
            </div>
        </form>
    </details>

    <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="mb-8 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="min-w-[200px] flex-1">
            <label class="mb-1 block text-xs font-semibold text-slate-600">Recherche</label>
            <input type="text" name="search" value="<?= htmlspecialchars((string) ($filters['search'] ?? '')) ?>" placeholder="Nom, email, indicatif…" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div class="min-w-[220px]">
            <label class="mb-1 block text-xs font-semibold text-slate-600">Emploi (au moins une affectation)</label>
            <select name="job_role_id" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                <option value="0">— Tous —</option>
                <?php foreach ($jobRoleOptions as $jo): ?>
                <option value="<?= (int) $jo['id'] ?>" <?= (int) ($filters['job_role_id'] ?? 0) === (int) $jo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($jo['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="unassigned" id="unassigned" value="1" <?= !empty($filters['unassigned']) ? 'checked' : '' ?>>
            <label for="unassigned" class="text-sm text-slate-700">Sans aucun emploi attribué</label>
        </div>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrer</button>
    </form>

    <div class="mb-4 text-sm text-slate-600">
        <?= (int) $assignmentsTotal ?> membre(s) · page <?= (int) $assignmentsPage ?> / <?= (int) $assignmentsTotalPages ?>
        <?php if ($pivotEnabled): ?> · jusqu’à <?= (int) $maxRoles ?> emploi(s) par personne<?php endif; ?>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[960px] border-collapse text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Membre</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Statut</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Emplois (dossier)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignmentRows as $row): ?>
                <?php
                $uid = (int) ($row['id'] ?? 0);
                $slug = trim((string) ($row['profile_slug'] ?? ''));
                $personnelUrl = url('personnel/' . ($slug !== '' ? $slug : (string) $uid));
                $curJr = isset($row['personnel_job_role_id']) ? (int) $row['personnel_job_role_id'] : 0;
                $pivotRows = $pivotEnabled && isset($assignmentPivot[$uid]) ? $assignmentPivot[$uid] : [];
                if ($pivotEnabled && $pivotRows === [] && $curJr > 0) {
                    $pivotRows = [[
                        'personnel_job_role_id' => $curJr,
                        'role_detail' => (string) ($row['role_sub_label'] ?? ''),
                        'is_primary' => 1,
                    ]];
                }
                $nExisting = count($pivotRows);
                $slotCount = $pivotEnabled
                    ? min($maxRoles, max($nExisting, 1, min($defaultExpand, $maxRoles)))
                    : 1;
                $primaryIdxFromData = null;
                foreach ($pivotRows as $pidx => $prow) {
                    if (!empty($prow['is_primary'])) {
                        $primaryIdxFromData = (int) $pidx;
                        break;
                    }
                }
                ?>
                <tr class="border-b border-slate-100 align-top hover:bg-slate-50/80">
                    <td class="p-3">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['display_name'] ?? '—')) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></p>
                        <?php if (trim((string) ($row['callsign'] ?? '')) !== ''): ?>
                        <p class="text-xs font-mono text-slate-600"><?= htmlspecialchars((string) $row['callsign']) ?></p>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($personnelUrl) ?>" class="mt-1 inline-block text-xs font-medium text-cyan-700 hover:underline">Fiche personnelle</a>
                    </td>
                    <td class="p-3 text-xs uppercase text-slate-600"><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                    <td class="p-3">
                        <form method="post" action="<?= url('back-office/personnel-job-roles/assignments/save') ?>" class="space-y-3 pjr-assign-form" data-max-slots="<?= (int) $maxRoles ?>" data-user-id="<?= $uid ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($pivotEnabled): ?>
                            <div class="space-y-2 rounded-lg border border-slate-100 bg-slate-50/50 p-3">
                                <p class="text-[10px] font-bold uppercase text-slate-500">Emplois</p>
                                <?php
                                for ($si = 0; $si < $slotCount; $si++):
                                    $pr = $pivotRows[$si] ?? null;
                                    $selId = $pr ? (int) ($pr['personnel_job_role_id'] ?? 0) : 0;
                                    $det = $pr ? trim((string) ($pr['role_detail'] ?? '')) : '';
                                    $isPri = ($primaryIdxFromData !== null && $primaryIdxFromData < $slotCount && $si === $primaryIdxFromData)
                                        || (($primaryIdxFromData === null || $primaryIdxFromData >= $slotCount) && $si === 0);
                                ?>
                                <div class="pjr-slot flex flex-col gap-2 rounded border border-slate-200 bg-white p-2 sm:flex-row sm:flex-wrap sm:items-end">
                                    <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-600 shrink-0">
                                        <input type="radio" name="primary_slot" value="<?= $si ?>" class="pjr-primary-radio text-emerald-600" <?= $isPri ? 'checked' : '' ?>>
                                        Principal
                                    </label>
                                    <div class="min-w-[200px] flex-1">
                                        <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Emploi</label>
                                        <select name="slots[<?= $si ?>][role_id]" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs">
                                            <option value="">— Aucun —</option>
                                            <?php foreach ($jobRoleOptions as $jo): ?>
                                            <option value="<?= (int) $jo['id'] ?>" <?= $selId === (int) $jo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($jo['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="min-w-[140px] flex-1">
                                        <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Précision</label>
                                        <input type="text" name="slots[<?= $si ?>][detail]" value="<?= htmlspecialchars($det) ?>" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs" maxlength="150" placeholder="Optionnel">
                                    </div>
                                </div>
                                <?php endfor; ?>
                                <button type="button" class="pjr-add-slot rounded border border-dashed border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Ajouter une ligne d’emploi</button>
                            </div>
                            <div class="min-w-[200px]">
                                <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Complément global (dossier)</label>
                                <input type="text" name="role_sub_label" value="<?= htmlspecialchars((string) ($row['role_sub_label'] ?? '')) ?>" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs" maxlength="150" placeholder="Texte ajouté au libellé principal">
                            </div>
                            <?php else: ?>
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                                <div class="min-w-[200px] flex-1">
                                    <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Rôle métier</label>
                                    <select name="personnel_job_role_id" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs">
                                        <option value="">— Aucun —</option>
                                        <?php foreach ($jobRoleOptions as $jo): ?>
                                        <option value="<?= (int) $jo['id'] ?>" <?= $curJr === (int) $jo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($jo['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="min-w-[160px] flex-1">
                                    <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Sous-rôle</label>
                                    <input type="text" name="role_sub_label" value="<?= htmlspecialchars((string) ($row['role_sub_label'] ?? '')) ?>" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs" maxlength="150" placeholder="Optionnel">
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="rounded border border-dashed border-slate-200 bg-slate-50/80 px-2 py-1.5 text-xs text-slate-600">
                                <span class="font-bold text-slate-500">Libellé dossier :</span>
                                <?= htmlspecialchars((string) ($row['primary_role'] ?? '—')) ?>
                            </div>
                            <div class="shrink-0">
                                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800">Enregistrer</button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($assignmentRows)): ?>
        <p class="p-8 text-center text-slate-500">Aucun membre ne correspond aux filtres.</p>
        <?php endif; ?>
    </div>

    <?php if ($assignmentsTotalPages > 1): ?>
    <div class="mt-6 flex flex-wrap justify-center gap-2">
        <?php for ($p = 1; $p <= $assignmentsTotalPages; $p++): ?>
        <?php
        $pageQs = http_build_query(array_filter([
            'search' => $filters['search'] ?? '',
            'job_role_id' => !empty($filters['job_role_id']) ? (int) $filters['job_role_id'] : null,
            'unassigned' => !empty($filters['unassigned']) ? '1' : null,
            'page' => $p > 1 ? $p : null,
        ], static fn ($v) => $v !== null && $v !== ''));
        $href = $baseUrl . ($pageQs !== '' ? '?' . $pageQs : '');
        ?>
        <a href="<?= htmlspecialchars($href) ?>" class="min-w-[2.25rem] rounded border px-3 py-1.5 text-sm <?= $p === $assignmentsPage ? 'border-slate-900 bg-slate-900 font-bold text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($pivotEnabled): ?>
<template id="pjr-slot-template">
    <div class="pjr-slot flex flex-col gap-2 rounded border border-slate-200 bg-white p-2 sm:flex-row sm:flex-wrap sm:items-end">
        <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-600 shrink-0">
            <input type="radio" name="primary_slot" value="__IDX__" class="text-emerald-600 pjr-primary-radio">
            Principal
        </label>
        <div class="min-w-[200px] flex-1">
            <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Emploi</label>
            <select name="slots[__IDX__][role_id]" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs pjr-role-select">
                <option value="">— Aucun —</option>
                <?php foreach ($jobRoleOptions as $jo): ?>
                <option value="<?= (int) $jo['id'] ?>"><?= htmlspecialchars($jo['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[140px] flex-1">
            <label class="mb-0.5 block text-[10px] font-bold uppercase text-slate-500">Précision</label>
            <input type="text" name="slots[__IDX__][detail]" value="" class="w-full rounded border border-slate-200 px-2 py-1.5 text-xs" maxlength="150" placeholder="Optionnel">
        </div>
    </div>
</template>
<script>
(function () {
  var maxGlobal = <?= (int) $maxRoles ?>;
  document.querySelectorAll('.pjr-assign-form').forEach(function (form) {
    var maxSlots = parseInt(form.getAttribute('data-max-slots') || String(maxGlobal), 10) || maxGlobal;
    var tpl = document.getElementById('pjr-slot-template');
    if (!tpl) return;
    var wrap = form.querySelector('.space-y-2.rounded-lg');
    if (!wrap) return;
    var addBtn = form.querySelector('.pjr-add-slot');
    function countSlots() {
      return wrap.querySelectorAll('.pjr-slot').length;
    }
    function reindexSlots() {
      var slots = wrap.querySelectorAll('.pjr-slot');
      slots.forEach(function (row, i) {
        row.querySelectorAll('input, select').forEach(function (el) {
          var n = el.getAttribute('name');
          if (n && n.indexOf('slots[') === 0) {
            el.setAttribute('name', n.replace(/slots\[\d+]/, 'slots[' + i + ']'));
          }
        });
        var rad = row.querySelector('.pjr-primary-radio');
        if (rad) {
          rad.value = String(i);
          rad.setAttribute('name', 'primary_slot');
        }
      });
    }
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        if (countSlots() >= maxSlots) return;
        var idx = countSlots();
        var html = tpl.innerHTML.replace(/__IDX__/g, String(idx));
        var div = document.createElement('div');
        div.innerHTML = html.trim();
        var node = div.firstElementChild;
        if (!node) return;
        node.classList.add('pjr-slot');
        wrap.insertBefore(node, addBtn);
        reindexSlots();
        var radios = wrap.querySelectorAll('input[type=radio][name=primary_slot]');
        if (radios.length && !Array.prototype.some.call(radios, function (r) { return r.checked; })) {
          radios[0].checked = true;
        }
      });
    }
  });
})();
</script>
<?php endif; ?>
