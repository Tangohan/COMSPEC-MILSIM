<?php
$roleDefinitions = $roleDefinitions ?? [];
$definitionRelations = $definitionRelations ?? [];
$tenantRoles = $tenantRoles ?? [];
$roleRelations = $roleRelations ?? [];
$units = $units ?? [];
$rolePresetMeta = $rolePresetMeta ?? [];
$graphJsonUrl = url('back-office/roles-functions/graph.json');
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10 space-y-8">
    <div class="rounded-lg border border-blue-100 bg-blue-50/90 px-4 py-3 text-sm text-slate-800">
        Cette page décrit les liens entre les rôles de <strong class="font-semibold">votre communauté</strong> et le référentiel des fonctions.
        Seuls les rôles internes à la communauté apparaissent dans le graphe ; les habilitations plateforme sont gérées ailleurs.
    </div>
    <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Gestion des rôles et fonctions</h1>
            <p class="text-sm text-slate-600 mt-2 max-w-2xl">
                Catalogue des fonctions de référence, graphe des relations entre rôles, unités de l’organigramme et raccourcis vers l’attribution de plusieurs rôles par membre.
                Les droits effectifs combinent les rôles attribués, les permissions et les spécificités par unité.
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Bibliothèque de données</p>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-slate-50 p-2"><p class="text-xl font-black text-slate-900"><?= count($roleDefinitions) ?></p><p class="text-[10px] uppercase text-slate-500">Fonctions</p></div>
                <div class="rounded-xl bg-slate-50 p-2"><p class="text-xl font-black text-slate-900"><?= count($tenantRoles) ?></p><p class="text-[10px] uppercase text-slate-500">Rôles</p></div>
                <div class="rounded-xl bg-slate-50 p-2"><p class="text-xl font-black text-slate-900"><?= count($roleRelations) ?></p><p class="text-[10px] uppercase text-slate-500">Relations</p></div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="<?= url('back-office/personnel-job-roles') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">Référentiel emplois</a>
                <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">Attributions</a>
            </div>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
            <a href="<?= url('back-office/users') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Utilisateurs</a>
            <a href="<?= url('back-office/roles') ?>" class="inline-flex items-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800">Rôles &amp; permissions</a>
            <a href="<?= url('back-office/roles/presets') ?>" class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-900 hover:bg-blue-100">Profils prédéfinis</a>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 mb-3">A. Attribution multi-rôles</h2>
        <p class="text-sm text-slate-600 mb-4">Sélectionnez un compte pour attribuer ou retirer des rôles (tags multiples, union des permissions).</p>
        <a href="<?= url('back-office/users') ?>" class="text-blue-700 font-semibold underline text-sm">Ouvrir la liste des utilisateurs →</a>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 mb-3">B. Contexte communauté et unité</h2>
        <p class="text-sm text-slate-600 mb-4">Unités de l’organigramme : les rôles peuvent être précisés selon l’affectation d’un membre à une unité donnée.</p>
        <?php if ($units === []): ?>
            <p class="text-sm text-amber-800 bg-amber-50 rounded-lg px-3 py-2">Aucune unité définie. Créez des groupes dans l’ORBAT.</p>
        <?php else: ?>
            <ul class="flex flex-wrap gap-2">
                <?php foreach ($units as $u): ?>
                    <li class="text-xs font-semibold uppercase tracking-wide bg-slate-100 text-slate-800 px-2 py-1 rounded-md"><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 mb-3">C. Hiérarchie &amp; graphe des rôles (tenant)</h2>
        <p class="text-sm text-slate-600 mb-4">Arêtes issues des relations configurées pour ce tenant (rapports, transversal, etc.).</p>
        <?php if ($roleRelations === []): ?>
            <p class="text-sm text-slate-500">Aucune relation pour ce tenant (les correspondances de slug avec le catalogue permettent de remplir le graphe après migration).</p>
        <?php else: ?>
            <ul class="space-y-2 text-sm">
                <?php foreach ($roleRelations as $rr): ?>
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($rr['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="text-[10px] uppercase font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars((string) ($rr['relation_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($rr['to_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50/80 p-4 min-h-[220px]" id="roles-graph-host" data-graph-url="<?= htmlspecialchars($graphJsonUrl, ENT_QUOTES, 'UTF-8') ?>">
            <p class="text-xs text-slate-500 mb-2">Visualisation (aperçu)</p>
            <canvas id="roles-graph-canvas" class="w-full max-h-64 border border-slate-200 rounded-lg bg-white" width="800" height="240"></canvas>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h2 class="text-lg font-bold text-slate-900 mb-3">D. Catalogue global (définitions)</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-semibold text-slate-600">Recherche rapide
                <input id="rf-library-search" type="search" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Slug, nom FR/US, famille…">
            </label>
            <label class="text-xs font-semibold text-slate-600">Filtrer par famille
                <select id="rf-library-family" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Toutes les familles</option>
                    <?php foreach (array_values(array_unique(array_filter(array_map(static fn(array $d): string => trim((string) ($d['family'] ?? '')), $roleDefinitions)))) as $fam): ?>
                        <option value="<?= htmlspecialchars($fam, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fam, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm" id="rf-library-table">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                        <th class="py-2 pr-4">Référence courte</th>
                        <th class="py-2 pr-4">FR</th>
                        <th class="py-2 pr-4">US</th>
                        <th class="py-2">Famille</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roleDefinitions as $d): ?>
                        <tr class="border-b border-slate-100" data-rf-row data-family="<?= htmlspecialchars((string) ($d['family'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars(mb_strtolower(trim((string) (($d['slug'] ?? '') . ' ' . ($d['name_fr'] ?? '') . ' ' . ($d['name_us'] ?? '') . ' ' . ($d['family'] ?? ''))), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                            <td class="py-2 pr-4 font-mono text-xs"><?= htmlspecialchars((string) ($d['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($d['name_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($d['name_us'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 text-slate-600"><?= htmlspecialchars((string) ($d['family'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p id="rf-library-empty" class="hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">Aucune entrée ne correspond au filtre courant.</p>
        <?php if ($definitionRelations !== []): ?>
            <h3 class="text-sm font-bold text-slate-800 mt-6 mb-2">Graphe catalogue (définitions)</h3>
            <ul class="text-xs text-slate-600 space-y-1">
                <?php foreach ($definitionRelations as $dr): ?>
                    <li><?= htmlspecialchars((string) ($dr['from_slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-slate-400">→</span> <?= htmlspecialchars((string) ($dr['to_slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-slate-400">(<?= htmlspecialchars((string) ($dr['relation_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 mb-3">E. Templates (packs)</h2>
        <p class="text-sm text-slate-600 mb-4">Appliquer un jeu de permissions à un rôle depuis la page des profils.</p>
        <ul class="grid sm:grid-cols-2 gap-3">
            <?php foreach ($rolePresetMeta as $pm): ?>
                <li class="rounded-lg border border-slate-200 p-3">
                    <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($pm['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-slate-600 mt-1"><?= htmlspecialchars((string) ($pm['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-[10px] text-slate-400 mt-2 font-mono"><?= htmlspecialchars((string) ($pm['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="<?= url('back-office/roles/presets') ?>" class="inline-flex mt-4 text-sm font-semibold text-blue-700 underline">Appliquer un profil →</a>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
        <h2 class="text-sm font-bold text-slate-800 mb-2">Rôles tenant (aperçu)</h2>
        <ul class="text-sm text-slate-700 space-y-1">
            <?php foreach ($tenantRoles as $tr): ?>
                <li><span class="font-medium"><?= htmlspecialchars((string) ($tr['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-slate-400 text-xs">(<?= htmlspecialchars((string) ($tr['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<script>
(function () {
  var host = document.getElementById('roles-graph-host');
  var canvas = document.getElementById('roles-graph-canvas');
  if (!host || !canvas) return;
  var url = host.getAttribute('data-graph-url');
  if (!url) return;
  fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (data) {
    var nodes = data.nodes || [];
    var edges = data.edges || [];
    var ctx = canvas.getContext('2d');
    var w = canvas.width;
    var h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    ctx.strokeStyle = '#94a3b8';
    ctx.fillStyle = '#0f172a';
    ctx.font = '11px system-ui,sans-serif';
    var pos = {};
    nodes.forEach(function (n, i) {
      var angle = (2 * Math.PI * i) / Math.max(nodes.length, 1);
      pos[n.id] = { x: w / 2 + Math.cos(angle) * (w * 0.35), y: h / 2 + Math.sin(angle) * (h * 0.35) };
    });
    edges.forEach(function (e) {
      var a = pos[e.from], b = pos[e.to];
      if (!a || !b) return;
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.stroke();
    });
    nodes.forEach(function (n) {
      var p = pos[n.id];
      if (!p) return;
      ctx.beginPath();
      ctx.arc(p.x, p.y, 6, 0, 2 * Math.PI);
      ctx.fillStyle = '#2563eb';
      ctx.fill();
      ctx.fillStyle = '#334155';
      ctx.fillText((n.label || n.slug || '').slice(0, 24), p.x + 10, p.y + 4);
    });
  }).catch(function () {});
})();

(function () {
  var search = document.getElementById('rf-library-search');
  var family = document.getElementById('rf-library-family');
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-rf-row]'));
  var empty = document.getElementById('rf-library-empty');
  if (!rows.length || !search || !family) return;
  function apply() {
    var q = (search.value || '').trim().toLowerCase();
    var fam = family.value || '';
    var visible = 0;
    rows.forEach(function (row) {
      var okQ = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      var okFam = !fam || (row.getAttribute('data-family') || '') === fam;
      var show = okQ && okFam;
      row.classList.toggle('hidden', !show);
      if (show) visible++;
    });
    if (empty) empty.classList.toggle('hidden', visible !== 0);
  }
  search.addEventListener('input', apply);
  family.addEventListener('change', apply);
})();
</script>
