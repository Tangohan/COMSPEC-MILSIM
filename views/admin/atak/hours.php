<?php
declare(strict_types=1);

use App\Support\AtakRoleDetection;
use App\Support\PlaytimeSheet;

/** @var bool $hoursReady */
/** @var bool $hoursSourceTracked */
/** @var list<array<string, mixed>> $hoursRows */
/** @var list<array<string, mixed>> $hoursPlayers */
/** @var string $hoursView */
/** @var int $hoursDays */
/** @var string $hoursFrom */
/** @var string $hoursTo */
/** @var int $hoursMember */
/** @var list<array{user_id: int, name: string}> $hoursMembers */
/** @var array<string, string> $hoursFilters */
/** @var array<int, string> $hoursPeriods */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($hoursRows ?? null) ? $hoursRows : [];
$players = is_array($hoursPlayers ?? null) ? $hoursPlayers : [];
$view = ($hoursView ?? 'detail') === 'joueurs' ? 'joueurs' : 'detail';
$days = (int) ($hoursDays ?? 30);
$from = (string) ($hoursFrom ?? '');
$to = (string) ($hoursTo ?? '');
$member = (int) ($hoursMember ?? 0);
$members = is_array($hoursMembers ?? null) ? $hoursMembers : [];
$filters = is_array($hoursFilters ?? null) ? $hoursFilters : [];
$periods = is_array($hoursPeriods ?? null) ? $hoursPeriods : [];
$ready = !empty($hoursReady);
$sourceTracked = !empty($hoursSourceTracked);

$link = static function (array $over) use ($filters, $view): string {
    $q = array_filter(array_merge($filters, ['vue' => $view], $over), static fn ($v): bool => $v !== null && $v !== '');
    if (($q['vue'] ?? '') === 'detail') {
        unset($q['vue']);
    }

    return url('back-office/atak/heures' . ($q !== [] ? '?' . http_build_query($q) : ''));
};
$who = static function (array $r): string {
    $call = (string) $r['callsign'];
    $name = (string) $r['display_name'];

    return $name !== '' ? $name : ($call !== '' ? $call : 'Membre #' . (int) $r['user_id']);
};
$pct = static fn (?float $v): string => $v === null ? '—' : str_replace('.', ',', (string) $v) . ' %';
$date = static fn (string $d): string => PlaytimeSheet::frenchDate($d) !== '' ? PlaytimeSheet::frenchDate($d) : '—';

$totalPlay = 0;
$totalRole = 0;
foreach ($rows as $r) {
    if ($r['kind'] === 'play' && $r['key'] === 'TOTAL') {
        $totalPlay += (int) $r['seconds'];
    } elseif ($r['kind'] === 'role') {
        $totalRole += (int) $r['seconds'];
    }
}
$typeOptions = [];
$detectionOptions = [];
$sourceOptions = [];
foreach ($rows as $r) {
    $typeOptions[AtakRoleDetection::kindLabel($r['kind'], $r['key'])] = true;
    if ($r['detection'] !== '') {
        $detectionOptions[AtakRoleDetection::label($r['detection'])] = true;
    }
    $sourceOptions[AtakRoleDetection::sourceLabel((int) $r['source_mask'])] = true;
}
$periodLabel = $from !== '' || $to !== ''
    ? ($from !== '' ? 'du ' . PlaytimeSheet::frenchDate($from) : 'jusqu’au') . ($to !== '' ? ($from !== '' ? ' au ' : ' ') . PlaytimeSheet::frenchDate($to) : ' à aujourd’hui')
    : (string) ($periods[$days] ?? '');
?>
<div class="aksq akhr" data-hours-sheet>
    <div class="aksq__frame">
        <header class="aksq__hero">
            <p class="aksq__kicker">ATAK · Heures de jeu</p>
            <h1 class="aksq__title">Heures de jeu, en tableur</h1>
            <p class="aksq__lead">Une ligne par joueur et par élément : temps de jeu en mission, temps par rôle, temps d’écran et apps du téléphone. La colonne <strong>Détection</strong> dit comment le rôle a été reconnu (poste d’équipage, rôle d’équipe choisi dans le téléphone, spécialité Arma ou nom du slot), <strong>Remonté par</strong> qui l’a compté (serveur Arma, téléphone ATAK ou les deux). Cliquez un en-tête pour trier.</p>
            <div class="aksq__kpis">
                <div class="aksq__kpi"><span>Joueurs</span><strong><?= count($players) ?></strong></div>
                <div class="aksq__kpi"><span>Temps de jeu</span><strong><?= $h(PlaytimeSheet::clock($totalPlay)) ?></strong></div>
                <div class="aksq__kpi"><span>Temps par rôle</span><strong><?= $h(PlaytimeSheet::clock($totalRole)) ?></strong></div>
                <div class="aksq__kpi"><span>Période</span><strong class="akhr__kpi-text"><?= $h($periodLabel) ?></strong></div>
            </div>
            <nav class="aksq__periods" aria-label="Période">
                <?php foreach ($periods as $n => $label): ?>
                    <?php $active = $from === '' && $to === '' && (int) $n === $days; ?>
                    <a href="<?= $h($link(['jours' => (string) $n, 'du' => null, 'au' => null])) ?>" class="aksq__period<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
                <a href="<?= $h(url('back-office/atak/temps-ecran')) ?>" class="aksq__link">Temps d’écran et rôles</a>
                <a href="<?= $h(url('back-office/atak/escouades')) ?>" class="aksq__link">Escouades en jeu</a>
            </nav>
            <form method="get" action="<?= $h(url('back-office/atak/heures')) ?>" class="akhr__filters">
                <?php if ($view === 'joueurs'): ?><input type="hidden" name="vue" value="joueurs"><?php endif; ?>
                <label class="akhr__field">
                    <span>Du</span>
                    <input type="date" name="du" value="<?= $h($from) ?>">
                </label>
                <label class="akhr__field">
                    <span>Au</span>
                    <input type="date" name="au" value="<?= $h($to) ?>">
                </label>
                <label class="akhr__field akhr__field--wide">
                    <span>Membre</span>
                    <select name="membre">
                        <option value="">Tous les membres</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= (int) $m['user_id'] ?>"<?= (int) $m['user_id'] === $member ? ' selected' : '' ?>><?= $h($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($from === '' && $to === ''): ?><input type="hidden" name="jours" value="<?= $days ?>"><?php endif; ?>
                <button type="submit" class="akhr__btn">Filtrer</button>
                <?php if ($filters !== ['jours' => '30'] && $filters !== []): ?>
                    <a href="<?= $h(url('back-office/atak/heures' . ($view === 'joueurs' ? '?vue=joueurs' : ''))) ?>" class="akhr__reset">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </header>

        <?php if (!$ready): ?>
            <p class="aksq__notice">La table du temps de jeu n’est pas encore créée : un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
        <?php elseif ($rows === []): ?>
            <div class="aksq__empty">
                <p class="aksq__empty-title">Aucune heure sur cette période.</p>
                <p>Le serveur Arma envoie le temps de jeu et les rôles de tous les joueurs pendant la partie ; les téléphones ATAK ajoutent le temps d’écran. Élargissez la période ou retirez le filtre de membre.</p>
            </div>
        <?php else: ?>
            <section class="akhr__sheet">
                <div class="akhr__bar">
                    <nav class="akhr__tabs" aria-label="Vue">
                        <a href="<?= $h($link(['vue' => 'detail'])) ?>" class="akhr__tab<?= $view === 'detail' ? ' is-active' : '' ?>"<?= $view === 'detail' ? ' aria-current="page"' : '' ?>>Détail (<?= count($rows) ?>)</a>
                        <a href="<?= $h($link(['vue' => 'joueurs'])) ?>" class="akhr__tab<?= $view === 'joueurs' ? ' is-active' : '' ?>"<?= $view === 'joueurs' ? ' aria-current="page"' : '' ?>>Par joueur (<?= count($players) ?>)</a>
                    </nav>
                    <a href="<?= $h($link(['format' => 'csv'])) ?>" class="akhr__btn akhr__btn--export" download>Exporter en CSV</a>
                </div>
                <div class="akhr__toolbar" role="search">
                    <label class="akhr__field akhr__field--grow">
                        <span>Rechercher</span>
                        <input type="search" placeholder="Joueur, indicatif, rôle…" data-hours-filter="q" autocomplete="off">
                    </label>
                    <?php if ($view === 'detail'): ?>
                        <?php foreach (['type' => ['Type', $typeOptions], 'detection' => ['Détection', $detectionOptions], 'source' => ['Remonté par', $sourceOptions]] as $fKey => [$fLabel, $fOptions]): ?>
                            <label class="akhr__field">
                                <span><?= $h($fLabel) ?></span>
                                <select data-hours-filter="<?= $h($fKey) ?>">
                                    <option value="">Tous</option>
                                    <?php foreach (array_keys($fOptions) as $opt): ?>
                                        <option value="<?= $h($opt) ?>"><?= $h($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <p class="akhr__count" aria-live="polite"><span data-hours-count><?= $view === 'detail' ? count($rows) : count($players) ?></span> ligne(s)</p>
                </div>

                <div class="akhr__wrap">
                    <?php if ($view === 'detail'): ?>
                    <table class="akhr__table" data-hours-table>
                        <thead>
                            <tr>
                                <th scope="col" data-sort-type="text">Joueur</th>
                                <th scope="col" data-sort-type="text">Indicatif</th>
                                <th scope="col" data-sort-type="text">Type</th>
                                <th scope="col" data-sort-type="text">Rôle / élément</th>
                                <th scope="col" data-sort-type="text">Détection</th>
                                <th scope="col" data-sort-type="text">Remonté par</th>
                                <th scope="col" data-sort-type="num" class="is-num">Heures</th>
                                <th scope="col" data-sort-type="num" class="is-num">Heures déc.</th>
                                <th scope="col" data-sort-type="num" class="is-num" title="Temps du rôle rapporté au temps de jeu du joueur sur la période">Part du jeu</th>
                                <th scope="col" data-sort-type="num" class="is-num" title="Parties distinctes (clé de mission ; une par jour pour les remontées plus anciennes)">Sessions</th>
                                <th scope="col" data-sort-type="num" class="is-num">Jours</th>
                                <th scope="col" data-sort-type="num">Première</th>
                                <th scope="col" data-sort-type="num">Dernière</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $type = AtakRoleDetection::kindLabel($r['kind'], $r['key']);
                                $det = $r['detection'] !== '' ? AtakRoleDetection::label($r['detection']) : '';
                                $src = AtakRoleDetection::sourceLabel((int) $r['source_mask']);
                                $name = $who($r);
                                ?>
                                <tr data-type="<?= $h($type) ?>" data-detection="<?= $h($det) ?>" data-source="<?= $h($src) ?>" data-sum-seconds="<?= (int) $r['seconds'] ?>"
                                    data-text="<?= $h(mb_strtolower($name . ' ' . $r['callsign'] . ' ' . $r['label'] . ' ' . $r['key'])) ?>">
                                    <th scope="row" data-sort="<?= $h(mb_strtolower($name)) ?>"><a href="<?= $h(url('personnel/' . (int) $r['user_id']) . '#temps-par-role') ?>"><?= $h($name) ?></a></th>
                                    <td data-sort="<?= $h(mb_strtolower((string) $r['callsign'])) ?>"><?= $h($r['callsign'] !== '' ? $r['callsign'] : '—') ?></td>
                                    <td><span class="akhr__type akhr__type--<?= $h($r['kind']) ?>"><?= $h($type) ?></span></td>
                                    <td data-sort="<?= $h(mb_strtolower((string) $r['label'])) ?>"><?= $h($r['label']) ?><?php if ($r['kind'] === 'role' && $r['label'] !== $r['key'] && !str_starts_with((string) $r['key'], 'SLOT:')): ?> <span class="aksq__muted"><?= $h($r['key']) ?></span><?php endif; ?></td>
                                    <td<?= $det !== '' ? ' title="' . $h(AtakRoleDetection::hint($r['detection'])) . '"' : '' ?>><?= $det !== '' ? '<span class="akhr__det akhr__det--' . $h($r['detection']) . '">' . $h($det) . '</span>' : '<span class="aksq__muted">—</span>' ?></td>
                                    <td class="<?= (int) $r['source_mask'] === 0 ? 'aksq__muted' : '' ?>"><?= $h($src) ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['seconds'] ?>"><?= $h(PlaytimeSheet::clock((int) $r['seconds'])) ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['seconds'] ?>"><?= $h(PlaytimeSheet::decimal((int) $r['seconds'])) ?></td>
                                    <td class="is-num" data-sort="<?= $r['share'] === null ? -1 : $h($r['share']) ?>"><?= $h($pct($r['share'])) ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['sessions'] ?>"><?= (int) $r['sessions'] ?></td>
                                    <td class="is-num" data-sort="<?= (int) $r['days'] ?>"><?= (int) $r['days'] ?></td>
                                    <td data-sort="<?= $h($r['first_day']) ?>"><?= $h($date((string) $r['first_day'])) ?></td>
                                    <td data-sort="<?= $h($r['last_day']) ?>"><?= $h($date((string) $r['last_day'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">Total affiché</th>
                                <td colspan="5" class="aksq__muted">Somme des lignes visibles (tous types confondus : filtrez par type pour un total utile).</td>
                                <td class="is-num" data-total="seconds" data-total-format="clock"></td>
                                <td class="is-num" data-total="seconds" data-total-format="decimal"></td>
                                <td colspan="5"></td>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                    <table class="akhr__table" data-hours-table>
                        <thead>
                            <tr>
                                <th scope="col" data-sort-type="text">Joueur</th>
                                <th scope="col" data-sort-type="text">Indicatif</th>
                                <th scope="col" data-sort-type="num" class="is-num" title="Temps de jeu compté sur la période choisie">Temps de jeu</th>
                                <th scope="col" data-sort-type="num" class="is-num" title="Cumul de toutes les sessions depuis le début du suivi">Cumul total</th>
                                <th scope="col" data-sort-type="num" class="is-num">Serveur</th>
                                <th scope="col" data-sort-type="num" class="is-num">Zeus</th>
                                <th scope="col" data-sort-type="num" class="is-num">Éditeur</th>
                                <th scope="col" data-sort-type="num" class="is-num" title="Temps remonté avant le détail serveur / Zeus / éditeur">Non ventilé</th>
                                <th scope="col" data-sort-type="text">Rôle principal</th>
                                <th scope="col" data-sort-type="num" class="is-num">Part</th>
                                <th scope="col" data-sort-type="num" class="is-num">Rôles</th>
                                <th scope="col" data-sort-type="num" class="is-num">Sessions</th>
                                <th scope="col" data-sort-type="num">Dernière partie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $p): ?>
                                <?php
                                $name = $who($p);
                                $opt = static fn (?int $s): string => $s === null ? '—' : PlaytimeSheet::clock($s);
                                ?>
                                <tr data-text="<?= $h(mb_strtolower($name . ' ' . $p['callsign'] . ' ' . $p['top_role'])) ?>"
                                    data-sum-play="<?= (int) $p['play'] ?>" data-sum-total="<?= (int) ($p['total'] ?? 0) ?>" data-sum-server="<?= (int) ($p['server'] ?? 0) ?>"
                                    data-sum-zeus="<?= (int) ($p['zeus'] ?? 0) ?>" data-sum-editor="<?= (int) ($p['editor'] ?? 0) ?>" data-sum-unsplit="<?= (int) ($p['unsplit'] ?? 0) ?>" data-sum-sessions="<?= (int) $p['sessions'] ?>">
                                    <th scope="row" data-sort="<?= $h(mb_strtolower($name)) ?>"><a href="<?= $h(url('personnel/' . (int) $p['user_id']) . '#temps-par-role') ?>"><?= $h($name) ?></a></th>
                                    <td data-sort="<?= $h(mb_strtolower((string) $p['callsign'])) ?>"><?= $h($p['callsign'] !== '' ? $p['callsign'] : '—') ?></td>
                                    <td class="is-num" data-sort="<?= (int) $p['play'] ?>"><strong><?= $h(PlaytimeSheet::clock((int) $p['play'])) ?></strong></td>
                                    <td class="is-num" data-sort="<?= (int) ($p['total'] ?? -1) ?>"><?= $h($opt($p['total'])) ?></td>
                                    <td class="is-num" data-sort="<?= (int) ($p['server'] ?? -1) ?>"><?= $h($opt($p['server'])) ?></td>
                                    <td class="is-num" data-sort="<?= (int) ($p['zeus'] ?? -1) ?>"><?= $h($opt($p['zeus'])) ?></td>
                                    <td class="is-num" data-sort="<?= (int) ($p['editor'] ?? -1) ?>"><?= $h($opt($p['editor'])) ?></td>
                                    <td class="is-num<?= (int) ($p['unsplit'] ?? 0) > 0 ? '' : ' aksq__muted' ?>" data-sort="<?= (int) ($p['unsplit'] ?? -1) ?>"><?= $h($opt($p['unsplit'])) ?></td>
                                    <td data-sort="<?= $h(mb_strtolower((string) $p['top_role'])) ?>"><?= $h($p['top_role'] !== '' ? $p['top_role'] : '—') ?></td>
                                    <td class="is-num" data-sort="<?= $p['top_role_share'] === null ? -1 : $h($p['top_role_share']) ?>"><?= $h($pct($p['top_role_share'])) ?></td>
                                    <td class="is-num" data-sort="<?= (int) $p['roles'] ?>"><?= (int) $p['roles'] ?></td>
                                    <td class="is-num" data-sort="<?= (int) $p['sessions'] ?>"><?= (int) $p['sessions'] ?></td>
                                    <td data-sort="<?= $h($p['last_day']) ?>"><?= $h($date((string) $p['last_day'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">Total affiché</th>
                                <td></td>
                                <td class="is-num" data-total="play" data-total-format="clock"></td>
                                <td class="is-num" data-total="total" data-total-format="clock"></td>
                                <td class="is-num" data-total="server" data-total-format="clock"></td>
                                <td class="is-num" data-total="zeus" data-total-format="clock"></td>
                                <td class="is-num" data-total="editor" data-total-format="clock"></td>
                                <td class="is-num" data-total="unsplit" data-total-format="clock"></td>
                                <td colspan="3"></td>
                                <td class="is-num" data-total="sessions" data-total-format="int"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <?php endif; ?>
                </div>
                <p class="akhr__empty-filter" data-hours-empty hidden>Aucune ligne ne correspond aux filtres.</p>
            </section>

            <section class="akhr__legend" aria-label="Lecture du tableau">
                <h2 class="aksq__session-title">Lecture</h2>
                <dl>
                    <?php foreach ([AtakRoleDetection::CREW, AtakRoleDetection::TEAM, AtakRoleDetection::SPECIALTY, AtakRoleDetection::SLOT, AtakRoleDetection::MANUAL] as $cat): ?>
                        <div><dt><span class="akhr__det akhr__det--<?= $h($cat) ?>"><?= $h(AtakRoleDetection::label($cat)) ?></span></dt><dd><?= $h(AtakRoleDetection::hint($cat)) ?></dd></div>
                    <?php endforeach; ?>
                    <div><dt><strong>Remonté par</strong></dt><dd>Serveur Arma : compté pour tous les joueurs pendant la partie, même téléphone éteint. Téléphone ATAK : envoyé par le téléphone du joueur (seule source du temps d’écran). « Non précisé » : temps enregistré avant ce suivi<?= $sourceTracked ? '' : ' (la colonne arrive avec la prochaine migration ou la prochaine remontée)' ?>, ou déplacé à la main.</dd></div>
                    <div><dt><strong>Non ventilé</strong></dt><dd>Partie du cumul remontée avant le détail serveur / Zeus / éditeur : elle compte dans le total mais n’est rattachée à aucun contexte.</dd></div>
                </dl>
            </section>
        <?php endif; ?>
    </div>
</div>
<script defer src="<?= $h(asset_url('assets/js/back-office-atak-hours.js')) ?>"></script>
