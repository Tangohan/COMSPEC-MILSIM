<?php
/**
 * Salon « Ordres et tâches par unité » : un tableau de toutes les unités visibles de l'arbre, avec tri, filtres, recherche,
 * ligne dépliable (les 3 ordres et les 3 tâches les plus urgents) et totaux en pied. Sans JavaScript, chaque tri, filtre et
 * dépli est une adresse servie par le serveur ; avec, le dépli est instantané et la recherche filtre au fil de la frappe.
 *
 * @var array<string, mixed> $view
 */

use App\Services\Jnet\JnetCommonRules;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitBoard;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($view['rows'] ?? null) ? $view['rows'] : [];
$totals = is_array($view['totals'] ?? null) ? $view['totals'] : JnetUnitBoard::totals([]);
$filters = is_array($view['filters'] ?? null) ? $view['filters'] : [];
$echelons = is_array($view['echelons'] ?? null) ? $view['echelons'] : [];
$states = is_array($view['states'] ?? null) ? $view['states'] : [];
$sort = (string) ($view['sort'] ?? 'unite');
$desc = !empty($view['desc']);
$open = (int) ($view['open'] ?? 0);
$allCount = (int) ($view['allCount'] ?? 0);

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
$sortHead = static function (string $key, string $label, string $title = '') use ($sortLink, $sort, $ariaSort, $h): string {
    return '<th scope="col" class="jc-th' . ($sort === $key ? ' is-sorted' : '') . '"' . $ariaSort($key) . '><a href="' . $h($sortLink($key)) . '" data-jc-salon="taches"' . ($title !== '' ? ' title="' . $h($title) . '"' : '') . '>' . $h($label) . '</a></th>';
};
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
                <input type="search" name="q" value="<?= $h((string) ($filters['q'] ?? '')) ?>" placeholder="Chercher une unité ou un chef (touche /)" autocomplete="off" data-jc-search data-jc-filter="[data-jc-row]">
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
        </form>

        <?php if ($rows === []): ?>
            <div class="jc-empty">
                <p><strong>Aucune unité ne correspond à ces filtres.</strong></p>
                <p><a href="<?= $h(url(JnetCommonRules::path('taches'))) ?>" data-jc-salon="taches">Afficher toutes les unités</a></p>
            </div>
        <?php else: ?>
            <div class="jc-tablewrap">
                <table class="jc-table" data-jc-table>
                    <caption class="ja-sr">Ordres et tâches de chaque unité, dans l’ordre de l’organigramme</caption>
                    <thead>
                        <tr>
                            <?= $sortHead('unite', 'Unité') ?>
                            <th scope="col" class="jc-th">Chef</th>
                            <?= $sortHead('accuser', 'Ordres à accuser', JnetCommonRules::fmt('Ordres des %1 derniers jours dont au moins un membre de l’unité n’a pas encore accusé réception', (int) $view['windowDays'])) ?>
                            <?= $sortHead('ordres', 'Ordres en cours', JnetCommonRules::fmt('Ordres reçus depuis %1 jours', (int) $view['recentDays'])) ?>
                            <th scope="col" class="jc-th">Tâches à faire</th>
                            <?= $sortHead('taches', 'Tâches en cours') ?>
                            <th scope="col" class="jc-th">Tâches faites</th>
                            <?= $sortHead('retard', 'Tâches en retard',JnetCommonRules::fmt('Tâches ouvertes sans aucun changement depuis %1 jours (une tâche n’a pas d’échéance)', (int) $view['staleDays'])) ?>
                            <?= $sortHead('dernier', 'Dernier échange') ?>
                            <?= $sortHead('etat', 'État') ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $id = (int) $r['id'];
                            $isOpen = $open === $id;
                            $o = $r['orders'];
                            $t = $r['tasks'];
                            $unitStyle = (string) $r['accent'] !== '' ? ' style="--u:' . $h((string) $r['accent']) . '"' : '';
                            $folded = JnetCommonRules::fold($r['label'] . ' ' . $r['code'] . ' ' . $r['leader'] . ' ' . $r['echelonLabel']);
                            $none = '<span class="jc-nil" title="Espace non accessible pour vous">—</span>';
                            ?>
                            <tr class="jc-row<?= $isOpen ? ' is-open' : '' ?>" id="u-<?= $id ?>" data-jc-row data-jc-q="<?= $h($folded) ?>"<?= $unitStyle ?>>
                                <th scope="row" class="jc-cell jc-cell--unit" data-label="Unité">
                                    <div class="jc-unitcell">
                                        <a class="jc-exp" href="<?= $h($link(['unite' => $isOpen ? '' : (string) $id]) . '#u-' . $id) ?>" role="button" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="u-<?= $id ?>-d" data-jc-exp title="Voir les ordres et tâches les plus urgents"><?= JnetCommonRules::icon('caret') ?><span class="ja-sr">Détail de l’unité</span></a>
                                        <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $r['label'], 0, 2)), (string) $r['badge'], 'jc-logo') ?>
                                        <span class="jc-unit">
                                            <?php if (!empty($r['openable'])): ?>
                                                <a class="jc-unit__name" href="<?= $h(url('jnet/u/' . $id)) ?>" translate="no"><?= $h((string) $r['label']) ?></a>
                                            <?php else: ?>
                                                <span class="jc-unit__name" translate="no"><?= $h((string) $r['label']) ?></span>
                                            <?php endif; ?>
                                            <span class="jc-unit__path">
                                                <?php if ((string) $r['echelonLabel'] !== ''): ?><span><?= $h((string) $r['echelonLabel']) ?></span><?php endif; ?>
                                                <?php if ((string) $r['parentLabel'] !== ''): ?><span>sous</span> <span translate="no"><?= $h((string) $r['parentLabel']) ?></span><?php endif; ?>
                                            </span>
                                        </span>
                                    </div>
                                </th>
                                <td class="jc-cell" data-label="Chef"><?= $r['leader'] !== '' ? '<span translate="no">' . $h((string) $r['leader']) . '</span>' : '<span class="jc-nil">—</span>' ?></td>
                                <td class="jc-cell jc-num<?= (int) $o['pending'] > 0 ? ' is-warn' : '' ?>" data-label="Ordres à accuser"><?= (int) $o['pending'] ?></td>
                                <td class="jc-cell jc-num" data-label="Ordres en cours"><?= (int) $o['recent'] ?></td>
                                <td class="jc-cell jc-num" data-label="Tâches à faire"><?= $r['tasksVisible'] ? (int) $t['todo'] : $none ?></td>
                                <td class="jc-cell jc-num" data-label="Tâches en cours"><?= $r['tasksVisible'] ? (int) $t['doing'] : $none ?></td>
                                <td class="jc-cell jc-num" data-label="Tâches faites"><?= $r['tasksVisible'] ? (int) $t['done'] : $none ?></td>
                                <td class="jc-cell jc-num<?= (int) $t['late'] > 0 ? ' is-bad' : '' ?>" data-label="Tâches en retard"><?= $r['tasksVisible'] ? (int) $t['late'] : $none ?></td>
                                <td class="jc-cell jc-when" data-label="Dernier échange"><?= $r['lastAt'] !== '' ? '<time title="' . $h((string) $r['lastAt']) . '">' . $h(JnetSpaceService::relativeTime((string) $r['lastAt'])) . '</time>' : '<span class="jc-nil">—</span>' ?></td>
                                <td class="jc-cell" data-label="État"><span class="jc-state jc-state--<?= $h((string) $r['state']) ?>"><?= $h((string) ($states[$r['state']] ?? '')) ?></span></td>
                            </tr>
                            <tr class="jc-detail" id="u-<?= $id ?>-d"<?= $isOpen ? '' : ' hidden' ?>>
                                <td colspan="10">
                                    <div class="jc-detail__grid">
                                        <section aria-label="Ordres les plus urgents de <?= $h((string) $r['label']) ?>">
                                            <h3 class="jc-detail__t">Ordres les plus urgents</h3>
                                            <?php if ($o['top'] === []): ?>
                                                <p class="jc-muted">Aucun ordre reçu ces <?= (int) $view['windowDays'] ?> derniers jours.</p>
                                            <?php else: ?>
                                                <ul class="jc-mini">
                                                    <?php foreach ($o['top'] as $ord): ?>
                                                        <li>
                                                            <?php $orderHref = !empty($r['openable']) ? url('jnet/u/' . $id . '?vue=echanges&direct=1') . '#ex-' . (int) $ord['id'] : ''; ?>
                                                            <?php if ($orderHref !== ''): ?><a href="<?= $h($orderHref) ?>"><?= $h((string) $ord['title']) ?></a><?php else: ?><span><?= $h((string) $ord['title']) ?></span><?php endif; ?>
                                                            <em>
                                                                <?php if ($ord['ack'] && (int) $ord['missing'] > 0): ?><span class="jc-warn"><?= (int) $ord['missing'] ?> accusé(s) manquant(s)</span>
                                                                <?php elseif ($ord['ack']): ?><span>Accusé complet</span>
                                                                <?php else: ?><span>Sans accusé demandé</span><?php endif; ?>
                                                                <span class="jc-sep" aria-hidden="true">·</span> <time><?= $h(JnetSpaceService::relativeTime((string) $ord['at'])) ?></time>
                                                            </em>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </section>
                                        <section aria-label="Tâches les plus urgentes de <?= $h((string) $r['label']) ?>">
                                            <h3 class="jc-detail__t">Tâches les plus urgentes</h3>
                                            <?php if (!$r['tasksVisible']): ?>
                                                <p class="jc-muted">Les tâches de cette unité ne sont pas accessibles depuis votre accès.</p>
                                            <?php elseif ($t['top'] === []): ?>
                                                <p class="jc-muted">Aucune tâche ouverte.</p>
                                            <?php else: ?>
                                                <ul class="jc-mini">
                                                    <?php foreach ($t['top'] as $task): ?>
                                                        <li>
                                                            <span><?= $h((string) $task['title']) ?></span>
                                                            <em>
                                                                <?php if ($task['late']): ?><span class="jc-bad">En retard</span><?php elseif ($task['status'] === 'doing'): ?><span>En cours</span><?php else: ?><span>À faire</span><?php endif; ?>
                                                                <?php if (trim((string) $task['who']) !== ''): ?><span class="jc-sep" aria-hidden="true">·</span> <span translate="no"><?= $h((string) $task['who']) ?></span><?php endif; ?>
                                                                <span class="jc-sep" aria-hidden="true">·</span> <span>Dernier changement</span> <time><?= $h(JnetSpaceService::relativeTime((string) $task['at'])) ?></time>
                                                            </em>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </section>
                                    </div>
                                    <?php if (!empty($r['openable'])): ?>
                                        <p class="jc-detail__go"><a class="jn-btn jn-btn--small" href="<?= $h(url('jnet/u/' . $id)) ?>">Ouvrir l’espace de l’unité</a>
                                            <?php if ($r['tasksVisible']): ?><a class="jc-link" href="<?= $h(url('jnet/u/' . $id . '?vue=taches&direct=1')) ?>">Toutes ses tâches</a><?php endif; ?></p>
                                    <?php endif; ?>
                                </td>
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
                            <td class="jc-cell jc-num<?= (int) $totals['pending'] > 0 ? ' is-warn' : '' ?>" data-label="Ordres à accuser"><?= (int) $totals['pending'] ?></td>
                            <td class="jc-cell jc-num" data-label="Ordres en cours"><?= (int) $totals['recent'] ?></td>
                            <td class="jc-cell jc-num" data-label="Tâches à faire"><?= (int) $totals['todo'] ?></td>
                            <td class="jc-cell jc-num" data-label="Tâches en cours"><?= (int) $totals['doing'] ?></td>
                            <td class="jc-cell jc-num" data-label="Tâches faites"><?= (int) $totals['done'] ?></td>
                            <td class="jc-cell jc-num<?= (int) $totals['late'] > 0 ? ' is-bad' : '' ?>" data-label="Tâches en retard"><?= (int) $totals['late'] ?></td>
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
        <?php endif; ?>
    <?php endif; ?>
</div>
