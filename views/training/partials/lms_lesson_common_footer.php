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
$finParcoursUrl = '';
$finParcoursLabel = 'Fin du parcours';
$finParcoursTitle = 'Avis & échanges';
$lessonFeedback = is_array($lessonFeedback ?? null) ? $lessonFeedback : null;
$eventRecommendation = is_array($eventRecommendation ?? null) ? $eventRecommendation : null;
$courseCompletedForFooter = !empty($courseCompletedForFooter);
$certificateUrlForFooter = trim((string) ($certificateUrlForFooter ?? ''));
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
        $showFinParcours = true;
        if ($courseCompletedForFooter && $certificateUrlForFooter !== '') {
            $finParcoursUrl = $certificateUrlForFooter;
            $finParcoursLabel = 'Réussite du parcours';
            $finParcoursTitle = 'Voir mon attestation';
        } elseif ($courseCompletedForFooter && $courseSlugNav !== '') {
            $finParcoursUrl = url('formations/' . rawurlencode($courseSlugNav));
            $finParcoursLabel = 'Réussite du parcours';
            $finParcoursTitle = 'Attestation et bilan';
        } else {
            $finParcoursUrl = $echangesUrl;
            $finParcoursLabel = 'Fin du parcours';
            $finParcoursTitle = 'Avis & échanges';
        }
        $showFinParcours = $finParcoursUrl !== '';
    }
} else {
    $nextUrl = $nextLesson ? url('formations/lesson/' . (int) $nextLesson['id'] . '?enrollment_id=' . $enrId) : '';
    $footerNextLessonTitle = (string) ($nextLesson['title'] ?? '');
    if ($echangesUrl !== '' && $nextLesson === null) {
        $showFinParcours = true;
        $finParcoursUrl = $courseCompletedForFooter && $certificateUrlForFooter !== ''
            ? $certificateUrlForFooter
            : ($courseCompletedForFooter && $courseSlugNav !== ''
                ? url('formations/' . rawurlencode($courseSlugNav))
                : $echangesUrl);
        $finParcoursLabel = $courseCompletedForFooter ? 'Réussite du parcours' : 'Fin du parcours';
        $finParcoursTitle = $courseCompletedForFooter
            ? ($certificateUrlForFooter !== '' ? 'Voir mon attestation' : 'Attestation et bilan')
            : 'Avis & échanges';
    }
}
?>
                <?php if (!empty($resources)): ?>
                <div class="lms-lesson-annexes" id="lms-lesson-annexes">
                    <div class="lms-lesson-annexes__head">
                        <h3>Annexes du formateur</h3>
                        <span><?= count($resources) ?> élément<?= count($resources) > 1 ? 's' : '' ?></span>
                    </div>
                    <ul class="lms-lesson-resources space-y-3">
                        <?php foreach ($resources as $r):
                            $rIsImage = function_exists('training_lms_resource_is_image') && training_lms_resource_is_image($r) && !empty($r['file_path']);
                            $rImgUrl = $rIsImage
                                ? url('api/training/resource/' . (int) $r['id'] . '/download?inline=1')
                                : '';
                            $rTitle = trim((string) ($r['title'] ?? ''));
                            if ($rTitle === '') {
                                $rTitle = 'Annexe';
                            }
                            $rKind = function_exists('training_lms_studio_resource_kind_label_fr')
                                ? training_lms_studio_resource_kind_label_fr($r)
                                : '';
                            $rExt = trim((string) ($r['external_url'] ?? ''));
                            $rExtIsSite = $rExt !== '' && (new \App\Services\Navigation\ExternalLeaveService())->isInternalUrl($rExt);
                            // Google Docs / Sheets / Slides / draw.io… : visionneuse directement dans la leçon.
                            $rExtEmbed = ($rExt !== '' && !$rExtIsSite && function_exists('training_url_viewer_embed_src'))
                                ? training_url_viewer_embed_src($rExt)
                                : null;
                        ?>
                        <li class="<?= $rIsImage ? 'lms-lesson-resources__image' : '' ?>">
                            <?php if ($rIsImage && $rImgUrl !== ''): ?>
                            <figure class="lms-res-figure">
                                <a href="<?= htmlspecialchars($rImgUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                    <img src="<?= htmlspecialchars($rImgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($rTitle) ?>" loading="lazy" class="lms-res-figure__img">
                                </a>
                                <figcaption class="mt-1.5 text-sm font-medium text-slate-700"><?= htmlspecialchars($rTitle) ?></figcaption>
                            </figure>
                            <?php elseif (($r['resource_type'] ?? '') === 'library_document' && !empty($r['document_id'])): ?>
                            <a href="<?= htmlspecialchars(url('api/training/resource/' . (int) $r['id'] . '/document?inline=1'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="lms-lesson-annexes__file">
                                <span class="lms-lesson-annexes__kind">Document</span>
                                <span class="lms-lesson-annexes__name"><?= htmlspecialchars($rTitle) ?></span>
                            </a>
                            <?php elseif (!empty($r['file_path'])): ?>
                            <a href="<?= url('api/training/resource/' . (int) $r['id'] . '/download') ?>" class="lms-lesson-annexes__file">
                                <span class="lms-lesson-annexes__kind"><?= htmlspecialchars($rKind !== '' ? $rKind : 'Fichier') ?></span>
                                <span class="lms-lesson-annexes__name"><?= htmlspecialchars($rTitle) ?></span>
                            </a>
                            <?php elseif ($rExtEmbed !== null): ?>
                            <?php
                            $viewerUrl = $rExt;
                            $viewerTitle = $rTitle;
                            $viewerForceFrame = false;
                            $viewerCompact = true;
                            require base_path('views/training/partials/url_viewer.php');
                            ?>
                            <?php elseif ($rExt !== ''): ?>
                            <?php $extResHref = training_lms_resource_external_href($rExt); ?>
                            <?php if ($extResHref !== null): ?>
                            <a href="<?= htmlspecialchars($extResHref, ENT_QUOTES, 'UTF-8') ?>"<?= $rExtIsSite ? '' : ' target="_blank" rel="noopener noreferrer"' ?> class="lms-lesson-annexes__file">
                                <span class="lms-lesson-annexes__kind">Lien</span>
                                <span class="lms-lesson-annexes__name"><?= htmlspecialchars($rTitle) ?></span>
                            </a>
                            <?php else: ?>
                            <span class="text-slate-600"><?= htmlspecialchars($rTitle) ?></span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-slate-600"><?= htmlspecialchars($rTitle) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                        <div class="lms-lesson-progress-block mt-4 flex flex-col gap-2.5 border-t border-slate-100 pt-4">
                            <p id="lms-progress-status" class="text-sm leading-relaxed <?= $lessonAlreadyCompleted ? 'text-slate-500' : 'text-slate-600' ?>" role="status">
                                <?php if ($lessonAlreadyCompleted): ?>
                                Vous pouvez poursuivre avec la navigation en bas de page.
                                <?php elseif ($autoLessonComplete): ?>
                                Parcourez tout le contenu ci-dessus. Quand c’est fait, le bouton « Terminer la leçon » se débloque pour enregistrer votre validation.
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
                                <button type="submit" id="lms-btn-complete" class="lms-btn lms-btn--primary">Terminer la leçon</button>
                            </form>
                            <?php elseif (!$lessonAlreadyCompleted && $autoLessonComplete): ?>
                            <button type="button" id="lms-btn-complete" class="lms-btn lms-btn--disabled" disabled data-lms-await-parcours="1" aria-describedby="lms-progress-status">Terminer la leçon</button>
                            <?php else: ?>
                            <span id="lms-btn-complete" class="sr-only">Leçon validée</span>
                            <?php endif; ?>
                            </div>
                        </div>

                        <section id="lms-event-recommendation" class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3.5 <?= $eventRecommendation ? '' : 'hidden' ?>" aria-live="polite">
                            <?php if ($eventRecommendation): ?>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Événement d’entraînement recommandé</p>
                            <h3 class="mt-1 text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($eventRecommendation['label'] ?? 'Créneau recommandé')) ?></h3>
                            <p class="mt-1 text-xs text-slate-600">
                                Début : <?= htmlspecialchars((string) ($eventRecommendation['starts_at'] ?? '')) ?>
                                <?php if (!empty($eventRecommendation['location'])): ?>
                                · Lieu : <?= htmlspecialchars((string) $eventRecommendation['location']) ?>
                                <?php endif; ?>
                            </p>
                            <a href="<?= htmlspecialchars($courseSlugNav !== '' ? url('formations/' . rawurlencode($courseSlugNav)) : url('formations')) ?>" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:underline">
                                Voir le parcours et ses créneaux →
                            </a>
                            <?php endif; ?>
                        </section>
                        <?php if ($prevUrl !== '' || $nextUrl !== '' || $footerQuiz !== null || $showFinParcours): ?>
                        <nav class="lms-lesson-nav-footer mt-3 flex flex-wrap items-start justify-between gap-3 border-t border-slate-200 pt-3 pb-0.5" aria-label="Navigation du parcours">
                            <div class="min-w-0 max-w-[48%]">
                                <?php if ($prevUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($prevUrl) ?>" title="<?= htmlspecialchars((string) ($prevLesson['title'] ?? '')) ?>" class="group inline-flex flex-col gap-1">
                                    <span class="text-sm font-semibold text-slate-800 group-hover:text-emerald-800">← Précédent</span>
                                    <span class="line-clamp-2 text-xs leading-snug text-slate-500"><?= htmlspecialchars((string) ($prevLesson['title'] ?? '')) ?></span>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="ml-auto min-w-0 max-w-[48%] text-right">
                                <?php if ($nextUrl !== '' || $footerQuiz !== null || $showFinParcours): ?>
                                <p class="text-xs text-slate-500">Le bouton en bas à droite permet de poursuivre le parcours.</p>
                                <?php endif; ?>
                            </div>
                        </nav>
                        <?php endif; ?>
                        <?php
                        $lmsStickyNext = [
                            'nextUrl' => $nextUrl,
                            'footerNextLessonTitle' => $footerNextLessonTitle,
                            'footerQuiz' => $footerQuiz,
                            'showFinParcours' => $showFinParcours,
                            'echangesUrl' => $echangesUrl,
                            'finParcoursUrl' => $finParcoursUrl,
                            'finParcoursLabel' => $finParcoursLabel,
                            'finParcoursTitle' => $finParcoursTitle,
                            'enrId' => $enrId,
                        ];
                        ?>
