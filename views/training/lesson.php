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
$lessonStep = $lessonStep ?? null;
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
    <div class="min-h-screen relative z-10 grid lg:grid-cols-[300px_1fr]">
        <?php
        $lmsBase = $base;
        $progressPercent = (float) ($progress['percent'] ?? 0);
        require base_path('views/training/partials/lms_course_sidebar.php');
        ?>

        <div class="flex flex-col min-w-0">
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

            <main class="section flex-1 py-8 px-4 sm:px-8">
                <div class="max-w-4xl mx-auto">
                    <?php if ($currentModule): ?>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1"><?= htmlspecialchars((string) ($currentModule['title'] ?? '')) ?></p>
                    <?php if (!empty($currentModule['subtitle'])): ?>
                    <p class="text-sm text-slate-600 mb-2"><?= htmlspecialchars((string) $currentModule['subtitle']) ?></p>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="kicker">Leçon</div>
                    <?php endif; ?>
                    <?php if ($lessonStep): ?>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Étape <?= (int) $lessonStep['current'] ?> / <?= (int) $lessonStep['total'] ?></p>
                    <?php endif; ?>
                    <h1 class="page-title mb-2"><?= htmlspecialchars((string) $lesson['title']) ?></h1>
                    <?php if ($lessonSummary !== ''): ?>
                    <p class="text-base text-slate-700 font-medium mb-6 max-w-3xl leading-relaxed"><?= htmlspecialchars($lessonSummary) ?></p>
                    <?php else: ?>
                    <p class="section-copy text-sm mb-8 text-slate-500">Progression liée à votre inscription au parcours.</p>
                    <?php endif; ?>

                    <?php if ($moduleObjectives !== []): ?>
                    <div class="mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 mb-2">Objectifs du module</p>
                        <ul class="text-sm text-slate-700 space-y-1 list-disc list-inside">
                            <?php foreach ($moduleObjectives as $mo): ?>
                            <li><?= htmlspecialchars($mo) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ($lessonObjectives !== []): ?>
                    <div class="mb-8 p-4 rounded-2xl border border-emerald-100 bg-emerald-50/40">
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-900 mb-2">À l’issue de cette leçon</p>
                        <ul class="text-sm text-slate-800 space-y-1.5 list-disc list-inside">
                            <?php foreach ($lessonObjectives as $lo): ?>
                            <li><?= htmlspecialchars($lo) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="grid-3 mb-10">
                        <article class="module-panel">
                            <h2 class="module-header">Fiche leçon</h2>
                            <p class="text-sm text-slate-600">
                                Type : <strong><?= htmlspecialchars($lessonTypeLabel) ?></strong>
                                <?php if (!empty($lesson['duration_minutes'])): ?><br>Durée : <?= (int) $lesson['duration_minutes'] ?> min<?php endif; ?>
                                <?php if ($diffLabel !== ''): ?><br>Niveau : <strong><?= htmlspecialchars($diffLabel) ?></strong><?php endif; ?>
                            </p>
                            <?php if ($currentModule && (int) ($currentModule['estimated_minutes'] ?? 0) > 0): ?>
                            <p class="text-xs text-slate-500 mt-2">Durée indicative du module : <?= (int) $currentModule['estimated_minutes'] ?> min</p>
                            <?php endif; ?>
                            <div class="progress mt-3"><span style="width:<?= min(100, (float)($progress['percent'] ?? 0)) ?>%"></span></div>
                        </article>
                        <article class="module-panel">
                            <h2 class="module-header">Progression</h2>
                            <p class="text-sm text-slate-600">Parcours : <?= (float)($progress['percent'] ?? 0) ?> %</p>
                        </article>
                        <article class="module-panel" id="parcours-sequence">
                            <h2 class="module-header">Séquence</h2>
                            <p class="text-xs text-slate-600 leading-relaxed"><?= $autoLessonComplete
                                ? 'Parcourez chaque étape du bloc pédagogique : la leçon se valide dès que le parcours est complété (ou le quiz réussi, selon le type de contenu).'
                                : 'Suivez le contenu puis indiquez manuellement que la leçon est terminée lorsque c’est pertinent.' ?></p>
                        </article>
                    </div>

                    <section class="mb-10">
                        <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
                            <div>
                                <div class="kicker">Contenu</div>
                                <h2 class="text-xl font-black uppercase tracking-tight text-slate-900">Bloc pédagogique</h2>
                            </div>
                        </div>

                        <article class="module-panel p-6 sm:p-8">
                <?php
                $embedSrc = ($lessonType === 'video_embed' && !empty($lesson['external_url']) && function_exists('training_video_embed_iframe_src'))
                    ? training_video_embed_iframe_src((string) $lesson['external_url'])
                    : null;
                ?>
                <?php if ($lessonType === 'canvas'): ?>
                <?php
                $deck = $canvasDeck;
                require base_path('views/training/partials/canvas_lesson_player.php');
                ?>
                <?php elseif ($lessonType === 'quiz' && $quizData): ?>
                <?php $quiz = $quizData;
                require base_path('views/training/partials/lesson_quiz_player.php'); ?>
                <?php elseif ($lessonType === 'modals' && $modalsDeck): ?>
                <?php $deck = $modalsDeck;
                require base_path('views/training/partials/lesson_modals_player.php'); ?>
                <?php elseif ($lessonType === 'slideshow' && $showDeck): ?>
                <?php $deck = $showDeck;
                require base_path('views/training/partials/lesson_slideshow_player.php'); ?>
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
                <p class="text-rose-600 text-sm">URL non reconnue pour l’intégration. <a href="<?= htmlspecialchars((string) $lesson['external_url']) ?>" class="underline font-bold" target="_blank" rel="noopener">Ouvrir le lien</a></p>
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
                <p class="mb-4">Lien externe :</p>
                <a href="<?= htmlspecialchars((string) $lesson['external_url']) ?>" target="_blank" rel="noopener" class="text-emerald-600 font-bold hover:underline"><?= htmlspecialchars((string) $lesson['external_url']) ?></a>
                <?php else: ?>
                <p class="text-slate-500">Contenu à afficher (type : <?= htmlspecialchars($lessonType) ?>).</p>
                <?php endif; ?>

                <?php if (!empty($resources)): ?>
                <div class="mt-10 pt-8 border-t border-slate-200">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 mb-3">Ressources</h3>
                    <ul class="space-y-2">
                        <?php foreach ($resources as $r): ?>
                        <li>
                            <?php if (!empty($r['file_path'])): ?>
                            <a href="<?= url('api/training/resource/' . (int)$r['id'] . '/download') ?>" class="text-emerald-600 hover:underline font-medium"><?= htmlspecialchars((string) $r['title']) ?></a>
                            <?php elseif (!empty($r['external_url'])): ?>
                            <a href="<?= htmlspecialchars((string) $r['external_url']) ?>" target="_blank" rel="noopener" class="text-emerald-600 hover:underline font-medium"><?= htmlspecialchars((string) $r['title']) ?></a>
                            <?php else: ?>
                            <span class="text-slate-600"><?= htmlspecialchars((string) $r['title']) ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                        <div class="mt-10 flex flex-col gap-4">
                            <p id="lms-progress-status" class="text-sm <?= $lessonAlreadyCompleted ? 'text-emerald-700 font-semibold' : 'text-slate-600' ?>" role="status">
                                <?php if ($lessonAlreadyCompleted): ?>
                                Cette leçon est déjà validée.
                                <?php elseif ($autoLessonComplete): ?>
                                Parcourez toutes les étapes du contenu ci-dessus : la leçon sera enregistrée automatiquement une fois le parcours complété.
                                <?php else: ?>
                                Lorsque vous avez terminé cette leçon, enregistrez votre progression avec le bouton ci-dessous.
                                <?php endif; ?>
                            </p>
                            <div class="flex flex-wrap gap-4">
                            <?php if (!$lessonAlreadyCompleted && !$autoLessonComplete): ?>
                            <form method="post" action="<?= url('api/training/progress/lesson') ?>" class="inline" data-progress-lesson>
                                <?= $csrf ?>
                                <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
                                <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" id="lms-btn-complete" class="px-6 py-3 bg-emerald-600 text-white text-sm font-bold uppercase rounded-xl hover:bg-emerald-700">Enregistrer la leçon comme terminée</button>
                            </form>
                            <?php elseif (!$lessonAlreadyCompleted && $autoLessonComplete): ?>
                            <button type="button" id="lms-btn-complete" class="px-6 py-3 bg-slate-200 text-slate-500 text-sm font-bold uppercase rounded-xl cursor-default opacity-80" disabled>Validation automatique</button>
                            <?php else: ?>
                            <span id="lms-btn-complete" class="sr-only">Leçon validée</span>
                            <?php endif; ?>
                            <a href="<?= url('formations/' . ($enrollment['course_slug'] ?? '')) ?>" class="px-6 py-3 border border-slate-300 text-slate-700 text-sm font-bold uppercase rounded-xl hover:bg-slate-100">Retour à la formation</a>
                            </div>
                        </div>
                        <?php
                        $enrId = (int) $enrollment['id'];
                        $prevUrl = $prevLesson ? url('formations/lesson/' . (int) $prevLesson['id'] . '?enrollment_id=' . $enrId) : '';
                        $nextUrl = $nextLesson ? url('formations/lesson/' . (int) $nextLesson['id'] . '?enrollment_id=' . $enrId) : '';
                        $courseSlugNav = trim((string) ($course['slug'] ?? $enrollment['course_slug'] ?? ''));
                        $echangesUrl = $courseSlugNav !== ''
                            ? url('formations/' . rawurlencode($courseSlugNav) . '/echanges')
                            : '';
                        $showFinParcours = $echangesUrl !== '' && $nextLesson === null;
                        ?>
                        <?php if ($prevUrl !== '' || $nextUrl !== '' || $showFinParcours): ?>
                        <nav class="mt-8 pt-6 border-t border-slate-200 flex flex-wrap items-stretch justify-between gap-6" aria-label="Navigation du parcours">
                            <div class="min-w-0 max-w-[48%]">
                                <?php if ($prevUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($prevUrl) ?>" title="<?= htmlspecialchars((string) ($prevLesson['title'] ?? '')) ?>" class="inline-flex flex-col gap-1 group">
                                    <span class="text-sm font-black uppercase tracking-wide text-slate-700 group-hover:text-emerald-800">← Précédent</span>
                                    <span class="text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars((string) ($prevLesson['title'] ?? '')) ?></span>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0 max-w-[48%] text-right ml-auto">
                                <?php if ($nextUrl !== ''): ?>
                                <a href="<?= htmlspecialchars($nextUrl) ?>" title="<?= htmlspecialchars((string) ($nextLesson['title'] ?? '')) ?>" class="inline-flex flex-col items-end gap-1 group">
                                    <span class="text-sm font-black uppercase tracking-wide text-white bg-emerald-600 group-hover:bg-emerald-700 px-5 py-2.5 rounded-xl">Suivant →</span>
                                    <span class="text-xs text-slate-500 line-clamp-2 text-right"><?= htmlspecialchars((string) ($nextLesson['title'] ?? '')) ?></span>
                                </a>
                                <?php elseif ($showFinParcours): ?>
                                <a href="<?= htmlspecialchars($echangesUrl) ?>" class="inline-flex flex-col items-end gap-1 group">
                                    <span class="text-sm font-black uppercase tracking-wide text-white bg-slate-900 group-hover:bg-slate-800 px-5 py-2.5 rounded-xl">Fin du parcours — Avis &amp; échanges →</span>
                                    <span class="text-xs text-slate-500 line-clamp-2 text-right">Note, questions et commentaires sur une page dédiée</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </nav>
                        <?php endif; ?>
                        </article>
                    </section>
                </div>
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
    'enrollmentId' => (int) $enrollment['id'],
    'lessonId' => (int) $lesson['id'],
    'alreadyCompleted' => $lessonAlreadyCompleted,
    'auto' => $autoLessonComplete,
    'lessonType' => $lessonType,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/training_lesson_progress.js" defer></script>
<?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
