<?php
$base = url('');
$lesson = $lesson ?? null;
$enrollment = $enrollment ?? null;
$resources = $resources ?? [];
$progress = $progress ?? ['progress' => [], 'percent' => 0];
$course = $course ?? null;
$currentLessonId = isset($currentLessonId) ? (int) $currentLessonId : (int) ($lesson['id'] ?? 0);
if (!$lesson || !$enrollment || !$course) {
    echo '<p>Leçon ou inscription non trouvée.</p>';
    return;
}
$lessonType = $lesson['lesson_type'] ?? 'richtext';

/**
 * Bascule progressive du lecteur de diapositives (training_courses.lesson_player_mode).
 * `stage` = lecteur « scène 16:9 » ; toute autre valeur, ou colonne absente sur un
 * déploiement non migré, conserve le lecteur Swiper historique.
 *
 * `?player=stage` permet à un membre habilité de prévisualiser sans basculer la formation.
 */
$lessonPlayerMode = strtolower(trim((string) ($course['lesson_player_mode'] ?? 'legacy')));
if (isset($_GET['player']) && in_array($_GET['player'], ['stage', 'legacy'], true)) {
    $gateForPreview = \App\Core\Gate::getInstance();
    if ($gateForPreview->allows('training.manage') || $gateForPreview->allows('training.update') || $gateForPreview->allows('admin.access')) {
        $lessonPlayerMode = (string) $_GET['player'];
    }
}
$useStagePlayer = $lessonPlayerMode === 'stage';

$currentModule = $currentModule ?? null;
$prevLesson = $prevLesson ?? null;
$nextLesson = $nextLesson ?? null;
$footerNext = $footerNext ?? null;
$lessonStep = $lessonStep ?? null;
$moduleLessonStep = $moduleLessonStep ?? null;
$levelLabels = function_exists('training_course_level_labels_fr') ? training_course_level_labels_fr() : [];
$lessonObjectives = function_exists('training_lms_learning_objectives')
    ? training_lms_learning_objectives(['learning_objectives' => $lesson['learning_objectives'] ?? ''])
    : [];
$moduleObjectives = $currentModule && function_exists('training_lms_learning_objectives')
    ? training_lms_learning_objectives(['learning_objectives' => $currentModule['learning_objectives'] ?? ''])
    : [];
$diffKey = trim((string) ($lesson['difficulty'] ?? ''));
$diffLabel = $diffKey !== '' && isset($levelLabels[$diffKey]) ? $levelLabels[$diffKey] : '';
$lessonSummary = trim((string) ($lesson['summary'] ?? ''));
$learnerTypeLabels = function_exists('training_lesson_type_labels_learner_fr')
    ? training_lesson_type_labels_learner_fr()
    : (function_exists('training_lesson_type_labels_fr') ? training_lesson_type_labels_fr() : []);
$lessonTypeLabel = $learnerTypeLabels[$lessonType] ?? 'Leçon';
$csrf = \App\Core\Csrf::field();
$csrfToken = \App\Core\Csrf::token();
$theme = function_exists('training_lms_parse_theme') ? training_lms_parse_theme((string) ($course['theme_json'] ?? '')) : [];
$lmsTitle = (string) $lesson['title'];
$lmsBase = $base;
$lmsThemeVars = function_exists('training_lms_theme_css_vars') ? training_lms_theme_css_vars($theme) : '';

$pdfUrl = '';
if ($lessonType === 'pdf') {
    $pdfUrl = trim((string) ($lesson['external_url'] ?? ''));
    if ($pdfUrl === '' && !empty($resources)) {
        foreach ($resources as $r) {
            if (($r['resource_type'] ?? '') === 'library_document' && !empty($r['document_id'])) {
                $pdfUrl = url('api/training/resource/' . (int) $r['id'] . '/document?inline=1');
                break;
            }
            $rt = (string) ($r['resource_type'] ?? '');
            if (($rt === 'pdf' || $rt === 'attachment') && !empty($r['file_path'])) {
                $pdfUrl = url('api/training/resource/' . (int) $r['id'] . '/download');
                break;
            }
        }
    }
    if ($pdfUrl === '' && !empty($lesson['content'])) {
        $c = trim(strip_tags((string) $lesson['content']));
        if (str_starts_with($c, 'http') || str_starts_with($c, '/')) {
            $pdfUrl = $c;
        }
    }
}

$lessonProgressStatus = null;
foreach (($progress['progress'] ?? []) as $p) {
    if ((int) ($p['lesson_id'] ?? 0) === $currentLessonId) {
        $lessonProgressStatus = (string) ($p['status'] ?? '');
        break;
    }
}
$lessonAlreadyCompleted = ($lessonProgressStatus === 'completed');

/* Repères de progression humaine (ce qui reste à faire) */
$orderedLessonsForMeta = function_exists('training_lms_ordered_lessons')
    ? training_lms_ordered_lessons(is_array($course) ? $course : [])
    : [];
$completedLessonIds = [];
foreach (($progress['progress'] ?? []) as $pRow) {
    if ((string) ($pRow['status'] ?? '') === 'completed') {
        $cid = (int) ($pRow['lesson_id'] ?? 0);
        if ($cid > 0) {
            $completedLessonIds[$cid] = true;
        }
    }
}
$lessonsTotalCount = 0;
$lessonsDoneCount = 0;
$remainingLessonsList = [];
foreach ($orderedLessonsForMeta as $olMeta) {
    $olid = (int) ($olMeta['id'] ?? 0);
    if ($olid < 1) {
        continue;
    }
    $lessonsTotalCount++;
    if (isset($completedLessonIds[$olid])) {
        $lessonsDoneCount++;
    } else {
        $remainingLessonsList[] = $olMeta;
    }
}
$lessonsLeftCount = count($remainingLessonsList);
$remainingLessonsPreview = array_slice($remainingLessonsList, 0, 3);
$nextStepHumanLabel = '';
if (is_array($footerNext ?? null)) {
    $fnKind = (string) ($footerNext['kind'] ?? '');
    if ($fnKind === 'lesson' && !empty($footerNext['lesson']) && is_array($footerNext['lesson'])) {
        $nextStepHumanLabel = trim((string) ($footerNext['lesson']['title'] ?? ''));
    } elseif ($fnKind === 'quiz' && !empty($footerNext['quiz']) && is_array($footerNext['quiz'])) {
        $qTitle = trim((string) ($footerNext['quiz']['title'] ?? ''));
        $nextStepHumanLabel = $qTitle !== '' ? 'Évaluation — ' . $qTitle : 'Évaluation suivante';
    } elseif ($fnKind === 'echanges') {
        $nextStepHumanLabel = 'Avis et échanges de fin de parcours';
    }
}
if ($nextStepHumanLabel === '' && is_array($nextLesson ?? null)) {
    $nextStepHumanLabel = trim((string) ($nextLesson['title'] ?? ''));
}
$progressPctDisplay = (int) round((float) ($progress['percent'] ?? 0));

$quizData = null;
$modalsDeck = null;
$showDeck = null;
$canvasDeck = function_exists('training_canvas_decode') ? training_canvas_decode((string) ($lesson['content'] ?? '')) : null;
if ($lessonType === 'quiz' && !empty($lesson['content'])) {
    $quizData = json_decode((string) $lesson['content'], true);
    $quizData = is_array($quizData) ? $quizData : null;
}
if ($lessonType === 'modals' && !empty($lesson['content'])) {
    $modalsDeck = json_decode((string) $lesson['content'], true);
    $modalsDeck = is_array($modalsDeck) ? $modalsDeck : null;
}
if ($lessonType === 'slideshow' && !empty($lesson['content'])) {
    $showDeck = json_decode((string) $lesson['content'], true);
    $showDeck = is_array($showDeck) ? $showDeck : null;
}
$slideshowWithImages = 0;
if ($showDeck && !empty($showDeck['slides']) && is_array($showDeck['slides'])) {
    foreach ($showDeck['slides'] as $sl) {
        if (is_array($sl) && !empty($sl['imageUrl'])) {
            $slideshowWithImages++;
        }
    }
}
$modalsCount = 0;
if ($modalsDeck && !empty($modalsDeck['modals']) && is_array($modalsDeck['modals'])) {
    foreach ($modalsDeck['modals'] as $m) {
        if (is_array($m)) {
            $modalsCount++;
        }
    }
}
$quizQuestionCount = ($quizData && !empty($quizData['questions']) && is_array($quizData['questions']))
    ? count(array_filter($quizData['questions'], 'is_array'))
    : 0;

$autoLessonComplete = false;
if (!$lessonAlreadyCompleted) {
    if ($lessonType === 'canvas' && $canvasDeck && !empty($canvasDeck['slides'])) {
        $autoLessonComplete = true;
    } elseif ($lessonType === 'slideshow' && $slideshowWithImages > 0) {
        $autoLessonComplete = true;
    } elseif ($lessonType === 'modals' && $modalsCount > 0) {
        $autoLessonComplete = true;
    } elseif ($lessonType === 'quiz' && $quizQuestionCount > 0) {
        $autoLessonComplete = true;
    } elseif (in_array($lessonType, ['video', 'video_integrated'], true) && !empty($lesson['external_url'])) {
        $autoLessonComplete = true;
    } elseif ($lessonType === 'audio' && !empty($lesson['external_url'])) {
        $autoLessonComplete = true;
    } elseif ($lessonType === 'richtext' && !empty($lesson['content'])) {
        $autoLessonComplete = true;
    }
}

$lmsExtraHead = '';
$lmsExtraHead .= '<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">' . "\n";
$lmsExtraHead .= '<link href="' . htmlspecialchars($base) . '/assets/css/training-parcours-deck.css" rel="stylesheet">' . "\n";
// Le lecteur « scène » n'utilise pas Swiper : ne pas charger la librairie inutilement.
$needsSwiper = in_array($lessonType, ['canvas', 'slideshow'], true)
    && !($useStagePlayer && $lessonType === 'slideshow');
if ($useStagePlayer && $lessonType === 'slideshow') {
    $lmsExtraHead .= '<link href="' . htmlspecialchars($base) . '/assets/css/training-stage-player.css" rel="stylesheet">' . "\n";
    $lmsExtraHead .= '<script defer src="' . htmlspecialchars($base) . '/assets/js/training-stage-player.js"></script>' . "\n";
}
if ($needsSwiper) {
    $lmsExtraHead .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">' . "\n";
    $lmsExtraHead .= '<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>' . "\n";
}
if (($lesson['lesson_type'] ?? '') === 'canvas') {
    $lmsExtraHead .= '<link href="' . htmlspecialchars($base) . '/assets/css/training_canvas.css" rel="stylesheet">' . "\n";
}
$isCanvasLesson = $lessonType === 'canvas' && $canvasDeck && !empty($canvasDeck['slides']);
$isReadingLesson = in_array($lessonType, ['richtext', 'checklist'], true);
if (in_array($lessonType, ['video', 'video_integrated', 'audio'], true)) {
    $lmsExtraHead .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css">' . "\n";
}
if ($lessonType === 'pdf' && $pdfUrl !== '') {
    $lmsExtraHead .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>' . "\n";
}
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr" class="module-shell">
<head>
<?= $headHtml ?>
</head>
<body class="lms-deck-page bg-slate-100 text-slate-900 min-h-screen">
    <div class="lms-grain"></div>
    <div class="relative z-10 lms-course-shell flex min-h-screen min-w-0 flex-col lg:flex-row">
        <?php
        $lmsBase = $base;
        $progressPercent = (float) ($progress['percent'] ?? 0);
        $lmsSequenceContext = 'lesson';
        $lmsCompletedLessonIds = $completedLessonIds;
        $lmsPassedQuizIds = is_array($lmsPassedQuizIds ?? null) ? $lmsPassedQuizIds : [];
        require base_path('views/training/partials/lms_course_sidebar.php');
        ?>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="topbar sticky top-0 z-50">
                <div class="lms-lesson-topbar-inner px-4 sm:px-6 h-14 flex items-center justify-between gap-4 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
                    <a href="<?= url('formations/' . ($enrollment['course_slug'] ?? '')) ?>" class="flex items-center gap-3 group min-w-0">
                        <div class="brand-mark shrink-0"><span>L</span></div>
                        <div class="min-w-0">
                            <strong class="text-xs font-black uppercase tracking-wide text-slate-900 block">Leçon</strong>
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider">Retour au parcours</span>
                        </div>
                    </a>
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                        <button type="button" data-lms-reperes-open class="lms-topbar-reperes-btn" aria-haspopup="dialog" aria-controls="lms-reperes-modal">
                            Repères
                        </button>
                        <span class="text-xs font-bold text-slate-500"><span data-lms-header-pct><?= (float)($progress['percent'] ?? 0) ?></span> %</span>
                        <div class="w-20 sm:w-28 h-1.5 bg-slate-200 rounded-full overflow-hidden lms-progress-bar">
                            <span data-lms-header-progress style="width: <?= (float)($progress['percent'] ?? 0) ?>%"></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="section lms-lesson-main flex-1 min-w-0 overflow-x-hidden pb-24">
                <?php require base_path('views/training/partials/lms_lesson_deck_hero.php'); ?>

                <?php if ($moduleLessonStep !== null && (int) ($moduleLessonStep['total'] ?? 0) > 0): ?>
                <?php
                $mcur = max(1, (int) $moduleLessonStep['current']);
                $mtot = max(1, (int) $moduleLessonStep['total']);
                $mPct = (int) round(100 * $mcur / $mtot);
                ?>
                <section class="lms-deck-modmeter">
                    <div class="lms-deck-modmeter__head">
                        <span>Progression dans le module</span>
                        <strong>Leçon <?= $mcur ?> sur <?= $mtot ?></strong>
                    </div>
                    <div class="lms-deck-modmeter__bar" role="img" aria-label="Progression du module : leçon <?= $mcur ?> sur <?= $mtot ?>">
                        <span style="width: <?= $mPct ?>%"></span>
                    </div>
                </section>
                <?php endif; ?>

                <div class="lms-deck-row">
                    <section class="lms-deck-main lms-lesson-content-panel">
                        <?php require base_path('views/training/partials/lms_lesson_actions_bar.php'); ?>

                        <?php if ($isCanvasLesson): ?>
                            <?php
                            $deck = $canvasDeck;
                            require base_path('views/training/partials/canvas_lesson_player.php');
                            ?>
                        <?php else: ?>
                            <?php
                            $embedSrc = ($lessonType === 'video_embed' && !empty($lesson['external_url']) && function_exists('training_video_embed_iframe_src'))
                                ? training_video_embed_iframe_src((string) $lesson['external_url'])
                                : null;
                            $readingEyebrow = $isReadingLesson
                                ? (stripos((string) ($lesson['title'] ?? ''), 'retenir') !== false ? 'À retenir' : 'Fiche de synthèse')
                                : $lessonTypeLabel;
                            ?>
                            <div class="lms-deck-reading" <?= ($lessonType === 'richtext' && $autoLessonComplete) ? 'data-lms-richtext-root="1"' : '' ?>>
                                <p class="lms-deck-reading__eyebrow"><?= htmlspecialchars($readingEyebrow) ?></p>

                                <?php if ($lessonType === 'quiz' && $quizData): ?>
                                    <?php $quiz = $quizData;
                                    require base_path('views/training/partials/lesson_quiz_player.php'); ?>
                                <?php elseif ($lessonType === 'modals' && $modalsDeck): ?>
                                    <?php $deck = $modalsDeck;
                                    require base_path('views/training/partials/lesson_modals_player.php'); ?>
                                <?php elseif ($lessonType === 'slideshow' && $showDeck): ?>
                                    <?php $deck = $showDeck;
                                    require base_path($useStagePlayer
                                        ? 'views/training/partials/lesson_stage_player.php'
                                        : 'views/training/partials/lesson_slideshow_player.php'); ?>
                                <?php elseif ($lessonType === 'richtext' && !empty($lesson['content'])): ?>
                                    <div class="prose prose-slate max-w-none">
                                        <?= $lesson['content'] ?>
                                        <?php if ($autoLessonComplete): ?>
                                        <div id="lms-richtext-sentinel" class="h-3 w-full mt-10 clear-both" aria-hidden="true"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (in_array($lessonType, ['video', 'video_integrated'], true) && !empty($lesson['external_url'])): ?>
                                    <div class="rounded-xl overflow-hidden bg-slate-900 shadow-lg">
                                        <video id="lms-lesson-video" playsinline controls crossorigin src="<?= htmlspecialchars((string) $lesson['external_url']) ?>" class="w-full aspect-video"></video>
                                    </div>
                                <?php elseif ($lessonType === 'video_embed' && $embedSrc): ?>
                                    <div class="rounded-xl overflow-hidden bg-slate-900 shadow-lg aspect-video">
                                        <iframe class="w-full h-full min-h-[240px]" src="<?= htmlspecialchars($embedSrc) ?>" title="Vidéo" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
                                    </div>
                                <?php elseif ($lessonType === 'video_embed' && !empty($lesson['external_url'])): ?>
                                    <?php $videoEmbedLeave = training_lms_resource_external_href((string) $lesson['external_url']); ?>
                                    <p class="text-rose-600 text-sm">Cette vidéo ne peut pas être intégrée ici. <?php if ($videoEmbedLeave !== null): ?><a href="<?= htmlspecialchars($videoEmbedLeave, ENT_QUOTES, 'UTF-8') ?>" class="underline font-bold" target="_blank" rel="noopener noreferrer">Ouvrir le lien</a><?php endif; ?></p>
                                <?php elseif ($lessonType === 'audio' && !empty($lesson['external_url'])): ?>
                                    <div class="rounded-xl overflow-hidden bg-slate-100 p-4">
                                        <audio id="lms-lesson-audio" controls crossorigin src="<?= htmlspecialchars((string) $lesson['external_url']) ?>" class="w-full"></audio>
                                    </div>
                                <?php elseif ($lessonType === 'pdf' && $pdfUrl !== ''): ?>
                                    <div class="space-y-3">
                                        <div id="lms-pdf-toolbar" class="flex flex-wrap gap-2 text-xs"></div>
                                        <div id="lms-pdf-viewer" class="rounded-xl border border-slate-200 bg-slate-50 min-h-[480px] overflow-auto"></div>
                                        <p class="text-xs text-slate-500"><a href="<?= htmlspecialchars($pdfUrl) ?>" class="text-emerald-600 font-semibold hover:underline" target="_blank" rel="noopener">Ouvrir le document</a></p>
                                    </div>
                                <?php elseif ($lessonType === 'external_link' && !empty($lesson['external_url'])): ?>
                                    <?php $externalLessonHref = training_lms_resource_external_href((string) $lesson['external_url']); ?>
                                    <?php if ($externalLessonHref !== null): ?>
                                    <p class="mb-4">Ressource externe :</p>
                                    <a href="<?= htmlspecialchars($externalLessonHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="text-emerald-600 font-bold hover:underline">Ouvrir la ressource</a>
                                    <?php else: ?>
                                    <p class="text-rose-600 text-sm">Le lien indiqué pour cette leçon n’est pas utilisable.</p>
                                    <?php endif; ?>
                                <?php elseif ($lessonType === 'checklist' && !empty($lesson['content'])): ?>
                                    <div class="prose prose-slate max-w-none"><?= $lesson['content'] ?></div>
                                <?php else: ?>
                                    <p class="text-slate-500">Le contenu de cette leçon n’est pas encore disponible.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php require base_path('views/training/partials/lms_lesson_common_footer.php'); ?>
                    </section>

                    <?php require base_path('views/training/partials/lms_lesson_reperes_aside.php'); ?>
                </div>
            </main>
        </div>
    </div>

<?php
$lmsStickyNext = is_array($lmsStickyNext ?? null) ? $lmsStickyNext : null;
if ($lmsStickyNext !== null):
    $snUrl = (string) ($lmsStickyNext['nextUrl'] ?? '');
    $snTitle = (string) ($lmsStickyNext['footerNextLessonTitle'] ?? '');
    $snQuiz = is_array($lmsStickyNext['footerQuiz'] ?? null) ? $lmsStickyNext['footerQuiz'] : null;
    $snFin = !empty($lmsStickyNext['showFinParcours']);
    $snEch = (string) ($lmsStickyNext['echangesUrl'] ?? '');
    $snFinUrl = trim((string) ($lmsStickyNext['finParcoursUrl'] ?? ''));
    if ($snFinUrl === '') {
        $snFinUrl = $snEch;
    }
    $snFinLabel = trim((string) ($lmsStickyNext['finParcoursLabel'] ?? ''));
    if ($snFinLabel === '') {
        $snFinLabel = 'Fin du parcours';
    }
    $snFinTitle = trim((string) ($lmsStickyNext['finParcoursTitle'] ?? ''));
    if ($snFinTitle === '') {
        $snFinTitle = 'Avis & échanges';
    }
    $snEnr = (int) ($lmsStickyNext['enrId'] ?? 0);
    $lmsNextArrowSvg = '<svg class="lms-module-next-sticky__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>';
    if ($snUrl !== ''):
?>
<a href="<?= htmlspecialchars($snUrl) ?>" title="<?= htmlspecialchars($snTitle) ?>" class="lms-module-next-sticky" data-lms-module-next>
    <span class="lms-module-next-sticky__text">
        <span class="lms-module-next-sticky__label">Module suivant</span>
        <?php if ($snTitle !== ''): ?>
        <span class="lms-module-next-sticky__title"><?= htmlspecialchars($snTitle) ?></span>
        <?php endif; ?>
    </span>
    <?= $lmsNextArrowSvg ?>
</a>
<?php elseif ($snQuiz !== null && (int) ($snQuiz['id'] ?? 0) > 0): ?>
<form method="post" action="<?= url('formations/quiz/start') ?>" class="lms-module-next-sticky lms-module-next-sticky--form" data-lms-module-next>
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="quiz_id" value="<?= (int) $snQuiz['id'] ?>">
    <input type="hidden" name="enrollment_id" value="<?= $snEnr ?>">
    <button type="submit" class="lms-module-next-sticky__btn">
        <span class="lms-module-next-sticky__text">
            <span class="lms-module-next-sticky__label">Évaluation suivante</span>
            <span class="lms-module-next-sticky__title"><?= htmlspecialchars((string) ($snQuiz['title'] ?? 'Évaluation')) ?></span>
        </span>
        <?= $lmsNextArrowSvg ?>
    </button>
</form>
<?php elseif ($snFin && $snFinUrl !== ''): ?>
<a href="<?= htmlspecialchars($snFinUrl) ?>" class="lms-module-next-sticky" data-lms-module-next>
    <span class="lms-module-next-sticky__text">
        <span class="lms-module-next-sticky__label"><?= htmlspecialchars($snFinLabel) ?></span>
        <span class="lms-module-next-sticky__title"><?= htmlspecialchars($snFinTitle) ?></span>
    </span>
    <?= $lmsNextArrowSvg ?>
</a>
<?php
    endif;
endif;
?>

<?php if (in_array($lessonType, ['video', 'video_integrated', 'audio'], true)): ?>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.polyfilled.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var v = document.getElementById('lms-lesson-video');
  var a = document.getElementById('lms-lesson-audio');
  if (v && window.Plyr) { new Plyr(v); }
  if (a && window.Plyr) { new Plyr(a); }
});
</script>
<?php endif; ?>

<?php if ($lessonType === 'pdf' && $pdfUrl !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var url = <?= json_encode($pdfUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var container = document.getElementById('lms-pdf-viewer');
  if (!container || typeof pdfjsLib === 'undefined') return;
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  var loading = pdfjsLib.getDocument({ url: url, withCredentials: true }).promise;
  loading.then(function (pdf) {
    var toolbar = document.getElementById('lms-pdf-toolbar');
    if (toolbar) toolbar.textContent = 'Pages : ' + pdf.numPages;
    for (var p = 1; p <= Math.min(pdf.numPages, 20); p++) {
      (function (pageNum) {
        pdf.getPage(pageNum).then(function (page) {
          var scale = 1.2;
          var vp = page.getViewport({ scale: scale });
          var canvas = document.createElement('canvas');
          var ctx = canvas.getContext('2d');
          canvas.width = vp.width;
          canvas.height = vp.height;
          canvas.className = 'block max-w-full mx-auto mb-4 shadow border border-slate-200 bg-white';
          container.appendChild(canvas);
          page.render({ canvasContext: ctx, viewport: vp });
        });
      })(p);
    }
  }).catch(function () {
    container.innerHTML = '<p class="p-4 text-sm text-rose-600">Impossible d’afficher le PDF (CORS ou fichier). Utilisez le lien de téléchargement.</p>';
  });
});
</script>
<?php endif; ?>

<script>
window.__LMS_CSRF__ = <?= json_encode($csrfToken) ?>;
window.__LMS_LESSON_PROGRESS__ = <?= json_encode([
    'apiUrl' => url('api/training/progress/lesson'),
    'feedbackApiUrl' => url('api/training/lesson-feedback'),
    'enrollmentId' => (int) $enrollment['id'],
    'lessonId' => (int) $lesson['id'],
    'courseUrl' => url('formations/' . rawurlencode((string) ($enrollment['course_slug'] ?? ''))),
    'alreadyCompleted' => $lessonAlreadyCompleted,
    'hasFeedback' => is_array($lessonFeedback ?? null),
    'auto' => $autoLessonComplete,
    'lessonType' => $lessonType,
    'strict' => [
        'slideDwellMs' => 1800,
        'richtextSentinelMs' => 2800,
        'richtextScrollRatio' => 0.86,
        'mediaPlayedMinRatio' => 0.88,
        'modalMinOpenMs' => 2600,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/lms_training_toast.js"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/training_lesson_progress.js" defer></script>
<?php require base_path('views/training/partials/lms_lesson_reperes_modal.php'); ?>
<?php require base_path('views/training/partials/lms_lesson_feedback_modal.php'); ?>
<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
