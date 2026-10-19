<?php
declare(strict_types=1);
/**
 * Accueil public — aperçu de la plateforme avec des données entièrement fictives (unité « TF ORION »).
 * Six écrans reproduits en HTML/CSS : ORBAT, personnel, commandement, formation, manœuvre, JNET / ATAK.
 * Aucun appel base : rien ici ne provient d’une vraie communauté ni d’un vrai joueur.
 *
 * Attend $e (échappement) et __() (traductions home.demo_*).
 */
$d = static fn (string $k): string => __('home.demo_' . $k);
$isEn = function_exists('locale') && locale() === 'en';
$t = static fn (string $fr, string $en): string => $isEn ? $en : $fr;
$tabs = [
    'orbat' => ['path' => 'orbat', 'icon' => '<path d="M12 3v5M5 14v-3h14v3M12 8v3"/><rect x="9" y="3" width="6" height="5" rx="1"/><rect x="2" y="14" width="6" height="5" rx="1"/><rect x="16" y="14" width="6" height="5" rx="1"/>'],
    'personnel' => ['path' => 'effectifs', 'icon' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.5a3 3 0 0 1 0 5.6M21 20c0-2.4-1.3-4.2-3.4-5"/>'],
    'command' => ['path' => 'commandement', 'icon' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>'],
    'training' => ['path' => 'formations', 'icon' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2.5 9 2.5 12 0v-5"/>'],
    'op' => ['path' => 'manoeuvres/night-heron', 'icon' => '<path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2z"/><path d="M9 4v14M15 6v14"/>'],
    'jnet' => ['path' => 'jnet', 'icon' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>'],
];

// --- Données fictives -----------------------------------------------------------------------------
$orbat = [
    ['name' => $t('État-major', 'HQ'), 'code' => 'EM', 'lead' => 'Cne SIRIUS', 'a' => 5, 't' => 6, 'tone' => '#94a3b8',
        'subs' => ['S1 Personnel', $t('S2 Renseignement', 'S2 Intelligence'), $t('S3 Opérations', 'S3 Operations'), $t('S6 Transmissions', 'S6 Signals')]],
    ['name' => $t('1re Cie « Spectre »', '1st Coy “Spectre”'), 'code' => 'SP', 'lead' => 'Lt VEGA', 'a' => 21, 't' => 24, 'tone' => '#34d399',
        'subs' => [$t('Section 1 · Alpha / Bravo', '1st Platoon · Alpha / Bravo'), $t('Section 2 · Charlie / Delta', '2nd Platoon · Charlie / Delta'), $t('Commando « Ghost »', 'Commando “Ghost”')]],
    ['name' => $t('Appui feu', 'Fire support'), 'code' => 'AF', 'lead' => 'Adj LYNX', 'a' => 7, 't' => 9, 'tone' => '#f59e0b',
        'subs' => [$t('Tireurs d’élite', 'Snipers'), $t('Mortiers 81', '81 mm mortars'), 'JTAC']],
    ['name' => $t('Aviation « Corbeau »', 'Aviation “Raven”'), 'code' => 'CB', 'lead' => 'Cne HALO', 'a' => 5, 't' => 7, 'tone' => '#38bdf8',
        'subs' => [$t('Hélico manœuvre', 'Utility helicopters'), $t('Hélico d’attaque', 'Attack helicopters'), 'Drones']],
];
$people = [
    ['cs' => 'VIPER', 'mat' => 'OR-0142', 'grade' => 'Sgt', 'unit' => 'Alpha', 'role' => $t('Chef de groupe', 'Squad leader'), 'q' => ['CQB', 'TRANS'], 'p' => 94, 'last' => 'NIGHT HERON', 'st' => 'active'],
    ['cs' => 'NOMAD', 'mat' => 'OR-0157', 'grade' => 'Cpl', 'unit' => 'Alpha', 'role' => $t('Infirmier', 'Medic'), 'q' => ['MED', 'CQB'], 'p' => 88, 'last' => 'NIGHT HERON', 'st' => 'active'],
    ['cs' => 'RAVEN', 'mat' => 'OR-0163', 'grade' => '1cl', 'unit' => 'Bravo', 'role' => $t('Mitrailleur', 'Machine gunner'), 'q' => ['AR'], 'p' => 71, 'last' => 'IRON VEIL', 'st' => 'active'],
    ['cs' => 'ONYX', 'mat' => 'OR-0121', 'grade' => 'Sgt', 'unit' => $t('Tireurs', 'Snipers'), 'role' => $t('Tireur d’élite', 'Sniper'), 'q' => ['SNIPER', 'JTAC'], 'p' => 97, 'last' => 'NIGHT HERON', 'st' => 'active'],
    ['cs' => 'DRIFT', 'mat' => 'OR-0188', 'grade' => 'Sdt', 'unit' => 'Charlie', 'role' => 'Grenadier', 'q' => [], 'p' => 42, 'last' => 'IRON VEIL', 'st' => 'trial'],
    ['cs' => 'HALO', 'mat' => 'OR-0104', 'grade' => 'Cne', 'unit' => $t('Corbeau', 'Raven'), 'role' => $t('Pilote', 'Pilot'), 'q' => ['PILOTE', 'TRANS'], 'p' => 90, 'last' => 'NIGHT HERON', 'st' => 'active'],
    ['cs' => 'ECHO', 'mat' => 'OR-0132', 'grade' => 'Cpl', 'unit' => 'S6', 'role' => $t('Transmetteur', 'Signaller'), 'q' => ['TRANS'], 'p' => 63, 'last' => 'DUST SIGNAL', 'st' => 'leave'],
    ['cs' => 'TALON', 'mat' => 'OR-0176', 'grade' => '1cl', 'unit' => 'Delta', 'role' => $t('Éclaireur', 'Scout'), 'q' => ['CQB'], 'p' => 81, 'last' => 'NIGHT HERON', 'st' => 'active'],
];
$weeks = [62, 70, 66, 78, 74, 85, 81, 88];
$trainees = [
    ['cs' => 'DRIFT', 'mods' => [2, 2, 1, 0, 0, 0], 'pct' => 38, 'state' => 'progress'],
    ['cs' => 'TALON', 'mods' => [2, 2, 2, 2, 2, 1], 'pct' => 90, 'state' => 'eval'],
    ['cs' => 'RAVEN', 'mods' => [2, 2, 2, 2, 1, 0], 'pct' => 72, 'state' => 'progress'],
    ['cs' => 'NOMAD', 'mods' => [2, 2, 2, 2, 2, 2], 'pct' => 100, 'state' => 'done'],
];
$roster = [
    ['grp' => 'Alpha', 'slots' => [['CDG', 'VIPER'], [$t('Infirmier', 'Medic'), 'NOMAD'], ['AR', 'RAVEN'], ['Grenadier', '']]],
    ['grp' => 'Bravo', 'slots' => [['CDG', 'KESTREL'], [$t('Éclaireur', 'Scout'), 'TALON'], ['AT', 'BOLT'], [$t('Fusilier', 'Rifleman'), 'DRIFT']]],
    ['grp' => $t('Appui', 'Support'), 'slots' => [[$t('Tireur', 'Sniper'), 'ONYX'], [$t('Observateur', 'Spotter'), ''], ['JTAC', 'ORBIT'], [$t('Pilote', 'Pilot'), 'HALO']]],
];
$initials = static fn (string $s): string => mb_strtoupper(mb_substr($s, 0, 2));
$first = array_key_first($tabs);
?>
<section class="hd hi-block" id="apercu" aria-labelledby="hd-heading" data-hd>
    <div class="hi-wrap">
        <div class="hd-head">
            <div class="hi-section-head">
                <p class="hi-kicker text-emerald-300"><?= $e($d('kicker')) ?></p>
                <h2 id="hd-heading" class="hi-h2 whitespace-pre-line text-white"><?= $e($d('title')) ?></h2>
                <p class="hi-lead text-white/70"><?= $e($d('lead')) ?></p>
            </div>
            <p class="hd-fake"><span aria-hidden="true">◆</span> <?= $e($d('fake_note')) ?></p>
        </div>

        <div class="hd-stage">
            <div class="hd-tabs" role="tablist" aria-label="<?= $e($d('tabs_aria')) ?>" aria-orientation="vertical">
                <?php $i = 0; foreach ($tabs as $key => $tab): $i++; $on = $key === $first; ?>
                    <button type="button" class="hd-tab<?= $on ? ' is-active' : '' ?>" role="tab" id="hd-tab-<?= $e($key) ?>" aria-controls="hd-panel-<?= $e($key) ?>" aria-selected="<?= $on ? 'true' : 'false' ?>" tabindex="<?= $on ? '0' : '-1' ?>" data-hd-tab="<?= $e($key) ?>">
                        <span class="hd-tab__n"><?= sprintf('%02d', $i) ?></span>
                        <svg class="hd-tab__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $tab['icon'] ?></svg>
                        <span class="hd-tab__txt">
                            <span class="hd-tab__t"><?= $e($d('tab_' . $key)) ?></span>
                            <span class="hd-tab__d"><?= $e($d('tab_' . $key . '_d')) ?></span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="hd-window">
                <div class="hd-chrome" aria-hidden="true">
                    <span class="hd-dots"><i></i><i></i><i></i></span>
                    <span class="hd-url">athena.ttrd.fr/c/tf-orion/<b data-hd-path><?= $e($tabs[$first]['path']) ?></b></span>
                    <span class="hd-chrome__badge"><?= $e($d('badge')) ?></span>
                </div>
                <div class="hd-app">
                    <div class="hd-appbar" aria-hidden="true">
                        <span class="hd-appbar__unit"><span class="hd-emblem">OR</span>TF ORION</span>
                        <span class="hd-appbar__crumb" data-hd-crumb><?= $e($d('tab_' . $first)) ?></span>
                        <span class="hd-appbar__me"><i>V</i>Sgt VIPER</span>
                    </div>

                    <!-- ORBAT -->
                    <div class="hd-panel" role="tabpanel" id="hd-panel-orbat" aria-labelledby="hd-tab-orbat" data-hd-panel="orbat" data-hd-path="orbat">
                        <div class="hd-orbat">
                            <div class="hd-node hd-node--root">
                                <span class="hd-node__em" style="--t:#e2e8f0">OR</span>
                                <span><b>Task Force ORION</b><small><span><?= $e($d('orbat_cdt')) ?> · Cdt AURIGA</span><span class="hd-node__n">38/44</span></small></span>
                                <span class="hd-meter"><i style="width:86%"></i></span>
                            </div>
                            <ol class="hd-orbat__row">
                                <?php foreach ($orbat as $u): $pct = (int) round($u['a'] / $u['t'] * 100); ?>
                                    <li class="hd-branch">
                                        <div class="hd-node">
                                            <span class="hd-node__em" style="--t:<?= $e($u['tone']) ?>"><?= $e($u['code']) ?></span>
                                            <span><b><?= $e($u['name']) ?></b><small><span><?= $e($u['lead']) ?></span><span class="hd-node__n"><?= (int) $u['a'] ?>/<?= (int) $u['t'] ?></span></small></span>
                                            <span class="hd-meter"><i style="width:<?= $pct ?>%"></i></span>
                                        </div>
                                        <ul class="hd-subs">
                                            <?php foreach ($u['subs'] as $s): ?><li><?= $e($s) ?></li><?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        <p class="hd-foot"><span class="hd-dot hd-dot--ok"></span><?= $e($d('orbat_foot')) ?></p>
                    </div>

                    <!-- Personnel -->
                    <div class="hd-panel" role="tabpanel" id="hd-panel-personnel" aria-labelledby="hd-tab-personnel" data-hd-panel="personnel" data-hd-path="effectifs" hidden>
                        <div class="hd-toolbar">
                            <span class="hd-chip is-on"><?= $e($d('all')) ?> <b>44</b></span>
                            <span class="hd-chip"><?= $e($d('st_active')) ?> <b>38</b></span>
                            <span class="hd-chip"><?= $e($d('st_leave')) ?> <b>4</b></span>
                            <span class="hd-chip"><?= $e($d('st_trial')) ?> <b>2</b></span>
                            <span class="hd-search"><?= $e($d('search')) ?></span>
                        </div>
                        <div class="hd-tablewrap">
                            <table class="hd-table">
                                <thead>
                                    <tr><th><?= $e($d('col_op')) ?></th><th><?= $e($d('col_grade')) ?></th><th><?= $e($d('col_unit')) ?></th><th><?= $e($d('col_quals')) ?></th><th><?= $e($d('col_presence')) ?></th><th><?= $e($d('col_last')) ?></th><th><?= $e($d('col_status')) ?></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($people as $p): ?>
                                        <tr>
                                            <td><span class="hd-op"><i><?= $e($initials($p['cs'])) ?></i><span><b><?= $e($p['cs']) ?></b><small><?= $e($p['mat']) ?> · <?= $e($p['role']) ?></small></span></span></td>
                                            <td class="hd-mono"><?= $e($p['grade']) ?></td>
                                            <td><?= $e($p['unit']) ?></td>
                                            <td><?php if ($p['q'] === []): ?><span class="hd-muted">—</span><?php endif; ?><?php foreach ($p['q'] as $q): ?><span class="hd-q"><?= $e($q) ?></span><?php endforeach; ?></td>
                                            <td><span class="hd-bar<?= $p['p'] < 60 ? ' is-low' : '' ?>"><i style="width:<?= (int) $p['p'] ?>%"></i></span><span class="hd-mono hd-pct"><?= (int) $p['p'] ?> %</span></td>
                                            <td class="hd-mono hd-muted"><?= $e($p['last']) ?></td>
                                            <td><span class="hd-st hd-st--<?= $e($p['st']) ?>"><?= $e($d('st_' . $p['st'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Commandement -->
                    <div class="hd-panel" role="tabpanel" id="hd-panel-command" aria-labelledby="hd-tab-command" data-hd-panel="command" data-hd-path="commandement" hidden>
                        <div class="hd-kpis">
                            <div class="hd-kpi"><small><?= $e($d('kpi_strength')) ?></small><b>38<em>/44</em></b><span class="hd-delta is-up">+3 <?= $e($d('kpi_month')) ?></span></div>
                            <div class="hd-kpi"><small><?= $e($d('kpi_next')) ?></small><b>J-3</b><span class="hd-delta">NIGHT HERON · 21:00</span></div>
                            <div class="hd-kpi is-warn"><small><?= $e($d('kpi_apps')) ?></small><b>3</b><span class="hd-delta"><?= $e($d('kpi_apps_d')) ?></span></div>
                            <div class="hd-kpi"><small><?= $e($d('kpi_quals')) ?></small><b>5</b><span class="hd-delta"><?= $e($d('kpi_quals_d')) ?></span></div>
                        </div>
                        <div class="hd-split">
                            <div class="hd-card">
                                <p class="hd-card__t"><?= $e($d('chart_t')) ?></p>
                                <div class="hd-chart" role="img" aria-label="<?= $e($d('chart_aria')) ?>">
                                    <?php foreach ($weeks as $i => $w): ?>
                                        <span class="hd-chart__col<?= $i === count($weeks) - 1 ? ' is-now' : '' ?>"><i style="height:<?= (int) $w ?>%"><em><?= (int) $w ?></em></i><small>S<?= 35 + $i ?></small></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="hd-card">
                                <p class="hd-card__t"><?= $e($d('todo_t')) ?> <b class="hd-count">4</b></p>
                                <ul class="hd-todo">
                                    <li><span class="hd-dot hd-dot--late"></span><span><b><?= $e($d('todo_1')) ?></b><small><?= $e($d('todo_1_d')) ?></small></span></li>
                                    <li><span class="hd-dot hd-dot--warn"></span><span><b><?= $e($d('todo_2')) ?></b><small><?= $e($t('CQB niv. 2', 'CQB lvl 2')) ?> · TALON</small></span></li>
                                    <li><span class="hd-dot hd-dot--warn"></span><span><b><?= $e($d('todo_3')) ?></b><small><?= $e($t('Sgt VIPER → Sgt-chef', 'Sgt VIPER → SSgt')) ?></small></span></li>
                                    <li><span class="hd-dot hd-dot--ok"></span><span><b><?= $e($d('todo_4')) ?></b><small>NIGHT HERON · 18 / 24</small></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Formation -->
                    <div class="hd-panel" role="tabpanel" id="hd-panel-training" aria-labelledby="hd-tab-training" data-hd-panel="training" data-hd-path="formations" hidden>
                        <div class="hd-course">
                            <span class="hd-course__badge">CQB</span>
                            <span><b><?= $e($d('course_t')) ?></b><small><?= $e($d('course_d')) ?></small></span>
                            <span class="hd-course__stats"><span><b>12</b><?= $e($d('course_enrolled')) ?></span><span><b>83 %</b><?= $e($d('course_success')) ?></span><span><b>2</b><?= $e($d('course_eval')) ?></span></span>
                        </div>
                        <div class="hd-mods" aria-hidden="true"><span></span><span class="hd-mods__list">
                            <?php foreach (($isEn ? ['Basics', 'Entry', 'Moving', 'Rooms', 'Hostages', 'Exam'] : ['Bases', 'Entrée', 'Progr.', 'Pièces', 'Otages', 'Examen']) as $mi => $m): ?><span><?= $e($m) ?></span><?php endforeach; ?>
                        </span></div>
                        <ul class="hd-trainees">
                            <?php foreach ($trainees as $tr): ?>
                                <li>
                                    <span class="hd-op"><i><?= $e($initials($tr['cs'])) ?></i><b><?= $e($tr['cs']) ?></b></span>
                                    <span class="hd-steps"><?php foreach ($tr['mods'] as $m): ?><i class="s<?= (int) $m ?>"></i><?php endforeach; ?></span>
                                    <span class="hd-bar hd-bar--wide"><i style="width:<?= (int) $tr['pct'] ?>%"></i></span>
                                    <span class="hd-st hd-st--<?= $e($tr['state']) ?>"><?= $e($d('tr_' . $tr['state'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="hd-foot"><span class="hd-dot hd-dot--warn"></span><?= $e($d('course_next')) ?></p>
                    </div>

                    <!-- Manœuvre -->
                    <div class="hd-panel" role="tabpanel" id="hd-panel-op" aria-labelledby="hd-tab-op" data-hd-panel="op" data-hd-path="manoeuvres/night-heron" hidden>
                        <div class="hd-ophead">
                            <span><small><?= $e($d('op_kind')) ?></small><b>OP NIGHT HERON</b></span>
                            <span class="hd-mono hd-muted"><?= $e($d('op_when')) ?></span>
                            <span class="hd-rsvp"><span class="is-yes">18 <?= $e($d('rsvp_yes')) ?></span><span class="is-maybe">4 <?= $e($d('rsvp_maybe')) ?></span><span class="is-no">2 <?= $e($d('rsvp_no')) ?></span></span>
                        </div>
                        <div class="hd-split hd-split--op">
                            <div class="hd-map" aria-hidden="true">
                                <svg viewBox="0 0 320 220" preserveAspectRatio="xMidYMid slice">
                                    <rect width="320" height="220" fill="#0f2a1f"/>
                                    <g fill="none" stroke="#2f5b45" stroke-width="1">
                                        <path d="M-10 170C60 140 90 180 160 150S270 120 330 140"/><path d="M-10 140C50 110 100 150 170 118S260 92 330 104"/>
                                        <path d="M-10 108C40 86 110 118 180 86S250 60 330 70"/><path d="M-10 74C60 54 120 82 190 54S260 30 330 36"/>
                                        <path d="M40 220C60 190 50 160 80 140"/><path d="M230 0C220 30 250 50 240 80"/>
                                    </g>
                                    <g stroke="#ffffff22" stroke-width=".5"><path d="M80 0v220M160 0v220M240 0v220M0 55h320M0 110h320M0 165h320"/></g>
                                    <path d="M40 200 C90 170 120 150 150 118 S210 70 250 58" fill="none" stroke="#34d399" stroke-width="2" stroke-dasharray="5 4"/>
                                    <path d="M120 0 L135 220" stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="2 3"/>
                                    <text x="138" y="14" fill="#f59e0b" font-size="9" font-family="monospace">PL ROUGE</text>
                                    <circle cx="40" cy="200" r="5" fill="#34d399"/><text x="50" y="204" fill="#d1fae5" font-size="9" font-family="monospace">ZPH</text>
                                    <g transform="translate(250 58)"><circle r="16" fill="#ef444422" stroke="#ef4444"/><path d="M-6-6l12 12M6-6l-12 12" stroke="#ef4444" stroke-width="2"/></g>
                                    <text x="232" y="88" fill="#fecaca" font-size="9" font-family="monospace">OBJ FALCON</text>
                                </svg>
                            </div>
                            <div class="hd-roster">
                                <?php foreach ($roster as $g): ?>
                                    <div class="hd-roster__g">
                                        <p><?= $e($g['grp']) ?></p>
                                        <?php foreach ($g['slots'] as [$slot, $who]): ?>
                                            <span class="hd-slot<?= $who === '' ? ' is-free' : '' ?>"><small><?= $e($slot) ?></small><b><?= $who !== '' ? $e($who) : $e($d('slot_free')) ?></b></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- JNET / ATAK -->
                    <div class="hd-panel" role="tabpanel" id="hd-panel-jnet" aria-labelledby="hd-tab-jnet" data-hd-panel="jnet" data-hd-path="jnet" hidden>
                        <div class="hd-feed">
                            <article class="hd-ex hd-ex--order">
                                <header><span class="hd-tag hd-tag--order"><?= $e($d('ex_order')) ?></span><span class="hd-muted"><?= $e($t('1re Cie', '1st Coy')) ?> → Alpha · Bravo</span><time class="hd-mono">20:30Z</time></header>
                                <b><?= $e($d('ex_order_t')) ?></b>
                                <p><?= $e($d('ex_order_d')) ?></p>
                                <footer><span class="hd-ack"><i style="width:78%"></i></span><span class="hd-mono">7 / 9 <?= $e($d('ex_read')) ?></span></footer>
                            </article>
                            <article class="hd-ex hd-ex--intel">
                                <header><span class="hd-src">ATAK</span><span class="hd-tag hd-tag--intel"><?= $e($d('ex_intel')) ?></span><span class="hd-muted">Alpha → <?= $e($t('1re Cie', '1st Coy')) ?></span><time class="hd-mono">21:47Z</time></header>
                                <b>SALUTE · 6 PAX, 1 technical</b>
                                <p class="hd-mono">S:6 · A:<?= $e($d('ex_salute_a')) ?> · L:38S MB 412 776 · E:DShK</p>
                            </article>
                            <article class="hd-ex hd-ex--intel">
                                <header><span class="hd-src">ATAK</span><span class="hd-tag hd-tag--intel"><?= $e($d('ex_photo')) ?></span><span class="hd-muted">ONYX · <?= $e($t('Tireurs', 'Snipers')) ?></span><time class="hd-mono">21:44Z</time></header>
                                <div class="hd-photo" aria-hidden="true">
                                    <svg viewBox="0 0 640 100" preserveAspectRatio="xMidYMid slice"><rect width="640" height="100" fill="#1b2a22"/><path d="M0 74 L90 58 L170 66 L260 48 L340 60 L430 44 L520 56 L640 46 V100 H0z" fill="#2c3d30"/><rect x="300" y="34" width="44" height="22" fill="#3d4a3c"/><rect x="348" y="40" width="26" height="16" fill="#36433a"/><g stroke="#34d39999" fill="none"><path d="M300 52h-34M322 56v20"/><circle cx="322" cy="45" r="15"/></g><text x="10" y="16" fill="#a7f3d0" font-size="10" font-family="monospace">AZ 284° · 640 m · 21:44Z</text></svg>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
