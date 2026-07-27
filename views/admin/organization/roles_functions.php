<?php

use App\Support\RoleDoctrineUiLabels;

$roleDefinitions = is_array($roleDefinitions ?? null) ? $roleDefinitions : [];
$definitionRelations = is_array($definitionRelations ?? null) ? $definitionRelations : [];
$tenantRoles = is_array($tenantRoles ?? null) ? $tenantRoles : [];
$roleRelations = is_array($roleRelations ?? null) ? $roleRelations : [];
$units = is_array($units ?? null) ? $units : [];
$rolePresetMeta = is_array($rolePresetMeta ?? null) ? $rolePresetMeta : [];
$graphJsonUrl = url('back-office/roles-functions/graph.json');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
if ($success === null || $success === '') {
    $success = \App\Core\Session::get('success');
    \App\Core\Session::forget('success');
}
if ($error === null || $error === '') {
    $error = \App\Core\Session::get('error');
    \App\Core\Session::forget('error');
}
$requiredRoleDefinitionsFeature = !empty($requiredRoleDefinitionsFeature ?? false);
$requiredDefinitionIds = is_array($requiredDefinitionIds ?? null) ? array_map('intval', $requiredDefinitionIds) : [];
$coverageRows = is_array($coverageRows ?? null) ? $coverageRows : [];
$assignMembers = is_array($assignMembers ?? null) ? $assignMembers : [];
$assignRolesByDefinitionJson = is_string($assignRolesByDefinitionJson ?? null) ? $assignRolesByDefinitionJson : '{}';

$defNameBySlug = [];
foreach ($roleDefinitions as $d) {
    $slug = trim((string) ($d['slug'] ?? ''));
    if ($slug !== '') {
        $defNameBySlug[$slug] = trim((string) ($d['name_fr'] ?? '')) ?: $slug;
    }
}

$coverageFilled = 0;
foreach ($coverageRows as $cr) {
    if (!empty($cr['filled'])) {
        $coverageFilled++;
    }
}
$coverageTotal = count($coverageRows);
$families = array_values(array_unique(array_filter(array_map(
    static fn (array $d): string => trim((string) ($d['family'] ?? '')),
    $roleDefinitions
))));

$edgePalette = [];
$edgeLegend = [];
foreach (RoleDoctrineUiLabels::relationTypeValues() as $rv) {
    $edgePalette[$rv] = RoleDoctrineUiLabels::relationTypeChartColor($rv);
    $edgeLegend[] = ['type' => $rv, 'label' => RoleDoctrineUiLabels::relationTypeShort($rv), 'color' => $edgePalette[$rv]];
}

$navSections = [
    'rf-obligatoires' => 'Obligatoires',
    'rf-creer' => 'Créer',
    'rf-graphe' => 'Graphe',
];
if ($rolePresetMeta !== []) {
    $navSections['rf-profils'] = 'Profils';
}
if (!$requiredRoleDefinitionsFeature) {
    unset($navSections['rf-obligatoires']);
}
?>
<?php if (!empty($isBackOfficeShell)): ?>
<?php require base_path('views/partials/ath_roles_functions.php'); return; ?>
<?php endif; ?>
<style>
.rf-sheet { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
.rf-sheet thead th {
    position: sticky; top: 0; z-index: 1;
    background: #0f172a; color: #f8fafc;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
    text-align: left; padding: 0.7rem 0.85rem; white-space: nowrap;
}
.rf-sheet thead th.num { text-align: right; }
.rf-sheet tbody td {
    padding: 0.75rem 0.85rem; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9;
    background: #fff; color: #0f172a;
}
.rf-sheet tbody td:last-child { border-right: none; }
.rf-sheet tbody tr:nth-child(even) td { background: #f8fafc; }
.rf-sheet tbody tr:hover td { background: #ecfdf5; }
.rf-sheet tbody tr:last-child td { border-bottom: none; }
.rf-sheet .num { text-align: right; font-variant-numeric: tabular-nums; }
.rf-check:has(input:checked) {
    border-color: #059669 !important;
    background: #ecfdf5 !important;
}
</style>
<div class="min-h-0 flex-1 bg-slate-50">
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 lg:py-12 space-y-8">

    <header class="rf-doctrine__hero relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-emerald-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-5 sm:px-8 py-7 lg:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Back-office · Cellule S1</p>
                <h1 class="mt-2 text-2xl lg:text-3xl font-black tracking-tight text-slate-900">Doctrine des fonctions</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                    Référentiel des fonctions, relations de commandement entre les rôles de votre communauté, et suivi des postes qui doivent être pourvus.
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="<?= htmlspecialchars(url('back-office/roles-functions/referentiel'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Référentiel</a>
                    <a href="<?= htmlspecialchars(url('back-office/roles-functions/catalogue'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-900 shadow-sm hover:bg-emerald-50">Catalogue</a>
                    <a href="<?= htmlspecialchars(url('back-office/personnel-job-roles'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Emplois &amp; missions</a>
                    <a href="<?= htmlspecialchars(url('back-office/personnel-job-roles/assignments'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Affectations</a>
                    <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Membres</a>
                    <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour</a>
                </div>
            </div>
            <div class="shrink-0 w-full lg:w-72 rounded-xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Aperçu</p>
                <dl class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Fonctions</dt>
                        <dd class="mt-0.5 text-2xl font-black tabular-nums text-slate-900"><?= count($roleDefinitions) ?></dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Rôles</dt>
                        <dd class="mt-0.5 text-2xl font-black tabular-nums text-slate-900"><?= count($tenantRoles) ?></dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-emerald-700/80">Relations</dt>
                        <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-800"><?= count($roleRelations) ?></dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-amber-700/80">Couverture</dt>
                        <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-800">
                            <?php if ($coverageTotal > 0): ?>
                                <?= $coverageFilled ?>/<?= $coverageTotal ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </header>

    <?php if (!empty($isBackOfficeShell)): ?>
    <?php
    $athKpis = [
        ['label' => 'FONCTIONS', 'value' => (string) count($roleDefinitions), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'référentiel'],
        ['label' => 'RÔLES', 'value' => (string) count($tenantRoles), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '—', 'note' => 'communauté'],
        ['label' => 'RELATIONS', 'value' => (string) count($roleRelations), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '—', 'note' => 'graphe'],
        ['label' => 'COUVERTURE', 'value' => $coverageTotal > 0 ? $coverageFilled . '/' . $coverageTotal : '—', 'delta' => '', 'tone' => '#c98a12', 'pct' => $coverageTotal > 0 ? (int) round($coverageFilled / $coverageTotal * 100) . '%' : '0%', 'note' => 'postes pourvus'],
    ];
    require base_path('views/partials/ath_kpis.php');
    ?>
    <div class="flex flex-wrap gap-2 ath-rise">
        <a href="<?= htmlspecialchars(url('back-office/roles-functions/referentiel'), ENT_QUOTES, 'UTF-8') ?>" class="ath-btn ath-btn--solid">Référentiel</a>
        <a href="<?= htmlspecialchars(url('back-office/roles-functions/catalogue'), ENT_QUOTES, 'UTF-8') ?>" class="ath-btn">Catalogue</a>
        <a href="<?= htmlspecialchars(url('back-office/personnel-job-roles'), ENT_QUOTES, 'UTF-8') ?>" class="ath-btn">Emplois &amp; missions</a>
        <a href="<?= htmlspecialchars(url('back-office/personnel-job-roles/assignments'), ENT_QUOTES, 'UTF-8') ?>" class="ath-btn">Affectations</a>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <nav class="sticky top-0 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 border-y border-slate-200/90 bg-slate-50/95 backdrop-blur-md shadow-sm" aria-label="Sections de la page">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($navSections as $nid => $nlabel): ?>
                <a href="#<?= htmlspecialchars($nid, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs sm:text-sm font-bold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-slate-900"><?= htmlspecialchars($nlabel, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
    </nav>

    <?php if (!$requiredRoleDefinitionsFeature): ?>
        <aside class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 text-sm text-amber-950 shadow-sm">
            <p class="font-bold">Fonctions obligatoires</p>
            <p class="mt-2 leading-relaxed">Une mise à jour de la plateforme est encore nécessaire pour indiquer quelles fonctions doivent être pourvues, suivre la couverture et attribuer rapidement un rôle à un membre depuis cette page.</p>
        </aside>
    <?php else: ?>
        <section id="rf-obligatoires" class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm space-y-0" aria-labelledby="s1-required-heading">
            <div class="border-b border-emerald-100 bg-gradient-to-r from-emerald-50/90 to-white px-5 sm:px-6 py-5">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800/80">Organisation</p>
                <h2 id="s1-required-heading" class="mt-1 text-lg font-black text-slate-900">Fonctions obligatoires</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">Cochez les fonctions du référentiel qui doivent être assurées. La couverture indique si au moins un membre actif possède un rôle relié à cette fonction.</p>
            </div>

            <div class="p-5 sm:p-6 space-y-6">
                <form method="post" action="<?= htmlspecialchars(url('back-office/roles-functions/required/save'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/40 p-3 grid gap-2 sm:grid-cols-2">
                        <?php foreach ($roleDefinitions as $d): ?>
                            <?php $did = (int) ($d['id'] ?? 0); ?>
                            <?php if ($did < 1) {
                                continue;
                            } ?>
                            <?php $famLab = RoleDoctrineUiLabels::definitionFamilyLabel(trim((string) ($d['family'] ?? ''))); ?>
                            <label class="rf-check flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm transition hover:border-emerald-300">
                                <input type="checkbox" name="definition_ids[]" value="<?= $did ?>" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= in_array($did, $requiredDefinitionIds, true) ? 'checked' : '' ?>>
                                <span class="min-w-0 text-sm">
                                    <span class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($d['name_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($famLab !== '' && $famLab !== '—'): ?>
                                        <span class="mt-0.5 block text-[11px] font-medium text-slate-500"><?= htmlspecialchars($famLab, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Enregistrer les fonctions obligatoires</button>
                </form>

                <?php if ($requiredDefinitionIds !== []): ?>
                    <div>
                        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Couverture</h3>
                                <p class="mt-0.5 text-xs text-slate-500">« Pourvue » : au moins un membre actif détient un rôle relié à cette fonction.</p>
                            </div>
                            <p class="text-xs font-bold tabular-nums text-slate-600"><?= $coverageFilled ?> pourvue<?= $coverageFilled > 1 ? 's' : '' ?> / <?= $coverageTotal ?></p>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="rf-sheet min-w-[48rem]">
                                <thead>
                                    <tr>
                                        <th>Fonction</th>
                                        <th>État</th>
                                        <th>Titulaires</th>
                                        <th>Rôles liés</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coverageRows as $cr): ?>
                                        <?php
                                        $filled = !empty($cr['filled']);
                                        $holders = is_array($cr['holders'] ?? null) ? $cr['holders'] : [];
                                        $rlist = is_array($cr['roles_for_definition'] ?? null) ? $cr['roles_for_definition'] : [];
                                        ?>
                                        <tr>
                                            <td class="font-semibold"><?= htmlspecialchars((string) ($cr['name_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if ($filled): ?>
                                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-900 ring-1 ring-inset ring-emerald-200">Pourvue</span>
                                                <?php else: ?>
                                                    <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-950 ring-1 ring-inset ring-amber-200">À pourvoir</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($holders === []): ?>
                                                    <span class="text-slate-400">—</span>
                                                <?php else: ?>
                                                    <ul class="space-y-1">
                                                        <?php foreach ($holders as $h): ?>
                                                            <?php $hid = (int) ($h['user_id'] ?? 0); ?>
                                                            <li>
                                                                <a href="<?= htmlspecialchars(url('back-office/users/' . $hid . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-emerald-800 hover:underline"><?= htmlspecialchars((string) ($h['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-slate-600">
                                                <?php if ($rlist === []): ?>
                                                    <span class="text-xs font-medium text-amber-800">Aucun rôle relié — rattachez un rôle de l’organisation à cette fonction.</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(implode(', ', array_map(static fn ($rr) => (string) ($rr['name'] ?? ''), $rlist)), ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                    <h3 class="text-base font-black text-slate-900">Attribuer un rôle à un membre</h3>
                    <p class="mt-1 text-sm text-slate-600">Le rôle est <strong>ajouté</strong> à ceux déjà possédés (sans retirer les autres).</p>
                    <?php if ($requiredDefinitionIds === []): ?>
                        <p class="mt-3 text-sm text-slate-600">Cochez au moins une fonction obligatoire ci-dessus pour activer cette attribution.</p>
                    <?php elseif ($assignMembers === []): ?>
                        <p class="mt-3 text-sm text-slate-600">Aucun membre actif trouvé pour la liste.</p>
                    <?php else: ?>
                        <script type="application/json" id="s1-assign-roles-data"><?= htmlspecialchars($assignRolesByDefinitionJson, ENT_QUOTES, 'UTF-8') ?></script>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/roles-functions/quick-assign-role'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <?= \App\Core\Csrf::field() ?>
                            <label class="text-xs font-semibold text-slate-700 sm:col-span-2 lg:col-span-1">Membre
                                <select name="user_id" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                                    <option value="">Choisir</option>
                                    <?php foreach ($assignMembers as $m): ?>
                                        <?php $mid = (int) ($m['id'] ?? 0); ?>
                                        <?php if ($mid < 1) {
                                            continue;
                                        } ?>
                                        <?php $mlabel = trim((string) ($m['display_name'] ?? '')) !== '' ? trim((string) $m['display_name']) : (string) ($m['email'] ?? ''); ?>
                                        <option value="<?= $mid ?>"><?= htmlspecialchars($mlabel, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="text-xs font-semibold text-slate-700 sm:col-span-2 lg:col-span-1">Fonction ciblée
                                <select name="role_definition_id" id="s1-def-pick" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                                    <option value="">Choisir</option>
                                    <?php foreach ($roleDefinitions as $d): ?>
                                        <?php $did = (int) ($d['id'] ?? 0); ?>
                                        <?php if ($did < 1 || !in_array($did, $requiredDefinitionIds, true)) {
                                            continue;
                                        } ?>
                                        <option value="<?= $did ?>"><?= htmlspecialchars((string) ($d['name_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="text-xs font-semibold text-slate-700 sm:col-span-2 lg:col-span-2">Rôle à ajouter
                                <select name="role_id" id="s1-role-pick" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                                    <option value="">Choisir une fonction d’abord</option>
                                </select>
                            </label>
                            <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap items-center gap-3">
                                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Ajouter le rôle</button>
                                <p id="s1-no-roles-hint" class="hidden text-xs text-amber-900">Aucun rôle attribuable n’est relié à cette fonction : créez ou ajustez un rôle dans votre organisation.</p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section id="rf-creer" class="scroll-mt-28 grid gap-4 lg:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <h2 class="text-base font-black text-slate-900">Créer une fonction</h2>
                <p class="mt-1 text-sm text-slate-600">Ajoute une entrée au référentiel global des fonctions.</p>
            </div>
            <form method="post" action="<?= url('back-office/roles-functions/definitions/store') ?>" class="p-5 grid gap-3 sm:grid-cols-2">
                <?= \App\Core\Csrf::field() ?>
                <label class="text-xs font-semibold text-slate-700 sm:col-span-1">Nom en français *
                    <input name="name_fr" type="text" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none" placeholder="Officier S1">
                </label>
                <label class="text-xs font-semibold text-slate-700 sm:col-span-1">Nom en anglais
                    <input name="name_us" type="text" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none" placeholder="S1 Officer">
                </label>
                <label class="text-xs font-semibold text-slate-700 sm:col-span-1">Famille
                    <input name="family" type="text" list="rf-family-suggestions" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none" placeholder="Ex. commandement, formation…">
                    <datalist id="rf-family-suggestions">
                        <?php foreach (['command', 'hr', 'training', 'support', 'comms', 'system'] as $famHint): ?>
                            <option value="<?= htmlspecialchars($famHint, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(RoleDoctrineUiLabels::definitionFamilyLabel($famHint), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>
                <label class="text-xs font-semibold text-slate-700 sm:col-span-1">Ordre d’affichage
                    <input name="sort_order" type="number" value="0" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                </label>
                <label class="text-xs font-semibold text-slate-700 sm:col-span-2">Description
                    <input name="description" type="text" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none" placeholder="Responsable RH et gestion administrative de l’unité.">
                </label>
                <label class="text-xs font-semibold text-slate-700 sm:col-span-2">Adresse courte <span class="font-normal text-slate-500">(facultatif)</span>
                    <input name="slug" type="text" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none" placeholder="Laisser vide pour la déduire du nom français">
                    <span class="mt-1 block text-[11px] font-normal text-slate-500">Identifiant stable pour le rattachement interne. Laissez vide sauf besoin précis.</span>
                </label>
                <div class="sm:col-span-2 pt-1">
                    <button class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700" type="submit">Créer la fonction</button>
                </div>
            </form>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <h2 class="text-base font-black text-slate-900">Créer une relation</h2>
                <p class="mt-1 text-sm text-slate-600">Lien orienté entre deux rôles de votre communauté.</p>
            </div>
            <form method="post" action="<?= url('back-office/roles-functions/relations/store') ?>" class="p-5 grid gap-3">
                <?= \App\Core\Csrf::field() ?>
                <label class="text-xs font-semibold text-slate-700">Rôle source
                    <select name="from_role_id" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                        <option value="">Sélectionner</option>
                        <?php foreach ($tenantRoles as $role): ?>
                            <option value="<?= (int) ($role['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-700">Nature du lien
                    <select name="relation_type" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none" aria-describedby="rf-relation-type-help">
                        <?php foreach (RoleDoctrineUiLabels::relationSelectRows() as $row): ?>
                            <option value="<?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p id="rf-relation-type-help" class="text-xs text-slate-500 -mt-1">Survolez un intitulé pour lire la définition du lien.</p>
                <label class="text-xs font-semibold text-slate-700">Rôle destination
                    <select name="to_role_id" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                        <option value="">Sélectionner</option>
                        <?php foreach ($tenantRoles as $role): ?>
                            <option value="<?= (int) ($role['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="pt-1">
                    <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800" type="submit">Créer la relation</button>
                </div>
            </form>
        </article>
    </section>

    <section id="rf-graphe" class="scroll-mt-28 grid gap-4 lg:grid-cols-[1.55fr_1fr]">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <div>
                    <h2 class="text-base font-black text-slate-900">Graphe des rôles</h2>
                    <p class="mt-1 text-sm text-slate-600">Maillage de commandement actif dans votre communauté.</p>
                </div>
                <a href="<?= htmlspecialchars($graphJsonUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-700 shadow-sm hover:bg-slate-50">Télécharger la carte</a>
            </div>
            <div class="p-5">
                <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4 min-h-[220px]" id="roles-graph-host" data-graph-url="<?= htmlspecialchars($graphJsonUrl, ENT_QUOTES, 'UTF-8') ?>" data-edge-palette="<?= htmlspecialchars(json_encode($edgePalette, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
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
                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="rf-sheet min-w-[36rem]">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Destination</th>
                                    <th>Nature</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roleRelations as $rr): ?>
                                    <?php $rt = (string) ($rr['relation_type'] ?? ''); ?>
                                    <tr>
                                        <td class="font-semibold"><?= htmlspecialchars((string) ($rr['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($rr['to_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-slate-600"><?= htmlspecialchars(RoleDoctrineUiLabels::relationTypeShort($rt), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Aucune relation active pour le moment.</p>
                <?php endif; ?>
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <h2 class="text-base font-black text-slate-900">Passerelles organigramme</h2>
                <p class="mt-1 text-sm text-slate-600">Accès rapides vers la structure.</p>
            </div>
            <div class="p-5 space-y-2">
                <a href="<?= url('back-office/groups') ?>" class="block rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-semibold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/50">Groupes</a>
                <a href="<?= url('back-office/teams') ?>" class="block rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-semibold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/50">Équipes</a>
                <a href="<?= url('back-office/categories') ?>" class="block rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-semibold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50/50">Catégories</a>
                <a href="<?= url('back-office/personnel-job-roles/assignments') ?>" class="block rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm font-semibold text-emerald-950 hover:bg-emerald-100">Affectations des emplois</a>
            </div>
            <?php if ($units !== []): ?>
                <div class="border-t border-slate-100 px-5 py-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400 mb-2">Unités</p>
                    <ul class="flex flex-wrap gap-2">
                        <?php foreach ($units as $u): ?>
                            <li class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-700"><?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="grid gap-4 sm:grid-cols-2">
        <a href="<?= htmlspecialchars(url('back-office/roles-functions/referentiel'), ENT_QUOTES, 'UTF-8') ?>" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800/80">Tableur</p>
            <h2 class="mt-2 text-lg font-black text-slate-900 group-hover:text-emerald-900">Référentiel des fonctions</h2>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= count($definitionRelations) ?> lien<?= count($definitionRelations) > 1 ? 's' : '' ?> entre fonctions — page dédiée, pleine largeur.</p>
            <span class="mt-4 inline-flex text-sm font-bold text-emerald-700">Ouvrir le tableur →</span>
        </a>
        <a href="<?= htmlspecialchars(url('back-office/roles-functions/catalogue'), ENT_QUOTES, 'UTF-8') ?>" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800/80">Tableur</p>
            <h2 class="mt-2 text-lg font-black text-slate-900 group-hover:text-emerald-900">Catalogue des fonctions</h2>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= count($roleDefinitions) ?> fonction<?= count($roleDefinitions) > 1 ? 's' : '' ?> de référence — page dédiée, pleine largeur, avec filtres.</p>
            <span class="mt-4 inline-flex text-sm font-bold text-emerald-700">Ouvrir le tableur →</span>
        </a>
    </section>

    <?php if ($rolePresetMeta !== []): ?>
        <section id="rf-profils" class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4">
                <h2 class="text-base font-black text-slate-900">Profils prêts à l’emploi</h2>
                <p class="mt-1 text-sm text-slate-600">Modèles d’habilitations proposés pour accélérer la configuration.</p>
            </div>
            <ul class="grid gap-3 p-5 sm:p-6 sm:grid-cols-2">
                <?php foreach ($rolePresetMeta as $pm): ?>
                    <li class="rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm">
                        <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($pm['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1.5 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars((string) ($pm['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
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
      ctx.fillStyle = '#059669';
      ctx.fill();
      ctx.fillStyle = '#334155';
      ctx.fillText((n.label || n.slug || '').slice(0, 24), p.x + 10, p.y + 4);
    });
  }).catch(function () {});
})();

(function () {
  var dataEl = document.getElementById('s1-assign-roles-data');
  var defSel = document.getElementById('s1-def-pick');
  var roleSel = document.getElementById('s1-role-pick');
  var hint = document.getElementById('s1-no-roles-hint');
  if (!dataEl || !defSel || !roleSel) return;
  var map = {};
  try {
    map = JSON.parse(dataEl.textContent || '{}') || {};
  } catch (e) {}
  function refill() {
    var did = String(defSel.value || '');
    roleSel.innerHTML = '';
    var opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = did ? 'Choisir un rôle' : 'Choisir une fonction d’abord';
    roleSel.appendChild(opt0);
    var list = map[did] || [];
    list.forEach(function (r) {
      var o = document.createElement('option');
      o.value = String(r.id);
      o.textContent = r.name || 'Rôle';
      roleSel.appendChild(o);
    });
    if (hint) hint.classList.toggle('hidden', !(did && list.length === 0));
  }
  defSel.addEventListener('change', refill);
})();
</script>
