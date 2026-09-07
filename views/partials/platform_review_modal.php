<?php
declare(strict_types=1);

use App\Support\PlatformReviewCatalog;

if (!\App\Core\Session::get('user_id')) {
    return;
}

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$t = static function (string $key, string $fallback): string {
    if (!function_exists('__')) {
        return $fallback;
    }
    $value = __($key);

    return ($value === '' || $value === $key) ? $fallback : $value;
};
$currentLocale = function_exists('locale') ? locale() : 'fr';
$usageOptions = PlatformReviewCatalog::usageOptions();
$frequencyOptions = PlatformReviewCatalog::frequencyOptions();
$frictionOptions = PlatformReviewCatalog::frictionOptions();
$deviceOptions = PlatformReviewCatalog::deviceOptions();
$areaOptions = PlatformReviewCatalog::areaOptions();
$localeOptions = PlatformReviewCatalog::localeOptions();
?>
<link href="<?= $h(asset_url('assets/css/platform-review-modal.css')) ?>" rel="stylesheet">
<div
    id="platform-review-root"
    class="prw"
    hidden
    data-csrf="<?= $h(\App\Core\Csrf::token()) ?>"
    data-state-url="<?= $h(url('api/platform-review/state')) ?>"
    data-review-url="<?= $h(url('api/platform-review')) ?>"
    data-snooze-url="<?= $h(url('api/platform-review/plus-tard')) ?>"
    data-translation-url="<?= $h(url('api/platform-review/traduction')) ?>"
    data-locale="<?= $h($currentLocale) ?>"
    data-msg-need-score="<?= $h($t('common.platform_review_need_score', 'Choisissez une note de 0 à 10.')) ?>"
    data-msg-review-fail="<?= $h($t('common.platform_review_send_fail', 'L’avis n’a pas pu être envoyé.')) ?>"
    data-msg-review-ok="<?= $h($t('common.platform_review_send_ok', 'Merci. Votre avis a bien été transmis.')) ?>"
    data-msg-translation-fail="<?= $h($t('common.platform_translate_send_fail', 'La proposition n’a pas pu être envoyée.')) ?>"
    data-msg-translation-ok="<?= $h($t('common.platform_translate_send_ok', 'Merci. Votre proposition sera relue.')) ?>"
>
    <button type="button" class="prw-launcher" id="prw-launcher" aria-haspopup="dialog" aria-expanded="false" aria-controls="prw-dialog">
        <span class="prw-launcher__mark" aria-hidden="true">★</span>
        <span><?= $h($t('common.platform_review_launcher', 'Avis Athena')) ?></span>
    </button>

    <div id="prw-dialog" class="prw-overlay" role="dialog" aria-modal="true" aria-labelledby="prw-title" hidden>
        <div class="prw-card">
            <header class="prw-head">
                <div>
                    <p class="prw-kicker"><?= $h($t('common.platform_review_kicker', 'Athena')) ?></p>
                    <h2 id="prw-title" class="prw-title"><?= $h($t('common.platform_review_title', 'Aidez-nous à améliorer Athena')) ?></h2>
                    <p class="prw-lead"><?= $h($t('common.platform_review_lead', 'Donnez votre avis sur la plateforme, ou proposez une meilleure formulation dans une autre langue.')) ?></p>
                </div>
                <button type="button" class="prw-close" data-prw-close aria-label="<?= $h($t('common.close', 'Fermer')) ?>">×</button>
            </header>

            <div class="prw-tabs" role="tablist">
                <button type="button" class="prw-tab is-on" role="tab" aria-selected="true" data-prw-tab="review" id="prw-tab-review"><?= $h($t('common.platform_review_tab_review', 'Votre avis')) ?></button>
                <button type="button" class="prw-tab" role="tab" aria-selected="false" data-prw-tab="translate" id="prw-tab-translate"><?= $h($t('common.platform_review_tab_translate', 'Traductions')) ?></button>
            </div>

            <div class="prw-pane" data-prw-pane="review" role="tabpanel" aria-labelledby="prw-tab-review">
                <p class="prw-question"><?= $h($t('common.platform_review_score_q', 'Recommanderiez-vous Athena à une autre communauté ?')) ?></p>
                <div class="prw-score" role="radiogroup" aria-label="<?= $h($t('common.platform_review_score_q', 'Recommanderiez-vous Athena à une autre communauté ?')) ?>">
                    <?php for ($i = 0; $i <= 10; $i++): ?>
                        <button type="button" class="prw-score__btn" data-prw-score="<?= $i ?>" aria-label="<?= $i ?> / 10"><?= $i ?></button>
                    <?php endfor; ?>
                </div>
                <div class="prw-score__hints">
                    <span><?= $h($t('common.platform_review_score_low', 'Peu probable')) ?></span>
                    <span><?= $h($t('common.platform_review_score_high', 'Tout à fait')) ?></span>
                </div>

                <fieldset class="prw-field prw-field--choice">
                    <legend><?= $h($t('common.platform_review_clarity_q', 'Athena est-elle claire à utiliser ?')) ?></legend>
                    <div class="prw-choice" role="radiogroup" aria-label="<?= $h($t('common.platform_review_clarity_q', 'Athena est-elle claire à utiliser ?')) ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" class="prw-choice__btn" data-prw-clarity="<?= $i ?>" aria-label="<?= $i ?> / 5"><?= $i ?></button>
                        <?php endfor; ?>
                    </div>
                    <div class="prw-score__hints">
                        <span><?= $h($t('common.platform_review_clarity_low', 'Pas clair')) ?></span>
                        <span><?= $h($t('common.platform_review_clarity_high', 'Très clair')) ?></span>
                    </div>
                </fieldset>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_review_usage', 'Vous utilisez surtout Athena comme')) ?></span>
                    <select data-prw-usage>
                        <option value=""><?= $h($t('common.platform_review_usage_choose', 'Choisir…')) ?></option>
                        <?php foreach ($usageOptions as $value => $label): ?>
                            <option value="<?= $h($value) ?>"><?= $h($t('common.platform_review_usage_' . $value, $label)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_review_frequency', 'Vous ouvrez Athena…')) ?></span>
                    <select data-prw-frequency>
                        <option value=""><?= $h($t('common.platform_review_usage_choose', 'Choisir…')) ?></option>
                        <?php foreach ($frequencyOptions as $value => $label): ?>
                            <option value="<?= $h($value) ?>"><?= $h($t('common.platform_review_frequency_' . $value, $label)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_review_friction', 'Où perdez-vous le plus de temps ?')) ?></span>
                    <select data-prw-friction>
                        <option value=""><?= $h($t('common.platform_review_usage_choose', 'Choisir…')) ?></option>
                        <?php foreach ($frictionOptions as $value => $label): ?>
                            <option value="<?= $h($value) ?>"><?= $h($t('common.platform_review_friction_' . $value, $label)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_review_device', 'Vous utilisez surtout Athena sur')) ?></span>
                    <select data-prw-device>
                        <option value=""><?= $h($t('common.platform_review_usage_choose', 'Choisir…')) ?></option>
                        <?php foreach ($deviceOptions as $value => $label): ?>
                            <option value="<?= $h($value) ?>"><?= $h($t('common.platform_review_device_' . $value, $label)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_review_highlights', 'Ce qui vous est vraiment utile')) ?></span>
                    <textarea rows="3" maxlength="2000" data-prw-highlights placeholder="<?= $h($t('common.platform_review_highlights_ph', 'Un écran, une habitude, un moment où ça aide vraiment…')) ?>"></textarea>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_review_improvements', 'Ce que vous changeriez')) ?></span>
                    <textarea rows="3" maxlength="2000" data-prw-improvements placeholder="<?= $h($t('common.platform_review_improvements_ph', 'Un blocage, une confusion, une idée concrète…')) ?>"></textarea>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_review_wishlist', 'Une fonctionnalité que vous aimeriez (facultatif)')) ?></span>
                    <textarea rows="2" maxlength="2000" data-prw-wishlist placeholder="<?= $h($t('common.platform_review_wishlist_ph', 'Par exemple : un rappel, un écran manquant, un export…')) ?>"></textarea>
                </label>

                <div class="prw-actions">
                    <button type="button" class="prw-btn prw-btn--ghost" data-prw-later><?= $h($t('common.platform_review_later', 'Plus tard')) ?></button>
                    <button type="button" class="prw-btn prw-btn--solid" data-prw-save-review><?= $h($t('common.platform_review_send', 'Envoyer l’avis')) ?></button>
                </div>
            </div>

            <div class="prw-pane" data-prw-pane="translate" role="tabpanel" aria-labelledby="prw-tab-translate" hidden>
                <p class="prw-question"><?= $h($t('common.platform_translate_intro', 'Vous voyez une phrase mal traduite ? Proposez une formulation plus naturelle. Elle sera relue avant d’apparaître partout.')) ?></p>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_translate_locale', 'Langue concernée')) ?></span>
                    <select data-prw-locale>
                        <?php foreach ($localeOptions as $code => $label): ?>
                            <option value="<?= $h($code) ?>" <?= $currentLocale === $code ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_translate_area', 'Où avez-vous lu ce texte ?')) ?></span>
                    <select data-prw-area>
                        <?php foreach ($areaOptions as $value => $label): ?>
                            <option value="<?= $h($value) ?>"><?= $h($t('common.platform_translate_area_' . $value, $label)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_translate_original', 'Texte actuel')) ?></span>
                    <textarea rows="2" maxlength="500" data-prw-original placeholder="<?= $h($t('common.platform_translate_original_ph', 'Copiez la phrase telle que vous la voyez.')) ?>"></textarea>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_translate_proposed', 'Formulation proposée')) ?></span>
                    <textarea rows="2" maxlength="500" data-prw-proposed placeholder="<?= $h($t('common.platform_translate_proposed_ph', 'Écrivez comment vous diriez cela naturellement.')) ?>"></textarea>
                </label>

                <label class="prw-field">
                    <span><?= $h($t('common.platform_translate_comment', 'Précision (facultatif)')) ?></span>
                    <textarea rows="2" maxlength="1000" data-prw-comment placeholder="<?= $h($t('common.platform_translate_comment_ph', 'Contexte, page, ou pourquoi cette formulation convient mieux.')) ?>"></textarea>
                </label>

                <div class="prw-actions">
                    <button type="button" class="prw-btn prw-btn--ghost" data-prw-close><?= $h($t('common.cancel', 'Annuler')) ?></button>
                    <button type="button" class="prw-btn prw-btn--solid" data-prw-save-translation><?= $h($t('common.platform_translate_send', 'Envoyer la proposition')) ?></button>
                </div>
            </div>

            <p class="prw-status" data-prw-status role="status" aria-live="polite"></p>
        </div>
    </div>
</div>
<script defer src="<?= $h(asset_url('assets/js/platform-review-modal.js')) ?>"></script>
