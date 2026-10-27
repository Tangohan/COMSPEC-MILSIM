<?php
declare(strict_types=1);

use App\Services\Intel\IntelPlayerFeedService;

/** @var list<array<string, mixed>> $playersRows */
/** @var array<string, int> $playersTotals */
/** @var array<string, bool> $playersSources */
/** @var string $playersPeriod */
/** @var string $playersQuery */
/** @var string $playersSort */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($playersRows ?? null) ? $playersRows : [];
$totals = is_array($playersTotals ?? null) ? $playersTotals : [];
$period = (string) ($playersPeriod ?? '30j');
$q = (string) ($playersQuery ?? '');
$sort = (string) ($playersSort ?? 'total');
$cats = IntelPlayerFeedService::CATEGORIES;
$missing = array_keys(array_filter(is_array($playersSources ?? null) ? $playersSources : [], static fn (bool $ok): bool => !$ok));
$labels = IntelPlayerFeedService::sourceLabels();

$link = static function (array $over) use ($period, $q, $sort): string {
    $params = array_filter(array_merge(['periode' => $period, 'q' => $q, 'tri' => $sort], $over), static fn ($v): bool => $v !== '' && $v !== null);

    return url('back-office/remontees/joueurs' . ($params !== [] ? '?' . http_build_query($params) : ''));
};
$ago = static function (string $ts): string {
    $t = strtotime($ts);
    if ($t === false || $ts === '') {
        return '—';
    }
    $d = time() - $t;

    return match (true) {
        $d < 90 => 'à l’instant',
        $d < 3600 => 'il y a ' . (int) round($d / 60) . ' min',
        $d < 86400 => 'il y a ' . (int) round($d / 3600) . ' h',
        $d < 86400 * 30 => 'il y a ' . (int) round($d / 86400) . ' j',
        default => 'le ' . date('d/m/Y', $t),
    };
};
$subTitle = static function (array $subs): string {
    arsort($subs);
    $parts = [];
    foreach ($subs as $k => $n) {
        $parts[] = $k . ' : ' . $n;
    }

    return implode(' · ', $parts);
};
$sortHead = static function (string $key, string $label, string $title = '') use ($h, $link, $sort): string {
    $active = $sort === $key;

    return '<a href="' . $h($link(['tri' => $key])) . '" class="rmj__sort' . ($active ? ' is-active' : '') . '"' . ($title !== '' ? ' title="' . $h($title) . '"' : '')
        . ($active ? ' aria-sort="descending"' : '') . '>' . $h($label) . ($active ? ' <span aria-hidden="true">▾</span>' : '') . '</a>';
};
$grand = (int) array_sum($totals);

// Intensité d'une case (0 à 4), relative au maximum de sa colonne : le tableau se lit comme une carte de chaleur.
$colMax = array_fill_keys(array_keys($cats), 0);
$maxTotal = 0;
$lastSeen = '';
$unlinked = 0;
foreach ($rows as $p) {
    foreach ($cats as $k => $c) {
        $colMax[$k] = max($colMax[$k], (int) ($p['counts'][$k] ?? 0));
    }
    $maxTotal = max($maxTotal, (int) $p['total']);
    if ((string) $p['last'] > $lastSeen) {
        $lastSeen = (string) $p['last'];
    }
    if ($p['kind'] !== 'user') {
        $unlinked++;
    }
}
$heat = static function (int $n, int $max): int {
    if ($n <= 0 || $max <= 0) {
        return 0;
    }

    return max(1, min(4, (int) ceil(4 * $n / $max)));
};
$topCat = '';
if ($grand > 0) {
    $sorted = $totals;
    arsort($sorted);
    $topCat = (string) array_key_first($sorted);
}
$playerName = static fn (array $p): string => (string) ($p['callsign'] !== '' ? $p['callsign'] : ($p['account'] !== '' ? $p['account'] : 'Sans indicatif'));
$playerHref = static fn (array $p, array $extra = []): ?string => $p['key'] !== 'inconnu'
    ? url('back-office/remontees/joueurs/' . rawurlencode((string) $p['key'])) . (($qs = http_build_query(array_filter(array_merge(['periode' => $period !== 'tout' ? $period : ''], $extra), static fn ($v): bool => $v !== ''))) !== '' ? '?' . $qs : '')
    : null;
$kindLabel = ['user' => 'Compte rattaché', 'steam' => 'Identifié par Steam', 'callsign' => 'Indicatif seul', 'unknown' => 'Non identifié'];
$periodLabel = IntelPlayerFeedService::PERIODS[$period] ?? $period;
?>
<div class="rmj">
    <header class="rmj__head">
        <div class="rmj__head-txt">
            <p class="rmj__kicker">Renseignement · Suivi</p>
            <h1 class="rmj__title">Remontées par joueur</h1>
            <p class="rmj__lead">Tout ce que chaque joueur a envoyé du jeu, compté par catégorie. Ouvrez un joueur pour son fil chronologique, ou cliquez un compteur pour ne voir que cette catégorie.</p>
        </div>
        <a class="rmj__btn" href="<?= $h(url('back-office/remontees')) ?>">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 4h10M3 8h10M3 12h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            Fil des remontées<?php if (!empty($intakeToTreat)): ?> <span class="rmj__badge"><?= (int) $intakeToTreat ?> à traiter</span><?php endif; ?>
        </a>
    </header>

    <?php $rmtNav = 'players'; require base_path('views/admin/intel/_intake_nav.php'); ?>

    <section class="rmj__kpis" aria-label="Synthèse · <?= $h($periodLabel) ?>">
        <div class="rmj__kpi">
            <span class="rmj__kpi-label"><i class="rmj__dot rmj__dot--ok" aria-hidden="true"></i>Joueurs</span>
            <strong class="rmj__kpi-value"><?= count($rows) ?></strong>
            <span class="rmj__kpi-sub">ont remonté au moins une fois</span>
        </div>
        <div class="rmj__kpi">
            <span class="rmj__kpi-label"><i class="rmj__dot rmj__dot--info" aria-hidden="true"></i>Remontées</span>
            <strong class="rmj__kpi-value"><?= $grand ?></strong>
            <span class="rmj__kpi-sub"><?php if (count($rows) > 0): ?><strong><?= (int) round($grand / count($rows)) ?></strong> <span>en moyenne par joueur</span><?php else: ?>aucune sur la période<?php endif; ?></span>
        </div>
        <div class="rmj__kpi">
            <span class="rmj__kpi-label"><i class="rmj__dot rmj__dot--accent" aria-hidden="true"></i>Catégorie en tête</span>
            <strong class="rmj__kpi-value rmj__kpi-value--text"><?= $topCat !== '' ? $h($cats[$topCat]['short']) : '—' ?></strong>
            <span class="rmj__kpi-sub"><?= $topCat !== '' ? (int) ($totals[$topCat] ?? 0) . ' · ' . $h($cats[$topCat]['label']) : 'Pas encore de remontée' ?></span>
        </div>
        <div class="rmj__kpi">
            <span class="rmj__kpi-label"><i class="rmj__dot rmj__dot--warn" aria-hidden="true"></i>Sans compte</span>
            <strong class="rmj__kpi-value<?= $unlinked > 0 ? ' is-warn' : '' ?>"><?= $unlinked ?></strong>
            <span class="rmj__kpi-sub">identifiés par Steam ou indicatif seul</span>
        </div>
        <div class="rmj__kpi">
            <span class="rmj__kpi-label"><i class="rmj__dot" aria-hidden="true"></i>Dernière remontée</span>
            <strong class="rmj__kpi-value rmj__kpi-value--text"><?= $h($ago($lastSeen)) ?></strong>
            <span class="rmj__kpi-sub"><?= $lastSeen !== '' && ($t = strtotime($lastSeen)) !== false ? $h(date('d/m/Y H:i', $t)) : 'Rien reçu' ?></span>
        </div>
    </section>

    <form class="rmj__toolbar" method="get" action="<?= $h(url('back-office/remontees/joueurs')) ?>" role="search">
        <input type="hidden" name="tri" value="<?= $h($sort) ?>">
        <?php /* Entrée dans la recherche : garde la période (bouton par défaut sans nom, période en champ caché placé avant les boutons). */ ?>
        <button type="submit" class="rmj__sr" tabindex="-1">Rechercher</button>
        <input type="hidden" name="periode" value="<?= $h($period) ?>">
        <label class="rmj__search">
            <span class="rmj__sr">Rechercher un joueur</span>
            <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Indicatif, compte, Steam…">
            <?php if ($q !== ''): ?><a class="rmj__clear" href="<?= $h($link(['q' => ''])) ?>" aria-label="Effacer la recherche">✕</a><?php endif; ?>
        </label>
        <div class="rmj__seg" role="group" aria-label="Période">
            <?php foreach (IntelPlayerFeedService::PERIODS as $k => $label): ?>
                <button type="submit" name="periode" value="<?= $h($k) ?>" class="rmj__seg-btn<?= $period === $k ? ' is-active' : '' ?>" aria-pressed="<?= $period === $k ? 'true' : 'false' ?>"><?= $h($label) ?></button>
            <?php endforeach; ?>
        </div>
    </form>

    <section class="rmj__panel" aria-label="Remontées par joueur">
        <div class="rmj__panel-head">
            <div>
                <h2 class="rmj__panel-title">Joueurs</h2>
                <p class="rmj__panel-sub">Période : <?= $h($periodLabel) ?><?= $q !== '' ? ' · recherche « ' . $h($q) . ' »' : '' ?><?php if ($rows !== []): ?> · <span>cliquez un en-tête de colonne pour trier.</span><?php endif; ?></p>
            </div>
            <?php if ($rows !== []): ?><p class="rmj__scale" aria-hidden="true">Peu <span class="rmj__scale-sw rmj__h1"></span><span class="rmj__scale-sw rmj__h2"></span><span class="rmj__scale-sw rmj__h3"></span><span class="rmj__scale-sw rmj__h4"></span> Beaucoup</p><?php endif; ?>
        </div>

        <?php if ($rows === []): ?>
            <div class="rmj__empty">
                <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="5.4" r="2.6" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M3 13.5c.6-2.6 2.6-4 5-4s4.4 1.4 5 4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <p class="rmj__empty-title"><?= $q !== '' ? 'Aucun joueur ne correspond à cette recherche.' : 'Aucune remontée sur la période.' ?></p>
                <p>Élargissez la période<?= $q !== '' ? ' ou effacez la recherche' : '' ?>. Les remontées arrivent des téléphones ATAK et du mod en jeu.</p>
                <?php if ($period !== 'tout'): ?><a class="rmj__btn" href="<?= $h($link(['periode' => 'tout', 'q' => ''])) ?>">Voir toute la période</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="rmj__scroll" tabindex="0" aria-label="Tableau défilant horizontalement">
                <table class="rmj__table">
                    <thead>
                        <tr>
                            <th scope="col" class="rmj__who-col"><?= $sortHead('name', 'Joueur') ?></th>
                            <?php foreach ($cats as $k => $c): ?>
                                <th scope="col" class="rmj__num"><?= $sortHead($k, $c['short'], $c['label']) ?></th>
                            <?php endforeach; ?>
                            <th scope="col" class="rmj__num rmj__total-col"><?= $sortHead('total', 'Total') ?></th>
                            <th scope="col" class="rmj__last-col"><?= $sortHead('last', 'Dernière') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $p):
                            $href = $playerHref($p);
                            $name = $playerName($p);
                            $others = array_values(array_filter((array) $p['callsigns_seen'], static fn (string $c): bool => mb_strtolower($c) !== mb_strtolower((string) $p['callsign'])));
                        ?>
                            <tr>
                                <th scope="row" class="rmj__who-col">
                                    <div class="rmj__who">
                                        <span class="rmj__avatar rmj__avatar--<?= $h($p['kind']) ?>" title="<?= $h($kindLabel[$p['kind']] ?? '') ?>" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($name, 0, 2))) ?></span>
                                        <div class="rmj__who-txt">
                                            <?php if ($href !== null): ?><a class="rmj__name" href="<?= $h($href) ?>"><?= $h($name) ?></a><?php else: ?><span class="rmj__name"><?= $h($name) ?></span><?php endif; ?>
                                            <span class="rmj__who-sub">
                                                <?php if ($p['kind'] === 'user'): ?><span title="Compte rattaché"><?= $h($p['account']) ?></span><?php else: ?><span class="rmj__tag">Sans compte</span><?php endif; ?>
                                                <?php if ($p['steam'] !== ''): ?><span class="rmj__mono" title="Identifiant Steam"><?= $h($p['steam']) ?></span><?php endif; ?>
                                            </span>
                                            <?php if ($others !== []): ?><span class="rmj__who-sub rmj__who-alt" title="Autres indicatifs vus dans les remontées">aussi : <?= $h(implode(', ', array_slice($others, 0, 4))) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </th>
                                <?php foreach ($cats as $k => $c): $n = (int) ($p['counts'][$k] ?? 0); $subs = (array) ($p['subs'][$k] ?? []); $lvl = $heat($n, $colMax[$k]); ?>
                                    <td class="rmj__num rmj__cell<?= $lvl > 0 ? ' rmj__h' . $lvl : ' is-zero' ?>"<?= $n > 0 ? ' title="' . $h($c['label'] . ($subs !== [] ? ' — ' . $subTitle($subs) : '')) . '"' : '' ?>>
                                        <?php if ($n > 0 && $href !== null): ?><a href="<?= $h($playerHref($p, ['categorie' => $k])) ?>"><?= $n ?></a><?php elseif ($n > 0): ?><?= $n ?><?php else: ?><span aria-label="aucune">·</span><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="rmj__num rmj__total-col">
                                    <strong><?= (int) $p['total'] ?></strong>
                                    <span class="rmj__bar" aria-hidden="true"><span style="width: <?= $maxTotal > 0 ? (int) round(100 * (int) $p['total'] / $maxTotal) : 0 ?>%"></span></span>
                                </td>
                                <td class="rmj__last-col" title="<?= $h($p['last']) ?>"><?= $h($ago((string) $p['last'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" class="rmj__who-col">Total</th>
                            <?php foreach ($cats as $k => $c): ?><td class="rmj__num"><?= (int) ($totals[$k] ?? 0) ?></td><?php endforeach; ?>
                            <td class="rmj__num rmj__total-col"><strong><?= $grand ?></strong></td>
                            <td class="rmj__last-col"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <ul class="rmj__cards" aria-label="Joueurs">
                <?php foreach ($rows as $p):
                    $href = $playerHref($p);
                    $name = $playerName($p);
                ?>
                    <li class="rmj__card">
                        <div class="rmj__card-top">
                            <span class="rmj__avatar rmj__avatar--<?= $h($p['kind']) ?>" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($name, 0, 2))) ?></span>
                            <div class="rmj__who-txt">
                                <?php if ($href !== null): ?><a class="rmj__name" href="<?= $h($href) ?>"><?= $h($name) ?></a><?php else: ?><span class="rmj__name"><?= $h($name) ?></span><?php endif; ?>
                                <span class="rmj__who-sub"><?= $p['kind'] === 'user' ? $h($p['account']) : '<span class="rmj__tag">Sans compte</span>' ?> · <?= $h($ago((string) $p['last'])) ?></span>
                            </div>
                            <strong class="rmj__card-total"><?= (int) $p['total'] ?></strong>
                        </div>
                        <span class="rmj__bar" aria-hidden="true"><span style="width: <?= $maxTotal > 0 ? (int) round(100 * (int) $p['total'] / $maxTotal) : 0 ?>%"></span></span>
                        <ul class="rmj__chips">
                            <?php foreach ($cats as $k => $c): $n = (int) ($p['counts'][$k] ?? 0); if ($n === 0) { continue; } ?>
                                <li><?php if ($href !== null): ?><a class="rmj__chip" href="<?= $h($playerHref($p, ['categorie' => $k])) ?>" title="<?= $h($c['label']) ?>"><?= $h($c['short']) ?> <strong><?= $n ?></strong></a><?php else: ?><span class="rmj__chip" title="<?= $h($c['label']) ?>"><?= $h($c['short']) ?> <strong><?= $n ?></strong></span><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <details class="rmj__panel rmj__fold">
        <summary>Sources lues et légende</summary>
        <div class="rmj__fold-body">
            <dl class="rmj__legend">
                <?php foreach ($cats as $k => $c): ?>
                    <div><dt><?= $h($c['short']) ?></dt><dd><strong><?= $h($c['label']) ?></strong><span><?= $h(implode(', ', array_keys(array_filter(IntelPlayerFeedService::sourceCategories(), static fn (string $cat): bool => $cat === $k)))) ?></span></dd></div>
                <?php endforeach; ?>
            </dl>
            <?php if ($missing !== []): ?>
                <p class="rmj__note rmj__note--warn">Absentes de cette installation (comptées à zéro) : <?= $h(implode(', ', array_map(static fn (string $t): string => ($labels[$t] ?? $t) . ' (' . $t . ')', $missing))) ?>.</p>
            <?php endif; ?>
            <p class="rmj__note">Les indicatifs sont rapprochés par compte, puis par identifiant Steam, puis par indicatif. Survolez un compteur pour le détail par type (SPOTREP, SALUTE, CAS, MEDEVAC…). Les pertes sont attribuées au joueur qui les a signalées, à défaut à la victime.</p>
        </div>
    </details>
</div>
