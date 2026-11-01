<?php
declare(strict_types=1);

/**
 * Critères d’éligibilité à une élévation, réglés par la communauté.
 *
 * @var array{min_months_in_grade:int,min_presences_90:int,min_presence_rate_90:int,required_qualification_ids:list<int>} $criteria
 * @var list<array<string,mixed>> $qualificationOptions
 * @var bool $canSetElevationCriteria
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$criteria = is_array($criteria ?? null) ? $criteria : \App\Services\Effectifs\ElevationEligibility::sanitize([]);
$qualificationOptions = is_array($qualificationOptions ?? null) ? $qualificationOptions : [];
$canEdit = (bool) ($canSetElevationCriteria ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$required = array_map('intval', $criteria['required_qualification_ids'] ?? []);
$configured = \App\Services\Effectifs\ElevationEligibility::isConfigured($criteria);
$disabled = $canEdit ? '' : ' disabled';
?>
<section class="eff-page-head">
    <div class="eff-pg__actions">
        <a href="<?= $h(effectifs_workspace_url('elevations')) ?>" class="eff-catalog__btn">Demandes d’élévation</a>
    </div>
    <p class="eff-page-kicker">Gouvernance</p>
    <h2 class="eff-page-title">Critères d’éligibilité à une élévation</h2>
    <p class="eff-page-lead">
        Fixez les conditions à remplir avant de proposer une élévation. Chaque fiche membre indique alors
        si la personne est éligible et quels critères sont remplis. L’indicateur est une aide à la décision :
        il ne change aucun grade et ne bloque aucune demande.
    </p>
</section>

<div class="eff-dup">
    <section class="eff-dup__panel" aria-labelledby="eff-crit-title">
        <header class="eff-dup__head">
            <div>
                <h2 id="eff-crit-title" class="eff-dup__title">Conditions</h2>
                <p class="eff-dup__sub">Laissez une valeur à 0 pour ne pas l’utiliser. <?= $configured ? 'Les critères ci-dessous sont appliqués sur les fiches.' : 'Aucun critère n’est défini pour l’instant : les fiches n’affichent pas d’éligibilité.' ?></p>
            </div>
        </header>
        <?php if (!$canEdit): ?>
            <p class="eff-dup__sub">Consultation seule : le réglage est réservé aux personnes qui gèrent les grades.</p>
        <?php endif; ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('elevations/criteres')) ?>" class="eff-card__form eff-crit-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label for="crit-months">Ancienneté minimale dans le grade actuel (en mois)</label>
            <input id="crit-months" type="number" name="min_months_in_grade" min="0" max="240" value="<?= (int) $criteria['min_months_in_grade'] ?>"<?= $disabled ?>>
            <p class="eff-card__hint">Compté à partir de la date du dernier changement de grade connu. Pour un membre jamais gradé, à partir de son engagement.</p>

            <label for="crit-presences">Nombre minimal de présences sur les 90 derniers jours</label>
            <input id="crit-presences" type="number" name="min_presences_90" min="0" max="200" value="<?= (int) $criteria['min_presences_90'] ?>"<?= $disabled ?>>
            <p class="eff-card__hint">Une présence est une session du calendrier pour laquelle la présence du membre a été pointée.</p>

            <label for="crit-rate">Taux de présence minimal sur les 90 derniers jours (%)</label>
            <input id="crit-rate" type="number" name="min_presence_rate_90" min="0" max="100" value="<?= (int) $criteria['min_presence_rate_90'] ?>"<?= $disabled ?>>
            <p class="eff-card__hint">Sessions pointées rapportées à toutes les sessions de la communauté sur la période.</p>

            <p class="eff-dup-fields__label" id="crit-quals-label">Qualifications requises</p>
            <?php if ($qualificationOptions === []): ?>
                <p class="eff-card__hint">Aucune qualification n’est définie dans le référentiel de la communauté.</p>
            <?php else: ?>
                <div class="eff-dup-fields" role="group" aria-labelledby="crit-quals-label">
                    <?php foreach ($qualificationOptions as $q): ?>
                        <?php $qid = (int) ($q['id'] ?? 0); $on = in_array($qid, $required, true); ?>
                        <label class="eff-dup-chip <?= $on ? 'is-on' : '' ?>">
                            <input type="checkbox" name="required_qualification_ids[]" value="<?= $qid ?>" <?= $on ? 'checked' : '' ?><?= $disabled ?>>
                            <span>
                                <strong><?= $h(trim((string) ($q['name'] ?? '')) ?: 'Qualification') ?></strong>
                                <?php if (trim((string) ($q['category_name'] ?? '')) !== ''): ?><em><?= $h((string) $q['category_name']) ?></em><?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="eff-card__hint">Une qualification compte si elle est détenue et valide le jour de la consultation (y compris si elle expire bientôt).</p>
            <?php endif; ?>
            <?php if ($canEdit): ?>
                <div class="eff-dup-form__actions">
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer les critères</button>
                </div>
            <?php endif; ?>
        </form>
    </section>
</div>
