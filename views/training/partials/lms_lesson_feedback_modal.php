<?php
declare(strict_types=1);
/**
 * Modal avis post-leçon — ouvert en fin de leçon (JS).
 *
 * @var array<string, mixed> $lesson
 * @var array<string, mixed> $enrollment
 * @var array<string, mixed>|null $lessonFeedback
 */
$enrIdFb = (int) ($enrollment['id'] ?? 0);
$lessonIdFb = (int) ($lesson['id'] ?? 0);
$lessonFeedback = is_array($lessonFeedback ?? null) ? $lessonFeedback : null;
$hasLessonFeedback = $lessonFeedback !== null;

$ratingScale = static function (string $name, string $label, string $hint): void {
    ?>
    <fieldset class="lms-fb-scale">
        <legend class="lms-fb-scale__legend">
            <span class="lms-fb-scale__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="lms-fb-scale__hint"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></span>
        </legend>
        <div class="lms-fb-scale__opts" role="radiogroup" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <label class="lms-fb-scale__opt">
                    <input type="radio" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" value="<?= $i ?>" <?= $i === 3 ? 'checked' : '' ?> required>
                    <span><?= $i ?></span>
                </label>
            <?php endfor; ?>
        </div>
        <div class="lms-fb-scale__ends" aria-hidden="true">
            <span>Faible</span>
            <span>Élevé</span>
        </div>
    </fieldset>
    <?php
};
?>
<div
    id="lms-feedback-modal"
    class="lms-modal-overlay hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="lms-feedback-modal-title"
    aria-hidden="true"
    data-lms-feedback-modal
    data-lms-feedback-done="<?= $hasLessonFeedback ? '1' : '0' ?>"
    data-lms-feedback-enrollment="<?= $enrIdFb ?>"
    data-lms-feedback-lesson="<?= $lessonIdFb ?>"
>
    <div class="lms-modal-overlay__backdrop" data-lms-feedback-close tabindex="-1" aria-hidden="true"></div>
    <div class="lms-modal-panel lms-modal-panel--feedback" role="document">
        <div class="lms-modal-panel__head">
            <div>
                <p class="lms-fb-kicker">Retour d’expérience</p>
                <h2 id="lms-feedback-modal-title" class="lms-modal-panel__title">Votre avis sur cette leçon</h2>
            </div>
            <button type="button" class="lms-modal-panel__close" data-lms-feedback-close aria-label="Fermer">×</button>
        </div>
        <div class="lms-modal-panel__body">
            <div id="lms-feedback-done" class="lms-fb-done <?= $hasLessonFeedback ? '' : 'hidden' ?>">
                <div class="lms-fb-done__icon" aria-hidden="true">✓</div>
                <p class="lms-fb-done__title">Merci : votre retour a bien été enregistré.</p>
                <?php if ($hasLessonFeedback): ?>
                <p class="lms-fb-done__meta">
                    Difficulté <?= (int) ($lessonFeedback['difficulty_rating'] ?? 0) ?>/5 ·
                    Clarté <?= (int) ($lessonFeedback['clarity_rating'] ?? 0) ?>/5 ·
                    Utilité <?= (int) ($lessonFeedback['utility_rating'] ?? 0) ?>/5
                </p>
                <?php endif; ?>
                <button type="button" class="lms-btn lms-btn--primary lms-btn--compact mt-4" data-lms-feedback-close>Continuer</button>
            </div>

            <?php if (!$hasLessonFeedback): ?>
            <div id="lms-feedback-form-wrap" class="lms-fb-form">
                <p class="lms-fb-lead">Notez la difficulté, la clarté et l’utilité terrain sur une échelle de 1 à 5. Votre retour aide à améliorer les contenus.</p>
                <form method="post" action="<?= url('api/training/lesson-feedback') ?>" data-lesson-feedback class="lms-fb-form__grid">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="enrollment_id" value="<?= $enrIdFb ?>">
                    <input type="hidden" name="lesson_id" value="<?= $lessonIdFb ?>">

                    <?php $ratingScale('difficulty_rating', 'Difficulté', 'Trop simple → trop dur'); ?>
                    <?php $ratingScale('clarity_rating', 'Clarté', 'Peu clair → très clair'); ?>
                    <?php $ratingScale('utility_rating', 'Utilité terrain', 'Peu utile → indispensable'); ?>

                    <label class="lms-fb-comment">
                        <span class="lms-fb-comment__label">Commentaire libre <em>(optionnel)</em></span>
                        <textarea name="comment" rows="3" maxlength="2000" class="lms-fb-comment__input" placeholder="Ce qui a aidé, ce qui manque, suggestions opérationnelles…"></textarea>
                    </label>

                    <div class="lms-fb-actions">
                        <button type="button" class="lms-fb-skip" data-lms-feedback-skip>Passer cette étape</button>
                        <button type="submit" class="lms-btn lms-btn--violet lms-btn--compact">Envoyer mon avis</button>
                    </div>
                    <p id="lms-feedback-status" class="lms-fb-status" role="status"></p>
                </form>
            </div>
            <?php else: ?>
            <p id="lms-feedback-status" class="sr-only" role="status">Retour déjà enregistré.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
