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

    return '<a href="' . $h($link(['tri' => $key])) . '" class="rmt__sort' . ($active ? ' is-active' : '') . '"' . ($title !== '' ? ' title="' . $h($title) . '"' : '')
        . ($active ? ' aria-sort="descending"' : '') . '>' . $h($label) . ($active ? ' <span aria-hidden="true">▾</span>' : '') . '</a>';
};
$grand = array_sum($totals);
?>
<div class="rmt">
    <div class="rmt__frame">
        <header class="rmt__hero">
            <p class="rmt__kicker">Renseignement · Suivi</p>
            <h1 class="rmt__title">Remontées par joueur</h1>
            <p class="rmt__lead">Tout ce que chaque joueur a envoyé du jeu : comptes rendus, fiches SSE, MEDEVAC et 9-line, SIGINT et RF, photos et notes de reconnaissance, POI, pings, QRF, zones, pertes et alertes SALUTE de la messagerie. Les indicatifs sont rapprochés par compte, puis par identifiant Steam, puis par indicatif. Ouvrez un joueur pour son fil chronologique.</p>
        </header>
        <?php $rmtNav = 'players'; require base_path('views/admin/intel/_intake_nav.php'); ?>

        <form class="rmt__filters" method="get" action="<?= $h(url('back-office/remontees/joueurs')) ?>" role="search">
            <input type="hidden" name="tri" value="<?= $h($sort) ?>">
            <?php /* Entrée dans la recherche : garde la période (bouton par défaut sans nom, période en champ caché placé avant les boutons). */ ?>
            <button type="submit" class="rmt__sr" tabindex="-1">Rechercher</button>
            <input type="hidden" name="periode" value="<?= $h($period) ?>">
            <label class="rmt__search">
                <span class="rmt__sr">Rechercher un joueur</span>
                <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Indicatif, compte, Steam…">
            </label>
            <div class="rmt__seg" role="group" aria-label="Période">
                <?php foreach (IntelPlayerFeedService::PERIODS as $k => $label): ?>
                    <button type="submit" name="periode" value="<?= $h($k) ?>" class="rmt__seg-btn<?= $period === $k ? ' is-active' : '' ?>"<?= $period === $k ? ' aria-pressed="true"' : ' aria-pressed="false"' ?>><?= $h($label) ?></button>
                <?php endforeach; ?>
            </div>
            <?php if ($q !== ''): ?>
                <a class="rmt__clear" href="<?= $h($link(['q' => ''])) ?>">Effacer la recherche</a>
            <?php endif; ?>
        </form>

        <section class="rmt__box" aria-label="Remontées par joueur">
            <div class="rmt__tabs">
                <span class="rmt__tab is-active"><strong><?= count($rows) ?></strong> joueur<?= count($rows) > 1 ? 's' : '' ?></span>
                <span class="rmt__tab"><strong><?= (int) $grand ?></strong> remontée<?= $grand > 1 ? 's' : '' ?> · <?= $h(IntelPlayerFeedService::PERIODS[$period] ?? $period) ?></span>
            </div>
            <?php if ($rows === []): ?>
                <div class="rmt__empty">
                    <p class="rmt__empty-title">Aucune remontée sur la période.</p>
                    <p>Élargissez la période ou effacez la recherche. Les remontées arrivent des téléphones ATAK et du mod en jeu.</p>
                </div>
            <?php else: ?>
                <div class="rmt__scroll" tabindex="0" aria-label="Tableau défilant horizontalement">
                    <table class="rmt__ptable">
                        <thead>
                            <tr>
                                <th scope="col" class="rmt__ptable-who"><?= $sortHead('name', 'Joueur') ?></th>
                                <?php foreach ($cats as $k => $c): ?>
                                    <th scope="col" class="rmt__num"><?= $sortHead($k, $c['short'], $c['label']) ?></th>
                                <?php endforeach; ?>
                                <th scope="col" class="rmt__num rmt__ptable-total"><?= $sortHead('total', 'Total') ?></th>
                                <th scope="col" class="rmt__ptable-last"><?= $sortHead('last', 'Dernière') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $p):
                                $href = $p['key'] !== 'inconnu' ? url('back-office/remontees/joueurs/' . rawurlencode((string) $p['key'])) . ($period !== 'tout' ? '?periode=' . rawurlencode($period) : '') : null;
                                $name = (string) ($p['callsign'] !== '' ? $p['callsign'] : ($p['account'] !== '' ? $p['account'] : 'Sans indicatif'));
                                $others = array_values(array_filter((array) $p['callsigns_seen'], static fn (string $c): bool => mb_strtolower($c) !== mb_strtolower((string) $p['callsign'])));
                            ?>
                                <tr>
                                    <th scope="row" class="rmt__ptable-who">
                                        <div class="rmt__who">
                                            <span class="rmt__avatar rmt__avatar--<?= $h($p['kind']) ?>" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($name, 0, 2))) ?></span>
                                            <div class="rmt__who-txt">
                                                <?php if ($href !== null): ?><a class="rmt__link" href="<?= $h($href) ?>"><?= $h($name) ?></a><?php else: ?><span class="rmt__link"><?= $h($name) ?></span><?php endif; ?>
                                                <span class="rmt__who-sub">
                                                    <?php if ($p['kind'] === 'user'): ?><span title="Compte rattaché"><?= $h($p['account']) ?></span><?php else: ?><span class="rmt__muted">Sans compte rattaché</span><?php endif; ?>
                                                    <?php if ($p['steam'] !== ''): ?> · <span class="rmt__mono" title="Identifiant Steam"><?= $h($p['steam']) ?></span><?php endif; ?>
                                                </span>
                                                <?php if ($others !== []): ?><span class="rmt__who-sub rmt__muted" title="Autres indicatifs vus dans les remontées">aussi : <?= $h(implode(', ', array_slice($others, 0, 4))) ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                    </th>
                                    <?php foreach ($cats as $k => $c): $n = (int) ($p['counts'][$k] ?? 0); $subs = (array) ($p['subs'][$k] ?? []); ?>
                                        <td class="rmt__num<?= $n === 0 ? ' is-zero' : '' ?>"<?= $n > 0 && $subs !== [] ? ' title="' . $h($c['label'] . ' — ' . $subTitle($subs)) . '"' : ($n > 0 ? ' title="' . $h($c['label']) . '"' : '') ?>>
                                            <?php if ($n > 0 && $href !== null): ?><a href="<?= $h(url('back-office/remontees/joueurs/' . rawurlencode((string) $p['key'])) . '?' . http_build_query(['periode' => $period, 'categorie' => $k])) ?>"><?= $n ?></a><?php else: ?><?= $n > 0 ? $n : '·' ?><?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="rmt__num rmt__ptable-total"><strong><?= (int) $p['total'] ?></strong></td>
                                    <td class="rmt__ptable-last" title="<?= $h($p['last']) ?>"><?= $h($ago((string) $p['last'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row" class="rmt__ptable-who">Total</th>
                                <?php foreach ($cats as $k => $c): ?><td class="rmt__num"><?= (int) ($totals[$k] ?? 0) ?></td><?php endforeach; ?>
                                <td class="rmt__num rmt__ptable-total"><strong><?= (int) $grand ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <details class="rmt__card rmt__fold">
            <summary>Sources lues et légende</summary>
            <div class="rmt__card-body">
                <dl class="rmt__facts rmt__facts--wide">
                    <?php foreach ($cats as $k => $c): ?>
                        <div><dt><?= $h($c['short']) ?></dt><dd><?= $h($c['label']) ?> — <?= $h(implode(', ', array_keys(array_filter(IntelPlayerFeedService::sourceCategories(), static fn (string $cat): bool => $cat === $k)))) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <?php if ($missing !== []): ?>
                    <p class="rmt__hint">Absentes de cette installation (comptées à zéro) : <?= $h(implode(', ', array_map(static fn (string $t): string => ($labels[$t] ?? $t) . ' (' . $t . ')', $missing))) ?>.</p>
                <?php endif; ?>
                <p class="rmt__hint">Survolez un compteur pour le détail par type (SPOTREP, SALUTE, CAS, MEDEVAC…). Les pertes sont attribuées au joueur qui les a signalées, à défaut à la victime.</p>
            </div>
        </details>
    </div>
</div>
