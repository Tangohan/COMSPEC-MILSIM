<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/unit_code.php';
require_once __DIR__ . '/partials/effk.php';

/*
 * Effectifs > Affectations (maquette « Effectifs Affectations », 2026-11-02).
 * Tableau par unité : glisser une carte (ou son bouton ⇄) ouvre la mutation, envoyée à l’action habituelle
 * POST membres/{id}/affectation (une transaction, règle unique d’appartenance UnitAssignmentConsistency).
 * Vue « Par membre » : qui tient quoi (unité, rôle, emplois, accès). Codes des unités : communauté et repères.
 */

$matrix = is_array($affMatrix ?? null) ? $affMatrix : [];
$members = is_array($matrix['members'] ?? null) ? $matrix['members'] : [];
$unitsList = is_array($matrix['units'] ?? null) ? $matrix['units'] : [];
$filtersData = is_array($matrix['filters'] ?? null) ? $matrix['filters'] : ['jobs' => [], 'roles' => [], 'access' => []];
$stats = is_array($matrix['stats'] ?? null) ? $matrix['stats'] : [];
$community = is_array($matrix['community'] ?? null) ? $matrix['community'] : null;
$movements = is_array($affMovements ?? null) ? $affMovements : [];
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$canEditCommunityCode = (bool) ($canEditCommunityCode ?? false);
$csrf = (string) ($csrfToken ?? '');
$view = (string) ($affView ?? 'units');
$initialQuery = (string) ($affInitialQuery ?? '');
$initialUnit = (int) ($affInitialUnit ?? 0);
if ($initialUnit > 0) {
    $view = 'units';
}
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$norm = static fn (string $v): string => mb_strtolower($v, 'UTF-8');
$initialUnitCode = '';
$unitById = [];
foreach ($unitsList as $u) {
    $unitById[(int) $u['id']] = $u;
    if ((int) $u['id'] === $initialUnit) {
        $initialUnitCode = (string) $u['code'];
    }
}
$noUnitColor = '#d97706';
$withoutUnitMembers = array_values(array_filter($members, static fn (array $m): bool => $m['unit_id'] === null));

/** Texte recherché d’un membre (mêmes champs dans les deux vues). */
$memberHaystack = static function (array $m) use ($norm): string {
    $jobNames = array_map(static fn (array $j): string => (string) $j['name'], $m['jobs']);

    return $norm(implode(' ', array_merge(
        [$m['name'], $m['callsign'], $m['character_name'], $m['grade'], $m['unit_name'], $m['unit_path'], $m['unit_code'], $m['unit_admin_id'], $m['role']],
        $jobNames,
        $m['access']
    )));
};
/** Attributs de filtre communs (ligne du tableau ou carte du tableau par unité). */
$filterAttrs = static function (array $m) use ($h, $norm, $memberHaystack): string {
    $jobNames = array_map(static fn (array $j): string => (string) $j['name'], $m['jobs']);

    return ' data-search="' . $h($memberHaystack($m)) . '"'
        . ' data-code="' . $h((string) $m['unit_code']) . '"'
        . ' data-jobs="' . $h('|' . implode('|', array_map($norm, $jobNames)) . '|') . '"'
        . ' data-role="' . $h($norm((string) $m['role'])) . '"'
        . ' data-access="' . $h('|' . implode('|', array_map($norm, $m['access'])) . '|') . '"';
};
/** Carte d’un membre dans une colonne. */
$card = static function (array $m) use ($h, $filterAttrs, $canManageAssignments): string {
    $id = (int) $m['id'];
    $role = trim((string) $m['role']);
    $job = $m['jobs'] !== [] ? (string) $m['jobs'][0]['name'] : '';
    if ($role !== '' && $role !== 'Membre') {
        $sub = '<small class="is-pos">' . $h($role . ($job !== '' ? ' · ' . $job : '')) . '</small>';
    } elseif ($job !== '' || (string) $m['callsign'] !== '') {
        $sub = '<small>' . $h(implode(' · ', array_filter([$job, (string) $m['callsign'] !== '' ? '« ' . $m['callsign'] . ' »' : '']))) . '</small>';
    } else {
        $sub = '<small>Sans rôle ni emploi</small>';
    }

    return '<div class="effk-mc" data-eff-aff-card data-member="' . $id . '"' . $filterAttrs($m) . ($canManageAssignments ? ' draggable="true"' : '') . '>'
        . effk_avatar($id, (string) $m['name'])
        . '<span class="effk-mc__txt"><b><a href="' . $h(effectifs_workspace_url('membres/' . $id)) . '" draggable="false">' . $h((string) $m['name']) . '</a>' . effk_grade((string) $m['grade']) . '</b>' . $sub . '</span>'
        . ($canManageAssignments ? '<button type="button" class="effk-mc__mv" data-eff-aff-move="' . $id . '" aria-haspopup="dialog" aria-label="' . $h('Muter ' . $m['name']) . '" title="Muter sans glisser">' . effk_icon('move') . '</button>' : '')
        . '</div>';
};

// Chiffres.
$monthAgo = date('Y-m-d', strtotime('-30 days'));
$recentMoves = count(array_filter($movements, static fn (array $mv): bool => (string) $mv['date'] >= $monthAgo));
$statItems = [
    ['label' => 'Effectif actif', 'value' => (int) ($stats['members'] ?? 0), 'hint' => 'dont ' . (int) ($stats['assigned'] ?? 0) . ' dans une unité'],
    ['label' => 'Sans unité', 'value' => (int) ($stats['without_unit'] ?? 0), 'hint' => 'à rattacher', 'tone' => (int) ($stats['without_unit'] ?? 0) > 0 ? 'warn' : '', 'href' => effectifs_workspace_url() . '?sans_affectation=1'],
    ['label' => 'Sans emploi', 'value' => (int) ($stats['without_job'] ?? 0), 'hint' => 'aucun emploi au dossier', 'tone' => (int) ($stats['without_job'] ?? 0) > 0 ? 'warn' : ''],
];
if ((int) ($stats['seats_authorized'] ?? 0) > 0) {
    $statItems[] = ['label' => 'Postes vacants', 'value' => (int) ($stats['seats_vacant'] ?? 0), 'hint' => 'sur ' . (int) $stats['seats_authorized'] . ' postes prévus à l’ORBAT', 'tone' => (int) ($stats['seats_vacant'] ?? 0) > 0 ? 'warn' : ''];
}
$statItems[] = ['label' => 'Unités', 'value' => count($unitsList), 'hint' => 'dont ' . (int) ($stats['empty_units'] ?? 0) . ' sans titulaire'];
$statItems[] = ['label' => 'Mutations', 'value' => $recentMoves >= 30 ? '30+' : $recentMoves, 'hint' => 'sur les 30 derniers jours'];

// Données de la fenêtre de mutation.
$jsUnits = [];
foreach ($unitsList as $u) {
    $jsUnits[(int) $u['id']] = [
        'name' => (string) $u['name'],
        'code' => (string) $u['code'],
        'color' => effk_unit_color((int) $u['id']),
        'count' => count($u['holders']),
        'seats' => is_array($u['seats'] ?? null) ? (int) $u['seats']['authorized'] : 0,
        'chef' => (int) ($u['commander_user_id'] ?? 0),
    ];
}
$jsMembers = [];
foreach ($members as $m) {
    $jsMembers[(int) $m['id']] = [
        'name' => (string) $m['name'],
        'grade' => (string) $m['grade'],
        'callsign' => (string) $m['callsign'],
        'unit' => (int) ($m['unit_id'] ?? 0),
        'role' => (string) $m['role'],
        'color' => effk_member_color((int) $m['id']),
        'initials' => effk_initials((string) $m['name']),
    ];
}
?>
<div class="effk eff-aff" data-eff-aff>
    <?= effk_page_head(
        'Organisation',
        'Affectations',
        $canManageAssignments
            ? 'Glissez un membre d’une unité à l’autre pour le muter. Chaque mutation est datée du jour et motivée ; elle rejoint l’historique d’affectation de son dossier.'
            : 'Qui tient quoi, dans quelle unité et avec quel rôle, avec le code de chaque unité.',
        '<a class="effk-btn" href="' . $h(effectifs_workspace_url('chaine')) . '">' . effk_icon('route') . 'Voir la chaîne de commandement</a>'
    ) ?>

    <?= effk_stats($statItems, 'Aperçu des affectations') ?>

    <details class="effk-panel effk-fold effk-codes" id="eff-aff-codes">
        <summary>
            <span><b>Codes des unités</b><small>Code de la communauté<?php if ($community !== null): ?> <?= eff_unit_code_html((string) $community['code'], 'n° ' . (string) $community['admin_id'], 'sm') ?><?php endif; ?> puis un repère par échelon</small></span>
            <?= effk_icon('down') ?>
        </summary>
        <div class="effk-fold__body">
            <div class="effk-codes__grid">
                <div>
                    <p>
                        Chaque unité porte un code construit depuis l’organigramme : code de la communauté, puis un repère par échelon
                        (par exemple <?= eff_unit_code_html(($community['code'] ?? 'SOAR') . '-B-A-1', '', 'sm') ?>).
                        Le numéro à côté est l’identifiant de l’unité : il ne change jamais, même si l’unité est renommée ou déplacée.
                    </p>
                    <?php if ($community !== null): ?>
                    <p>Votre communauté : <?= eff_unit_code_html((string) $community['code'], 'n° ' . (string) $community['admin_id']) ?></p>
                    <?php else: ?>
                    <p class="effk-warn-text" role="status">Les codes n’ont pas pu être calculés pour le moment. Rechargez la page ; si ce message reste affiché, signalez-le au support.</p>
                    <?php endif; ?>
                </div>
                <?php if ($community !== null && $canEditCommunityCode): ?>
                <form method="post" action="<?= $h(effectifs_workspace_url('codes/communaute')) ?>" class="effk-codes__form">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <label for="eff-aff-community-code">Préfixe visuel des codes unité
                        <input id="eff-aff-community-code" name="community_code" value="<?= $h((string) $community['code']) ?>" minlength="2" maxlength="10" pattern="[A-Za-z0-9]{2,10}" title="De 2 à 10 lettres ou chiffres" autocomplete="off" spellcheck="false">
                    </label>
                    <p>Ce préfixe remplace le premier segment à l’affichage. Le tenant et les identifiants administratifs restent inchangés. Laissez vide pour rétablir le préfixe par défaut.</p><button type="submit" class="effk-btn is-dark">Enregistrer</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </details>

    <form class="effk-bar" role="search" data-eff-aff-filters>
        <label class="effk-search">
            <?= effk_icon('search') ?>
            <span class="sr-only">Rechercher</span>
            <input type="search" data-eff-aff-q value="<?= $h($initialQuery) ?>" placeholder="Membre, indicatif, unité, code, emploi…" data-eff-slash-focus>
        </label>
        <label class="sr-only" for="eff-aff-unit">Unité</label>
        <select id="eff-aff-unit" data-eff-aff-unit class="<?= $initialUnitCode !== '' ? 'is-set' : '' ?>">
            <option value="">Toutes les unités</option>
            <?php foreach ($unitsList as $u): ?>
                <?php if ((string) $u['code'] === '') {
                    continue;
                } ?>
                <option value="<?= $h((string) $u['code']) ?>" <?= (int) $u['id'] === $initialUnit ? 'selected' : '' ?>><?= $h(str_repeat('  ', (int) $u['depth']) . $u['code'] . ' · ' . $u['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filtersData['jobs'] !== []): ?>
        <label class="sr-only" for="eff-aff-job">Emploi</label>
        <select id="eff-aff-job" data-eff-aff-job>
            <option value="">Tous les emplois</option>
            <?php foreach ($filtersData['jobs'] as $job): ?><option value="<?= $h($norm($job)) ?>"><?= $h($job) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($filtersData['roles'] !== []): ?>
        <label class="sr-only" for="eff-aff-role">Rôle dans l’unité</label>
        <select id="eff-aff-role" data-eff-aff-role>
            <option value="">Tous les rôles</option>
            <?php foreach ($filtersData['roles'] as $role): ?><option value="<?= $h($norm($role)) ?>"><?= $h($role) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($filtersData['access'] !== []): ?>
        <label class="sr-only" for="eff-aff-access">Niveau d’accès</label>
        <select id="eff-aff-access" data-eff-aff-access>
            <option value="">Tous les accès</option>
            <?php foreach ($filtersData['access'] as $acc): ?><option value="<?= $h($norm($acc)) ?>"><?= $h($acc) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label class="effk-check"><input type="checkbox" data-eff-aff-gap value="unit"> Sans unité</label>
        <label class="effk-check"><input type="checkbox" data-eff-aff-gap value="job"> Sans emploi</label>
        <span class="effk-sp" aria-hidden="true"></span>
        <?php if ($canManageAssignments): ?>
        <small class="effk-tip" data-eff-aff-tip<?= $view !== 'units' ? ' hidden' : '' ?>>Astuce : le bouton <b aria-hidden="true">⇄</b> d’une carte mute sans glisser.</small>
        <?php endif; ?>
        <div class="effk-seg" role="group" aria-label="Présentation">
            <button type="button" data-eff-aff-view="units" class="<?= $view === 'units' ? 'is-on' : '' ?>" aria-pressed="<?= $view === 'units' ? 'true' : 'false' ?>">Par unité</button>
            <button type="button" data-eff-aff-view="members" class="<?= $view === 'members' ? 'is-on' : '' ?>" aria-pressed="<?= $view === 'members' ? 'true' : 'false' ?>">Par membre</button>
        </div>
    </form>
    <p class="effk-count" role="status" aria-live="polite" data-eff-aff-count>
        <b><?= count($members) ?></b> membre<?= count($members) > 1 ? 's' : '' ?><?= !empty($stats['truncated']) ? ' (les ' . \App\Services\Effectifs\AffectationsMatrixService::MAX_MEMBERS . ' premiers)' : '' ?>
    </p>

    <div data-eff-aff-pane="units" id="eff-aff-units"<?= $view !== 'units' ? ' hidden' : '' ?>>
        <div class="effk-lay">
            <div class="effk-board" data-eff-aff-board>
            <?php if ($unitsList === []): ?>
                <?= effk_empty(
                    'Aucune unité dans l’organigramme',
                    'Créez vos unités dans la structure pour leur attribuer un code.',
                    $canManageAssignments ? '<br><a href="' . $h(url('back-office/organisation/structure')) . '">Ouvrir la structure</a>' : ''
                ) ?>
            <?php endif; ?>
            <?php foreach ($unitsList as $u): ?>
                <?php
                $uid = (int) $u['id'];
                $holders = $u['holders'];
                $seats = is_array($u['seats'] ?? null) ? $u['seats'] : null;
                $count = count($holders);
                $authorized = $seats !== null ? (int) $seats['authorized'] : 0;
                $unitHay = $norm(implode(' ', array_merge(
                    [$u['name'], $u['code'], $u['admin_id'], $u['path'], $u['commander']],
                    $seats['vacant'] ?? []
                )));
                $type = effk_unit_type_label((string) $u['type']);
                $parentPath = '';
                if ((int) $u['parent_id'] > 0 && isset($unitById[(int) $u['parent_id']])) {
                    $parentPath = (string) $unitById[(int) $u['parent_id']]['name'];
                }
                $subLine = implode(' · ', array_filter([
                    $type,
                    $parentPath !== '' ? 'rattachée à ' . $parentPath : '',
                    $u['children'] > 0 ? $u['children'] . ' sous-unité' . ($u['children'] > 1 ? 's' : '') : '',
                ]));
                $level = effk_unit_level_badge((string) ($u['visibility'] ?? ''));
                ?>
                <section class="effk-col<?= $uid === $initialUnit ? ' is-target' : '' ?>" id="unite-<?= $uid ?>" style="--c:<?= effk_unit_color($uid) ?>"
                    data-eff-aff-col data-unit="<?= $uid ?>" data-code="<?= $h((string) $u['code']) ?>" data-search="<?= $h($unitHay) ?>"<?= $authorized > 0 && $count >= $authorized ? ' data-full' : '' ?>
                    aria-labelledby="eff-aff-col-<?= $uid ?>">
                    <header class="effk-col__head">
                        <div class="effk-col__t">
                            <h3 id="eff-aff-col-<?= $uid ?>"><?= $h((string) $u['name']) ?></h3>
                            <span class="effk-col__cnt" title="<?= $authorized > 0 ? $h($count . ' titulaire' . ($count > 1 ? 's' : '') . ' pour ' . $authorized . ' postes prévus à l’ORBAT') : $h($count . ' titulaire' . ($count > 1 ? 's' : '')) ?>"><?= $count ?><?php if ($authorized > 0): ?><small> / <?= $authorized ?></small><?php endif; ?></span>
                        </div>
                        <?php if ($subLine !== ''): ?><p class="effk-col__sub"><?= $h($subLine) ?></p><?php endif; ?>
                        <div class="effk-col__code">
                            <?php if ((string) $u['code'] !== ''): ?>
                                <?= eff_unit_code_html((string) $u['code'], '', 'sm') ?>
                                <span class="effk-col__id" title="Identifiant administratif : ne change jamais"><span>ID</span><?= $h((string) $u['admin_id']) ?></span>
                            <?php else: ?>
                                <span class="effk-col__id">Code en cours d’attribution</span>
                            <?php endif; ?>
                            <?= $level ?>
                        </div>
                        <?php if ($authorized > 0): ?>
                            <?php if (max($authorized, $count) <= 40): ?>
                            <div class="effk-cap" aria-hidden="true"><?php for ($i = 0; $i < max($authorized, $count); $i++): ?><i class="<?= $i < $count ? ($i >= $authorized ? 'is-x' : 'is-f') : '' ?>"></i><?php endfor; ?></div>
                            <?php else: ?>
                            <div class="effk-cap is-bar" aria-hidden="true"><i style="width:<?= min(100, (int) round($count * 100 / $authorized)) ?>%"></i></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <p class="effk-col__lead<?= (string) $u['commander'] === '' ? ' is-vac' : '' ?>"><?= effk_icon('user') ?>Chef : <b><?= $h((string) $u['commander'] !== '' ? (string) $u['commander'] : 'à désigner') ?></b></p>
                        <div class="effk-col__acts">
                            <a href="<?= $h(effectifs_workspace_url() . '?unite=' . $uid) ?>">Voir au tableur</a>
                            <?php if ((string) $u['commander'] === '' && $canManageAssignments): ?>
                                <a href="<?= $h(effectifs_workspace_url('chaine')) ?>">Désigner le chef</a>
                            <?php endif; ?>
                            <?php if ($canManageAssignments): ?>
                            <details class="eff-aff-seg">
                                <summary>Modifier le code</summary>
                                <form method="post" action="<?= $h(effectifs_workspace_url('codes/unites/' . $uid)) ?>" class="eff-aff-seg__panel">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                    <label for="eff-aff-seg-<?= $uid ?>">Repère de l’unité dans le code</label>
                                    <input id="eff-aff-seg-<?= $uid ?>" name="segment" value="<?= $h((string) $u['own_code']) ?>" maxlength="8" pattern="[A-Za-z0-9]{0,8}" placeholder="<?= $h((string) $u['segment']) ?>" autocomplete="off" spellcheck="false">
                                    <span>Lettres ou chiffres, 8 au plus. Laissez vide pour le calculer depuis le nom<?= (string) $u['segment'] !== '' ? ' (actuellement « ' . $h((string) $u['segment']) . ' »)' : '' ?>.</span>
                                    <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary">Enregistrer</button>
                                </form>
                            </details>
                            <?php endif; ?>
                        </div>
                    </header>
                    <div class="effk-cards" data-eff-aff-drop>
                        <?php foreach ($holders as $x): ?><?= $card($x) ?><?php endforeach; ?>
                        <?php foreach ($seats['vacant'] ?? [] as $vacant): ?>
                            <div class="effk-free is-vac">Poste vacant : <?= $h((string) $vacant) ?></div>
                        <?php endforeach; ?>
                        <?php if ($holders === [] && ($seats['vacant'] ?? []) === []): ?>
                            <div class="effk-free">Aucun titulaire</div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <?php if ($unitsList !== []): ?>
                <section class="effk-col" id="unite-0" style="--c:<?= $noUnitColor ?>" data-eff-aff-col data-unit="0" data-code="" data-search="<?= $h($norm('sans unité à rattacher')) ?>" aria-labelledby="eff-aff-col-0">
                    <header class="effk-col__head">
                        <div class="effk-col__t">
                            <h3 id="eff-aff-col-0">Sans unité</h3>
                            <span class="effk-col__cnt"><?= count($withoutUnitMembers) ?></span>
                        </div>
                        <p class="effk-col__sub">Membres actifs à rattacher : déposez une carte ici pour retirer l’affectation.</p>
                        <div class="effk-col__acts"><a href="<?= $h(effectifs_workspace_url() . '?sans_affectation=1') ?>">Voir au tableur</a></div>
                    </header>
                    <div class="effk-cards" data-eff-aff-drop>
                        <?php foreach ($withoutUnitMembers as $x): ?><?= $card($x) ?><?php endforeach; ?>
                        <?php if ($withoutUnitMembers === []): ?><div class="effk-free">Tout le monde a une unité</div><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
            </div>

            <aside class="effk-panel effk-hist" aria-labelledby="eff-aff-hist-title">
                <div class="effk-hist__h"><h3 id="eff-aff-hist-title">Mouvements récents</h3><span class="effk-k"><?= count($movements) ?></span></div>
                <ul class="effk-mvl">
                    <?php if ($movements === []): ?>
                        <li><span class="effk-mvl__empty">Aucun mouvement enregistré pour l’instant.</span></li>
                    <?php endif; ?>
                    <?php foreach ($movements as $mv): ?>
                        <?php
                        $to = $unitById[(int) $mv['unit_id']] ?? null;
                        $from = (int) $mv['from_unit_id'] > 0 ? ($unitById[(int) $mv['from_unit_id']] ?? null) : null;
                        $who = (string) $mv['display_name'] !== '' ? (string) $mv['display_name'] : ((string) $mv['callsign'] !== '' ? (string) $mv['callsign'] : 'Membre');
                        ?>
                        <li>
                            <div class="effk-mvl__r">
                                <?= effk_avatar((int) $mv['user_id'], $who, 'sm') ?>
                                <a href="<?= $h(effectifs_workspace_url('membres/' . (int) $mv['user_id'])) ?>"><?= $h($who) ?></a>
                                <?php if ((string) $mv['date'] !== ''): ?><time datetime="<?= $h((string) $mv['date']) ?>"><?= $h(effk_short_date((string) $mv['date'])) ?></time><?php endif; ?>
                            </div>
                            <div class="effk-mvl__path">
                                <?php if ((int) $mv['from_unit_id'] > 0): ?>
                                    <span class="effk-up" style="--c:<?= effk_unit_color((int) $mv['from_unit_id']) ?>"><i aria-hidden="true"></i><?= $h($from !== null ? (string) $from['name'] : 'Unité supprimée') ?></span>
                                <?php else: ?>
                                    <span class="effk-up">Première affectation</span>
                                <?php endif; ?>
                                <?= effk_icon('arrow') ?><span class="sr-only">vers</span>
                                <span class="effk-up" style="--c:<?= effk_unit_color((int) $mv['unit_id']) ?>"><i aria-hidden="true"></i><?= $h($to !== null ? (string) $to['name'] : 'Unité') ?></span>
                            </div>
                            <?php if ((string) $mv['reason'] !== ''): ?><small><?= $h((string) $mv['reason']) ?></small><?php endif; ?>
                            <?php if ((string) $mv['user_status'] !== '' && (string) $mv['user_status'] !== 'active'): ?><small>Compte inactif</small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </div>
        <div class="effk-empty" data-eff-aff-none-units hidden><b>Aucune unité ne correspond</b>Retirez un filtre ou cherchez un autre code.</div>
    </div>

    <div data-eff-aff-pane="members"<?= $view !== 'members' ? ' hidden' : '' ?>>
        <?php if ($members === []): ?>
            <?= effk_empty('Aucun membre actif', 'Ajoutez un membre ou réactivez un compte depuis le tableur.') ?>
        <?php else: ?>
        <div class="effk-panel eff-aff-scroll">
        <table class="eff-aff-table">
            <thead>
                <tr>
                    <th scope="col">Membre</th>
                    <th scope="col">Unité</th>
                    <th scope="col">Rôle dans l’unité</th>
                    <th scope="col">Emplois</th>
                    <th scope="col">Accès</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr data-eff-aff-row<?= $filterAttrs($m) ?>>
                    <td>
                        <span class="eff-aff-who">
                            <a href="<?= $h(effectifs_workspace_url('membres/' . (int) $m['id'])) ?>"><?= $h((string) $m['name']) ?></a>
                            <small><?= $h(implode(' · ', array_filter([(string) $m['grade'], $m['callsign'] !== '' ? '« ' . $m['callsign'] . ' »' : '']))) ?></small>
                        </span>
                    </td>
                    <td>
                        <?php if ($m['unit_id'] !== null): ?>
                        <span class="eff-aff-where">
                            <?= eff_unit_code_html((string) $m['unit_code'], (string) $m['unit_admin_id'], 'sm') ?>
                            <small title="<?= $h((string) $m['unit_path']) ?>"><?= $h((string) ($m['unit_name'] !== '' ? $m['unit_name'] : $m['unit_path'])) ?></small>
                            <?= effk_unit_level_badge((string) ($unitById[(int) $m['unit_id']]['visibility'] ?? '')) ?>
                        </span>
                        <?php else: ?>
                            <span class="eff-aff-none is-warn">Sans unité</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($m['role'] !== ''): ?><span class="eff-aff-chip is-role"><?= $h((string) $m['role']) ?></span><?php else: ?><span class="eff-aff-none">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($m['jobs'] === []): ?>
                            <span class="eff-aff-none is-warn">Sans emploi</span>
                        <?php else: ?>
                            <span class="eff-aff-chips">
                                <?php foreach ($m['jobs'] as $j): ?>
                                    <span class="eff-aff-chip<?= $j['primary'] ? ' is-main' : '' ?>"<?= $j['primary'] ? ' title="Emploi principal"' : '' ?>><?= $h((string) $j['name'] . ($j['detail'] !== '' ? ' — ' . $j['detail'] : '')) ?></span>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($m['access'] === []): ?><span class="eff-aff-none">—</span><?php else: ?><span class="eff-aff-chips"><?php foreach ($m['access'] as $acc): ?><span class="eff-aff-chip"><?= $h((string) $acc) ?></span><?php endforeach; ?></span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="effk-empty" data-eff-aff-none hidden><b>Aucun membre ne correspond</b>Retirez un filtre ou cherchez autrement (indicatif, code unité, emploi).</div>
        <?php endif; ?>
    </div>

    <?php if ($canManageAssignments): ?>
    <div class="effk-pop" data-eff-aff-menu role="dialog" aria-labelledby="eff-aff-menu-title" hidden>
        <p class="effk-pop__h" id="eff-aff-menu-title" data-eff-aff-menu-title></p>
        <div class="effk-pop__list" data-eff-aff-menu-list></div>
    </div>
    <dialog class="effk-md" data-eff-aff-dialog aria-labelledby="eff-aff-move-title">
        <form method="post" action="" data-eff-aff-move-form data-action="<?= $h(effectifs_workspace_url('membres/__ID__/affectation')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="unit_id" value="0" data-eff-aff-move-unit>
            <input type="hidden" name="return_url" value="<?= $h(effectifs_workspace_url('affectations')) ?>" data-eff-aff-move-return>
            <div class="effk-md__h">
                <div><span class="effk-k">Mutation</span><h2 id="eff-aff-move-title" data-eff-aff-move-title>Muter</h2></div>
                <button type="button" class="effk-md__x" data-eff-aff-close aria-label="Fermer"><?= effk_icon('x') ?></button>
            </div>
            <div class="effk-md__b">
                <div class="effk-md__who" data-eff-aff-move-who></div>
                <div class="effk-flow">
                    <div class="effk-flow__u" data-eff-aff-move-from><small>Depuis</small><b></b></div>
                    <?= effk_icon('arrow') ?>
                    <div class="effk-flow__u" data-eff-aff-move-to><small>Vers</small><b></b></div>
                </div>
                <div data-eff-aff-move-notes></div>
                <div class="effk-md__fl">
                    <label for="eff-aff-move-reason">Motif</label>
                    <textarea id="eff-aff-move-reason" name="reason" maxlength="255" placeholder="Renfort, fin de probation, demande du membre…"></textarea>
                    <small>Facultatif. La mutation prend effet aujourd’hui et rejoint l’historique d’affectation du dossier.</small>
                </div>
            </div>
            <div class="effk-md__f">
                <button type="button" class="effk-md__btn" data-eff-aff-close>Annuler</button>
                <button type="submit" class="effk-md__btn is-dark" data-eff-aff-move-submit>Confirmer la mutation</button>
            </div>
        </form>
    </dialog>
    <script type="application/json" id="eff-aff-data"><?= json_encode(['units' => (object) $jsUnits, 'members' => (object) $jsMembers, 'noUnitColor' => $noUnitColor], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/effectifs-kit.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/eff-affectations.js')) ?>" defer></script>
