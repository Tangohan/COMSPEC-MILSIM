<?php
declare(strict_types=1);

$csrf = \App\Core\Csrf::token();
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<link href="<?= $h(asset_url('assets/css/ux-feedback-widget.css')) ?>" rel="stylesheet">
<div
    id="ux-feedback-root"
    class="uxfb-root"
    data-csrf="<?= $h($csrf) ?>"
    data-state-url="<?= $h(url('api/ux-feedback/state')) ?>"
    data-rating-url="<?= $h(url('api/ux-feedback/rating')) ?>"
    data-survey-url="<?= $h(url('api/ux-feedback/survey')) ?>"
    hidden
>
    <button type="button" class="uxfb-launcher" id="uxfb-launcher" aria-haspopup="dialog" aria-expanded="false" aria-controls="uxfb-panel">
        <span class="uxfb-launcher__icon" aria-hidden="true">★</span>
        <span class="uxfb-launcher__label">Retour UI</span>
    </button>

    <div class="uxfb-panel" id="uxfb-panel" role="dialog" aria-labelledby="uxfb-panel-title" aria-modal="true" hidden>
        <header class="uxfb-panel__head">
            <div>
                <p class="uxfb-panel__kicker">Expérience back-office</p>
                <h2 class="uxfb-panel__title" id="uxfb-panel-title">Votre avis sur cette page</h2>
            </div>
            <button type="button" class="uxfb-panel__close" data-uxfb-close aria-label="Fermer">×</button>
        </header>

        <div class="uxfb-panel__body">
            <section class="uxfb-block" aria-labelledby="uxfb-quick-title">
                <h3 id="uxfb-quick-title" class="uxfb-block__title">Note rapide</h3>
                <p class="uxfb-block__hint">De 1 (insatisfaisant) à 5 (excellent) — enregistrée pour cette page.</p>
                <div class="uxfb-stars" data-uxfb-stars="rating" role="radiogroup" aria-label="Note globale de la page">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="uxfb-star" data-value="<?= $i ?>" aria-label="<?= $i ?> sur 5">★</button>
                    <?php endfor; ?>
                </div>
                <label class="uxfb-field">
                    <span class="uxfb-field__label">Commentaire court <span class="uxfb-optional">(facultatif)</span></span>
                    <textarea rows="2" maxlength="500" data-uxfb-rating-comment placeholder="Ce qui fonctionne ou bloque sur cette page…"></textarea>
                </label>
                <button type="button" class="uxfb-btn uxfb-btn--primary" data-uxfb-save-rating>Enregistrer la note</button>
            </section>

            <section class="uxfb-block uxfb-block--survey" aria-labelledby="uxfb-survey-title">
                <h3 id="uxfb-survey-title" class="uxfb-block__title">Questionnaire détaillé</h3>
                <p class="uxfb-block__hint">Quatre critères + points de friction — une réponse par page et par utilisateur.</p>

                <?php
                $surveyAxes = [
                    'ease_rating' => ['Facilité d’usage', 'Difficile → intuitif'],
                    'clarity_rating' => ['Clarté', 'Confus → limpide'],
                    'design_rating' => ['Design & lisibilité', 'Peu soigné → agréable'],
                    'usefulness_rating' => ['Utilité métier', 'Peu utile → indispensable'],
                ];
                foreach ($surveyAxes as $field => $meta):
                ?>
                <div class="uxfb-axis">
                    <div class="uxfb-axis__copy">
                        <span class="uxfb-axis__label"><?= $h($meta[0]) ?></span>
                        <span class="uxfb-axis__hint"><?= $h($meta[1]) ?></span>
                    </div>
                    <div class="uxfb-stars uxfb-stars--compact" data-uxfb-stars="<?= $h($field) ?>" role="radiogroup" aria-label="<?= $h($meta[0]) ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="uxfb-star" data-value="<?= $i ?>" aria-label="<?= $i ?> sur 5">★</button>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <fieldset class="uxfb-issues">
                    <legend class="uxfb-field__label">Points signalés</legend>
                    <div class="uxfb-issues__grid">
                        <?php
                        $issueOptions = [
                            'navigation' => 'Navigation',
                            'labels' => 'Libellés',
                            'performance' => 'Performance',
                            'mobile' => 'Mobile',
                            'accessibility' => 'Accessibilité',
                            'missing_info' => 'Info manquante',
                            'workflow' => 'Parcours long',
                            'visual_noise' => 'Interface chargée',
                        ];
                        foreach ($issueOptions as $slug => $label):
                        ?>
                        <label class="uxfb-check">
                            <input type="checkbox" name="issues[]" value="<?= $h($slug) ?>">
                            <span><?= $h($label) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <label class="uxfb-field">
                    <span class="uxfb-field__label">Suggestions d’amélioration</span>
                    <textarea rows="4" maxlength="4000" data-uxfb-improvement placeholder="Décrivez ce que vous changeriez, ce qui manque, ou un exemple concret…"></textarea>
                </label>

                <fieldset class="uxfb-recommend">
                    <legend class="uxfb-field__label">Recommanderiez-vous cette interface à un collègue ?</legend>
                    <label class="uxfb-radio"><input type="radio" name="would_recommend" value="1"> Oui</label>
                    <label class="uxfb-radio"><input type="radio" name="would_recommend" value="0"> Non</label>
                </fieldset>

                <button type="button" class="uxfb-btn uxfb-btn--accent" data-uxfb-save-survey>Envoyer le questionnaire</button>
            </section>

            <p class="uxfb-status" data-uxfb-status role="status" aria-live="polite"></p>
        </div>
    </div>
</div>
<script defer src="<?= $h(asset_url('assets/js/ux-feedback-widget.js')) ?>"></script>
