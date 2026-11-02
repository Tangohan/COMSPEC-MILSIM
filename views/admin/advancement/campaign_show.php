<?php
require __DIR__ . '/_helpers.php';

use App\Repositories\AdvancementProposalRepository as Decisions;
use App\Services\Advancement\AdvancementProposalEngine as Engine;
use App\Support\AdvancementCodes;

$campaign = is_array($campaign ?? null) ? $campaign : [];
$candidacies = is_array($candidacies ?? null) ? $candidacies : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$billets = is_array($billets ?? null) ? $billets : [];
$progress = is_array($progress ?? null) ? $progress : [];
$proposals = is_array($proposals ?? null) ? $proposals : ['rows' => [], 'counts' => []];
$proposalSettings = is_array($proposalSettings ?? null) ? $proposalSettings : Engine::DEFAULTS;
$sources = is_array($sources ?? null) ? $sources : [];
$commission = is_array($commission ?? null) ? $commission : [];
$id = (int) ($campaign['id'] ?? 0);
$status = (string) ($campaign['status'] ?? '');
$open = $status === 'ouverte' || $status === AdvancementCodes::CAMPAIGN_OPEN;
$published = in_array($status, ['publiee', 'archivee', AdvancementCodes::CAMPAIGN_PUBLISHED, AdvancementCodes::CAMPAIGN_ARCHIVED], true);
$inWindow = ($progress['window'] ?? 'open') === 'open';
$canAct = $open && $inWindow;
$tone = match ($status) {
    'ouverte', AdvancementCodes::CAMPAIGN_OPEN, 'publiee', AdvancementCodes::CAMPAIGN_PUBLISHED => 'ok',
    'en_commission', AdvancementCodes::CAMPAIGN_IN_COMMISSION, 'cloturee', AdvancementCodes::CAMPAIGN_CLOSED => 'wait',
    default => 'quiet',
};
$quota = $progress['quota'] ?? null;
$months = (int) ($campaign['min_time_in_previous_grade_months'] ?? 0);
$qualificationId = (int) ($campaign['required_qualification_id'] ?? 0);
$previousLabel = trim((string) ($proposals['previous_label'] ?? ''));

// Propositions réparties par état.
$groups = ['pending' => [], 'review' => [], 'later' => [], 'blocked' => [], 'candidate' => [], 'dismissed' => []];
foreach (is_array($proposals['rows'] ?? null) ? $proposals['rows'] : [] as $row) {
    $st = (string) ($row['proposal']['status'] ?? '');
    $dec = (string) ($row['decision']['decision'] ?? '');
    if (!empty($row['is_candidate'])) {
        $groups['candidate'][] = $row;
    } elseif ($dec === Decisions::DECISION_DISMISSED) {
        $groups['dismissed'][] = $row;
    } elseif ($st === Engine::STATUS_PROPOSE) {
        $groups['pending'][] = $row;
    } elseif ($st === Engine::STATUS_REVIEW) {
        $groups['review'][] = $row;
    } elseif ($st === Engine::STATUS_BLOCKED) {
        $groups['blocked'][] = $row;
    } else {
        $groups['later'][] = $row;
    }
}
$listed = array_values(array_filter($candidacies, static fn (array $r): bool => (string) ($r['decision'] ?? '') === 'inscrit'));
usort($listed, static fn (array $a, array $b): int => ((int) ($a['preference_rank'] ?? 9999)) <=> ((int) ($b['preference_rank'] ?? 9999)));
?>
<div class="adv-page advc" data-adv-page="campaign">
    <?php require __DIR__ . '/_flash.php'; ?>
    <p class="advc-back"><a href="<?= adv_h(url('back-office/rh/avancement')) ?>">Toutes les campagnes</a></p>

    <section class="advc-panel advc-campaign">
        <div class="advc-card__top">
            <div>
                <p class="advc-kicker">Campagne au choix</p>
                <h2><?= adv_h((string) ($campaign['grade_label'] ?? 'Campagne')) ?> · <?= (int) ($campaign['year'] ?? 0) ?></h2>
                <p class="advc-muted">Candidatures du <?= adv_h(adv_date((string) ($campaign['opens_at'] ?? ''))) ?> au <?= adv_h(adv_date((string) ($campaign['closes_at'] ?? ''))) ?> · <?php if ($quota === null): ?>places non limitées<?php else: ?><?= (int) $quota ?> place(s)<?php endif; ?></p>
            </div>
            <span class="adv-badge adv-badge--<?= adv_h($tone) ?>"><?= adv_h(AdvancementCodes::campaignLabel($status)) ?></span>
        </div>
        <?php $stepperCurrent = (int) ($progress['step'] ?? 1); $stepperDone = !empty($progress['done']); $stepperCompact = false; require __DIR__ . '/_stepper.php'; ?>
        <dl class="advc-figures">
            <div><dt>Candidats</dt><dd><?= (int) ($progress['candidates'] ?? 0) ?></dd></div>
            <div><dt>Éligibles</dt><dd><?= (int) ($progress['eligible'] ?? 0) ?></dd></div>
            <div><dt>Propositions en attente</dt><dd><?= count($groups['pending']) ?></dd></div>
            <div><dt>Avis donnés</dt><dd><?= (int) ($progress['opinions'] ?? 0) ?></dd></div>
            <div><dt>Inscrits</dt><dd><?= (int) ($progress['listed'] ?? 0) ?><?php if ($quota !== null): ?> / <?= (int) $quota ?><?php endif; ?></dd></div>
        </dl>
        <p class="advc-next"><strong>Prochaine étape :</strong> <?= adv_h((string) ($progress['next'] ?? '')) ?></p>
        <div class="advc-actions">
            <?php if ($open): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/cloturer')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Clôturer les candidatures</button></form>
            <?php endif; ?>
            <?php if (in_array($status, ['ouverte', 'cloturee', AdvancementCodes::CAMPAIGN_OPEN, AdvancementCodes::CAMPAIGN_CLOSED], true)): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn ath-btn--solid" type="submit">Passer en commission</button></form>
            <?php endif; ?>
            <?php if (in_array($status, ['en_commission', 'publiee', AdvancementCodes::CAMPAIGN_IN_COMMISSION, AdvancementCodes::CAMPAIGN_PUBLISHED], true)): ?>
                <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/rh/avancement/' . $id . '/commission')) ?>">Ouvrir la commission</a>
            <?php endif; ?>
            <?php if (!$published): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/reverifier')) ?>"><?= \App\Core\Csrf::field() ?><button class="ath-btn" type="submit">Revérifier l’éligibilité</button></form>
            <?php endif; ?>
        </div>
    </section>

    <section class="advc-panel" aria-labelledby="advc-rules">
        <h2 id="advc-rules">Règles du grade visé</h2>
        <p class="advc-lead">Un membre est éligible quand toutes ces règles sont réunies. Elles se règlent sur le grade, dans les règles d’avancement.</p>
        <ul class="advc-rules">
            <li><strong>Grade précédent</strong><span><?php if ($previousLabel !== ''): ?>Être <?= adv_h($previousLabel) ?>, dans la même filière.<?php else: ?>Occuper le grade juste en dessous, dans la même filière.<?php endif; ?></span></li>
            <li><strong>Temps dans le grade</strong><span><?php if ($months > 0): ?><?= $months ?> mois minimum dans le grade actuel.<?php else: ?>Aucun minimum.<?php endif; ?></span></li>
            <li><strong>Qualification</strong><span><?php if ($qualificationId > 0): ?>Une qualification est exigée et doit être valide au jour du calcul.<?php else: ?>Aucune qualification exigée.<?php endif; ?></span></li>
            <?php if ((int) ($campaign['required_pass_id'] ?? 0) > 0): ?>
                <li><strong>PASS</strong><span>Les conditions du PASS lié au grade doivent être remplies.</span></li>
            <?php endif; ?>
        </ul>
        <p class="advc-muted"><a href="<?= adv_h(url('back-office/organisation/grades')) ?>">Modifier les règles d’avancement</a></p>
    </section>

    <?php if (!$published): ?>
    <section class="advc-panel" id="propositions" aria-labelledby="advc-props">
        <div class="advc-head">
            <h2 id="advc-props">Propositions du système</h2>
            <?php if ($canAct && count($groups['pending']) > 1): ?>
                <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/propositions/retenir-tout')) ?>" data-adv-confirm="Retenir toutes les propositions « Proposé » ? Une candidature sera créée pour chacune, avec sa justification.">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="ath-btn ath-btn--solid" type="submit">Retenir les <?= count($groups['pending']) ?> proposés</button>
                </form>
            <?php endif; ?>
        </div>
        <p class="advc-lead">Membres du grade précédent, classés par le système d’après leurs données réelles. Chaque proposition affiche son score, le détail des points et ce qui manque. Retenir crée la candidature ; rien n’est attribué avant la publication du tableau.</p>
        <?php if (!$open): ?>
            <p class="advc-notice">Les candidatures sont closes : les propositions restent consultables, mais ne peuvent plus être retenues.</p>
        <?php elseif (!$inWindow): ?>
            <p class="advc-notice">Hors de la période de candidatures : les propositions se retiennent entre le <?= adv_h(adv_date((string) ($campaign['opens_at'] ?? ''))) ?> et le <?= adv_h(adv_date((string) ($campaign['closes_at'] ?? ''))) ?>.</p>
        <?php endif; ?>

        <?php if (($proposals['rows'] ?? []) === []): ?>
            <div class="advc-empty">
                <p><strong>Personne au grade précédent.</strong> Le système ne trouve aucun membre dont le grade enregistré précède celui-ci dans la même filière. Vérifiez l’ordre des grades et les grades des membres.</p>
            </div>
        <?php else: ?>
            <?php
            $sections = [
                ['key' => 'pending', 'title' => 'Proposés', 'help' => 'Règles réunies, score suffisant, rien ne bloque.', 'open' => true],
                ['key' => 'review', 'title' => 'À examiner', 'help' => 'Éligibles, mais un minimum manque ou le score est sous le seuil : à vous de juger.', 'open' => true],
                ['key' => 'blocked', 'title' => 'Écartés par la détection', 'help' => 'Éligibles, mais une sanction récente ou une inactivité prolongée bloque la proposition.', 'open' => false],
                ['key' => 'later', 'title' => 'Pas encore éligibles', 'help' => 'Une règle du grade manque : la raison est indiquée pour chacun.', 'open' => false],
                ['key' => 'candidate', 'title' => 'Déjà candidats', 'help' => 'Leur dossier est dans la liste des candidatures ci-dessous.', 'open' => false],
                ['key' => 'dismissed', 'title' => 'Écartées par vous', 'help' => 'Propositions écartées pour cette campagne, avec le motif.', 'open' => false],
            ];
            ?>
            <?php foreach ($sections as $sec): ?>
                <?php $list = $groups[$sec['key']]; if ($list === []) { continue; } ?>
                <details class="advc-group-block"<?= $sec['open'] ? ' open' : '' ?>>
                    <summary><span><?= adv_h($sec['title']) ?></span> <span class="advc-count"><?= count($list) ?></span> <span class="advc-muted"><?= adv_h($sec['help']) ?></span></summary>
                    <div class="advc-props">
                        <?php foreach ($list as $prow): ?>
                            <?php
                            $pcampaignId = $id;
                            $pcanAct = $canAct;
                            $pmode = $sec['key'] === 'candidate' ? 'candidate' : ($sec['key'] === 'dismissed' ? 'dismissed' : 'pending');
                            require __DIR__ . '/_proposal_card.php';
                            ?>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>

        <details class="advc-details" id="reglages">
            <summary>Comment le score est calculé</summary>
            <?php
            $criteriaSettings = $proposalSettings;
            $criteriaSources = $sources;
            $criteriaFieldsOnly = false;
            $criteriaFormAction = url('back-office/rh/avancement/reglages');
            $criteriaCampaignId = $id;
            require __DIR__ . '/_criteria.php';
            ?>
        </details>
    </section>
    <?php endif; ?>

    <?php require __DIR__ . '/_detections.php'; ?>

    <?php if ($open): ?>
    <section class="advc-panel">
        <h2>Ajouter une candidature à la main <?= adv_info('Candidature', 'Pour un membre que le système ne propose pas. Un membre hors critères reste non éligible jusqu’à un passage exceptionnel en commission. Le poste visé n’est pas réservé.') ?></h2>
        <form method="post" action="<?= adv_h(url('back-office/rh/avancement/' . $id . '/candidatures')) ?>" class="adv-inline">
            <?= \App\Core\Csrf::field() ?>
            <select name="personnel_id" class="adv-search" data-placeholder="Choisir un membre" required>
                <option value="">Choisir un membre</option>
                <?php foreach ($personnel as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"><?= adv_h(adv_option($person)) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="adv-check"><input type="checkbox" name="mobility_requested" value="1"> Mobilité</label>
            <select name="requested_billet_id" class="adv-search" data-placeholder="Poste visé, à titre indicatif">
                <option value="">Poste visé, à titre indicatif</option>
                <?php foreach ($billets as $billet): ?>
                    <option value="<?= (int) $billet['id'] ?>"><?= adv_h((string) ($billet['title'] ?? $billet['code'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="ath-btn" type="submit">Enregistrer</button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($published): ?>
        <section class="advc-panel advc-board" aria-labelledby="advc-board">
            <h2 id="advc-board">Tableau d’avancement publié</h2>
            <p class="advc-muted">Publié le <?= adv_h(adv_date((string) ($campaign['published_at'] ?? ''))) ?>. Les inscrits ont reçu leur nouveau grade et un message.</p>
            <?php if ($listed === []): ?>
                <p class="advc-muted">Aucun inscrit au tableau.</p>
            <?php else: ?>
                <ol class="advc-board__list">
                    <?php foreach ($listed as $row): ?>
                        <li><strong><?= adv_h(adv_person($row)) ?></strong><?php if (!empty($row['is_forced'])): ?> <span class="adv-badge adv-badge--exception">Passage exceptionnel</span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="advc-panel" aria-labelledby="advc-cands">
        <div class="advc-head">
            <h2 id="advc-cands">Candidatures</h2>
            <span class="advc-count"><?= count($candidacies) ?></span>
        </div>
        <?php if ($candidacies === []): ?>
            <div class="advc-empty" role="status">
                <p><?= $open ? 'Aucune candidature pour l’instant. Retenez les propositions ci-dessus ou ajoutez un membre à la main.' : 'Personne ne s’est présenté pendant la période de candidatures.' ?></p>
            </div>
        <?php else: ?>
            <ul class="adv-people">
                <?php foreach ($candidacies as $row): ?>
                    <?php
                    $rank = $row['preference_rank'] ?? null;
                    $eligible = !empty($row['live_eligible']) || (!isset($row['live_eligible']) && !empty($row['is_eligible']));
                    $forced = !empty($row['is_forced']);
                    $opinionRaw = trim((string) ($row['commission_opinion'] ?? ''));
                    $decisionRaw = trim((string) ($row['decision'] ?? ''));
                    $opinion = AdvancementCodes::opinionLabel($opinionRaw !== '' ? $opinionRaw : null);
                    $decision = AdvancementCodes::decisionLabel($decisionRaw !== '' ? $decisionRaw : null);
                    if ($opinion === '—') {
                        $opinion = 'Pas encore d’avis';
                    }
                    if ($decision === 'Inscrit') {
                        $decision = 'Inscrit au tableau';
                    } elseif ($decision === '—') {
                        $decision = 'En attente';
                    }
                    $conditions = is_array($row['conditions'] ?? null) ? $row['conditions'] : [];
                    $notes = trim((string) ($row['notes'] ?? ''));
                    ?>
                    <li class="adv-person">
                        <div class="adv-person__who">
                            <strong><?= adv_h(adv_person($row)) ?></strong>
                            <?php if ($eligible): ?>
                                <span class="adv-badge adv-badge--ok">Éligible</span>
                            <?php elseif ($forced): ?>
                                <span class="adv-badge adv-badge--exception">Passage exceptionnel</span>
                            <?php else: ?>
                                <span class="adv-badge adv-badge--bad">Non éligible</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!$eligible && !$forced): ?>
                            <p class="advc-why"><strong>Pourquoi :</strong> <?= adv_h((string) ($row['live_reason'] ?? $row['eligibility_reason'] ?? 'un critère du grade manque')) ?></p>
                        <?php endif; ?>
                        <dl class="adv-person__facts">
                            <div><dt>Temps de grade</dt><dd><?= (int) ($row['months_in_grade'] ?? 0) ?> mois<?php if (($row['months_required'] ?? null) !== null): ?> sur <?= (int) $row['months_required'] ?><?php endif; ?></dd></div>
                            <div><dt>Classement</dt><dd><?php if ($rank === null || $rank === ''): ?>Pas encore classé<?php else: ?>Rang <?= (int) $rank ?><?php endif; ?></dd></div>
                            <div><dt>Mobilité</dt><dd><?= !empty($row['mobility_requested']) ? 'Demandée' : 'Non demandée' ?></dd></div>
                            <div><dt>Avis</dt><dd><?= adv_h($opinion) ?></dd></div>
                            <div><dt>Décision</dt><dd><?= adv_h($decision) ?></dd></div>
                        </dl>
                        <?php if ($conditions !== []): ?>
                            <ul class="advc-conds" aria-label="Règles du grade">
                                <?php foreach ($conditions as $cond): ?>
                                    <?php if ((string) ($cond['key'] ?? '') === 'voie') { continue; } ?>
                                    <li class="<?= !empty($cond['met']) ? 'is-met' : 'is-unmet' ?>"><span><?= adv_h((string) ($cond['label'] ?? '')) ?></span> <?= adv_h((string) ($cond['detail'] ?? '')) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($notes !== ''): ?>
                            <p class="advc-note"><?= adv_h($notes) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
