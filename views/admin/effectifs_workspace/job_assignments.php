<?php
declare(strict_types=1);
/**
 * Onglet « Qui tient quel emploi » (Effectifs › Emplois & fonctions).
 * Données : JobAssignmentBoardController::boardData (confidentialité déjà appliquée par JobAssignmentBoardLoader).
 * Inclus par fonctions.php : $base, $csrf et $canManage y sont définis.
 */
use App\Services\Personnel\JobAssignmentBoard;
use App\Support\JobRoleBadge;

$jaE = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$jaBoard = is_array($jobBoard ?? null) ? $jobBoard : JobAssignmentBoard::build([], [], JobAssignmentBoard::filters([]));
$jaF = $jaBoard['filters'];
$jaInd = $jaBoard['indicators'];
$jaFacets = $jaBoard['facets'];
$jaView = $jaF['affichage'];
$jaManage = !empty($canManage) && !empty($jobBoardReady);
$jaAllMembers = is_array($jobBoardAllMembers ?? null) ? $jobBoardAllMembers : [];
$jaAllJobs = is_array($jobBoardAllJobs ?? null) ? $jobBoardAllJobs : [];
$jaCategories = is_array($jobBoardCategories ?? null) ? $jobBoardCategories : [];
$jaTags = is_array($jobBoardTags ?? null) ? $jobBoardTags : [];
$jaUnits = is_array($jobBoardUnits ?? null) ? $jobBoardUnits : [];
$jaSettings = is_array($pjrAssignSettings ?? null) ? $pjrAssignSettings : [];
$jaMaxRoles = (int) ($jaSettings['max_roles_per_member'] ?? 5);
$jaUrl = static fn (array $override = []): string => $base . '?' . http_build_query(JobAssignmentBoard::query($jaF, $override));
$jaReturnQuery = http_build_query(JobAssignmentBoard::query($jaF));
$jaBulkUrl = $base . '/attributions/lot';
$jaExportUrl = $base . '/attributions/export?' . $jaReturnQuery;
$jaRosterUrl = effectifs_workspace_url();
$jaMemberUrl = static fn (int $jaId): string => effectifs_workspace_url('membres/' . $jaId);

$jaCategoryById = [];
foreach ($jaCategories as $jaC) {
    $jaCategoryById[(int) $jaC['id']] = $jaC;
}
$jaCategoryDepth = static function (int $jaId) use ($jaCategoryById): int {
    $jaD = 0;
    $jaSeen = [];
    while (isset($jaCategoryById[$jaId]) && (int) ($jaCategoryById[$jaId]['parent_id'] ?? 0) > 0 && !isset($jaSeen[$jaId]) && $jaD < 8) {
        $jaSeen[$jaId] = true;
        $jaId = (int) $jaCategoryById[$jaId]['parent_id'];
        $jaD++;
    }

    return $jaD;
};
$jaTagById = [];
foreach ($jaTags as $jaT) {
    $jaTagById[(int) $jaT['id']] = $jaT;
}
$jaUnitById = [];
foreach ($jaUnits as $jaU) {
    $jaUnitById[(int) $jaU['id']] = $jaU;
}
$jaGrades = [];
foreach ($jaAllMembers as $jaM) {
    if ((string) $jaM['grade'] !== '') {
        $jaGrades[(string) $jaM['grade']] = min($jaGrades[(string) $jaM['grade']] ?? 999, (int) $jaM['grade_order']);
    }
}
asort($jaGrades);
$jaSituations = [];
foreach ($jaAllMembers as $jaM) {
    $jaS = trim((string) $jaM['situation']);
    if ($jaS !== '') {
        $jaSituations[JobAssignmentBoard::fold($jaS)] = $jaS;
    }
}
ksort($jaSituations);
$jaAccountStatuses = ['active' => 'Actif', 'inactive' => 'Inactif', 'pending_verification' => 'En attente', 'suspended' => 'Suspendu'];
$jaStates = ['' => 'Tous', 'vacants' => 'Emplois avec des places vacantes', 'sans_titulaire' => 'Emplois sans titulaire', 'critiques' => 'Emplois vacants critiques',
    'sans_emploi' => 'Membres sans emploi', 'multi' => 'Membres à plusieurs emplois'];
$jaOrigins = ['' => 'Toutes', 'community' => 'Créées par vous', 'site' => 'Fonctions du site', 'orbat' => 'Issues de l’ORBAT', 'masquees' => 'Fonctions du site masquées'];
$jaJobSorts = ['categorie' => 'Catégorie', 'nom' => 'Nom de l’emploi', 'titulaires' => 'Nombre de titulaires', 'vacants' => 'Places vacantes d’abord'];
$jaMemberSorts = ['nom' => 'Nom', 'grade' => 'Grade', 'unite' => 'Unité', 'emplois' => 'Nombre d’emplois'];
$jaViews = ['matrice' => 'Matrice', 'liste' => 'Liste des emplois', 'membres' => 'Par membre'];
$jaCount = static fn (array $map, int|string $jaKey): string => isset($map[$jaKey]) ? ' (' . (int) $map[$jaKey] . ')' : ' (0)';

// Filtres actifs : pastilles avec retrait individuel.
$jaActive = [];
if ($jaF['q'] !== '') $jaActive[] = ['Recherche', $jaF['q'], ['q' => '']];
if ($jaF['categorie'] > 0) $jaActive[] = ['Catégorie', (string) ($jaCategoryById[$jaF['categorie']]['name'] ?? '#' . $jaF['categorie']), ['categorie' => 0]];
if ($jaF['etiquette'] > 0) $jaActive[] = ['Étiquette', (string) ($jaTagById[$jaF['etiquette']]['name'] ?? '#' . $jaF['etiquette']), ['etiquette' => 0]];
if ($jaF['unite'] > 0) $jaActive[] = ['Unité', (string) ($jaUnitById[$jaF['unite']]['label'] ?? '#' . $jaF['unite']), ['unite' => 0, 'sous_unites' => true]];
if ($jaF['grade'] !== '') $jaActive[] = ['Grade', $jaF['grade'], ['grade' => '']];
if ($jaF['statut'] !== '') {
    [$jaSk, $jaSv] = array_pad(explode(':', $jaF['statut'], 2), 2, '');
    $jaActive[] = ['Statut', $jaSk === 'compte' ? ($jaAccountStatuses[$jaSv] ?? $jaSv) : ($jaSituations[JobAssignmentBoard::fold($jaSv)] ?? $jaSv), ['statut' => '']];
}
if ($jaF['etat'] !== '') $jaActive[] = ['État', $jaStates[$jaF['etat']], ['etat' => '']];
if ($jaF['origine'] !== '') $jaActive[] = ['Origine', $jaOrigins[$jaF['origine']], ['origine' => '']];

$jaInitials = static function (string $jaName): string {
    $jaParts = preg_split('/\s+/u', trim($jaName)) ?: [];
    $jaOut = '';
    foreach (array_slice($jaParts, 0, 2) as $jaP) {
        $jaOut .= mb_strtoupper(mb_substr($jaP, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return $jaOut !== '' ? $jaOut : '?';
};
$jaAvatar = static function (array $jaM) use ($jaE, $jaInitials): string {
    if (!empty($jaM['avatar'])) {
        return '<span class="ja-av" aria-hidden="true"><img src="' . $jaE($jaM['avatar']) . '" alt="" loading="lazy" decoding="async"></span>';
    }

    return '<span class="ja-av' . (!empty($jaM['anonymized']) ? ' ja-av--anon' : '') . '" aria-hidden="true">' . $jaE(!empty($jaM['anonymized']) ? '?' : $jaInitials((string) $jaM['name'])) . '</span>';
};
$jaUnitHtml = static function (array $jaM) use ($jaE): string {
    $jaLabel = (string) ($jaM['unit_label'] ?? '');
    if (!empty($jaM['unit_linkable']) && (int) $jaM['unit_id'] > 0) {
        return '<a class="ja-unit" href="' . $jaE(orbat_page_url(['unit' => (int) $jaM['unit_id']])) . '" translate="no" title="' . $jaE((string) $jaM['unit_path']) . '">' . $jaE($jaLabel) . '</a>' . (string) $jaM['unit_badge'];
    }
    if ((string) ($jaM['unit_badge'] ?? '') !== '') {
        return (string) $jaM['unit_badge'];
    }

    return $jaLabel !== '' ? '<span class="ja-unit" translate="no">' . $jaE($jaLabel) . '</span>' : '<span class="ja-muted">Sans unité</span>';
};
$jaMemberName = static function (array $jaM) use ($jaE, $jaMemberUrl): string {
    if (!empty($jaM['anonymized'])) {
        // Membre anonymisé : la seule mention commune, sans nom, grade ni lien.
        return (string) $jaM['person_badge'];
    }
    $jaGrade = (string) $jaM['grade'] !== '' ? '<abbr class="ja-grade" translate="no" title="' . $jaE((string) $jaM['grade_long']) . '">' . $jaE($jaM['grade']) . '</abbr> ' : '';
    $jaName = (int) $jaM['id'] > 0
        ? '<a class="ja-name" href="' . $jaE($jaMemberUrl((int) $jaM['id'])) . '" translate="no">' . $jaE($jaM['name']) . '</a>'
        : '<span class="ja-name">' . $jaE($jaM['name']) . '</span>';

    return $jaGrade . $jaName . (string) $jaM['person_badge'];
};
$jaJobTitle = static function (array $jaJ, bool $link = true) use ($jaE, $base): string {
    $jaBadge = JobRoleBadge::html($jaJ['decoration'] ?? null);
    $jaName = $link ? '<a class="ja-job__name" href="' . $jaE($base . '?emploi=' . (int) $jaJ['id']) . '" translate="no">' . $jaE($jaJ['name']) . '</a>'
        : '<span class="ja-job__name" translate="no">' . $jaE($jaJ['name']) . '</span>';
    $jaOrigin = JobRoleBadge::origin((string) $jaJ['origin']);
    if (!empty($jaJ['hidden'])) {
        $jaOrigin .= ' <span class="job-hidden-flag">Masquée</span>';
    }

    return $jaBadge . $jaName . ' ' . $jaOrigin;
};
$jaStateLabel = static function (array $jaJ): string {
    if ($jaJ['state'] === 'vacant') {
        return !empty($jaJ['critical']) ? '<span class="ja-state ja-state--critical">Vacant critique</span>' : '<span class="ja-state ja-state--vacant">Sans titulaire</span>';
    }
    if ($jaJ['state'] === 'partial') {
        return '<span class="ja-state ja-state--partial">' . (int) $jaJ['vacancies'] . ' place(s) vacante(s)</span>';
    }

    return '<span class="ja-state ja-state--ok">Pourvu</span>';
};
$jaHolderActions = static function (array $jaM, array $jaJ) use ($jaE, $jaManage, $csrf, $jaBulkUrl, $jaReturnQuery): string {
    if (!$jaManage || empty($jaM['selectable'])) {
        return '';
    }
    $jaId = (int) $jaM['id'];

    // Une fonction du site masquée se retire mais ne se confie plus : pas de remplacement.
    return '<span class="ja-holder__acts">'
        . (!empty($jaJ['hidden']) ? '' : '<button type="button" class="ja-icon-btn" data-ja-open="remplacer" data-job="' . (int) $jaJ['id'] . '" data-from="' . $jaId . '" aria-label="Remplacer ce titulaire" title="Remplacer ce titulaire">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h11l-3-3M17 17H6l3 3"/></svg></button>')
        . '<form method="post" action="' . $jaE($jaBulkUrl) . '" data-confirm="Retirer cet emploi à ce membre ?">'
        . '<input type="hidden" name="_csrf_token" value="' . $jaE($csrf) . '"><input type="hidden" name="action" value="retirer">'
        . '<input type="hidden" name="job_role_id" value="' . (int) $jaJ['id'] . '"><input type="hidden" name="user_ids[]" value="' . $jaId . '">'
        . '<input type="hidden" name="return_query" value="' . $jaE($jaReturnQuery) . '">'
        . '<button class="ja-icon-btn ja-icon-btn--danger" aria-label="Retirer cet emploi" title="Retirer cet emploi"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></form>'
        . '</span>';
};
$jaHolderItem = static function (array $jaM, array $jaJ) use ($jaAvatar, $jaMemberName, $jaUnitHtml, $jaHolderActions, $jaE): string {
    $jaMatch = in_array((string) $jaM['key'], $jaJ['matching_keys'] ?? [], true) ? ' is-match' : '';
    $jaPrimary = (int) $jaM['primary_job'] === (int) $jaJ['id'] ? ' <span class="ja-primary" title="Emploi principal du membre">Principal</span>' : '';
    $jaCallsign = (string) $jaM['callsign'] !== '' ? '<span class="ja-callsign" translate="no">' . $jaE($jaM['callsign']) . '</span>' : '';

    return '<li class="ja-holder' . $jaMatch . '">' . $jaAvatar($jaM)
        . '<span class="ja-holder__id"><span class="ja-holder__line">' . $jaMemberName($jaM) . $jaPrimary . '</span>'
        . '<span class="ja-holder__meta">' . $jaCallsign . $jaUnitHtml($jaM) . '</span></span>'
        . $jaHolderActions($jaM, $jaJ) . '</li>';
};
$jaJobLinks = static function (array $jaJ) use ($jaE, $base, $jaRosterUrl): string {
    $jaLinks = ['<a href="' . $jaE($base . '?emploi=' . (int) $jaJ['id']) . '">Fiche emploi</a>',
        '<a href="' . $jaE($jaRosterUrl . '?' . http_build_query(['fonction' => (string) $jaJ['name']])) . '">Tableur</a>'];
    $jaSeen = [];
    foreach ($jaJ['billets'] ?? [] as $jaB) {
        $jaU = $jaB['unit'] ?? null;
        if (is_array($jaU) && !empty($jaU['linkable']) && (int) $jaU['id'] > 0 && !isset($jaSeen[(int) $jaU['id']])) {
            $jaSeen[(int) $jaU['id']] = true;
            $jaLinks[] = '<a href="' . $jaE(orbat_page_url(['unit' => (int) $jaU['id']])) . '">ORBAT <span translate="no">' . $jaE($jaU['label']) . '</span></a>';
        }
    }
    $jaLu = $jaJ['linked_unit'] ?? null;
    if (is_array($jaLu) && !empty($jaLu['linkable']) && (int) $jaLu['id'] > 0 && !isset($jaSeen[(int) $jaLu['id']])) {
        $jaLinks[] = '<a href="' . $jaE(orbat_page_url(['unit' => (int) $jaLu['id']])) . '">Unité <span translate="no">' . $jaE($jaLu['label']) . '</span></a>';
    }

    return implode('', array_map(static fn (string $jaL): string => '<li>' . $jaL . '</li>', $jaLinks));
};
$jaSeats = static function (array $jaJ): string {
    $jaHolders = count($jaJ['holders']);
    if ((int) $jaJ['places'] < 1) {
        return '<span class="ja-seats">' . $jaHolders . ' titulaire(s)</span>';
    }
    $jaPct = (int) min(100, round(100 * min($jaHolders, (int) $jaJ['places']) / max(1, (int) $jaJ['places'])));

    return '<span class="ja-seats">' . $jaHolders . ' / ' . (int) $jaJ['places'] . ' place(s) ORBAT</span>'
        . '<span class="ja-meter" role="img" aria-label="' . $jaPct . ' % des places tenues"><span style="width:' . $jaPct . '%"></span></span>';
};
?>
<section class="ja-board" data-ja-board aria-labelledby="ja-title">
<header class="ja-head">
    <div>
        <h2 id="ja-title" class="ja-head__title">Qui tient quel emploi</h2>
        <p class="ja-head__lead">Retrouvez les titulaires de chaque emploi, les places vacantes et les membres sans emploi. Un emploi décrit une responsabilité au dossier : il ne donne aucun droit d’accès et ne modifie pas l’affectation ORBAT.</p>
    </div>
    <p class="ja-print-date">Édité le <?= $jaE(\App\Support\LocalizedCalendar::dateTime((int) date('j'), (int) date('n'), date('Y'), date('H:i'))) ?></p>
</header>

<?php if (empty($jobBoardReady)): ?>
<p class="eff-jobs-warning" role="status">Les attributions d’emplois arrivent avec la prochaine mise à jour du site.</p>
<?php else: ?>
<?php if (!empty($jobBoardTruncated)): ?><p class="eff-jobs-warning" role="status">Votre communauté compte plus de <?= (int) \App\Services\Personnel\JobAssignmentBoardLoader::MAX_MEMBERS ?> membres : seuls les premiers par ordre alphabétique sont pris en compte ici. Utilisez le tableur des effectifs pour la liste complète.</p><?php endif; ?>

<ul class="ja-kpis" aria-label="Indicateurs">
    <li class="ja-kpi"><span class="ja-kpi__label">Couverture des emplois</span><strong class="ja-kpi__value"><?= (int) $jaInd['coverage'] ?> %</strong>
        <span class="ja-kpi__note"><?= (int) $jaInd['jobs_held'] ?> emploi(s) tenu(s) sur <?= (int) $jaInd['jobs'] ?></span>
        <span class="ja-meter" aria-hidden="true"><span style="width:<?= (int) $jaInd['coverage'] ?>%"></span></span></li>
    <li class="ja-kpi<?= $jaInd['critical'] > 0 ? ' is-alert' : '' ?>"><a href="<?= $jaE($jaUrl(['etat' => 'critiques', 'affichage' => $jaView === 'membres' ? 'liste' : $jaView])) ?>"><span class="ja-kpi__label">Vacants critiques</span><strong class="ja-kpi__value"><?= (int) $jaInd['critical'] ?></strong>
        <span class="ja-kpi__note">Postes ORBAT prévus, aucun titulaire</span></a></li>
    <li class="ja-kpi"><a href="<?= $jaE($jaUrl(['etat' => 'sans_titulaire', 'affichage' => $jaView === 'membres' ? 'liste' : $jaView])) ?>"><span class="ja-kpi__label">Emplois sans titulaire</span><strong class="ja-kpi__value"><?= (int) $jaInd['vacant'] ?></strong>
        <span class="ja-kpi__note"><?= (int) $jaInd['places_filled'] ?> / <?= (int) $jaInd['places'] ?> place(s) ORBAT tenue(s)</span></a></li>
    <li class="ja-kpi"><a href="<?= $jaE($jaUrl(['etat' => 'multi', 'affichage' => 'membres', 'tri' => 'emplois'])) ?>"><span class="ja-kpi__label">Membres à plusieurs emplois</span><strong class="ja-kpi__value"><?= (int) $jaInd['multi'] ?></strong>
        <span class="ja-kpi__note"><?= (int) $jaInd['links'] ?> attribution(s) au total</span></a></li>
    <li class="ja-kpi"><a href="<?= $jaE($jaUrl(['etat' => 'sans_emploi', 'affichage' => 'membres', 'tri' => 'nom'])) ?>"><span class="ja-kpi__label">Membres sans emploi</span><strong class="ja-kpi__value"><?= (int) $jaInd['without_job'] ?></strong>
        <span class="ja-kpi__note">sur <?= (int) $jaInd['members'] ?> membre(s) affiché(s)</span></a></li>
</ul>

<form method="get" action="<?= $jaE($base) ?>" class="ja-filters" role="search" aria-label="Rechercher et filtrer les attributions" data-ja-filters>
    <input type="hidden" name="vue" value="attributions">
    <input type="hidden" name="affichage" value="<?= $jaE($jaView) ?>">
    <label class="ja-filters__search">Recherche
        <input type="search" name="q" value="<?= $jaE($jaF['q']) ?>" maxlength="120" placeholder="Emploi, abrégé, étiquette, membre, indicatif, unité…" data-ja-search autocomplete="off">
    </label>
    <label>Catégorie d’emploi
        <select name="categorie" data-ja-auto><option value="0">Toutes</option>
        <?php foreach ($jaCategories as $jaC): $jaCid = (int) $jaC['id']; ?><option value="<?= $jaCid ?>" <?= $jaF['categorie'] === $jaCid ? 'selected' : '' ?> translate="no"><?= $jaE(str_repeat('— ', $jaCategoryDepth($jaCid)) . $jaC['name'] . $jaCount($jaFacets['categories'], $jaCid)) ?></option><?php endforeach; ?>
        </select></label>
    <label>Étiquette
        <select name="etiquette" data-ja-auto <?= $jaTags === [] ? 'disabled' : '' ?>><option value="0">Toutes</option>
        <?php foreach ($jaTags as $jaT): $jaTid = (int) $jaT['id']; ?><option value="<?= $jaTid ?>" <?= $jaF['etiquette'] === $jaTid ? 'selected' : '' ?> translate="no"><?= $jaE($jaT['name'] . $jaCount($jaFacets['tags'], $jaTid)) ?></option><?php endforeach; ?>
        </select></label>
    <label>Unité
        <select name="unite" data-ja-auto><option value="0">Toutes</option>
        <?php foreach ($jaUnits as $jaU): $jaUid = (int) $jaU['id']; ?><option value="<?= $jaUid ?>" <?= $jaF['unite'] === $jaUid ? 'selected' : '' ?> translate="no"><?= $jaE(str_repeat('— ', max(0, (int) $jaU['depth'])) . $jaU['label'] . $jaCount($jaFacets['units'], $jaUid)) ?></option><?php endforeach; ?>
        </select></label>
    <label class="ja-check"><input type="hidden" name="sous_unites" value="0"><input type="checkbox" name="sous_unites" value="1" <?= $jaF['sous_unites'] ? 'checked' : '' ?> data-ja-auto>Inclure les sous-unités</label>
    <label>Grade
        <select name="grade" data-ja-auto><option value="">Tous</option>
        <?php foreach ($jaGrades as $jaG => $jaUnused): ?><option value="<?= $jaE($jaG) ?>" <?= $jaF['grade'] === (string) $jaG ? 'selected' : '' ?> translate="no"><?= $jaE($jaG . $jaCount($jaFacets['grades'], (string) $jaG)) ?></option><?php endforeach; ?>
        </select></label>
    <label>Statut du membre
        <select name="statut" data-ja-auto><option value="">Tous</option>
        <optgroup label="Compte">
        <?php foreach ($jaAccountStatuses as $jaK => $jaLabel): ?><option value="compte:<?= $jaE($jaK) ?>" <?= $jaF['statut'] === 'compte:' . $jaK ? 'selected' : '' ?>><?= $jaE($jaLabel) ?><?= $jaE($jaCount($jaFacets['statuses'], 'compte:' . $jaK)) ?></option><?php endforeach; ?>
        </optgroup>
        <?php if ($jaSituations !== []): ?><optgroup label="Situation au dossier">
        <?php foreach ($jaSituations as $jaK => $jaLabel): ?><option value="situation:<?= $jaE($jaLabel) ?>" <?= JobAssignmentBoard::fold($jaF['statut']) === 'situation:' . $jaK ? 'selected' : '' ?> translate="no"><?= $jaE($jaLabel . $jaCount($jaFacets['statuses'], 'situation:' . $jaK)) ?></option><?php endforeach; ?>
        </optgroup><?php endif; ?>
        </select></label>
    <label>État
        <select name="etat" data-ja-auto>
        <?php foreach ($jaStates as $jaK => $jaLabel): ?><option value="<?= $jaE($jaK) ?>" <?= $jaF['etat'] === $jaK ? 'selected' : '' ?>><?= $jaE($jaLabel) ?></option><?php endforeach; ?>
        </select></label>
    <label>Origine de l’emploi
        <select name="origine" data-ja-auto>
        <?php foreach ($jaOrigins as $jaK => $jaLabel): ?><option value="<?= $jaE($jaK) ?>" <?= $jaF['origine'] === $jaK ? 'selected' : '' ?>><?= $jaE($jaLabel) ?><?= $jaK !== '' ? $jaE($jaCount($jaFacets['origins'], $jaK)) : '' ?></option><?php endforeach; ?>
        </select></label>
    <label>Trier par
        <select name="tri" data-ja-auto>
        <?php foreach ($jaView === 'membres' ? $jaMemberSorts : $jaJobSorts as $jaK => $jaLabel): ?><option value="<?= $jaE($jaK) ?>" <?= $jaF['tri'] === $jaK ? 'selected' : '' ?>><?= $jaE($jaLabel) ?></option><?php endforeach; ?>
        </select></label>
    <div class="ja-filters__actions">
        <button class="eff-btn eff-btn--primary">Appliquer</button>
        <a class="eff-btn" href="<?= $jaE($base . '?' . http_build_query(['vue' => 'attributions', 'affichage' => $jaView])) ?>">Réinitialiser</a>
    </div>
</form>

<?php if ($jaActive !== []): ?>
<ul class="ja-chips" aria-label="Filtres actifs">
<?php foreach ($jaActive as [$jaLabel, $jaValue, $jaRemove]): ?>
    <li><a class="ja-chip" href="<?= $jaE($jaUrl($jaRemove)) ?>" aria-label="Retirer le filtre <?= $jaE($jaLabel) ?>"><?= $jaE($jaLabel) ?> : <span translate="no"><?= $jaE($jaValue) ?></span> <span aria-hidden="true">×</span></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<div class="ja-toolbar">
    <nav class="ja-views" aria-label="Présentation">
    <?php foreach ($jaViews as $jaK => $jaLabel): $jaTri = $jaK === 'membres' ? 'nom' : 'categorie'; ?>
        <a href="<?= $jaE($jaUrl(['affichage' => $jaK, 'tri' => $jaView === $jaK ? $jaF['tri'] : $jaTri])) ?>" class="ja-views__item<?= $jaView === $jaK ? ' is-current' : '' ?>" <?= $jaView === $jaK ? 'aria-current="page"' : '' ?>><?= $jaE($jaLabel) ?></a>
    <?php endforeach; ?>
    </nav>
    <p class="ja-toolbar__count" role="status" aria-live="polite">
        <?php if ($jaView === 'membres'): ?><?= count($jaBoard['members']) ?> membre(s) affiché(s)<?php else: ?><?= count($jaBoard['jobs']) ?> emploi(s) affiché(s) · <?= array_sum(array_map(static fn (array $jaJ): int => count($jaJ['holders']), $jaBoard['jobs'])) ?> titulaire(s)<?php endif; ?>
    </p>
    <div class="ja-toolbar__acts">
        <a class="eff-btn" href="<?= $jaE($jaExportUrl) ?>" download>Exporter en CSV</a>
        <button type="button" class="eff-btn" data-ja-print>Imprimer</button>
        <a class="eff-btn" href="<?= $jaE($jaRosterUrl . ($jaF['etiquette'] > 0 ? '?' . http_build_query(['etiquette_fonction' => $jaF['etiquette']]) : ($jaF['unite'] > 0 ? '?' . http_build_query(['unite' => $jaF['unite']]) : ''))) ?>">Tableur des effectifs</a>
    </div>
</div>

<?php if ($jaView === 'matrice'): ?>
    <?php if ($jaBoard['jobs'] === []): ?><p class="eff-jobs-empty">Aucun emploi ne correspond à ces critères.</p><?php endif; ?>
    <?php
    $jaGroups = [];
    foreach ($jaBoard['jobs'] as $jaJ) {
        $jaGroups[$jaF['tri'] === 'categorie' ? (string) $jaJ['category'] : ''][] = $jaJ;
    }
    foreach ($jaGroups as $jaGroupName => $jaGroupJobs): ?>
        <?php if ($jaF['tri'] === 'categorie'): ?><h3 class="ja-group"><span translate="no"><?= $jaE($jaGroupName !== '' ? $jaGroupName : '—') ?></span> <small><?= count($jaGroupJobs) ?></small></h3><?php endif; ?>
        <div class="ja-grid">
        <?php foreach ($jaGroupJobs as $jaJ): ?>
        <article class="ja-card ja-card--<?= $jaE($jaJ['state']) ?><?= !empty($jaJ['critical']) ? ' is-critical' : '' ?>" aria-labelledby="ja-job-<?= (int) $jaJ['id'] ?>">
            <header class="ja-card__head">
                <h4 id="ja-job-<?= (int) $jaJ['id'] ?>" class="ja-job"><?= $jaJobTitle($jaJ) ?></h4>
                <?= $jaStateLabel($jaJ) ?>
            </header>
            <?php if (($jaJ['tags'] ?? []) !== [] || $jaF['tri'] !== 'categorie'): ?><p class="ja-card__sub"><?php if ($jaF['tri'] !== 'categorie'): ?><span class="ja-muted" translate="no"><?= $jaE($jaJ['category']) ?></span> <?php endif; ?><?= JobRoleBadge::tags($jaJ['tags'] ?? []) ?></p><?php endif; ?>
            <div class="ja-card__seats"><?= $jaSeats($jaJ) ?></div>
            <?php if ($jaJ['holders'] !== []): ?><ul class="ja-holders"><?php foreach ($jaJ['holders'] as $jaM) echo $jaHolderItem($jaM, $jaJ); ?></ul>
            <?php else: ?><p class="ja-card__empty">Aucun titulaire<?= ($jaF['unite'] > 0 || $jaF['grade'] !== '' || $jaF['statut'] !== '') && (int) $jaJ['holders_total'] > 0 ? ' parmi les membres filtrés' : '' ?>.</p><?php endif; ?>
            <footer class="ja-card__foot">
                <ul class="ja-links"><?= $jaJobLinks($jaJ) ?></ul>
                <?php if ($jaManage && empty($jaJ['hidden'])): ?><button type="button" class="eff-btn ja-add" data-ja-open="attribuer" data-job="<?= (int) $jaJ['id'] ?>">Ajouter des titulaires</button><?php endif; ?>
            </footer>
        </article>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

<?php elseif ($jaView === 'liste'): ?>
    <div class="eff-table-scroll ja-table-wrap">
    <table class="ja-table">
        <caption class="sr-only">Emplois et titulaires</caption>
        <thead><tr><th scope="col">Emploi</th><th scope="col">Catégorie</th><th scope="col">Titulaires</th><th scope="col">Places</th><th scope="col">État</th><th scope="col"><span class="sr-only">Liens et actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($jaBoard['jobs'] as $jaJ): ?>
            <tr class="<?= !empty($jaJ['critical']) ? 'is-critical' : '' ?>">
                <th scope="row"><span class="ja-job"><?= $jaJobTitle($jaJ) ?></span><?= ($jaJ['tags'] ?? []) !== [] ? '<br>' . JobRoleBadge::tags($jaJ['tags']) : '' ?></th>
                <td translate="no"><?= $jaE($jaJ['category']) ?></td>
                <td><?php if ($jaJ['holders'] !== []): ?><ul class="ja-holders ja-holders--compact"><?php foreach ($jaJ['holders'] as $jaM) echo $jaHolderItem($jaM, $jaJ); ?></ul><?php else: ?><span class="ja-muted">Aucun titulaire</span><?php endif; ?></td>
                <td><?= $jaSeats($jaJ) ?></td>
                <td><?= $jaStateLabel($jaJ) ?></td>
                <td><ul class="ja-links"><?= $jaJobLinks($jaJ) ?></ul><?php if ($jaManage && empty($jaJ['hidden'])): ?><button type="button" class="eff-btn ja-add" data-ja-open="attribuer" data-job="<?= (int) $jaJ['id'] ?>">Ajouter</button><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($jaBoard['jobs'] === []): ?><tr><td colspan="6" class="eff-jobs-empty">Aucun emploi ne correspond à ces critères.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>

<?php else: ?>
    <form method="post" action="<?= $jaE($jaBulkUrl) ?>" class="ja-bulk-form" data-ja-bulk>
    <input type="hidden" name="_csrf_token" value="<?= $jaE($csrf) ?>">
    <input type="hidden" name="return_query" value="<?= $jaE($jaReturnQuery) ?>">
    <div class="eff-table-scroll ja-table-wrap">
    <table class="ja-table ja-table--members">
        <caption class="sr-only">Emplois tenus par chaque membre</caption>
        <thead><tr>
            <?php if ($jaManage): ?><th scope="col" class="ja-col-check"><input type="checkbox" data-ja-select-all aria-label="Sélectionner tous les membres affichés"></th><?php endif; ?>
            <th scope="col">Membre</th><th scope="col">Unité</th><th scope="col">Statut</th><th scope="col">Emplois</th><th scope="col"><span class="sr-only">Liens</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($jaBoard['members'] as $jaM): ?>
            <tr>
                <?php if ($jaManage): ?><td class="ja-col-check"><?php if (!empty($jaM['selectable'])): ?><input type="checkbox" name="user_ids[]" value="<?= (int) $jaM['id'] ?>" data-ja-member aria-label="Sélectionner <?= $jaE($jaM['name']) ?>"><?php endif; ?></td><?php endif; ?>
                <th scope="row"><span class="ja-member"><?= $jaAvatar($jaM) ?><span><span class="ja-holder__line"><?= $jaMemberName($jaM) ?></span><?php if ((string) $jaM['callsign'] !== ''): ?><span class="ja-callsign" translate="no"><?= $jaE($jaM['callsign']) ?></span><?php endif; ?></span></span></th>
                <td><?= $jaUnitHtml($jaM) ?></td>
                <td><span class="ja-status ja-status--<?= $jaE($jaM['account_status']) ?>"><?= $jaE($jaM['status_label']) ?></span><?php if ((string) $jaM['situation'] !== ''): ?> <span class="ja-muted" translate="no"><?= $jaE($jaM['situation']) ?></span><?php endif; ?></td>
                <td><?php if ($jaM['job_items'] === []): ?><span class="ja-muted">Aucun emploi</span><?php else: ?><ul class="ja-jobchips"><?php foreach ($jaM['job_items'] as $jaJ): ?>
                    <li class="ja-jobchip<?= (int) $jaM['primary_job'] === (int) $jaJ['id'] ? ' ja-jobchip--primary' : '' ?>"><?= JobRoleBadge::html($jaJ['decoration'] ?? null, false) ?><a href="<?= $jaE($base . '?emploi=' . (int) $jaJ['id']) ?>" translate="no"><?= $jaE($jaJ['name']) ?></a><?php if ((int) $jaM['primary_job'] === (int) $jaJ['id']): ?> <span class="ja-primary">Principal</span><?php endif; ?></li>
                <?php endforeach; ?></ul><?php endif; ?></td>
                <td><?php if ((int) $jaM['id'] > 0): ?><ul class="ja-links"><li><a href="<?= $jaE($jaMemberUrl((int) $jaM['id'])) ?>">Fiche</a></li><li><a href="<?= $jaE($jaMemberUrl((int) $jaM['id']) . '#carriere') ?>">Qualifications</a></li><?php if ($jaManage): ?><li><a href="<?= $jaE($jaMemberUrl((int) $jaM['id']) . '#eff-dlg-jobs') ?>">Emploi principal et précisions</a></li><?php endif; ?></ul><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($jaBoard['members'] === []): ?><tr><td colspan="<?= $jaManage ? 6 : 5 ?>" class="eff-jobs-empty">Aucun membre ne correspond à ces critères.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php if ($jaManage): ?>
    <div class="ja-bulkbar" data-ja-bulkbar>
        <p class="ja-bulkbar__count" aria-live="polite"><span data-ja-selected>0</span> membre(s) sélectionné(s)</p>
        <label>Emploi
            <select name="job_role_id" required>
                <option value="">Choisir un emploi…</option>
                <?php foreach ($jaAllJobs as $jaJ): if (!empty($jaJ['hidden'])) continue; ?><option value="<?= (int) $jaJ['id'] ?>" translate="no"><?= $jaE(($jaJ['abbreviation'] ? $jaJ['abbreviation'] . ' · ' : '') . $jaJ['name']) ?></option><?php endforeach; ?>
            </select></label>
        <button class="eff-btn eff-btn--primary" name="action" value="attribuer">Attribuer aux membres sélectionnés</button>
        <button class="eff-btn eff-btn--danger" name="action" value="retirer" data-confirm="Retirer cet emploi aux membres sélectionnés ?">Retirer aux membres sélectionnés</button>
    </div>
    <?php endif; ?>
    </form>
<?php endif; ?>

<?php if ($jaManage): ?>
<dialog class="ja-dialog" data-ja-dialog aria-labelledby="ja-dialog-title">
    <form method="post" action="<?= $jaE($jaBulkUrl) ?>" class="ja-dialog__form">
        <input type="hidden" name="_csrf_token" value="<?= $jaE($csrf) ?>">
        <input type="hidden" name="return_query" value="<?= $jaE($jaReturnQuery) ?>">
        <input type="hidden" name="action" value="attribuer" data-ja-dialog-action>
        <input type="hidden" name="job_role_id" value="" data-ja-dialog-job>
        <input type="hidden" name="replaced_user_id" value="" data-ja-dialog-from>
        <header class="ja-dialog__head">
            <h2 id="ja-dialog-title"><span data-ja-mode="attribuer">Ajouter des titulaires</span><span data-ja-mode="remplacer" hidden>Remplacer le titulaire</span></h2>
            <p class="ja-dialog__job" translate="no" data-ja-dialog-name></p>
            <p class="ja-muted" data-ja-mode="remplacer" hidden>Choisissez le nouveau titulaire : l’emploi est retiré à l’ancien et attribué au nouveau, avec l’historique de chacun. Les autres emplois ne changent pas.</p>
            <p class="ja-muted" data-ja-mode="attribuer">Cochez un ou plusieurs membres. Chacun garde ses autres emplois (<?= $jaMaxRoles ?> au maximum par membre).</p>
        </header>
        <label class="ja-dialog__search">Rechercher un membre<input type="search" data-ja-dialog-search autocomplete="off" placeholder="Nom, indicatif, grade, unité…"></label>
        <ul class="ja-picker" data-ja-picker>
        <?php foreach ($jaAllMembers as $jaM): if (empty($jaM['selectable'])) continue; ?>
            <li data-ja-pick data-jobs="<?= $jaE(implode(',', $jaM['jobs'])) ?>" data-search="<?= $jaE(JobAssignmentBoard::fold($jaM['name'] . ' ' . $jaM['callsign'] . ' ' . $jaM['grade'] . ' ' . $jaM['unit_label'])) ?>">
                <label><input type="checkbox" name="user_ids[]" value="<?= (int) $jaM['id'] ?>"><?= $jaAvatar($jaM) ?>
                <span><span class="ja-holder__line"><?php if ((string) $jaM['grade'] !== ''): ?><span class="ja-grade" translate="no"><?= $jaE($jaM['grade']) ?></span> <?php endif; ?><span translate="no"><?= $jaE($jaM['name']) ?></span></span>
                <span class="ja-holder__meta"><?php if ((string) $jaM['callsign'] !== ''): ?><span class="ja-callsign" translate="no"><?= $jaE($jaM['callsign']) ?></span><?php endif; ?><span translate="no"><?= $jaE($jaM['unit_label']) ?></span> · <?= count($jaM['jobs']) ?> emploi(s)</span></span>
                <span class="ja-picker__held">Déjà titulaire</span></label>
            </li>
        <?php endforeach; ?>
        </ul>
        <p class="ja-muted" data-ja-picker-empty hidden>Aucun membre ne correspond.</p>
        <footer class="ja-dialog__foot">
            <button type="button" class="eff-btn" data-ja-dialog-close>Annuler</button>
            <button class="eff-btn eff-btn--primary" data-ja-dialog-submit>Enregistrer</button>
        </footer>
    </form>
</dialog>
<script type="application/json" data-ja-jobs><?= json_encode(array_map(static fn (array $jaJ): array => ['id' => (int) $jaJ['id'], 'name' => (string) $jaJ['name']], $jaAllJobs), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<details class="eff-jobs-guide ja-settings">
    <summary>Paramètres d’attribution</summary>
    <form method="post" action="<?= $jaE(url('back-office/personnel-job-roles/assignments/settings')) ?>" class="ja-settings__form">
        <input type="hidden" name="_csrf_token" value="<?= $jaE($csrf) ?>">
        <label>Emplois maximum par membre<input type="number" name="max_roles_per_member" min="1" max="12" value="<?= $jaMaxRoles ?>"></label>
        <input type="hidden" name="assignments_page_size" value="<?= (int) ($jaSettings['assignments_page_size'] ?? 30) ?>">
        <input type="hidden" name="default_expand_role_rows" value="<?= (int) ($jaSettings['default_expand_role_rows'] ?? 3) ?>">
        <?php foreach (['require_primary_when_multiple' => 'Exiger un emploi principal lorsque plusieurs emplois sont renseignés', 'show_english_labels' => 'Afficher le libellé anglais dans les listes',
            'show_category_in_role_picklist' => 'Afficher la famille d’emplois dans les listes', 'append_secondaries_to_primary_display' => 'Fusionner les emplois secondaires dans le libellé principal du dossier'] as $jaKey => $jaLabel): ?>
        <label class="ja-check"><input type="hidden" name="<?= $jaE($jaKey) ?>" value="0"><input type="checkbox" name="<?= $jaE($jaKey) ?>" value="1" <?= !empty($jaSettings[$jaKey]) ? 'checked' : '' ?>><?= $jaE($jaLabel) ?></label>
        <?php endforeach; ?>
        <button class="eff-btn">Enregistrer les paramètres</button>
    </form>
</details>
<?php endif; ?>
<?php endif; ?>
</section>
<?php if (empty($jobsFragment)): ?><link rel="stylesheet" href="<?= $jaE(asset_url('assets/css/job-assignments-board.css')) ?>"><script src="<?= $jaE(asset_url('assets/js/job-assignments-board.js')) ?>" defer></script><?php endif; ?>
