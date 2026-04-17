<?php
$categories = $categories ?? [];
$roles = $roles ?? [];
$permCounts = $permCounts ?? [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$activeTab = $activeTab ?? 'referentiel';
$personnelProfilesJobRoleReady = $personnelProfilesJobRoleReady ?? true;
?>
<div class="mx-auto max-w-6xl px-6 py-12">
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if (empty($personnelProfilesJobRoleReady)): ?>
    <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
        Les colonnes dossier (<code class="rounded bg-amber-100 px-1">personnel_job_role_id</code>) ne sont pas encore en base : le référentiel est éditable, mais les <strong>attributions effectifs</strong> et le dossier personnel nécessitent la migration complète.
    </div>
    <?php endif; ?>
    <div class="mb-6 grid gap-4 lg:grid-cols-[1.4fr_1fr] lg:items-start">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Référentiel — rôles métier</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">Arborescence par catégories, rôles nommés et presets de permissions (référentiel distinct des rôles communauté / accès).</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Bibliothèque visible</p>
            <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-lg bg-slate-50 px-2 py-2"><p class="text-lg font-black text-slate-900"><?= count($categories) ?></p><p class="text-[10px] uppercase text-slate-500">Catégories</p></div>
                <div class="rounded-lg bg-slate-50 px-2 py-2"><p class="text-lg font-black text-slate-900"><?= count($roles) ?></p><p class="text-[10px] uppercase text-slate-500">Emplois</p></div>
                <div class="rounded-lg bg-slate-50 px-2 py-2"><p class="text-lg font-black text-slate-900"><?= array_sum(array_map(static fn ($v): int => (int) $v, $permCounts)) ?></p><p class="text-[10px] uppercase text-slate-500">Droits liés</p></div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">Attributions effectifs</a>
                <a href="<?= url('back-office/personnel-job-roles/roles/create') ?>" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Nouveau rôle</a>
            </div>
        </div>
    </div>
    <?php if ($flashSuccess): ?>
    <p class="mb-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <p class="mb-4 rounded bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>

    <section class="mb-10 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-bold text-slate-900">Nouvelle catégorie</h2>
        <form method="post" action="<?= url('back-office/personnel-job-roles/categories/save') ?>" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="category_id" value="0">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Parent (optionnel)</label>
                <select name="parent_id" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
                    <option value="">— Racine —</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Nom</label>
                <input type="text" name="category_name" required class="w-full rounded border border-slate-200 px-3 py-2 text-sm" maxlength="120" placeholder="Ex. Renseignement">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Identifiant (slug)</label>
                <input type="text" name="category_slug" class="w-full rounded border border-slate-200 px-3 py-2 text-sm font-mono text-xs" maxlength="80" placeholder="auto si vide">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Ordre</label>
                <input type="number" name="category_sort_order" value="0" class="w-full rounded border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2 lg:col-span-4">
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Créer la catégorie</button>
            </div>
        </form>
    </section>

    <div class="mb-4 grid gap-3 sm:grid-cols-2">
        <label class="text-xs font-semibold text-slate-600">Recherche dans le référentiel
            <input id="pjr-library-search" type="search" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Nom, slug, catégorie, MOS…">
        </label>
        <label class="text-xs font-semibold text-slate-600">Filtrer catégorie
            <select id="pjr-library-category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200">
        <table class="w-full border-collapse text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Catégorie</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Rôle</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Référence</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Spécialité US</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Perms</th>
                    <th class="p-3 text-xs font-semibold uppercase text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50/80" data-pjr-row data-category="<?= htmlspecialchars((string) ($r['category_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars(mb_strtolower(trim((string) (($r['category_name'] ?? '') . ' ' . ($r['name'] ?? '') . ' ' . ($r['slug'] ?? '') . ' ' . ($r['mos_code'] ?? '') . ' ' . ($r['mos_specialty_title'] ?? ''))), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                    <td class="p-3 text-slate-700"><?= htmlspecialchars((string) ($r['category_name'] ?? '—')) ?></td>
                    <td class="p-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($r['name'] ?? '')) ?></td>
                    <td class="p-3 font-mono text-xs text-slate-600"><?= htmlspecialchars((string) ($r['slug'] ?? '')) ?></td>
                    <td class="p-3 text-xs text-slate-700">
                        <?php
                        $mc = trim((string) ($r['mos_code'] ?? ''));
                        $mt = trim((string) ($r['mos_specialty_title'] ?? ''));
                        ?>
                        <?php if ($mc !== ''): ?>
                        <span class="font-mono font-semibold text-slate-900"><?= htmlspecialchars($mc) ?></span>
                        <?php if ($mt !== ''): ?>
                        <span class="block text-[11px] text-slate-500"><?= htmlspecialchars($mt) ?></span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-slate-600"><?= (int) ($permCounts[(int) $r['id']] ?? 0) ?></td>
                    <td class="p-3">
                        <a href="<?= url('back-office/personnel-job-roles/roles/' . (int) $r['id'] . '/edit') ?>" class="text-slate-700 hover:underline">Modifier</a>
                        <?php if (empty($r['is_system'])): ?>
                        <form action="<?= url('back-office/personnel-job-roles/roles/' . (int) $r['id'] . '/delete') ?>" method="post" class="inline" onsubmit="return confirm('Supprimer ce rôle ? Les profils le référenceront comme vide.');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="ml-2 text-rose-600 hover:underline">Supprimer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($roles)): ?>
        <p class="p-6 text-slate-500">Aucun rôle métier — créez une catégorie puis un rôle.</p>
        <?php endif; ?>
    </div>
    <p id="pjr-library-empty" class="hidden mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">Aucun rôle ne correspond à ce filtre.</p>

    <?php if (!empty($categories)): ?>
    <section class="mt-10 rounded-xl border border-amber-200 bg-amber-50/50 p-6">
        <h2 class="mb-3 text-sm font-bold text-amber-950">Supprimer une catégorie vide</h2>
        <p class="mb-4 text-xs text-amber-900/90">Uniquement si aucune sous-catégorie ni rôle n’y est rattaché.</p>
        <ul class="flex flex-wrap gap-3">
            <?php foreach ($categories as $c): ?>
            <li>
                <form action="<?= url('back-office/personnel-job-roles/categories/' . (int) $c['id'] . '/delete') ?>" method="post" class="inline" onsubmit="return confirm('Supprimer cette catégorie ?');">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="rounded border border-amber-300 bg-white px-2 py-1 text-xs font-medium text-amber-950 hover:bg-amber-100"><?= htmlspecialchars((string) $c['name']) ?></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</div>
<script>
(function () {
  var search = document.getElementById('pjr-library-search');
  var cat = document.getElementById('pjr-library-category');
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-pjr-row]'));
  var empty = document.getElementById('pjr-library-empty');
  if (!search || !cat || !rows.length) return;
  function apply() {
    var q = (search.value || '').trim().toLowerCase();
    var c = cat.value || '';
    var visible = 0;
    rows.forEach(function (row) {
      var okQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var okC = !c || (row.getAttribute('data-category') || '') === c;
      var show = okQ && okC;
      row.classList.toggle('hidden', !show);
      if (show) visible++;
    });
    if (empty) empty.classList.toggle('hidden', visible !== 0);
  }
  search.addEventListener('input', apply);
  cat.addEventListener('change', apply);
})();
</script>
