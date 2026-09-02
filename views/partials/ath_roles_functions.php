<?php
declare(strict_types=1);

/**
 * Doctrine des fonctions — rendu ATHENA (Cellule S1).
 *
 * Variables attendues depuis admin.organization.roles_functions.
 */

use App\Support\RoleDoctrineUiLabels;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$roleDefinitions = is_array($roleDefinitions ?? null) ? $roleDefinitions : [];
$definitionRelations = is_array($definitionRelations ?? null) ? $definitionRelations : [];
$tenantRoles = is_array($tenantRoles ?? null) ? $tenantRoles : [];
$roleRelations = is_array($roleRelations ?? null) ? $roleRelations : [];
$units = is_array($units ?? null) ? $units : [];
$rolePresetMeta = is_array($rolePresetMeta ?? null) ? $rolePresetMeta : [];
$graphJsonUrl = url('back-office/roles-functions/graph.json');
$requiredRoleDefinitionsFeature = !empty($requiredRoleDefinitionsFeature ?? false);
$requiredDefinitionIds = is_array($requiredDefinitionIds ?? null) ? array_map('intval', $requiredDefinitionIds) : [];
$coverageRows = is_array($coverageRows ?? null) ? $coverageRows : [];
$assignMembers = is_array($assignMembers ?? null) ? $assignMembers : [];
$assignRolesByDefinitionJson = is_string($assignRolesByDefinitionJson ?? null) ? $assignRolesByDefinitionJson : '{}';

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

$defNameBySlug = [];
foreach ($roleDefinitions as $d) {
    $slug = trim((string) ($d['slug'] ?? ''));
    if ($slug !== '') {
        $defNameBySlug[$slug] = trim((string) ($d['name_fr'] ?? '')) ?: $slug;
    }
}

$parentByDefSlug = [];
foreach ($definitionRelations as $dr) {
    if ((string) ($dr['relation_type'] ?? '') !== 'reports_to') {
        continue;
    }
    $fromSlug = trim((string) ($dr['from_slug'] ?? ''));
    $toSlug = trim((string) ($dr['to_slug'] ?? ''));
    if ($fromSlug !== '' && $toSlug !== '') {
        $parentByDefSlug[$fromSlug] = $defNameBySlug[$toSlug] ?? $toSlug;
    }
}

$rolesByDefId = [];
$attachedRolesCount = 0;
foreach ($tenantRoles as $role) {
    $did = (int) ($role['definition_id'] ?? 0);
    if ($did > 0) {
        $rolesByDefId[$did] = ($rolesByDefId[$did] ?? 0) + 1;
        $attachedRolesCount++;
    }
}

$coverageByDefId = [];
$coverageFilled = 0;
foreach ($coverageRows as $cr) {
    $did = (int) ($cr['definition_id'] ?? 0);
    if ($did > 0) {
        $coverageByDefId[$did] = $cr;
    }
    if (!empty($cr['filled'])) {
        $coverageFilled++;
    }
}
$coverageTotal = count($coverageRows);
$requiredCount = count($requiredDefinitionIds);
$uncoveredRequired = max(0, $coverageTotal - $coverageFilled);

$coveragePct = $coverageTotal > 0 ? (int) round($coverageFilled / $coverageTotal * 100) : 0;
$requiredPct = $requiredCount > 0 ? (int) round($coverageFilled / max(1, $coverageTotal) * 100) : 0;
$functionsPct = count($roleDefinitions) > 0 ? min(100, (int) round($attachedRolesCount / max(1, count($roleDefinitions)) * 10)) : 0;
$relationsPct = count($tenantRoles) > 0 ? min(100, (int) round(count($roleRelations) / max(1, count($tenantRoles)) * 100)) : 0;

$athKpis = [
    ['label' => 'FONCTIONS', 'value' => (string) count($roleDefinitions), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'au référentiel'],
    ['label' => 'RÔLES RATTACHÉS', 'value' => (string) $attachedRolesCount, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $functionsPct . '%', 'note' => 'toutes familles'],
    ['label' => 'RELATIONS', 'value' => (string) count($roleRelations), 'delta' => '', 'tone' => '#1e4f80', 'pct' => $relationsPct . '%', 'note' => 'liens de commandement'],
    ['label' => 'FONCTIONS OBLIGATOIRES', 'value' => (string) $requiredCount, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $requiredCount > 0 ? $requiredPct . '%' : '0%', 'note' => $coverageTotal > 0 ? 'couverture ' . $coveragePct . ' %' : 'non configurées'],
    ['label' => 'NON COUVERTES', 'value' => (string) $uncoveredRequired, 'delta' => '', 'tone' => '#c98a12', 'pct' => $coverageTotal > 0 ? (100 - $coveragePct) . '%' : '0%', 'note' => 'à pourvoir'],
];
require base_path('views/partials/ath_kpis.php');

$edgePalette = [];
$edgeLegend = [];
foreach (RoleDoctrineUiLabels::relationTypeValues() as $rv) {
    $edgePalette[$rv] = RoleDoctrineUiLabels::relationTypeChartColor($rv);
    $edgeLegend[] = ['type' => $rv, 'label' => RoleDoctrineUiLabels::relationTypeShort($rv), 'color' => $edgePalette[$rv]];
}

$stateLabel = static function (bool $isRequired, bool $filled, int $linkedRoles): string {
    if (!$isRequired) {
        return 'Référentiel';
    }
    if ($filled) {
        return 'Conforme';
    }
    if ($linkedRoles <= 0) {
        return 'Non couvert';
    }

    return 'Manquant';
};

$athTableRows = [];
$athTableRowHrefs = [];
foreach ($roleDefinitions as $d) {
    $did = (int) ($d['id'] ?? 0);
    $slug = trim((string) ($d['slug'] ?? ''));
    $isRequired = in_array($did, $requiredDefinitionIds, true);
    $linkedRoles = (int) ($rolesByDefId[$did] ?? 0);
    $cr = $coverageByDefId[$did] ?? null;
    $filled = is_array($cr) && !empty($cr['filled']);
    $holders = is_array($cr) ? count($cr['holders'] ?? []) : 0;
    $famLab = RoleDoctrineUiLabels::definitionFamilyLabel(trim((string) ($d['family'] ?? '')));
    $nameUs = trim((string) ($d['name_us'] ?? ''));
    $parentLabel = $slug !== '' ? ($parentByDefSlug[$slug] ?? '—') : '—';
    $coverageLabel = '—';
    if ($isRequired) {
        $coverageLabel = $filled ? '100 %' : '0 %';
    }
    $code = $did > 0 ? 'FN-' . str_pad((string) $did, 3, '0', STR_PAD_LEFT) : '—';

    $athTableRows[] = [
        $code,
        (string) ($d['name_fr'] ?? '—'),
        $nameUs !== '' ? $nameUs : '—',
        $famLab !== '' ? $famLab : '—',
        (string) (int) ($d['sort_order'] ?? 0),
        $parentLabel,
        (string) $linkedRoles,
        (string) ($holders > 0 ? $holders : ($filled ? 1 : 0)),
        $isRequired ? 'Oui' : 'Non',
        'Non',
        $coverageLabel,
        $stateLabel($isRequired, $filled, $linkedRoles),
    ];
    $athTableRowHrefs[] = url('back-office/roles-functions/catalogue');
}

$athTableTitle = 'Référentiel des fonctions';
$athTableCount = count($roleDefinitions);
$athTableCols = [
    'CODE|m', 'FONCTION', 'LIBELLÉ|m', 'CATÉGORIE', 'NIVEAU|r', 'RATTACHÉE À',
    'RÔLES LIÉS|r', 'TITULAIRES|r', 'OBLIG.', 'SUPPLÉANT', 'COUVERTURE|r', 'ÉTAT|b',
];
$athTableFilters = ['Catégorie', 'Obligatoire', 'Couverture'];
$athTableMinWidth = '1620px';
$athTableFoot = count($roleDefinitions) > 0
    ? 'Affichage 1 – ' . count($roleDefinitions) . ' sur ' . count($roleDefinitions) . ' · ' . date('d/m/Y H:i')
    : 'Aucune fonction · ' . date('d/m/Y H:i');
$athTableShowCheckbox = true;
?>

<div class="bo-rf ath-rise">
    <div class="ath-users-filters ath-rise">
        <a href="<?= $h(url('back-office/personnel-job-roles/kits')) ?>" class="ath-btn ath-btn--solid">Kits d’accès</a>
        <a href="<?= $h(url('back-office/roles-functions/referentiel')) ?>" class="ath-btn">Référentiel</a>
        <a href="<?= $h(url('back-office/roles-functions/catalogue')) ?>" class="ath-btn">Catalogue</a>
        <?php if ($requiredRoleDefinitionsFeature): ?>
        <a href="#rf-obligatoires" class="ath-btn">Obligatoires</a>
        <?php endif; ?>
        <a href="#rf-graphe" class="ath-btn">Graphe</a>
        <a href="<?= $h(url('back-office/personnel-job-roles/assignments')) ?>" class="ath-btn">Attributions métier</a>
    </div>

    <?php require base_path('views/partials/ath_table.php'); ?>

    <?php if (!$requiredRoleDefinitionsFeature): ?>
    <aside class="ath-card ath-rise bo-rf__notice" style="padding:16px 18px;margin-top:16px;">
        <p class="bo-rf__notice-title">Fonctions obligatoires</p>
        <p class="bo-rf__notice-text">Une mise à jour de la plateforme est encore nécessaire pour indiquer quelles fonctions doivent être pourvues, suivre la couverture et attribuer rapidement un rôle à un membre depuis cette page.</p>
    </aside>
    <?php else: ?>
    <section id="rf-obligatoires" class="ath-card ath-rise bo-rf__section scroll-mt-24">
        <div class="bo-rf__section-head">
            <p class="bo-rf__section-kicker">Organisation</p>
            <h2 class="ath-section-title">Fonctions obligatoires</h2>
            <p class="ath-body">Cochez les fonctions du référentiel qui doivent être assurées. La couverture indique si au moins un membre actif possède un rôle relié à cette fonction.</p>
        </div>
        <div class="bo-rf__section-body">
            <form method="post" action="<?= $h(url('back-office/roles-functions/required/save')) ?>" class="bo-rf__required-form">
                <?= \App\Core\Csrf::field() ?>
                <div class="bo-rf__check-grid">
                    <?php foreach ($roleDefinitions as $d): ?>
                        <?php $did = (int) ($d['id'] ?? 0); ?>
                        <?php if ($did < 1) {
                            continue;
                        } ?>
                        <?php $famLab = RoleDoctrineUiLabels::definitionFamilyLabel(trim((string) ($d['family'] ?? ''))); ?>
                        <label class="bo-rf__check">
                            <input type="checkbox" name="definition_ids[]" value="<?= $did ?>" <?= in_array($did, $requiredDefinitionIds, true) ? 'checked' : '' ?>>
                            <span class="bo-rf__check-copy">
                                <span class="bo-rf__check-title"><?= $h((string) ($d['name_fr'] ?? '')) ?></span>
                                <?php if ($famLab !== '' && $famLab !== '—'): ?>
                                <span class="bo-rf__check-meta"><?= $h($famLab) ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="bo-rf__form-actions">
                    <button type="submit" class="ath-btn ath-btn--solid">Enregistrer les fonctions obligatoires</button>
                </div>
            </form>

            <?php if ($requiredDefinitionIds !== []): ?>
            <div class="bo-rf__coverage">
                <div class="bo-rf__coverage-head">
                    <div>
                        <h3 class="bo-rf__subsection-title">Couverture</h3>
                        <p class="ath-body">« Pourvue » : au moins un membre actif détient un rôle relié à cette fonction.</p>
                    </div>
                    <p class="bo-rf__coverage-stat"><?= $coverageFilled ?> pourvue<?= $coverageFilled > 1 ? 's' : '' ?> / <?= $coverageTotal ?></p>
                </div>
                <?php
                $athTableRows = [];
                $athTableRowHrefs = [];
                foreach ($coverageRows as $cr) {
                    $filled = !empty($cr['filled']);
                    $holders = is_array($cr['holders'] ?? null) ? $cr['holders'] : [];
                    $rlist = is_array($cr['roles_for_definition'] ?? null) ? $cr['roles_for_definition'] : [];
                    $holderNames = [];
                    foreach ($holders as $holder) {
                        $holderNames[] = trim((string) ($holder['display_name'] ?? '')) ?: 'Membre';
                    }
                    $roleNames = implode(', ', array_map(static fn ($rr) => (string) ($rr['name'] ?? ''), $rlist));
                    if ($roleNames === '') {
                        $roleNames = 'Aucun rôle relié';
                    }
                    $athTableRows[] = [
                        (string) ($cr['name_fr'] ?? '—'),
                        $filled ? 'Pourvue' : 'À pourvoir',
                        $holderNames !== [] ? implode(', ', $holderNames) : '—',
                        $roleNames,
                    ];
                    $hid = isset($holders[0]['user_id']) ? (int) $holders[0]['user_id'] : 0;
                    $athTableRowHrefs[] = $hid > 0 ? url('back-office/users/' . $hid . '/edit') : null;
                }
                $athTableTitle = 'Suivi des postes obligatoires';
                $athTableCount = count($coverageRows);
                $athTableCols = ['FONCTION', 'ÉTAT|b', 'TITULAIRES', 'RÔLES LIÉS'];
                $athTableFilters = ['État'];
                $athTableMinWidth = '960px';
                $athTableShowCheckbox = false;
                $athTableFoot = $coverageTotal > 0
                    ? 'Affichage 1 – ' . $coverageTotal . ' sur ' . $coverageTotal
                    : 'Aucun poste obligatoire';
                require base_path('views/partials/ath_table.php');
                ?>
            </div>

            <div class="bo-rf__assign ath-card" style="padding:16px 18px;margin-top:16px;">
                <h3 class="bo-rf__subsection-title">Attribuer un rôle à un membre</h3>
                <p class="ath-body">Le rôle est <strong>ajouté</strong> à ceux déjà possédés (sans retirer les autres).</p>
                <?php if ($assignMembers === []): ?>
                <p class="ath-body" style="margin-top:10px;">Aucun membre actif trouvé pour la liste.</p>
                <?php else: ?>
                <script type="application/json" id="s1-assign-roles-data"><?= $h($assignRolesByDefinitionJson) ?></script>
                <form method="post" action="<?= $h(url('back-office/roles-functions/quick-assign-role')) ?>" class="bo-rf__assign-form">
                    <?= \App\Core\Csrf::field() ?>
                    <label class="ath-users-filters__label">Membre
                        <select name="user_id" required>
                            <option value="">Choisir</option>
                            <?php foreach ($assignMembers as $m): ?>
                                <?php $mid = (int) ($m['id'] ?? 0); ?>
                                <?php if ($mid < 1) {
                                    continue;
                                } ?>
                                <?php $mlabel = trim((string) ($m['display_name'] ?? '')) !== '' ? trim((string) $m['display_name']) : (string) ($m['email'] ?? ''); ?>
                                <option value="<?= $mid ?>"><?= $h($mlabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ath-users-filters__label">Fonction ciblée
                        <select name="role_definition_id" id="s1-def-pick" required>
                            <option value="">Choisir</option>
                            <?php foreach ($roleDefinitions as $d): ?>
                                <?php $did = (int) ($d['id'] ?? 0); ?>
                                <?php if ($did < 1 || !in_array($did, $requiredDefinitionIds, true)) {
                                    continue;
                                } ?>
                                <option value="<?= $did ?>"><?= $h((string) ($d['name_fr'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ath-users-filters__label">Rôle à ajouter
                        <select name="role_id" id="s1-role-pick">
                            <option value="">Choisir une fonction d’abord</option>
                        </select>
                    </label>
                    <div class="bo-rf__form-actions">
                        <button type="submit" class="ath-btn ath-btn--solid">Ajouter le rôle</button>
                        <p id="s1-no-roles-hint" class="bo-rf__hint hidden">Aucun rôle attribuable n’est relié à cette fonction : créez ou ajustez un rôle dans votre organisation.</p>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section id="rf-creer" class="bo-rf__grid-2 ath-rise scroll-mt-24">
        <article class="ath-card bo-rf__section">
            <div class="bo-rf__section-head">
                <h2 class="ath-section-title">Créer une fonction</h2>
                <p class="ath-body">Ajoute une entrée au référentiel global des fonctions.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/roles-functions/definitions/store')) ?>" class="bo-rf__section-body bo-rf__form-grid">
                <?= \App\Core\Csrf::field() ?>
                <label class="ath-users-filters__label">Nom en français *
                    <input name="name_fr" type="text" required placeholder="Officier S1">
                </label>
                <label class="ath-users-filters__label">Nom en anglais
                    <input name="name_us" type="text" placeholder="S1 Officer">
                </label>
                <label class="ath-users-filters__label">Famille
                    <select name="family">
                        <option value="">Choisir une famille</option>
                        <?php foreach (['command', 'hr', 'training', 'support', 'comms', 'system'] as $famHint): ?>
                        <option value="<?= $h($famHint) ?>"><?= $h(RoleDoctrineUiLabels::definitionFamilyLabel($famHint)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="ath-users-filters__label">Ordre d’affichage
                    <input name="sort_order" type="number" value="0">
                </label>
                <label class="ath-users-filters__label bo-rf__field-span">Description
                    <input name="description" type="text" placeholder="Responsable RH et gestion administrative de l’unité.">
                </label>
                <label class="ath-users-filters__label bo-rf__field-span">Adresse courte <span class="bo-rf__opt">(facultatif)</span>
                    <input name="slug" type="text" placeholder="Laisser vide pour la déduire du nom français">
                    <span class="bo-rf__field-help">Identifiant stable pour le rattachement interne. Laissez vide sauf besoin précis.</span>
                </label>
                <div class="bo-rf__form-actions bo-rf__field-span">
                    <button class="ath-btn ath-btn--solid" type="submit">Créer la fonction</button>
                </div>
            </form>
        </article>

        <article class="ath-card bo-rf__section">
            <div class="bo-rf__section-head">
                <h2 class="ath-section-title">Créer une relation</h2>
                <p class="ath-body">Lien orienté entre deux rôles de votre communauté.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/roles-functions/relations/store')) ?>" class="bo-rf__section-body bo-rf__form-stack">
                <?= \App\Core\Csrf::field() ?>
                <label class="ath-users-filters__label">Rôle source
                    <select name="from_role_id" required>
                        <option value="">Sélectionner</option>
                        <?php foreach ($tenantRoles as $role): ?>
                        <option value="<?= (int) ($role['id'] ?? 0) ?>"><?= $h((string) ($role['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="ath-users-filters__label">Nature du lien
                    <select name="relation_type" aria-describedby="rf-relation-type-help">
                        <?php foreach (RoleDoctrineUiLabels::relationSelectRows() as $row): ?>
                        <option value="<?= $h($row['value']) ?>" title="<?= $h($row['title']) ?>"><?= $h($row['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p id="rf-relation-type-help" class="bo-rf__field-help">Survolez un intitulé pour lire la définition du lien.</p>
                <label class="ath-users-filters__label">Rôle destination
                    <select name="to_role_id" required>
                        <option value="">Sélectionner</option>
                        <?php foreach ($tenantRoles as $role): ?>
                        <option value="<?= (int) ($role['id'] ?? 0) ?>"><?= $h((string) ($role['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="bo-rf__form-actions">
                    <button class="ath-btn ath-btn--solid" type="submit">Créer la relation</button>
                </div>
            </form>
        </article>
    </section>

    <section id="rf-graphe" class="bo-rf__grid-graph ath-rise scroll-mt-24">
        <article class="ath-card bo-rf__section">
            <div class="bo-rf__section-head bo-rf__section-head--row">
                <div>
                    <h2 class="ath-section-title">Carte des rôles</h2>
                    <p class="ath-body">Qui relève de qui, et les liens de tutorat ou de coordination.</p>
                </div>
                <a href="<?= $h($graphJsonUrl) ?>" class="ath-btn">Télécharger la carte</a>
            </div>
            <div class="bo-rf__section-body">
                <div class="bo-rf__graph-host" id="roles-graph-host" data-graph-url="<?= $h($graphJsonUrl) ?>" data-edge-palette="<?= $h(json_encode($edgePalette, JSON_UNESCAPED_UNICODE)) ?>">
                    <div class="bo-rf__graph-stage" aria-live="polite"></div>
                    <ul class="bo-rf__graph-legend" aria-label="Légende des types de liens">
                        <?php foreach ($edgeLegend as $leg): ?>
                        <li data-type="<?= $h((string) ($leg['type'] ?? '')) ?>" style="color: <?= $h($leg['color']) ?>">
                            <span class="bo-rf__graph-legend-line"></span>
                            <span><?= $h($leg['label']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if ($roleRelations !== []): ?>
                    <?php
                    $athTableRows = [];
                    $athTableRowHrefs = [];
                    foreach ($roleRelations as $rr) {
                        $rt = (string) ($rr['relation_type'] ?? '');
                        $athTableRows[] = [
                            (string) ($rr['from_name'] ?? '—'),
                            (string) ($rr['to_name'] ?? '—'),
                            RoleDoctrineUiLabels::relationTypeShort($rt),
                        ];
                        $athTableRowHrefs[] = null;
                    }
                    $athTableTitle = 'Relations actives';
                    $athTableCount = count($roleRelations);
                    $athTableCols = ['SOURCE', 'DESTINATION', 'NATURE'];
                    $athTableFilters = ['Nature'];
                    $athTableMinWidth = '720px';
                    $athTableShowCheckbox = false;
                    $athTableFoot = count($roleRelations) . ' relation' . (count($roleRelations) > 1 ? 's' : '');
                    require base_path('views/partials/ath_table.php');
                    ?>
                <?php else: ?>
                <p class="bo-rf__empty">Aucune relation active pour le moment.</p>
                <?php endif; ?>
            </div>
        </article>

        <article class="ath-card bo-rf__section">
            <div class="bo-rf__section-head">
                <h2 class="ath-section-title">Passerelles organigramme</h2>
                <p class="ath-body">Accès rapides vers la structure.</p>
            </div>
            <div class="bo-rf__section-body bo-rf__links">
                <a href="<?= $h(url('back-office/groups')) ?>" class="ath-btn">Groupes</a>
                <a href="<?= $h(url('back-office/teams')) ?>" class="ath-btn">Équipes</a>
                <a href="<?= $h(url('back-office/categories')) ?>" class="ath-btn">Catégories</a>
                <a href="<?= $h(url('back-office/personnel-job-roles/assignments')) ?>" class="ath-btn ath-btn--solid">Affectations des emplois</a>
            </div>
            <?php if ($units !== []): ?>
            <div class="bo-rf__units">
                <p class="bo-rf__units-label">Unités</p>
                <ul class="bo-rf__units-list">
                    <?php foreach ($units as $u): ?>
                    <li><?= $h((string) ($u['name'] ?? '')) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </article>
    </section>

    <?php if ($rolePresetMeta !== []): ?>
    <section id="rf-profils" class="ath-card ath-rise bo-rf__section scroll-mt-24">
        <div class="bo-rf__section-head">
            <h2 class="ath-section-title">Profils prêts à l’emploi</h2>
            <p class="ath-body">Modèles d’habilitations proposés pour accélérer la configuration.</p>
        </div>
        <ul class="bo-rf__preset-grid">
            <?php foreach ($rolePresetMeta as $pm): ?>
            <li class="bo-rf__preset">
                <p class="bo-rf__preset-title"><?= $h((string) ($pm['label'] ?? '')) ?></p>
                <p class="bo-rf__preset-desc"><?= $h((string) ($pm['description'] ?? '')) ?></p>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</div>

<script src="<?= $h(asset_url('assets/js/roles-graph.js')) ?>?v=<?= $h(platform_app_version()) ?>"></script>
<script>
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
