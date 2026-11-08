<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/effk.php';
require_once __DIR__ . '/partials/adequacy.php';

/*
 * Effectifs > Chaîne de commandement (maquette « Effectifs Chaîne », 2026-11-02).
 * Alertes (quel problème, où, quoi faire), propositions tirées des données (App\Services\Effectifs\CommandChainAdvisor),
 * organigramme, adéquation, tableau des chefs, « qui relève de qui ».
 * Écriture : POST chaine (commanders[unité] = membre) en arrière-plan depuis les fenêtres de eff-chain.js ; les
 * formulaires restent de vrais formulaires, qui fonctionnent sans JavaScript (repli).
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
$shortName = static function (string $name): string {
    $name = trim($name);

    return mb_strlen($name, 'UTF-8') > 18 ? mb_substr($name, 0, 17, 'UTF-8') . '…' : $name;
};
$crest = static function (array $u): string {
    $code = trim((string) ($u['code'] ?? ''));
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
$onlyGaps = !empty($adequacyOnlyGaps) && $adq !== null;
$memberUrl = static fn (int $id): string => effectifs_workspace_url('membres/' . $id);
/** Pastille d’adéquation d’une personne (sous-capacité, surqualification), chaîne vide sinon. */
$adqMark = static function (int $userId) use ($adqPeople): string {
    $status = (string) ($adqPeople[$userId]['status'] ?? '');

    return in_array($status, [\App\Services\Organization\OrgAdequacyEngine::STATUS_UNDER, \App\Services\Organization\OrgAdequacyEngine::STATUS_OVER], true)
        ? adq_badge($status, true) : '';
};
/** Ligne « Adjoint » sous le chef : adjoint désigné, adjoint de fait, ou adjoint attendu à nommer. */
$deputyLine = static function (int $unitId) use ($h, $adqUnits, $adqPeople, $adqIssues): string {
    $row = $adqUnits[$unitId] ?? null;
    if (!is_array($row)) {
        return '';
    }
    $did = (int) ($row['deputy_id'] ?? 0);
    if ($did > 0) {
        return '<small class="effk-slot__adj">' . $h(($row['deputy_source'] ?? '') === 'subunit' ? 'Adjoint de fait :' : 'Adjoint :') . ' '
            . $h((string) ($adqPeople[$did]['label'] ?? 'Membre')) . '</small>';
    }
    foreach ($row['issues'] ?? [] as $i) {
        if (($adqIssues[$i]['code'] ?? '') === 'deputy_missing') {
            return '<small class="effk-slot__adj is-miss">Adjoint à nommer</small>';
        }
    }

    return '';
};

/** Poste de chef d’une unité : titulaire ou poste à pourvoir. */
$slot = static function (array $u, string $variant = '') use ($h, $canManage, $memberById, $isWithin, $shortName, $unitById, $adqMark, $deputyLine): string {
    $uid = (int) $u['id'];
    $cid = (int) ($u['commander_user_id'] ?? 0);
    $type = effk_unit_type_label((string) ($u['type'] ?? ''));
    $role = 'Chef' . ($type !== '' ? ' · ' . $type : '');
    $unitName = trim((string) ($u['name'] ?? ''));
    $tag = $canManage ? 'button' : 'div';
    $attrs = ' class="effk-slot' . ($variant !== '' ? ' is-' . $variant : '') . ($cid < 1 ? ' is-vac' : '') . '" style="--c:' . effk_unit_color($uid) . '"'
        . ' data-chain-slot="' . $uid . '"' . ($cid > 0 ? ' data-chain-member="' . $cid . '"' : '');
    if ($canManage) {
        $attrs .= ' type="button" aria-haspopup="dialog" aria-label="' . $h(($cid > 0 ? 'Changer le chef de ' : 'Désigner le chef de ') . ($unitName !== '' ? $unitName : 'l’unité')) . '"';
    }
    if ($cid < 1) {
        return '<' . $tag . $attrs . '>'
            . '<span class="effk-slot__vi" aria-hidden="true">' . effk_icon('plus') . '</span>'
            . '<span class="effk-slot__txt"><span class="effk-k">' . $h($role) . '</span><b>Chef à désigner</b>'
            . '<small>' . $h($unitName) . '</small>' . $deputyLine($uid) . '</span>'
            . '</' . $tag . '>';
    }
    $m = $memberById[$cid] ?? null;
    $label = (string) ($m['label'] ?? ($u['commander_label'] ?? 'Membre'));
    $homeUnit = (int) ($m['unit_id'] ?? 0);
    $detached = '';
    if ($homeUnit < 1) {
        $detached = '<span class="effk-slot__det">sans unité</span>';
    } elseif (!$isWithin($homeUnit, $uid) && !$isWithin($uid, $homeUnit)) {
        $detached = '<span class="effk-slot__det">détaché · ' . $h($shortName((string) ($unitById[$homeUnit]['name'] ?? ''))) . '</span>';
    }
    $sub = trim((string) ($m['role_name'] ?? ''));
    if ($sub === '' || $sub === 'Membre') {
        $sub = $unitName;
    }

    return '<' . $tag . $attrs . '>'
        . effk_avatar($cid, $label, $variant === 'cmd' ? 'lg' : '')
        . '<span class="effk-slot__txt"><span class="effk-k">' . $h($role) . '</span>'
        . '<b><span class="effk-slot__name">' . $h($label) . '</span>' . $detached . $adqMark($cid) . '</b>'
        . '<small>' . $h($sub) . '</small>' . $deputyLine($uid) . '</span>'
        . ($canManage ? effk_icon('down', 'effk-slot__ed') : '')
        . '</' . $tag . '>';
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
$none = '<span class="effk-mem__none">Aucun membre</span>';

/** Sous-unités (récursif) : poste de chef, membres, puis leurs propres sous-unités. */
$branch = static function (int $unitId, int $depth = 0) use (&$branch, $childrenOf, $unitById, $membersByUnit, $slot, $chips, $none, $h): string {
    $out = '';
    foreach ($childrenOf[$unitId] ?? [] as $cid) {
        $c = $unitById[$cid];
        $badge = effk_unit_level_badge((string) ($c['visibility'] ?? ''));
        $memberChips = $chips($cid);
        $grpCount = count($membersByUnit[$cid] ?? []);
        $out .= '<div class="effk-grp" style="--c:' . effk_unit_color($cid) . '">'
            . '<div class="effk-grp__h"><i aria-hidden="true"></i><b>' . $h((string) ($c['name'] ?? '')) . '</b>'
            . '<small>' . $grpCount . ' membre' . ($grpCount > 1 ? 's' : '') . '</small></div>'
            . ($badge !== '' ? '<div class="effk-grp__conf">' . $badge . '</div>' : '')
            . $slot($c, 'grp')
            . '<div class="effk-mem">' . ($memberChips !== '' ? $memberChips : $none) . '</div>'
            . ($depth < 6 && ($childrenOf[$cid] ?? []) !== [] ? '<div class="effk-sub">' . $branch($cid, $depth + 1) . '</div>' : '')
            . '</div>';
    }

    return $out;
};

/** Colonne d’une unité de premier rang sous le sommet. */
$column = static function (int $unitId) use ($unitById, $childrenOf, $membersByUnit, $slot, $chips, $branch, $crest, $h, $none): string {
    $u = $unitById[$unitId];
    $count = count($membersByUnit[$unitId] ?? []);
    $subs = count($childrenOf[$unitId] ?? []);
    $badge = effk_unit_level_badge((string) ($u['visibility'] ?? ''));
    $memberChips = $chips($unitId);
    $lower = ($memberChips !== '' || $subs === 0 ? '<div class="effk-grp"><span class="effk-k">Membres</span><div class="effk-mem">' . ($memberChips !== '' ? $memberChips : $none) . '</div></div>' : '')
        . $branch($unitId);

    return '<div class="effk-uc" style="--c:' . effk_unit_color($unitId) . '">'
        . '<div class="effk-uh"><span class="effk-crest" translate="no">' . $h($crest($u)) . '</span>'
        . '<span class="effk-uh__txt"><b>' . $h((string) $u['name']) . '</b>'
        . '<small>' . $count . ' membre' . ($count > 1 ? 's' : '') . ($subs > 0 ? ' · ' . $subs . ' sous-unité' . ($subs > 1 ? 's' : '') : '') . '</small>'
        . ($badge !== '' ? '<span class="effk-uh__conf">' . $badge . '</span>' : '')
        . '</span></div>'
        . '<div class="effk-chainv">' . $slot($u) . '<div class="effk-sub">' . $lower . '</div></div>'
        . '</div>';
};

$roots = $childrenOf[0] ?? [];
$withoutUnitMembers = array_values(array_filter($members, static fn (array $m): bool => (int) ($m['unit_id'] ?? 0) < 1 && ($m['commands'] ?? []) === []));

// Données du sélecteur de chef et de « Remonter la chaîne ».
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
$alertRow = static function (array $a) use ($h, $canManage): string {
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
        ? '<a class="effk-btn is-sm" href="#eff-chain-setup" data-chain-slot="' . $unitId . '" aria-haspopup="dialog">' . $h((string) ($a['action'] ?? 'Désigner un chef')) . '</a>'
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
$structureLink = $canManage ? '<a class="effk-btn" href="' . $h(url('back-office/organisation/structure')) . '">' . effk_icon('tree') . 'Ouvrir la structure</a>' : '';
?>
<?php if (!$fragment): ?>
<div class="effk effk-chainpage" data-eff-chain data-chain-url="<?= $h($chainUrl) ?>" data-chain-csrf="<?= $h($csrf) ?>" data-chain-assign-url="<?= $h(effectifs_workspace_url('membres/__ID__/affectation')) ?>"<?= $canManage ? ' data-can-manage' : '' ?>>
    <?php
    $actions = $structureLink;
    if ($units !== [] && $members !== []) {
        $actions = '<label class="effk-search effk-search--wide">' . effk_icon('route')
            . '<span class="sr-only">Remonter la chaîne d’un membre</span>'
            . '<input type="search" id="eff-chain-q" list="eff-chain-names" placeholder="Remonter la chaîne d’un membre…" autocomplete="off" data-chain-q></label>'
            . '<datalist id="eff-chain-names">';
        foreach ($members as $m) {
            $actions .= '<option value="' . $h((string) $m['label']) . '"></option>';
        }
        $actions .= '</datalist>' . $structureLink;
    }
    echo effk_page_head(
        'Organisation',
        'Chaîne de commandement',
        'Qui commande qui. Désignez le chef de chaque unité et vérifiez que chaque membre sait à qui rendre compte : un membre relève du chef de son unité, un chef relève du chef de l’unité au-dessus.',
        $actions
    );
    ?>
    <?= $adq !== null ? adq_styles() : '' ?>

    <div class="effk-path" data-chain-path role="status" aria-live="polite" hidden></div>

    <div class="effk-chain-live" data-chain-live>
<?php endif; ?>

<?php if ($units === []): ?>
    <?= effk_empty(
        'Aucune unité définie',
        $canManage ? 'La chaîne de commandement se construit en trois étapes.' : 'La communauté n’a pas encore d’unité : la chaîne de commandement apparaîtra ici.',
        $canManage
            ? '<ol class="effk-chain-steps"><li>Créez les unités dans la structure.</li><li>Affectez les membres à leur unité.</li><li>Revenez ici pour désigner le chef de chacune.</li></ol>'
                . '<a class="effk-btn is-dark" href="' . $h(url('back-office/organisation/structure')) . '">' . effk_icon('tree') . '<span>Créer les unités</span></a>'
            : ''
    ) ?>
<?php else: ?>
    <?php
    $adqCount = count($adqIssues);
    $stats = [
        ['label' => 'Unités', 'value' => count($units), 'hint' => 'Dans l’organigramme'],
        ['label' => 'Chefs désignés', 'value' => $designated . ' / ' . count($units), 'tone' => $missingChefs > 0 ? 'warn' : 'ok', 'hint' => $missingChefs > 0 ? $missingChefs . ' à désigner' : 'Tous les postes sont pourvus'],
        ['label' => 'Membres sans chef', 'value' => $missingSuperiors, 'tone' => $missingSuperiors > 0 ? 'warn' : 'ok', 'hint' => 'Aucun chef au-dessus d’eux'],
        ['label' => 'Membres sans unité', 'value' => $withoutUnit, 'tone' => $withoutUnit > 0 ? 'warn' : 'ok', 'hint' => 'Hors de l’organigramme'],
    ];
    if ($adq !== null) {
        $stats[] = ['label' => 'Écarts d’adéquation', 'value' => $adqCount, 'tone' => $adqCount > 0 ? 'warn' : 'ok', 'hint' => 'Postes et échelons', 'href' => '#eff-adequacy'];
    }
    echo effk_stats($stats, 'État de la chaîne de commandement');
    ?>

    <div class="effk-chain-todo<?= $proposals !== [] ? ' has-proposals' : '' ?>">
        <section class="effk-panel effk-chain-box" aria-labelledby="eff-chain-alerts-title">
            <header class="effk-chain-box__h">
                <h3 id="eff-chain-alerts-title">À corriger</h3>
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
                <h3 id="eff-chain-proposals-title">Propositions</h3>
                <span class="effk-chain-box__n"><?= count($proposals) ?></span>
            </header>
            <p class="effk-chain-box__lead">Tirées des grades, des affectations et des postes de la communauté. Rien n’est appliqué sans votre confirmation.</p>
            <?= $foldedList($proposals, $proposalRow, 'effk-chain-list') ?>
        </section>
        <?php endif; ?>
    </div>

    <section class="effk-chart" aria-labelledby="eff-chain-chart-title">
        <div class="effk-chart__bar">
            <b id="eff-chain-chart-title"><?= $h($community !== '' ? $community : 'Organigramme') ?></b>
            <span><?= count($units) ?> unité<?= count($units) > 1 ? 's' : '' ?> · <?= $designated ?> chef<?= $designated > 1 ? 's' : '' ?> désigné<?= $designated > 1 ? 's' : '' ?> · <?= $missingChefs ?> à désigner</span>
            <span class="effk-sp" aria-hidden="true"></span>
            <span class="effk-lg"><i aria-hidden="true"></i>Chef désigné</span>
            <span class="effk-lg"><i class="is-vac" aria-hidden="true"></i>À désigner</span>
            <span class="effk-lg"><i class="is-det" aria-hidden="true"></i>Détaché</span>
            <span class="effk-lg"><i class="is-hl" aria-hidden="true"></i>Chaîne suivie</span>
        </div>
        <div class="effk-chart__in" data-chain-chart>
            <?php if (count($roots) === 1): ?>
                <?php
                $root = $unitById[$roots[0]];
                $rootChips = $chips($roots[0]);
                $cols = $childrenOf[$roots[0]] ?? [];
                ?>
                <div class="effk-top">
                    <?= $slot($root, 'cmd') ?>
                    <p class="effk-top__unit"><?= $h((string) $root['name']) ?> <?= effk_unit_level_badge((string) ($root['visibility'] ?? '')) ?></p>
                    <?php if ($rootChips !== ''): ?>
                    <div class="effk-staff"><div class="effk-grp"><span class="effk-k">Rattachés à <?= $h((string) $root['name']) ?></span><div class="effk-mem"><?= $rootChips ?></div></div></div>
                    <?php endif; ?>
                </div>
                <?php if ($cols !== []): ?>
                <div class="effk-vline" aria-hidden="true"></div>
                <div class="effk-units" style="--n:<?= min(4, count($cols)) ?>">
                    <?php foreach ($cols as $cid): ?><?= $column($cid) ?><?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="effk-units is-roots" style="--n:<?= min(4, count($roots)) ?>">
                    <?php foreach ($roots as $rid): ?><?= $column($rid) ?><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($withoutUnitMembers !== []): ?>
        <p class="effk-chart__foot">
            <span>Sans unité, hors chaîne :</span>
            <span><?= $h(implode(', ', array_map(static fn (array $m): string => (string) $m['label'], array_slice($withoutUnitMembers, 0, 12)))) ?><?= count($withoutUnitMembers) > 12 ? ' (+' . (count($withoutUnitMembers) - 12) . ')' : '' ?></span>
            · <a href="<?= $h(effectifs_workspace_url('affectations')) ?>">gérer les affectations</a>
        </p>
        <?php endif; ?>
    </section>

    <?php if ($adq !== null): ?>
    <?php
    $byUnitIssues = [];
    $personIssues = [];
    foreach ($adqIssues as $issue) {
        $iu = (int) ($issue['unit_id'] ?? 0);
        if ($iu > 0 && isset($unitById[$iu])) {
            $byUnitIssues[$iu][] = $issue;
        } else {
            $personIssues[] = $issue;
        }
    }
    $settingsUrl = effectifs_workspace_url('chaine/adequation');
    ?>
    <details class="effk-panel effk-fold adq-scope" id="eff-adequacy"<?= $onlyGaps || $adqIssues !== [] ? ' open' : '' ?>>
        <summary><span><b>Adéquation de l’organisation</b><small><?= $adqIssues === [] ? 'Chaque poste est tenu au niveau attendu par son échelon' : str_replace('%1', (string) count($adqIssues), count($adqIssues) > 1 ? '%1 écarts à examiner' : '%1 écart à examiner') ?></small></span><?= effk_icon('down') ?></summary>
        <div class="effk-fold__body">
            <p class="effk-fold__lead">
                Chaque échelon attend un chef d’un certain grade, souvent un adjoint et un effectif de référence.
                Les écarts décrivent la capacité de commandement d’un poste au regard de son échelon, pas la valeur des personnes :
                une sous-capacité signale un poste tenu en dessous de ce qu’il demande, une surqualification une expérience qui servirait plus haut.
            </p>
            <?php $adqSummary = adq_summary_items(is_array($adq['summary'] ?? null) ? $adq['summary'] : []); ?>
            <?php if ($adqSummary !== []): ?>
            <div class="effk-pills" role="group" aria-label="Écarts par nature">
                <?php foreach ($adqSummary as [$sLabel, $sCount, $sTone]): ?>
                    <a class="effk-pill<?= $sTone !== 'muted' ? ' is-' . $h($sTone) : '' ?>" href="<?= $h($chainUrl . '?ecarts=1#eff-chain-people-panel') ?>" data-chain-filter="1"><span><?= $h($sLabel) ?></span> <b><?= (int) $sCount ?></b></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="adq-filter">
                <a class="effk-btn" href="<?= $h($settingsUrl) ?>"><?= effk_icon('route') ?><span>Référentiel et réglages de l’adéquation</span></a>
                <?php if ($onlyGaps): ?>
                    <a class="effk-btn" href="<?= $h($chainUrl . '#eff-chain-people-panel') ?>" data-chain-filter="0">Afficher tout le monde</a>
                <?php else: ?>
                    <a class="effk-btn" href="<?= $h($chainUrl . '?ecarts=1#eff-chain-people-panel') ?>" data-chain-filter="1">Filtrer : personnes avec un écart</a>
                <?php endif; ?>
            </div>
            <?php if ($adqIssues === []): ?>
                <?= effk_empty('Aucun écart', 'Chaque personne a sa place, son chef, et chaque poste est tenu au niveau attendu.') ?>
            <?php else: ?>
                <div class="adq-groups">
                    <?php foreach ($units as $u):
                        $uid = (int) $u['id'];
                        if (($byUnitIssues[$uid] ?? []) === []) {
                            continue;
                        }
                        $urow = $adqUnits[$uid] ?? [];
                        ?>
                        <div class="adq-group" id="eff-adequacy-unit-<?= $uid ?>">
                            <h4><?= $h((string) $u['name']) ?>
                                <?php if (($urow['standard_label'] ?? '') !== ''): ?><small><?= $h((string) $urow['standard_label']) ?> · <?= (int) ($urow['strength'] ?? 0) ?> / <?= $h((string) ($urow['strength_expected'] ?? '')) ?></small><?php endif; ?>
                                <?= effk_unit_level_badge((string) ($u['visibility'] ?? '')) ?>
                            </h4>
                            <?= adq_issue_list($byUnitIssues[$uid], $adqPeople, $memberUrl, $canManage) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($personIssues !== []): ?>
                        <div class="adq-group">
                            <h4>Personnes hors de l’organigramme</h4>
                            <?php
                            $named = [];
                            foreach ($personIssues as $pi) {
                                $piUser = (int) ($pi['user_id'] ?? 0);
                                $pi['title'] = (string) ($adqPeople[$piUser]['label'] ?? 'Membre');
                                $named[] = $pi;
                            }
                            ?>
                            <?= adq_issue_list($named, $adqPeople, $memberUrl, $canManage, 30) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </details>
    <?php endif; ?>

    <script type="application/json" id="eff-chain-data"><?= json_encode(['units' => (object) $jsUnits, 'members' => (object) $jsMembers, 'order' => array_map(static fn (array $u): int => (int) $u['id'], $units), 'affectations' => effectifs_workspace_url('affectations')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <details class="effk-panel effk-fold" id="eff-chain-setup">
        <summary><span><b>Chefs d’unité</b><small>Tout le tableau, pour désigner plusieurs chefs d’un coup</small></span><?= effk_icon('down') ?></summary>
        <form method="post" action="<?= $h($chainUrl) ?>" class="eff-chain effk-fold__body" data-chain-bulk>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <p class="effk-fold__lead">
                Pour chaque unité, choisissez la personne qui la commande. Laissez vide si le poste n’est pas encore pourvu.
                Pour rattacher une unité à une autre, ouvrez la structure.
            </p>
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
                <div class="effk-fold__foot effk-chain-bulk">
                    <span data-chain-bulk-count>Un chef laissé vide n’apparaît pas dans la chaîne tant qu’il n’est pas désigné.</span>
                    <span class="effk-chain-bulk__a">
                        <button type="button" class="effk-btn" data-chain-bulk-reset hidden>Annuler mes choix</button>
                        <button type="submit" class="effk-btn is-dark" data-chain-bulk-save>Enregistrer les chefs</button>
                    </span>
                </div>
            <?php endif; ?>
        </form>
    </details>

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
    <details class="effk-panel effk-fold" id="eff-chain-people-panel"<?= $onlyGaps ? ' open' : '' ?>>
        <summary><span><b>Qui relève de qui</b><small><?= $onlyGaps ? 'Filtré : personnes avec un écart d’adéquation' : 'La chaîne de chaque membre, lue depuis les chefs désignés et l’organigramme' ?></small></span><?= effk_icon('down') ?></summary>
        <div class="effk-fold__body">
        <?php if ($onlyGaps): ?>
            <p class="effk-fold__lead"><?= count($peopleRows) ?> personne<?= count($peopleRows) > 1 ? 's' : '' ?> avec un écart. <a href="<?= $h($chainUrl . '#eff-chain-people-panel') ?>" data-chain-filter="0">Afficher tout le monde</a></p>
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
                <div class="effk-fold__foot">
                    <span><strong><?= $withoutUnit ?></strong> membre<?= $withoutUnit > 1 ? 's' : '' ?> sans unité, donc sans chef.</span>
                    <a class="effk-btn" href="<?= $h(effectifs_workspace_url() . '?sans_affectation=1') ?>">Ouvrir la liste des membres sans unité</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </details>
<?php endif; ?>
<?php if (!$fragment): ?>
    </div>

    <?php if ($canManage): ?>
    <dialog class="effk-md effk-md--chain" data-chain-dialog aria-labelledby="eff-chain-dlg-title">
        <div class="effk-md__h">
            <div><span class="effk-k" data-chain-dlg-kicker></span><h2 id="eff-chain-dlg-title" data-chain-dlg-title></h2></div>
            <button type="button" class="effk-md__x" data-chain-dlg-close aria-label="Fermer"><?= effk_icon('x') ?></button>
        </div>
        <div class="effk-md__b" data-chain-dlg-body></div>
        <p class="effk-note is-danger effk-md__err" role="alert" data-chain-dlg-error hidden></p>
        <div class="effk-md__f">
            <button type="button" class="effk-md__btn" data-chain-dlg-back hidden>Retour</button>
            <button type="button" class="effk-md__btn" data-chain-dlg-close>Annuler</button>
            <button type="button" class="effk-md__btn is-dark" data-chain-dlg-confirm hidden></button>
        </div>
    </dialog>
    <?php endif; ?>
    <div class="effk-pop" data-chain-menu role="dialog" aria-labelledby="eff-chain-menu-title" hidden>
        <p class="effk-pop__h" id="eff-chain-menu-title" data-chain-menu-title></p>
        <div class="effk-pop__list" data-chain-menu-list></div>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/effectifs-kit.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/eff-chain.js')) ?>" defer></script>
<?php endif; ?>
