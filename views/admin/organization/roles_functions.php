<?php

use App\Support\RoleDoctrineUiLabels;

$roleDefinitions = is_array($roleDefinitions ?? null) ? $roleDefinitions : [];
$definitionRelations = is_array($definitionRelations ?? null) ? $definitionRelations : [];
$tenantRoles = is_array($tenantRoles ?? null) ? $tenantRoles : [];
$roleRelations = is_array($roleRelations ?? null) ? $roleRelations : [];
$units = is_array($units ?? null) ? $units : [];
$rolePresetMeta = is_array($rolePresetMeta ?? null) ? $rolePresetMeta : [];
$graphJsonUrl = url('back-office/roles-functions/graph.json');
$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');

$defNameBySlug = [];
foreach ($roleDefinitions as $d) {
    $slug = trim((string) ($d['slug'] ?? ''));
    if ($slug !== '') {
        $defNameBySlug[$slug] = trim((string) ($d['name_fr'] ?? '')) ?: $slug;
    }
}
$edgePalette = [];
$edgeLegend = [];
foreach (RoleDoctrineUiLabels::relationTypeValues() as $rv) {
    $edgePalette[$rv] = RoleDoctrineUiLabels::relationTypeChartColor($rv);
    $edgeLegend[] = ['type' => $rv, 'label' => RoleDoctrineUiLabels::relationTypeShort($rv), 'color' => $edgePalette[$rv]];
}
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10 space-y-8">
    <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600" aria-label="Fil d’Ariane">
        <a href="<?= htmlspecialchars(url('back-office/configuration'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-2 py-1 transition hover:bg-slate-100 hover:text-slate-900">Configuration</a>
        <span class="text-slate-400" aria-hidden="true">/</span>
        <span class="rounded-lg bg-white px-2 py-1 text-slate-900 ring-1 ring-slate-200">Rôles & fonctions</span>
    </nav>

    <header class="rounded-2xl border border-blue-100 bg-blue-50/80 p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Cellule S1</p>
        <h1 class="mt-2 text-3xl font-black text-slate-900">Doctrine des fonctions et des droits</h1>
        <p class="mt-2 max-w-4xl text-sm text-slate-700">Module complet de commandement RH/RBAC : création des fonctions de référence, construction des relations hiérarchiques entre rôles du tenant, et liaison avec l’organigramme (groupes / équipes / catégories).</p>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-white/70 p-3 text-center"><p class="text-2xl font-black text-slate-900"><?= count($roleDefinitions) ?></p><p class="text-xs uppercase text-slate-500">Fonctions</p></div>
            <div class="rounded-xl bg-white/70 p-3 text-center"><p class="text-2xl font-black text-slate-900"><?= count($tenantRoles) ?></p><p class="text-xs uppercase text-slate-500">Rôles tenant</p></div>
            <div class="rounded-xl bg-white/70 p-3 text-center"><p class="text-2xl font-black text-slate-900"><?= count($roleRelations) ?></p><p class="text-xs uppercase text-slate-500">Relations actives</p></div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(url('back-office/personnel-job-roles/assignments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-blue-300/70 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-blue-800 transition hover:bg-blue-100">Attributions membres</a>
            <a href="<?= htmlspecialchars(url('back-office/roles-functions/graph.json'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-700 transition hover:bg-slate-100">Exporter la carte des relations</a>
        </div>
    </header>

    <?php if ($success): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error): ?><p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Créer une fonction (référentiel)</h2>
            <p class="mt-1 text-sm text-slate-600">Ajoute une nouvelle fonction doctrinale dans le catalogue global.</p>
            <form method="post" action="<?= url('back-office/roles-functions/definitions/store') ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                <?= \App\Core\Csrf::field() ?>
                <label class="text-xs font-semibold text-slate-600 sm:col-span-1">Référence courte (unique)
                    <input name="slug" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="officier-s1" title="Identifiant stable pour le système ; laissez vide pour le déduire du nom français.">
                </label>
                <label class="text-xs font-semibold text-slate-600 sm:col-span-1">Famille de fonction
                    <input name="family" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="command" title="Regroupement logique (ex. command, hr, training).">
                </label>
                <label class="text-xs font-semibold text-slate-600 sm:col-span-1">Nom FR *
                    <input name="name_fr" type="text" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Officier S1">
                </label>
                <label class="text-xs font-semibold text-slate-600 sm:col-span-1">Nom US
                    <input name="name_us" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="S1 Officer">
                </label>
                <label class="text-xs font-semibold text-slate-600 sm:col-span-2">Description
                    <input name="description" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Responsable RH et gestion administrative de l’unité.">
                </label>
                <label class="text-xs font-semibold text-slate-600 sm:col-span-1">Ordre
                    <input name="sort_order" type="number" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <div class="sm:col-span-2">
                    <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" type="submit">Créer la fonction</button>
                </div>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Créer une relation de commandement</h2>
            <p class="mt-1 text-sm text-slate-600">Définit un lien orienté entre deux rôles de votre tenant.</p>
            <form method="post" action="<?= url('back-office/roles-functions/relations/store') ?>" class="mt-4 grid gap-3">
                <?= \App\Core\Csrf::field() ?>
                <label class="text-xs font-semibold text-slate-600">Rôle source
                    <select name="from_role_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Sélectionner</option>
                        <?php foreach ($tenantRoles as $role): ?>
                            <option value="<?= (int) ($role['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($role['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Nature du lien
                    <select name="relation_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" aria-describedby="rf-relation-type-help">
                        <?php foreach (RoleDoctrineUiLabels::relationSelectRows() as $row): ?>
                            <option value="<?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p id="rf-relation-type-help" class="text-xs text-slate-500 -mt-1">Survolez un intitulé pour lire la définition opérationnelle du lien.</p>
                <label class="text-xs font-semibold text-slate-600">Rôle destination
                    <select name="to_role_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Sélectionner</option>
                        <?php foreach ($tenantRoles as $role): ?>
                            <option value="<?= (int) ($role['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($role['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Créer la relation</button>
                </div>
            </form>
        </article>
    </section>

    <section class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Graphe des rôles du tenant</h2>
            <p class="mt-1 text-sm text-slate-600">Visualisation du maillage de commandement actif.</p>
            <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50/80 p-4 min-h-[220px]" id="roles-graph-host" data-graph-url="<?= htmlspecialchars($graphJsonUrl, ENT_QUOTES, 'UTF-8') ?>" data-edge-palette="<?= htmlspecialchars(json_encode($edgePalette, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                <canvas id="roles-graph-canvas" class="w-full max-h-64 border border-slate-200 rounded-lg bg-white" width="800" height="240"></canvas>
                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-slate-600" aria-label="Légende des types de liens">
                    <?php foreach ($edgeLegend as $leg): ?>
                        <li class="inline-flex items-center gap-2">
                            <span class="inline-block h-0.5 w-7 rounded-full shrink-0" style="background-color: <?= htmlspecialchars($leg['color'], ENT_QUOTES, 'UTF-8') ?>"></span>
                            <span><?= htmlspecialchars($leg['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if ($roleRelations !== []): ?>
                <ul class="mt-4 space-y-1.5 text-xs text-slate-600">
                    <?php foreach ($roleRelations as $rr): ?>
                        <?php $rt = (string) ($rr['relation_type'] ?? ''); ?>
                        <li><?= htmlspecialchars((string) ($rr['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars((string) ($rr['to_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <span class="text-slate-500">(<?= htmlspecialchars(RoleDoctrineUiLabels::relationTypeShort($rt), ENT_QUOTES, 'UTF-8') ?>)</span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Passerelles ORBAT</h2>
            <div class="mt-3 space-y-2">
                <a href="<?= url('back-office/groups') ?>" class="block rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Groupes</a>
                <a href="<?= url('back-office/teams') ?>" class="block rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Équipes</a>
                <a href="<?= url('back-office/categories') ?>" class="block rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Catégories</a>
                <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="block rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100">Attributions membres</a>
            </div>
            <?php if ($units !== []): ?>
                <p class="mt-4 text-xs uppercase text-slate-500">Unités connues</p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($units as $u): ?>
                        <li class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700"><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h2 class="text-lg font-bold text-slate-900">Toile doctrinale du référentiel</h2>
        <p class="text-sm text-slate-600 max-w-3xl">Liens entre <strong>fonctions de référence</strong> (catalogue global). Ils servent de modèle lors de l’amorçage des relations entre rôles du tenant, lorsque les mêmes codes de fonction existent côté organisation.</p>
        <?php if ($definitionRelations === []): ?>
            <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Aucune relation doctrinale n’est encore enregistrée dans le référentiel.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                            <th class="py-2 pr-4">Fonction source</th>
                            <th class="py-2 pr-4">Fonction cible</th>
                            <th class="py-2">Nature du lien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($definitionRelations as $dr): ?>
                            <?php
                            $fs = trim((string) ($dr['from_slug'] ?? ''));
                            $ts = trim((string) ($dr['to_slug'] ?? ''));
                            $drt = (string) ($dr['relation_type'] ?? '');
                            ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-4"><?= htmlspecialchars($defNameBySlug[$fs] ?? $fs, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 pr-4"><?= htmlspecialchars($defNameBySlug[$ts] ?? $ts, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2 text-slate-700"><?= htmlspecialchars(RoleDoctrineUiLabels::relationTypeShort($drt), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h2 class="text-lg font-bold text-slate-900">Catalogue des fonctions</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-semibold text-slate-600">Recherche rapide
                <input id="rf-library-search" type="search" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Référence, nom FR ou US, famille…">
            </label>
            <label class="text-xs font-semibold text-slate-600">Filtrer par famille
                <select id="rf-library-family" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Toutes les familles</option>
                    <?php foreach (array_values(array_unique(array_filter(array_map(static fn(array $d): string => trim((string) ($d['family'] ?? '')), $roleDefinitions)))) as $fam): ?>
                        <option value="<?= htmlspecialchars($fam, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(RoleDoctrineUiLabels::definitionFamilyLabel($fam), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm" id="rf-library-table">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                        <th class="py-2 pr-4">Référence</th>
                        <th class="py-2 pr-4">Nom FR</th>
                        <th class="py-2 pr-4">Nom US</th>
                        <th class="py-2">Famille</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roleDefinitions as $d): ?>
                        <?php $famRaw = trim((string) ($d['family'] ?? '')); ?>
                        <tr class="border-b border-slate-100" data-rf-row data-family="<?= htmlspecialchars($famRaw, ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars(mb_strtolower(trim((string) (($d['slug'] ?? '') . ' ' . ($d['name_fr'] ?? '') . ' ' . ($d['name_us'] ?? '') . ' ' . ($d['family'] ?? '') . ' ' . RoleDoctrineUiLabels::definitionFamilyLabel($famRaw))), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                            <td class="py-2 pr-4 font-mono text-xs"><?= htmlspecialchars((string) ($d['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($d['name_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 pr-4"><?= htmlspecialchars((string) ($d['name_us'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 text-slate-600"><?= htmlspecialchars(RoleDoctrineUiLabels::definitionFamilyLabel($famRaw), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p id="rf-library-empty" class="hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">Aucune entrée ne correspond au filtre courant.</p>
    </section>

    <?php if ($rolePresetMeta !== []): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Profils prêts à l’emploi</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php foreach ($rolePresetMeta as $pm): ?>
                    <li class="rounded-lg border border-slate-200 p-3">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($pm['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-600 mt-1"><?= htmlspecialchars((string) ($pm['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
<script>
(function () {
  var host = document.getElementById('roles-graph-host');
  var canvas = document.getElementById('roles-graph-canvas');
  if (!host || !canvas) return;
  var url = host.getAttribute('data-graph-url');
  if (!url) return;
  var palette = {};
  try {
    palette = JSON.parse(host.getAttribute('data-edge-palette') || '{}') || {};
  } catch (e) {}
  fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (data) {
    var nodes = data.nodes || [];
    var edges = data.edges || [];
    var ctx = canvas.getContext('2d');
    var w = canvas.width;
    var h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    ctx.lineWidth = 1.5;
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
      ctx.strokeStyle = palette[e.type] || '#94a3b8';
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
