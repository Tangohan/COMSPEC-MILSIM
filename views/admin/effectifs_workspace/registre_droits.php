<?php
declare(strict_types=1);

/**
 * Effectifs › Registre des droits (EffectifsWorkspaceController::registreDroits, données AccessRegisterController::pageData).
 * Fonctionne sans JavaScript (formulaires GET et POST) ; access-register.js ajoute le choix des colonnes, le tri,
 * la recherche instantanée, la sélection et le plein écran.
 *
 * @var array{view: string, q: string, kind: string, state: string, year: string} $regFilters
 * @var list<array<string, mixed>> $regRows
 * @var array<string, array{0: string, 1: bool}> $regColumns
 */
use App\Services\Rbac\AccessRegisterService;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$f = $regFilters;
$isHistory = $f['view'] === 'historique';
$rows = $regRows;
$cols = $regColumns;
$counts = $regCounts;
$canManage = (bool) $regCanManage && !$isHistory;
$base = effectifs_workspace_url('registre-droits');
$query = array_filter(['vue' => $isHistory ? 'historique' : '', 'q' => $f['q'], 'nature' => $f['kind'], 'etat' => $f['state'], 'annee' => $f['year']], static fn ($v): bool => $v !== '');
$returnQuery = http_build_query($query);
$csvUrl = effectifs_workspace_url('registre-droits/export.csv') . ($query !== [] ? '?' . $returnQuery : '');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$flashWarn = \App\Core\Session::getFlash('warning');
$stateClass = [
    AccessRegisterService::STATE_ACTIVE => 'is-ok',
    AccessRegisterService::STATE_EXPIRING => 'is-warn',
    AccessRegisterService::STATE_UPCOMING => 'is-info',
    AccessRegisterService::STATE_EXPIRED => 'is-bad',
];
$today = date('Y-m-d');
$filterLink = static function (array $over) use ($base, $query): string {
    $q = array_filter(array_merge($query, $over), static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($q !== [] ? '?' . http_build_query($q) : '');
};
?>
<section class="areg" data-areg data-areg-view="<?= $h($f['view']) ?>" aria-labelledby="areg-title">
    <header class="areg-head">
        <div>
            <p class="areg-kicker">Habilitations</p>
            <h2 id="areg-title">Registre des droits</h2>
            <p class="areg-lead"><?= $isHistory
                ? 'Chaque attribution et chaque retrait, avec la personne qui l’a fait et son motif.'
                : 'Une ligne par droit détenu : qui, quel droit, sur quel périmètre, accordé par qui, depuis quand et jusqu’à quand.' ?></p>
        </div>
        <a class="areg-refresh" href="<?= $h($filterLink([])) ?>" data-areg-refresh>
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v5h-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Rafraîchir
        </a>
    </header>

    <?php if ($flashErr): ?><p class="areg-flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
    <?php if ($flashWarn): ?><p class="areg-flash is-warn" role="status"><?= $h($flashWarn) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="areg-flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if ($regError !== ''): ?><p class="areg-flash is-bad" role="alert"><?= $h($regError) ?></p><?php endif; ?>
    <?php if ((int) $regSwept > 0): ?><p class="areg-flash" role="status">Droits arrivés à échéance et retirés : <?= (int) $regSwept ?>. Voir l’historique.</p><?php endif; ?>

    <?php if (!$isHistory): ?>
    <nav class="areg-kpis" aria-label="Résumé du registre">
        <a class="areg-kpi<?= $f['state'] === '' && $f['kind'] === '' ? ' is-on' : '' ?>" href="<?= $h($filterLink(['etat' => '', 'nature' => ''])) ?>"><b><?= (int) $counts['total'] ?></b><span>droits au registre</span></a>
        <a class="areg-kpi is-warn<?= $f['state'] === AccessRegisterService::STATE_EXPIRING ? ' is-on' : '' ?>" href="<?= $h($filterLink(['etat' => AccessRegisterService::STATE_EXPIRING, 'nature' => ''])) ?>"><b><?= (int) $counts['expiring'] ?></b><span>échéances sous <?= AccessRegisterService::EXPIRING_DAYS ?> jours</span></a>
        <a class="areg-kpi is-info<?= $f['state'] === AccessRegisterService::STATE_UPCOMING ? ' is-on' : '' ?>" href="<?= $h($filterLink(['etat' => AccessRegisterService::STATE_UPCOMING, 'nature' => ''])) ?>"><b><?= (int) $counts['upcoming'] ?></b><span>à venir</span></a>
        <a class="areg-kpi<?= $f['kind'] === AccessRegisterService::KIND_GRANT ? ' is-on' : '' ?>" href="<?= $h($filterLink(['nature' => AccessRegisterService::KIND_GRANT, 'etat' => ''])) ?>"><b><?= (int) $counts['individual'] ?></b><span>droits individuels</span></a>
        <span class="areg-kpi is-muted"><b><?= (int) $counts['unreviewed'] ?></b><span>jamais revus</span></span>
    </nav>
    <?php endif; ?>

    <form class="areg-bar" method="get" action="<?= $h($base) ?>" data-areg-filters>
        <label class="areg-field">
            <span>Registre</span>
            <select name="vue" data-areg-autosubmit>
                <option value="vigueur" <?= !$isHistory ? 'selected' : '' ?>>Droits en vigueur</option>
                <option value="historique" <?= $isHistory ? 'selected' : '' ?>>Historique des mouvements</option>
            </select>
        </label>
        <label class="areg-field">
            <span>Registre annuel</span>
            <select name="annee" data-areg-autosubmit>
                <option value="">Tous</option>
                <?php foreach ($regYears as $y): ?>
                    <option value="<?= $h($y) ?>" <?= $f['year'] === $y ? 'selected' : '' ?>><?= $h($y) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="areg-field">
            <span>Nature</span>
            <select name="nature" data-areg-autosubmit>
                <option value="">Toutes</option>
                <?php foreach (AccessRegisterService::kindLabels() as $k => $label): ?>
                    <option value="<?= $h($k) ?>" <?= $f['kind'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!$isHistory): ?>
        <label class="areg-field">
            <span>État</span>
            <select name="etat" data-areg-autosubmit>
                <option value="">Tous</option>
                <?php foreach (AccessRegisterService::stateLabels() as $k => $label): ?>
                    <option value="<?= $h($k) ?>" <?= $f['state'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <details class="areg-field areg-cols" data-areg-cols>
            <summary><span>Colonnes activées</span>Colonnes</summary>
            <div class="areg-pop">
                <?php foreach ($cols as $key => [$label, $visible]): ?>
                    <label><input type="checkbox" name="cols[]" value="<?= $h($key) ?>" data-areg-col-toggle="<?= $h($key) ?>" <?= $visible ? 'checked' : '' ?>> <?= $h($label) ?></label>
                <?php endforeach; ?>
                <p class="areg-hint">Votre choix est gardé sur cet appareil et repris dans l’export CSV.</p>
            </div>
        </details>
        <label class="areg-search">
            <span class="areg-sr">Rechercher</span>
            <input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Nom, indicatif, droit, unité, motif…" data-areg-search autocomplete="off">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </label>
        <noscript><button type="submit" class="areg-btn">Filtrer</button></noscript>
        <div class="areg-actions">
            <a class="areg-btn" href="<?= $h($csvUrl) ?>" data-areg-csv>CSV (max. <?= number_format(AccessRegisterService::MAX_ROWS, 0, ',', ' ') ?> lignes)
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 2v8m-3.5-3.5L8 10l3.5-3.5M3 13h10" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            </a>
            <details class="areg-help">
                <summary class="areg-btn">Aide <span aria-hidden="true">▾</span></summary>
                <div class="areg-pop areg-pop--wide">
                    <h3>Lire le registre</h3>
                    <dl class="eff-duty-help__list">
                        <dt>Niveau d’accès</dt><dd>Membre, Ressources humaines, Gestionnaire ou un niveau créé par la communauté. Il ouvre les droits cochés dans « Accès ».</dd>
                        <dt>Accès limité à une unité</dt><dd>Le niveau ne vaut que pour une unité : seuls ses droits de portée « unité » s’appliquent (fiches, affectations de cette unité).</dd>
                        <dt>Droit individuel</dt><dd>Un droit précis donné à une personne, en général après une demande d’élévation. Un retrait individuel enlève un droit que son niveau donnerait.</dd>
                        <dt>Depuis, jusqu’au</dt><dd>Un droit borné ne donne rien avant sa date de début. À son échéance, il cesse aussitôt et sort du registre, avec une ligne dans l’historique.</dd>
                        <dt>Échéance proche</dt><dd>Le droit s’arrête dans moins de <?= AccessRegisterService::EXPIRING_DAYS ?> jours : renouvelez-le en l’attribuant de nouveau, ou laissez-le finir.</dd>
                        <dt>Revue</dt><dd>Cochez des lignes puis « Marquer comme revus » : la date et votre nom sont notés. Une revue régulière montre que chaque droit est encore justifié.</dd>
                    </dl>
                    <h3>Positions administratives</h3>
                    <?php $apgCurrent = null; $apgShowLink = true; require dirname(__DIR__, 2) . '/partials/administrative_positions_guide.php'; ?>
                </div>
            </details>
            <button type="button" class="areg-btn" data-areg-fullscreen hidden>Plein écran</button>
        </div>
    </form>

    <?php if ($canManage): ?>
    <details class="areg-grant" <?= $rows === [] && $f['q'] === '' && $f['kind'] === '' && $f['state'] === '' ? 'open' : '' ?>>
        <summary class="areg-btn areg-btn--solid">Attribuer un droit</summary>
        <form method="post" action="<?= $h(effectifs_workspace_url('registre-droits/attribuer')) ?>" class="areg-grant__form">
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
            <label class="areg-field areg-field--grow"><span>Motif</span><input type="text" name="reason" maxlength="<?= AccessRegisterService::REASON_MAX ?>" placeholder="Ex. : chef de groupe pendant l’opération Tempête"></label>
            <button type="submit" class="areg-btn areg-btn--solid">Attribuer</button>
            <p class="areg-hint">Les positions s’appliquent sans dates. Un niveau déjà détenu sur le même périmètre est renouvelé avec les nouvelles dates.</p>
        </form>
    </details>
    <form id="areg-bulk" method="post" action="<?= $h(effectifs_workspace_url('registre-droits/revue')) ?>" class="areg-bulk" data-areg-bulk>
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
        <span class="areg-bulk__count">Lignes cochées : <b data-areg-selcount>0</b></span>
        <button type="submit" class="areg-btn">Marquer comme revus</button>
        <input type="text" name="reason" maxlength="<?= AccessRegisterService::REASON_MAX ?>" placeholder="Motif du retrait" aria-label="Motif du retrait">
        <button type="submit" class="areg-btn areg-btn--danger" formaction="<?= $h(effectifs_workspace_url('registre-droits/retirer')) ?>" data-areg-confirm="Retirer les droits cochés maintenant ?">Retirer la sélection</button>
    </form>
    <?php endif; ?>

    <p class="areg-count">Lignes affichées : <b data-areg-shown><?= count($rows) ?></b> / <b><?= (int) $regMatched ?></b><?php if ($regMatched > count($rows)): ?>. Affinez la recherche pour voir les autres.<?php endif; ?></p>

    <div class="areg-scroll" data-areg-scroll>
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
                            <?php else: ?>
                                <?= $val !== '' ? $h($val) : '<span class="areg-empty">—</span>' ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <?php if ($canManage): ?>
                    <td class="areg-row-actions">
                        <?php if ($r['can_edit'] || $r['can_revoke']): ?>
                        <details>
                            <summary class="areg-btn areg-btn--sm">Gérer</summary>
                            <div class="areg-pop areg-pop--right">
                                <?php if ($r['can_edit']): ?>
                                <form method="post" action="<?= $h(effectifs_workspace_url('registre-droits/modifier')) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
                                    <input type="hidden" name="ref" value="<?= $h($r['ref']) ?>">
                                    <label class="areg-field"><span>Depuis</span><input type="date" name="valid_from" value="<?= $h($r['from_input'] ?? '') ?>"></label>
                                    <label class="areg-field"><span>Jusqu’au</span><input type="date" name="valid_until" value="<?= $h($r['until']) ?>" min="<?= $h($today) ?>"></label>
                                    <label class="areg-field"><span>Motif</span><input type="text" name="reason" value="<?= $h($r['reason']) ?>" maxlength="<?= AccessRegisterService::REASON_MAX ?>"></label>
                                    <button type="submit" class="areg-btn areg-btn--sm">Enregistrer</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['can_revoke']): ?>
                                <form method="post" action="<?= $h(effectifs_workspace_url('registre-droits/retirer')) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="return_query" value="<?= $h($returnQuery) ?>">
                                    <input type="hidden" name="ref" value="<?= $h($r['ref']) ?>">
                                    <label class="areg-field"><span>Motif du retrait</span><input type="text" name="reason" maxlength="<?= AccessRegisterService::REASON_MAX ?>"></label>
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
        <p class="areg-nodata" data-areg-nodata<?= $rows === [] ? '' : ' hidden' ?>>
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 3 2 21h20L12 3Z" fill="currentColor"/><path d="M12 10v5m0 3v.01" stroke="var(--ath-surface, #fff)" stroke-width="2" stroke-linecap="round"/></svg>
            Aucune donnée à afficher
        </p>
    </div>
</section>
<script defer src="<?= $h(asset_url('assets/js/access-register.js')) ?>"></script>
