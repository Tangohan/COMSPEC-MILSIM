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
$lessonTypeLabel = function_exists('training_lesson_type_labels_fr')
    ? (training_lesson_type_labels_fr()[$lessonType] ?? $lessonType)
    : $lessonType;
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
$needsSwiper = in_array($lessonType, ['canvas', 'slideshow'], true);
if ($needsSwiper) {
    $lmsExtraHead .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">' . "\n";
    $lmsExtraHead .= '<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>' . "\n";
}
if (($lesson['lesson_type'] ?? '') === 'canvas') {
    $lmsExtraHead .= '<link href="' . htmlspecialchars($base) . '/assets/css/training_canvas.css" rel="stylesheet">' . "\n";
}
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
<body class="bg-slate-100 text-slate-900 min-h-screen">
    <div class="lms-grain"></div>
    <div class="relative z-10 lms-course-shell flex min-h-screen min-w-0 flex-col lg:flex-row">
        <?php
        $lmsBase = $base;
        $progressPercent = (float) ($progress['percent'] ?? 0);
        require base_path('views/training/partials/lms_course_sidebar.php');
        ?>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="topbar sticky top-0 z-50">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
                    <a href="<?= url('formations/' . ($enrollment['course_slug'] ?? '')) ?>" class="flex items-center gap-3 group">
                        <div class="brand-mark"><span>L</span></div>
                        <div>
                            <strong class="text-xs font-black uppercase tracking-wide text-slate-900 block">Leçon</strong>
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider">Retour au parcours</span>
                        </div>
                    </a>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-slate-500"><span data-lms-header-pct><?= (float)($progress['percent'] ?? 0) ?></span> %</span>
                        <div class="w-28 h-1.5 bg-slate-200 rounded-full overflow-hidden lms-progress-bar">
                            <span data-lms-header-progress style="width: <?= (float)($progress['percent'] ?? 0) ?>%"></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="section flex-1 min-w-0 overflow-x-hidden py-8 px-4 sm:px-8">
                <?php if ($lessonType === 'canvas' && $canvasDeck && !empty($canvasDeck['slides'])): ?>
                <div class="mx-auto max-w-6xl space-y-6">
                    <?php require base_path('views/training/partials/lms_canvas_mission_hero.php'); ?>
                    <?php if ($moduleLessonStep !== null && (int) $moduleLessonStep['total'] > 0): ?>
                    <?php
                    $mcur = (int) $moduleLessonStep['current'];
                    $mtot = (int) $moduleLessonStep['total'];
                    $mPct = $mtot > 0 ? (int) round(100 * $mcur / $mtot) : 0;
                    ?>
                    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-4">
                            <p class="lms-canvas-label">Progression dans le module</p>
                            <p class="text-sm font-bold text-slate-900">Leçon <?= $mcur ?> sur <?= $mtot ?></p>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" style="width: <?= $mPct ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-[1fr_minmax(260px,320px)]">
                        <div class="min-w-0 space-y-6">
                            <?php if ($lessonStep): ?>
                            <p class="text-sm font-medium text-slate-600">Étape <?= (int) $lessonStep['current'] ?> / <?= (int) $lessonStep['total'] ?> sur l’ensemble du parcours</p>
                            <?php endif; ?>
                            <section class="mb-10">
                                <p class="lms-lesson-content-eyebrow mb-3">Parcours interactif</p>
                                <article class="module-panel p-6 sm:p-8 lms-lesson-content-panel">
                <?php require base_path('views/training/partials/lms_lesson_actions_bar.php'); ?>
                <?php
                $deck = $canvasDeck;
                require base_path('views/training/partials/canvas_lesson_player.php');
                ?>
                <?php
                require base_path('views/training/partials/lms_lesson_common_footer.php');
                ?>
                                </article>
                            </section>
                        </div>
                        <aside class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-24 lg:self-start" aria-label="Repères de la leçon">
                            <h3 class="lms-sidebar-heading">Repères</h3>
                            <?php if ($moduleObjectives !== []): ?>
                            <div>
                                <p class="mb-2 lms-sidebar-sublabel">Objectifs du module</p>
                                <ul class="list-inside list-disc space-y-1.5 text-sm text-slate-700">
                                    <?php foreach ($moduleObjectives as $mo): ?>
                                    <li><?= htmlspecialchars($mo) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            <?php if ($lessonObjectives !== []): ?>
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                                <p class="mb-2 lms-sidebar-sublabel text-emerald-800">À l’issue de cette leçon</p>
                                <ul class="list-inside list-disc space-y-1 text-sm text-slate-800">
                                    <?php foreach ($lessonObjectives as $lo): ?>
                                    <li><?= htmlspecialchars($lo) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php elseif ($lessonSummary !== ''): ?>
                            <div>
                                <p class="mb-2 lms-sidebar-sublabel">Résumé</p>
                                <p class="text-sm leading-relaxed text-slate-700"><?= htmlspecialchars($lessonSummary) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <p class="mb-2 lms-sidebar-sublabel">Durée indicative</p>
                                <p class="text-sm font-bold text-slate-900"><?= !empty($lesson['duration_minutes']) ? (int) $lesson['duration_minutes'] . ' min' : '—' ?></p>
                                <?php if ($currentModule && (int) ($currentModule['estimated_minutes'] ?? 0) > 0): ?>
                                <p class="mt-2 text-xs text-slate-500">Module (estimation) : <?= (int) $currentModule['estimated_minutes'] ?> min</p>
                                <?php endif; ?>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <p class="mb-2 lms-sidebar-sublabel">État</p>
                                <p class="text-sm font-bold <?= $lessonAlreadyCompleted ? 'text-emerald-700' : 'text-amber-800' ?>"><?= $lessonAlreadyCompleted ? 'Terminée' : 'En cours' ?></p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <p class="mb-2 lms-sidebar-sublabel">Avancement du parcours</p>
                                <p class="lms-stat-number text-2xl font-semibold text-slate-900"><?= (int) round((float) ($progress['percent'] ?? 0)) ?> %</p>
                            </div>
                        </aside>
                    </div>
                </div>
                <?php else: ?>
                <div class="mx-auto max-w-4xl">
                    <?php if ($currentModule): ?>
                    <p class="lms-module-crumb mb-1"><?= htmlspecialchars((string) ($currentModule['title'] ?? '')) ?></p>
                    <?php if (!empty($currentModule['subtitle'])): ?>
                    <p class="mb-2 text-sm text-slate-600"><?= htmlspecialchars((string) $currentModule['subtitle']) ?></p>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="kicker">Leçon</div>
                    <?php endif; ?>
                    <?php if ($lessonStep): ?>
                    <p class="mb-2 text-sm font-medium text-slate-600">Étape <?= (int) $lessonStep['current'] ?> / <?= (int) $lessonStep['total'] ?></p>
                    <?php endif; ?>
                    <h1 class="page-title mb-2"><?= htmlspecialchars((string) $lesson['title']) ?></h1>
                    <?php if ($lessonSummary !== ''): ?>
                    <p class="mb-6 max-w-3xl text-base font-medium leading-relaxed text-slate-700"><?= htmlspecialchars($lessonSummary) ?></p>
                    <?php else: ?>
                    <p class="section-copy mb-8 text-sm text-slate-500">Progression liée à votre inscription au parcours.</p>
                    <?php endif; ?>

                    <?php if ($moduleObjectives !== []): ?>
                    <div class="mb-6 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <p class="mb-2 lms-sidebar-sublabel">Objectifs du module</p>
                        <ul class="list-inside list-disc space-y-1 text-sm text-slate-700">
                            <?php foreach ($moduleObjectives as $mo): ?>
                            <li><?= htmlspecialchars($mo) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ($lessonObjectives !== []): ?>
                    <div class="mb-8 rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4">
                        <p class="mb-2 lms-sidebar-sublabel text-emerald-900">À l’issue de cette leçon</p>
                        <ul class="list-inside list-disc space-y-1.5 text-sm text-slate-800">
                            <?php foreach ($lessonObjectives as $lo): ?>
                            <li><?= htmlspecialchars($lo) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="lms-lesson-meta-board mb-10">
                        <div class="lms-lesson-meta-cell">
                            <h2 class="lms-lesson-meta-cell__title">Fiche leçon</h2>
                            <ul class="lms-lesson-meta-list">
                                <li><span class="lms-lesson-meta-list__k">Type</span> <span class="lms-lesson-meta-list__v"><?= htmlspecialchars($lessonTypeLabel) ?></span></li>
                                <?php if (!empty($lesson['duration_minutes'])): ?>
                                <li><span class="lms-lesson-meta-list__k">Durée</span> <span class="lms-lesson-meta-list__v"><?= (int) $lesson['duration_minutes'] ?> min</span></li>
                                <?php endif; ?>
                                <?php if ($diffLabel !== ''): ?>
                                <li><span class="lms-lesson-meta-list__k">Niveau</span> <span class="lms-lesson-meta-list__v"><?= htmlspecialchars($diffLabel) ?></span></li>
                                <?php endif; ?>
                            </ul>
                            <?php if ($currentModule && (int) ($currentModule['estimated_minutes'] ?? 0) > 0): ?>
                            <p class="lms-lesson-meta-module-hint">Durée indicative du module : <?= (int) $currentModule['estimated_minutes'] ?> min</p>
                            <?php endif; ?>
                            <p class="lms-lesson-meta-progress-label">Avancement du parcours</p>
                            <div class="lms-lesson-progress-track">
                                <span class="lms-lesson-progress-fill" style="width:<?= min(100, (float) ($progress['percent'] ?? 0)) ?>%"></span>
                            </div>
                        </div>
                        <div class="lms-lesson-meta-cell lms-lesson-meta-cell--stat">
                            <h2 class="lms-lesson-meta-cell__title">Progression</h2>
                            <p class="lms-lesson-meta-stat"><?= htmlspecialchars((string) round((float) ($progress['percent'] ?? 0), 1)) ?><span class="lms-lesson-meta-stat__unit">%</span></p>
                            <p class="lms-lesson-meta-stat-caption">du parcours complété</p>
                        </div>
                        <div class="lms-lesson-meta-cell" id="parcours-sequence">
                            <h2 class="lms-lesson-meta-cell__title">Séquence</h2>
                            <p class="lms-lesson-meta-sequence-copy"><?= $autoLessonComplete
                                ? 'Chaque étape doit rester affichée un court instant, le bas du texte être visible assez longtemps en ayant fait défiler la page, ou les médias être lus sur presque toute leur durée — sans cela la leçon ne se valide pas automatiquement. Les quiz se valident sur une note suffisante.'
                                : 'Lisez le contenu, puis indiquez que la leçon est terminée lorsque c’est pertinent pour vous.' ?></p>
                        </div>
                    </div>

                    <section class="mb-10">
                        <p class="lms-lesson-content-eyebrow mb-3">Contenu à parcourir</p>

                        <article class="module-panel p-6 sm:p-8 lms-lesson-content-panel">
                <?php require base_path('views/training/partials/lms_lesson_actions_bar.php'); ?>
                <?php
                $embedSrc = ($lessonType === 'video_embed' && !empty($lesson['external_url']) && function_exists('training_video_embed_iframe_src'))
                    ? training_video_embed_iframe_src((string) $lesson['external_url'])
                    : null;
                ?>
                <?php if ($lessonType === 'quiz' && $quizData): ?>
                <?php $quiz = $quizData;
                require base_path('views/training/partials/lesson_quiz_player.php'); ?>
                <?php elseif ($lessonType === 'modals' && $modalsDeck): ?>
                <?php $deck = $modalsDeck;
                require base_path('views/training/partials/lesson_modals_player.php'); ?>
                <?php elseif ($lessonType === 'slideshow' && $showDeck): ?>
                <?php $deck = $showDeck;
                require base_path('views/training/partials/lesson_slideshow_player.php'); ?>
                <?php elseif ($lessonType === 'richtext' && !empty($lesson['content'])): ?>
                <div class="prose prose-slate max-w-none" <?= $autoLessonComplete ? 'data-lms-richtext-root="1"' : '' ?>>
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
                <p class="text-rose-600 text-sm">URL non reconnue pour l’intégration. <?php if ($videoEmbedLeave !== null): ?><a href="<?= htmlspecialchars($videoEmbedLeave, ENT_QUOTES, 'UTF-8') ?>" class="underline font-bold" target="_blank" rel="noopener noreferrer">Ouvrir le lien</a><?php else: ?>Le lien fourni n’est pas utilisable depuis cette page.<?php endif; ?></p>
                <?php elseif ($lessonType === 'audio' && !empty($lesson['external_url'])): ?>
                <div class="rounded-xl overflow-hidden bg-slate-100 p-4">
                    <audio id="lms-lesson-audio" controls crossorigin src="<?= htmlspecialchars((string) $lesson['external_url']) ?>" class="w-full"></audio>
                </div>
                <?php elseif ($lessonType === 'pdf' && $pdfUrl !== ''): ?>
                <div class="space-y-3">
                    <div id="lms-pdf-toolbar" class="flex flex-wrap gap-2 text-xs"></div>
                    <div id="lms-pdf-viewer" class="rounded-xl border border-slate-200 bg-slate-50 min-h-[480px] overflow-auto"></div>
                    <p class="text-xs text-slate-500"><a href="<?= htmlspecialchars($pdfUrl) ?>" class="text-emerald-600 font-semibold hover:underline" target="_blank" rel="noopener">Ouvrir / télécharger le PDF</a></p>
                </div>
                <?php elseif ($lessonType === 'external_link' && !empty($lesson['external_url'])): ?>
                <?php $externalLessonHref = training_lms_resource_external_href((string) $lesson['external_url']); ?>
                <?php if ($externalLessonHref !== null): ?>
                <p class="mb-4">Lien externe :</p>
                <a href="<?= htmlspecialchars($externalLessonHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="text-emerald-600 font-bold hover:underline"><?= htmlspecialchars((string) $lesson['external_url']) ?></a>
                <?php else: ?>
                <p class="text-rose-600 text-sm">Le lien indiqué pour cette leçon n’est pas utilisable.</p>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-slate-500">Contenu à afficher (type : <?= htmlspecialchars($lessonType) ?>).</p>
                <?php endif; ?>

                <?php require base_path('views/training/partials/lms_lesson_common_footer.php'); ?>
                        </article>
                    </section>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

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
    'auto' => $autoLessonComplete,
    'lessonType' => $lessonType,
    'strict' => [
        'slideDwellMs' => 2600,
        'richtextSentinelMs' => 2800,
        'richtextScrollRatio' => 0.86,
        'mediaPlayedMinRatio' => 0.88,
        'modalMinOpenMs' => 2600,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/lms_training_toast.js"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/training_lesson_progress.js" defer></script>
<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
