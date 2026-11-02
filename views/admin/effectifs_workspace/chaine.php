<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/effk.php';

/*
 * Effectifs > Chaîne de commandement (maquette « Effectifs Chaîne », 2026-11-02).
 * Organigramme : le chef de chaque unité, ses sous-unités et leurs membres ; « Remonter la chaîne » d’un membre.
 * Écriture : le même envoi qu’auparavant (POST chaine, commanders[unité] = membre), depuis le sélecteur
 * de l’organigramme ou depuis le tableau des chefs plus bas.
 */

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

/** Poste de chef d’une unité : titulaire ou poste à pourvoir. */
$slot = static function (array $u, string $variant = '') use ($h, $canManage, $memberById, $isWithin, $shortName, $unitById): string {
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
            . '<small>' . $h($unitName) . '</small></span>'
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
        . '<b><span class="effk-slot__name">' . $h($label) . '</span>' . $detached . '</b>'
        . '<small>' . $h($sub) . '</small></span>'
        . ($canManage ? effk_icon('down', 'effk-slot__ed') : '')
        . '</' . $tag . '>';
};

/** Membres d’une unité (hors chef) en pastilles ; chaîne vide s’il n’y en a aucun. */
$chips = static function (int $unitId) use ($h, $membersByUnit, $unitById, $chipLimit): string {
    $cid = (int) ($unitById[$unitId]['commander_user_id'] ?? 0);
    $list = array_values(array_filter($membersByUnit[$unitId] ?? [], static fn (array $m): bool => (int) $m['user_id'] !== $cid));
    if ($list === []) {
        return '';
    }
    $out = '';
    foreach (array_slice($list, 0, $chipLimit) as $m) {
        $mid = (int) $m['user_id'];
        $role = trim((string) ($m['role_name'] ?? ''));
        $out .= '<a class="effk-chip" href="' . $h(effectifs_workspace_url('membres/' . $mid)) . '" data-chain-chip="' . $mid . '" data-chain-member="' . $mid . '"'
            . ($role !== '' && $role !== 'Membre' ? ' title="' . $h($role) . '"' : '') . '>'
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
    ];
}
$structureLink = $canManage ? '<a class="effk-btn" href="' . $h(url('back-office/organisation/structure')) . '">' . effk_icon('tree') . 'Ouvrir la structure</a>' : '';
?>
<div class="effk effk-chainpage" data-eff-chain<?= $canManage ? ' data-can-manage' : '' ?>>
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

<?php if ($units === []): ?>
    <?= effk_empty(
        'Aucune unité définie',
        'Créez d’abord les unités de rattachement, puis revenez ici pour désigner qui commande chacune.',
        $canManage ? ' <a href="' . $h(url('back-office/organisation/structure')) . '">Ouvrir la structure</a>' : ''
    ) ?>
<?php else: ?>
    <div class="effk-pills" role="group" aria-label="Points à traiter">
        <?php
        $vacant = array_values(array_filter($units, static fn (array $u): bool => (int) ($u['commander_user_id'] ?? 0) < 1));
        if ($vacant === []) {
            echo effk_pill('Toutes les unités ont un chef', 'ok', 'check');
        }
        foreach (array_slice($vacant, 0, 8) as $u) {
            echo effk_pill('Chef à désigner · ' . (string) $u['name'], 'warn', 'alert', '', $canManage, $canManage ? ['data-chain-slot' => (string) (int) $u['id'], 'aria-haspopup' => 'dialog'] : []);
        }
        if (count($vacant) > 8) {
            echo effk_pill('+' . (count($vacant) - 8) . ' autres unités sans chef', 'warn');
        }
        if ($missingSuperiors > 0) {
            echo effk_pill($missingSuperiors . ' membre' . ($missingSuperiors > 1 ? 's' : '') . ' sans chef lisible', 'info');
        }
        if ($withoutUnit > 0) {
            echo effk_pill($withoutUnit . ' membre' . ($withoutUnit > 1 ? 's' : '') . ' sans unité, donc sans chef', 'info', '', effectifs_workspace_url() . '?sans_affectation=1');
        }
        ?>
    </div>

    <div class="effk-path" data-chain-path role="status" aria-live="polite" hidden></div>

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

    <?php if ($canManage): ?>
    <form method="post" action="<?= $h(effectifs_workspace_url('chaine')) ?>" data-chain-quick hidden>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <input type="hidden" name="commanders[0]" value="0" data-chain-quick-field>
    </form>
    <div class="effk-pop" data-chain-pop role="dialog" aria-labelledby="eff-chain-pop-title" hidden>
        <p class="effk-pop__h" id="eff-chain-pop-title" data-chain-pop-title></p>
        <label class="effk-pop__q"><?= effk_icon('search') ?><span class="sr-only">Filtrer les membres</span><input type="search" placeholder="Filtrer les membres…" autocomplete="off" data-chain-pop-q></label>
        <div class="effk-pop__list" data-chain-pop-list></div>
    </div>
    <?php endif; ?>
    <div class="effk-pop" data-chain-menu role="dialog" aria-labelledby="eff-chain-menu-title" hidden>
        <p class="effk-pop__h" id="eff-chain-menu-title" data-chain-menu-title></p>
        <div class="effk-pop__list" data-chain-menu-list></div>
    </div>
    <script type="application/json" id="eff-chain-data"><?= json_encode(['units' => (object) $jsUnits, 'members' => (object) $jsMembers, 'affectations' => effectifs_workspace_url('affectations')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <details class="effk-panel effk-fold" id="eff-chain-setup">
        <summary><span><b>Chefs d’unité</b><small>Tout le tableau, pour désigner plusieurs chefs d’un coup</small></span><?= effk_icon('down') ?></summary>
        <form method="post" action="<?= $h(effectifs_workspace_url('chaine')) ?>" class="eff-chain effk-fold__body">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <p class="effk-fold__lead">
                Pour chaque unité, choisissez la personne qui la commande. Laissez vide si le poste n’est pas encore pourvu.
                Pour rattacher une unité à une autre, ouvrez la structure.
            </p>
            <div class="effk-table-wrap" role="region" aria-label="Chefs d’unité" tabindex="0">
                <table class="effk-table" id="eff-chain-units">
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
                            <td>
                                <span class="eff-chain__unit" style="--eff-chain-depth:<?= $depth ?>"><?= $h($name !== '' ? $name : '—') ?></span>
                                <?= effk_unit_level_badge((string) ($u['visibility'] ?? '')) ?>
                            </td>
                            <td><?= $label !== '' ? '<span class="effk-tag">' . $h($label) . '</span>' : '<span class="effk-muted">—</span>' ?></td>
                            <td><?= $parentName !== '' ? $h($parentName) : '<span class="effk-muted">Sommet de l’organigramme</span>' ?></td>
                            <td>
                                <?php if ($canManage): ?>
                                    <select class="eff-chain__select effk-sel" id="eff-chain-chef-<?= $uid ?>" name="commanders[<?= $uid ?>]" aria-label="Chef de <?= $h($name !== '' ? $name : 'l’unité') ?>">
                                        <option value="0">Aucun chef pour le moment</option>
                                        <?php
                                        $inUnit = [];
                                        $others = [];
                                        foreach ($options as $opt) {
                                            $oid = (int) ($opt['id'] ?? 0);
                                            if ($oid < 1) {
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
                            <td class="effk-num"><?= (int) ($u['member_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canManage): ?>
                <div class="effk-fold__foot">
                    <span>Un chef laissé vide n’apparaît pas dans la chaîne tant qu’il n’est pas désigné.</span>
                    <button type="submit" class="effk-btn is-dark">Enregistrer les chefs</button>
                </div>
            <?php endif; ?>
        </form>
    </details>

    <details class="effk-panel effk-fold" id="eff-chain-people-panel">
        <summary><span><b>Qui relève de qui</b><small>La chaîne de chaque membre, lue depuis les chefs désignés et l’organigramme</small></span><?= effk_icon('down') ?></summary>
        <div class="effk-fold__body">
        <?php if ($members === []): ?>
            <?= effk_empty('Aucun membre actif à afficher.') ?>
        <?php else: ?>
            <div class="effk-table-wrap" role="region" aria-label="Qui relève de qui" tabindex="0">
                <table class="effk-table" id="eff-chain-people">
                    <thead>
                        <tr>
                            <th scope="col">Membre</th>
                            <th scope="col">Unité</th>
                            <th scope="col">Relève de</th>
                            <th scope="col">Chaîne</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $m):
                        $mid = (int) ($m['user_id'] ?? 0);
                        $unitName = trim((string) ($m['unit_name'] ?? ''));
                        $reports = trim((string) ($m['reports_to_label'] ?? ''));
                        $commands = is_array($m['commands'] ?? null) ? $m['commands'] : [];
                        $chain = is_array($m['chain_labels'] ?? null) ? $m['chain_labels'] : [];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= $h(effectifs_workspace_url('membres/' . $mid)) ?>"><?= $h((string) ($m['label'] ?? 'Membre')) ?></a>
                                <?php if ($commands !== []): ?><small class="effk-muted effk-block">Chef de <?= $h(implode(', ', $commands)) ?></small><?php endif; ?>
                            </td>
                            <td><?= $unitName !== '' ? $h($unitName) : '<span class="effk-muted">Sans unité</span>' ?></td>
                            <td>
                                <?php if ($reports !== ''): ?>
                                    <?= $h($reports) ?>
                                <?php elseif ($unitName === '' && $commands === []): ?>
                                    <span class="effk-muted">À rattacher</span>
                                <?php else: ?>
                                    <span class="effk-muted">Chef non désigné</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $chain !== [] ? '<span class="eff-chain__path">' . $h(implode(' → ', $chain)) . '</span>' : '<span class="effk-muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($withoutUnit > 0): ?>
                <div class="effk-fold__foot">
                    <span><strong><?= $withoutUnit ?></strong> membre<?= $withoutUnit > 1 ? 's' : '' ?> sans unité, donc sans chef.</span>
                    <a class="effk-btn" href="<?= $h(effectifs_workspace_url() . '?sans_affectation=1') ?>">Traiter les membres sans unité</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </details>
<?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/effectifs-kit.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/eff-chain.js')) ?>" defer></script>
