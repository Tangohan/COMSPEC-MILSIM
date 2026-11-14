<?php
declare(strict_types=1);

/**
 * Effectifs › Registre des droits (EffectifsWorkspaceController::registreDroits, données AccessRegisterController::pageData).
 * Droits en vigueur : une ligne par membre (défaut, ses droits en pastilles) ou une ligne par droit (revue, retrait,
 * modification). La position administrative est une colonne, pas une ligne de droit. Historique : une ligne par mouvement.
 * Fonctionne sans JavaScript (formulaires GET et POST) ; access-register.js ajoute le choix des colonnes, le tri,
 * la recherche instantanée, la sélection et le plein écran.
 *
 * @var array{view: string, q: string, kind: string, state: string, year: string, display: string} $regFilters
 * @var list<array<string, mixed>> $regRows
 * @var array<string, array{0: string, 1: bool}> $regColumns
 */
use App\Controllers\Admin\AccessRegisterController as C;
use App\Services\Rbac\AccessRegisterService as R;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$f = $regFilters;
$isHistory = $f['view'] === 'historique';
$byMember = !$isHistory && ($f['display'] ?? C::DISPLAY_MEMBERS) === C::DISPLAY_MEMBERS;
$rows = $regRows;
$cols = $regColumns;
$counts = $regCounts;
$canManage = (bool) $regCanManage && !$isHistory;
$base = effectifs_workspace_url('registre-droits');
$query = array_filter([
    'vue' => $isHistory ? 'historique' : '',
    'affichage' => !$isHistory && !$byMember ? C::DISPLAY_RIGHTS : '',
    'q' => $f['q'], 'nature' => $f['kind'], 'etat' => $f['state'], 'annee' => $f['year'],
], static fn ($v): bool => $v !== '');
$returnQuery = http_build_query($query);
$csvUrl = effectifs_workspace_url('registre-droits/export.csv') . ($query !== [] ? '?' . $returnQuery : '');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$flashWarn = \App\Core\Session::getFlash('warning');
$stateClass = [
    R::STATE_ACTIVE => 'is-ok',
    R::STATE_EXPIRING => 'is-warn',
    R::STATE_UPCOMING => 'is-info',
    R::STATE_EXPIRED => 'is-bad',
];
$today = date('Y-m-d');
$filterLink = static function (array $over) use ($base, $query): string {
    $q = array_filter(array_merge($query, $over), static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($q !== [] ? '?' . http_build_query($q) : '');
};

// Vue par membre : les lignes du même membre réunies, ses droits en pastilles.
$members = [];
if ($byMember) {
    foreach ($rows as $r) {
        $uid = (int) ($r['user_id'] ?? 0);
        $members[$uid] ??= [
            'user_id' => $uid, 'member' => (string) $r['member'], 'callsign' => (string) $r['callsign'],
            'position' => (string) $r['position'], 'account' => (string) ($r['account'] ?? ''),
            'levels' => [], 'grants' => [], 'next' => '', 'unreviewed' => 0, 'search' => '',
        ];
        $m = &$members[$uid];
        $isIndividual = in_array($r['kind'], [R::KIND_GRANT, R::KIND_DENY], true);
        $m[$isIndividual ? 'grants' : 'levels'][] = $r;
        if ((string) $r['until'] !== '' && ($m['next'] === '' || $r['until'] < $m['next'])) {
            $m['next'] = (string) $r['until'];
        }
        $m['unreviewed'] += !empty($r['can_review']) && (string) $r['reviewed'] === '' ? 1 : 0;
        $m['search'] .= ' ' . $r['right'] . ' ' . $r['scope'] . ' ' . $r['reason'];
        unset($m);
    }
}
$chip = static function (array $r) use ($h, $stateClass): string {
    $scope = (string) $r['scope'];
    $label = (string) $r['right'] . ($scope !== '' && $scope !== 'Toute la communauté' ? ' · ' . $scope : '');
    $title = $r['kind_label'] . ' · depuis ' . $r['since'] . ((string) $r['until'] !== '' ? ' · jusqu’au ' . $r['until'] : '')
        . ' · accordé par ' . $r['granted_by'] . ((string) $r['reason'] !== '' ? ' · ' . $r['reason'] : '');
    $deny = $r['kind'] === R::KIND_DENY;

    return '<span class="areg-chip ' . $h($stateClass[$r['state']] ?? '') . ($deny ? ' is-deny' : '') . '" title="' . $h($title) . '">'
        . ($deny ? '<s>' . $h($label) . '</s>' : $h($label))
        . ((string) $r['until'] !== '' ? ' <small>→ ' . $h($r['until']) . '</small>' : '') . '</span>';
};
?>
<section class="areg" data-areg data-areg-view="<?= $h($f['view']) ?>" aria-labelledby="areg-title">
    <header class="areg-head">
        <div class="areg-head__text">
            <h2 id="areg-title">Registre des droits</h2>
            <p class="areg-lead"><?= $isHistory
                ? 'Chaque attribution et chaque retrait, avec la personne qui l’a fait et son motif.'
                : 'Qui détient quel droit, sur quel périmètre, depuis quand et jusqu’à quand.' ?></p>
        </div>
        <div class="areg-head__actions">
            <a class="areg-btn areg-btn--ghost" href="<?= $h($csvUrl) ?>" data-areg-csv>Exporter en CSV</a>
            <button type="button" class="areg-btn areg-btn--ghost" data-areg-fullscreen hidden>Plein écran</button>
            <?php if ($canManage): ?>
                <a class="areg-btn areg-btn--solid" href="#areg-grant" data-areg-grant-open>Attribuer un droit</a>
            <?php endif; ?>
        </div>
    </header>

    <nav class="areg-tabs" aria-label="Registre">
        <a class="areg-tab<?= !$isHistory ? ' is-on' : '' ?>" href="<?= $h($base) ?>"<?= !$isHistory ? ' aria-current="page"' : '' ?>>Droits en vigueur</a>
        <a class="areg-tab<?= $isHistory ? ' is-on' : '' ?>" href="<?= $h($base . '?vue=historique') ?>"<?= $isHistory ? ' aria-current="page"' : '' ?>>Historique des mouvements</a>
    </nav>

    <?php if ($flashErr): ?><p class="areg-flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
    <?php if ($flashWarn): ?><p class="areg-flash is-warn" role="status"><?= $h($flashWarn) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="areg-flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if ($regError !== ''): ?><p class="areg-flash is-bad" role="alert"><?= $h($regError) ?></p><?php endif; ?>
    <?php if ((int) $regSwept > 0): ?><p class="areg-flash" role="status">Droits arrivés à échéance et retirés : <?= (int) $regSwept ?>. Voir l’historique.</p><?php endif; ?>

    <?php if (!$isHistory): ?>
    <nav class="areg-kpis" aria-label="Résumé du registre">
        <span class="areg-kpi"><b><?= (int) ($counts['members'] ?? 0) ?></b><span>membres</span></span>
        <a class="areg-kpi<?= $f['state'] === '' && $f['kind'] === '' ? ' is-on' : '' ?>" href="<?= $h($filterLink(['etat' => '', 'nature' => ''])) ?>"><b><?= (int) $counts['total'] ?></b><span>droits</span></a>
        <a class="areg-kpi is-warn<?= $f['state'] === R::STATE_EXPIRING ? ' is-on' : '' ?>" href="<?= $h($filterLink(['etat' => R::STATE_EXPIRING, 'nature' => ''])) ?>"><b><?= (int) $counts['expiring'] ?></b><span>échéances sous <?= R::EXPIRING_DAYS ?> jours</span></a>
        <a class="areg-kpi is-info<?= $f['state'] === R::STATE_UPCOMING ? ' is-on' : '' ?>" href="<?= $h($filterLink(['etat' => R::STATE_UPCOMING, 'nature' => ''])) ?>"><b><?= (int) $counts['upcoming'] ?></b><span>à venir</span></a>
        <a class="areg-kpi<?= $f['kind'] === R::KIND_GRANT ? ' is-on' : '' ?>" href="<?= $h($filterLink(['nature' => R::KIND_GRANT, 'etat' => ''])) ?>"><b><?= (int) $counts['individual'] ?></b><span>droits individuels</span></a>
        <span class="areg-kpi is-muted"><b><?= (int) $counts['unreviewed'] ?></b><span>jamais revus</span></span>
    </nav>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <div class="areg-grant<?= $rows === [] && $f['q'] === '' && $f['kind'] === '' && $f['state'] === '' ? ' is-open' : '' ?>" id="areg-grant" data-areg-grant>
        <form method="post" action="<?= $h(effectifs_workspace_url('registre-droits/attribuer')) ?>" class="areg-grant__form">
            <h3>Attribuer un droit</h3>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
            <label class="areg-field"><span>Membre</span>
                <select name="user_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($regMembers as $mid => $mlabel): ?>
                        <option value="<?= (int) $mid ?>"><?= $h($mlabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="areg-field"><span>Droit</span>
                <select name="target" required>
                    <option value="">Choisir…</option>
                    <?php $grp = null; foreach ($regTargets as $t): ?>
                        <?php if ($t['group'] !== $grp): ?><?= $grp !== null ? '</optgroup>' : '' ?><optgroup label="<?= $h($t['group']) ?>"><?php $grp = $t['group']; endif; ?>
                        <option value="<?= $h($t['value']) ?>"><?= $h($t['label']) ?></option>
                    <?php endforeach; ?>
                    <?= $grp !== null ? '</optgroup>' : '' ?>
                </select>
            </label>
            <label class="areg-field"><span>Périmètre</span>
                <select name="unit_id">
                    <option value="0">Toute la communauté</option>
                    <?php foreach ($regUnits as $uid => $uname): ?>
                        <option value="<?= (int) $uid ?>">Unité : <?= $h($uname) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="areg-field"><span>Depuis (facultatif)</span><input type="date" name="valid_from"></label>
            <label class="areg-field"><span>Jusqu’au (facultatif)</span><input type="date" name="valid_until" min="<?= $h($today) ?>"></label>
            <label class="areg-field areg-field--grow"><span>Motif</span><input type="text" name="reason" maxlength="<?= R::REASON_MAX ?>" placeholder="Ex. : chef de groupe pendant l’opération Tempête"></label>
            <div class="areg-grant__actions">
                <button type="submit" class="areg-btn areg-btn--solid">Attribuer</button>
                <p class="areg-hint">Les positions s’appliquent sans dates. Un niveau déjà détenu sur le même périmètre est renouvelé avec les nouvelles dates.</p>
            </div>
            <a class="areg-grant__close" href="#areg-title" data-areg-grant-close>Fermer</a>
        </form>
    </div>
    <?php endif; ?>

    <form class="areg-bar" method="get" action="<?= $h($base) ?>" data-areg-filters>
        <?php if ($isHistory): ?><input type="hidden" name="vue" value="historique"><?php endif; ?>
        <label class="areg-search">
            <span class="areg-sr">Rechercher</span>
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Nom, indicatif, droit, unité, motif…" data-areg-search autocomplete="off">
        </label>
        <label class="areg-field">
            <span>Nature</span>
            <select name="nature" data-areg-autosubmit>
                <option value="">Toutes</option>
                <?php foreach (R::kindLabels() as $k => $label): ?>
                    <option value="<?= $h($k) ?>" <?= $f['kind'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!$isHistory): ?>
        <label class="areg-field">
            <span>État</span>
            <select name="etat" data-areg-autosubmit>
                <option value="">Tous</option>
                <?php foreach (R::stateLabels() as $k => $label): ?>
                    <option value="<?= $h($k) ?>" <?= $f['state'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label class="areg-field">
            <span>Année</span>
            <select name="annee" data-areg-autosubmit>
                <option value="">Toutes</option>
                <?php foreach ($regYears as $y): ?>
                    <option value="<?= $h($y) ?>" <?= $f['year'] === $y ? 'selected' : '' ?>><?= $h($y) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!$isHistory): ?>
            <div class="areg-seg" role="group" aria-label="Affichage">
                <a class="areg-seg__item<?= $byMember ? ' is-on' : '' ?>" href="<?= $h($filterLink(['affichage' => ''])) ?>"<?= $byMember ? ' aria-current="true"' : '' ?>>Par membre</a>
                <a class="areg-seg__item<?= !$byMember ? ' is-on' : '' ?>" href="<?= $h($filterLink(['affichage' => C::DISPLAY_RIGHTS])) ?>"<?= !$byMember ? ' aria-current="true"' : '' ?>>Par droit</a>
            </div>
            <?php if (!$byMember): ?><input type="hidden" name="affichage" value="<?= $h(C::DISPLAY_RIGHTS) ?>"><?php endif; ?>
        <?php endif; ?>
        <?php if (!$byMember): ?>
        <details class="areg-cols" data-areg-cols>
            <summary class="areg-btn areg-btn--ghost">Colonnes</summary>
            <div class="areg-pop">
                <?php foreach ($cols as $key => [$label, $visible]): ?>
                    <label><input type="checkbox" name="cols[]" value="<?= $h($key) ?>" data-areg-col-toggle="<?= $h($key) ?>" <?= $visible ? 'checked' : '' ?>> <?= $h($label) ?></label>
                <?php endforeach; ?>
                <p class="areg-hint">Votre choix est gardé sur cet appareil et repris dans l’export CSV.</p>
            </div>
        </details>
        <?php endif; ?>
        <details class="areg-help">
            <summary class="areg-btn areg-btn--ghost">Aide</summary>
            <div class="areg-pop areg-pop--wide">
                <h3>Lire le registre</h3>
                <dl class="eff-duty-help__list">
                    <dt>Niveau d’accès</dt><dd>Membre, Ressources humaines, Gestionnaire ou un niveau créé par la communauté. Il ouvre les droits cochés dans « Accès ».</dd>
                    <dt>Accès limité à une unité</dt><dd>Le niveau ne vaut que pour une unité : seuls ses droits de portée « unité » s’appliquent (fiches, affectations de cette unité).</dd>
                    <dt>Droit individuel</dt><dd>Un droit précis donné à une personne, en général après une demande d’élévation. Un retrait individuel (barré) enlève un droit que son niveau donnerait.</dd>
                    <dt>Position administrative</dt><dd>En formation, en service actif… Elle s’affiche dans sa colonne. Pour la voir comme un droit à part, choisissez la nature « Position administrative ».</dd>
                    <dt>Depuis, jusqu’au</dt><dd>Un droit borné ne donne rien avant sa date de début. À son échéance, il cesse aussitôt et sort du registre, avec une ligne dans l’historique.</dd>
                    <dt>Échéance proche</dt><dd>Le droit s’arrête dans moins de <?= R::EXPIRING_DAYS ?> jours : renouvelez-le en l’attribuant de nouveau, ou laissez-le finir.</dd>
                    <dt>Revue</dt><dd>Affichage « Par droit » : cochez des lignes puis « Marquer comme revus ». La date et votre nom sont notés.</dd>
                </dl>
                <h3>Positions administratives</h3>
                <?php $apgCurrent = null; $apgShowLink = true; require dirname(__DIR__, 2) . '/partials/administrative_positions_guide.php'; ?>
            </div>
        </details>
        <noscript><button type="submit" class="areg-btn">Filtrer</button></noscript>
    </form>

    <?php if ($canManage && !$byMember): ?>
    <form id="areg-bulk" method="post" action="<?= $h(effectifs_workspace_url('registre-droits/revue')) ?>" class="areg-bulk" data-areg-bulk>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
        <span class="areg-bulk__count">Lignes cochées : <b data-areg-selcount>0</b></span>
        <button type="submit" class="areg-btn areg-btn--ghost">Marquer comme revus</button>
        <input type="text" name="reason" maxlength="<?= R::REASON_MAX ?>" placeholder="Motif du retrait" aria-label="Motif du retrait">
        <button type="submit" class="areg-btn areg-btn--danger" formaction="<?= $h(effectifs_workspace_url('registre-droits/retirer')) ?>" data-areg-confirm="Retirer les droits cochés maintenant ?">Retirer la sélection</button>
    </form>
    <?php endif; ?>

    <p class="areg-count">
        <?php if ($byMember): ?>
            <b data-areg-shown><?= count($members) ?></b> membre<?= count($members) > 1 ? 's' : '' ?> · <?= count($rows) ?> droit<?= count($rows) > 1 ? 's' : '' ?>
        <?php else: ?>
            Lignes affichées : <b data-areg-shown><?= count($rows) ?></b> / <b><?= (int) $regMatched ?></b><?php if ($regMatched > count($rows)): ?>. Affinez la recherche pour voir les autres.<?php endif; ?>
        <?php endif; ?>
    </p>

    <div class="areg-scroll" data-areg-scroll>
        <?php if ($byMember): ?>
        <table class="areg-table areg-table--members" data-areg-table>
            <thead>
                <tr>
                    <th scope="col">Membre</th>
                    <th scope="col">Position administrative</th>
                    <th scope="col">Niveaux d’accès</th>
                    <th scope="col">Droits individuels</th>
                    <th scope="col">Prochaine échéance</th>
                    <th scope="col">Revue</th>
                    <th data-nosort><span class="areg-sr">Détail</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr data-search="<?= $h($m['member'] . ' ' . $m['callsign'] . ' ' . $m['position'] . $m['search']) ?>">
                    <td class="areg-who">
                        <?php if ($m['user_id'] > 0): ?><a href="<?= $h(effectifs_workspace_url('membres/' . $m['user_id'])) ?>"><?= $h($m['member']) ?></a><?php else: ?><?= $h($m['member']) ?><?php endif; ?>
                        <?php if ($m['callsign'] !== ''): ?><span class="areg-callsign" translate="no"><?= $h($m['callsign']) ?></span><?php endif; ?>
                    </td>
                    <td><?= $m['position'] !== '' ? '<span class="areg-position">' . $h($m['position']) . '</span>' : '<span class="areg-empty">—</span>' ?></td>
                    <td><div class="areg-chips"><?= $m['levels'] !== [] ? implode('', array_map($chip, $m['levels'])) : '<span class="areg-empty">—</span>' ?></div></td>
                    <td><div class="areg-chips"><?= $m['grants'] !== [] ? implode('', array_map($chip, $m['grants'])) : '<span class="areg-empty">—</span>' ?></div></td>
                    <td><?= $m['next'] !== '' ? $h($m['next']) : '<span class="areg-empty">Sans échéance</span>' ?></td>
                    <td><?= $m['unreviewed'] > 0 ? '<span class="areg-pill is-muted">' . (int) $m['unreviewed'] . ' à revoir</span>' : '<span class="areg-pill is-ok">Revu</span>' ?></td>
                    <td class="areg-row-actions"><a class="areg-btn areg-btn--sm areg-btn--ghost" href="<?= $h($filterLink(['affichage' => C::DISPLAY_RIGHTS, 'q' => $m['callsign'] !== '' ? $m['callsign'] : $m['member']])) ?>">Détail</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <table class="areg-table" data-areg-table>
            <thead>
                <tr>
                    <?php if ($canManage): ?><th class="areg-check" data-nosort><input type="checkbox" data-areg-checkall aria-label="Tout cocher"></th><?php endif; ?>
                    <?php foreach ($cols as $key => [$label, $visible]): ?>
                        <th scope="col" data-col="<?= $h($key) ?>"<?= $visible ? '' : ' hidden' ?>><?= $h($label) ?><span class="areg-arrow" aria-hidden="true"></span></th>
                    <?php endforeach; ?>
                    <?php if ($canManage): ?><th data-nosort><span class="areg-sr">Actions</span></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <?php if ($canManage): ?>
                        <td class="areg-check"><input type="checkbox" form="areg-bulk" name="refs[]" value="<?= $h($r['ref']) ?>" aria-label="Choisir <?= $h($r['right'] . ' · ' . $r['member']) ?>" data-areg-row<?= !$r['can_revoke'] && !$r['can_review'] ? ' disabled' : '' ?>></td>
                    <?php endif; ?>
                    <?php foreach ($cols as $key => [$label, $visible]):
                        $val = (string) ($r[$key] ?? '');
                        ?>
                        <td data-col="<?= $h($key) ?>"<?= $visible ? '' : ' hidden' ?>>
                            <?php if ($key === 'state_label' && $val !== ''): ?>
                                <span class="areg-pill <?= $h($stateClass[$r['state']] ?? '') ?>"><?= $h($val) ?></span>
                            <?php elseif ($key === 'action_label'): ?>
                                <span class="areg-pill <?= ($r['action'] ?? '') === 'revoke' ? 'is-bad' : 'is-ok' ?>"><?= $h($val) ?></span>
                            <?php elseif ($key === 'member' && (int) ($r['user_id'] ?? 0) > 0): ?>
                                <a href="<?= $h(effectifs_workspace_url('membres/' . (int) $r['user_id'])) ?>"><?= $h($val) ?></a>
                            <?php elseif ($key === 'position' && $val !== ''): ?>
                                <span class="areg-position"><?= $h($val) ?></span>
                            <?php else: ?>
                                <?= $val !== '' ? $h($val) : '<span class="areg-empty">—</span>' ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <?php if ($canManage): ?>
                    <td class="areg-row-actions">
                        <?php if ($r['can_edit'] || $r['can_revoke']): ?>
                        <details>
                            <summary class="areg-btn areg-btn--sm areg-btn--ghost">Gérer</summary>
                            <div class="areg-pop areg-pop--right">
                                <?php if ($r['can_edit']): ?>
                                <form method="post" action="<?= $h(effectifs_workspace_url('registre-droits/modifier')) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
                                    <input type="hidden" name="ref" value="<?= $h($r['ref']) ?>">
                                    <label class="areg-field"><span>Depuis</span><input type="date" name="valid_from" value="<?= $h($r['from_input'] ?? '') ?>"></label>
                                    <label class="areg-field"><span>Jusqu’au</span><input type="date" name="valid_until" value="<?= $h($r['until']) ?>" min="<?= $h($today) ?>"></label>
                                    <label class="areg-field"><span>Motif</span><input type="text" name="reason" value="<?= $h($r['reason']) ?>" maxlength="<?= R::REASON_MAX ?>"></label>
                                    <button type="submit" class="areg-btn areg-btn--sm">Enregistrer</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['can_revoke']): ?>
                                <form method="post" action="<?= $h(effectifs_workspace_url('registre-droits/retirer')) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
                                    <input type="hidden" name="ref" value="<?= $h($r['ref']) ?>">
                                    <label class="areg-field"><span>Motif du retrait</span><input type="text" name="reason" maxlength="<?= R::REASON_MAX ?>"></label>
                                    <button type="submit" class="areg-btn areg-btn--sm areg-btn--danger" data-areg-confirm="Retirer « <?= $h($r['right']) ?> » à <?= $h($r['member']) ?> maintenant ?">Retirer maintenant</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </details>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p class="areg-nodata" data-areg-nodata<?= $rows === [] ? '' : ' hidden' ?>>Aucun droit ne correspond à ces filtres.</p>
    </div>
</section>
<script defer src="<?= $h(asset_url('assets/js/access-register.js?r=202611142000')) ?>"></script>
