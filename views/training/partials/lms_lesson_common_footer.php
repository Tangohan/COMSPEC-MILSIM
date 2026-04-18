<?php
declare(strict_types=1);
/** @var array<int, array<string, mixed>> $resources */
/** @var array<string, mixed> $lesson */
/** @var array<string, mixed> $enrollment */
/** @var array<string, mixed>|null $course */
/** @var bool $lessonAlreadyCompleted */
/** @var bool $autoLessonComplete */
/** @var string $csrf */
/** @var array<string, mixed>|null $prevLesson */
/** @var array<string, mixed>|null $nextLesson */
/** @var array<string, mixed>|null $footerNext */
/** @var array<string, mixed>|null $lessonFeedback */
/** @var array<string, mixed>|null $eventRecommendation */
$enrId = (int) $enrollment['id'];
$prevUrl = $prevLesson ? url('formations/lesson/' . (int) $prevLesson['id'] . '?enrollment_id=' . $enrId) : '';
$c = $course ?? [];
$courseSlugNav = trim((string) ($c['slug'] ?? $enrollment['course_slug'] ?? ''));
$echangesUrl = $courseSlugNav !== ''
    ? url('formations/' . rawurlencode($courseSlugNav) . '/echanges')
    : '';
$footerNext = is_array($footerNext ?? null) ? $footerNext : null;
$nextUrl = '';
$footerQuiz = null;
$showFinParcours = false;
$footerNextLessonTitle = '';
$lessonFeedback = is_array($lessonFeedback ?? null) ? $lessonFeedback : null;
$eventRecommendation = is_array($eventRecommendation ?? null) ? $eventRecommendation : null;
if ($footerNext !== null) {
    $k = (string) ($footerNext['kind'] ?? '');
    if ($k === 'lesson' && !empty($footerNext['lesson']) && is_array($footerNext['lesson'])) {
        $nl = $footerNext['lesson'];
        $nid = (int) ($nl['id'] ?? 0);
        if ($nid > 0) {
            $nextUrl = url('formations/lesson/' . $nid . '?enrollment_id=' . $enrId);
            $footerNextLessonTitle = (string) ($nl['title'] ?? '');
        }
    } elseif ($k === 'quiz' && !empty($footerNext['quiz']) && is_array($footerNext['quiz'])) {
        $footerQuiz = $footerNext['quiz'];
    } elseif ($k === 'echanges') {
        $showFinParcours = $echangesUrl !== '';
    }
} else {
    $nextUrl = $nextLesson ? url('formations/lesson/' . (int) $nextLesson['id'] . '?enrollment_id=' . $enrId) : '';
    $footerNextLessonTitle = (string) ($nextLesson['title'] ?? '');
    $showFinParcours = $echangesUrl !== '' && $nextLesson === null;
}
?>
                <?php if (!empty($resources)): ?>
                <div class="mt-10 border-t border-slate-200 pt-8">
                    <h3 class="mb-3 text-sm font-semibold text-slate-700">Ressources</h3>
                    <ul class="space-y-2">
                        <?php foreach ($resources as $r): ?>
                        <li>
                            <?php if (($r['resource_type'] ?? '') === 'library_document' && !empty($r['document_id'])): ?>
                            <a href="<?= htmlspecialchars(url('api/training/resource/' . (int) $r['id'] . '/document?inline=1'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="font-medium text-emerald-600 hover:underline"><?= htmlspecialchars((string) $r['title']) ?></a>
                            <?php elseif (!empty($r['file_path'])): ?>
                            <a href="<?= url('api/training/resource/' . (int) $r['id'] . '/download') ?>" class="font-medium text-emerald-600 hover:underline"><?= htmlspecialchars((string) $r['title']) ?></a>
                            <?php elseif (!empty($r['external_url'])): ?>
                            <?php $extResHref = training_lms_resource_external_href((string) $r['external_url']); ?>
                            <?php if ($extResHref !== null): ?>
                            <a href="<?= htmlspecialchars($extResHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="font-medium text-emerald-600 hover:underline"><?= htmlspecialchars((string) $r['title']) ?></a>
                            <?php else: ?>
                            <span class="text-slate-600"><?= htmlspecialchars((string) $r['title']) ?></span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-slate-600"><?= htmlspecialchars((string) $r['title']) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                        <div class="mt-10 flex flex-col gap-4 border-t border-slate-100 pt-8">
                            <p id="lms-progress-status" class="text-sm leading-relaxed <?= $lessonAlreadyCompleted ? 'text-slate-500' : 'text-slate-600' ?>" role="status">
                                <?php if ($lessonAlreadyCompleted): ?>
                                Vous pouvez poursuivre avec la navigation en bas de page.
                                <?php elseif ($autoLessonComplete): ?>
                                Parcourez tout le contenu ci-dessus : la leçon sera enregistrée automatiquement une fois le parcours complété.
                                <?php else: ?>
                                Lorsque vous avez terminé cette leçon, enregistrez votre progression avec le bouton ci-dessous.
                                <?php endif; ?>
                            </p>
                            <div class="flex flex-wrap gap-3">
                            <?php if (!$lessonAlreadyCompleted && !$autoLessonComplete): ?>
                            <form method="post" action="<?= url('api/training/progress/lesson') ?>" class="inline" data-progress-lesson>
                                <?= $csrf ?>
                                <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
                                <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" id="lms-btn-complete" class="lms-btn lms-btn--primary">Enregistrer la leçon comme terminée</button>
                            </form>
                            <?php elseif (!$lessonAlreadyCompleted && $autoLessonComplete): ?>
                            <button type="button" id="lms-btn-complete" class="lms-btn lms-btn--disabled" disabled>Validation automatique</button>
                            <?php else: ?>
                            <span id="lms-btn-complete" class="sr-only">Leçon validée</span>
                            <?php endif; ?>
                            </div>
                        </div>
                        <section class="mt-8 rounded-2xl border border-violet-200 bg-violet-50/50 p-5" aria-label="Feedback post-leçon">
                            <div class="mb-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Feedback post-leçon</p>
                                <h3 class="text-sm font-bold text-slate-900">Difficulté, clarté, utilité</h3>
                                <p class="mt-1 text-xs text-slate-600">Format standardisé (échelle 1 à 5) pour améliorer les contenus et ajuster les parcours.</p>
                            </div>
                            <form method="post" action="<?= url('api/training/lesson-feedback') ?>" data-lesson-feedback class="grid gap-3 sm:grid-cols-3">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="enrollment_id" value="<?= $enrId ?>">
                                <input type="hidden" name="lesson_id" value="<?= (int) ($lesson['id'] ?? 0) ?>">
                                <label class="text-xs font-semibold text-slate-700">
                                    Difficulté
                                    <select name="difficulty_rating" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" required>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?= $i ?>" <?= (int) ($lessonFeedback['difficulty_rating'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Clarté
                                    <select name="clarity_rating" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" required>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?= $i ?>" <?= (int) ($lessonFeedback['clarity_rating'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Utilité terrain
                                    <select name="utility_rating" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm" required>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?= $i ?>" <?= (int) ($lessonFeedback['utility_rating'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?>/5</option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <label class="sm:col-span-3 text-xs font-semibold text-slate-700">
                                    Commentaire libre (optionnel)
                                    <textarea name="comment" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm" placeholder="Ce qui a aidé, ce qui manque, suggestions opérationnelles…"><?= htmlspecialchars((string) ($lessonFeedback['comment'] ?? '')) ?></textarea>
                                </label>
                                <div class="sm:col-span-3 flex flex-wrap items-center gap-3">
                                    <button type="submit" class="lms-btn lms-btn--violet lms-btn--compact">Envoyer le feedback</button>
                                    <p id="lms-feedback-status" class="text-xs text-slate-600" role="status">
                                        <?= $lessonFeedback ? 'Feedback déjà enregistré (vous pouvez le mettre à jour).' : 'Aucun feedback enregistré pour cette leçon.' ?>
                                    </p>
                                </div>
                            </form>
                        </section>
                        <section id="lms-event-recommendation" class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 <?= $eventRecommendation ? '' : 'hidden' ?>" aria-live="polite">
                            <?php if ($eventRecommendation): ?>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Événement d’entraînement recommandé</p>
                            <h3 class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($eventRecommendation['label'] ?? 'Créneau recommandé')) ?></h3>
                            <p class="mt-1 text-xs text-slate-600">
                                Début : <?= htmlspecialchars((string) ($eventRecommendation['starts_at'] ?? '')) ?>
                                <?php if (!empty($eventRecommendation['location'])): ?>
                                · Lieu : <?= htmlspecialchars((string) $eventRecommendation['location']) ?>
                                <?php endif; ?>
                            </p>
                            <a href="<?= htmlspecialchars($courseSlugNav !== '' ? url('formations/' . rawurlencode($courseSlugNav)) : url('formations')) ?>" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:underline">
                                Voir le parcours et ses créneaux →
                            </a>
                            <?php endif; ?>
                        </section>
                        <?php if ($prevUrl !== '' || $nextUrl !== '' || $footerQuiz !== null || $showFinParcours): ?>
                        <nav class="lms-lesson-nav-footer mt-8 flex flex-wrap items-stretch justify-between gap-6 border-t border-slate-200 pt-6" aria-label="Navigation du parcours">
                            <div class="min-w-0 max-w-[48%]">
                                <?php if ($prevUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($prevUrl) ?>" title="<?= htmlspecialchars((string) ($prevLesson['title'] ?? '')) ?>" class="group inline-flex flex-col gap-1.5">
                                    <span class="text-sm font-semibold text-slate-800 group-hover:text-emerald-800">← Précédent</span>
                                    <span class="line-clamp-2 text-xs text-slate-500"><?= htmlspecialchars((string) ($prevLesson['title'] ?? '')) ?></span>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="ml-auto min-w-0 max-w-[48%] text-right">
                                <?php if ($nextUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($nextUrl) ?>" title="<?= htmlspecialchars($footerNextLessonTitle) ?>" class="lms-lesson-nav-next group inline-flex flex-col items-end gap-1.5 no-underline">
                                    <span class="lms-btn lms-btn--primary lms-btn--compact">Suivant →</span>
                                    <span class="line-clamp-2 text-right text-xs text-slate-500"><?= htmlspecialchars($footerNextLessonTitle) ?></span>
                                </a>
                                <?php elseif ($footerQuiz !== null && (int) ($footerQuiz['id'] ?? 0) > 0): ?>
                                <div class="group inline-flex flex-col items-end gap-1.5">
                                    <form method="post" action="<?= url('formations/quiz/start') ?>" class="inline">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="quiz_id" value="<?= (int) $footerQuiz['id'] ?>">
                                        <input type="hidden" name="enrollment_id" value="<?= $enrId ?>">
                                        <button type="submit" class="lms-btn lms-btn--violet lms-btn--compact">Évaluation suivante →</button>
                                    </form>
                                    <span class="line-clamp-2 text-right text-xs text-slate-500"><?= htmlspecialchars((string) ($footerQuiz['title'] ?? 'Évaluation')) ?></span>
                                </div>
                                <?php elseif ($showFinParcours): ?>
                                <a href="<?= htmlspecialchars($echangesUrl) ?>" class="group inline-flex flex-col items-end gap-1.5">
                                    <span class="lms-btn lms-btn--dark lms-btn--compact">Fin du parcours — Avis &amp; échanges →</span>
                                    <span class="line-clamp-2 text-right text-xs text-slate-500">Note, questions et commentaires sur une page dédiée</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </nav>
                        <?php endif; ?>
