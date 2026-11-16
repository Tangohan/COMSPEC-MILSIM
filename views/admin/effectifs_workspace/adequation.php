<?php
declare(strict_types=1);

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacyEngine;
use App\Services\Organization\OrgAdequacyImpact;
use App\Services\Organization\OrgAdequacyService;
use App\Services\Organization\OrgAdequacySettings;

/*
 * Effectifs › Chaîne › Adéquation de l’organisation.
 * En-tête en bande (back-office-bands.css), trois onglets : vue d’ensemble (écarts actuels par type), règles
 * (interrupteurs par thème, attentes appliquées, seuil de surqualification) et échelons (liste compacte, une fenêtre
 * par échelon en quatre étapes). Aide « Comment ça marche » et aide contextuelle « ? » sur chaque notion.
 * Enregistrement : un seul formulaire POST chaine/adequation, champs org_adequacy[…] inchangés
 * (OrgAdequacySettings::sanitize). Styles : eff-adequacy-page.css (.adqp-*) ; comportement : eff-adequacy-page.js.
 */

$settings = is_array($adequacySettings ?? null) ? $adequacySettings : OrgAdequacySettings::defaults();
$profile = EchelonStandards::sanitizeProfile((string) ($adequacyProfile ?? EchelonStandards::PROFILE_FR));
$standards = is_array($adequacyStandards ?? null) ? $adequacyStandards : [];
$gradeLabels = is_array($adequacyGradeLabels ?? null) ? $adequacyGradeLabels : [];
$qualifications = is_array($adequacyQualifications ?? null) ? $adequacyQualifications : [];
$jobRoles = is_array($adequacyJobRoles ?? null) ? $adequacyJobRoles : [];
$result = is_array($adequacyResult ?? null) ? $adequacyResult : null;
$canEdit = !empty($canEditAdequacy);
$csrf = (string) ($csrfToken ?? '');
$openEchelon = (string) ($adequacyOpenEchelon ?? '');
$modalError = trim((string) ($adequacyModalError ?? ''));
$viewerId = (int) ($adequacyViewerId ?? 0);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$overrides = is_array($settings['echelons'] ?? null) ? $settings['echelons'] : [];
$dis = $canEdit ? '' : ' disabled';
$tab = (string) ($adequacyTab ?? '');
$tab = in_array($tab, ['ensemble', 'regles', 'echelons'], true) ? $tab : ($openEchelon !== '' ? 'echelons' : 'ensemble');
$pageUrl = effectifs_workspace_url('chaine/adequation');
$memberUrl = static fn (int $id): string => effectifs_member_url($id);

$profileLabels = [
    OrgAdequacySettings::PROFILE_AUTO => 'Selon la doctrine de la communauté',
    EchelonStandards::PROFILE_FR => 'Référentiel français',
    EchelonStandards::PROFILE_US => 'Référentiel américain',
    EchelonStandards::PROFILE_GB => 'Référentiel britannique',
    EchelonStandards::PROFILE_MY => 'Référentiel malaisien (modèle britannique)',
    EchelonStandards::PROFILE_DE => 'Référentiel allemand (Bundeswehr)',
    EchelonStandards::PROFILE_RU => 'Référentiel russe',
    EchelonStandards::PROFILE_CN => 'Référentiel chinois (Armée populaire de libération)',
    EchelonStandards::PROFILE_NATO => 'Référentiel OTAN générique',
];
$profileName = $profileLabels[$profile] ?? 'Référentiel français';

// Chiffres clés.
$enabled = !empty($settings['enabled']);
$evaluated = $enabled && $result !== null && !empty($result['enabled']);
$issueCount = $evaluated ? count(is_array($result['issues'] ?? null) ? $result['issues'] : []) : 0;
$echelonsOn = count(array_filter($standards, static fn (array $s): bool => !empty($s['enabled'])));
$rulesOn = count(array_filter(is_array($settings['rules'] ?? null) ? $settings['rules'] : [], static fn ($v): bool => !empty($v)));
$impact = $evaluated ? OrgAdequacyImpact::byEchelon($result) : [];
$groups = $evaluated ? OrgAdequacyImpact::grouped($result) : array_fill_keys(array_keys(OrgAdequacyImpact::GROUPS), []);
$people = $evaluated && is_array($result['people'] ?? null) ? $result['people'] : [];
$units = $evaluated && is_array($result['units'] ?? null) ? $result['units'] : [];
$customCount = count(array_filter(array_keys($standards), static fn (string $k): bool => OrgAdequacyImpact::customized($settings, $k)));
$plural = static fn (int $n, string $one, string $many): string => $n > 1 ? $many : $one;

/** Icônes au trait (SVG), décoratives. */
$icon = static function (string $name): string {
    $paths = [
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14"/><path d="M12 17h.01"/>',
        'pencil' => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'undo' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'back' => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'next' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'minus' => '<path d="M5 12h14"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'unit' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
    ];

    return '<svg class="adqp-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
};

/** Aide contextuelle : bouton « ? » et sa bulle (définition et exemple). */
$helpTexts = [
    'grade_min' => ['Grade minimum', 'Le grade le plus bas accepté pour tenir le poste. En dessous, un écart « sous-capacité de commandement » est signalé.', 'Minimum Sergeant (OR-5) : un chef Corporal (OR-4) est signalé.'],
    'grade_max' => ['Grade maximum', 'Le haut de la plage attendue. Au-delà de ce grade, plus le seuil de surqualification, le titulaire est signalé surqualifié.', 'Maximum OR-6 et seuil de 2 : un chef OR-8 ou plus est signalé.'],
    'deputy' => ['Adjoint exigé', 'Indique si l’échelon doit avoir un adjoint désigné, capable de reprendre le commandement en l’absence du chef.', 'Exigé pour un Platoon : sans platoon sergeant désigné, l’écart « adjoint attendu » apparaît.'],
    'deputy_grade' => ['Grades de l’adjoint', 'La plage de grades attendue pour l’adjoint, comparée comme celle du chef.', 'Adjoint attendu OR-7 : un adjoint OR-5 est en sous-capacité.'],
    'strength' => ['Effectif', 'Le nombre de personnes de l’unité, sous-unités, chef et adjoint compris. Hors de la plage, l’écart est indicatif.', 'Plage de 8 à 9 : un squad de 6 personnes est en sous-effectif.'],
    'subunits' => ['Sous-unités', 'Le nombre minimum d’unités subordonnées de l’échelon attendu, telles que l’ORBAT les décrit.', 'Minimum de 2 pour un Squad : avec une seule fire team, l’écart est signalé.'],
    'quals' => ['Qualifications de commandement', 'Les qualifications que le chef doit toutes détenir. Le référentiel n’en impose aucune : vous les choisissez parmi celles de la communauté.', 'Exiger « Brevet de chef de groupe » : un chef qui ne l’a pas est signalé.'],
    'jobs' => ['Emploi attendu', 'Les emplois dont le chef doit tenir au moins un. Laissez vide pour ne rien exiger.', 'Emploi « Squad leader » attendu : un chef sans cet emploi est signalé.'],
    'profile' => ['Attentes appliquées', 'Le référentiel doctrinal qui donne les valeurs de départ de chaque échelon. Vos ajustements par échelon s’appliquent par-dessus.', 'Référentiel américain : un Squad attend un Staff Sergeant (OR-6) et 8 à 9 personnes.'],
    'margin' => ['Seuil de surqualification', 'Le nombre de niveaux de grade au-dessus du maximum attendu à partir duquel un titulaire est signalé surqualifié.', 'Maximum OR-6 et seuil de 2 : signalé à partir d’OR-8.'],
];
$help = static function (string $key, string $uid) use ($helpTexts, $h): array {
    [$title, $def, $example] = $helpTexts[$key];
    $id = 'adqp-help-' . $uid;
    $btn = '<button type="button" class="adqp-q" aria-expanded="false" aria-controls="' . $h($id) . '" aria-label="' . $h('Aide : ' . $title) . '"><span aria-hidden="true">?</span></button>';
    $pop = '<div class="adqp-pop" id="' . $h($id) . '" role="note" hidden>'
        . '<p class="adqp-pop__t">' . $h($title) . '</p>'
        . '<p>' . $h($def) . '</p>'
        . '<p class="adqp-pop__ex"><b>Exemple</b> <span>' . $h($example) . '</span></p></div>';

    return [$btn, $pop];
};

/** Pastilles d’état d’un champ : valeur du référentiel ou valeur personnalisée. */
$statePills = static function (bool $custom): string {
    return '<span class="adqp-pill adqp-pill--ref" data-adqp-is="ref"' . ($custom ? ' hidden' : '') . '>référentiel</span>'
        . '<span class="adqp-pill adqp-pill--custom" data-adqp-is="custom"' . ($custom ? '' : ' hidden') . '>personnalisé</span>';
};
$resetBtn = static function (string $label, bool $custom) use ($h, $icon, $canEdit): string {
    if (!$canEdit) {
        return '';
    }

    return '<button type="button" class="adqp-reset" data-adqp-reset aria-label="' . $h(str_replace('%1', $label, 'Rétablir la valeur du référentiel : %1')) . '"' . ($custom ? '' : ' hidden') . '>' . $icon('undo') . '<span>Rétablir</span></button>';
};

/** Libellé lisible d’un grade (« Sergent (OR-5) », sinon le code OTAN). */
$gradeName = static function (int $level) use ($gradeLabels): string {
    $labels = array_values(array_filter(array_map('trim', $gradeLabels[$level] ?? []), static fn (string $l): bool => $l !== ''));

    return $labels === [] ? GradeLevel::code($level) : implode(' / ', $labels) . ' (' . GradeLevel::code($level) . ')';
};

/** Champ de grade (code OTAN) : vide = valeur du référentiel. */
$gradeField = static function (string $name, string $id, string $label, int $default, ?string $current, string $helpKey) use ($h, $dis, $gradeName, $help, $statePills, $resetBtn): string {
    $custom = $current !== null && $current !== '';
    [$qBtn, $qPop] = $help($helpKey, $id);
    $out = '<div class="adqp-field" data-adqp-field>'
        . '<div class="adqp-field__head"><label class="adqp-field__label" for="' . $h($id) . '">' . $h($label) . '</label>' . $qBtn . $statePills($custom) . '</div>'
        . $qPop
        . '<div class="adqp-field__row"><select class="adqp-select' . ($custom ? '' : ' is-ref') . '" name="' . $h($name) . '" id="' . $h($id) . '" data-adqp-input' . $dis . '>'
        . '<option value="">' . $h('Référentiel : ' . $gradeName($default)) . '</option>';
    foreach ([['OR-1 – OR-9', 1, 9], ['OF-1 – OF-10', 10, GradeLevel::MAX]] as [$group, $from, $to]) {
        $out .= '<optgroup label="' . $h($group) . '">';
        for ($l = $from; $l <= $to; $l++) {
            $code = GradeLevel::code($l);
            $out .= '<option value="' . $h($code) . '"' . ($current === $code ? ' selected' : '') . '>' . $h($gradeName($l)) . '</option>';
        }
        $out .= '</optgroup>';
    }

    return $out . '</select>' . $resetBtn($label, $custom) . '</div>'
        . '<p class="adqp-field__ref" data-adqp-ref-line' . ($custom ? '' : ' hidden') . '><span>Référentiel</span> <b data-adqp-default>' . $h($gradeName($default)) . '</b></p></div>';
};

/** Champ numérique : vide = valeur du référentiel (affichée en grisé). */
$numberField = static function (string $name, string $id, string $label, int $default, ?int $current, string $helpKey) use ($h, $dis, $help, $statePills, $resetBtn): string {
    $custom = $current !== null;
    [$qBtn, $qPop] = $help($helpKey, $id);

    return '<div class="adqp-field" data-adqp-field>'
        . '<div class="adqp-field__head"><label class="adqp-field__label" for="' . $h($id) . '">' . $h($label) . '</label>' . $qBtn . $statePills($custom) . '</div>'
        . $qPop
        . '<div class="adqp-field__row"><input class="adqp-num" type="number" inputmode="numeric" min="0" max="20000" name="' . $h($name) . '" id="' . $h($id) . '" value="' . ($custom ? (int) $current : '') . '" placeholder="' . $default . '" data-adqp-input' . $dis . '>'
        . $resetBtn($label, $custom) . '</div>'
        . '<p class="adqp-field__ref" data-adqp-ref-line' . ($custom ? '' : ' hidden') . '><span>Référentiel</span> <b data-adqp-default>' . $default . '</b></p></div>';
};

/** Liste de cases (qualifications, emplois) : aucune cochée = rien d’exigé (valeur du référentiel). */
$checksField = static function (string $name, string $id, string $label, array $options, array $selected, string $helpKey, string $emptyText) use ($h, $dis, $help, $statePills, $resetBtn): string {
    $custom = $selected !== [];
    [$qBtn, $qPop] = $help($helpKey, $id);
    $out = '<fieldset class="adqp-field adqp-field--checks" data-adqp-field>'
        . '<legend class="adqp-vh" id="' . $h($id) . '-l">' . $h($label) . '</legend>'
        . '<div class="adqp-field__head"><span class="adqp-field__label" aria-hidden="true">' . $h($label) . '</span>' . $qBtn . $statePills($custom) . $resetBtn($label, $custom) . '</div>'
        . $qPop;
    if ($options === []) {
        return $out . '<p class="adqp-field__empty">Aucun élément dans le référentiel de la communauté.</p></fieldset>';
    }
    if (count($options) > 8) {
        $out .= '<input class="adqp-filter" type="search" data-adqp-filter aria-label="' . $h('Filtrer : ' . $label) . '" placeholder="' . $h('Filtrer la liste') . '">';
    }
    $out .= '<ul class="adqp-checks" aria-labelledby="' . $h($id) . '-l">';
    foreach ($options as $i => $o) {
        $oid = (int) ($o['id'] ?? 0);
        $cid = $id . '-' . $oid;
        $out .= '<li><label for="' . $h($cid) . '"><input type="checkbox" id="' . $h($cid) . '" name="' . $h($name) . '[]" value="' . $oid . '"'
            . (in_array($oid, $selected, true) ? ' checked' : '') . ' data-adqp-input' . $dis . '> <span translate="no">' . $h((string) ($o['label'] ?? '')) . '</span></label></li>';
    }

    return $out . '</ul><p class="adqp-field__ref"><span>Référentiel</span> <b data-adqp-default>' . $h($emptyText) . '</b></p></fieldset>';
};

/** Résumé lisible d’un échelon effectif (liste et récapitulatif). */
$summary = static function (array $std) use ($gradeLabels): array {
    $lead = GradeLevel::rangeLabel((int) $std['leader_grade'][0], (int) $std['leader_grade'][1], $gradeLabels);
    if (empty($std['deputy_required'])) {
        $dep = 'non';
    } elseif (!empty($std['deputy_from_subunit'])) {
        $dep = 'chef de sous-unité le plus ancien';
    } elseif (is_array($std['deputy_grade'] ?? null)) {
        $dep = GradeLevel::rangeLabel((int) $std['deputy_grade'][0], (int) $std['deputy_grade'][1], $gradeLabels);
    } else {
        $dep = 'oui';
    }
    $rows = [
        ['Chef', $lead],
        ['Adjoint', $dep],
        ['Effectif', OrgAdequacyEngine::rangeText($std['strength'])],
        ['Sous-unités', (int) $std['subunits'][1] > 0 ? OrgAdequacyEngine::rangeText($std['subunits']) : 'aucune'],
    ];
    if (($std['qualification_ids'] ?? []) !== []) {
        $rows[] = ['Qualifications', (string) count($std['qualification_ids'])];
    }
    if (($std['job_role_ids'] ?? []) !== []) {
        $rows[] = ['Emplois', (string) count($std['job_role_ids'])];
    }

    return $rows;
};

// Vue d’ensemble : un type d’écart par carte.
$groupTexts = [
    'under' => ['Sous-capacité de commandement', 'Un chef ou un adjoint dont le grade, la qualification ou l’emploi est en dessous de ce que demande l’échelon.'],
    'vacant' => ['Poste de chef à pourvoir', 'Une unité dont personne n’est désigné chef.'],
    'deputy' => ['Adjoint attendu', 'L’échelon prévoit un adjoint, mais personne n’est désigné pour reprendre le commandement.'],
    'placement' => ['Personnes sans place', 'Un membre actif sans affectation, ou sans aucun chef au-dessus de lui.'],
    'over' => ['Surqualification', 'Un grade nettement au-dessus de la plage attendue, ou des qualifications d’un échelon supérieur : une expérience qui servirait plus haut.'],
    'strength' => ['Effectif hors plage', 'L’unité compte moins ou plus de personnes que l’échelon ne le prévoit ; signalé à titre indicatif.'],
    'subunits' => ['Sous-unités attendues', 'Il manque des sous-unités du bon échelon, par exemple une fire team dans un squad.'],
    'info' => ['Informations à compléter', 'Grade, équivalence OTAN ou échelon manquant : le poste ne peut pas être évalué.'],
];
$toneLabels = ['warn' => 'À traiter', 'info' => 'À examiner', 'muted' => 'À compléter'];

// Règles regroupées par thème : [titre, phrase, effet concret].
$ruleTexts = [
    'vacancy' => ['Poste de chef à pourvoir', 'Signale chaque unité sans chef désigné.', 'La chaîne de commandement propose des candidats du grade attendu.'],
    'deputy' => ['Adjoint attendu', 'Signale les unités dont l’échelon exige un adjoint qui n’est pas désigné.', 'Écart « adjoint manquant » sur l’unité.'],
    'grade_below' => ['Sous-capacité de commandement (grade)', 'Compare le grade du chef et de l’adjoint au minimum attendu par l’échelon.', 'Écart sur la fiche du titulaire et dans les alertes RH ; un grade absent est signalé à compléter.'],
    'grade_above' => ['Surqualification (grade ou qualifications)', 'Repère les grades nettement au-dessus de la plage et les qualifications d’un échelon supérieur.', 'Signalement pour information, jamais une alerte.'],
    'qualifications' => ['Qualifications de commandement attendues', 'Vérifie que le chef détient les qualifications choisies pour son échelon.', 'Sans qualification choisie dans Échelons, rien n’est vérifié.'],
    'functions' => ['Emploi attendu du chef', 'Vérifie que le chef tient l’un des emplois choisis pour son échelon.', 'Sans emploi choisi dans Échelons, rien n’est vérifié.'],
    'strength' => ['Effectif hors plage', 'Compare le nombre de personnes de l’unité à la plage de l’échelon.', 'Indicatif : une unité est rarement au complet.'],
    'subunits' => ['Sous-unités attendues', 'Compte les sous-unités du bon échelon sous chaque unité.', 'Écart « sous-unités manquantes » sur l’unité.'],
    'placement' => ['Personnes sans place ou sans chef', 'Repère les membres sans affectation, ou sans aucun chef au-dessus d’eux.', 'La personne apparaît dans la vue d’ensemble et dans la chaîne.'],
    'echelon_unknown' => ['Unités sans échelon renseigné', 'Signale les unités peuplées dont l’échelon n’est pas choisi dans l’ORBAT.', 'Sans échelon, l’unité n’est pas évaluée.'],
];
$ruleThemes = [
    ['Postes de commandement', 'Qui commande, et qui le seconde.', ['vacancy', 'deputy']],
    ['Niveau du chef et de l’adjoint', 'Grade, qualifications et emploi au regard de l’échelon.', ['grade_below', 'grade_above', 'qualifications', 'functions']],
    ['Structure des unités', 'Effectif, sous-unités et échelon renseigné.', ['strength', 'subunits', 'echelon_unknown']],
    ['Place des personnes', 'Chaque membre a une place et un chef.', ['placement']],
];
$margin = (int) ($settings['overqualification_margin'] ?? 2);
$steps = ['Chef attendu', 'Adjoint attendu', 'Effectif et sous-unités', 'Récapitulatif'];
$tabs = [
    'ensemble' => ['Vue d’ensemble', $evaluated ? $issueCount : null],
    'regles' => ['Règles', $rulesOn],
    'echelons' => ['Échelons', $echelonsOn],
];
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/back-office-bands.css')) ?>">
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/eff-adequacy-page.css')) ?>">
<div class="adqp" data-eff-adequacy data-adqp
     data-adqp-preview="<?= $h($pageUrl . '/apercu') ?>"
     data-adqp-viewer="<?= $viewerId ?>"
     data-adqp-open-echelon="<?= $h($openEchelon) ?>"
     data-adqp-editable="<?= $canEdit ? '1' : '0' ?>">

    <header class="bo-band bo-band--hero bo-band--emerald adqp-hero" aria-labelledby="adqp-title">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__who">
                <h2 class="bo-band__title" id="adqp-title">Adéquation de l’organisation</h2>
                <p class="bo-band__line">Compare votre organisation réelle à ce que chaque échelon devrait avoir.</p>
                <div class="bo-band__actions">
                    <button type="button" class="bo-band__btn bo-band__btn--solid" data-adqp-help-open><?= $icon('help') ?><span>Comment ça marche</span></button>
                    <a class="bo-band__btn" href="<?= $h(effectifs_workspace_url('chaine') . '#eff-adequacy') ?>"><?= $icon('back') ?><span>Chaîne de commandement</span></a>
                </div>
            </div>
            <div class="bo-band__side">
                <p class="bo-band__meta"><span>Attentes</span> <?= $h($profileName) ?></p>
                <div class="adqp-figs">
                    <p class="bo-figure<?= $issueCount > 0 ? ' is-hot' : '' ?>"><strong><?= $evaluated ? $issueCount : '—' ?></strong><span><?= $enabled ? $h($plural($issueCount, 'écart détecté', 'écarts détectés')) : 'contrôle désactivé' ?></span></p>
                    <p class="bo-figure"><strong><?= $echelonsOn ?><small>/<?= count($standards) ?></small></strong><span>échelons évalués</span></p>
                    <p class="bo-figure"><strong><?= $rulesOn ?><small>/<?= count(OrgAdequacySettings::RULES) ?></small></strong><span>règles actives</span></p>
                </div>
            </div>
        </div>
    </header>

    <div class="adqp-tabs" role="tablist" aria-label="Sections de l’adéquation">
        <?php foreach ($tabs as $tk => [$tl, $tn]): ?>
            <a class="adqp-tab" role="tab" id="adqp-tab-<?= $h($tk) ?>" href="<?= $h($pageUrl . '?onglet=' . $tk) ?>"
               aria-controls="adqp-panel-<?= $h($tk) ?>" aria-selected="<?= $tab === $tk ? 'true' : 'false' ?>"
               tabindex="<?= $tab === $tk ? '0' : '-1' ?>" data-adqp-tab="<?= $h($tk) ?>">
                <span><?= $h($tl) ?></span><?php if ($tn !== null): ?> <span class="adqp-tab__n"><?= (int) $tn ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php /* ——— 01 Vue d’ensemble ——— */ ?>
    <section class="adqp-panel bo-section" role="tabpanel" id="adqp-panel-ensemble" aria-labelledby="adqp-tab-ensemble" tabindex="0"<?= $tab !== 'ensemble' ? ' hidden' : '' ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 01</span>
            <h3 class="bo-section__title">Écarts actuels</h3>
            <p class="bo-section__desc">Ce qui ne correspond pas aux attentes aujourd’hui, regroupé par type ; ouvrez un type pour aller à l’unité ou au membre.</p>
        </header>
        <?php if (!$enabled): ?>
            <div class="bo-band bo-band--empty bo-band--amber">
                <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
                <div class="bo-band__glow" aria-hidden="true"></div>
                <div class="bo-band__veil" aria-hidden="true"></div>
                <div class="bo-band__body">
                    <div class="bo-band__who">
                        <h4 class="bo-band__title">Le contrôle est désactivé</h4>
                        <p class="bo-band__line">Aucun écart n’est calculé : la chaîne, la fiche membre et les alertes RH n’en affichent pas.</p>
                        <button type="button" class="bo-band__btn bo-band__btn--solid" data-adqp-goto="regles"><span>Ouvrir les règles</span><span class="bo-band__arrow" aria-hidden="true">→</span></button>
                    </div>
                </div>
            </div>
        <?php elseif (!$evaluated): ?>
            <p class="adqp-note" role="status">L’évaluation est momentanément indisponible. Les règles et les échelons restent modifiables.</p>
        <?php else: ?>
            <?php if ($rulesOn === 0): ?>
                <p class="adqp-note" role="status">Aucune règle active : rien n’est vérifié. Activez des règles dans l’onglet Règles.</p>
            <?php elseif ($issueCount === 0): ?>
                <p class="adqp-allgood" role="status"><?= $icon('check') ?><span>Aucun écart : chaque personne a sa place et chaque poste est tenu au niveau attendu.</span></p>
            <?php endif; ?>
            <ul class="adqp-types">
                <?php foreach (OrgAdequacyImpact::GROUPS as $gk => $gdef):
                    [$gTitle, $gText] = $groupTexts[$gk];
                    $gIssues = $groups[$gk] ?? [];
                    $gCount = count($gIssues);
                    $gOff = OrgAdequacyImpact::groupDisabled($gk, $settings);
                    $gTone = (string) $gdef['tone'];
                    ?>
                    <li class="adqp-type is-<?= $h($gTone) ?><?= $gCount === 0 ? ' is-zero' : '' ?><?= $gOff ? ' is-off' : '' ?>" data-adqp-group="<?= $h($gk) ?>">
                        <div class="adqp-type__head">
                            <h4 class="adqp-type__title" id="adqp-type-<?= $h($gk) ?>"><?= $h($gTitle) ?></h4>
                            <strong class="adqp-type__count" aria-label="<?= $h($gCount . ' ' . $plural($gCount, 'écart', 'écarts')) ?>"><?= $gCount ?></strong>
                        </div>
                        <p class="adqp-type__text"><?= $h($gText) ?></p>
                        <div class="adqp-type__foot">
                            <?php if ($gOff): ?>
                                <span class="adqp-pill">Règle désactivée</span>
                                <button type="button" class="adqp-link" data-adqp-goto="regles">Activer dans Règles</button>
                            <?php elseif ($gCount === 0): ?>
                                <span class="adqp-pill adqp-pill--ok"><?= $icon('check') ?>Rien à signaler</span>
                            <?php else: ?>
                                <span class="adqp-pill adqp-pill--<?= $h($gTone) ?>"><?= $h($toneLabels[$gTone] ?? '') ?></span>
                                <button type="button" class="adqp-btn" data-adqp-open="adqp-dlg-type-<?= $h($gk) ?>" aria-describedby="adqp-type-<?= $h($gk) ?>"><?= $h($gCount > 1 ? str_replace('%1', (string) $gCount, 'Voir les %1 écarts') : 'Voir l’écart') ?></button>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= $h($pageUrl) ?>" id="adqp-form" class="adqp-form" data-adqp-form novalidate>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <input type="hidden" name="adqp_tab" value="<?= $h($tab) ?>" data-adqp-tabfield>
        <input type="hidden" name="adqp_echelon" value="" data-adqp-echfield>

        <?php /* ——— 02 Règles ——— */ ?>
        <section class="adqp-panel bo-section" role="tabpanel" id="adqp-panel-regles" aria-labelledby="adqp-tab-regles" tabindex="0"<?= $tab !== 'regles' ? ' hidden' : '' ?>>
            <header class="bo-section__head">
                <span class="bo-section__index" aria-hidden="true">/ 02</span>
                <h3 class="bo-section__title">Règles</h3>
                <p class="bo-section__desc">Ce qui est contrôlé, d’après quel référentiel et avec quelle tolérance.</p>
            </header>

            <div class="adqp-cards adqp-cards--top">
                <div class="adqp-card">
                    <div class="adqp-switch">
                        <input type="hidden" name="org_adequacy[enabled]" value="0">
                        <input type="checkbox" role="switch" id="adqp-enabled" name="org_adequacy[enabled]" value="1"<?= $enabled ? ' checked' : '' ?><?= $dis ?> data-adqp-input>
                        <label for="adqp-enabled"><b>Contrôler l’adéquation de l’organisation</b><span>Désactivé, aucun écart n’est calculé : la chaîne, la fiche membre et les alertes RH n’en affichent plus.</span></label>
                    </div>
                </div>

                <div class="adqp-card">
                    <?php [$qBtn, $qPop] = $help('profile', 'profile'); ?>
                    <div class="adqp-field__head"><label class="adqp-card__title" for="adq-profile">Attentes appliquées</label><?= $qBtn ?></div>
                    <?= $qPop ?>
                    <select class="adqp-select" id="adq-profile" name="org_adequacy[profile]"<?= $dis ?> data-adqp-input>
                        <?php foreach ($profileLabels as $pk => $pl): ?>
                            <option value="<?= $h($pk) ?>"<?= ($settings['profile'] ?? '') === $pk ? ' selected' : '' ?>><?= $h($pl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="adqp-card__text">« Selon la doctrine de la communauté » suit la doctrine et le système de grades choisis dans les réglages. Retenu aujourd’hui : <b><?= $h($profileName) ?></b>.</p>
                </div>

                <div class="adqp-card">
                    <?php [$qBtn, $qPop] = $help('margin', 'margin'); ?>
                    <div class="adqp-field__head"><label class="adqp-card__title" for="adq-margin">Seuil de surqualification</label><?= $qBtn ?></div>
                    <?= $qPop ?>
                    <div class="adqp-stepper" data-adqp-stepper>
                        <button type="button" class="adqp-stepper__btn" data-adqp-step-by="-1" aria-label="Diminuer le seuil" aria-controls="adq-margin"<?= $dis ?>><?= $icon('minus') ?></button>
                        <input class="adqp-num adqp-stepper__in" type="number" min="1" max="6" id="adq-margin" name="org_adequacy[overqualification_margin]" value="<?= $margin ?>"<?= $dis ?> data-adqp-input data-adqp-margin>
                        <button type="button" class="adqp-stepper__btn" data-adqp-step-by="1" aria-label="Augmenter le seuil" aria-controls="adq-margin"<?= $dis ?>><?= $icon('plus') ?></button>
                        <span class="adqp-stepper__unit">niveaux au-dessus de la plage</span>
                    </div>
                    <p class="adqp-card__text" aria-live="polite">Exemple : poste attendu jusqu’à <b>OR-6</b> ; avec <b data-adqp-margin-n><?= $margin ?></b> niveaux, un titulaire <b data-adqp-margin-ex><?= $h(GradeLevel::code(6 + $margin)) ?></b> ou plus est signalé.</p>
                </div>
            </div>

            <div class="adqp-cards">
                <?php foreach ($ruleThemes as [$themeTitle, $themeText, $themeRules]): ?>
                    <fieldset class="adqp-card adqp-theme">
                        <legend class="adqp-card__title"><?= $h($themeTitle) ?></legend>
                        <p class="adqp-card__text"><?= $h($themeText) ?></p>
                        <?php foreach ($themeRules as $rk):
                            [$rTitle, $rText, $rEffect] = $ruleTexts[$rk];
                            ?>
                            <div class="adqp-switch">
                                <input type="hidden" name="org_adequacy[rules][<?= $h($rk) ?>]" value="0">
                                <input type="checkbox" role="switch" id="adqp-rule-<?= $h($rk) ?>" name="org_adequacy[rules][<?= $h($rk) ?>]" value="1"<?= !empty($settings['rules'][$rk]) ? ' checked' : '' ?><?= $dis ?> data-adqp-input aria-describedby="adqp-rule-<?= $h($rk) ?>-fx">
                                <label for="adqp-rule-<?= $h($rk) ?>"><b><?= $h($rTitle) ?></b><span><?= $h($rText) ?></span></label>
                                <p class="adqp-switch__fx" id="adqp-rule-<?= $h($rk) ?>-fx"><span>Effet</span> <?= $h($rEffect) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>

            <?php if ($canEdit): ?>
                <div class="adqp-danger">
                    <p><b>Tout rétablir</b> <span>Règles, référentiel, seuil et échelons reviennent aux valeurs d’origine.</span></p>
                    <button type="button" class="adqp-btn adqp-btn--danger" data-adqp-open="adqp-dlg-reset"><?= $icon('undo') ?><span>Rétablir les valeurs par défaut</span></button>
                </div>
            <?php endif; ?>
        </section>

        <?php /* ——— 03 Échelons ——— */ ?>
        <section class="adqp-panel bo-section" role="tabpanel" id="adqp-panel-echelons" aria-labelledby="adqp-tab-echelons" tabindex="0"<?= $tab !== 'echelons' ? ' hidden' : '' ?>>
            <header class="bo-section__head">
                <span class="bo-section__index" aria-hidden="true">/ 03</span>
                <h3 class="bo-section__title">Échelons</h3>
                <div class="bo-section__aside">
                    <span class="bo-chip"><b><?= $customCount ?></b> <?= $h($plural($customCount, 'personnalisé', 'personnalisés')) ?></span>
                </div>
                <p class="bo-section__desc">Ce que chaque échelon attend de son chef, de son adjoint et de son effectif ; le crayon ouvre le détail.</p>
            </header>
            <ul class="adqp-ech-list">
                <?php foreach ($standards as $key => $std):
                    $key = (string) $key;
                    $isCustom = OrgAdequacyImpact::customized($settings, $key);
                    $f = 'org_adequacy[echelons][' . $key . ']';
                    $stat = $impact[$key] ?? null;
                    ?>
                    <li class="adqp-ech<?= empty($std['enabled']) ? ' is-off' : '' ?>" data-adqp-ech="<?= $h($key) ?>">
                        <div class="adqp-ech__main">
                            <div class="adqp-ech__head">
                                <h4 class="adqp-ech__name" id="adqp-ech-name-<?= $h($key) ?>"><?= $h((string) $std['label']) ?></h4>
                                <?php if ($isCustom): ?><span class="adqp-pill adqp-pill--custom">personnalisé</span><?php endif; ?>
                                <span class="adqp-pill adqp-pill--warn" data-adqp-unsaved hidden>non enregistré</span>
                            </div>
                            <p class="adqp-ech__desc"><?= $h((string) $std['composition']) ?></p>
                            <dl class="adqp-sum">
                                <?php foreach ($summary($std) as [$sk, $sv]): ?>
                                    <div><dt><?= $h($sk) ?></dt><dd><?= $h($sv) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                        <div class="adqp-ech__side">
                            <?php if ($stat !== null): ?>
                                <p class="adqp-ech__stat"><span><b><?= (int) $stat['units'] ?></b> <?= $h($plural((int) $stat['units'], 'unité', 'unités')) ?></span><span class="<?= (int) $stat['issues'] > 0 ? 'is-warn' : '' ?>"><b><?= (int) $stat['issues'] ?></b> <?= $h($plural((int) $stat['issues'], 'écart', 'écarts')) ?></span></p>
                            <?php endif; ?>
                            <div class="adqp-switch adqp-switch--compact">
                                <input type="hidden" name="<?= $h($f) ?>[enabled]" value="0">
                                <input type="checkbox" role="switch" id="adqp-ech-on-<?= $h($key) ?>" name="<?= $h($f) ?>[enabled]" value="1"<?= !empty($std['enabled']) ? ' checked' : '' ?><?= $dis ?> data-adqp-input data-adqp-ech-toggle aria-describedby="adqp-ech-name-<?= $h($key) ?>">
                                <label for="adqp-ech-on-<?= $h($key) ?>"><b>Évaluer cet échelon</b></label>
                            </div>
                            <button type="button" class="adqp-btn adqp-btn--edit" data-adqp-open="adqp-dlg-ech-<?= $h($key) ?>" aria-label="<?= $h(str_replace('%1', (string) $std['label'], $canEdit ? 'Modifier l’échelon %1' : 'Voir l’échelon %1')) ?>"><?= $icon($canEdit ? 'pencil' : 'eye') ?><span><?= $canEdit ? 'Modifier' : 'Voir' ?></span></button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$canEdit): ?>
                <p class="adqp-note">Lecture seule : les attentes sont modifiées par les gestionnaires des affectations.</p>
            <?php endif; ?>
        </section>

        <?php /* ——— Fenêtres des échelons (dans le formulaire : leurs champs partent avec « Enregistrer ») ——— */ ?>
        <?php foreach ($standards as $key => $std):
            $key = (string) $key;
            $o = is_array($overrides[$key] ?? null) ? $overrides[$key] : [];
            $def = is_array($std['default'] ?? null) ? $std['default'] : $std;
            $f = 'org_adequacy[echelons][' . $key . ']';
            $id = 'adq-' . $key;
            $depBase = is_array($def['deputy_grade'] ?? null) ? $def['deputy_grade'] : $def['leader_grade'];
            $hasSub = (int) $def['subunits'][1] > 0;
            $stat = $impact[$key] ?? null;
            $depDefaultText = !empty($def['deputy_required']) ? 'oui' : 'non';
            $depCurrent = array_key_exists('deputy_required', $o) ? ($o['deputy_required'] === true ? '1' : '0') : '';
            $dlgId = 'adqp-dlg-ech-' . $key;
            $isOpen = $openEchelon === $key;
            $subNames = [];
            foreach ($def['subunit_echelons'] ?? [] as $sk) {
                $subNames[] = (string) ($standards[$sk]['label'] ?? $sk);
            }
            ?>
            <dialog class="bo-dialog adqp-dlg adqp-dlg--wide" id="<?= $h($dlgId) ?>" aria-labelledby="<?= $h($dlgId) ?>-t" aria-describedby="<?= $h($dlgId) ?>-s" data-adqp-ech-dialog="<?= $h($key) ?>" data-adqp-steps>
                <div class="bo-dialog__head">
                    <div class="bo-dialog__titles">
                        <h3 class="bo-dialog__title" id="<?= $h($dlgId) ?>-t"><?= $h((string) $std['label']) ?></h3>
                        <p class="bo-dialog__sub" id="<?= $h($dlgId) ?>-s"><?= $h((string) $std['composition']) ?></p>
                    </div>
                    <button type="button" class="bo-dialog__close" data-adqp-close aria-label="Fermer"><?= $icon('x') ?></button>
                </div>
                <ol class="adqp-steps" aria-label="Étapes">
                    <?php foreach ($steps as $si => $sl): ?>
                        <li><button type="button" class="adqp-steps__it" data-adqp-goto-step="<?= $si ?>"<?= $si === 0 ? ' aria-current="step"' : '' ?>><span class="adqp-steps__n" aria-hidden="true"><?= $si + 1 ?></span><span class="adqp-steps__l"><?= $h($sl) ?></span></button></li>
                    <?php endforeach; ?>
                </ol>
                <div class="bo-dialog__body adqp-dlg__body">
                    <div class="adqp-dlg__err" role="alert" data-adqp-err<?= $isOpen && $modalError !== '' ? '' : ' hidden' ?>><?= $isOpen ? $h($modalError) : '' ?></div>
                    <?php if ($canEdit): ?>
                        <div class="adqp-dlg__tools">
                            <button type="button" class="adqp-btn adqp-btn--ghost" data-adqp-reset-echelon><?= $icon('undo') ?><span>Rétablir les valeurs du référentiel</span></button>
                        </div>
                    <?php endif; ?>

                    <div class="adqp-stepbody" data-adqp-stepbody="0">
                        <p class="adqp-stepbody__lead"><b><?= $h((string) $std['leader_label']) ?></b> <span>Le grade, l’emploi et les qualifications attendus du chef.</span></p>
                        <div class="adqp-grid2">
                            <?= $gradeField($f . '[leader_grade_min]', $id . '-lmin', 'Grade minimum du chef', (int) $def['leader_grade'][0], isset($o['leader_grade_min']) ? (string) $o['leader_grade_min'] : null, 'grade_min') ?>
                            <?= $gradeField($f . '[leader_grade_max]', $id . '-lmax', 'Grade maximum du chef', (int) $def['leader_grade'][1], isset($o['leader_grade_max']) ? (string) $o['leader_grade_max'] : null, 'grade_max') ?>
                        </div>
                        <?= $checksField($f . '[job_role_ids]', $id . '-j', 'Emploi attendu du chef', $jobRoles, array_map('intval', $std['job_role_ids']), 'jobs', 'aucun emploi exigé') ?>
                        <?= $checksField($f . '[qualification_ids]', $id . '-q', 'Qualifications de commandement', $qualifications, array_map('intval', $std['qualification_ids']), 'quals', 'aucune qualification exigée') ?>
                        <?php if (trim((string) ($std['qualification_hint'] ?? '')) !== ''): ?>
                            <p class="adqp-hint"><span>Repère doctrinal</span> <?= $h((string) $std['qualification_hint']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="adqp-stepbody" data-adqp-stepbody="1" hidden>
                        <p class="adqp-stepbody__lead"><b><?= $h((string) $std['deputy_label']) ?></b>
                            <?php if (!empty($def['deputy_from_subunit'])): ?><span>Le chef de sous-unité le plus ancien tient lieu d’adjoint quand aucun adjoint n’est nommé.</span><?php endif; ?></p>
                        <?php [$qBtn, $qPop] = $help('deputy', $id . '-dreq'); ?>
                        <fieldset class="adqp-field" data-adqp-field>
                            <legend class="adqp-vh">Adjoint exigé</legend>
                            <div class="adqp-field__head"><span class="adqp-field__label" aria-hidden="true">Adjoint exigé</span><?= $qBtn ?><?= $statePills($depCurrent !== '') ?></div>
                            <?= $qPop ?>
                            <div class="adqp-radios" id="<?= $h($id) ?>-dreq">
                                <label><input type="radio" name="<?= $h($f) ?>[deputy_required]" value=""<?= $depCurrent === '' ? ' checked' : '' ?><?= $dis ?> data-adqp-input data-adqp-ref-choice> <span>Comme le référentiel (<?= $h($depDefaultText) ?>)</span></label>
                                <label><input type="radio" name="<?= $h($f) ?>[deputy_required]" value="1"<?= $depCurrent === '1' ? ' checked' : '' ?><?= $dis ?> data-adqp-input> <span>Oui, un adjoint est exigé</span></label>
                                <label><input type="radio" name="<?= $h($f) ?>[deputy_required]" value="0"<?= $depCurrent === '0' ? ' checked' : '' ?><?= $dis ?> data-adqp-input> <span>Non, pas d’adjoint exigé</span></label>
                            </div>
                            <p class="adqp-field__ref"><span>Référentiel</span> <b data-adqp-default><?= $h($depDefaultText) ?></b></p>
                        </fieldset>
                        <div class="adqp-grid2">
                            <?= $gradeField($f . '[deputy_grade_min]', $id . '-dmin', 'Grade minimum de l’adjoint', (int) $depBase[0], isset($o['deputy_grade_min']) ? (string) $o['deputy_grade_min'] : null, 'deputy_grade') ?>
                            <?= $gradeField($f . '[deputy_grade_max]', $id . '-dmax', 'Grade maximum de l’adjoint', (int) $depBase[1], isset($o['deputy_grade_max']) ? (string) $o['deputy_grade_max'] : null, 'grade_max') ?>
                        </div>
                    </div>

                    <div class="adqp-stepbody" data-adqp-stepbody="2" hidden>
                        <p class="adqp-stepbody__lead"><span>Personnes de l’unité, sous-unités comprises<?= $hasSub ? ', et sous-unités attendues' : '' ?>.</span></p>
                        <div class="adqp-grid2">
                            <?= $numberField($f . '[strength_min]', $id . '-smin', 'Effectif minimum', (int) $def['strength'][0], isset($o['strength_min']) ? (int) $o['strength_min'] : null, 'strength') ?>
                            <?= $numberField($f . '[strength_max]', $id . '-smax', 'Effectif maximum', (int) $def['strength'][1], isset($o['strength_max']) ? (int) $o['strength_max'] : null, 'strength') ?>
                        </div>
                        <?php if ($hasSub): ?>
                            <?= $numberField($f . '[subunits_min]', $id . '-umin', 'Sous-unités minimum', (int) $def['subunits'][0], isset($o['subunits_min']) ? (int) $o['subunits_min'] : null, 'subunits') ?>
                            <?php if ($subNames !== []): ?><p class="adqp-hint"><span>Sous-unités comptées</span> <?= $h(implode(' / ', $subNames)) ?></p><?php endif; ?>
                        <?php else: ?>
                            <p class="adqp-hint"><span>Sous-unités</span> Aucune : cet échelon est le plus petit.</p>
                        <?php endif; ?>
                    </div>

                    <div class="adqp-stepbody" data-adqp-stepbody="3" hidden>
                        <div class="adqp-impact" data-adqp-impact="<?= $h($key) ?>" aria-live="polite">
                            <p class="adqp-impact__t">Ce que ça change</p>
                            <div class="adqp-impact__figs">
                                <p><strong><?= $stat !== null ? (int) $stat['units'] : '—' ?></strong><span>unités de cet échelon</span></p>
                                <p><strong data-adqp-now><?= $stat !== null ? (int) $stat['issues'] : '—' ?></strong><span>écarts aujourd’hui</span></p>
                                <p class="is-next"><strong data-adqp-next>—</strong><span>écarts avec ces valeurs</span></p>
                            </div>
                            <p class="adqp-impact__msg" data-adqp-impact-msg="idle">Calculé à partir de l’organisation actuelle, sans rien enregistrer.</p>
                            <p class="adqp-impact__msg" data-adqp-impact-msg="loading" hidden>Calcul en cours…</p>
                            <p class="adqp-impact__msg" data-adqp-impact-msg="error" hidden>Aperçu indisponible : les valeurs s’appliqueront à l’enregistrement.</p>
                            <p class="adqp-impact__msg" data-adqp-impact-msg="off" hidden>Cet échelon n’est pas évalué : ses unités ne produisent aucun écart.</p>
                        </div>
                        <dl class="adqp-recap">
                            <?php
                            $recapRows = [
                                [$id . '-lmin', 'Grade minimum du chef'], [$id . '-lmax', 'Grade maximum du chef'],
                                [$id . '-dreq', 'Adjoint exigé'], [$id . '-dmin', 'Grade minimum de l’adjoint'], [$id . '-dmax', 'Grade maximum de l’adjoint'],
                                [$id . '-smin', 'Effectif minimum'], [$id . '-smax', 'Effectif maximum'],
                            ];
                            if ($hasSub) {
                                $recapRows[] = [$id . '-umin', 'Sous-unités minimum'];
                            }
                            $recapRows[] = [$id . '-j', 'Emploi attendu du chef'];
                            $recapRows[] = [$id . '-q', 'Qualifications de commandement'];
                            foreach ($recapRows as [$rid, $rl]): ?>
                                <div data-adqp-recap="<?= $h($rid) ?>"><dt><?= $h($rl) ?></dt><dd><span data-adqp-recap-v>—</span> <?= $statePills(false) ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    </div>

                    <details class="adqp-sources">
                        <summary>Sources</summary>
                        <ul>
                            <?php foreach ($std['sources'] ?? [] as $src): ?><li><?= $h((string) $src) ?></li><?php endforeach; ?>
                        </ul>
                    </details>
                </div>
                <div class="bo-dialog__foot adqp-dlg__foot">
                    <?php if ($canEdit): ?>
                        <button type="button" class="bo-dialog__btn" data-adqp-cancel hidden><span>Annuler</span></button>
                    <?php endif; ?>
                    <span class="adqp-dlg__spacer"></span>
                    <button type="button" class="bo-dialog__btn" data-adqp-prev hidden><?= $icon('back') ?><span>Précédent</span></button>
                    <button type="button" class="bo-dialog__btn bo-dialog__btn--primary" data-adqp-next-step><span>Suivant</span><?= $icon('next') ?></button>
                    <?php if ($canEdit): ?>
                        <button type="submit" class="bo-dialog__btn bo-dialog__btn--primary" data-adqp-save-echelon="<?= $h($key) ?>" hidden><?= $icon('check') ?><span>Enregistrer</span></button>
                    <?php else: ?>
                        <button type="button" class="bo-dialog__btn" data-adqp-close-btn hidden><span>Fermer</span></button>
                    <?php endif; ?>
                </div>
            </dialog>
        <?php endforeach; ?>

        <?php if ($canEdit): ?>
            <dialog class="bo-dialog adqp-dlg" id="adqp-dlg-reset" aria-labelledby="adqp-dlg-reset-t">
                <div class="bo-dialog__head">
                    <div class="bo-dialog__titles">
                        <h3 class="bo-dialog__title" id="adqp-dlg-reset-t">Tout rétablir ?</h3>
                        <p class="bo-dialog__sub">Règles, attentes appliquées, seuil et réglages de chaque échelon reviennent aux valeurs d’origine. Les modifications non enregistrées sont perdues.</p>
                    </div>
                    <button type="button" class="bo-dialog__close" data-adqp-close aria-label="Fermer"><?= $icon('x') ?></button>
                </div>
                <div class="bo-dialog__foot">
                    <button type="button" class="bo-dialog__btn" data-adqp-close>Garder mes réglages</button>
                    <button type="submit" class="bo-dialog__btn bo-dialog__btn--danger" name="reset" value="1">Rétablir les valeurs par défaut</button>
                </div>
            </dialog>

            <div class="adqp-savebar" data-adqp-savebar<?= $tab === 'ensemble' ? ' hidden' : '' ?>>
                <p class="adqp-savebar__msg">
                    <span data-adqp-saved><?= $icon('check') ?>Tout est enregistré.</span>
                    <span data-adqp-dirty hidden>Modifications non enregistrées.</span>
                </p>
                <button type="submit" class="adqp-btn adqp-btn--primary" data-adqp-save><?= $icon('check') ?><span>Enregistrer</span></button>
            </div>
        <?php endif; ?>
    </form>

    <?php /* ——— Écarts d’un type ——— */ ?>
    <?php foreach (OrgAdequacyImpact::GROUPS as $gk => $gdef):
        $gIssues = $groups[$gk] ?? [];
        if ($gIssues === []) {
            continue;
        }
        [$gTitle, $gText] = $groupTexts[$gk];
        ?>
        <dialog class="bo-dialog adqp-dlg adqp-dlg--wide" id="adqp-dlg-type-<?= $h($gk) ?>" aria-labelledby="adqp-dlg-type-<?= $h($gk) ?>-t">
            <div class="bo-dialog__head">
                <div class="bo-dialog__titles">
                    <h3 class="bo-dialog__title" id="adqp-dlg-type-<?= $h($gk) ?>-t"><?= $h($gTitle) ?> <span class="adqp-dlg__count"><?= count($gIssues) ?></span></h3>
                    <p class="bo-dialog__sub"><?= $h($gText) ?></p>
                </div>
                <button type="button" class="bo-dialog__close" data-adqp-close aria-label="Fermer"><?= $icon('x') ?></button>
            </div>
            <div class="bo-dialog__body">
                <ul class="adqp-issues">
                    <?php foreach ($gIssues as $issue):
                        $iu = (int) ($issue['unit_id'] ?? 0);
                        $ip = (int) ($issue['user_id'] ?? 0);
                        $tone = (string) ($issue['tone'] ?? 'info');
                        ?>
                        <li class="adqp-issue is-<?= $h($tone) ?>" data-adq-code="<?= $h((string) ($issue['code'] ?? '')) ?>">
                            <p class="adqp-issue__text"><?= $h((string) ($issue['text'] ?? '')) ?></p>
                            <p class="adqp-issue__links">
                                <?php if ($iu > 0 && isset($units[$iu])): ?>
                                    <a class="adqp-chip" href="<?= $h(url('back-office/organisation/unites/' . $iu)) ?>"><?= $icon('unit') ?><span translate="no"><?= $h((string) $units[$iu]['name']) ?></span></a>
                                <?php endif; ?>
                                <?php if ($ip > 0): ?>
                                    <a class="adqp-chip" href="<?= $h($memberUrl($ip)) ?>"><?= $icon('user') ?><span translate="no"><?= $h((string) ($people[$ip]['label'] ?? 'Membre')) ?></span></a>
                                <?php endif; ?>
                                <?php if ($canEdit):
                                    foreach (is_array($issue['actions'] ?? null) ? $issue['actions'] : [] as $a):
                                        $link = OrgAdequacyService::actionLink(is_array($a) ? $a : []);
                                        if ($link === null) {
                                            continue;
                                        } ?>
                                        <a class="adqp-act" href="<?= $h($link['href']) ?>"><?= $h($link['label']) ?></a>
                                    <?php endforeach;
                                endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="bo-dialog__foot">
                <button type="button" class="bo-dialog__btn" data-adqp-close>Fermer</button>
            </div>
        </dialog>
    <?php endforeach; ?>

    <?php /* ——— Comment ça marche ——— */ ?>
    <dialog class="bo-dialog adqp-dlg adqp-dlg--wide adqp-guide" id="adqp-dlg-help" aria-labelledby="adqp-dlg-help-t" data-adqp-steps data-adqp-guide>
        <div class="bo-dialog__head">
            <div class="bo-dialog__titles">
                <h3 class="bo-dialog__title" id="adqp-dlg-help-t">Comment ça marche</h3>
                <p class="bo-dialog__sub">Quatre étapes pour lire et régler l’adéquation de l’organisation.</p>
            </div>
            <button type="button" class="bo-dialog__close" data-adqp-close aria-label="Fermer"><?= $icon('x') ?></button>
        </div>
        <ol class="adqp-steps" aria-label="Étapes">
            <?php foreach (['Ce qui est comparé', 'D’où viennent les attentes', 'Ce que signifie un écart', 'Comment ajuster'] as $si => $sl): ?>
                <li><button type="button" class="adqp-steps__it" data-adqp-goto-step="<?= $si ?>"<?= $si === 0 ? ' aria-current="step"' : '' ?>><span class="adqp-steps__n" aria-hidden="true"><?= $si + 1 ?></span><span class="adqp-steps__l"><?= $h($sl) ?></span></button></li>
            <?php endforeach; ?>
        </ol>
        <div class="bo-dialog__body adqp-dlg__body">
            <div class="adqp-stepbody adqp-guide__step" data-adqp-stepbody="0">
                <svg class="adqp-art" viewBox="0 0 320 120" aria-hidden="true" focusable="false">
                    <rect x="8" y="14" width="128" height="92" rx="10" class="adqp-art__box"/>
                    <rect x="184" y="14" width="128" height="92" rx="10" class="adqp-art__box adqp-art__box--ref"/>
                    <path d="M144 60h32M168 52l8 8-8 8" class="adqp-art__line"/>
                    <circle cx="40" cy="44" r="10" class="adqp-art__dot"/><rect x="58" y="38" width="60" height="12" rx="6" class="adqp-art__bar"/>
                    <circle cx="40" cy="76" r="10" class="adqp-art__dot adqp-art__dot--2"/><rect x="58" y="70" width="44" height="12" rx="6" class="adqp-art__bar"/>
                    <rect x="200" y="38" width="96" height="12" rx="6" class="adqp-art__bar adqp-art__bar--ref"/><rect x="200" y="70" width="70" height="12" rx="6" class="adqp-art__bar adqp-art__bar--ref"/>
                </svg>
                <h4 class="adqp-guide__t">Ce qui est comparé</h4>
                <p>Pour chaque unité de l’ORBAT dont l’échelon est renseigné, Athena compare le chef, l’adjoint, l’effectif et les sous-unités à ce que cet échelon devrait avoir.</p>
                <p class="adqp-guide__ex"><b>Exemple</b> <span>Un Squad nommé « Alpha » : son chef, son adjoint, ses 6 personnes et ses 2 fire teams.</span></p>
            </div>
            <div class="adqp-stepbody adqp-guide__step" data-adqp-stepbody="1" hidden>
                <svg class="adqp-art" viewBox="0 0 320 120" aria-hidden="true" focusable="false">
                    <rect x="20" y="20" width="120" height="80" rx="10" class="adqp-art__box adqp-art__box--ref"/>
                    <rect x="180" y="20" width="120" height="80" rx="10" class="adqp-art__box"/>
                    <path d="M146 60h28" class="adqp-art__line"/><path d="M160 46v28" class="adqp-art__line"/>
                    <rect x="36" y="40" width="88" height="10" rx="5" class="adqp-art__bar adqp-art__bar--ref"/><rect x="36" y="62" width="64" height="10" rx="5" class="adqp-art__bar adqp-art__bar--ref"/>
                    <rect x="196" y="40" width="40" height="10" rx="5" class="adqp-art__bar"/><path d="M262 48l8 8 14-16" class="adqp-art__line"/>
                </svg>
                <h4 class="adqp-guide__t">D’où viennent les attentes</h4>
                <p>Un référentiel tiré de la doctrine publique (américain, français, britannique…) donne les valeurs de départ. Vous les ajustez échelon par échelon ; une valeur non modifiée reste celle du référentiel.</p>
                <p class="adqp-guide__ex"><b>Exemple</b> <span>Référentiel américain : un Squad attend un Staff Sergeant (OR-6), un adjoint, 8 à 9 personnes et 2 fire teams.</span></p>
            </div>
            <div class="adqp-stepbody adqp-guide__step" data-adqp-stepbody="2" hidden>
                <svg class="adqp-art" viewBox="0 0 320 120" aria-hidden="true" focusable="false">
                    <rect x="20" y="52" width="280" height="16" rx="8" class="adqp-art__box"/>
                    <rect x="150" y="52" width="60" height="16" rx="8" class="adqp-art__bar adqp-art__bar--ref"/>
                    <circle cx="110" cy="60" r="11" class="adqp-art__dot adqp-art__dot--warn"/>
                    <circle cx="270" cy="60" r="11" class="adqp-art__dot adqp-art__dot--info"/>
                </svg>
                <h4 class="adqp-guide__t">Ce que signifie un écart</h4>
                <p>Un écart décrit la capacité d’un poste au regard de son échelon, jamais la valeur d’une personne. Sous la plage : sous-capacité ; nettement au-dessus : surqualification.</p>
                <p class="adqp-guide__ex"><b>Exemple</b> <span>« Alpha » compte 6 personnes et son chef est Sergeant (OR-5) : 2 écarts, effectif 6 pour 8 à 9 attendus et grade OR-5 pour OR-6 attendu. Avec un seuil de 2, un chef OR-8 serait signalé surqualifié.</span></p>
            </div>
            <div class="adqp-stepbody adqp-guide__step" data-adqp-stepbody="3" hidden>
                <svg class="adqp-art" viewBox="0 0 320 120" aria-hidden="true" focusable="false">
                    <rect x="20" y="24" width="84" height="72" rx="10" class="adqp-art__box"/>
                    <rect x="118" y="24" width="84" height="72" rx="10" class="adqp-art__box"/>
                    <rect x="216" y="24" width="84" height="72" rx="10" class="adqp-art__box adqp-art__box--ref"/>
                    <rect x="34" y="52" width="32" height="16" rx="8" class="adqp-art__bar adqp-art__bar--ref"/><circle cx="58" cy="60" r="6" class="adqp-art__dot"/>
                    <path d="M146 72l20-20 6 6-20 20h-6z" class="adqp-art__line"/>
                    <path d="M240 60l10 10 22-24" class="adqp-art__line"/>
                </svg>
                <h4 class="adqp-guide__t">Comment ajuster</h4>
                <p>Dans « Règles », activez ce qui doit être contrôlé et réglez le seuil. Dans « Échelons », le crayon ouvre les attentes d’un échelon ; « Ce que ça change » montre l’effet avant d’enregistrer.</p>
                <p class="adqp-guide__ex"><b>Exemple</b> <span>Votre communauté joue des squads de 6 : réglez l’effectif minimum du Squad à 6, et « Alpha » n’est plus en sous-effectif.</span></p>
            </div>
        </div>
        <div class="bo-dialog__foot adqp-dlg__foot">
            <span class="adqp-dlg__spacer"></span>
            <button type="button" class="bo-dialog__btn" data-adqp-prev hidden><?= $icon('back') ?><span>Précédent</span></button>
            <button type="button" class="bo-dialog__btn bo-dialog__btn--primary" data-adqp-next-step><span>Suivant</span><?= $icon('next') ?></button>
            <button type="button" class="bo-dialog__btn bo-dialog__btn--primary" data-adqp-close data-adqp-last hidden><?= $icon('check') ?><span>J’ai compris</span></button>
        </div>
    </dialog>

    <p hidden data-adqp-msg="order">Le minimum dépasse le maximum : corrigez l’une des deux valeurs avant d’enregistrer.</p>
</div>
<script src="<?= $h(asset_url('assets/js/eff-adequacy-page.js')) ?>" defer></script>
