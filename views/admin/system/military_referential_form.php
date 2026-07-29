<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $militaryUnit */
$unit = $militaryUnit ?? null;
$isEdit = is_array($unit);
$id = $isEdit ? (int) ($unit['id'] ?? 0) : 0;
$countries = $militaryCountries ?? [];
$services = $militaryServices ?? [];
$types = $militaryEntityTypes ?? [];
$parents = $militaryParents ?? [];
$functions = $militaryFunctions ?? [];
$specialties = $militarySpecialties ?? [];
$domains = $militaryDomains ?? [];
$classifications = $militaryClassifications ?? [];
$sources = $militarySources ?? [];
$aliases = $unitAliases ?? [];
$fnIds = $unitFunctionIds ?? [];
$spIds = $unitSpecialtyIds ?? [];
$domIds = $unitDomainIds ?? [];
$clIds = $unitClassificationIds ?? [];
$unitSources = $unitSources ?? [];
$hierarchy = $hierarchyLabels ?? [];
$formAction = $formAction ?? url('admin/system/military-referential');

$aliasTypeLabels = [
    'SHORT_NAME' => 'Titre abrégé',
    'ACRONYM' => 'Acronyme',
    'COMMON_NAME' => 'Titre usuel',
    'FORMER_NAME' => 'Ancienne appellation',
    'ENGLISH_NAME' => 'Traduction anglaise',
    'FRENCH_NAME' => 'Traduction française',
    'NICKNAME' => 'Surnom',
    'CODE_NAME' => 'Nom de code',
    'ALTERNATIVE_SPELLING' => 'Variante orthographique',
];
$infoTypeLabels = [
    'IDENTITY' => 'Identité',
    'HIERARCHY' => 'Rattachement',
    'MISSION' => 'Mission',
    'FUNCTION' => 'Fonction',
    'SPECIALTY' => 'Spécialité',
    'HISTORY' => 'Historique',
    'STATUS' => 'Statut',
];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900"><?= $isEdit ? 'Modifier l’entité' : 'Nouvelle entité' ?></h1>
            <?php if ($hierarchy !== []): ?>
                <p class="text-sm text-slate-600 mt-2"><?= htmlspecialchars(implode(' → ', $hierarchy), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <a href="<?= url('admin/system/military-referential') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour à la liste</a>
    </div>

    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="space-y-8 bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-4">Identité</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="official_name">Titre officiel</label>
                    <input id="official_name" name="official_name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['official_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="display_name">Titre affiché</label>
                    <input id="display_name" name="display_name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="short_name">Titre abrégé</label>
                    <input id="short_name" name="short_name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['short_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="international_name">Traduction internationale</label>
                    <input id="international_name" name="international_name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['international_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="code">Code stable</label>
                    <input id="code" name="code" required <?= $isEdit ? 'readonly' : '' ?> class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm <?= $isEdit ? 'bg-slate-50' : '' ?>" value="<?= htmlspecialchars((string) ($unit['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="ex. fr-cdo-hubert">
                    <p class="text-xs text-slate-500 mt-1">Identifiant technique unique, utilisé pour conserver les affiliations existantes.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="slug">Adresse courte</label>
                    <input id="slug" name="slug" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-4">Organisation</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="country_id">Pays</label>
                    <select id="country_id" name="country_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($unit['country_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $c['name_fr'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="service_id">Armée / service</label>
                    <select id="service_id" name="service_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        <?php foreach ($services as $sRow): ?>
                            <option value="<?= (int) $sRow['id'] ?>" <?= (int) ($unit['service_id'] ?? 0) === (int) $sRow['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($sRow['country_iso2'] ?? '') . ' — ' . ($sRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="entity_type_id">Nature organisationnelle</label>
                    <select id="entity_type_id" name="entity_type_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (int) ($unit['entity_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $t['label_fr'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="parent_id">Rattachement (parent)</label>
                    <select id="parent_id" name="parent_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Aucun (racine)</option>
                        <?php foreach ($parents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($unit['parent_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) (($p['country_iso2'] ?? '') . ' — ' . ($p['display_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="sort_order">Ordre d’affichage</label>
                    <input type="number" id="sort_order" name="sort_order" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= (int) ($unit['sort_order'] ?? 0) ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="status">Statut</label>
                    <select id="status" name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach (['active' => 'Actif', 'inactive' => 'Inactif', 'dissolved' => 'Dissous'] as $val => $lab): ?>
                            <option value="<?= $val ?>" <?= ($unit['status'] ?? 'active') === $val ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="checkbox" id="active" name="active" value="1" <?= !isset($unit['active']) || !empty($unit['active']) ? 'checked' : '' ?>>
                    <label for="active" class="text-sm text-slate-700">Visible dans les listes d’affiliation</label>
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="checkbox" id="mark_verified" name="mark_verified" value="1">
                    <label for="mark_verified" class="text-sm text-slate-700">Marquer comme vérifié aujourd’hui</label>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="founded_at">Date de création</label>
                    <input type="date" id="founded_at" name="founded_at" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['founded_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="dissolved_at">Date de dissolution</label>
                    <input type="date" id="dissolved_at" name="dissolved_at" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['dissolved_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-4">Description</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="description_short">Résumé</label>
                    <textarea id="description_short" name="description_short" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($unit['description_short'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="mission_summary">Mission</label>
                    <textarea id="mission_summary" name="mission_summary" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($unit['mission_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1" for="official_website">Site officiel</label>
                    <input id="official_website" name="official_website" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($unit['official_website'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">Fonctions</h2>
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2">
                <?php foreach ($functions as $f): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="function_ids[]" value="<?= (int) $f['id'] ?>" <?= in_array((int) $f['id'], $fnIds, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars((string) $f['label_fr'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">Spécialités</h2>
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2">
                <?php foreach ($specialties as $sp): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="specialty_ids[]" value="<?= (int) $sp['id'] ?>" <?= in_array((int) $sp['id'], $spIds, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars((string) $sp['label_fr'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">Domaines opérationnels</h2>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($domains as $d): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="domain_ids[]" value="<?= (int) $d['id'] ?>" <?= in_array((int) $d['id'], $domIds, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars((string) $d['label_fr'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">Place dans l’écosystème SOF</h2>
            <div class="grid sm:grid-cols-2 gap-2">
                <?php foreach ($classifications as $cl): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="classification_ids[]" value="<?= (int) $cl['id'] ?>" <?= in_array((int) $cl['id'], $clIds, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars((string) $cl['label_fr'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-slate-500 mt-2">« Élite conventionnelle » n’est pas synonyme de forces spéciales.</p>
        </section>

        <div class="pt-2">
            <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-emerald-600 transition-colors">
                Enregistrer
            </button>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <section class="mt-10 bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Alias</h2>
            <?php if ($aliases === []): ?>
                <p class="text-sm text-slate-500 mb-4">Aucun alias.</p>
            <?php else: ?>
                <ul class="space-y-2 mb-4">
                    <?php foreach ($aliases as $a): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 text-sm border-b border-slate-100 pb-2">
                            <span>
                                <strong><?= htmlspecialchars((string) $a['alias'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span class="text-slate-500">— <?= htmlspecialchars($aliasTypeLabels[(string) ($a['alias_type'] ?? '')] ?? (string) ($a['alias_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <form method="post" action="<?= url('admin/system/military-referential/' . $id . '/aliases/' . (int) $a['id'] . '/delete') ?>" onsubmit="return confirm('Retirer cet alias ?');">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="text-rose-700 font-semibold text-xs">Retirer</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('admin/system/military-referential/' . $id . '/aliases') ?>" class="grid sm:grid-cols-4 gap-3 items-end">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="alias">Nouvel alias</label>
                    <input id="alias" name="alias" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="alias_type">Type</label>
                    <select id="alias_type" name="alias_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($aliasTypeLabels as $val => $lab): ?>
                            <option value="<?= $val ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-slate-800 text-white text-sm font-semibold px-4 py-2">Ajouter</button>
            </form>
        </section>

        <section class="mt-6 bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Sources</h2>
            <?php if ($unitSources === []): ?>
                <p class="text-sm text-slate-500 mb-4">Aucune source associée.</p>
            <?php else: ?>
                <ul class="space-y-2 mb-4 text-sm">
                    <?php foreach ($unitSources as $us): ?>
                        <li class="border-b border-slate-100 pb-2">
                            <strong><?= htmlspecialchars((string) ($us['source_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="text-slate-500">— <?= htmlspecialchars($infoTypeLabels[(string) ($us['information_type'] ?? '')] ?? (string) ($us['information_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="<?= url('admin/system/military-referential/' . $id . '/sources') ?>" class="grid sm:grid-cols-2 gap-3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="source_id">Source existante</label>
                    <select id="source_id" name="source_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Créer ci-dessous —</option>
                        <?php foreach ($sources as $src): ?>
                            <option value="<?= (int) $src['id'] ?>"><?= htmlspecialchars((string) $src['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="information_type">Type d’information</label>
                    <select id="information_type" name="information_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($infoTypeLabels as $val => $lab): ?>
                            <option value="<?= $val ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="new_source_name">Ou nouvelle source — nom</label>
                    <input id="new_source_name" name="new_source_name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="new_source_url">Adresse web de la source</label>
                    <input id="new_source_url" name="new_source_url" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-lg bg-slate-800 text-white text-sm font-semibold px-4 py-2">Associer la source</button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>
