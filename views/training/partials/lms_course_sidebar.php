<?php
declare(strict_types=1);
/** @var array<string, mixed> $course */
/** @var array<string, mixed>|null $enrollment */
/** @var float|int $progressPercent */
/** @var int|null $currentLessonId */
/** @var string $lmsBase */
/** @var string|null $lmsSequenceContext preamble|lesson|quiz|echanges */
/** @var int|null $lmsSequenceQuizId */
$lmsBase = $lmsBase ?? url('');
$course = $course ?? [];
$enrollment = $enrollment ?? null;
$progressPercent = (float) ($progressPercent ?? 0);
$currentLessonId = isset($currentLessonId) ? (int) $currentLessonId : null;
$lmsHideEchangesSidebarLink = !empty($lmsHideEchangesSidebarLink);
$canWithdrawEnrollment = !empty($canWithdrawEnrollment);
/** @var array<int, true> $lmsCompletedLessonIds */
$lmsCompletedLessonIds = is_array($lmsCompletedLessonIds ?? null) ? $lmsCompletedLessonIds : [];
/** @var array<int, true> $lmsPassedQuizIds */
$lmsPassedQuizIds = is_array($lmsPassedQuizIds ?? null) ? $lmsPassedQuizIds : [];
if (!isset($lessonResources)) {
    $lessonResources = (isset($resources) && is_array($resources)) ? $resources : [];
}
$courseSlug = (string) ($course['slug'] ?? '');
$code = (string) ($course['course_code'] ?? '');
if ($code === '') {
    $code = 'F-' . (int) ($course['id'] ?? 0);
}
$courseUrl = $courseSlug !== ''
    ? htmlspecialchars($lmsBase) . '/formations/' . rawurlencode($courseSlug)
    : htmlspecialchars($lmsBase) . '/formations';

$seqSteps = function_exists('training_lms_build_guided_sequence')
    ? training_lms_build_guided_sequence($course)
    : [];
$seqContext = (string) ($lmsSequenceContext ?? 'preamble');
$seqQuizId = isset($lmsSequenceQuizId) ? (int) $lmsSequenceQuizId : null;

// Sur la fiche parcours : si une leçon reste à faire, la position courante = cette leçon (pas le préambule).
if ($seqContext === 'preamble' && $enrollment && $currentLessonId === null) {
    $continueId = 0;
    if (isset($continueLesson) && is_array($continueLesson)) {
        $continueId = (int) ($continueLesson['id'] ?? 0);
    }
    if ($continueId < 1 && function_exists('training_lms_next_incomplete_lesson') && function_exists('training_lms_ordered_lessons')) {
        $ordered = training_lms_ordered_lessons($course);
        $progressRows = [];
        foreach ($lmsCompletedLessonIds as $cid => $_) {
            $progressRows[] = ['lesson_id' => (int) $cid, 'status' => 'completed'];
        }
        $nextInc = training_lms_next_incomplete_lesson($ordered, $progressRows);
        $continueId = (int) ($nextInc['id'] ?? 0);
    }
    if ($continueId > 0 && $lmsCompletedLessonIds !== []) {
        $seqContext = 'lesson';
        $currentLessonId = $continueId;
    } elseif ($continueId > 0 && $lmsCompletedLessonIds === [] && !empty($lmsSequenceSkipPreamble)) {
        $seqContext = 'lesson';
        $currentLessonId = $continueId;
    }
}

$seqPos = function_exists('training_lms_sequence_position')
    ? training_lms_sequence_position($seqSteps, $seqContext, $currentLessonId, $seqQuizId)
    : ['index' => 0, 'total' => count($seqSteps), 'current' => $seqSteps[0] ?? null, 'next' => $seqSteps[1] ?? null, 'previous' => null];
$seqIndex = (int) ($seqPos['index'] ?? 0);
$seqNext = is_array($seqPos['next'] ?? null) ? $seqPos['next'] : null;
$seqNextLabel = function_exists('training_lms_sequence_step_human_label')
    ? training_lms_sequence_step_human_label($seqNext)
    : '';
?>
<aside class="lms-dark-panel lms-course-aside w-full min-w-0 shrink-0 text-white flex flex-col lg:sticky lg:top-0 lg:self-start lg:overflow-hidden">
    <div class="lms-course-aside__head shrink-0 px-4 lg:px-5 pt-4 lg:pt-5 pb-4 border-b border-white/10">
        <a href="<?= htmlspecialchars($lmsBase) ?>/formations" class="text-[10px] font-black uppercase tracking-widest text-emerald-400 hover:text-white">← Catalogue</a>
        <h1 class="mt-3 text-base font-black tracking-tight uppercase leading-tight"><?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h1>
        <p class="text-[10px] font-mono text-emerald-400/90 mt-1.5"><?= htmlspecialchars($code) ?></p>
        <?php if ($enrollment): ?>
        <div class="mt-3">
            <div class="flex justify-between text-[10px] font-black uppercase text-white/50 mb-1">
                <span>Progression</span>
                <span class="tabular-nums"><?= (int) round($progressPercent) ?> %</span>
            </div>
            <div class="lms-progress-bar h-1.5 bg-white/10 rounded-full overflow-hidden">
                <span style="width: <?= min(100, max(0, $progressPercent)) ?>%"></span>
            </div>
        </div>
        <?php if ($seqNextLabel !== ''): ?>
        <p class="mt-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-2 text-[10px] leading-snug text-emerald-100/95">
            <span class="font-black uppercase tracking-wider text-emerald-300/90">Prochaine étape</span><br>
            <?= htmlspecialchars($seqNextLabel) ?>
        </p>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <nav class="lms-sidebar-scroll lms-course-aside__nav min-h-0 flex-1 overscroll-contain px-4 lg:px-5 py-3 space-y-1" aria-label="Déroulement du parcours">
        <?php
        $lastPhase = null;
        foreach ($seqSteps as $si => $step):
            if (!is_array($step)) {
                continue;
            }
            $kind = (string) ($step['kind'] ?? '');
            $phase = (string) ($step['phase'] ?? '');
            $label = (string) ($step['label'] ?? '');
            $isCurrent = $si === $seqIndex;
            $isPast = $si < $seqIndex;
            $stepNum = $si + 1;

            if ($phase !== '' && $phase !== $lastPhase):
                $lastPhase = $phase;
                ?>
        <p class="pt-3 first:pt-0 text-[8px] font-black tracking-[0.28em] uppercase text-white/35 mb-1.5"><?= htmlspecialchars($phase) ?></p>
            <?php endif; ?>

            <?php if ($kind === 'preamble'): ?>
        <a href="<?= $courseUrl ?>#lms-parcours-debut" class="lms-seq-item <?= $isCurrent ? 'lms-seq-item--current' : ($isPast ? 'lms-seq-item--done' : 'lms-seq-item--todo') ?>">
            <span class="lms-seq-item__num" aria-hidden="true"><?= $isPast ? '✓' : $stepNum ?></span>
            <span class="lms-seq-item__body">
                <span class="lms-seq-item__title"><?= htmlspecialchars($label) ?></span>
                <span class="lms-seq-item__hint">Cadre et démarrage du parcours</span>
            </span>
        </a>
            <?php elseif ($kind === 'lesson'):
                $lid = (int) ($step['lesson']['id'] ?? 0);
                $lesSum = trim((string) ($step['lesson']['summary'] ?? ''));
                $href = ($enrollment && $lid > 0)
                    ? htmlspecialchars($lmsBase) . '/formations/lesson/' . $lid . '?enrollment_id=' . (int) $enrollment['id']
                    : $courseUrl . '#lms-parcours-debut';
                $doneByProgress = isset($lmsCompletedLessonIds[$lid]);
                ?>
        <a href="<?= $href ?>" title="<?= $lesSum !== '' ? htmlspecialchars($lesSum) : '' ?>" class="lms-seq-item <?= $isCurrent ? 'lms-seq-item--current' : (($isPast || $doneByProgress) ? 'lms-seq-item--done' : 'lms-seq-item--todo') ?>">
            <span class="lms-seq-item__num" aria-hidden="true"><?= ($isPast || $doneByProgress) && !$isCurrent ? '✓' : $stepNum ?></span>
            <span class="lms-seq-item__body">
                <span class="lms-seq-item__title"><?= htmlspecialchars($label) ?></span>
                <?php if ($lesSum !== ''): ?>
                <span class="lms-seq-item__hint"><?= htmlspecialchars($lesSum) ?></span>
                <?php elseif ($isCurrent): ?>
                <span class="lms-seq-item__hint">Étape en cours</span>
                <?php endif; ?>
            </span>
        </a>
            <?php elseif ($kind === 'quiz'):
                $qid = (int) ($step['quiz']['id'] ?? 0);
                $passed = isset($lmsPassedQuizIds[$qid]);
                ?>
                <?php if ($enrollment && $qid > 0): ?>
        <form method="post" action="<?= htmlspecialchars($lmsBase) ?>/formations/quiz/start" class="block">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="quiz_id" value="<?= $qid ?>">
            <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
            <button type="submit" class="lms-seq-item w-full text-left <?= $isCurrent ? 'lms-seq-item--current' : (($isPast || $passed) ? 'lms-seq-item--done' : 'lms-seq-item--todo') ?>">
                <span class="lms-seq-item__num" aria-hidden="true"><?= ($isPast || $passed) && !$isCurrent ? '✓' : $stepNum ?></span>
                <span class="lms-seq-item__body">
                    <span class="lms-seq-item__title"><?= htmlspecialchars($label) ?></span>
                    <span class="lms-seq-item__hint"><?= !empty($step['quiz']['is_final']) ? 'Après les modules' : 'Après les leçons du module' ?></span>
                </span>
            </button>
        </form>
                <?php else: ?>
        <div class="lms-seq-item lms-seq-item--todo opacity-60">
            <span class="lms-seq-item__num"><?= $stepNum ?></span>
            <span class="lms-seq-item__body">
                <span class="lms-seq-item__title"><?= htmlspecialchars($label) ?></span>
            </span>
        </div>
                <?php endif; ?>
            <?php elseif ($kind === 'echanges' && !$lmsHideEchangesSidebarLink): ?>
        <a href="<?= $courseSlug !== '' ? htmlspecialchars($lmsBase) . '/formations/' . rawurlencode($courseSlug) . '/echanges' : '#' ?>" class="lms-seq-item <?= $isCurrent ? 'lms-seq-item--current' : ($isPast ? 'lms-seq-item--done' : 'lms-seq-item--todo') ?>">
            <span class="lms-seq-item__num" aria-hidden="true"><?= $isPast ? '✓' : $stepNum ?></span>
            <span class="lms-seq-item__body">
                <span class="lms-seq-item__title"><?= htmlspecialchars($label) ?></span>
                <span class="lms-seq-item__hint">À la fin du parcours</span>
            </span>
        </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!empty($lessonResources)): ?>
        <div class="pt-3 mt-2 border-t border-white/10" aria-label="Ressources de la leçon">
            <p class="text-[8px] font-black tracking-[0.28em] uppercase text-white/30 mb-1.5">Ressources</p>
            <ul class="space-y-1">
                <?php foreach ($lessonResources as $r):
                    $resId = (int) ($r['id'] ?? 0);
                    $resTitle = trim((string) ($r['title'] ?? ''));
                    if ($resId < 1 || $resTitle === '') {
                        continue;
                    }
                    $openBlank = false;
                    if (($r['resource_type'] ?? '') === 'library_document' && !empty($r['document_id'])) {
                        $resHref = url('api/training/resource/' . $resId . '/document?inline=1');
                        $openBlank = true;
                    } elseif (!empty($r['file_path'])) {
                        $resHref = url('api/training/resource/' . $resId . '/download');
                    } elseif (!empty($r['external_url'])) {
                        $extH = training_lms_resource_external_href((string) $r['external_url']);
                        $resHref = $extH ?? '';
                        $openBlank = $extH !== null;
                    } else {
                        $resHref = '';
                    }
                ?>
                <li>
                    <?php if ($resHref !== ''): ?>
                    <a href="<?= htmlspecialchars($resHref, ENT_QUOTES, 'UTF-8') ?>"<?= $openBlank ? ' target="_blank" rel="noopener noreferrer"' : '' ?> class="block rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-emerald-200/95 border border-emerald-500/25 hover:bg-emerald-500/10 leading-snug">
                        <?= htmlspecialchars($resTitle) ?>
                    </a>
                    <?php else: ?>
                    <span class="block rounded-lg px-2.5 py-1.5 text-[11px] text-white/45 leading-snug"><?= htmlspecialchars($resTitle) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </nav>

    <div class="lms-course-aside__foot shrink-0">
    <?php if ($enrollment && $canWithdrawEnrollment && $courseSlug !== ''): ?>
        <form method="post" action="<?= htmlspecialchars($lmsBase) ?>/formations/inscription/annuler" class="lms-course-aside__withdraw" data-ui-confirm="1" data-ui-confirm-title="Annuler l’inscription" data-ui-confirm-body="Annuler votre inscription à ce parcours ? Vous pourrez vous réinscrire depuis le catalogue si les conditions le permettent.">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
            <input type="hidden" name="return_path" value="<?= htmlspecialchars('formations/' . $courseSlug, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="lms-course-aside__withdraw-btn">
                Annuler mon inscription
            </button>
        </form>
    <?php endif; ?>
        <a href="<?= htmlspecialchars($lmsBase) ?>/dashboard" class="lms-course-aside__dash">
            <span class="lms-course-aside__dash-label">Tableau de bord</span>
            <span class="lms-course-aside__dash-hint" aria-hidden="true">←</span>
        </a>
    </div>
</aside>
