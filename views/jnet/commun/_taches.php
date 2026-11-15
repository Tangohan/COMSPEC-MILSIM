<?php
/**
 * Salon « Ordres et tâches par unité » : un tableau de toutes les unités visibles de l'arbre, avec tri, filtres, recherche,
 * arbre repliable (sans filtre), détail d'une unité dans un tiroir et totaux en pied. Sans JavaScript, chaque tri, filtre
 * et détail est une adresse servie par le serveur (la fiche de l'unité s'ouvre au-dessus du tableau) ; avec, le détail
 * s'ouvre dans le tiroir, l'arbre se replie et la recherche filtre au fil de la frappe.
 *
 * @var array<string, mixed> $view
 */

use App\Services\Jnet\JnetCommonRules;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitBoard;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($view['rows'] ?? null) ? $view['rows'] : [];
$rowsById = is_array($view['rowsById'] ?? null) ? $view['rowsById'] : [];
$unitPins = is_array($view['unitPins'] ?? null) ? $view['unitPins'] : [];
$totals = is_array($view['totals'] ?? null) ? $view['totals'] : JnetUnitBoard::totals([]);
$filters = is_array($view['filters'] ?? null) ? $view['filters'] : [];
$echelons = is_array($view['echelons'] ?? null) ? $view['echelons'] : [];
$states = is_array($view['states'] ?? null) ? $view['states'] : [];
$sort = (string) ($view['sort'] ?? 'unite');
$desc = !empty($view['desc']);
$open = (int) ($view['open'] ?? 0);
$allCount = (int) ($view['allCount'] ?? 0);
$asTree = !empty($view['asTree']);

// Adresse du salon avec les filtres courants, et un paramètre changé.
$link = static function (array $over = []) use ($filters, $sort, $desc): string {
    $params = [
        'q' => (string) ($filters['q'] ?? ''),
        'echelon' => (string) ($filters['echelon'] ?? ''),
        'etat' => (string) ($filters['etat'] ?? ''),
        'retard' => !empty($filters['retard']) ? '1' : '',
        'tri' => $sort === 'unite' ? '' : $sort,
        'sens' => $sort === 'unite' ? '' : ($desc ? 'desc' : 'asc'),
    ];

    return url(JnetCommonRules::path('taches', array_merge($params, $over)));
};
$sortLink = static function (string $key) use ($link, $sort, $desc): string {
    if ($sort === $key) {
        return $link(['tri' => $key, 'sens' => $desc ? 'asc' : 'desc']);
    }

    return $link(['tri' => $key, 'sens' => $key === 'unite' ? 'asc' : 'desc']);
};
$ariaSort = static fn (string $key): string => $sort === $key ? ' aria-sort="' . ($desc ? 'descending' : 'ascending') . '"' : '';
$hasFilter = ($filters['q'] ?? '') !== '' || ($filters['echelon'] ?? '') !== '' || ($filters['etat'] ?? '') !== '' || !empty($filters['retard']);
$sortHead = static function (string $key, string $label, string $title = '', string $class = '') use ($sortLink, $sort, $ariaSort, $h): string {
    return '<th scope="col" class="jc-th' . ($class !== '' ? ' ' . $class : '') . ($sort === $key ? ' is-sorted' : '') . '"' . $ariaSort($key) . '><a href="' . $h($sortLink($key)) . '" data-jc-salon="taches"' . ($title !== '' ? ' title="' . $h($title) . '"' : '') . '>' . $h($label) . '</a></th>';
};
$unitOpen = $open > 0 && isset($rowsById[$open]);
?>
<div class="jc-body">
    <?php if ($allCount === 0): ?>
        <div class="jc-empty">
            <p><strong>L’organigramme n’est pas encore renseigné.</strong></p>
            <p>Chaque unité de la chaîne de commandement apparaîtra ici avec ses ordres et ses tâches.</p>
            <p><a class="jn-btn" href="<?= $h(effectifs_workspace_url('chaine')) ?>">Ouvrir la chaîne de commandement</a></p>
        </div>
    <?php else: ?>
        <form class="jc-filters" method="get" action="<?= $h(url('jnet')) ?>" role="search" aria-label="Filtrer le tableau des unités">
            <input type="hidden" name="commun" value="1">
            <input type="hidden" name="salon" value="taches">
            <?php if ($sort !== 'unite'): ?>
                <input type="hidden" name="tri" value="<?= $h($sort) ?>">
                <input type="hidden" name="sens" value="<?= $desc ? 'desc' : 'asc' ?>">
            <?php endif; ?>
            <label class="jc-search">
                <span class="ja-sr">Chercher une unité ou un chef</span>
                <?= JnetCommonRules::icon('search') ?>
                <input type="search" name="q" value="<?= $h((string) ($filters['q'] ?? '')) ?>" placeholder="Chercher une unité ou un chef" autocomplete="off" data-jc-search data-jc-filter="[data-jc-row]">
            </label>
            <label class="jc-select">
                <span class="ja-sr">Échelon</span>
                <select name="echelon">
                    <option value="">Tous les échelons</option>
                    <?php foreach ($echelons as $key => $label): ?>
                        <option value="<?= $h((string) $key) ?>"<?= ($filters['echelon'] ?? '') === (string) $key ? ' selected' : '' ?>><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="jc-select">
                <span class="ja-sr">État</span>
                <select name="etat">
                    <option value="">Tous les états</option>
                    <?php foreach ($states as $key => $label): ?>
                        <option value="<?= $h((string) $key) ?>"<?= ($filters['etat'] ?? '') === (string) $key ? ' selected' : '' ?>><?= $h((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="jc-check">
                <input type="checkbox" name="retard" value="1"<?= !empty($filters['retard']) ? ' checked' : '' ?>>
                <span>Tâches en retard seulement</span>
            </label>
            <button type="submit" class="jn-btn jn-btn--small">Filtrer</button>
            <?php if ($hasFilter): ?><a class="jc-reset" href="<?= $h(url(JnetCommonRules::path('taches'))) ?>" data-jc-salon="taches">Réinitialiser</a><?php endif; ?>
            <?php if ($asTree): ?><button type="button" class="jn-btn jn-btn--small jn-btn--ghost jc-jsonly" data-jc-expand-all>Tout déplier</button><?php endif; ?>
        </form>

        <?php if ($unitOpen): ?>
            <section class="jc-unitcard" id="jc-unit-card" data-jc-unit-card="<?= $open ?>" aria-label="Détail de l’unité">
                <?php $r = $rowsById[$open]; $drawerAsCard = true; require base_path('views/jnet/commun/_unit_drawer.php'); ?>
            </section>
        <?php endif; ?>

        <?php if ($rows === []): ?>
            <div class="jc-empty">
                <p><strong>Aucune unité ne correspond à ces filtres.</strong></p>
                <p><a href="<?= $h(url(JnetCommonRules::path('taches'))) ?>" data-jc-salon="taches">Afficher toutes les unités</a></p>
            </div>
        <?php else: ?>
            <div class="jc-tablewrap">
                <table class="jc-table" data-jc-table<?= $asTree ? ' data-jc-tree-table' : '' ?>>
                    <caption class="ja-sr">Ordres et tâches de chaque unité, dans l’ordre de l’organigramme</caption>
                    <thead>
                        <tr>
                            <?= $sortHead('unite', 'Unité') ?>
                            <th scope="col" class="jc-th">Chef</th>
                            <?= $sortHead('accuser', 'À accuser', JnetCommonRules::fmt('Ordres des %1 derniers jours dont au moins un membre de l’unité n’a pas encore accusé réception', (int) $view['windowDays']), 'jc-th--n') ?>
                            <?= $sortHead('ordres', 'Ordres en cours', JnetCommonRules::fmt('Ordres reçus depuis %1 jours', (int) $view['recentDays']), 'jc-th--n') ?>
                            <?= $sortHead('taches', 'Tâches', 'À faire · en cours · faites') ?>
                            <?= $sortHead('retard', 'Retard', JnetCommonRules::fmt('Tâches ouvertes sans aucun changement depuis %1 jours (une tâche n’a pas d’échéance)', (int) $view['staleDays']), 'jc-th--n') ?>
                            <?= $sortHead('dernier', 'Dernier échange') ?>
                            <?= $sortHead('etat', 'État') ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $id = (int) $r['id'];
                            $o = $r['orders'];
                            $t = $r['tasks'];
                            $kids = count((array) $r['childIds']);
                            $unitStyle = ' style="--u:' . $h((string) ($r['accent'] !== '' ? $r['accent'] : 'var(--ja-accent)')) . ';--d:' . ($asTree ? (int) $r['depth'] : 0) . '"';
                            $folded = JnetCommonRules::fold($r['label'] . ' ' . $r['code'] . ' ' . $r['leader'] . ' ' . $r['echelonLabel']);
                            $none = '<span class="jc-nil" title="Espace non accessible pour vous">—</span>';
                            $total = max(1, (int) $t['todo'] + (int) $t['doing'] + (int) $t['done']);
                            ?>
                            <tr class="jc-row<?= $open === $id ? ' is-open' : '' ?>" id="u-<?= $id ?>" data-jc-row data-jc-q="<?= $h($folded) ?>" data-id="<?= $id ?>" data-parent="<?= (int) $r['parentId'] ?>" data-depth="<?= (int) $r['depth'] ?>" data-kids="<?= $kids ?>"<?= $unitStyle ?>>
                                <th scope="row" class="jc-cell jc-cell--unit" data-label="Unité">
                                    <div class="jc-unitcell">
                                        <?php if ($asTree && $kids > 0): ?>
                                            <button type="button" class="jc-tg jc-jsonly" data-jc-tg="<?= $id ?>" aria-expanded="true" aria-label="Replier les sous-unités"><?= JnetCommonRules::icon('caret') ?></button>
                                        <?php elseif ($asTree): ?>
                                            <span class="jc-tg jc-tg--no" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $r['label'], 0, 2)), (string) $r['badge'], 'jc-logo') ?>
                                        <span class="jc-unit">
                                            <a class="jc-unit__name" href="<?= $h($link(['unite' => (string) $id]) . '#jc-unit-card') ?>" data-jc-unit="<?= $id ?>" translate="no"><?= $h((string) $r['label']) ?></a>
                                            <span class="jc-unit__path">
                                                <?php if ((string) $r['echelonLabel'] !== ''): ?><span><?= $h((string) $r['echelonLabel']) ?></span><?php endif; ?>
                                                <?php if (!$asTree && (string) $r['parentLabel'] !== ''): ?><span>sous</span> <span translate="no"><?= $h((string) $r['parentLabel']) ?></span><?php endif; ?>
                                                <?php if ($asTree && $kids > 0): ?><span class="jc-unit__sub" data-jc-sub="<?= $kids ?>"></span><?php endif; ?>
                                            </span>
                                        </span>
                                    </div>
                                </th>
                                <td class="jc-cell" data-label="Chef"><?= $r['leader'] !== '' ? '<span translate="no">' . $h((string) $r['leader']) . '</span>' : '<span class="jc-nil">Non désigné</span>' ?></td>
                                <td class="jc-cell jc-num<?= (int) $o['pending'] > 0 ? ' is-warn' : '' ?>" data-label="À accuser"><?= (int) $o['pending'] ?></td>
                                <td class="jc-cell jc-num" data-label="Ordres en cours"><?= (int) $o['recent'] ?></td>
                                <td class="jc-cell" data-label="Tâches">
                                    <?php if ($r['tasksVisible']): ?>
                                        <div class="jc-tbar">
                                            <span class="jc-tbar__tr" role="img" aria-label="<?= $h(JnetCommonRules::fmt('À faire : %1, en cours : %2, faites : %3', (int) $t['todo'], (int) $t['doing'], (int) $t['done'])) ?>"><i class="a" style="width:<?= round((int) $t['todo'] / $total * 100, 1) ?>%"></i><i class="b" style="width:<?= round((int) $t['doing'] / $total * 100, 1) ?>%"></i><i class="c" style="width:<?= round((int) $t['done'] / $total * 100, 1) ?>%"></i></span>
                                            <span class="jc-mono"><?= (int) $t['todo'] ?> · <?= (int) $t['doing'] ?> · <?= (int) $t['done'] ?></span>
                                        </div>
                                    <?php else: ?><?= $none ?><?php endif; ?>
                                </td>
                                <td class="jc-cell jc-num<?= (int) $t['late'] > 0 ? ' is-bad' : '' ?>" data-label="Retard"><?= $r['tasksVisible'] ? (int) $t['late'] : $none ?></td>
                                <td class="jc-cell jc-when" data-label="Dernier échange"><?= $r['lastAt'] !== '' ? '<time title="' . $h((string) $r['lastAt']) . '">' . $h(JnetSpaceService::relativeTime((string) $r['lastAt'])) . '</time>' : '<span class="jc-nil">—</span>' ?></td>
                                <td class="jc-cell" data-label="État"><span class="jc-state jc-state--<?= $h((string) $r['state']) ?>"><?= $h((string) ($states[$r['state']] ?? '')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="jc-foot">
                            <th scope="row" colspan="2" class="jc-cell"><?= $h(
                                $hasFilter
                                    ? JnetCommonRules::fmt('%1 sur %2 unités affichées', (int) $totals['units'], $allCount)
                                    : ((int) $totals['units'] > 1 ? JnetCommonRules::fmt('%1 unités', (int) $totals['units']) : JnetCommonRules::fmt('%1 unité', (int) $totals['units']))
                            ) ?></th>
                            <td class="jc-cell jc-num<?= (int) $totals['pending'] > 0 ? ' is-warn' : '' ?>" data-label="À accuser"><?= (int) $totals['pending'] ?></td>
                            <td class="jc-cell jc-num" data-label="Ordres en cours"><?= (int) $totals['recent'] ?></td>
                            <td class="jc-cell" data-label="Tâches"><span class="jc-mono"><?= (int) $totals['todo'] ?> · <?= (int) $totals['doing'] ?> · <?= (int) $totals['done'] ?></span></td>
                            <td class="jc-cell jc-num<?= (int) $totals['late'] > 0 ? ' is-bad' : '' ?>" data-label="Retard"><?= (int) $totals['late'] ?></td>
                            <td class="jc-cell" colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="jc-note">
                Les ordres comptés sont ceux des <?= (int) $view['windowDays'] ?> derniers jours qui descendent jusqu’à l’unité ; « en cours » : reçus depuis <?= (int) $view['recentDays'] ?> jours.
                Une tâche « en retard » est une tâche ouverte sans aucun changement depuis <?= (int) $view['staleDays'] ?> jours.
                <?php if (empty($view['workReady'])): ?>Les tâches d’unité ne sont pas encore activées : un administrateur doit appliquer les migrations.<?php endif; ?>
            </p>

            <div hidden data-jc-drawers>
                <?php foreach ($rows as $r): ?>
                    <template data-jc-unit-tpl="<?= (int) $r['id'] ?>">
                        <?php $drawerAsCard = false; require base_path('views/jnet/commun/_unit_drawer.php'); ?>
                    </template>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
