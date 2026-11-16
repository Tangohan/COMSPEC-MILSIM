<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/effk.php';
require_once __DIR__ . '/partials/adequacy.php';

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\OrgAdequacyEngine;
use App\Services\Organization\OrgAdequacyImpact;

/*
 * Effectifs › Chaîne de commandement (refonte 2026-11-16).
 * En-tête en bande (back-office-bands.css) : utilité, chiffres clés, actions. Résumé de l’adéquation (#eff-adequacy)
 * vers la page Adéquation. Quatre onglets : organigramme (cartes de poste compactes, filtres par unité, échelon et
 * état ; chaque carte ouvre sa fenêtre de détail), à corriger (alertes et propositions), chefs d’unité (tableau
 * complet), qui relève de qui. Styles : eff-chain-page.css (.chn-*) ; onglets et filtres : eff-chain-page.js.
 * Écriture inchangée : POST chaine (commanders[unité] = membre) en arrière-plan depuis la fenêtre de eff-chain.js ;
 * les formulaires restent de vrais formulaires, qui fonctionnent sans JavaScript (repli).
 * $chainFragment : rendu de la seule zone vivante [data-chain-live], remplacée après chaque enregistrement.
 */

$fragment = !empty($chainFragment);

$units = is_array($chainUnits ?? null) ? $chainUnits : [];
$members = is_array($chainMembers ?? null) ? $chainMembers : [];
$options = is_array($chainMemberOptions ?? null) ? $chainMemberOptions : [];
$canManage = !empty($canManageAssignments);
$missingChefs = (int) ($chainMissingCommanders ?? 0);
$missingSuperiors = (int) ($chainMissingSuperiors ?? 0);
$withoutUnit = (int) ($chainWithoutUnit ?? 0);
$csrf = (string) ($csrfToken ?? '');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$community = trim((string) ($communityName ?? ''));
$advice = is_array($chainAdvice ?? null) ? $chainAdvice : [];
$alerts = is_array($advice['alerts'] ?? null) ? $advice['alerts'] : [];
$proposals = $canManage && is_array($advice['proposals'] ?? null) ? $advice['proposals'] : [];
$suggested = is_array($advice['suggested'] ?? null) ? $advice['suggested'] : [];
$activeSet = array_fill_keys(array_map('intval', is_array($chainActiveIds ?? null) ? $chainActiveIds : []), true);
$chainUrl = effectifs_workspace_url('chaine');
$adequacyUrl = effectifs_workspace_url('chaine/adequation');
$plural = static fn (int $n, string $one, string $many): string => $n > 1 ? $many : $one;

$unitById = [];
$childrenOf = [];
foreach ($units as $u) {
    $unitById[(int) $u['id']] = $u;
}
foreach ($units as $u) {
    $pid = (int) ($u['parent_id'] ?? 0);
    $childrenOf[isset($unitById[$pid]) ? $pid : 0][] = (int) $u['id'];
}
$memberById = [];
$membersByUnit = [];
foreach ($members as $m) {
    $memberById[(int) $m['user_id']] = $m;
    if ((int) ($m['unit_id'] ?? 0) > 0) {
        $membersByUnit[(int) $m['unit_id']][] = $m;
    }
}
/** Ancêtres d’une unité, du sommet à l’unité elle-même. */
$pathOf = static function (int $unitId) use ($unitById): array {
    $ids = [];
    $guard = 0;
    while ($unitId > 0 && isset($unitById[$unitId]) && $guard++ < 32) {
        array_unshift($ids, $unitId);
        $unitId = (int) ($unitById[$unitId]['parent_id'] ?? 0);
    }

    return $ids;
};
// Un chef dont l’unité principale est hors de la branche qu’il commande est « détaché ».
$isWithin = static function (int $unitId, int $ancestorId) use ($unitById): bool {
    $guard = 0;
    while ($unitId > 0 && $guard++ < 32) {
        if ($unitId === $ancestorId) {
            return true;
        }
        $unitId = (int) ($unitById[$unitId]['parent_id'] ?? 0);
    }

    return false;
};
$crest = static function (array $u): string {
    $code = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', trim((string) ($u['code'] ?? '')));
    if ($code !== '') {
        return mb_strtoupper(mb_substr($code, 0, 4, 'UTF-8'), 'UTF-8');
    }

    return effk_initials((string) ($u['name'] ?? ''));
};
$designated = count($units) - $missingChefs;
$chipLimit = 24;

// Adéquation de l’organisation (OrgAdequacyService) : absente si le contrôle est désactivé ou indisponible.
$adq = is_array($chainAdequacy ?? null) && !empty($chainAdequacy['enabled']) ? $chainAdequacy : null;
$adqPeople = $adq !== null && is_array($adq['people'] ?? null) ? $adq['people'] : [];
$adqUnits = $adq !== null && is_array($adq['units'] ?? null) ? $adq['units'] : [];
$adqIssues = $adq !== null && is_array($adq['issues'] ?? null) ? $adq['issues'] : [];
$adqCount = count($adqIssues);
$onlyGaps = !empty($adequacyOnlyGaps) && $adq !== null;
$memberUrl = static fn (int $id): string => effectifs_workspace_url('membres/' . $id);
$byUnitIssues = [];
foreach ($adqIssues as $issue) {
    $iu = (int) ($issue['unit_id'] ?? 0);
    if ($iu > 0 && isset($unitById[$iu])) {
        $byUnitIssues[$iu][] = $issue;
    }
}

// Onglet ouvert au chargement : ?vue=…, le filtre « écarts » ouvre « Qui relève de qui ».
$views = [
    'organigramme' => 'Organigramme',
    'corriger' => 'À corriger',
    'chefs' => 'Chefs d’unité',
    'releve' => 'Qui relève de qui',
];
$view = (string) ($chainView ?? '');
if (!isset($views[$view])) {
    $view = $onlyGaps ? 'releve' : 'organigramme';
}

/** Échelon d’une unité : [clé, libellé] (clé vide s’il n’est pas renseigné). */
$echelonOf = static function (array $u) use ($adqUnits): array {
    $raw = trim((string) ($u['echelon'] ?? ''));
    $key = $raw !== '' ? (EchelonStandards::standardKey($raw) ?? $raw) : '';
    if ($key === '') {
        return ['', ''];
    }
    $label = trim((string) ($adqUnits[(int) $u['id']]['standard_label'] ?? ''));
    if ($label === '') {
        $std = EchelonStandards::find(EchelonStandards::PROFILE_FR, $key);
        $label = is_array($std) ? (string) ($std['label'] ?? '') : '';
    }

    return [$key, $label !== '' ? $label : mb_convert_case($key, MB_CASE_TITLE, 'UTF-8')];
};

/**
 * État d’un poste de chef : codes pour le filtre et pastille affichée sur la carte.
 *
 * @return array{codes: list<string>, pill: array{0: string, 1: string}, detached: string}
 */
$postState = static function (array $u) use ($memberById, $isWithin, $unitById, $adqPeople, $byUnitIssues): array {
    $uid = (int) $u['id'];
    $cid = (int) ($u['commander_user_id'] ?? 0);
    $codes = [];
    $detached = '';
    if ($cid < 1) {
        $codes[] = 'vacant';
    } else {
        $home = (int) ($memberById[$cid]['unit_id'] ?? 0);
        if ($home < 1) {
            $detached = 'Sans unité';
        } elseif (!$isWithin($home, $uid) && !$isWithin($uid, $home)) {
            $detached = 'Détaché · ' . trim((string) ($unitById[$home]['name'] ?? ''));
        }
        if ($detached !== '') {
            $codes[] = 'detached';
        }
    }
    $personStatus = $cid > 0 ? (string) ($adqPeople[$cid]['status'] ?? '') : '';
    $gap = ($byUnitIssues[$uid] ?? []) !== [] || in_array($personStatus, [OrgAdequacyEngine::STATUS_UNDER, OrgAdequacyEngine::STATUS_OVER], true);
    if ($gap) {
        $codes[] = 'gap';
    }
    if ($codes === []) {
        $codes[] = 'ok';
    }
    $pill = match (true) {
        $cid < 1 => ['À pourvoir', 'warn'],
        $personStatus === OrgAdequacyEngine::STATUS_UNDER => ['Sous-capacité', 'warn'],
        $personStatus === OrgAdequacyEngine::STATUS_OVER => ['Surqualifié', 'info'],
        $gap => [str_replace('%1', (string) count($byUnitIssues[$uid] ?? []), count($byUnitIssues[$uid] ?? []) > 1 ? '%1 écarts' : '%1 écart'), 'info'],
        $detached !== '' => ['Détaché', 'info'],
        default => ['Pourvu', 'ok'],
    };

    return ['codes' => $codes, 'pill' => $pill, 'detached' => $detached];
};

/** Carte compacte d’un poste de chef : nom, grade, indicatif, unité, état. Ouvre la fenêtre de détail. */
$card = static function (array $u, bool $top = false) use ($h, $memberById, $adqPeople, $postState): string {
    $uid = (int) $u['id'];
    $cid = (int) ($u['commander_user_id'] ?? 0);
    $unitName = trim((string) ($u['name'] ?? ''));
    $state = $postState($u);
    [$pillLabel, $pillTone] = $state['pill'];
    $classes = 'chn-card' . ($top ? ' is-top' : '') . ($cid < 1 ? ' is-vac' : '') . (in_array('gap', $state['codes'], true) ? ' is-gap' : '')
        . ($state['detached'] !== '' ? ' is-det' : '');
    $attrs = ' type="button" class="' . $classes . '" style="--c:' . effk_unit_color($uid) . '" data-chn-card="' . $uid . '"'
        . ($cid > 0 ? ' data-chain-member="' . $cid . '"' : '') . ' aria-haspopup="dialog"';
    if ($cid < 1) {
        return '<button' . $attrs . '>'
            . '<span class="chn-card__av is-vac" aria-hidden="true">' . effk_icon('plus') . '</span>'
            . '<span class="chn-card__body"><span class="chn-card__name">Chef à désigner</span>'
            . '<span class="chn-card__role">' . $h($unitName) . '</span></span>'
            . '<span class="chn-pill is-' . $h($pillTone) . '">' . $h($pillLabel) . '</span></button>';
    }
    $m = $memberById[$cid] ?? null;
    $label = (string) ($m['label'] ?? ($u['commander_label'] ?? 'Membre'));
    $grade = trim((string) ($adqPeople[$cid]['grade_label'] ?? ''));
    $callsign = trim((string) ($m['callsign'] ?? ''));
    $meta = [];
    if ($grade !== '') {
        $meta[] = '<span class="chn-card__grade" translate="no">' . $h($grade) . '</span>';
    }
    if ($callsign !== '') {
        $meta[] = '<span class="chn-card__cs" translate="no">' . $h($callsign) . '</span>';
    }

    return '<button' . $attrs . '>'
        . effk_avatar($cid, $label, $top ? 'lg' : '')
        . '<span class="chn-card__body"><span class="chn-card__name">' . $h($label) . '</span>'
        . ($meta !== [] ? '<span class="chn-card__meta">' . implode('<span aria-hidden="true"> · </span>', $meta) . '</span>' : '')
        . '<span class="chn-card__role">' . $h('Chef · ' . $unitName) . '</span></span>'
        . '<span class="chn-pill is-' . $h($pillTone) . '">' . $h($pillLabel) . '</span></button>';
};

/** Membres d’une unité (hors chef) en pastilles ; chaîne vide s’il n’y en a aucun. */
$chips = static function (int $unitId) use ($h, $membersByUnit, $unitById, $chipLimit, $adqPeople): string {
    $cid = (int) ($unitById[$unitId]['commander_user_id'] ?? 0);
    $list = array_values(array_filter($membersByUnit[$unitId] ?? [], static fn (array $m): bool => (int) $m['user_id'] !== $cid));
    if ($list === []) {
        return '';
    }
    $out = '';
    foreach (array_slice($list, 0, $chipLimit) as $m) {
        $mid = (int) $m['user_id'];
        $role = trim((string) ($m['role_name'] ?? ''));
        $adqStatus = (string) ($adqPeople[$mid]['status'] ?? '');
        $flag = in_array($adqStatus, ['sous_capacite', 'surqualifie', 'sans_chef'], true) ? adq_status_meta($adqStatus) : null;
        $title = trim(($role !== '' && $role !== 'Membre' ? $role : '') . ($flag !== null ? ($role !== '' && $role !== 'Membre' ? ' · ' : '') . $flag[0] : ''));
        $out .= '<a class="effk-chip' . ($flag !== null ? ' has-adq is-adq-' . $h($flag[1]) : '') . '" href="' . $h(effectifs_workspace_url('membres/' . $mid)) . '" data-chain-chip="' . $mid . '" data-chain-member="' . $mid . '"'
            . ($title !== '' ? ' title="' . $h($title) . '"' : '') . '>'
            . effk_avatar($mid, (string) $m['label'], 'sm') . '<span>' . $h((string) $m['label']) . '</span></a>';
    }
    if (count($list) > $chipLimit) {
        $more = count($list) - $chipLimit;
        $out .= '<a class="effk-chip is-more" href="' . $h(effectifs_workspace_url('affectations') . '?unite=' . $unitId) . '">+' . $more . ' autre' . ($more > 1 ? 's' : '') . '</a>';
    }

    return $out;
};

/** Bloc d’une unité : en-tête, carte du chef, membres, puis sous-unités (récursif). */
$block = static function (int $unitId, int $depth = 0) use (&$block, $childrenOf, $unitById, $membersByUnit, $card, $chips, $crest, $h, $pathOf, $echelonOf, $postState, $plural): string {
    $u = $unitById[$unitId];
    $count = count($membersByUnit[$unitId] ?? []);
    $subs = $childrenOf[$unitId] ?? [];
    [$echKey, $echLabel] = $echelonOf($u);
    $badge = effk_unit_level_badge((string) ($u['visibility'] ?? ''));
    $memberChips = $chips($unitId);
    $facts = [$count . ' ' . $plural($count, 'membre', 'membres')];
    if ($echLabel !== '') {
        $facts[] = $echLabel;
    }
    $out = '<div class="chn-unit' . ($depth === 0 ? ' is-col' : '') . '" style="--c:' . effk_unit_color($unitId) . '" data-chn-unit="' . $unitId . '"'
        . ' data-chn-path="' . $h(implode(' ', $pathOf($unitId))) . '" data-chn-echelon="' . $h($echKey) . '"'
        . ' data-chn-state="' . $h(implode(' ', $postState($u)['codes'])) . '">'
        . '<div class="chn-unit__head"><span class="chn-crest" translate="no" aria-hidden="true">' . $h($crest($u)) . '</span>'
        . '<span class="chn-unit__id"><b>' . $h((string) ($u['name'] ?? '')) . '</b><small>' . $h(implode(' · ', $facts)) . '</small></span>'
        . ($badge !== '' ? '<span class="chn-unit__conf">' . $badge . '</span>' : '') . '</div>'
        . $card($u);
    if ($memberChips !== '') {
        $out .= '<div class="chn-unit__mem effk-mem" aria-label="' . $h('Membres de ' . (string) ($u['name'] ?? '')) . '">' . $memberChips . '</div>';
    }
    if ($subs !== [] && $depth < 6) {
        $out .= '<div class="chn-unit__subs">';
        foreach ($subs as $sid) {
            $out .= $block($sid, $depth + 1);
        }
        $out .= '</div>';
    }

    return $out . '</div>';
};

$roots = $childrenOf[0] ?? [];
$withoutUnitMembers = array_values(array_filter($members, static fn (array $m): bool => (int) ($m['unit_id'] ?? 0) < 1 && ($m['commands'] ?? []) === []));

// Données du sélecteur de chef, de « Remonter la chaîne » et de la fenêtre de détail.
$jsUnits = [];
foreach ($units as $u) {
    $jsUnits[(int) $u['id']] = [
        'name' => (string) $u['name'],
        'chef' => (int) ($u['commander_user_id'] ?? 0),
        'members' => array_values(array_map('intval', is_array($u['member_ids'] ?? null) ? $u['member_ids'] : [])),
        'suggested' => array_values(array_map('intval', is_array($suggested[(int) $u['id']] ?? null) ? $suggested[(int) $u['id']] : [])),
        'depth' => max(0, (int) ($u['depth'] ?? 0)),
    ];
}
$jsMembers = [];
foreach ($members as $m) {
    $jsMembers[(int) $m['user_id']] = [
        'label' => (string) $m['label'],
        'unit' => (int) ($m['unit_id'] ?? 0),
        'unitName' => (string) ($m['unit_name'] ?? ''),
        'chain' => array_values(array_map('intval', is_array($m['chain_ids'] ?? null) ? $m['chain_ids'] : [])),
        'commands' => array_values(array_map('strval', is_array($m['commands'] ?? null) ? $m['commands'] : [])),
        'color' => effk_member_color((int) $m['user_id']),
        'initials' => effk_initials((string) $m['label']),
        'url' => effectifs_workspace_url('membres/' . (int) $m['user_id']),
        'off' => $activeSet !== [] && !isset($activeSet[(int) $m['user_id']]),
    ];
}

/** Une alerte : le problème, l’endroit, quoi faire, et l’action directe pour qui peut agir. */
$alertRow = static function (array $a) use ($h, $canManage, $chainUrl): string {
    $tone = (string) ($a['tone'] ?? 'info');
    $unitId = (int) ($a['unit_id'] ?? 0);
    $people = '';
    if (is_array($a['people'] ?? null) && $a['people'] !== []) {
        $people = '<div class="effk-al__people">';
        foreach (array_slice($a['people'], 0, 12) as $person) {
            $pid = (int) $person['id'];
            $people .= '<a class="effk-al__person" href="' . $h(effectifs_workspace_url('membres/' . $pid)) . '"'
                . ($canManage ? ' data-chain-assign="' . $pid . '" aria-haspopup="dialog"' : '') . '>'
                . effk_avatar($pid, (string) $person['label'], 'sm') . '<span>' . $h((string) $person['label']) . '</span></a>';
        }
        if (count($a['people']) > 12) {
            $people .= '<a class="effk-al__more" href="' . $h(effectifs_workspace_url() . '?sans_affectation=1') . '">' . $h(str_replace('%1', (string) count($a['people']), 'Voir les %1 membres')) . '</a>';
        }
        $people .= '</div>';
    }
    $action = $canManage && $unitId > 0
        ? '<a class="effk-btn is-sm" href="' . $h($chainUrl . '?vue=chefs#eff-chain-setup') . '" data-chain-slot="' . $unitId . '" aria-haspopup="dialog">' . $h((string) ($a['action'] ?? 'Désigner un chef')) . '</a>'
        : '';

    return '<li class="effk-al is-' . $h($tone) . '" data-chain-alert="' . $h((string) ($a['code'] ?? '')) . '">'
        . '<span class="effk-al__i" aria-hidden="true">' . effk_icon($tone === 'info' ? 'user' : 'alert') . '</span>'
        . '<div class="effk-al__c"><b>' . $h((string) ($a['title'] ?? '')) . '</b>'
        . '<small>' . $h((string) ($a['where'] ?? '')) . '</small>'
        . '<p>' . $h((string) ($a['text'] ?? '')) . '</p>' . $people . '</div>'
        . $action . '</li>';
};

/** Une proposition : un vrai formulaire (repli sans JavaScript), confirmé dans une fenêtre quand le script tourne. */
$proposalRow = static function (array $p) use ($h, $csrf, $chainUrl): string {
    $unitId = (int) $p['unit_id'];
    $userId = (int) $p['user_id'];
    $isAssign = (string) $p['kind'] === \App\Services\Effectifs\CommandChainAdvisor::KIND_ASSIGN;
    $fields = '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '">';
    if ($isAssign) {
        $action = effectifs_workspace_url('membres/' . $userId . '/affectation');
        $fields .= '<input type="hidden" name="unit_id" value="' . $unitId . '">'
            . '<input type="hidden" name="return_url" value="' . $h($chainUrl) . '">';
    } else {
        $action = $chainUrl;
        $fields .= '<input type="hidden" name="commanders[' . $unitId . ']" value="' . $userId . '">'
            . '<input type="hidden" name="expected_commanders[' . $unitId . ']" value="' . (int) $p['current_id'] . '">';
    }

    return '<li class="effk-prop" data-chain-prop="' . $h((string) $p['id']) . '">'
        . '<div class="effk-prop__c"><b data-chain-confirm-title>' . $h((string) $p['title']) . '</b><p>' . $h((string) $p['reason']) . '</p></div>'
        . '<form method="post" action="' . $h($action) . '" class="effk-prop__a" data-chain-proposal data-unit="' . $unitId . '" data-kind="' . $h((string) $p['kind']) . '">'
        . $fields
        . '<span hidden data-chain-confirm-body>' . $h((string) $p['confirm']) . '</span>'
        . '<button type="submit" class="effk-btn is-dark is-sm">' . $h((string) $p['action']) . '</button>'
        . (!$isAssign ? '<button type="button" class="effk-btn is-sm" data-chain-slot="' . $unitId . '" aria-haspopup="dialog">Autre choix</button>' : '')
        . '</form></li>';
};
/** Liste repliée au-delà de $visible lignes. */
$foldedList = static function (array $rows, callable $render, string $listClass, int $visible = 5): string {
    $out = '<ul class="' . $listClass . '">' . implode('', array_map($render, array_slice($rows, 0, $visible))) . '</ul>';
    $rest = array_slice($rows, $visible);
    if ($rest !== []) {
        $out .= '<details class="effk-chain-more"><summary>' . effk_e(str_replace('%1', (string) count($rest), 'Voir les %1 autres')) . '</summary>'
            . '<ul class="' . $listClass . '">' . implode('', array_map($render, $rest)) . '</ul></details>';
    }

    return $out;
};

/** Fenêtre de détail d’un poste (modèle cloné par eff-chain.js) : titulaire, unité, adjoint, écarts et actions. */
$detail = static function (array $u) use ($h, $memberById, $adqPeople, $adqUnits, $byUnitIssues, $memberUrl, $canManage, $echelonOf, $postState, $unitById, $membersByUnit, $plural): string {
    $uid = (int) $u['id'];
    $cid = (int) ($u['commander_user_id'] ?? 0);
    $name = trim((string) ($u['name'] ?? ''));
    $m = $cid > 0 ? ($memberById[$cid] ?? null) : null;
    $label = $cid > 0 ? (string) ($m['label'] ?? ($u['commander_label'] ?? 'Membre')) : '';
    $state = $postState($u);
    [$pillLabel, $pillTone] = $state['pill'];
    [, $echLabel] = $echelonOf($u);
    $row = is_array($adqUnits[$uid] ?? null) ? $adqUnits[$uid] : [];
    $type = effk_unit_type_label((string) ($u['type'] ?? ''));
    $sub = trim(($type !== '' ? $type : 'Unité') . ($echLabel !== '' ? ' · ' . $echLabel : ''));

    $facts = [];
    $parentId = (int) ($u['parent_id'] ?? 0);
    $facts[] = ['Rattachée à', $parentId > 0 && isset($unitById[$parentId]) ? (string) $unitById[$parentId]['name'] : 'Sommet de l’organigramme'];
    // Effectif évalué par l’adéquation (sous-unités comprises), sinon les membres affectés à l’unité même.
    $strength = isset($row['strength']) ? (int) $row['strength'] : count($membersByUnit[$uid] ?? []);
    $expected = trim((string) ($row['strength_expected'] ?? ''));
    $facts[] = ['Effectif', $strength . ' ' . $plural($strength, 'personne', 'personnes') . ($expected !== '' ? ' (attendu : ' . $expected . ')' : '')];
    $did = (int) ($row['deputy_id'] ?? 0);
    if ($did > 0) {
        $facts[] = [($row['deputy_source'] ?? '') === 'subunit' ? 'Adjoint de fait' : 'Adjoint', (string) ($adqPeople[$did]['label'] ?? 'Membre')];
    }
    if ($state['detached'] !== '') {
        $facts[] = ['Affectation du chef', $state['detached']];
    }
    $role = $cid > 0 ? trim((string) ($m['role_name'] ?? '')) : '';
    if ($role !== '' && $role !== 'Membre') {
        $facts[] = ['Rôle', $role];
    }

    $who = '<div class="chn-det__who">'
        . ($cid > 0 ? effk_avatar($cid, $label, 'lg') : '<span class="chn-card__av is-vac" aria-hidden="true">' . effk_icon('plus') . '</span>')
        . '<div class="chn-det__id"><b>' . $h($cid > 0 ? $label : 'Poste de chef à pourvoir') . '</b>';
    $meta = [];
    if ($cid > 0 && trim((string) ($adqPeople[$cid]['grade_label'] ?? '')) !== '') {
        $meta[] = '<span translate="no">' . $h(trim((string) $adqPeople[$cid]['grade_label'])) . '</span>';
    }
    if ($cid > 0 && trim((string) ($m['callsign'] ?? '')) !== '') {
        $meta[] = '<span translate="no">' . $h(trim((string) $m['callsign'])) . '</span>';
    }
    $who .= ($meta !== [] ? '<small>' . implode(' · ', $meta) . '</small>' : '')
        . '</div><span class="chn-pill is-' . $h($pillTone) . '">' . $h($pillLabel) . '</span></div>';

    $dl = '<dl class="chn-det__facts">';
    foreach ($facts as [$k, $v]) {
        $dl .= '<div><dt>' . $h($k) . '</dt><dd>' . $h($v) . '</dd></div>';
    }
    $dl .= '</dl>';

    $issues = $byUnitIssues[$uid] ?? [];
    $gaps = $issues !== []
        ? '<div class="chn-det__gaps adq-scope"><p class="chn-det__h">' . $h(str_replace('%1', (string) count($issues), count($issues) > 1 ? '%1 écarts d’adéquation' : '%1 écart d’adéquation')) . '</p>'
            . adq_issue_list($issues, $adqPeople, $memberUrl, $canManage) . '</div>'
        : '';

    $acts = [];
    if ($canManage) {
        $acts[] = '<button type="button" class="effk-md__btn is-dark" data-chn-act="pick" data-unit="' . $uid . '">' . $h($cid > 0 ? 'Changer le chef' : 'Désigner un chef') . '</button>';
    }
    if ($cid > 0) {
        $acts[] = '<button type="button" class="effk-md__btn" data-chn-act="follow" data-member="' . $cid . '">' . $h('Remonter sa chaîne') . '</button>';
        $acts[] = '<a class="effk-md__btn" href="' . $h($memberUrl($cid)) . '">Ouvrir sa fiche</a>';
        if ($canManage) {
            $acts[] = '<button type="button" class="effk-md__btn" data-chn-act="move" data-member="' . $cid . '">' . $h('Muter vers une autre unité') . '</button>';
        }
    }
    $acts[] = '<a class="effk-md__btn" href="' . $h(effectifs_workspace_url('affectations') . '?unite=' . $uid) . '">' . $h('Membres de l’unité') . '</a>';

    return '<template data-chn-detail="' . $uid . '" data-chn-title="' . $h($name) . '" data-chn-sub="' . $h($sub) . '">'
        . '<div class="chn-det" data-adq-unit="' . $uid . '">' . $who . $dl . $gaps
        . '<div class="chn-det__acts">' . implode('', $acts) . '</div></div></template>';
};

$structureUrl = url('back-office/organisation/structure');
?>
<?php if (!$fragment): ?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/back-office-bands.css')) ?>">
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/eff-chain-page.css')) ?>">
<?= adq_styles() ?>
<div class="effk effk-chainpage chn" data-eff-chain data-chn data-chain-url="<?= $h($chainUrl) ?>" data-chain-csrf="<?= $h($csrf) ?>" data-chain-assign-url="<?= $h(effectifs_workspace_url('membres/__ID__/affectation')) ?>"<?= $canManage ? ' data-can-manage' : '' ?>>
    <header class="bo-band bo-band--hero bo-band--sky chn-hero" aria-labelledby="chn-title">
        <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
        <div class="bo-band__glow" aria-hidden="true"></div>
        <div class="bo-band__veil" aria-hidden="true"></div>
        <div class="bo-band__body">
            <div class="bo-band__who">
                <h2 class="bo-band__title" id="chn-title">Chaîne de commandement</h2>
                <p class="bo-band__line">Qui commande qui : désignez le chef de chaque unité et vérifiez que chaque membre sait à qui rendre compte.</p>
                <div class="bo-band__actions">
                    <?php if ($canManage && $units !== []): ?>
                    <a class="bo-band__btn bo-band__btn--solid" href="<?= $h($chainUrl . '?vue=chefs#eff-chain-setup') ?>" data-chn-goto="chefs"><?= effk_icon('user') ?><span>Désigner les chefs</span></a>
                    <?php endif; ?>
                    <a class="bo-band__btn" href="<?= $h($adequacyUrl) ?>"><?= effk_icon('route') ?><span>Adéquation de l’organisation</span></a>
                    <?php if ($canManage): ?>
                    <a class="bo-band__btn" href="<?= $h($structureUrl) ?>"><?= effk_icon('tree') ?><span>Ouvrir la structure</span></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($units !== []): ?>
            <div class="bo-band__side">
                <div class="chn-figs">
                    <p class="bo-figure"><strong><?= count($units) ?></strong><span><?= $h($plural(count($units), 'unité', 'unités')) ?></span></p>
                    <p class="bo-figure"><strong><?= $designated ?><small>/<?= count($units) ?></small></strong><span><?= $h($plural($designated, 'poste de chef pourvu', 'postes de chef pourvus')) ?></span></p>
                    <p class="bo-figure<?= $missingChefs > 0 ? ' is-hot' : '' ?>"><strong><?= $missingChefs ?></strong><span>à pourvoir</span></p>
                    <?php if ($adq !== null): ?>
                    <p class="bo-figure<?= $adqCount > 0 ? ' is-hot' : '' ?>"><strong><?= $adqCount ?></strong><span><?= $h($plural($adqCount, 'écart d’adéquation', 'écarts d’adéquation')) ?></span></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="effk-chain-live chn-live" data-chain-live>
<?php endif; ?>

<?php if ($units === []): ?>
    <?= effk_empty(
        'Aucune unité définie',
        $canManage ? 'La chaîne de commandement se construit en trois étapes.' : 'La communauté n’a pas encore d’unité : la chaîne de commandement apparaîtra ici.',
        $canManage
            ? '<ol class="effk-chain-steps"><li>Créez les unités dans la structure.</li><li>Affectez les membres à leur unité.</li><li>Revenez ici pour désigner le chef de chacune.</li></ol>'
                . '<a class="effk-btn is-dark" href="' . $h($structureUrl) . '">' . effk_icon('tree') . '<span>Créer les unités</span></a>'
            : ''
    ) ?>
<?php else: ?>
    <?php // ——— Résumé de l’adéquation : l’ancre #eff-adequacy (alertes RH, page Adéquation) arrive ici. ——— ?>
    <section class="chn-adq" id="eff-adequacy" aria-labelledby="chn-adq-title" tabindex="-1">
        <?php if ($adq === null): ?>
            <div class="chn-adq__main">
                <h3 class="chn-adq__title" id="chn-adq-title">Adéquation de l’organisation</h3>
                <p class="chn-adq__line">Le contrôle n’est pas actif : aucun écart n’est calculé pour la chaîne.</p>
            </div>
            <div class="chn-adq__acts">
                <a class="chn-btn" href="<?= $h($adequacyUrl . '?onglet=regles') ?>">Ouvrir les règles</a>
            </div>
        <?php else: ?>
            <?php $adqGroups = OrgAdequacyImpact::grouped($adq); ?>
            <div class="chn-adq__main">
                <h3 class="chn-adq__title" id="chn-adq-title">Adéquation : <span class="chn-adq__n<?= $adqCount > 0 ? ' is-hot' : '' ?>"><?= $adqCount ?></span> <?= $h($plural($adqCount, 'écart', 'écarts')) ?></h3>
                <p class="chn-adq__line"><?= $adqCount === 0 ? 'Chaque poste est tenu au niveau attendu par son échelon.' : 'Postes tenus en dessous ou au-dessus de ce que leur échelon attend, par type.' ?></p>
                <?php if ($adqCount > 0): ?>
                <ul class="chn-adq__types" aria-label="Écarts par type">
                    <?php foreach (OrgAdequacyImpact::GROUPS as $gk => $gdef):
                        $gn = count($adqGroups[$gk] ?? []);
                        if ($gn === 0) {
                            continue;
                        }
                        ?>
                        <li><a class="chn-type is-<?= $h((string) $gdef['tone']) ?>" href="<?= $h($adequacyUrl . '?onglet=ensemble') ?>"><span><?= $h(OrgAdequacyImpact::LABELS[$gk] ?? $gk) ?></span> <b><?= $gn ?></b></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <div class="chn-adq__acts">
                <a class="chn-btn chn-btn--primary" href="<?= $h($adequacyUrl) ?>">Ouvrir l’adéquation</a>
                <?php if ($adqCount > 0): ?>
                    <?php if ($onlyGaps): ?>
                        <a class="chn-btn" href="<?= $h($chainUrl . '?vue=releve#eff-chain-people-panel') ?>" data-chain-filter="0">Afficher tout le monde</a>
                    <?php else: ?>
                        <a class="chn-btn" href="<?= $h($chainUrl . '?ecarts=1&vue=releve#eff-chain-people-panel') ?>" data-chain-filter="1">Personnes avec un écart</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php
    $tabCounts = ['corriger' => count($alerts) + count($proposals)];
    ?>
    <div class="chn-tabs" role="tablist" aria-label="Sections de la chaîne de commandement" data-chn-tabs>
        <?php foreach ($views as $vk => $vl): ?>
            <a class="chn-tab" role="tab" id="chn-tab-<?= $h($vk) ?>" href="<?= $h($chainUrl . '?vue=' . $vk) ?>"
               aria-controls="chn-panel-<?= $h($vk) ?>" aria-selected="<?= $view === $vk ? 'true' : 'false' ?>"
               tabindex="<?= $view === $vk ? '0' : '-1' ?>" data-chn-tab="<?= $h($vk) ?>">
                <span><?= $h($vl) ?></span><?php if (($tabCounts[$vk] ?? 0) > 0): ?> <span class="chn-tab__n"><?= (int) $tabCounts[$vk] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php // ——— 01 Organigramme ——— ?>
    <section class="chn-panel bo-section" role="tabpanel" id="chn-panel-organigramme" aria-labelledby="chn-tab-organigramme" tabindex="0" data-chn-panel="organigramme"<?= $view !== 'organigramme' ? ' hidden' : '' ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 01</span>
            <h3 class="bo-section__title"><?= $h($community !== '' ? $community : 'Organigramme') ?></h3>
            <div class="bo-section__aside">
                <span class="bo-chip"><b><?= $designated ?></b> <?= $h($plural($designated, 'chef désigné', 'chefs désignés')) ?></span>
                <?php if ($missingChefs > 0): ?><span class="bo-chip"><b><?= $missingChefs ?></b> à désigner</span><?php endif; ?>
            </div>
            <p class="bo-section__desc">Un poste de chef par carte ; ouvrez une carte pour voir le titulaire, ses écarts et agir.</p>
        </header>

        <div class="chn-tools" data-chn-tools hidden>
            <?php if ($members !== []): ?>
            <label class="chn-field chn-field--search">
                <span class="chn-field__l">Remonter la chaîne d’un membre</span>
                <input type="search" id="eff-chain-q" list="eff-chain-names" placeholder="Nom du membre" autocomplete="off" data-chain-q>
                <datalist id="eff-chain-names">
                    <?php foreach ($members as $m): ?><option value="<?= $h((string) $m['label']) ?>"></option><?php endforeach; ?>
                </datalist>
            </label>
            <?php endif; ?>
            <label class="chn-field">
                <span class="chn-field__l">Unité</span>
                <select data-chn-filter="unit">
                    <option value="">Toutes les unités</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= str_repeat("\u{00A0}\u{00A0}", max(0, (int) ($u['depth'] ?? 0))) . $h((string) $u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php
            $echelonOptions = [];
            foreach ($units as $u) {
                [$ek, $el] = $echelonOf($u);
                if ($ek !== '') {
                    $echelonOptions[$ek] = $el;
                }
            }
            ?>
            <?php if ($echelonOptions !== []): ?>
            <label class="chn-field">
                <span class="chn-field__l">Échelon</span>
                <select data-chn-filter="echelon">
                    <option value="">Tous les échelons</option>
                    <?php foreach ($echelonOptions as $ek => $el): ?><option value="<?= $h($ek) ?>"><?= $h($el) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label class="chn-field">
                <span class="chn-field__l">État du poste</span>
                <select data-chn-filter="state">
                    <option value="">Tous les postes</option>
                    <option value="vacant">À pourvoir</option>
                    <?php if ($adq !== null): ?><option value="gap">Avec un écart</option><?php endif; ?>
                    <option value="detached">Chef détaché</option>
                    <option value="ok">Pourvu, sans écart</option>
                </select>
            </label>
            <p class="chn-tools__count" role="status" aria-live="polite" data-chn-count></p>
            <button type="button" class="chn-btn chn-btn--ghost" data-chn-reset hidden>Effacer les filtres</button>
        </div>

        <div class="effk-path chn-path" data-chain-path role="status" aria-live="polite" hidden></div>

        <div class="chn-chart" data-chain-chart>
            <?php if (count($roots) === 1): ?>
                <?php
                $root = $unitById[$roots[0]];
                $rootChips = $chips($roots[0]);
                [$rootEchKey] = $echelonOf($root);
                ?>
                <div class="chn-apex" data-chn-unit="<?= (int) $roots[0] ?>" data-chn-path="<?= (int) $roots[0] ?>" data-chn-echelon="<?= $h($rootEchKey) ?>" data-chn-state="<?= $h(implode(' ', $postState($root)['codes'])) ?>">
                    <p class="chn-apex__unit"><b><?= $h((string) $root['name']) ?></b> <?= effk_unit_level_badge((string) ($root['visibility'] ?? '')) ?></p>
                    <?= $card($root, true) ?>
                    <?php if ($rootChips !== ''): ?>
                    <div class="chn-unit__mem effk-mem" aria-label="<?= $h('Rattachés à ' . (string) $root['name']) ?>"><?= $rootChips ?></div>
                    <?php endif; ?>
                </div>
                <?php $cols = $childrenOf[$roots[0]] ?? []; ?>
                <?php if ($cols !== []): ?>
                <div class="chn-cols" style="--n:<?= min(4, count($cols)) ?>">
                    <?php foreach ($cols as $cid): ?><?= $block($cid) ?><?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="chn-cols" style="--n:<?= min(4, count($roots)) ?>">
                    <?php foreach ($roots as $rid): ?><?= $block($rid) ?><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="chn-empty" data-chn-none hidden>Aucun poste ne correspond à ces filtres.</p>
        </div>
        <?php if ($withoutUnitMembers !== []): ?>
        <p class="chn-foot">
            <span><span>Sans unité, hors chaîne :</span> <?= $h(implode(', ', array_map(static fn (array $m): string => (string) $m['label'], array_slice($withoutUnitMembers, 0, 12)))) ?><?= count($withoutUnitMembers) > 12 ? ' (+' . (count($withoutUnitMembers) - 12) . ')' : '' ?></span>
            <a href="<?= $h(effectifs_workspace_url('affectations')) ?>">Gérer les affectations</a>
        </p>
        <?php endif; ?>
    </section>

    <?php // ——— 02 À corriger ——— ?>
    <section class="chn-panel bo-section" role="tabpanel" id="chn-panel-corriger" aria-labelledby="chn-tab-corriger" tabindex="0" data-chn-panel="corriger"<?= $view !== 'corriger' ? ' hidden' : '' ?>>
        <header class="bo-section__head">
            <span class="bo-section__index" aria-hidden="true">/ 02</span>
            <h3 class="bo-section__title">À corriger</h3>
            <div class="bo-section__aside">
                <span class="bo-chip"><b><?= $missingSuperiors ?></b> <?= $h($plural($missingSuperiors, 'membre sans chef', 'membres sans chef')) ?></span>
                <span class="bo-chip"><b><?= $withoutUnit ?></b> sans unité</span>
            </div>
            <p class="bo-section__desc">Le problème, l’endroit et quoi faire ; les propositions ne s’appliquent qu’après votre confirmation.</p>
        </header>
        <div class="effk-chain-todo<?= $proposals !== [] ? ' has-proposals' : '' ?>">
            <section class="effk-panel effk-chain-box" aria-labelledby="eff-chain-alerts-title">
                <header class="effk-chain-box__h">
                    <h4 id="eff-chain-alerts-title">Alertes</h4>
                    <?php if ($alerts !== []): ?><span class="effk-chain-box__n"><?= count($alerts) ?></span><?php endif; ?>
                </header>
                <?php if ($members === []): ?>
                    <p class="effk-note is-info"><?= effk_icon('user') ?><span>Aucun membre actif n’est affecté : personne ne peut encore être désigné chef. <a href="<?= $h(effectifs_workspace_url('affectations')) ?>">Ouvrir les affectations</a></span></p>
                <?php endif; ?>
                <?php if ($alerts !== []): ?>
                    <?= $foldedList($alerts, $alertRow, 'effk-chain-list') ?>
                <?php elseif ($members !== []): ?>
                    <p class="effk-note is-ok"><?= effk_icon('check') ?><span>Chaîne complète : chaque unité a un chef actif et chaque membre a une unité.</span></p>
                <?php endif; ?>
            </section>
            <?php if ($proposals !== []): ?>
            <section class="effk-panel effk-chain-box" id="eff-chain-proposals" aria-labelledby="eff-chain-proposals-title">
                <header class="effk-chain-box__h">
                    <h4 id="eff-chain-proposals-title">Propositions</h4>
                    <span class="effk-chain-box__n"><?= count($proposals) ?></span>
                </header>
                <p class="effk-chain-box__lead">Tirées des grades, des affectations et des postes de la communauté. Rien n’est appliqué sans votre confirmation.</p>
                <?= $foldedList($proposals, $proposalRow, 'effk-chain-list') ?>
            </section>
            <?php endif; ?>
        </div>
    </section>

    <script type="application/json" id="eff-chain-data"><?= json_encode(['units' => (object) $jsUnits, 'members' => (object) $jsMembers, 'order' => array_map(static fn (array $u): int => (int) $u['id'], $units), 'affectations' => effectifs_workspace_url('affectations')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <?php // ——— 03 Chefs d’unité ——— ?>
    <section class="chn-panel bo-section" role="tabpanel" id="chn-panel-chefs" aria-labelledby="chn-tab-chefs" tabindex="0" data-chn-panel="chefs"<?= $view !== 'chefs' ? ' hidden' : '' ?>>
        <header class="bo-section__head" id="eff-chain-setup">
            <span class="bo-section__index" aria-hidden="true">/ 03</span>
            <h3 class="bo-section__title">Chefs d’unité</h3>
            <p class="bo-section__desc">Tout le tableau, pour désigner plusieurs chefs d’un coup ; laissez vide un poste pas encore pourvu.</p>
        </header>
        <form method="post" action="<?= $h($chainUrl) ?>" class="eff-chain chn-bulk" data-chain-bulk>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <div class="effk-table-wrap" role="region" aria-label="Chefs d’unité" tabindex="0">
                <table class="effk-table effk-table--stack" id="eff-chain-units">
                    <thead>
                        <tr>
                            <th scope="col">Unité</th>
                            <th scope="col">Type</th>
                            <th scope="col">Rattachée à</th>
                            <th scope="col">Chef</th>
                            <th scope="col">Effectif</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($units as $u):
                        $uid = (int) ($u['id'] ?? 0);
                        $depth = max(0, (int) ($u['depth'] ?? 0));
                        $name = trim((string) ($u['name'] ?? ''));
                        $parentName = trim((string) ($u['parent_name'] ?? ''));
                        $commanderId = (int) ($u['commander_user_id'] ?? 0);
                        $memberIds = array_values(array_filter(array_map('intval', is_array($u['member_ids'] ?? null) ? $u['member_ids'] : [])));
                        $memberIdSet = array_fill_keys($memberIds, true);
                        $label = effk_unit_type_label((string) ($u['type'] ?? ''));
                        ?>
                        <tr>
                            <td data-label="Unité">
                                <span class="eff-chain__unit" style="--eff-chain-depth:<?= $depth ?>"><?= $h($name !== '' ? $name : '—') ?></span>
                                <?= effk_unit_level_badge((string) ($u['visibility'] ?? '')) ?>
                            </td>
                            <td data-label="Type"><?= $label !== '' ? '<span class="effk-tag">' . $h($label) . '</span>' : '<span class="effk-muted">—</span>' ?></td>
                            <td data-label="Rattachée à"><?= $parentName !== '' ? $h($parentName) : '<span class="effk-muted">Sommet de l’organigramme</span>' ?></td>
                            <td data-label="Chef">
                                <?php if ($canManage): ?>
                                    <input type="hidden" name="expected_commanders[<?= $uid ?>]" value="<?= $commanderId ?>">
                                    <select class="eff-chain__select effk-sel" id="eff-chain-chef-<?= $uid ?>" name="commanders[<?= $uid ?>]" data-chain-initial="<?= $commanderId ?>" data-unit="<?= $uid ?>" aria-label="Chef de <?= $h($name !== '' ? $name : 'l’unité') ?>">
                                        <option value="0">Aucun chef pour le moment</option>
                                        <?php
                                        $inUnit = [];
                                        $others = [];
                                        foreach ($options as $opt) {
                                            $oid = (int) ($opt['id'] ?? 0);
                                            // Un compte inactif ne se nomme plus ; le chef en place reste affiché tant qu’il n’est pas remplacé.
                                            if ($oid < 1 || ($activeSet !== [] && !isset($activeSet[$oid]) && $oid !== $commanderId)) {
                                                continue;
                                            }
                                            if (isset($memberIdSet[$oid])) {
                                                $inUnit[] = $opt;
                                            } else {
                                                $others[] = $opt;
                                            }
                                        }
                                        ?>
                                        <?php if ($inUnit !== []): ?>
                                            <optgroup label="Membres de l’unité">
                                                <?php foreach ($inUnit as $opt): ?>
                                                    <option value="<?= (int) $opt['id'] ?>" <?= $commanderId === (int) $opt['id'] ? 'selected' : '' ?>><?= $h((string) $opt['label']) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ($others !== []): ?>
                                            <optgroup label="Autres membres">
                                                <?php foreach ($others as $opt): ?>
                                                    <option value="<?= (int) $opt['id'] ?>" <?= $commanderId === (int) $opt['id'] ? 'selected' : '' ?>><?= $h((string) $opt['label']) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                <?php else: ?>
                                    <?= $h(trim((string) ($u['commander_label'] ?? '')) !== '' ? (string) $u['commander_label'] : 'Aucun chef') ?>
                                <?php endif; ?>
                            </td>
                            <td class="effk-num" data-label="Effectif"><?= (int) ($u['member_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canManage): ?>
                <div class="chn-bulk__foot effk-chain-bulk">
                    <span data-chain-bulk-count>Un chef laissé vide n’apparaît pas dans la chaîne tant qu’il n’est pas désigné.</span>
                    <span class="effk-chain-bulk__a">
                        <button type="button" class="effk-btn" data-chain-bulk-reset hidden>Annuler mes choix</button>
                        <button type="submit" class="effk-btn is-dark" data-chain-bulk-save>Enregistrer les chefs</button>
                    </span>
                </div>
            <?php endif; ?>
        </form>
    </section>

    <?php
    $peopleRows = $members;
    // Toutes les personnes évaluées, y compris celles sans affectation que la chaîne ne liste pas.
    foreach ($adqPeople as $apId => $apRow) {
        if (!isset($memberById[(int) $apId])) {
            $peopleRows[] = ['user_id' => (int) $apId, 'label' => (string) ($apRow['label'] ?? 'Membre'), 'unit_id' => 0, 'unit_name' => '',
                'reports_to_label' => '', 'commands' => [], 'chain_labels' => []];
        }
    }
    if ($onlyGaps) {
        $peopleRows = array_values(array_filter($peopleRows, static fn (array $m): bool => ($adqPeople[(int) ($m['user_id'] ?? 0)]['issues'] ?? []) !== []));
    }
    ?>
    <?php // ——— 04 Qui relève de qui ——— ?>
    <section class="chn-panel bo-section" role="tabpanel" id="chn-panel-releve" aria-labelledby="chn-tab-releve" tabindex="0" data-chn-panel="releve"<?= $view !== 'releve' ? ' hidden' : '' ?>>
        <header class="bo-section__head" id="eff-chain-people-panel"<?= $onlyGaps ? ' data-filtered' : '' ?>>
            <span class="bo-section__index" aria-hidden="true">/ 04</span>
            <h3 class="bo-section__title">Qui relève de qui</h3>
            <?php if ($onlyGaps): ?>
            <div class="bo-section__aside"><span class="bo-chip"><b><?= count($peopleRows) ?></b> <?= $h($plural(count($peopleRows), 'personne avec un écart', 'personnes avec un écart')) ?></span></div>
            <?php endif; ?>
            <p class="bo-section__desc"><?= $onlyGaps ? 'Filtré : personnes avec un écart d’adéquation.' : 'La chaîne de chaque membre, lue depuis les chefs désignés et l’organigramme.' ?></p>
        </header>
        <?php if ($onlyGaps): ?>
            <p class="chn-filtered"><a class="chn-btn" href="<?= $h($chainUrl . '?vue=releve#eff-chain-people-panel') ?>" data-chain-filter="0">Afficher tout le monde</a></p>
        <?php endif; ?>
        <?php if ($peopleRows === []): ?>
            <?= effk_empty('Aucun membre actif à afficher.') ?>
        <?php else: ?>
            <div class="effk-table-wrap" role="region" aria-label="Qui relève de qui" tabindex="0">
                <table class="effk-table effk-table--stack" id="eff-chain-people">
                    <thead>
                        <tr>
                            <th scope="col">Membre</th>
                            <th scope="col">Unité</th>
                            <th scope="col">Relève de</th>
                            <th scope="col">Chaîne</th>
                            <?php if ($adq !== null): ?>
                            <th scope="col">Place</th>
                            <th scope="col">Adjoint</th>
                            <th scope="col">Adéquation</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($peopleRows as $m):
                        $mid = (int) ($m['user_id'] ?? 0);
                        $unitName = trim((string) ($m['unit_name'] ?? ''));
                        $reports = trim((string) ($m['reports_to_label'] ?? ''));
                        $commands = is_array($m['commands'] ?? null) ? $m['commands'] : [];
                        $chain = is_array($m['chain_labels'] ?? null) ? $m['chain_labels'] : [];
                        ?>
                        <tr>
                            <td data-label="Membre">
                                <a href="<?= $h(effectifs_workspace_url('membres/' . $mid)) ?>"><?= $h((string) ($m['label'] ?? 'Membre')) ?></a>
                                <?php if ($commands !== []): ?><small class="effk-muted effk-block">Chef de <?= $h(implode(', ', $commands)) ?></small><?php endif; ?>
                            </td>
                            <td data-label="Unité"><?= $unitName !== '' ? $h($unitName) : '<span class="effk-muted">Sans unité</span>' ?></td>
                            <td data-label="Relève de">
                                <?php if ($reports !== ''): ?>
                                    <?= $h($reports) ?>
                                <?php elseif ($unitName === '' && $commands === []): ?>
                                    <span class="effk-muted">À rattacher</span>
                                <?php else: ?>
                                    <span class="effk-muted">Chef non désigné</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Chaîne"><?= $chain !== [] ? '<span class="eff-chain__path">' . $h(implode(' → ', $chain)) . '</span>' : '<span class="effk-muted">—</span>' ?></td>
                            <?php if ($adq !== null):
                                $ap = $adqPeople[$mid] ?? null;
                                $apDeputy = (int) ($ap['deputy_id'] ?? 0);
                                ?>
                            <td data-label="Place"><?= $ap !== null ? $h(adq_place_label((string) $ap['place'])) : '<span class="effk-muted">—</span>' ?><?php if ($ap !== null && ($ap['roles'] ?? []) !== []): ?><small class="effk-muted effk-block"><?= adq_roles_html($ap['roles']) ?></small><?php endif; ?></td>
                            <td data-label="Adjoint"><?= $apDeputy > 0 ? $h((string) ($adqPeople[$apDeputy]['label'] ?? 'Membre')) : '<span class="effk-muted">—</span>' ?></td>
                            <td data-label="Adéquation"><?php if ($ap !== null): ?><?= adq_badge((string) $ap['status'], true) ?><?php foreach (array_slice($ap['issues'] ?? [], 0, 2) as $ii): ?><small class="effk-muted effk-block"><?= $h((string) ($adqIssues[$ii]['title'] ?? '')) ?></small><?php endforeach; ?><?php endif; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($withoutUnit > 0): ?>
                <div class="chn-foot">
                    <span><strong><?= $withoutUnit ?></strong> <?= $h($plural($withoutUnit, 'membre sans unité, donc sans chef.', 'membres sans unité, donc sans chef.')) ?></span>
                    <a class="chn-btn" href="<?= $h(effectifs_workspace_url() . '?sans_affectation=1') ?>">Ouvrir la liste des membres sans unité</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php foreach ($units as $u): ?><?= $detail($u) ?><?php endforeach; ?>
<?php endif; ?>
<?php if (!$fragment): ?>
    </div>

    <dialog class="effk-md effk-md--chain chn-dlg" data-chain-dialog aria-labelledby="eff-chain-dlg-title" aria-describedby="eff-chain-dlg-sub">
        <div class="effk-md__h">
            <div class="chn-dlg__titles"><h2 id="eff-chain-dlg-title" data-chain-dlg-title></h2><p class="chn-dlg__sub" id="eff-chain-dlg-sub" data-chain-dlg-kicker></p></div>
            <button type="button" class="effk-md__x" data-chain-dlg-close aria-label="Fermer"><?= effk_icon('x') ?></button>
        </div>
        <div class="effk-md__b" data-chain-dlg-body></div>
        <p class="effk-note is-danger effk-md__err" role="alert" data-chain-dlg-error hidden></p>
        <div class="effk-md__f">
            <button type="button" class="effk-md__btn" data-chain-dlg-back hidden>Retour</button>
            <button type="button" class="effk-md__btn" data-chain-dlg-close data-chain-dlg-dismiss>Annuler</button>
            <button type="button" class="effk-md__btn is-dark" data-chain-dlg-confirm hidden></button>
        </div>
    </dialog>
    <div class="effk-pop" data-chain-menu role="dialog" aria-labelledby="eff-chain-menu-title" hidden>
        <p class="effk-pop__h" id="eff-chain-menu-title" data-chain-menu-title></p>
        <div class="effk-pop__list" data-chain-menu-list></div>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/effectifs-kit.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/eff-chain.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/eff-chain-page.js')) ?>" defer></script>
<?php endif; ?>
