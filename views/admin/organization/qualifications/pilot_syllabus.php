<?php
declare(strict_types=1);

use App\Services\Personnel\PilotQualificationEngine as Eng;
use App\Support\PilotSyllabusCatalog as Cat;

/**
 * Back-office > Qualifications > Cursus pilote.
 *
 * @var bool $schemaReady
 * @var bool $allowed
 * @var array{rules: list<array<string, mixed>>, members: array<int, array{display_name: string, callsign: string}>, flown_families: array<string, list<array{key: string, label: string, hours: float, pilots: int}>>, pending_total: int, eligible_total: int} $board
 * @var array<string, array<string, mixed>> $tiers
 * @var array<string, array{label: string, short: string, noun: string}> $tracks
 * @var array<string, array<string, string>> $knownFamilies
 * @var int $actorId
 * @var list<array<string, mixed>> $declaredFlights
 * @var array<string, bool> $canReviewTrack
 */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$base = 'back-office/referentiels/qualifications/cursus-pilote';
$rules = $board['rules'] ?? [];
$members = $board['members'] ?? [];
$flown = $board['flown_families'] ?? [];
$hours = static fn (float $v): string => Eng::formatHours($v);
$who = static function (int $uid) use ($members): string {
    $m = $members[$uid] ?? null;
    if ($m === null) {
        return 'Membre #' . $uid;
    }
    $call = trim($m['callsign']);

    return trim($m['display_name']) !== '' ? $m['display_name'] . ($call !== '' ? ' « ' . $call . ' »' : '') : ($call !== '' ? $call : 'Membre #' . $uid);
};
$target = static function (array $r) use ($hours): string {
    $t = $hours((float) $r['min_scope_hours']) . ' sur ' . ((string) ($r['scope_label'] ?? '') ?: Cat::trackLabel((string) $r['track']));
    if ((float) $r['min_track_hours'] > 0) {
        $t .= ' et ' . $hours((float) $r['min_track_hours']) . ' en ' . mb_strtolower(Cat::trackLabel((string) $r['track']));
    }
    $cond = Cat::tier((string) $r['tier'])['cond'] ?? null;
    if ($cond !== null && (float) ($r['min_cond_hours'] ?? 0) > 0) {
        $t .= ', dont ' . $hours((float) $r['min_cond_hours']) . ' ' . (Cat::conditions()[$cond] ?? '') . ' contresignées au carnet';
    }

    return $t;
};

// Propositions en attente, toutes règles confondues, et règles rangées par filière : tronc commun puis un rang par type.
$pending = [];
$byTrack = [];
$pilotIds = [];
foreach ($rules as $r) {
    foreach ($r['groups'] as $state => $list) {
        foreach ($list as $it) {
            $pilotIds[$it['user_id']] = true;
        }
    }
    foreach ($r['groups'][Eng::PENDING] as $it) {
        $pending[] = ['rule' => $r, 'item' => $it];
    }
    $track = (string) $r['track'];
    $isFamily = str_starts_with((string) $r['scope'], 'family:');
    $row = $isFamily ? (string) $r['scope'] : 'common';
    $byTrack[$track][$row]['label'] = $isFamily ? (string) ($r['scope_label'] ?? '') : 'Tronc commun';
    $byTrack[$track][$row]['rules'][] = $r;
}
foreach ($byTrack as $t => $rows) {
    uksort($rows, static fn (string $a, string $b): int => ($a === 'common' ? 0 : 1) <=> ($b === 'common' ? 0 : 1) ?: strcmp($a, $b));
    $byTrack[$t] = $rows;
}
$declaredFlights = is_array($declaredFlights ?? null) ? $declaredFlights : [];
$canReviewTrack = is_array($canReviewTrack ?? null) ? $canReviewTrack : [];
$logbookUrl = static fn (int $uid): string => url($base . '/carnet/' . $uid);
$heldTotal = array_sum(array_map(static fn (array $r): int => count($r['groups'][Eng::HELD]), $rules));

$athKpis = [
    ['label' => 'Paliers du cursus', 'value' => (string) count($rules), 'pct' => $rules !== [] ? '100%' : '0%', 'note' => count($rules) > 0 ? count(array_filter($rules, static fn (array $r): bool => !empty($r['is_enabled']))) . ' actif(s)' : 'Installez un cursus ci-dessous'],
    ['label' => 'Pilotes suivis', 'value' => (string) count($pilotIds), 'pct' => $pilotIds !== [] ? '100%' : '0%', 'note' => 'Membres avec des heures de vol en jeu'],
    ['label' => 'À valider', 'value' => (string) count($pending), 'tone' => $pending !== [] ? '#c27a1a' : '#0b8a5c', 'pct' => $pending !== [] ? '100%' : '0%', 'note' => $pending !== [] ? 'Propositions en attente d’un instructeur' : 'Aucune proposition en attente'],
    ['label' => 'Qualifications obtenues', 'value' => (string) $heldTotal, 'pct' => $heldTotal > 0 ? '100%' : '0%', 'note' => 'Titulaires de chaque palier'],
];
?>
<div class="pcs">
    <?php if (!$schemaReady): ?>
    <p class="pcs-notice pcs-notice--warn" role="status"><strong>Le cursus pilote n’est pas encore prêt sur ce serveur.</strong> L’administrateur doit lancer les migrations (étape « pilot_qualification_rules »).</p>
    <?php endif; ?>
    <?php if (!$allowed): ?>
    <div class="pcs-lock" role="note">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <div>
            <strong>Module des formules payantes.</strong>
            <span>Le cursus pilote automatique est compris dans les formules Standard, Pro et Pro+. Vous pouvez parcourir les paliers ; l’installation et l’attribution automatique demandent une de ces formules.</span>
        </div>
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('platform/upgrade') . '?from=pilot_qualifications') ?>">Voir les formules</a>
    </div>
    <?php endif; ?>

    <div class="bo-qual__toolbar ath-rise" role="group" aria-label="Actions du cursus pilote">
        <?php if ($rules !== []): ?>
        <form method="post" action="<?= $h(url($base . '/evaluer')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="ath-btn ath-btn--solid"<?= $allowed ? '' : ' disabled' ?>>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
                Évaluer maintenant
            </button>
        </form>
        <?php endif; ?>
        <a href="#installer" class="ath-btn"><?= $rules === [] ? 'Installer un cursus' : 'Ajouter des paliers ou des appareils' ?></a>
        <a href="<?= $h(url('back-office/referentiels/qualifications')) ?>" class="ath-btn">Toutes les qualifications</a>
    </div>

    <?php require base_path('views/partials/ath_kpis.php'); ?>

    <section class="pcs-how ath-card" aria-labelledby="pcs-how-title">
        <h2 id="pcs-how-title">Comment ça marche</h2>
        <ol class="pcs-how__steps">
            <li><b>Les heures se comptent toutes seules.</b> Le mod relève le temps aux commandes de chaque appareil (toutes les 10 minutes en jeu), par type : UH-60, AH-64, C-130…</li>
            <li><b>Un palier atteint est attribué ou proposé.</b> Toutes les heures, chaque membre qui a les heures et le palier précédent reçoit la qualification (palier « automatique ») ou une proposition « en évaluation ».</li>
            <li><b>Un instructeur valide, comme dans la vraie vie.</b> Le lâché solo, la qualification de type ou le vol de nuit se valident après un vol avec un instructeur de la filière. On ne se valide pas soi-même.</li>
            <li><b>Le carnet de vol complète, comme dans la vraie vie.</b> Chaque pilote note ses vols et ses heures de nuit, aux instruments ou en patrouille dans « Mon carnet de vol » ; un instructeur contresigne, puis elles comptent.</li>
            <li><b>Le maintien de qualification suit le dernier vol.</b> Un pilote qui n’a pas volé sur le type depuis 90 jours n’est plus « à jour » ; il le redevient dès son prochain vol.</li>
        </ol>
    </section>

    <?php if ($pending !== []): ?>
    <section class="pcs-pending ath-card" aria-labelledby="pcs-pending-title">
        <header class="pcs-sec__head">
            <h2 id="pcs-pending-title">À valider par un instructeur <span class="rql__count"><?= count($pending) ?></span></h2>
            <p>Le membre a les heures demandées. Validez après le contrôle en vol, ou ajournez s’il doit encore s’entraîner.</p>
        </header>
        <ul class="pcs-pending__list">
            <?php foreach ($pending as $p):
                $r = $p['rule'];
                $it = $p['item'];
                $validators = $r['validators'];
                $mine = (int) $it['user_id'] === $actorId;
                $canValidate = !$mine && ($validators === null || in_array($actorId, $validators, true));
                ?>
            <li class="pcs-pending__row">
                <span class="pcs-pending__badge"><?= $h((string) ($tiers[$r['tier']]['short'] ?? '')) ?></span>
                <span class="pcs-pending__main">
                    <a class="pcs-pending__who" href="<?= $h($logbookUrl((int) $it['user_id'])) ?>" title="Ouvrir son carnet de vol"><?= $h($who((int) $it['user_id'])) ?></a>
                    <span class="pcs-pending__what"><?= $h((string) $r['definition_name']) ?> · <?= $h($hours((float) $it['scope_hours'])) ?> de vol pour <?= $h($hours((float) $it['target_scope'])) ?> demandées</span>
                    <?php if ($mine): ?>
                    <span class="pcs-pending__hint">Votre propre proposition : un autre instructeur doit la valider.</span>
                    <?php elseif (!$canValidate): ?>
                    <span class="pcs-pending__hint">Réservé aux instructeurs <?= $h(mb_strtolower(Cat::trackLabel((string) $r['track']))) ?> de la communauté.</span>
                    <?php elseif ($validators === null && !empty($r['validator_tier'])): ?>
                    <span class="pcs-pending__hint">Aucun instructeur <?= $h(mb_strtolower(Cat::trackLabel((string) $r['track']))) ?> pour l’instant : un gestionnaire valide.</span>
                    <?php endif; ?>
                </span>
                <?php if ($canValidate && $allowed): ?>
                <form method="post" action="<?= $h(url($base . '/propositions/' . (int) $it['award_id'])) ?>" class="pcs-pending__actions">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" name="decision" value="valider" class="ath-btn ath-btn--solid">Valider</button>
                    <button type="submit" name="decision" value="ajourner" class="ath-btn">Ajourner</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($declaredFlights !== []): ?>
    <section class="pcs-pending pcs-flights ath-card" aria-labelledby="pcs-flights-title">
        <header class="pcs-sec__head">
            <h2 id="pcs-flights-title">Vols à contresigner <span class="rql__count"><?= count($declaredFlights) ?></span></h2>
            <p>Vols saisis ou annotés par les pilotes dans leur carnet. Une fois contresignés, leurs heures (et les heures de nuit, aux instruments ou en patrouille) comptent pour le cursus.</p>
        </header>
        <ul class="pcs-pending__list">
            <?php foreach ($declaredFlights as $f):
                $fUid = (int) $f['user_id'];
                $fKind = (string) $f['vehicle_kind'];
                $mineFlight = $fUid === $actorId;
                $canSign = !$mineFlight && !empty($canReviewTrack[$fKind]);
                $fName = isset($members[$fUid]) ? $who($fUid) : (trim((string) ($f['display_name'] ?? '')) ?: (trim((string) ($f['callsign'] ?? '')) ?: 'Membre #' . $fUid));
                $conds = [];
                foreach (['night' => 'night_seconds', 'ifr' => 'ifr_seconds', 'formation' => 'formation_seconds'] as $ck => $col) {
                    if ((int) ($f[$col] ?? 0) > 0) {
                        $conds[] = $hours((int) $f[$col] / 3600) . ' ' . (Cat::conditions()[$ck] ?? '');
                    }
                }
                ?>
            <li class="pcs-pending__row">
                <span class="pcs-pending__badge"><?= ($f['source'] ?? '') === 'mod' ? 'JEU' : 'SAISI' ?></span>
                <span class="pcs-pending__main">
                    <a class="pcs-pending__who" href="<?= $h($logbookUrl($fUid)) ?>"><?= $h($fName) ?></a>
                    <span class="pcs-pending__what"><?= $h(date('d/m/Y', strtotime((string) $f['flown_on']) ?: time())) ?> · <?= $h((string) ($f['vehicle_label'] ?: $f['vehicle_class'])) ?> · <?= $h($hours((int) $f['seconds'] / 3600)) ?><?= $conds !== [] ? ' · dont ' . $h(implode(', ', $conds)) : '' ?><?= (int) ($f['landings'] ?? 0) > 0 ? ' · ' . (int) $f['landings'] . ' atterrissage(s)' : '' ?></span>
                    <?php if (trim((string) ($f['remarks'] ?? '')) !== ''): ?><span class="pcs-pending__what">« <?= $h((string) $f['remarks']) ?> »</span><?php endif; ?>
                    <?php if ($mineFlight): ?><span class="pcs-pending__hint">Votre propre vol : un autre instructeur doit le contresigner.</span>
                    <?php elseif (!$canSign): ?><span class="pcs-pending__hint">Réservé aux instructeurs <?= $h(mb_strtolower(Cat::trackLabel($fKind))) ?> de la communauté.</span><?php endif; ?>
                </span>
                <?php if ($canSign && $allowed): ?>
                <form method="post" action="<?= $h(url($base . '/carnet/vols/' . (int) $f['id'])) ?>" class="pcs-pending__actions pcs-flights__form">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="retour" value="<?= $h($base) ?>">
                    <input type="text" name="note" maxlength="255" placeholder="Motif (obligatoire pour refuser)" aria-label="Motif">
                    <button type="submit" name="decision" value="contresigner" class="ath-btn ath-btn--solid">Contresigner</button>
                    <button type="submit" name="decision" value="refuser" class="ath-btn">Refuser</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php foreach ($byTrack as $track => $rows): ?>
    <section class="pcs-track ath-card" aria-labelledby="pcs-track-<?= $h($track) ?>">
        <header class="pcs-sec__head">
            <h2 id="pcs-track-<?= $h($track) ?>">Filière <?= $h(mb_strtolower(Cat::trackLabel($track))) ?></h2>
            <p>De gauche à droite, l’ordre du parcours. Chaque palier demande le précédent.</p>
        </header>
        <?php foreach ($rows as $rowKey => $row): ?>
        <div class="pcs-ladder">
            <h3 class="pcs-ladder__title"><?= $h((string) $row['label']) ?></h3>
            <ol class="pcs-ladder__steps">
                <?php foreach ($row['rules'] as $r):
                    $g = $r['groups'];
                    $direct = ($r['award_mode'] ?? '') === Cat::MODE_DIRECT;
                    $off = empty($r['is_enabled']);
                    $near = array_slice($g[Eng::PROGRESS], 0, 3);
                    ?>
                <li class="pcs-step<?= $off ? ' is-off' : '' ?>" id="palier-<?= (int) $r['id'] ?>">
                    <div class="pcs-step__top">
                        <span class="pcs-step__short"><?= $h((string) ($tiers[$r['tier']]['short'] ?? '')) ?></span>
                        <span class="pcs-step__mode <?= $direct ? 'is-auto' : 'is-ip' ?>"><?= $direct ? 'Automatique' : 'Instructeur' ?></span>
                    </div>
                    <a class="pcs-step__name" href="<?= $h(url('back-office/referentiels/qualifications/' . (int) $r['definition_id'] . '/edit')) ?>"><?= $h(Cat::tierLabel((string) $r['tier'])) ?></a>
                    <p class="pcs-step__target"><?= $h($target($r)) ?></p>
                    <p class="pcs-step__counts">
                        <span><b><?= count($g[Eng::HELD]) ?></b> titulaire<?= count($g[Eng::HELD]) > 1 ? 's' : '' ?></span>
                        <?php if ($g[Eng::PENDING] !== []): ?><span class="is-warn"><b><?= count($g[Eng::PENDING]) ?></b> à valider</span><?php endif; ?>
                        <?php if ($g[Eng::ELIGIBLE] !== []): ?><span class="is-ok"><b><?= count($g[Eng::ELIGIBLE]) ?></b> éligible<?= count($g[Eng::ELIGIBLE]) > 1 ? 's' : '' ?></span><?php endif; ?>
                    </p>
                    <?php if ($near !== []): ?>
                    <ul class="pcs-step__near" aria-label="Les plus proches du palier">
                        <?php foreach ($near as $it): ?>
                        <li>
                            <a class="pcs-step__who" href="<?= $h($logbookUrl((int) $it['user_id'])) ?>"><?= $h($who((int) $it['user_id'])) ?></a>
                            <span class="pcs-bar" role="img" aria-label="<?= (int) $it['pct'] ?> %"><span style="width:<?= (int) $it['pct'] ?>%"></span></span>
                            <span class="pcs-step__h"><?= $h($hours((float) $it['scope_hours'])) ?><?= !$it['prereq_ok'] ? ' · palier précédent manquant' : '' ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <details class="pcs-step__edit">
                        <summary>Régler</summary>
                        <form method="post" action="<?= $h(url($base . '/paliers/' . (int) $r['id'])) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <label>Heures sur <?= $h((string) ($r['scope_label'] ?? '')) ?>
                                <input type="number" name="min_scope_hours" min="0" max="9999" step="0.5" value="<?= $h(rtrim(rtrim(number_format((float) $r['min_scope_hours'], 2, '.', ''), '0'), '.')) ?>">
                            </label>
                            <label>Heures en <?= $h(mb_strtolower(Cat::trackLabel((string) $r['track']))) ?> (tous appareils)
                                <input type="number" name="min_track_hours" min="0" max="9999" step="0.5" value="<?= $h(rtrim(rtrim(number_format((float) $r['min_track_hours'], 2, '.', ''), '0'), '.')) ?>">
                            </label>
                            <?php $rc = Cat::tier((string) $r['tier'])['cond'] ?? null; if ($rc !== null): ?>
                            <label>Heures <?= $h(Cat::conditions()[$rc] ?? '') ?> contresignées au carnet
                                <input type="number" name="min_cond_hours" min="0" max="9999" step="0.5" value="<?= $h(rtrim(rtrim(number_format((float) ($r['min_cond_hours'] ?? 0), 2, '.', ''), '0'), '.')) ?>">
                            </label>
                            <?php endif; ?>
                            <label>Attribution
                                <select name="award_mode">
                                    <option value="propose"<?= !$direct ? ' selected' : '' ?>>Proposée, validée par un instructeur</option>
                                    <option value="direct"<?= $direct ? ' selected' : '' ?>>Automatique dès les heures atteintes</option>
                                </select>
                            </label>
                            <label class="pcs-check"><input type="checkbox" name="is_enabled" value="1"<?= $off ? '' : ' checked' ?>> Palier actif</label>
                            <button type="submit" class="ath-btn ath-btn--solid"<?= $allowed ? '' : ' disabled' ?>>Enregistrer</button>
                        </form>
                    </details>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

    <section class="pcs-install ath-card" id="installer" aria-labelledby="pcs-install-title">
        <header class="pcs-sec__head">
            <h2 id="pcs-install-title"><?= $rules === [] ? 'Installer un cursus' : 'Ajouter des paliers ou des appareils' ?></h2>
            <p>Les qualifications sont créées dans votre référentiel (catégorie « Pilotage »), avec leurs prérequis. Celles déjà installées sont reprises sans doublon. Les seuils restent modifiables ensuite.</p>
        </header>
        <?php foreach ($tracks as $trackKey => $trackInfo):
            $flownHere = $flown[$trackKey] ?? [];
            $flownKeys = array_column($flownHere, 'key');
            ?>
        <details class="pcs-install__track"<?= $trackKey === Cat::TRACK_HELI ? ' open' : '' ?>>
            <summary>Filière <?= $h(mb_strtolower($trackInfo['label'])) ?><?= $flownHere !== [] ? ' · ' . count($flownHere) . ' type(s) déjà piloté(s) dans la communauté' : '' ?></summary>
            <form method="post" action="<?= $h(url($base . '/installer')) ?>" class="pcs-install__form">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="track" value="<?= $h($trackKey) ?>">
                <fieldset>
                    <legend>Appareils (qualification de type, pilote opérationnel, commandant de bord, instructeur)</legend>
                    <div class="pcs-chips">
                        <?php foreach ($flownHere as $f): ?>
                        <label class="pcs-chip is-flown">
                            <input type="checkbox" name="families[]" value="<?= $h($f['key']) ?>" checked>
                            <span><?= $h($f['label']) ?> <small><?= $h(number_format($f['hours'], 1, ',', ' ')) ?> h · <?= (int) $f['pilots'] ?> pilote<?= $f['pilots'] > 1 ? 's' : '' ?></small></span>
                            <?php if (str_starts_with($f['key'], 'class:')): ?><input type="hidden" name="family_labels[<?= $h($f['key']) ?>]" value="<?= $h($f['label']) ?>"><?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                        <?php foreach ($knownFamilies[$trackKey] ?? [] as $fKey => $fLabel):
                            if (in_array($fKey, $flownKeys, true)) {
                                continue;
                            } ?>
                        <label class="pcs-chip">
                            <input type="checkbox" name="families[]" value="<?= $h($fKey) ?>">
                            <span><?= $h($fLabel) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Paliers</legend>
                    <div class="pcs-tiers">
                        <?php foreach ($tiers as $tKey => $t): ?>
                        <label class="pcs-tier">
                            <input type="checkbox" name="tiers[]" value="<?= $h($tKey) ?>" checked>
                            <span class="pcs-tier__body">
                                <span class="pcs-tier__name"><b><?= $h($t['short']) ?></b> <?= $h($t['label']) ?> <em><?= $t['mode'] === Cat::MODE_DIRECT ? 'automatique' : 'instructeur' ?></em></span>
                                <span class="pcs-tier__game">Jeu : <?= $h($hours((float) $t['min_scope_hours'])) ?> <?= $t['per'] === Cat::PER_FAMILY ? 'sur le type' : 'en ' . $h($trackInfo['noun']) ?><?= (float) $t['min_track_hours'] > 0 ? ', ' . $h($hours((float) $t['min_track_hours'])) . ' en ' . $h($trackInfo['noun']) : '' ?><?= $t['cond'] !== null ? ', dont ' . $h($hours((float) $t['min_cond_hours'])) . ' ' . $h(Cat::conditions()[$t['cond']] ?? '') . ' au carnet' : '' ?><?= $t['currency_days'] ? ' · à jour ' . (int) $t['currency_days'] . ' jours après le dernier vol' : '' ?></span>
                                <span class="pcs-tier__real">Vraie vie : <?= $h((string) ($t['real'][$trackKey] ?? '')) ?></span>
                                <?php if ($t['data_note'] !== ''): ?><span class="pcs-tier__note"><?= $h($t['data_note']) ?></span><?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <button type="submit" class="ath-btn ath-btn--solid"<?= $allowed && $schemaReady ? '' : ' disabled' ?>>Installer la filière <?= $h(mb_strtolower($trackInfo['label'])) ?></button>
            </form>
        </details>
        <?php endforeach; ?>
        <p class="pcs-install__foot">Les seuils du jeu sont ramenés à l’échelle d’une communauté qui vole quelques heures par semaine : les minimums réels se comptent en dizaines ou centaines d’heures. Les références (EASA Part-FCL, FAA 14 CFR Part 61, US Army TC 3-04.11) sont des repères publics, pas une certification.</p>
    </section>
</div>
