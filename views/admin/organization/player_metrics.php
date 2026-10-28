<?php
declare(strict_types=1);

use App\Support\PlayerMetrics;

/** @var list<array<string, mixed>> $pmRows */
/** @var array<string, int> $pmTotals */
/** @var list<array<string, mixed>> $pmAircraft */
/** @var list<array{key: string, label: string, seconds: int, members: int}> $pmRoles */
/** @var array{screen: bool, distance: bool, garmin: bool} $pmSources */
/** @var int $pmDays */
/** @var string $pmSort */
/** @var array<int, string> $pmPeriods */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($pmRows ?? null) ? $pmRows : [];
$t = is_array($pmTotals ?? null) ? $pmTotals : PlayerMetrics::totals([]);
$aircraft = is_array($pmAircraft ?? null) ? $pmAircraft : [];
$roles = is_array($pmRoles ?? null) ? $pmRoles : [];
$sources = is_array($pmSources ?? null) ? $pmSources : ['screen' => false, 'distance' => false, 'garmin' => false];
$days = (int) ($pmDays ?? 30);
$sort = (string) ($pmSort ?? 'jeu');
$periods = is_array($pmPeriods ?? null) ? $pmPeriods : [];
$dur = [PlayerMetrics::class, 'duration'];
$dist = [PlayerMetrics::class, 'distance'];
$qs = static function (array $over) use ($days, $sort): string {
    $q = ['jours' => (string) $days, 'tri' => $sort];
    foreach ($over as $k => $v) {
        $q[$k] = (string) $v;
    }
    if (($q['jours'] ?? '') === '30') {
        unset($q['jours']);
    }
    if (($q['tri'] ?? '') === 'jeu') {
        unset($q['tri']);
    }

    return url('back-office/metriques-joueurs' . ($q !== [] ? '?' . http_build_query($q) : ''));
};
$modes = [
    ['key' => 'foot', 'label' => 'À pied', 'm' => $t['foot_m']],
    ['key' => 'land', 'label' => 'Véhicule terrestre', 'm' => $t['land_m']],
    ['key' => 'sea', 'label' => 'Bateau', 'm' => $t['sea_m']],
    ['key' => 'air', 'label' => 'Vol', 'm' => $t['air_m']],
    ['key' => 'halo', 'label' => 'Chute HALO', 'm' => $t['halo_m']],
];
$modeSum = array_sum(array_column($modes, 'm'));
$maxRole = $roles !== [] ? max(1, ...array_column($roles, 'seconds')) : 1;
$maxAir = $aircraft !== [] ? max(1, ...array_column($aircraft, 'seconds')) : 1;
$maxPlay = $rows !== [] ? max(1, ...array_column($rows, 'play')) : 1;
$missing = array_keys(array_filter($sources, static fn (bool $ok): bool => !$ok));
$sourceLabels = ['screen' => 'temps d’écran', 'distance' => 'distances', 'garmin' => 'montre Garmin'];
$periodLabel = $days > 0 ? (string) ($periods[$days] ?? '') : 'depuis le début';
$num = static fn (int $n): string => number_format($n, 0, ',', ' ');
$dash = '<span class="aksq__muted">—</span>';
$kpis = [
    ['label' => 'Membres actifs', 'value' => (string) $t['members'], 'zero' => $t['members'] === 0, 'sub' => $periodLabel],
    ['label' => 'Temps de jeu', 'value' => $t['play'] > 0 ? $dur($t['play']) : '—', 'zero' => $t['play'] === 0, 'sub' => $t['members'] > 0 && $t['play'] > 0 ? '≈ ' . $dur(intdiv($t['play'], $t['members'])) . ' par membre' : 'pas encore de donnée'],
    ['label' => 'Temps d’écran', 'value' => $t['screen'] > 0 ? $dur($t['screen']) : '—', 'zero' => $t['screen'] === 0, 'sub' => $t['play'] > 0 && $t['screen'] > 0 ? (int) round(100 * $t['screen'] / $t['play']) . ' % du temps de jeu' : 'pas encore de donnée'],
    ['label' => 'Distance parcourue', 'value' => $t['total_m'] > 0 ? $dist($t['total_m']) : '—', 'zero' => $t['total_m'] === 0, 'sub' => $t['total_m'] > 0 ? $num($t['steps']) . ' pas en jeu' : 'pas encore de donnée'],
    ['label' => 'Heures de vol', 'value' => $t['pilot_s'] > 0 ? $dur($t['pilot_s']) : '—', 'zero' => $t['pilot_s'] === 0, 'sub' => $t['pilot_s'] > 0 ? 'cumul depuis le début' : 'pas encore de donnée'],
    ['label' => 'Montres Garmin', 'value' => (string) $t['watches'], 'zero' => $t['watches'] === 0, 'sub' => $t['watches'] > 0 ? $num($t['watch_steps']) . ' pas mesurés' : 'aucune montre reliée'],
];
$sortCol = ['nom' => 0, 'jeu' => 2, 'ecran' => 3, 'distance' => 4, 'pied' => 5, 'vol' => 7, 'pas' => 8][$sort] ?? 2;
$cols = [
    ['Membre', 'text', ''],
    ['Rôle principal', 'text', ''],
    ['Temps de jeu', 'num', ''],
    ['Écran', 'num', 'Temps passé téléphone ATAK ouvert'],
    ['Distance', 'num', 'Tous modes confondus'],
    ['À pied', 'num', ''],
    ['Pas en jeu', 'num', ''],
    ['Vol (cumul)', 'num', 'Heures de vol depuis le début du suivi'],
    ['Pas (montre)', 'num', 'Pas mesurés par la montre Garmin en jeu'],
    ['Récupération', 'num', 'Montre Garmin : Body Battery et sommeil moyen'],
    ['Sessions', 'num', 'Sessions avec distance relevée'],
    ['Dernière activité', 'num', ''],
];
?>
<div class="aksq pm">
    <div class="aksq__frame">
        <header class="aksq__hero pm__hero">
            <div class="pm__head">
                <div class="pm__intro">
                    <p class="aksq__kicker">Personnel · Métriques joueurs</p>
                    <h1 class="aksq__title">Métriques joueurs</h1>
                    <p class="aksq__lead">Temps de jeu, temps d’écran, distances, heures de vol et montre Garmin de vos membres. Cliquez un membre pour ouvrir sa fiche.</p>
                </div>
                <div class="pm__actions">
                    <a href="<?= $h(url('back-office/atak/temps-ecran' . ($days !== 30 ? '?jours=' . $days : ''))) ?>" class="pm__ghost">Temps d’écran et rôles</a>
                    <a href="<?= $h(url('back-office/atak/heures' . ($days !== 30 ? '?jours=' . $days : ''))) ?>" class="pm__ghost">Heures de jeu (tableur)</a>
                    <a href="<?= $h($qs(['format' => 'csv'])) ?>" class="akhr__btn akhr__btn--export" download>Exporter en CSV</a>
                </div>
            </div>
            <div class="pm__bar">
                <nav class="akhr__tabs" aria-label="Période">
                    <?php foreach ($periods as $n => $label): ?>
                        <a href="<?= $h($qs(['jours' => $n])) ?>" class="akhr__tab<?= (int) $n === $days ? ' is-active' : '' ?>"<?= (int) $n === $days ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="pm__kpis">
                <?php foreach ($kpis as $k): ?>
                    <div class="pm__kpi<?= $k['zero'] ? ' is-zero' : '' ?>">
                        <span class="pm__kpi-label"><?= $h($k['label']) ?></span>
                        <strong class="pm__kpi-value"><?= $h($k['value']) ?></strong>
                        <span class="pm__kpi-sub"><?= $h($k['sub']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </header>

        <?php if ($missing !== []): ?>
            <p class="aksq__notice">Données pas encore disponibles sur ce serveur : <?= $h(implode(', ', array_map(static fn (string $k): string => $sourceLabels[$k] ?? $k, $missing))) ?>. Un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
        <?php endif; ?>

        <?php if ($rows === []): ?>
            <div class="aksq__empty">
                <p class="aksq__empty-title">Rien sur cette période.</p>
                <p>Les téléphones ATAK envoient le temps d’écran toutes les 5 minutes, le serveur Arma envoie les distances toutes les 10 minutes, et la montre Garmin se synchronise depuis le PC du joueur toutes les 5 minutes.</p>
            </div>
        <?php else: ?>
            <div class="pm__panels">
                <?php if ($roles !== []): ?>
                    <section class="pm__panel">
                        <h2 class="pm__panel-title">Temps de jeu par rôle<small><?= $h($periodLabel) ?></small></h2>
                        <ol class="pm__rank">
                            <?php foreach (array_slice($roles, 0, 8) as $r): ?>
                                <li>
                                    <span class="pm__rank-label" title="<?= $h($r['label']) ?>"><?= $h($r['label']) ?></span>
                                    <span class="pm__rank-value"><?= $h($dur((int) $r['seconds'])) ?><em><?= (int) $r['members'] ?> membre(s)</em></span>
                                    <span class="pm__track"><span style="width: <?= max(2, (int) round(100 * $r['seconds'] / $maxRole)) ?>%"></span></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <section class="pm__panel">
                    <h2 class="pm__panel-title">Distances par mode<small><?= $h($periodLabel) ?></small></h2>
                    <?php if ($modeSum > 0): ?>
                        <div class="pm__stack" role="img" aria-label="Répartition des distances par mode">
                            <?php foreach ($modes as $mode): ?>
                                <?php if ($mode['m'] > 0): ?><span class="pm__seg pm__seg--<?= $h($mode['key']) ?>" style="width: <?= max(1, round(100 * $mode['m'] / $modeSum, 1)) ?>%"></span><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <ul class="pm__legend">
                            <?php foreach ($modes as $mode): ?>
                                <li class="<?= $mode['m'] > 0 ? '' : 'is-zero' ?>">
                                    <span class="pm__dot pm__seg--<?= $h($mode['key']) ?>"></span>
                                    <span class="pm__legend-label"><?= $h($mode['label']) ?></span>
                                    <span class="pm__legend-value"><?= $h($dist((int) $mode['m'])) ?></span>
                                    <span class="pm__legend-pct"><?= $mode['m'] > 0 ? (int) round(100 * $mode['m'] / $modeSum) . ' %' : '' ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($t['halo_jumps'] > 0): ?><p class="pm__foot"><?= (int) $t['halo_jumps'] ?> saut(s) HALO sur la période.</p><?php endif; ?>
                    <?php else: ?>
                        <p class="pm__none">Aucune distance relevée sur la période. Le serveur Arma envoie les distances toutes les 10 minutes pendant la partie.</p>
                    <?php endif; ?>
                </section>

                <section class="pm__panel">
                    <h2 class="pm__panel-title">Heures de vol par appareil<small>cumul depuis le début</small></h2>
                    <?php if ($aircraft !== []): ?>
                        <ol class="pm__rank">
                            <?php foreach (array_slice($aircraft, 0, 8) as $a): ?>
                                <li>
                                    <span class="pm__rank-label" title="<?= $h($a['label']) ?>"><?= $h($a['label']) ?></span>
                                    <span class="pm__rank-value"><?= $h($dur((int) $a['seconds'])) ?><em><?= (int) $a['pilots'] ?> pilote(s)</em></span>
                                    <span class="pm__track pm__track--air"><span style="width: <?= max(2, (int) round(100 * $a['seconds'] / $maxAir)) ?>%"></span></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="pm__none">Aucune heure de vol relevée. Elles s’ajoutent quand un membre pilote un aéronef pendant une partie.</p>
                    <?php endif; ?>
                </section>
            </div>

            <section class="akhr__sheet pm__sheet" data-pm-table>
                <div class="pm__sheet-head">
                    <h2 class="pm__panel-title">Par membre<small>cliquez un en-tête pour trier</small></h2>
                    <label class="akhr__field pm__search">
                        <input type="search" data-pm-q placeholder="Rechercher un indicatif, un nom ou un rôle" aria-label="Rechercher un membre" autocomplete="off">
                    </label>
                    <p class="akhr__count" aria-live="polite"><span data-pm-count><?= count($rows) ?></span> membre(s)</p>
                </div>
                <div class="akhr__wrap">
                    <table class="akhr__table pm__table" data-pm-grid>
                        <thead>
                            <tr>
                                <?php foreach ($cols as $i => [$label, $type, $title]): ?>
                                    <th scope="col" data-sort-type="<?= $h($type) ?>"<?= $type === 'num' && $i !== 11 ? ' class="is-num"' : '' ?><?= $title !== '' ? ' title="' . $h($title) . '"' : '' ?><?= $i === $sortCol ? ' data-sort-initial="' . ($type === 'num' ? 'descending' : 'ascending') . '"' : '' ?>><?= $h($label) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php $g = is_array($r['garmin']) ? $r['garmin'] : null; ?>
                                <tr data-pm-name="<?= $h(mb_strtolower($r['callsign'] . ' ' . $r['display_name'] . ' ' . $r['top_role'])) ?>">
                                    <th scope="row" data-sort="<?= $h(mb_strtolower((string) $r['name'])) ?>">
                                        <a class="pm__member" href="<?= $h(url('personnel/' . (int) $r['user_id'])) ?>"><?= $h($r['name']) ?></a><?php if ($r['callsign'] !== '' && $r['display_name'] !== ''): ?> <span class="pm__alias"><?= $h($r['display_name']) ?></span><?php endif; ?>
                                    </th>
                                    <td data-sort="<?= $h(mb_strtolower((string) $r['top_role'])) ?>"><?= $r['top_role'] !== '' ? '<span class="pm__role" title="' . $h($r['top_role']) . '">' . $h($r['top_role']) . '</span>' : $dash ?></td>
                                    <td class="is-num pm__play" data-sort="<?= (int) $r['play'] ?>">
                                        <?php if ($r['play'] > 0): ?>
                                            <span class="pm__mini"><span style="width: <?= max(3, (int) round(100 * $r['play'] / $maxPlay)) ?>%"></span></span><strong><?= $h($dur($r['play'])) ?></strong>
                                        <?php else: ?><?= $dash ?><?php endif; ?>
                                    </td>
                                    <td class="is-num" data-sort="<?= (int) $r['screen'] ?>"><?= $r['screen'] > 0 ? $h($dur($r['screen'])) : $dash ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['total_m'] ?>"><?= $r['total_m'] > 0 ? $h($dist($r['total_m'])) : $dash ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['foot_m'] ?>"><?= $r['foot_m'] > 0 ? $h($dist($r['foot_m'])) : $dash ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['steps'] ?>"><?= $r['steps'] > 0 ? $h($num((int) $r['steps'])) : $dash ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['pilot_s'] ?>"><?= $r['pilot_s'] > 0 ? $h($dur($r['pilot_s'])) : $dash ?></td>
                                    <td class="is-num" data-sort="<?= $g !== null ? (int) $g['steps'] : -1 ?>"><?= $g !== null ? $h($num((int) $g['steps'])) : $dash ?></td>
                                    <td class="is-num" data-sort="<?= $g !== null ? (int) $g['body_battery'] : -1 ?>">
                                        <?php if ($g !== null): ?>
                                            <span class="pm__bb" title="Body Battery">BB <?= (int) $g['body_battery'] ?></span><?php if ((int) $g['avg_sleep_s'] > 0): ?> <span class="pm__alias" title="Sommeil moyen">· <?= $h($dur((int) $g['avg_sleep_s'])) ?></span><?php endif; ?>
                                        <?php else: ?><?= $dash ?><?php endif; ?>
                                    </td>
                                    <td class="is-num" data-sort="<?= (int) $r['sessions'] ?>"><?= $r['sessions'] > 0 ? (int) $r['sessions'] : $dash ?></td>
                                    <td data-sort="<?= $h($r['last_day']) ?>"><?= $r['last_day'] !== '' ? $h(date('d/m/Y', (int) strtotime($r['last_day']))) : $dash ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">Communauté</th>
                                <td></td>
                                <td class="is-num"><?= $t['play'] > 0 ? $h($dur($t['play'])) : $dash ?></td>
                                <td class="is-num"><?= $t['screen'] > 0 ? $h($dur($t['screen'])) : $dash ?></td>
                                <td class="is-num"><?= $t['total_m'] > 0 ? $h($dist($t['total_m'])) : $dash ?></td>
                                <td class="is-num"><?= $t['foot_m'] > 0 ? $h($dist($t['foot_m'])) : $dash ?></td>
                                <td class="is-num"><?= $t['steps'] > 0 ? $h($num($t['steps'])) : $dash ?></td>
                                <td class="is-num"><?= $t['pilot_s'] > 0 ? $h($dur($t['pilot_s'])) : $dash ?></td>
                                <td class="is-num"><?= $t['watch_steps'] > 0 ? $h($num($t['watch_steps'])) : $dash ?></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="akhr__empty-filter aksq__muted" data-pm-none hidden>Aucun membre ne correspond à cette recherche.</p>
            </section>
        <?php endif; ?>
    </div>
</div>
<script defer src="<?= $h(asset_url('assets/js/back-office-player-metrics.js')) ?>"></script>
