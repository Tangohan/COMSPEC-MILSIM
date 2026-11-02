<?php
declare(strict_types=1);

use App\Services\Personnel\PilotSyllabusStepService as Steps;
use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog as Cat;

/**
 * Cursus pilote > Paliers de la communauté : création, réglage, ordre, copie et archivage des paliers propres à la
 * communauté. Les conditions deviennent la règle automatique de la qualification délivrée (moteur commun).
 * Inclus par pilot_syllabus.php (mêmes variables).
 */
$customSteps = is_array($customSteps ?? null) ? $customSteps : [];
$scopeOptions = is_array($scopeOptions ?? null) ? $scopeOptions : [];
$definitionNames = is_array($definitionNames ?? null) ? $definitionNames : [];
$freeDefinitions = is_array($freeDefinitions ?? null) ? $freeDefinitions : [];
$stepTracks = [Cat::TRACK_HELI => 'Hélicoptère', Cat::TRACK_PLANE => 'Avion', PilotFleet::TRACK_OTHER => 'Autre (drone, mixte…)'];
$scopeLabels = [];
$scopeGroups = ['' => [], 'family' => [], 'group' => [], 'class' => []];
foreach ($scopeOptions as [$v, $l, $g]) {
    $scopeLabels[$v] = $l;
    $scopeGroups[$g][] = [$v, $l];
}
$scopeLabels[''] = 'Tous les aéronefs';
$stepNames = [];
foreach ($customSteps as $s) {
    $stepNames[(int) $s['definition_id']] = (string) $s['definition_name'];
}
$num = static fn (float $v): string => $v > 0 ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '';

$scopeSelect = static function (string $name, string $selected, string $emptyLabel) use ($h, $scopeGroups): void {
    ?>
    <select name="<?= $h($name) ?>">
        <option value=""><?= $h($emptyLabel) ?></option>
        <?php foreach ($scopeGroups[''] as [$v, $l]): if ($v === '') { continue; } ?><option value="<?= $h($v) ?>"<?= $v === $selected ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
        <?php if ($scopeGroups['group'] !== []): ?>
        <optgroup label="Famille d’appareils">
            <?php foreach ($scopeGroups['group'] as [$v, $l]): ?><option value="<?= $h($v) ?>"<?= $v === $selected ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
        </optgroup>
        <?php endif; ?>
        <?php if ($scopeGroups['family'] !== []): ?>
        <optgroup label="Appareil">
            <?php foreach ($scopeGroups['family'] as [$v, $l]): ?><option value="<?= $h($v) ?>"<?= $v === $selected ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
        </optgroup>
        <?php endif; ?>
        <?php if ($scopeGroups['class'] !== []): ?>
        <optgroup label="Classe Arma précise vue en jeu">
            <?php foreach ($scopeGroups['class'] as [$v, $l]): ?><option value="<?= $h($v) ?>"<?= $v === $selected ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
        </optgroup>
        <?php endif; ?>
    </select>
    <?php
};

/** @param array<string, mixed>|null $step */
$stepForm = static function (?array $step) use ($h, $base, $num, $scopeSelect, $stepTracks, $stepNames, $definitionNames, $freeDefinitions, $allowed): void {
    $f = $step['form'] ?? ['name' => '', 'track' => Cat::TRACK_HELI, 'total_hours' => 0.0, 'scopes' => [], 'flights' => 0, 'flights_scope' => '', 'night_hours' => 0.0, 'ifr_hours' => 0.0, 'formation_hours' => 0.0, 'prereq_step' => 0, 'prereq_qualification' => 0, 'instructor' => true];
    $defId = (int) ($step['definition_id'] ?? 0);
    $uid = $step !== null ? 's' . (int) $step['id'] : 'new';
    $scopes = array_pad($f['scopes'], Steps::MAX_SCOPES, ['scope' => '', 'hours' => 0.0]);
    ?>
    <form method="post" action="<?= $h(url($base . '/paliers-communaute' . ($step !== null ? '/' . (int) $step['id'] : ''))) ?>" class="pcs-sform">
        <?= \App\Core\Csrf::field() ?>
        <div class="pcs-sform__row">
            <label class="pcs-sform__wide">Nom du palier
                <input type="text" name="name" maxlength="120" required value="<?= $h((string) $f['name']) ?>" placeholder="Ex. : Pilote EVASAN, Chef de patrouille Tigre…">
            </label>
            <label>Abrégé
                <input type="text" name="short" maxlength="30" value="<?= $h((string) ($step['definition_short_name'] ?? '')) ?>" placeholder="Ex. : EVASAN">
            </label>
            <label>Filière
                <select name="track">
                    <?php foreach ($stepTracks as $tk => $tl): ?><option value="<?= $h($tk) ?>"<?= $f['track'] === $tk ? ' selected' : '' ?>><?= $h($tl) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php if ($step === null): ?>
        <label class="pcs-sform__line">Qualification délivrée
            <select name="definition_id">
                <option value="0">Nouvelle qualification portant le nom du palier (catégorie « Pilotage »)</option>
                <?php foreach ($freeDefinitions as $id => $name): ?><option value="<?= (int) $id ?>"><?= $h($name) ?></option><?php endforeach; ?>
            </select>
            <small>Choisir une qualification existante remplace sa règle automatique par les conditions de ce palier.</small>
        </label>
        <?php endif; ?>

        <fieldset class="pcs-sform__set">
            <legend>Heures de vol relevées en jeu</legend>
            <label>Au total sur la filière
                <span class="pcs-unit"><input type="number" name="total_hours" min="0" max="9999" step="0.5" value="<?= $h($num((float) $f['total_hours'])) ?>" placeholder="0"><span>h</span></span>
            </label>
            <?php foreach ($scopes as $i => $sc): ?>
            <div class="pcs-sform__scope">
                <label>Heures sur
                    <?php $scopeSelect('scope[' . $i . ']', (string) $sc['scope'], '— Choisir un appareil ou une famille —'); ?>
                </label>
                <label>Heures
                    <span class="pcs-unit"><input type="number" name="scope_hours[<?= (int) $i ?>]" min="0" max="9999" step="0.5" value="<?= $h($num((float) $sc['hours'])) ?>" placeholder="0"><span>h</span></span>
                </label>
            </div>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="pcs-sform__set">
            <legend>Vols et carnet de vol</legend>
            <div class="pcs-sform__scope">
                <label>Nombre de vols
                    <input type="number" name="flights" min="0" max="9999" step="1" value="<?= (int) $f['flights'] > 0 ? (int) $f['flights'] : '' ?>" placeholder="0">
                </label>
                <label>Sur
                    <?php $scopeSelect('flights_scope', (string) $f['flights_scope'], 'Tous les aéronefs'); ?>
                </label>
            </div>
            <div class="pcs-sform__row">
                <label>De nuit
                    <span class="pcs-unit"><input type="number" name="night_hours" min="0" max="9999" step="0.5" value="<?= $h($num((float) $f['night_hours'])) ?>" placeholder="0"><span>h</span></span>
                </label>
                <label>Aux instruments (IFR)
                    <span class="pcs-unit"><input type="number" name="ifr_hours" min="0" max="9999" step="0.5" value="<?= $h($num((float) $f['ifr_hours'])) ?>" placeholder="0"><span>h</span></span>
                </label>
                <label>En patrouille
                    <span class="pcs-unit"><input type="number" name="formation_hours" min="0" max="9999" step="0.5" value="<?= $h($num((float) $f['formation_hours'])) ?>" placeholder="0"><span>h</span></span>
                </label>
            </div>
            <small class="pcs-sform__hint">Un vol compte un par mission et par appareil relevé par le mod, plus les vols saisis au carnet et contresignés. Les heures de nuit, aux instruments et en patrouille viennent du carnet de vol, une fois contresignées par un instructeur.</small>
        </fieldset>

        <fieldset class="pcs-sform__set">
            <legend>Prérequis et validation</legend>
            <div class="pcs-sform__row">
                <label>Palier précédent
                    <select name="prereq_step">
                        <option value="0">Aucun</option>
                        <?php foreach ($stepNames as $id => $name): if ($id === $defId) { continue; } ?><option value="<?= (int) $id ?>"<?= (int) $f['prereq_step'] === $id ? ' selected' : '' ?>><?= $h($name) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Autre qualification détenue
                    <select name="prereq_qualification">
                        <option value="0">Aucune</option>
                        <?php foreach ($definitionNames as $id => $name): if ($id === $defId) { continue; } ?><option value="<?= (int) $id ?>"<?= (int) $f['prereq_qualification'] === $id ? ' selected' : '' ?>><?= $h($name) ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="pcs-check"><input type="checkbox" name="instructor" value="1"<?= !empty($f['instructor']) ? ' checked' : '' ?>> Validation par un instructeur ou un responsable (sinon délivrée automatiquement dès les conditions remplies)</label>
        </fieldset>
        <div class="pcs-sform__foot">
            <button type="submit" class="ath-btn ath-btn--solid"<?= $allowed ? '' : ' disabled' ?>><?= $step === null ? 'Créer le palier' : 'Enregistrer le palier' ?></button>
            <?php if ($step !== null && $defId > 0): ?>
            <a class="pcs-link" href="<?= $h(url('back-office/referentiels/qualifications/' . $defId)) ?>">Fiche de la qualification (titulaires, réglages avancés)</a>
            <?php endif; ?>
        </div>
    </form>
    <?php
};

$byStepTrack = [];
foreach ($customSteps as $s) {
    $byStepTrack[(string) $s['track']][] = $s;
}
?>
    <section class="pcs-custom ath-card" id="paliers-communaute" aria-labelledby="pcs-custom-title">
        <header class="pcs-sec__head">
            <h2 id="pcs-custom-title">Paliers de la communauté <?php if ($customSteps !== []): ?><span class="rql__count"><?= count($customSteps) ?></span><?php endif; ?></h2>
            <p>En plus du cursus modèle, créez vos propres paliers : heures au total ou sur un appareil ou une famille, nombre de vols, heures de nuit ou aux instruments contresignées, palier précédent. Chaque palier délivre une qualification du référentiel, évaluée par le même moteur que les autres qualifications automatiques (toutes les heures, ou avec « Évaluer maintenant »).</p>
        </header>
        <?php if (empty($stepsReady)): ?>
        <p class="pcs-notice pcs-notice--warn" role="status">Les paliers de la communauté arrivent avec la prochaine mise à jour du site : l’administrateur doit lancer la migration « pilot_fleet_and_steps ».</p>
        <?php else: ?>
        <?php if ($customSteps === []): ?>
        <div class="pcs-empty pcs-empty--box">
            <strong>Aucun palier propre pour l’instant.</strong>
            <span>Exemples : « Pilote EVASAN » après 15 h sur la famille Transport et 5 vols de nuit, « Chef de patrouille Tigre » après le palier Pilote opérationnel et 3 h en patrouille.</span>
        </div>
        <?php endif; ?>
        <?php foreach ($byStepTrack as $tk => $list): ?>
        <div class="pcs-ladder">
            <h3 class="pcs-ladder__title">Filière <?= $h(mb_strtolower($stepTracks[$tk] ?? (string) $tk)) ?></h3>
            <ol class="pcs-csteps">
                <?php foreach ($list as $pos => $s):
                    $f = $s['form'];
                    $sum = $s['summary'];
                    $archived = !empty($s['archived']);
                    ?>
                <li class="pcs-cstep<?= $archived ? ' is-off' : '' ?>" id="etape-<?= (int) $s['id'] ?>">
                    <div class="pcs-cstep__head">
                        <span class="pcs-cstep__n"><?= (int) $pos + 1 ?></span>
                        <span class="pcs-cstep__title">
                            <a class="pcs-step__name" href="<?= $h(url('back-office/referentiels/qualifications/' . (int) $s['definition_id'])) ?>"><?= $h((string) $s['definition_name']) ?></a>
                            <span class="pcs-step__mode <?= !empty($f['instructor']) ? 'is-ip' : 'is-auto' ?>"><?= !empty($f['instructor']) ? 'Instructeur' : 'Automatique' ?></span>
                            <?php if ($archived): ?><span class="pcs-tag">Archivé</span><?php endif; ?>
                        </span>
                        <form method="post" action="<?= $h(url($base . '/paliers-communaute/' . (int) $s['id'] . '/action')) ?>" class="pcs-cstep__acts">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" name="action" value="monter" class="pcs-icon-btn" title="Monter" aria-label="Monter"<?= $pos === 0 || !$allowed ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></button>
                            <button type="submit" name="action" value="descendre" class="pcs-icon-btn" title="Descendre" aria-label="Descendre"<?= $pos === count($list) - 1 || !$allowed ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
                            <button type="submit" name="action" value="dupliquer" class="ath-btn ath-btn--sm"<?= $allowed ? '' : ' disabled' ?>>Dupliquer</button>
                            <button type="submit" name="action" value="<?= $archived ? 'reactiver' : 'archiver' ?>" class="ath-btn ath-btn--sm"<?= $allowed ? '' : ' disabled' ?>><?= $archived ? 'Réactiver' : 'Archiver' ?></button>
                        </form>
                    </div>
                    <ul class="pcs-cstep__conds">
                        <?php if ((float) $f['total_hours'] > 0): ?><li><b><?= $h($hours((float) $f['total_hours'])) ?></b> de vol au total</li><?php endif; ?>
                        <?php foreach ($f['scopes'] as $sc): ?><li><b><?= $h($hours((float) $sc['hours'])) ?></b> de vol<span> · <?= $h($scopeLabels[$sc['scope']] ?? (string) $sc['scope']) ?></span></li><?php endforeach; ?>
                        <?php if ((int) $f['flights'] > 0): ?><li><b><?= (int) $f['flights'] ?></b> vol(s)<span> · <?= $h($scopeLabels[$f['flights_scope']] ?? (string) $f['flights_scope']) ?></span></li><?php endif; ?>
                        <?php if ((float) $f['night_hours'] > 0): ?><li><b><?= $h($hours((float) $f['night_hours'])) ?></b> de nuit contresignées au carnet</li><?php endif; ?>
                        <?php if ((float) $f['ifr_hours'] > 0): ?><li><b><?= $h($hours((float) $f['ifr_hours'])) ?></b> aux instruments contresignées au carnet</li><?php endif; ?>
                        <?php if ((float) $f['formation_hours'] > 0): ?><li><b><?= $h($hours((float) $f['formation_hours'])) ?></b> en patrouille contresignées au carnet</li><?php endif; ?>
                        <?php if ((int) $f['prereq_step'] > 0): ?><li>Après le palier « <?= $h($stepNames[(int) $f['prereq_step']] ?? ($definitionNames[(int) $f['prereq_step']] ?? '')) ?> »</li><?php endif; ?>
                        <?php if ((int) $f['prereq_qualification'] > 0): ?><li>Qualification « <?= $h($definitionNames[(int) $f['prereq_qualification']] ?? '') ?> » détenue</li><?php endif; ?>
                        <?php if ($f['extra_items'] !== [] || $f['extra_groups'] !== []): ?><li class="is-muted">Autres conditions réglées sur la fiche de la qualification</li><?php endif; ?>
                    </ul>
                    <p class="pcs-step__counts">
                        <span><b><?= (int) $sum['held'] ?></b> titulaire<?= $sum['held'] > 1 ? 's' : '' ?></span>
                        <?php if ($sum['pending'] !== []): ?><span class="is-warn"><b><?= count($sum['pending']) ?></b> à valider</span><?php endif; ?>
                        <?php if ($sum['eligible'] > 0): ?><span class="is-ok"><b><?= (int) $sum['eligible'] ?></b> éligible<?= $sum['eligible'] > 1 ? 's' : '' ?></span><?php endif; ?>
                    </p>
                    <?php if ($sum['near'] !== []): ?>
                    <ul class="pcs-step__near" aria-label="Les plus proches du palier">
                        <?php foreach ($sum['near'] as $n):
                            $nm = $n['member'];
                            $nName = trim((string) $nm['display_name']) !== '' ? (string) $nm['display_name'] : ((string) $nm['callsign'] !== '' ? (string) $nm['callsign'] : 'Membre');
                            ?>
                        <li>
                            <a class="pcs-step__who" href="<?= $h($logbookUrl((int) $nm['user_id'])) ?>"><?= $h($nName) ?></a>
                            <span class="pcs-bar" role="img" aria-label="<?= (int) $n['pct'] ?> %"><span style="width:<?= (int) $n['pct'] ?>%"></span></span>
                            <span class="pcs-step__h"><?= (int) $n['pct'] ?> %<?= $n['missing'] !== [] ? ' · prérequis manquant' : '' ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <details class="pcs-step__edit">
                        <summary>Régler</summary>
                        <?php $stepForm($s); ?>
                    </details>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endforeach; ?>
        <details class="pcs-install__track pcs-custom__new"<?= $customSteps === [] ? ' open' : '' ?>>
            <summary>Créer un palier</summary>
            <div class="pcs-install__form"><?php $stepForm(null); ?></div>
        </details>
        <?php endif; ?>
    </section>
