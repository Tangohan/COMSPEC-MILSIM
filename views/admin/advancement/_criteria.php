<?php
/**
 * Explication des critères de la détection et, si $criteriaFormAction est fourni, formulaire des seuils.
 * $criteriaSettings : seuils de la communauté ; $criteriaSources : données disponibles ; $criteriaFieldsOnly : champs seuls
 * (assistant de création, sans balise form).
 */
$criteriaSettings = is_array($criteriaSettings ?? null) ? $criteriaSettings : \App\Services\Advancement\AdvancementProposalEngine::DEFAULTS;
$criteriaSources = is_array($criteriaSources ?? null) ? $criteriaSources : [];
$criteriaFieldsOnly = !empty($criteriaFieldsOnly);
$criteriaFormAction = (string) ($criteriaFormAction ?? '');
$criteriaCampaignId = (int) ($criteriaCampaignId ?? 0);
$w = \App\Services\Advancement\AdvancementProposalEngine::WEIGHTS;
$s = $criteriaSettings;
$measured = [];
foreach ($criteriaSources as $src) {
    $measured[(string) ($src['key'] ?? '')] = $src;
}
$explain = [
    ['key' => 'grade_time', 'label' => 'Temps dans le grade', 'max' => $w['grade_time']],
    ['key' => 'seniority', 'label' => 'Ancienneté dans la communauté', 'max' => $w['seniority']],
    ['key' => 'presences', 'label' => 'Présences', 'max' => $w['presences']],
    ['key' => 'hours', 'label' => 'Heures de jeu', 'max' => $w['hours']],
    ['key' => 'activity', 'label' => 'Activité récente', 'max' => $w['activity']],
    ['key' => 'conduct', 'label' => 'Conduite', 'max' => $w['conduct']],
];
$fields = [
    ['key' => 'window_days', 'label' => 'Période observée', 'unit' => 'jours', 'help' => 'Présences et heures sont comptées sur cette période.'],
    ['key' => 'min_presences', 'label' => 'Présences minimum', 'unit' => 'jours', 'help' => '0 = critère non utilisé.'],
    ['key' => 'min_hours', 'label' => 'Heures de jeu minimum', 'unit' => 'h', 'help' => '0 = critère non utilisé.'],
    ['key' => 'inactivity_days', 'label' => 'Inactivité tolérée', 'unit' => 'jours', 'help' => '0 = critère non utilisé.'],
    ['key' => 'sanction_window_days', 'label' => 'Sanctions prises en compte', 'unit' => 'jours', 'help' => 'Ancienneté maximale d’une sanction retenue.'],
    ['key' => 'min_score', 'label' => 'Score pour être proposé', 'unit' => 'sur 100', 'help' => 'En dessous, le membre est « à examiner ».'],
    ['key' => 'grade_bonus_months', 'label' => 'Mois au-delà du minimum', 'unit' => 'mois', 'help' => 'Pour obtenir tous les points du temps dans le grade.'],
    ['key' => 'seniority_full_months', 'label' => 'Ancienneté pour le maximum', 'unit' => 'mois', 'help' => 'Pour obtenir tous les points d’ancienneté.'],
    ['key' => 'near_months', 'label' => 'Bientôt éligible', 'unit' => 'mois', 'help' => 'Signale ceux à qui il manque au plus ce temps dans le grade.'],
];
$renderFields = static function () use ($fields, $s): void {
    ?>
    <div class="advc-fields">
        <?php foreach ($fields as $f): ?>
            <label class="advc-field">
                <span class="advc-field__label"><?= adv_h($f['label']) ?></span>
                <span class="advc-field__input">
                    <input type="number" inputmode="numeric" name="criteria[<?= adv_h($f['key']) ?>]" value="<?= (int) ($s[$f['key']] ?? 0) ?>" min="0">
                    <span class="advc-field__unit"><?= adv_h($f['unit']) ?></span>
                </span>
                <span class="advc-field__help"><?= adv_h($f['help']) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <?php
};
?>
<div class="advc-criteria">
    <p class="advc-lead">Deux étages. <strong>Les règles du grade visé</strong> (grade précédent, temps minimum, qualification, PASS) décident si un membre est éligible. <strong>Le score sur 100</strong> classe ensuite les éligibles, avec le détail des points :</p>
    <ul class="advc-weights">
        <?php foreach ($explain as $c): ?>
            <?php $src = $measured[$c['key']] ?? null; $off = $src !== null && empty($src['measured']); ?>
            <li class="<?= $off ? 'is-off' : '' ?>">
                <span class="advc-weights__max"><?= (int) $c['max'] ?> pts</span>
                <span class="advc-weights__body">
                    <strong><?= adv_h($c['label']) ?></strong>
                    <?php if ($c['key'] === 'grade_time'): ?>
                        <span>Mois passés dans le grade actuel. Le minimum du grade visé reste une règle ; chaque mois au-delà rapporte des points, jusqu’à <?= (int) $s['grade_bonus_months'] ?> mois de plus.</span>
                    <?php elseif ($c['key'] === 'seniority'): ?>
                        <span>Depuis la date d’engagement de la fiche, sinon la création du compte. Tous les points à <?= (int) $s['seniority_full_months'] ?> mois.</span>
                    <?php elseif ($c['key'] === 'presences'): ?>
                        <span>Jours de présence sur <?= (int) $s['window_days'] ?> jours (sessions Arma et événements pointés). Minimum attendu : <?= (int) $s['min_presences'] ?>.</span>
                    <?php elseif ($c['key'] === 'hours'): ?>
                        <span>Temps de jeu validé dans les sessions Arma sur <?= (int) $s['window_days'] ?> jours. Minimum attendu : <?= (int) $s['min_hours'] ?> h.</span>
                    <?php elseif ($c['key'] === 'activity'): ?>
                        <span>Au-delà de <?= (int) $s['inactivity_days'] ?> jours sans activité, le membre est écarté. Une absence déclarée en cours suspend ce critère.</span>
                    <?php else: ?>
                        <span>Une suspension, une restriction ou une exclusion de moins de <?= (int) $s['sanction_window_days'] ?> jours, ou en vigueur, écarte le membre ; un avertissement retire ces points.</span>
                    <?php endif; ?>
                    <?php if ($src !== null): ?>
                        <span class="advc-source<?= $off ? ' is-off' : ' is-on' ?>"><?= $off ? 'Non compté' : 'Mesuré' ?> · <?= adv_h((string) ($src['detail'] ?? '')) ?></span>
                    <?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="advc-muted">Un critère sans donnée dans la communauté n’est pas compté : le score est ramené sur les critères mesurés. Score d’au moins <?= (int) $s['min_score'] ?> et aucun blocage = « Proposé ». Rien n’est attribué automatiquement : vous retenez ou écartez chaque proposition.</p>
    <?php if (isset($measured['absences'])): ?>
        <p class="advc-muted">Absences déclarées : <?= adv_h((string) ($measured['absences']['detail'] ?? '')) ?>.</p>
    <?php endif; ?>
    <?php if ($criteriaFieldsOnly): ?>
        <?php $renderFields(); ?>
    <?php elseif ($criteriaFormAction !== ''): ?>
        <details class="advc-details">
            <summary>Modifier les seuils de la communauté</summary>
            <form method="post" action="<?= adv_h($criteriaFormAction) ?>" class="advc-settings">
                <?= \App\Core\Csrf::field() ?>
                <?php if ($criteriaCampaignId > 0): ?>
                    <input type="hidden" name="campaign_id" value="<?= $criteriaCampaignId ?>">
                <?php endif; ?>
                <?php $renderFields(); ?>
                <div class="advc-actions">
                    <button class="ath-btn ath-btn--solid" type="submit">Enregistrer les seuils</button>
                    <span class="advc-muted">Valeurs par défaut : 90 jours, 3 présences, 6 h, 30 jours d’inactivité, 180 jours pour les sanctions, score 60.</span>
                </div>
            </form>
        </details>
    <?php endif; ?>
</div>
