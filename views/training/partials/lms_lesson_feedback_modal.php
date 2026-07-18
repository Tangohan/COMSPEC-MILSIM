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
$lessonFeedback = is_array($lessonFeedback ?? null) ? $lessonFeedback : null;
$hasLessonFeedback = $lessonFeedback !== null;
?>
<div id="lms-feedback-modal" class="lms-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="lms-feedback-modal-title" aria-hidden="true" data-lms-feedback-modal data-lms-feedback-done="<?= $hasLessonFeedback ? '1' : '0' ?>">
    <div class="lms-modal-overlay__backdrop" data-lms-feedback-close tabindex="-1" aria-hidden="true"></div>
    <div class="lms-modal-panel lms-modal-panel--feedback" role="document">
        <div class="lms-modal-panel__head">
            <h2 id="lms-feedback-modal-title" class="lms-modal-panel__title">Votre avis sur cette leçon</h2>
            <button type="button" class="lms-modal-panel__close" data-lms-feedback-close aria-label="Fermer">×</button>
        </div>
        <div class="lms-modal-panel__body">
            <div id="lms-feedback-done" class="<?= $hasLessonFeedback ? '' : 'hidden' ?>">
                <p class="text-sm font-semibold text-slate-900">Merci : votre retour a bien été enregistré.</p>
                <?php if ($hasLessonFeedback): ?>
                <p class="mt-2 text-sm text-slate-700">
                    Difficulté <?= (int) ($lessonFeedback['difficulty_rating'] ?? 0) ?>/5 ·
                    Clarté <?= (int) ($lessonFeedback['clarity_rating'] ?? 0) ?>/5 ·
                    Utilité <?= (int) ($lessonFeedback['utility_rating'] ?? 0) ?>/5
                </p>
                <?php endif; ?>
            </div>
            <?php if (!$hasLessonFeedback): ?>
            <div id="lms-feedback-form-wrap">
                <p class="mb-3 text-sm text-slate-600">Indiquez la difficulté, la clarté et l’utilité terrain (échelle de 1 à 5) pour aider à améliorer les contenus.</p>
                <form method="post" action="<?= url('api/training/lesson-feedback') ?>" data-lesson-feedback class="grid gap-3 sm:grid-cols-3">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="enrollment_id" value="<?= $enrIdFb ?>">
                    <input type="hidden" name="lesson_id" value="<?= (int) ($lesson['id'] ?? 0) ?>">
                    <label class="text-xs font-semibold text-slate-700">
                        Difficulté
                        <select name="difficulty_rating" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?>/5</option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-700">
                        Clarté
                        <select name="clarity_rating" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?>/5</option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-700">
                        Utilité terrain
                        <select name="utility_rating" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?>/5</option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label class="sm:col-span-3 text-xs font-semibold text-slate-700">
                        Commentaire libre (optionnel)
                        <textarea name="comment" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm" placeholder="Ce qui a aidé, ce qui manque, suggestions opérationnelles…"></textarea>
                    </label>
                    <div class="sm:col-span-3 flex flex-wrap items-center gap-3">
                        <button type="submit" class="lms-btn lms-btn--violet lms-btn--compact">Envoyer mon avis</button>
                        <p id="lms-feedback-status" class="text-xs text-slate-600" role="status"></p>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <p id="lms-feedback-status" class="sr-only" role="status">Retour déjà enregistré.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
