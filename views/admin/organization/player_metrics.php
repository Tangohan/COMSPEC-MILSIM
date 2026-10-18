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
    ['label' => 'À pied', 'm' => $t['foot_m']],
    ['label' => 'Véhicule terrestre', 'm' => $t['land_m']],
    ['label' => 'Bateau', 'm' => $t['sea_m']],
    ['label' => 'Vol', 'm' => $t['air_m']],
    ['label' => 'Chute HALO', 'm' => $t['halo_m']],
];
$maxMode = max(1, ...array_column($modes, 'm'));
$maxRole = $roles !== [] ? max(1, ...array_column($roles, 'seconds')) : 1;
$maxAir = $aircraft !== [] ? max(1, ...array_column($aircraft, 'seconds')) : 1;
$missing = array_keys(array_filter($sources, static fn (bool $ok): bool => !$ok));
$sourceLabels = ['screen' => 'temps d’écran', 'distance' => 'distances', 'garmin' => 'montre Garmin'];
?>
<div class="aksq pm">
    <div class="aksq__frame">
        <header class="aksq__hero">
            <p class="aksq__kicker">Personnel · Métriques joueurs</p>
            <h1 class="aksq__title">Métriques joueurs</h1>
            <p class="aksq__lead">Tout ce que le jeu remonte sur vos membres, au même endroit : temps de jeu et temps d’écran du téléphone ATAK, distances parcourues (à pied, en véhicule, en vol, en HALO), heures de vol et montre Garmin. Chaque ligne ouvre la fiche du membre, où se trouvent les cartes détaillées.</p>
            <div class="aksq__kpis">
                <div class="aksq__kpi"><span>Membres actifs</span><strong><?= (int) $t['members'] ?></strong></div>
                <div class="aksq__kpi"><span>Temps de jeu</span><strong><?= $h($dur($t['play'])) ?></strong></div>
                <div class="aksq__kpi"><span>Temps d’écran</span><strong><?= $h($dur($t['screen'])) ?></strong></div>
                <div class="aksq__kpi"><span>Distance parcourue</span><strong><?= $h($dist($t['total_m'])) ?></strong></div>
                <div class="aksq__kpi"><span>Pas en jeu</span><strong><?= $h(number_format($t['steps'], 0, ',', ' ')) ?></strong></div>
                <div class="aksq__kpi"><span>Montres Garmin</span><strong><?= (int) $t['watches'] ?></strong></div>
            </div>
            <nav class="aksq__periods" aria-label="Période">
                <?php foreach ($periods as $n => $label): ?>
                    <a href="<?= $h($qs(['jours' => $n])) ?>" class="aksq__period<?= (int) $n === $days ? ' is-active' : '' ?>"<?= (int) $n === $days ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
                <a href="<?= $h(url('back-office/atak/temps-ecran' . ($days !== 30 ? '?jours=' . $days : ''))) ?>" class="aksq__link">Temps d’écran et rôles</a>
                <a href="<?= $h(url('back-office/atak/heures' . ($days !== 30 ? '?jours=' . $days : ''))) ?>" class="aksq__link">Heures de jeu (tableur)</a>
                <a href="<?= $h($qs(['format' => 'csv'])) ?>" class="aksq__link">Exporter en CSV</a>
            </nav>
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
            <div class="pm__grid">
                <section class="aksq__session">
                    <h2 class="aksq__session-title">Distances par mode<small><?= $h($days > 0 ? $periods[$days] ?? '' : 'depuis le début') ?></small></h2>
                    <div class="aksq__bars">
                        <?php foreach ($modes as $mode): ?>
                            <div class="aksq__bar">
                                <span class="aksq__bar-label"><?= $h($mode['label']) ?></span>
                                <span class="aksq__bar-track"><span style="width: <?= $mode['m'] > 0 ? max(2, (int) round(100 * $mode['m'] / $maxMode)) : 0 ?>%"></span></span>
                                <span class="aksq__bar-value"><?= $h($dist((int) $mode['m'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($t['halo_jumps'] > 0): ?><p class="aksq__muted pm__note"><?= (int) $t['halo_jumps'] ?> saut(s) HALO sur la période.</p><?php endif; ?>
                    </div>
                </section>
                <section class="aksq__session">
                    <h2 class="aksq__session-title">Heures de vol par appareil<small>cumul depuis le début</small></h2>
                    <div class="aksq__bars">
                        <?php foreach (array_slice($aircraft, 0, 8) as $a): ?>
                            <div class="aksq__bar">
                                <span class="aksq__bar-label"><?= $h($a['label']) ?></span>
                                <span class="aksq__bar-track"><span style="width: <?= max(2, (int) round(100 * $a['seconds'] / $maxAir)) ?>%"></span></span>
                                <span class="aksq__bar-value"><?= $h($dur((int) $a['seconds'])) ?> · <?= (int) $a['pilots'] ?> pilote(s)</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($aircraft === []): ?><p class="aksq__muted pm__note">Aucune heure de vol relevée.</p><?php endif; ?>
                    </div>
                </section>
                <?php if ($roles !== []): ?>
                    <section class="aksq__session">
                        <h2 class="aksq__session-title">Temps de jeu par rôle</h2>
                        <div class="aksq__bars">
                            <?php foreach (array_slice($roles, 0, 8) as $r): ?>
                                <div class="aksq__bar">
                                    <span class="aksq__bar-label"><?= $h($r['label']) ?></span>
                                    <span class="aksq__bar-track"><span style="width: <?= max(2, (int) round(100 * $r['seconds'] / $maxRole)) ?>%"></span></span>
                                    <span class="aksq__bar-value"><?= $h($dur((int) $r['seconds'])) ?> · <?= (int) $r['members'] ?> membre(s)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
                <?php if ($t['watches'] > 0): ?>
                    <section class="aksq__session">
                        <h2 class="aksq__session-title">Montre Garmin en jeu</h2>
                        <div class="aksq__kpis pm__watch">
                            <div class="aksq__kpi"><span>Montres reliées</span><strong><?= (int) $t['watches'] ?></strong></div>
                            <div class="aksq__kpi"><span>Pas mesurés</span><strong><?= $h(number_format($t['watch_steps'], 0, ',', ' ')) ?></strong></div>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <section class="aksq__session" data-pm-table>
                <h2 class="aksq__session-title">Par membre</h2>
                <div class="aksq__filters" role="search">
                    <label class="aksq__filter aksq__filter--grow">
                        <span>Rechercher</span>
                        <input type="search" data-pm-q placeholder="Indicatif ou nom" autocomplete="off">
                    </label>
                    <form method="get" action="<?= $h(url('back-office/metriques-joueurs')) ?>" class="aksq__filter">
                        <span>Trier par</span>
                        <?php if ($days !== 30): ?><input type="hidden" name="jours" value="<?= (int) $days ?>"><?php endif; ?>
                        <select name="tri" data-pm-sort>
                            <?php foreach (PlayerMetrics::SORTS as $key => $label): ?>
                                <option value="<?= $h($key) ?>"<?= $key === $sort ? ' selected' : '' ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="aksq__link">Trier</button></noscript>
                    </form>
                    <span class="aksq__muted" data-pm-count aria-live="polite"><?= count($rows) ?> membre(s)</span>
                </div>
                <div class="aksq__table-wrap">
                    <table class="aksq__table pm__table">
                        <thead>
                            <tr>
                                <th scope="col">Membre</th>
                                <th scope="col">Temps de jeu</th>
                                <th scope="col">Écran</th>
                                <th scope="col">Distance</th>
                                <th scope="col">À pied</th>
                                <th scope="col">Vol (cumul)</th>
                                <th scope="col">Montre Garmin</th>
                                <th scope="col">Dernière activité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php $g = is_array($r['garmin']) ? $r['garmin'] : null; ?>
                                <tr data-pm-name="<?= $h(mb_strtolower($r['callsign'] . ' ' . $r['display_name'])) ?>">
                                    <th scope="row">
                                        <a class="pm__member" href="<?= $h(url('personnel/' . (int) $r['user_id'])) ?>">
                                            <strong><?= $h($r['name']) ?></strong>
                                            <?php if ($r['callsign'] !== '' && $r['display_name'] !== ''): ?><span class="aksq__muted"><?= $h($r['display_name']) ?></span><?php endif; ?>
                                        </a>
                                        <?php if ($r['top_role'] !== ''): ?><span class="aksq__chip aksq__chip--role"><?= $h($r['top_role']) ?></span><?php endif; ?>
                                    </th>
                                    <td><?= $r['play'] > 0 ? $h($dur($r['play'])) : '<span class="aksq__muted">—</span>' ?></td>
                                    <td><?= $r['screen'] > 0 ? $h($dur($r['screen'])) : '<span class="aksq__muted">—</span>' ?></td>
                                    <td>
                                        <?php if ($r['total_m'] > 0): ?>
                                            <?= $h($dist($r['total_m'])) ?>
                                            <span class="aksq__muted pm__sub"><?= (int) $r['sessions'] ?> session(s)</span>
                                        <?php else: ?><span class="aksq__muted">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['foot_m'] > 0): ?>
                                            <?= $h($dist($r['foot_m'])) ?>
                                            <span class="aksq__muted pm__sub"><?= $h(number_format($r['steps'], 0, ',', ' ')) ?> pas</span>
                                        <?php else: ?><span class="aksq__muted">—</span><?php endif; ?>
                                    </td>
                                    <td><?= $r['pilot_s'] > 0 ? $h($dur($r['pilot_s'])) : '<span class="aksq__muted">—</span>' ?></td>
                                    <td>
                                        <?php if ($g !== null): ?>
                                            <?= $h(number_format((int) $g['steps'], 0, ',', ' ')) ?> pas
                                            <span class="aksq__muted pm__sub">Body Battery <?= (int) $g['body_battery'] ?></span>
                                            <?php if ((int) $g['avg_sleep_s'] > 0): ?><span class="aksq__muted pm__sub">Sommeil moyen <?= $h($dur((int) $g['avg_sleep_s'])) ?></span><?php endif; ?>
                                        <?php else: ?><span class="aksq__muted">—</span><?php endif; ?>
                                    </td>
                                    <td class="aksq__muted"><?= $h($r['last_day'] !== '' ? date('d/m/Y', (int) strtotime($r['last_day'])) : '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="aksq__empty-filter aksq__muted" data-pm-none hidden>Aucun membre ne correspond à cette recherche.</p>
            </section>
        <?php endif; ?>
    </div>
</div>
<script defer src="<?= $h(asset_url('assets/js/back-office-player-metrics.js')) ?>"></script>
