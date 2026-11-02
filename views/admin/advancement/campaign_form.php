<?php
require __DIR__ . '/_helpers.php';

use App\Services\Advancement\AdvancementProposalEngine as Engine;

$grades = is_array($grades ?? null) ? $grades : [];
$overview = is_array($overview ?? null) ? $overview : [];
$personnel = is_array($personnel ?? null) ? $personnel : [];
$proposalSettings = is_array($proposalSettings ?? null) ? $proposalSettings : Engine::DEFAULTS;
$preselected = (int) ($preselected ?? 0);
$byFiliere = [];
foreach ($grades as $grade) {
    if (empty($grade['advancement_choice_enabled']) || !empty($grade['archived_at'])) {
        continue;
    }
    $fid = trim((string) ($grade['filiere_label'] ?? ''));
    $byFiliere[$fid !== '' ? $fid : 'Sans filière'][] = $grade;
}
$today = date('Y-m-d');
$closeDefault = date('Y-m-d', strtotime('+30 days'));
?>
<div class="adv-page advc" data-adv-page="campaign-form">
    <?php require __DIR__ . '/_flash.php'; ?>
    <p class="advc-back"><a href="<?= adv_h(url('back-office/rh/avancement')) ?>">Retour à l’avancement</a></p>

    <section class="advc-intro advc-intro--slim">
        <div class="advc-intro__text">
            <p class="advc-kicker">Nouvelle campagne</p>
            <h2>Lancer une campagne d’avancement au choix</h2>
            <p>Six étapes courtes. À la fin, la campagne s’ouvre et les propositions du système vous attendent : vous les retenez ou les écartez une par une, puis la commission arbitre.</p>
        </div>
    </section>

    <?php if ($byFiliere === []): ?>
        <section class="advc-panel">
            <div class="advc-empty">
                <p><strong>Aucun grade n’est ouvert à la voie choix.</strong> Ouvrez la voie choix sur au moins un grade dans les règles d’avancement, puis revenez ici.</p>
                <a class="ath-btn ath-btn--solid" href="<?= adv_h(url('back-office/organisation/grades')) ?>">Règles d’avancement</a>
            </div>
        </section>
    <?php else: ?>
    <form method="post" action="<?= adv_h(url('back-office/rh/avancement/store')) ?>" class="advc-wizard" data-advc-wizard novalidate>
        <?= \App\Core\Csrf::field() ?>
        <ol class="advc-wizard__nav" data-advc-nav hidden>
            <li><button type="button" data-advc-goto="1"><span>1</span> Grades</button></li>
            <li><button type="button" data-advc-goto="2"><span>2</span> Période</button></li>
            <li><button type="button" data-advc-goto="3"><span>3</span> Critères</button></li>
            <li><button type="button" data-advc-goto="4"><span>4</span> Places</button></li>
            <li><button type="button" data-advc-goto="5"><span>5</span> Commission</button></li>
            <li><button type="button" data-advc-goto="6"><span>6</span> Vérifier</button></li>
        </ol>

        <fieldset class="advc-panel advc-wstep" data-advc-step="1">
            <legend><span class="advc-wstep__n">1</span> Grades concernés</legend>
            <p class="advc-lead">Une campagne par grade visé. Seuls les grades ouverts à la voie choix sont listés. Les chiffres indiquent ce que le système détecte aujourd’hui parmi les membres du grade juste en dessous.</p>
            <?php foreach ($byFiliere as $filiereLabel => $group): ?>
                <h3 class="advc-group"><?= adv_h((string) $filiereLabel) ?></h3>
                <div class="advc-choices">
                    <?php foreach ($group as $grade): ?>
                        <?php
                        $gid = (int) $grade['id'];
                        $row = $overview[$gid] ?? [];
                        $n = is_array($row['counts'] ?? null) ? $row['counts'] : [];
                        $running = is_array($row['campaign'] ?? null) ? $row['campaign'] : null;
                        $previous = trim((string) ($row['previous_label'] ?? ''));
                        $months = (int) ($grade['min_time_in_previous_grade_months'] ?? 0);
                        $qual = trim((string) ($grade['required_qualification_name'] ?? ''));
                        ?>
                        <label class="advc-choice">
                            <input type="checkbox" name="grade_ids[]" value="<?= $gid ?>" data-advc-grade data-label="<?= adv_h((string) $grade['label']) ?>" data-proposed="<?= (int) ($n[Engine::STATUS_PROPOSE] ?? 0) ?>"<?= $preselected === $gid ? ' checked' : '' ?>>
                            <span class="advc-choice__body">
                                <strong><?= adv_h((string) $grade['label']) ?> <span class="advc-code"><?= adv_h((string) ($grade['code'] ?? '')) ?></span></strong>
                                <span class="advc-muted"><?php if ($previous !== ''): ?>Depuis <?= adv_h($previous) ?><?php else: ?>Aucun membre au grade précédent<?php endif; ?><?php if ($months > 0): ?> · <?= $months ?> mois minimum<?php endif; ?><?php if ($qual !== ''): ?> · qualification <?= adv_h($qual) ?><?php endif; ?></span>
                                <span class="advc-chips advc-chips--inline">
                                    <span><b><?= (int) ($n['population'] ?? 0) ?></b> au grade précédent</span>
                                    <span class="is-ok"><b><?= (int) ($n[Engine::STATUS_PROPOSE] ?? 0) ?></b> proposé(s)</span>
                                    <span class="is-warn"><b><?= (int) ($n[Engine::STATUS_REVIEW] ?? 0) ?></b> à examiner</span>
                                </span>
                                <?php if ($running !== null): ?>
                                    <span class="advc-warn">Une campagne <?= (int) ($running['year'] ?? 0) ?> est déjà en cours pour ce grade.</span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <p class="advc-error" data-advc-error="1" hidden>Cochez au moins un grade.</p>
        </fieldset>

        <fieldset class="advc-panel advc-wstep" data-advc-step="2">
            <legend><span class="advc-wstep__n">2</span> Période de candidatures</legend>
            <p class="advc-lead">Pendant cette période, vous retenez les propositions du système et les membres peuvent se porter candidats depuis « Mon avancement ». L’éligibilité est recalculée chaque jour : un membre peut le devenir en cours de route.</p>
            <div class="advc-fields advc-fields--3">
                <label class="advc-field"><span class="advc-field__label">Année du tableau</span><span class="advc-field__input"><input type="number" name="year" value="<?= (int) date('Y') ?>" min="2000" max="2100" required data-advc-year></span></label>
                <label class="advc-field"><span class="advc-field__label">Ouverture</span><span class="advc-field__input"><input type="date" name="opens_at" value="<?= adv_h($today) ?>" required data-advc-opens></span></label>
                <label class="advc-field"><span class="advc-field__label">Clôture</span><span class="advc-field__input"><input type="date" name="closes_at" value="<?= adv_h($closeDefault) ?>" required data-advc-closes></span><span class="advc-field__help">Par défaut 30 jours.</span></label>
            </div>
            <p class="advc-error" data-advc-error="2" hidden>La clôture doit suivre l’ouverture.</p>
        </fieldset>

        <fieldset class="advc-panel advc-wstep" data-advc-step="3">
            <legend><span class="advc-wstep__n">3</span> Critères de la détection</legend>
            <?php
            $criteriaSettings = $proposalSettings;
            $criteriaSources = is_array($sources ?? null) ? $sources : [];
            $criteriaFieldsOnly = true;
            $criteriaFormAction = '';
            $criteriaCampaignId = 0;
            require __DIR__ . '/_criteria.php';
            ?>
            <label class="adv-check advc-check"><input type="checkbox" name="save_criteria" value="1" checked> Enregistrer ces seuils pour la communauté (ils s’appliquent à toutes les campagnes)</label>
        </fieldset>

        <fieldset class="advc-panel advc-wstep" data-advc-step="4">
            <legend><span class="advc-wstep__n">4</span> Nombre de places</legend>
            <p class="advc-lead">Le quota limite le nombre d’inscrits au tableau ; il est contrôlé à la publication. Laissez vide pour ne pas limiter. Le nombre de membres proposés aujourd’hui est indiqué pour vous aider.</p>
            <div class="advc-quotas">
                <?php foreach ($byFiliere as $group): ?>
                    <?php foreach ($group as $grade): ?>
                        <?php $gid = (int) $grade['id']; $proposed = (int) (($overview[$gid]['counts'] ?? [])[Engine::STATUS_PROPOSE] ?? 0); ?>
                        <label class="advc-quota" data-advc-quota="<?= $gid ?>">
                            <span><strong><?= adv_h((string) $grade['label']) ?></strong> <span class="advc-muted"><?= $proposed ?> proposé(s) aujourd’hui</span></span>
                            <input type="number" min="0" name="quota[<?= $gid ?>]" placeholder="Illimité">
                        </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="advc-panel advc-wstep" data-advc-step="5">
            <legend><span class="advc-wstep__n">5</span> Qui valide</legend>
            <p class="advc-lead">La commission classe les candidats, donne son avis et décide des inscriptions. Vous pouvez la composer maintenant ou plus tard. Un membre de la commission ne devrait pas juger sa propre candidature : le système le signale.</p>
            <div class="advc-members">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="advc-member">
                        <select name="members[<?= $i ?>][personnel_id]" class="adv-search" data-placeholder="Choisir un membre" data-advc-member>
                            <option value="">Aucun</option>
                            <?php foreach ($personnel as $person): ?>
                                <option value="<?= (int) $person['id'] ?>"><?= adv_h(adv_option($person)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="members[<?= $i ?>][role]" aria-label="Rôle dans la commission">
                            <option value="titulaire">Titulaire</option>
                            <option value="suppleant">Suppléant</option>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>
            <label class="advc-field advc-field--narrow"><span class="advc-field__label">Date de séance prévue</span><span class="advc-field__input"><input type="date" name="meeting_date"></span><span class="advc-field__help">Facultatif.</span></label>
        </fieldset>

        <fieldset class="advc-panel advc-wstep" data-advc-step="6">
            <legend><span class="advc-wstep__n">6</span> Vérifier et ouvrir</legend>
            <div class="advc-summary" data-advc-summary>
                <p class="advc-muted">Vérifiez les grades, la période, les places et la commission, puis ouvrez la campagne.</p>
            </div>
            <p class="advc-muted">À l’ouverture : la campagne passe « Ouverte », les propositions du système s’affichent sur sa page, et rien n’est attribué tant que le tableau n’est pas publié.</p>
            <div class="advc-actions">
                <button class="ath-btn ath-btn--solid" type="submit">Ouvrir la campagne</button>
                <a class="ath-btn" href="<?= adv_h(url('back-office/rh/avancement')) ?>">Annuler</a>
            </div>
        </fieldset>

        <div class="advc-wizard__bar" data-advc-bar hidden>
            <button type="button" class="ath-btn" data-advc-prev>Précédent</button>
            <span class="advc-muted" data-advc-where></span>
            <button type="button" class="ath-btn ath-btn--solid" data-advc-next>Suivant</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<script src="<?= adv_h(asset_url('assets/js/back-office-advancement.js')) ?>"></script>
