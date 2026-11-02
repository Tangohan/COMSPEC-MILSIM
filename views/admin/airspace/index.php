<?php
declare(strict_types=1);

use App\Services\Personnel\PilotQualificationEngine as Eng;
use App\Support\PilotSyllabusCatalog as Cat;

/**
 * Back-office > Espace aérien : ligne de vol, cursus, pilotes, carnet de vol, flotte (données relevées seulement).
 *
 * @var array<string, mixed> $aero AirspaceOverviewService::overview()
 * @var string $aeroView
 * @var string $aeroTrack
 * @var string $aeroLogFilter
 * @var bool $allowed
 * @var int $actorId
 */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fh = static fn (float $v): string => Eng::formatHours($v);
$fd = static fn (string $d): string => $d !== '' ? date('d/m/Y', strtotime($d) ?: time()) : '';
$members = $aero['members'] ?? [];
$name = static function (int $uid, array $fallback = []) use ($members): string {
    $m = $members[$uid] ?? $fallback;
    $call = trim((string) ($m['callsign'] ?? ''));
    $dn = trim((string) ($m['display_name'] ?? ''));

    return $call !== '' ? $call : ($dn !== '' ? $dn : '#' . $uid);
};
$full = static function (int $uid, array $fallback = []) use ($members): string {
    $m = $members[$uid] ?? $fallback;
    $call = trim((string) ($m['callsign'] ?? ''));
    $dn = trim((string) ($m['display_name'] ?? ''));

    return $dn !== '' && $call !== '' && $dn !== $call ? $dn : '';
};
$avatar = static function (int $uid, string $label, string $size = '') use ($h): string {
    $palette = ['#059669', '#1d4ed8', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#475569', '#b45309'];
    $ini = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', $label) ?: '?', 0, 2));

    return '<span class="aero-av' . ($size !== '' ? ' aero-av--' . $size : '') . '" style="background:' . $palette[abs($uid) % count($palette)] . '" aria-hidden="true">' . $h($ini) . '</span>';
};
$trackShort = [Cat::TRACK_HELI => 'HÉLI', Cat::TRACK_PLANE => 'AVION', 'other' => 'AUTRE'];
$trackName = [Cat::TRACK_HELI => 'Hélicoptère', Cat::TRACK_PLANE => 'Avion', 'other' => 'Autre'];
$curBadge = static function (?array $c) use ($h): string {
    if ($c === null) {
        return '';
    }
    if (!empty($c['permanent'])) {
        return '<span class="aero-cur ok"><i></i>permanent</span>';
    }
    if ($c['s'] === 'exp') {
        return '<span class="aero-cur exp" title="' . $h('pas à jour') . '"><i></i>' . ($c['since'] === null ? 'jamais volé' : 'J+' . (int) $c['since']) . '</span>';
    }

    return '<span class="aero-cur ' . $h((string) $c['s']) . '"><i></i>' . (int) $c['left'] . ' j</span>';
};
$cursusUrl = url('back-office/referentiels/qualifications/cursus-pilote');
$logbookUrl = static fn (int $uid): string => url('back-office/referentiels/qualifications/cursus-pilote/carnet/' . $uid);
$retour = 'back-office/espace-aerien' . ($aeroView !== 'ligne' ? '?vue=' . $aeroView : '');
$queue = $aero['queue'] ?? [];
$counts = $aero['log_counts'] ?? [];

$aeroTab = $aeroView;
$aeroHud = $aero['hud'] ?? null;
$aeroTabCounts = ['ligne' => count($queue), 'carnet' => (int) ($counts['declared'] ?? 0)];
?>
<div class="aero">
    <?php require __DIR__ . '/_hero.php'; ?>

    <?php if (empty($aero['schema_ready'])): ?>
    <p class="aero-note warn" role="status">Le cursus pilote n’est pas encore prêt sur ce serveur : l’administrateur doit lancer les migrations.</p>
    <?php endif; ?>

    <?php if ($aeroView === 'ligne'): ?>
    <div class="aero-ph">
        <div><span class="aero-k">Disponibilité</span><h2>Ligne de vol</h2><p>Qui peut décoller sur quoi, aujourd’hui : les pilotes qualifiés sur chaque appareil du cursus et leur maintien, qui expire après le délai du palier sans vol sur le type.</p></div>
    </div>
    <div class="aero-grid2">
        <div class="aero-panel">
            <?php if (($aero['line'] ?? []) === []): ?>
            <div class="aero-empty"><b>Aucun appareil au cursus</b>Installez les qualifications de type des appareils pilotés dans <a href="<?= $h($cursusUrl . '#installer') ?>">Réglages du cursus</a>.</div>
            <?php else: ?>
            <div class="aero-line">
                <?php foreach ($aero['line'] as $col):
                    $n = max(1, count($col['pilots']));
                    ?>
                <div class="aero-col">
                    <div class="aero-col__h">
                        <b><span><?= $h((string) $col['label']) ?></span><span class="aero-fchip is-<?= $h((string) $col['track']) ?>"><?= $h($trackShort[$col['track']] ?? '') ?></span></b>
                        <small><?= count($col['pilots']) ?> qualifié(s) · <?= (int) $col['count']['ok'] + (int) $col['count']['soon'] ?> à jour</small>
                        <div class="aero-meter" aria-hidden="true"><i style="width:<?= round($col['count']['ok'] / $n * 100) ?>%;background:var(--a-primary)"></i><i style="width:<?= round($col['count']['soon'] / $n * 100) ?>%;background:#f59e0b"></i><i style="width:<?= round($col['count']['exp'] / $n * 100) ?>%;background:var(--a-danger)"></i></div>
                    </div>
                    <?php if ($col['pilots'] === []): ?>
                    <div class="aero-none">Aucun pilote qualifié sur ce type.</div>
                    <?php endif; ?>
                    <?php foreach ($col['pilots'] as $p): $uid = (int) $p['user_id']; ?>
                    <a class="aero-prow" href="<?= $h($logbookUrl($uid)) ?>"><?= $avatar($uid, $name($uid), 'sm') ?><b><?= $h($name($uid)) ?></b><span class="aero-code"><?= $h((string) $p['top']) ?></span><?= $curBadge($p['cur']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="aero-panel">
            <div class="aero-phd"><div><h3>À valider</h3><small>Paliers atteints et vols à contresigner</small></div><span class="aero-stt pending"><?= count($queue) ?></span></div>
            <?php if ($queue === []): ?>
            <div class="aero-empty"><b>Rien à valider</b>Toutes les propositions ont été traitées.</div>
            <?php endif; ?>
            <div class="aero-q">
                <?php foreach ($queue as $it):
                    $uid = (int) $it['user_id'];
                    $who = $it['kind'] === 'step' ? $name($uid, $it['member']) : ($it['kind'] === 'flight' ? $name($uid, $it['flight']) : $name($uid));
                    ?>
                <div class="aero-qi">
                    <div class="aero-qi__top">
                        <?= $avatar($uid, $who) ?>
                        <?php if ($it['kind'] === 'rule'): $r = $it['rule']; $e = $it['item']; ?>
                        <span><b><?= $h($who) ?> · <?= $h((string) $it['code']) ?></b><small><?= $h((string) $r['definition_name']) ?></small></span><span class="aero-qi__kind">Palier</span>
                        <?php elseif ($it['kind'] === 'step'): $s = $it['step']; ?>
                        <span><b><?= $h($who) ?> · <?= $h((string) $it['code']) ?></b><small><?= $h((string) $s['definition_name']) ?></small></span><span class="aero-qi__kind">Palier</span>
                        <?php else: $f = $it['flight']; ?>
                        <span><b><?= $h($who) ?> · <?= $h((string) (($f['vehicle_label'] ?? '') ?: $f['vehicle_class'])) ?> · <?= $h($fh((int) $f['seconds'] / 3600)) ?></b><small><?= $h($fd((string) $f['flown_on'])) ?><?= trim((string) ($f['remarks'] ?? '')) !== '' ? ' · ' . $h((string) $f['remarks']) : '' ?></small></span><span class="aero-qi__kind">Carnet</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($it['kind'] === 'rule'): ?>
                    <div class="aero-req"><span><?= $h($fh((float) $e['scope_hours'])) ?> / <?= $h($fh((float) $e['target_scope'])) ?></span><?php if ((float) $e['target_track'] > 0): ?><span><?= $h($fh((float) $e['track_hours'])) ?> / <?= $h($fh((float) $e['target_track'])) ?> · <?= $h(mb_strtolower(Cat::trackLabel((string) $r['track']))) ?></span><?php endif; ?><?php if ((float) ($e['target_cond'] ?? 0) > 0): ?><span><?= $h($fh((float) $e['cond_hours'])) ?> <?= $h(Cat::conditions()[(string) $e['cond']] ?? '') ?></span><?php endif; ?></div>
                    <?php elseif ($it['kind'] === 'flight'):
                        $tags = [];
                        foreach (['night_seconds' => 'NUIT', 'ifr_seconds' => 'IFR', 'formation_seconds' => 'PATR'] as $col => $lab) {
                            if ((int) ($f[$col] ?? 0) > 0) {
                                $tags[] = '<span class="aero-tag is-' . strtolower($lab) . '">' . $lab . ' ' . $h($fh((int) $f[$col] / 3600)) . '</span>';
                            }
                        }
                        ?>
                    <?php if ($tags !== []): ?><div class="aero-req"><?= implode('', $tags) ?></div><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($it['mine']): ?>
                    <small class="aero-hint">Votre propre proposition : un autre instructeur doit la traiter.</small>
                    <?php elseif (!$it['can']): ?>
                    <small class="aero-hint">Réservé aux instructeurs de la filière.</small>
                    <?php elseif ($allowed): ?>
                    <div class="aero-qi__acts">
                        <?php if ($it['kind'] === 'rule'): ?>
                        <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/cursus-pilote/propositions/' . (int) $it['item']['award_id'])) ?>">
                            <?= \App\Core\Csrf::field() ?><input type="hidden" name="retour" value="<?= $h($retour) ?>">
                            <button type="submit" name="decision" value="valider" class="aero-btn sm pri">Valider après vol d’évaluation</button>
                            <button type="submit" name="decision" value="ajourner" class="aero-btn sm">Ajourner</button>
                        </form>
                        <?php elseif ($it['kind'] === 'step'): ?>
                        <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/cursus-pilote/paliers-communaute/propositions/' . (int) $it['award_id'])) ?>">
                            <?= \App\Core\Csrf::field() ?><input type="hidden" name="retour" value="<?= $h($retour) ?>"><input type="hidden" name="definition_id" value="<?= (int) $it['step']['definition_id'] ?>">
                            <button type="submit" name="decision" value="valider" class="aero-btn sm pri">Valider</button>
                            <button type="submit" name="decision" value="ajourner" class="aero-btn sm">Ajourner</button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/cursus-pilote/carnet/vols/' . (int) $f['id'])) ?>" class="aero-sign">
                            <?= \App\Core\Csrf::field() ?><input type="hidden" name="retour" value="<?= $h($retour) ?>">
                            <input type="text" name="note" maxlength="255" placeholder="Motif (obligatoire pour refuser)" aria-label="Motif">
                            <button type="submit" name="decision" value="contresigner" class="aero-btn sm dark">Contresigner</button>
                            <button type="submit" name="decision" value="refuser" class="aero-btn sm red">Refuser</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php elseif ($aeroView === 'cursus'):
        $rows = $aero['tracks'][$aeroTrack] ?? [];
        $custom = $aero['custom'][$aeroTrack] ?? [];
        $arrow = '<span class="aero-lk" aria-hidden="true"><svg viewBox="0 0 26 14"><path d="M1 7h22M18 2l5 5-5 5"/></svg></span>';
        ?>
    <div class="aero-ph">
        <div><span class="aero-k">Référentiel</span><h2>Cursus pilote</h2><p>De gauche à droite, l’ordre du parcours : chaque palier demande le précédent. Cliquez un palier pour voir qui y est et régler ses seuils.</p></div>
        <div class="aero-seg" role="group" aria-label="Filière">
            <?php foreach ($trackName as $tk => $tl):
                if ($tk === 'other' && empty($aero['custom']['other'])) {
                    continue;
                }
                $n = count($aero['tracks'][$tk] ?? []) + count($aero['custom'][$tk] ?? []);
                ?>
            <a href="<?= $h(url('back-office/espace-aerien') . '?vue=cursus&filiere=' . $tk) ?>" class="<?= $aeroTrack === $tk ? 'is-on' : '' ?>"><?= $h($tl) ?><span class="c"><?= $n ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="aero-how">
        <div><b><em>1</em>Les heures se comptent seules</b>Le mod relève le temps aux commandes, par appareil, toutes les 10 minutes en jeu.</div>
        <div><b><em>2</em>Un palier atteint est proposé</b>Palier « automatique » : attribué. Palier « instructeur » : envoyé en évaluation.</div>
        <div><b><em>3</em>Un instructeur valide</b>Après un vol d’évaluation avec un instructeur de la filière. Jamais soi-même.</div>
        <div><b><em>4</em>Le carnet complète</b>Nuit, instruments, patrouille : le pilote les note, un instructeur contresigne.</div>
        <div><b><em>5</em>Le maintien suit le dernier vol</b>Sans vol sur le type pendant le délai du palier, le pilote n’est plus à jour.</div>
    </div>
    <div class="aero-panel">
        <?php foreach ($rows as $row): ?>
        <div class="aero-trk">
            <div class="aero-trk__l"><span class="aero-k"><?= $row['kind'] === 'common' ? 'Tronc commun' : 'Appareil' ?></span><b><?= $h((string) $row['label']) ?></b><small><?= (int) $row['pilots'] ?> pilote(s) · <?= $h($fh((float) $row['hours'])) ?></small></div>
            <div class="aero-nodes">
                <?php foreach ($row['nodes'] as $i => $node): $r = $node['rule']; $direct = ($r['award_mode'] ?? '') === Cat::MODE_DIRECT; ?>
                <?= $i > 0 ? $arrow : '' ?>
                <a class="aero-pn<?= empty($r['is_enabled']) ? ' is-off' : '' ?>" href="<?= $h($cursusUrl . '#palier-' . (int) $r['id']) ?>">
                    <span class="aero-pn__t"><span class="aero-code"><?= $h((string) $node['code']) ?></span><span class="aero-mode <?= $direct ? 'auto' : 'ins' ?>"><?= $direct ? 'Automatique' : 'Instructeur' ?></span></span>
                    <b><?= $h(Cat::tierLabel((string) $r['tier'])) ?></b>
                    <small><?= $h($fh((float) $r['min_scope_hours'])) ?> · <?= $h((string) ($r['scope_label'] ?? '')) ?><?php if ((float) $r['min_track_hours'] > 0): ?> · <?= $h($fh((float) $r['min_track_hours'])) ?> · <?= $h(mb_strtolower(Cat::trackLabel((string) $r['track']))) ?><?php endif; ?></small>
                    <span class="aero-pn__c"><span><b><?= (int) $node['held'] ?></b> titulaire(s)</span><?php if ($node['pending'] > 0): ?><span class="aero-ev"><?= (int) $node['pending'] ?> à valider</span><?php endif; ?><?php if ($node['progress'] !== []): ?><span class="aero-avs"><?php foreach (array_slice($node['progress'], 0, 4) as $pu): ?><?= $avatar((int) $pu, $name((int) $pu), 'xs') ?><?php endforeach; ?></span><?php endif; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ($custom !== []): ?>
        <div class="aero-trk">
            <div class="aero-trk__l"><span class="aero-k">Communauté</span><b>Paliers de la communauté</b><small><?= count($custom) ?> palier(s)</small></div>
            <div class="aero-nodes">
                <?php foreach ($custom as $i => $s): $sum = $s['summary']; ?>
                <?= $i > 0 ? $arrow : '' ?>
                <a class="aero-pn" href="<?= $h($cursusUrl . '#etape-' . (int) $s['id']) ?>">
                    <span class="aero-pn__t"><span class="aero-code"><?= $h((string) (($s['definition_short_name'] ?? '') ?: ($i + 1))) ?></span><span class="aero-mode <?= !empty($s['form']['instructor']) ? 'ins' : 'auto' ?>"><?= !empty($s['form']['instructor']) ? 'Instructeur' : 'Automatique' ?></span></span>
                    <b><?= $h((string) $s['definition_name']) ?></b>
                    <span class="aero-pn__c"><span><b><?= (int) $sum['held'] ?></b> titulaire(s)</span><?php if ($sum['pending'] !== []): ?><span class="aero-ev"><?= count($sum['pending']) ?> à valider</span><?php endif; ?><?php if ($sum['near'] !== []): ?><span class="aero-avs"><?php foreach ($sum['near'] as $nr): ?><?= $avatar((int) $nr['member']['user_id'], $name((int) $nr['member']['user_id'], $nr['member']), 'xs') ?><?php endforeach; ?></span><?php endif; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($rows === [] && $custom === []): ?>
        <div class="aero-empty"><b>Aucun palier dans cette filière</b><a href="<?= $h($cursusUrl . '#installer') ?>">Installer un cursus ou créer vos paliers</a></div>
        <?php endif; ?>
    </div>

    <?php elseif ($aeroView === 'pilotes'): ?>
    <div class="aero-ph">
        <div><span class="aero-k">Personnel navigant</span><h2>Pilotes</h2><p>Heures par appareil relevées en jeu et au carnet, palier atteint, maintien et prochaine étape.</p></div>
    </div>
    <?php if (($aero['pilots'] ?? []) === []): ?>
    <div class="aero-panel"><div class="aero-empty"><b>Aucun pilote pour l’instant</b>Les pilotes apparaissent ici dès leur premier vol relevé par le mod ATAK.</div></div>
    <?php endif; ?>
    <div class="aero-pcards">
        <?php foreach ($aero['pilots'] ?? [] as $p): $uid = (int) $p['user_id']; ?>
        <div class="aero-pcard">
            <div class="aero-pcard__h">
                <?= $avatar($uid, $name($uid), 'lg') ?>
                <span><a href="<?= $h($logbookUrl($uid)) ?>"><b><?= $h($name($uid)) ?></b></a><?php if ($full($uid) !== ''): ?><small class="aero-gd"><?= $h($full($uid)) ?></small><?php endif; ?><small><?= $h(implode(' · ', array_map(static fn (string $t): string => $trackName[$t] ?? $t, $p['tracks']))) ?> · nuit <?= $h($fh((float) $p['night'])) ?> · IFR <?= $h($fh((float) $p['ifr'])) ?></small></span>
                <span class="aero-tot"><b><?= $h($fh((float) $p['total'])) ?></b><small>total</small></span>
            </div>
            <?php if ($p['common'] !== []): ?>
            <div class="aero-trow"><div class="aero-trow__a"><b>Tronc commun</b></div><div class="aero-ladder"><?php $prevTrack = ''; foreach ($p['common'] as $c): ?><?php if (count($p['tracks']) > 1 && $c['track'] !== $prevTrack): $prevTrack = $c['track']; ?><em class="aero-ladder__t"><?= $h($trackShort[$c['track']] ?? '') ?></em><?php endif; ?><span class="<?= $c['state'] === Eng::HELD ? 'h' : ($c['state'] === Eng::PENDING ? 'e' : '') ?>"><?= $h((string) $c['code']) ?></span><?php endforeach; ?></div></div>
            <?php endif; ?>
            <?php foreach ($p['rows'] as $row): ?>
            <div class="aero-trow">
                <div class="aero-trow__a"><b><?= $h((string) $row['label']) ?></b><span class="aero-hh"><?= $h($fh((float) $row['hours'])) ?></span><?= $curBadge($row['cur']) ?></div>
                <?php if ($row['installed']): ?>
                <div class="aero-ladder"><?php foreach ($row['ladder'] as $c): ?><span class="<?= $c['state'] === Eng::HELD ? 'h' : ($c['state'] === Eng::PENDING ? 'e' : '') ?>"><?= $h((string) $c['code']) ?></span><?php endforeach; ?></div>
                <?php $nx = $row['next']; ?>
                <div class="aero-bar2"><i style="width:<?= $nx === null ? 100 : (int) $nx['pct'] ?>%"></i></div>
                <div class="aero-nx">
                    <?php if ($nx === null): ?><span>Cursus terminé</span>
                    <?php elseif ($nx['state'] === Eng::PENDING || $nx['state'] === Eng::ELIGIBLE): ?><span><b><?= $h((string) $nx['code']) ?></b> prêt — en attente d’un instructeur</span><span>100 %</span>
                    <?php elseif (!$nx['prereq_ok']): ?><span><?= $h((string) $nx['code']) ?> · palier précédent d’abord</span>
                    <?php else: ?><span>Vers <b><?= $h((string) $nx['code']) ?></b> · <?= $h($fh((float) $nx['hours'])) ?> / <?= $h($fh((float) $nx['target'])) ?></span><span><?= (int) $nx['pct'] ?> %</span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="aero-nx"><span>Appareil hors cursus</span></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif ($aeroView === 'carnet'):
        $statusLabel = ['verified' => 'Relevé en jeu', 'declared' => 'À contresigner', 'countersigned' => 'Contresigné', 'rejected' => 'Refusé'];
        $statusClass = ['verified' => 'mod', 'declared' => 'pending', 'countersigned' => 'signed', 'rejected' => 'refused'];
        $all = array_sum(array_map('intval', $counts));
        ?>
    <div class="aero-ph">
        <div><span class="aero-k">Heures contresignées</span><h2>Carnet de vol</h2><p>La durée est relevée par le mod. Le pilote déclare ses heures de nuit, aux instruments ou en patrouille ; un instructeur de la filière les contresigne avant qu’elles comptent.</p></div>
        <a class="aero-btn dark" href="<?= $h(url('back-office/ma-situation/carnet-de-vol')) ?>">Noter un vol</a>
    </div>
    <div class="aero-bar">
        <div class="aero-seg" role="group" aria-label="Statut">
            <?php foreach (['' => ['Tous', $all], 'declared' => ['À contresigner', (int) ($counts['declared'] ?? 0)], 'countersigned' => ['Contresignés', (int) ($counts['countersigned'] ?? 0)], 'rejected' => ['Refusés', (int) ($counts['rejected'] ?? 0)], 'verified' => ['Relevés en jeu', (int) ($counts['verified'] ?? 0)]] as $fk => [$fl, $fn]): ?>
            <a href="<?= $h(url('back-office/espace-aerien') . '?vue=carnet' . ($fk !== '' ? '&carnet=' . $fk : '')) ?>" class="<?= $aeroLogFilter === $fk ? 'is-on' : '' ?>"><?= $h($fl) ?><span class="c"><?= $fn ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="aero-panel aero-scroll">
        <?php if (empty($aero['log_ready'])): ?>
        <div class="aero-empty"><b>Carnet de vol indisponible</b>La migration du carnet de vol n’a pas encore été lancée sur ce serveur.</div>
        <?php elseif (($aero['log'] ?? []) === []): ?>
        <div class="aero-empty"><b>Aucun vol</b>Rien dans ce filtre.</div>
        <?php else: ?>
        <table class="aero-log">
            <thead><tr><th>Date</th><th>Pilote</th><th>Appareil</th><th>Durée</th><th>Déclaré au carnet</th><th>Mission</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($aero['log'] as $f):
                $uid = (int) $f['user_id'];
                $st = (string) $f['status'];
                $tags = [];
                foreach (['night_seconds' => 'NUIT', 'ifr_seconds' => 'IFR', 'formation_seconds' => 'PATR'] as $col => $lab) {
                    if ((int) ($f[$col] ?? 0) > 0) {
                        $tags[] = '<span class="aero-tag is-' . strtolower($lab) . '">' . $lab . ' ' . $h($fh((int) $f[$col] / 3600)) . '</span>';
                    }
                }
                $by = trim((string) (($f['reviewer_callsign'] ?? '') ?: ($f['reviewer_name'] ?? '')));
                $mission = trim((string) ($f['mission_key'] ?? ''));
                $mission = $mission !== '' ? str_replace('@', ' · ', (string) preg_replace('/#\d+$/', '', $mission)) : trim((string) ($f['remarks'] ?? ''));
                $canSign = $st === 'declared' && $uid !== $actorId && !empty($aero['can_review'][(string) $f['vehicle_kind']]) && $allowed;
                ?>
            <tr>
                <td class="mono"><?= $h($fd((string) $f['flown_on'])) ?></td>
                <td><a class="aero-who" href="<?= $h($logbookUrl($uid)) ?>"><?= $avatar($uid, $name($uid, $f), 'sm') ?><b><?= $h($name($uid, $f)) ?></b></a></td>
                <td class="nw"><?= $h((string) (($f['vehicle_label'] ?? '') ?: $f['vehicle_class'])) ?></td>
                <td class="mono"><?= $h($fh((int) $f['seconds'] / 3600)) ?></td>
                <td><?= $tags !== [] ? implode(' ', $tags) : '<span class="aero-muted">—</span>' ?></td>
                <td><?= $h($mission) ?><?php if ($st === 'rejected' && trim((string) ($f['review_note'] ?? '')) !== ''): ?><br><small class="aero-bad"><?= $h((string) $f['review_note']) ?></small><?php endif; ?></td>
                <td><span class="aero-stt <?= $h($statusClass[$st] ?? '') ?>"><?= $h($statusLabel[$st] ?? $st) ?></span><?php if ($by !== '' && in_array($st, ['countersigned', 'rejected'], true)): ?> <small class="aero-muted">· <?= $h($by) ?></small><?php endif; ?></td>
                <td><?php if ($canSign): ?>
                    <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/cursus-pilote/carnet/vols/' . (int) $f['id'])) ?>" class="aero-sign">
                        <?= \App\Core\Csrf::field() ?><input type="hidden" name="retour" value="<?= $h('back-office/espace-aerien?vue=carnet' . ($aeroLogFilter !== '' ? '&carnet=' . $aeroLogFilter : '')) ?>">
                        <input type="text" name="note" maxlength="255" placeholder="Motif (obligatoire pour refuser)" aria-label="Motif">
                        <button type="submit" name="decision" value="contresigner" class="aero-btn sm dark">Contresigner</button>
                        <button type="submit" name="decision" value="refuser" class="aero-btn sm red">Refuser</button>
                    </form>
                <?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="aero-ph">
        <div><span class="aero-k">Configuration</span><h2>Flotte et filières</h2><p>Appareils réellement pilotés dans la communauté et appareils du cursus. Chaque appareil installé reçoit les paliers de type, créés dans le référentiel (catégorie « Pilotage ») sans doublon ; les seuils restent modifiables dans les réglages du cursus.</p></div>
        <a class="aero-btn dark" href="<?= $h($cursusUrl . '#flotte') ?>">Gérer appareils et familles</a>
    </div>
    <div class="aero-fleet">
        <?php foreach ([Cat::TRACK_HELI, Cat::TRACK_PLANE] as $tk):
            $list = $aero['fleet'][$tk] ?? [];
            $inst = count(array_filter($list, static fn (array $a): bool => $a['installed']));
            ?>
        <div class="aero-panel">
            <div class="aero-phd"><div><h3>Filière <?= $h(mb_strtolower($trackName[$tk])) ?></h3><small><?= $inst ?> appareil(s) au cursus · <?= count(array_filter($list, static fn (array $a): bool => $a['flown'])) ?> piloté(s) en jeu</small></div><span class="aero-fchip is-<?= $h($tk) ?>"><?= $h($trackShort[$tk]) ?></span></div>
            <?php if ($list === []): ?>
            <div class="aero-empty"><b>Aucun appareil</b>Aucun vol relevé dans cette filière pour l’instant.</div>
            <?php else: ?>
            <div class="aero-chips">
                <?php foreach ($list as $a): ?>
                <a class="aero-ac<?= $a['installed'] ? ' is-on' : '' ?>" href="<?= $h($cursusUrl . ($a['installed'] ? '#flotte' : '#installer')) ?>" title="<?= $a['installed'] ? 'Au cursus' : 'Pas encore au cursus : l’installer' ?>"><?= $a['installed'] ? '✓ ' : '+ ' ?><?= $h((string) $a['label']) ?><?php if ($a['flown']): ?><small><?= $h($fh((float) $a['hours'])) ?> · <?= (int) $a['pilots'] ?> pil.</small><?php endif; ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="aero-scroll">
            <table class="aero-ptab">
                <thead><tr><th>Palier</th><th>Seuil en jeu</th><th>Maintien</th><th>Repère réel</th></tr></thead>
                <tbody>
                <?php foreach (Cat::tiers() as $t): ?>
                <tr>
                    <td><span class="aero-pt"><span class="aero-code<?= $t['per'] === Cat::PER_FAMILY ? '' : ' ghost' ?>"><?= $h($t['short']) ?></span><b><?= $h($t['label']) ?></b></span></td>
                    <td><?= $h($fh((float) $t['min_scope_hours'])) ?> <?= $t['per'] === Cat::PER_FAMILY ? 'sur le type' : 'dans la filière' ?><?php if ((float) $t['min_track_hours'] > 0): ?>, <?= $h($fh((float) $t['min_track_hours'])) ?> dans la filière<?php endif; ?><br><span class="aero-mode <?= $t['mode'] === Cat::MODE_DIRECT ? 'auto' : 'ins' ?>"><?= $t['mode'] === Cat::MODE_DIRECT ? 'Automatique' : 'Instructeur' ?></span></td>
                    <td class="mono"><?= $t['currency_days'] ? (int) $t['currency_days'] . ' j' : '—' ?></td>
                    <td class="rv"><?= $h((string) ($t['real'][$tk] ?? '')) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="aero-disc">Seuils du cursus modèle, ramenés à l’échelle d’une communauté qui vole quelques heures par semaine. Les références (EASA Part-FCL, FAA 14 CFR Part 61, US Army TC 3-04.11) sont des repères publics, pas une certification.</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
