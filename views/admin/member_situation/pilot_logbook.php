<?php
declare(strict_types=1);

use App\Services\Personnel\PilotQualificationEngine as Eng;
use App\Support\PilotSyllabusCatalog as Cat;

/**
 * Carnet de vol : « Mon carnet de vol » (mine) ou carnet d'un membre vu par un instructeur.
 *
 * @var bool $schemaReady
 * @var bool $mine
 * @var int $memberId
 * @var list<array<string, mixed>> $entries
 * @var array{total: int, by_aircraft: list<array{label: string, kind: string, seconds: int}>, cond: array<string, int>, landings: int, pending: int, flights: int, carried: int} $summary
 * @var array<string, bool> $canReview
 * @var array<string, string> $knownFamilies
 * @var array<string, string> $crewRoles
 */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$dur = static fn (int $s): string => $s > 0 ? Eng::formatHours($s / 3600) : '—';
$hm = static fn (int $s): string => $s > 0 ? sprintf('%d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '';
$mineBase = 'back-office/ma-situation/carnet-de-vol';
$reviewBase = 'back-office/referentiels/qualifications/cursus-pilote/carnet';
$statusLabel = [
    'verified' => ['Relevé en jeu', 'is-ok'],
    'declared' => ['À contresigner', 'is-warn'],
    'countersigned' => ['Contresigné', 'is-ok'],
    'rejected' => ['Refusé', 'is-bad'],
];
$today = date('Y-m-d');
$fields = static function (array $e, bool $flight) use ($h, $hm, $knownFamilies, $crewRoles, $today): void {
    $label = (string) ($e['vehicle_label'] ?? '');
    $known = array_search($label, $knownFamilies, true);
    ?>
    <?php if ($flight): ?>
    <label>Date<input type="date" name="flown_on" required max="<?= $h($today) ?>" value="<?= $h((string) ($e['flown_on'] ?? $today)) ?>"></label>
    <label>Durée (h:mm)<input type="text" name="duration" required inputmode="numeric" placeholder="1:30" value="<?= $h($hm((int) ($e['seconds'] ?? 0))) ?>"></label>
    <label>Appareil
        <select name="aircraft">
            <option value="">Autre (préciser)</option>
            <?php foreach ($knownFamilies as $k => $l): ?>
            <option value="<?= $h($k) ?>"<?= $known === $k ? ' selected' : '' ?>><?= $h($l) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Autre appareil<input type="text" name="aircraft_other" maxlength="120" placeholder="ex. Bell 412" value="<?= $h($known === false ? $label : '') ?>"></label>
    <label>Type
        <select name="vehicle_kind">
            <option value="heli"<?= ($e['vehicle_kind'] ?? 'heli') !== 'plane' ? ' selected' : '' ?>>Hélicoptère</option>
            <option value="plane"<?= ($e['vehicle_kind'] ?? '') === 'plane' ? ' selected' : '' ?>>Avion</option>
        </select>
    </label>
    <label>Mission<input type="text" name="mission" maxlength="160" placeholder="facultatif" value="<?= $h((string) ($e['mission_key'] ?? '')) ?>"></label>
    <?php endif; ?>
    <label>Fonction à bord
        <select name="crew_role">
            <option value="">—</option>
            <?php foreach ($crewRoles as $k => $l): ?>
            <option value="<?= $h($k) ?>"<?= ($e['crew_role'] ?? '') === $k ? ' selected' : '' ?>><?= $h($l) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Dont de nuit<input type="text" name="night" inputmode="numeric" placeholder="0:00" value="<?= $h($hm((int) ($e['night_seconds'] ?? 0))) ?>"></label>
    <label>Dont aux instruments<input type="text" name="ifr" inputmode="numeric" placeholder="0:00" value="<?= $h($hm((int) ($e['ifr_seconds'] ?? 0))) ?>"></label>
    <label>Dont en patrouille<input type="text" name="formation" inputmode="numeric" placeholder="0:00" value="<?= $h($hm((int) ($e['formation_seconds'] ?? 0))) ?>"></label>
    <label>Atterrissages<input type="number" name="landings" min="0" max="999" value="<?= (int) ($e['landings'] ?? 0) ?>"></label>
    <label class="pcs-lb__wide">Remarques<input type="text" name="remarks" maxlength="500" placeholder="Exercice, équipage, instructeur à bord…" value="<?= $h((string) ($e['remarks'] ?? '')) ?>"></label>
    <?php
};
?>
<div class="pcs pcs-lb">
    <?php if (!$schemaReady): ?>
    <p class="pcs-notice pcs-notice--warn" role="status"><strong>Le carnet de vol n’est pas encore prêt sur ce serveur.</strong> L’administrateur doit lancer les migrations (étape « pilot_logbook »).</p>
    <?php endif; ?>

    <div class="bo-qual__toolbar ath-rise" role="group" aria-label="Actions du carnet">
        <?php if ($mine): ?>
        <a href="#ajouter" class="ath-btn ath-btn--solid">Ajouter un vol</a>
        <a href="<?= $h(url('back-office/ma-situation/qualifications')) ?>" class="ath-btn">Mon cursus pilote</a>
        <?php else: ?>
        <a href="<?= $h(url('back-office/referentiels/qualifications/cursus-pilote')) ?>" class="ath-btn">Retour au cursus pilote</a>
        <a href="<?= $h(url('back-office/ressources/effectifs/membres/' . $memberId)) ?>#qualifications" class="ath-btn">Fiche du membre</a>
        <?php endif; ?>
    </div>

    <section class="pcs-lb__totals ath-card" aria-label="Totaux du carnet">
        <div class="pcs-lb__total">
            <span>Total des heures de vol</span>
            <b><?= $h($dur($summary['total'])) ?></b>
            <small><?= (int) $summary['flights'] ?> vol(s) au carnet<?= $summary['carried'] > 0 ? ' · dont ' . $h($dur($summary['carried'])) . ' reportées' : '' ?></small>
        </div>
        <div class="pcs-lb__total"><span>De nuit</span><b><?= $h($dur($summary['cond']['night'])) ?></b><small>contresignées</small></div>
        <div class="pcs-lb__total"><span>Aux instruments</span><b><?= $h($dur($summary['cond']['ifr'])) ?></b><small>contresignées</small></div>
        <div class="pcs-lb__total"><span>En patrouille</span><b><?= $h($dur($summary['cond']['formation'])) ?></b><small>contresignées</small></div>
        <div class="pcs-lb__total"><span>Atterrissages</span><b><?= (int) $summary['landings'] ?></b><small><?= (int) $summary['pending'] ?> vol(s) à contresigner</small></div>
        <?php if ($summary['by_aircraft'] !== []): ?>
        <ul class="pcs-lb__types" aria-label="Heures par appareil">
            <?php foreach ($summary['by_aircraft'] as $a): ?>
            <li><span><?= $h($a['label']) ?></span><b><?= $h($dur($a['seconds'])) ?></b></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <?php if ($mine && $schemaReady): ?>
    <details class="pcs-lb__add ath-card" id="ajouter"<?= $entries === [] ? ' open' : '' ?>>
        <summary>Ajouter un vol à mon carnet</summary>
        <p class="pcs-lb__hint">Notez un vol que le mod n’a pas relevé (entraînement sur un autre serveur, vol en double commande…), ou complétez vos vols relevés avec les heures de nuit, aux instruments ou en patrouille. Un instructeur contresigne ; vos heures comptent alors pour le cursus.</p>
        <form method="post" action="<?= $h(url($mineBase . '/ajouter')) ?>" class="pcs-lb__form">
            <?= \App\Core\Csrf::field() ?>
            <?php $fields([], true); ?>
            <div class="pcs-lb__wide"><button type="submit" class="ath-btn ath-btn--solid">Ajouter au carnet</button></div>
        </form>
    </details>
    <?php endif; ?>

    <section class="pcs-lb__book ath-card" aria-labelledby="pcs-lb-title">
        <header class="pcs-sec__head">
            <h2 id="pcs-lb-title">Vols <span class="rql__count"><?= count($entries) ?></span></h2>
            <p>Les vols « relevés en jeu » viennent du mod, rangés par mission et par jour. Les vols saisis et les heures de nuit, IFR ou patrouille comptent une fois contresignés.</p>
        </header>
        <?php if ($entries === []): ?>
        <p class="pcs-lb__empty">Aucun vol au carnet pour l’instant. Les prochains vols en jeu s’y rangent tout seuls<?= $summary['carried'] > 0 ? ' ; les ' . $h($dur($summary['carried'])) . ' déjà relevées figurent en report dans le total' : '' ?>.</p>
        <?php else: ?>
        <div class="pcs-lb__scroll">
            <table class="pcs-lb__table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Appareil</th>
                        <th scope="col">Mission</th>
                        <th scope="col">Fonction</th>
                        <th scope="col" class="num">Durée</th>
                        <th scope="col" class="num">Nuit</th>
                        <th scope="col" class="num">IFR</th>
                        <th scope="col" class="num">Patr.</th>
                        <th scope="col" class="num">Att.</th>
                        <th scope="col">Visa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e):
                        $eid = (int) $e['id'];
                        $st = (string) ($e['status'] ?? 'verified');
                        [$stLabel, $stTone] = $statusLabel[$st] ?? ['', ''];
                        $fromMod = ($e['source'] ?? '') === 'mod';
                        $reviewer = trim((string) ($e['reviewer_name'] ?? '')) ?: trim((string) ($e['reviewer_callsign'] ?? ''));
                        $kind = (string) ($e['vehicle_kind'] ?? 'heli');
                        ?>
                    <tr id="vol-<?= $eid ?>" class="<?= $st === 'rejected' ? 'is-rejected' : '' ?>">
                        <td><?= $h(date('d/m/Y', strtotime((string) $e['flown_on']) ?: time())) ?></td>
                        <td><?= $h((string) (($e['vehicle_label'] ?? '') !== '' ? $e['vehicle_label'] : $e['vehicle_class'])) ?> <small><?= $h(Cat::trackLabel($kind)) ?></small></td>
                        <td><?= $h((string) ($e['mission_key'] ?? '')) ?: '—' ?><?= $fromMod ? ' <small class="pcs-lb__src">jeu</small>' : ' <small class="pcs-lb__src is-pilot">saisi</small>' ?></td>
                        <td><?= $h($crewRoles[(string) ($e['crew_role'] ?? '')] ?? '—') ?></td>
                        <td class="num"><?= $h($dur((int) $e['seconds'])) ?></td>
                        <td class="num"><?= $h($dur((int) $e['night_seconds'])) ?></td>
                        <td class="num"><?= $h($dur((int) $e['ifr_seconds'])) ?></td>
                        <td class="num"><?= $h($dur((int) $e['formation_seconds'])) ?></td>
                        <td class="num"><?= (int) $e['landings'] ?: '—' ?></td>
                        <td>
                            <span class="pcs-me__state <?= $h($stTone) ?>"><?= $h($stLabel) ?></span>
                            <?php if ($reviewer !== '' && in_array($st, ['countersigned', 'rejected'], true)): ?><small class="pcs-lb__by">par <?= $h($reviewer) ?></small><?php endif; ?>
                            <?php if ($st === 'rejected' && trim((string) ($e['review_note'] ?? '')) !== ''): ?><small class="pcs-lb__note">« <?= $h((string) $e['review_note']) ?> »</small><?php endif; ?>
                        </td>
                    </tr>
                    <?php if (trim((string) ($e['remarks'] ?? '')) !== '' || $mine || ($st === 'declared' && !empty($canReview[$kind]))): ?>
                    <tr class="pcs-lb__sub">
                        <td colspan="10">
                            <?php if (trim((string) ($e['remarks'] ?? '')) !== ''): ?><p class="pcs-lb__remarks"><?= $h((string) $e['remarks']) ?></p><?php endif; ?>
                            <?php if ($mine): ?>
                            <details class="pcs-step__edit">
                                <summary><?= $fromMod ? 'Annoter ce vol' : 'Modifier' ?></summary>
                                <form method="post" action="<?= $h(url($mineBase . '/' . $eid . '/modifier')) ?>" class="pcs-lb__form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <?php $fields($e, !$fromMod); ?>
                                    <div class="pcs-lb__wide"><button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button></div>
                                </form>
                                <?php if (!$fromMod && $st !== 'countersigned'): ?>
                                <form method="post" action="<?= $h(url($mineBase . '/' . $eid . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce vol de votre carnet ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="pcs-lb__del">Retirer ce vol</button>
                                </form>
                                <?php endif; ?>
                            </details>
                            <?php elseif ($st === 'declared' && !empty($canReview[$kind])): ?>
                            <form method="post" action="<?= $h(url($reviewBase . '/vols/' . $eid)) ?>" class="pcs-pending__actions pcs-flights__form">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="retour" value="<?= $h($reviewBase . '/' . $memberId) ?>">
                                <input type="text" name="note" maxlength="255" placeholder="Motif (obligatoire pour refuser)" aria-label="Motif">
                                <button type="submit" name="decision" value="contresigner" class="ath-btn ath-btn--solid">Contresigner</button>
                                <button type="submit" name="decision" value="refuser" class="ath-btn">Refuser</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
