<?php
$base = url('');
$course = $course ?? null;
$enrollment = $enrollment ?? null;
$progressPercent = $progressPercent ?? 0;
$certificate = $certificate ?? null;
if (!$course) {
    echo '<p>Formation non trouvée.</p>';
    return;
}
$courseId = (int) $course['id'];
$slugForForms = (string) ($course['slug'] ?? '');
$policyEval = $policyEval ?? ['allowed' => true, 'messages' => []];
$policyDisplay = $policyDisplay ?? ['prerequisite_courses' => [], 'certificate_courses' => [], 'policy_flags' => []];
$preCourses = $policyDisplay['prerequisite_courses'] ?? [];
$certCourses = $policyDisplay['certificate_courses'] ?? [];
$policyFlags = $policyDisplay['policy_flags'] ?? [];
$hasPolicyInfo = $preCourses !== [] || $certCourses !== [] || $policyFlags !== [];
$isFavorite = $isFavorite ?? false;
$isLiked = $isLiked ?? false;
$analyticsBeacon = $analyticsBeacon ?? null;
$courseSessions = $courseSessions ?? [];
$viewerLoggedIn = $viewerLoggedIn ?? false;
$continueLesson = $continueLesson ?? null;
$firstLesson = $firstLesson ?? null;
$lessonDone = $lessonDone ?? [];
$hasCompletedAnyLesson = $lessonDone !== [];
$canAccessLearning = $canAccessLearning ?? false;
$canWithdrawEnrollment = $canWithdrawEnrollment ?? false;
$lmsCommentsEnabled = $lmsCommentsEnabled ?? true;
$canPublishOperationalBoard = !empty($canPublishOperationalBoard);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$lmsShowCompletionBanner = !empty($lmsShowCompletionBanner);
$lmsCourseCertifying = (int) ($course['is_certifying'] ?? 0) === 1;
$modules = $course['modules'] ?? [];
$theme = function_exists('training_lms_parse_theme') ? training_lms_parse_theme((string) ($course['theme_json'] ?? '')) : [];
$objectives = function_exists('training_lms_learning_objectives') ? training_lms_learning_objectives($course) : [];
$code = (string) ($course['course_code'] ?? '');
if ($code === '') {
    $code = 'F-' . $courseId;
}
$lmsTitle = (string) $course['title'];
$lmsBase = $base;
$lmsThemeVars = function_exists('training_lms_theme_css_vars') ? training_lms_theme_css_vars($theme) : '';
$courseMetaChips = [];
$formatCourseMetaLabel = static function (string $raw): string {
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    $normalized = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $dictionary = [
        'debutant' => 'Débutant',
        'débutant' => 'Débutant',
        'intermediaire' => 'Intermédiaire',
        'intermédiaire' => 'Intermédiaire',
        'avance' => 'Avancé',
        'avancé' => 'Avancé',
        'expert' => 'Expert',
    ];

    if (isset($dictionary[$normalized])) {
        return $dictionary[$normalized];
    }

    return ucfirst($value);
};
$estMin = (int) ($course['estimated_minutes'] ?? 0);
if ($estMin > 0) {
    $courseMetaChips[] = ['text' => $estMin . ' min', 'hint' => 'Durée estimée'];
}
$catTrim = trim((string) ($course['category'] ?? ''));
if ($catTrim !== '') {
    $courseMetaChips[] = ['text' => $formatCourseMetaLabel($catTrim), 'hint' => 'Thème'];
}
$lvlTrim = trim((string) ($course['level'] ?? ''));
if ($lvlTrim !== '') {
    $courseMetaChips[] = ['text' => $formatCourseMetaLabel($lvlTrim), 'hint' => 'Niveau'];
}
$courseHeaderAsideVisible = $viewerLoggedIn || $canPublishOperationalBoard || ($enrollment && $canAccessLearning);
$lmsExtraHead = '';
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();

$bp = trim((string) ($course['banner_path'] ?? ''));
$tp = trim((string) ($course['thumbnail_path'] ?? ''));
$bannerPick = $bp !== '' ? $bp : ($tp !== '' ? $tp : null);
$bannerSrc = training_media_url($bannerPick);

$lmsOpeningCourseId = $courseId;
$lmsOpeningTitle = (string) $course['title'];
$lmsOpeningBannerSrc = $bannerSrc;
$lmsOpeningLoaderImageSrc = training_media_url((string) ($theme['openingLoaderImage'] ?? ''));
$lmsOpeningLoaderTitle = trim((string) ($theme['openingLoaderTitle'] ?? ''));
$lmsOpeningLoaderBody = trim((string) ($theme['openingLoaderBody'] ?? ''));
$lmsOpeningCtaMode = 'scroll_inscription';
$lmsOpeningLessonUrl = '';
if ($enrollment && $canAccessLearning && $firstLesson) {
    $lmsOpeningCtaMode = 'lesson';
    if ($continueLesson) {
        $lmsOpeningLessonUrl = url('formations/lesson/' . (int) $continueLesson['id'] . '?enrollment_id=' . (int) $enrollment['id']);
    } else {
        $lmsOpeningLessonUrl = url('formations/lesson/' . (int) $firstLesson['id'] . '?enrollment_id=' . (int) $enrollment['id']);
    }
}
$lmsOpeningCtaLabel = ($enrollment && $canAccessLearning)
    ? ($hasCompletedAnyLesson ? 'Continuer' : 'Continuer vers le module')
    : 'Commencer';
$lmsCompletedLessonIds = [];
foreach ($lessonDone as $doneId => $_) {
    $lmsCompletedLessonIds[(int) $doneId] = true;
}
$lmsSequenceContext = 'preamble';
$lmsPassedQuizIds = is_array($lmsPassedQuizIds ?? null) ? $lmsPassedQuizIds : [];
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 overflow-x-hidden">
    <?php require base_path('views/training/partials/lms_course_opening_sequence.php'); ?>
    <div class="lms-grain"></div>
    <div class="min-h-screen relative z-10">
        <div class="lms-course-shell flex min-h-screen min-w-0 flex-col lg:flex-row">
            <?php
            $lmsBase = $base;
            $currentLessonId = null;
            require base_path('views/training/partials/lms_course_sidebar.php');
            ?>

            <main class="min-w-0 flex-1 p-5 md:p-8 lg:p-10 space-y-8">
                <?php if ($flashOk): ?>
                <div class="lms-panel rounded-2xl p-4 bg-emerald-50 border border-emerald-200 text-emerald-950 text-sm font-medium"><?= htmlspecialchars((string) $flashOk) ?></div>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                <div class="lms-panel rounded-2xl p-4 bg-rose-50 border border-rose-200 text-rose-950 text-sm font-medium"><?= htmlspecialchars((string) $flashErr) ?></div>
                <?php endif; ?>
                <?php if ($lmsShowCompletionBanner): ?>
                <section class="lms-panel relative overflow-hidden rounded-[2rem] border border-emerald-200/80 bg-gradient-to-br from-emerald-50 via-white to-teal-50/90 p-6 md:p-8 shadow-sm" role="status" aria-live="polite">
                    <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-emerald-400/15 blur-2xl" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -bottom-12 -left-8 h-40 w-40 rounded-full bg-teal-400/10 blur-2xl" aria-hidden="true"></div>
                    <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                        <div class="flex gap-4 min-w-0">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/25" aria-hidden="true">
                                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700/90">Parcours réussi</p>
                                <h2 class="mt-1 text-xl md:text-2xl font-black tracking-tight text-slate-900">Bravo — tout est validé</h2>
                                <?php if ($lmsCourseCertifying): ?>
                                <p class="mt-2 text-sm text-slate-600 max-w-xl">Vous avez terminé l’ensemble du parcours. Vous pouvez consulter votre attestation<?php if ($certificate): ?> et enregistrer le document officiel<?php endif; ?> depuis la page dédiée.</p>
                                <?php else: ?>
                                <p class="mt-2 text-sm text-slate-600 max-w-xl">Vous avez terminé l’ensemble du parcours. Merci pour votre engagement.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-3 shrink-0 md:justify-end">
                            <?php if ($lmsCourseCertifying && $certificate): ?>
                            <a href="<?= url('formations/certificate/' . (int) $certificate['id']) ?>" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-6 py-3.5 text-xs font-black uppercase tracking-wider text-white shadow-md shadow-emerald-600/25 transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                Voir l’attestation et le certificat
                            </a>
                            <?php elseif ($lmsCourseCertifying): ?>
                            <p class="text-sm font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 max-w-md">Si l’attestation n’apparaît pas tout de suite, actualisez la page dans un instant.</p>
                            <?php endif; ?>
                            <a href="<?= url('formations/mes-formations') ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white/80 px-6 py-3.5 text-xs font-black uppercase tracking-wider text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">Mes formations</a>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
                <div class="lms-course-hero">
                    <img src="<?= htmlspecialchars($bannerSrc) ?>" alt="" class="lms-course-hero__media" loading="eager" decoding="async" fetchpriority="high">
                    <div class="lms-course-hero__veil" aria-hidden="true"></div>
                </div>

                <header class="lms-panel relative overflow-hidden rounded-[2rem] p-6 md:p-8">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-emerald-500/75 via-emerald-500/20 to-transparent" aria-hidden="true"></div>
                    <div class="w-full min-w-0 <?= $courseHeaderAsideVisible ? 'grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start lg:gap-x-10 lg:gap-y-8' : 'flex flex-col gap-8' ?>">
                        <div class="min-w-0 <?= $courseHeaderAsideVisible ? '' : 'flex-1' ?>">
                            <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <p class="text-[9px] font-black uppercase tracking-[0.35em] text-slate-400">Fiche formation</p>
                                <span class="inline-flex items-center rounded-lg border border-emerald-200/80 bg-emerald-50/90 px-2.5 py-0.5 font-mono text-[11px] font-bold tracking-tight text-emerald-800" title="Référence du parcours"><?= htmlspecialchars($code) ?></span>
                            </div>
                            <h1 id="lms-course-page-title" class="text-balance break-words text-2xl font-black leading-[1.15] tracking-tight text-slate-900 md:text-4xl"><?= htmlspecialchars((string) $course['title']) ?></h1>
                            <?php if ($courseMetaChips !== []): ?>
                            <ul class="mt-4 flex flex-wrap gap-2" aria-label="Caractéristiques du parcours">
                                <?php foreach ($courseMetaChips as $chip): ?>
                                <li>
                                    <span class="inline-flex items-center rounded-full border border-slate-200/90 bg-white/90 px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm" title="<?= htmlspecialchars((string) $chip['hint'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $chip['text']) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                            <?php if (!empty($course['short_description'])): ?>
                            <p class="mt-4 max-w-3xl text-pretty text-sm leading-relaxed text-slate-600 md:text-base"><?= htmlspecialchars((string) $course['short_description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($courseHeaderAsideVisible): ?>
                        <aside class="flex w-full min-w-0 max-w-full flex-col gap-4 sm:max-lg:flex-row sm:max-lg:flex-wrap lg:max-w-[20rem] lg:justify-self-stretch" aria-label="Actions et suivi">
                            <?php if ($viewerLoggedIn): ?>
                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-3 shadow-sm sm:max-lg:flex-1 sm:max-lg:min-w-[12rem]">
                                <p class="mb-2 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Réactions</p>
                                <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                                    <form method="post" action="<?= url('formations/favorite') ?>" class="min-w-0 flex-1">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                        <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                                        <input type="hidden" name="favorite" value="<?= $isFavorite ? '0' : '1' ?>">
                                        <button type="submit" class="flex w-full min-h-[2.5rem] items-center justify-center rounded-xl border px-3 py-2 text-center text-xs font-black uppercase tracking-wider transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 <?= $isFavorite ? 'border-amber-400 bg-amber-50 text-amber-900 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50' ?>"><?= $isFavorite ? '★ Favori' : '☆ Favori' ?></button>
                                    </form>
                                    <form method="post" action="<?= url('formations/like') ?>" class="min-w-0 flex-1">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                        <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                                        <input type="hidden" name="like" value="<?= $isLiked ? '0' : '1' ?>">
                                        <button type="submit" class="flex w-full min-h-[2.5rem] items-center justify-center rounded-xl border px-3 py-2 text-center text-xs font-black uppercase tracking-wider transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-2 <?= $isLiked ? 'border-rose-400 bg-rose-50 text-rose-900 hover:bg-rose-100' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50' ?>"><?= $isLiked ? '♥ J’aime' : '♡ J’aime' ?></button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($canPublishOperationalBoard): ?>
                            <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/40 p-3 sm:max-lg:flex-1 sm:max-lg:min-w-[14rem]">
                                <?php
                                $opBoardPublishSourceType = 'formation';
                                $opBoardPublishSourceId = $courseId;
                                $opBoardPublishVariant = 'course';
                                require base_path('views/partials/operational_board_publish_linked_form.php');
                                ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($enrollment && $canAccessLearning): ?>
                            <div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white to-slate-50/90 px-4 py-4 text-center shadow-sm sm:max-lg:flex-1 sm:max-lg:min-w-[10rem] sm:text-left lg:text-right">
                                <p class="font-mono text-3xl font-black tabular-nums leading-none tracking-tight text-slate-900"><?= (int) $progressPercent ?><span class="text-lg font-black text-slate-500">%</span></p>
                                <p class="mt-1.5 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Progression</p>
                            </div>
                            <?php if ($certificate): ?>
                            <a href="<?= url('formations/certificate/' . (int) $certificate['id']) ?>" class="inline-flex min-h-[2.75rem] w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">Attestation</a>
                            <?php elseif (($enrollment['status'] ?? '') === 'completed'): ?>
                            <p class="rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-center text-xs font-bold text-emerald-800 sm:text-left lg:text-right">Formation validée</p>
                            <?php endif; ?>
                            <?php endif; ?>
                        </aside>
                        <?php endif; ?>
                    </div>
                    <?php if ($enrollment && $canAccessLearning && (int) $progressPercent < 100): ?>
                    <div class="mt-8 border-t border-slate-200/80 pt-6">
                        <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Avancement du parcours</span>
                            <span class="text-xs font-black tabular-nums text-slate-800"><?= (int) $progressPercent ?> %</span>
                        </div>
                        <div class="lms-progress-bar h-2.5 overflow-hidden rounded-full bg-slate-200/90" role="progressbar" aria-valuenow="<?= (int) $progressPercent ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progression du parcours">
                            <span style="width: <?= (int) $progressPercent ?>%"></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </header>

                <?php if ($enrollment && $canAccessLearning && $firstLesson): ?>
                <section id="lms-parcours-debut" class="lms-panel scroll-mt-24 rounded-[1.5rem] p-5 md:p-6 border border-emerald-200/80 bg-gradient-to-br from-white to-emerald-50/40">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center sm:justify-between gap-4">
                        <div>
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-emerald-700/80 mb-2">Préambule · suite</p>
                            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Enchaînement du parcours</h2>
                            <p class="text-sm text-slate-600 mt-1 max-w-xl">Le préambule pose le cadre. Ensuite viennent les leçons du module, puis l’évaluation du module, puis le module suivant — une étape après l’autre.</p>
                        </div>
                        <div class="flex flex-col sm:items-end gap-2 shrink-0">
                            <?php if ($continueLesson): ?>
                            <?php
                            $ctaPrimary = $hasCompletedAnyLesson ? 'Continuer la formation' : 'Continuer vers le module';
                            $nextHuman = 'Leçon — ' . (string) ($continueLesson['title'] ?? '');
                            // Si la prochaine étape logique est un quiz après la leçon courante… on reste sur continueLesson pour démarrer.
                            ?>
                            <a href="<?= url('formations/lesson/' . (int) $continueLesson['id'] . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="inline-flex items-center justify-center px-8 py-3.5 bg-emerald-600 text-white text-xs font-black uppercase tracking-wider rounded-xl hover:bg-emerald-700 shadow-sm text-center"><?= htmlspecialchars($ctaPrimary) ?></a>
                            <p class="text-[11px] text-slate-500 text-center sm:text-right">Prochaine étape : <strong class="text-slate-800"><?= htmlspecialchars($nextHuman) ?></strong></p>
                            <?php else: ?>
                            <p class="text-sm font-bold text-emerald-800">Toutes les leçons du parcours sont terminées.</p>
                            <a href="<?= url('formations/lesson/' . (int) $firstLesson['id'] . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="inline-flex items-center justify-center px-6 py-2.5 border border-emerald-300 text-emerald-900 text-xs font-bold uppercase rounded-xl hover:bg-emerald-50">Revoir depuis le début</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <section class="lms-panel rounded-[2rem] p-6 md:p-10 border border-slate-200/90">
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-6">Prérequis &amp; inscription</p>
                    <div class="grid lg:grid-cols-2 gap-8 lg:gap-12">
                        <div class="space-y-5 min-w-0">
                            <h2 class="text-sm font-black uppercase tracking-wide text-slate-900">Prérequis</h2>
                            <?php if (!$hasPolicyInfo): ?>
                            <p class="text-sm text-slate-500 leading-relaxed">Aucun prérequis de parcours ni condition supplémentaire n’est renseigné pour cette formation.</p>
                            <?php else: ?>
                            <?php if ($preCourses !== []): ?>
                            <div>
                                <p class="text-xs font-bold text-slate-600 mb-2">Formations à avoir validées avant</p>
                                <ul class="space-y-2">
                                    <?php foreach ($preCourses as $pc): ?>
                                    <li class="flex flex-wrap items-center gap-2 text-sm text-slate-800">
                                        <?php if ($pc['completed'] === true): ?>
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-black" title="Validé">✓</span>
                                        <?php elseif ($pc['completed'] === false): ?>
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-amber-800 text-xs font-black" title="À valider">!</span>
                                        <?php else: ?>
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-slate-500 text-xs">?</span>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars((string) ($pc['title'] ?? '')) ?></span>
                                        <?php if (!empty($pc['slug'])): ?>
                                        <a href="<?= url('formations/' . rawurlencode((string) $pc['slug'])) ?>" class="text-xs font-semibold text-emerald-700 hover:underline">Voir la formation</a>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (!$viewerLoggedIn): ?>
                                <p class="text-xs text-slate-500 mt-2">Connectez-vous pour voir si vos validations sont prises en compte.</p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($certCourses !== []): ?>
                            <div>
                                <p class="text-xs font-bold text-slate-600 mb-2">Attestation ou validation attendue pour</p>
                                <ul class="space-y-2">
                                    <?php foreach ($certCourses as $cc): ?>
                                    <li class="flex flex-wrap items-center gap-2 text-sm text-slate-800">
                                        <?php if ($cc['completed'] === true): ?>
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-black">✓</span>
                                        <?php elseif ($cc['completed'] === false): ?>
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-amber-800 text-xs font-black">!</span>
                                        <?php else: ?>
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-slate-500 text-xs">?</span>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars((string) ($cc['title'] ?? '')) ?></span>
                                        <?php if (!empty($cc['slug'])): ?>
                                        <a href="<?= url('formations/' . rawurlencode((string) $cc['slug'])) ?>" class="text-xs font-semibold text-emerald-700 hover:underline">Voir la formation</a>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            <?php foreach ($policyFlags as $pf): ?>
                            <p class="text-sm text-slate-700 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2"><?= htmlspecialchars($pf) ?></p>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div id="lms-inscription" class="rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 to-white p-6 min-w-0 scroll-mt-24">
                            <h2 class="text-sm font-black uppercase tracking-wide text-emerald-950 mb-3">Inscription au parcours</h2>
                            <?php if ($enrollment): ?>
                            <?php if (($enrollment['status'] ?? '') === 'pending_approval'): ?>
                            <p class="text-sm text-slate-700 leading-relaxed">Votre demande d’inscription a bien été enregistrée. Un formateur doit la valider avant que vous puissiez ouvrir les leçons.</p>
                            <p class="text-xs text-amber-800 mt-3 font-bold bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">En attente de validation</p>
                            <?php else: ?>
                            <p class="text-sm text-slate-700 leading-relaxed">Vous suivez cette formation. Utilisez les modules ci-dessous pour poursuivre le parcours.</p>
                            <?php
                            $st = (string) ($enrollment['status'] ?? '');
                            $stLab = match ($st) {
                                'assigned' => 'Assigné',
                                'in_progress' => 'En cours',
                                'completed' => 'Terminé',
                                'failed' => 'Non validé',
                                'expired' => 'Expiré',
                                'revoked' => 'Révoqué',
                                default => $st,
                            };
                            ?>
                            <p class="text-xs text-slate-500 mt-3">Statut : <strong class="text-slate-800"><?= htmlspecialchars($stLab) ?></strong></p>
                            <?php endif; ?>
                            <?php elseif (!$viewerLoggedIn): ?>
                            <p class="text-sm text-slate-700 mb-4">Connectez-vous pour vous inscrire et accéder au contenu.</p>
                            <a href="<?= url('login') ?>" class="inline-flex items-center justify-center w-full sm:w-auto px-6 py-3 bg-slate-900 text-white text-xs font-black uppercase rounded-xl hover:bg-slate-800">Se connecter</a>
                            <?php elseif (!empty($policyEval['allowed'])): ?>
                            <form method="post" action="<?= url('formations/enroll') ?>" class="space-y-4">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                                <label class="block">
                                    <span class="text-xs font-bold text-slate-600">Message de motivation <span class="font-normal text-slate-400">(optionnel)</span></span>
                                    <textarea name="enrollment_motivation" rows="4" maxlength="4000" class="mt-1.5 w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" placeholder="Pourquoi souhaitez-vous suivre cette formation ?"></textarea>
                                </label>
                                <button type="submit" class="w-full px-6 py-3.5 bg-emerald-600 text-white text-xs font-black uppercase tracking-wider rounded-xl hover:bg-emerald-700 shadow-sm">Confirmer mon inscription</button>
                            </form>
                            <?php else: ?>
                            <div class="space-y-2">
                                <span class="inline-flex px-3 py-1.5 rounded-full bg-rose-500/10 border border-rose-200 text-[10px] font-black uppercase text-rose-900">Inscription indisponible</span>
                                <?php foreach (($policyEval['messages'] ?? []) as $pm): ?>
                                <p class="text-sm text-rose-800"><?= htmlspecialchars((string) $pm) ?></p>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <?php if (!empty($course['instruction_audio_url'])):
                    $au = (string) $course['instruction_audio_url'];
                ?>
                <section class="lms-panel rounded-[2rem] p-6 md:p-8">
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-3">Consignes audio</p>
                    <?php if (!empty($course['instruction_audio_notes'])): ?>
                    <p class="text-sm text-slate-600 mb-3"><?= htmlspecialchars((string) $course['instruction_audio_notes']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($course['instruction_audio_instructor_optional'])): ?>
                    <p class="text-xs text-slate-500 mb-3">Écoute possible sans instructeur présent.</p>
                    <?php endif; ?>
                    <audio controls class="w-full max-w-xl" src="<?= htmlspecialchars($au) ?>">Audio</audio>
                </section>
                <?php endif; ?>

                <?php if ($courseSessions !== []): ?>
                <?php
                $formatSessionInstant = static function (string $raw): string {
                    $raw = trim($raw);
                    if ($raw === '') {
                        return '';
                    }
                    $ts = strtotime($raw);

                    return $ts !== false ? date('d/m/Y \à H:i', $ts) : $raw;
                };
                $formatSessionRange = static function (string $startRaw, string $endRaw) use ($formatSessionInstant): string {
                    $startRaw = trim($startRaw);
                    $endRaw = trim($endRaw);
                    if ($startRaw === '' && $endRaw === '') {
                        return '';
                    }
                    if ($startRaw !== '' && ($startRaw === $endRaw || $endRaw === '')) {
                        return $formatSessionInstant($startRaw);
                    }
                    if ($startRaw === '' && $endRaw !== '') {
                        return 'Jusqu’au ' . $formatSessionInstant($endRaw);
                    }
                    $tsS = strtotime($startRaw);
                    $tsE = strtotime($endRaw);
                    if ($tsS !== false && $tsE !== false && date('Y-m-d', $tsS) === date('Y-m-d', $tsE)) {
                        return date('d/m/Y', $tsS) . ' — de ' . date('H:i', $tsS) . ' à ' . date('H:i', $tsE);
                    }

                    return 'Du ' . $formatSessionInstant($startRaw) . ' au ' . $formatSessionInstant($endRaw);
                };
                ?>
                <section class="lms-panel rounded-[2rem] p-6 md:p-8">
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-4">Créneaux</p>
                    <ul class="space-y-3">
                        <?php foreach ($courseSessions as $cs): ?>
                        <?php
                        $sessLabel = trim((string) ($cs['label'] ?? ''));
                        if ($sessLabel === '') {
                            $sessLabel = 'Session';
                        }
                        $sessLoc = trim((string) ($cs['location'] ?? ''));
                        $locLower = strtolower($sessLoc);
                        $locChipClass = str_contains($locLower, 'discord')
                            ? 'border-indigo-200/90 bg-indigo-50 text-indigo-950'
                            : 'border-slate-200/90 bg-slate-50 text-slate-800';
                        $rangeText = $formatSessionRange((string) ($cs['starts_at'] ?? ''), (string) ($cs['ends_at'] ?? ''));
                        ?>
                        <li class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition-shadow hover:shadow-md md:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-sky-500/25 bg-sky-500/10 text-sky-800" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5"/></svg>
                                </div>
                                <div class="min-w-0 flex-1 space-y-2">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"><?= htmlspecialchars($sessLabel) ?></p>
                                    <?php if ($rangeText !== ''): ?>
                                    <p class="text-sm font-semibold leading-snug text-slate-900"><?= htmlspecialchars($rangeText) ?></p>
                                    <?php endif; ?>
                                    <?php if ($sessLoc !== ''): ?>
                                    <p class="flex flex-wrap items-center gap-2 pt-0.5">
                                        <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold <?= htmlspecialchars($locChipClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <svg class="h-3.5 w-3.5 shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                            <span class="min-w-0 truncate"><?= htmlspecialchars($sessLoc) ?></span>
                                        </span>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($cs['audio_briefing_url'])): ?>
                            <div class="mt-4 border-t border-slate-100 pt-4 sm:pl-[3.75rem]">
                                <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Briefing audio</p>
                                <audio controls class="w-full max-w-md" src="<?= htmlspecialchars((string) $cs['audio_briefing_url']) ?>"></audio>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <?php if ($objectives !== []): ?>
                <section class="lms-panel rounded-[2rem] p-6 md:p-8">
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-4">Objectifs pédagogiques</p>
                    <ul class="space-y-3 text-[13px] text-slate-700 font-medium max-w-3xl">
                        <?php $oi = 1; foreach ($objectives as $obj): ?>
                        <li class="flex gap-3"><span class="text-emerald-600 font-black"><?= str_pad((string) $oi, 2, '0', STR_PAD_LEFT) ?></span><span><?= htmlspecialchars($obj) ?></span></li>
                        <?php $oi++; endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <section id="lms-deroulement" class="space-y-4" aria-label="Déroulement du parcours">
                    <div class="lms-panel rounded-[1.5rem] p-5 md:p-6 border border-slate-200/90">
                        <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Déroulement</p>
                        <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Préambule → module → évaluation → suite</h2>
                        <p class="mt-1.5 text-sm text-slate-600 max-w-2xl">Chaque bloc suit le précédent. L’étape en cours est mise en avant ; les suivantes restent accessibles sans tout mélanger sur un seul écran de contenu.</p>
                    </div>

                    <?php
                    $guidedSteps = function_exists('training_lms_build_guided_sequence')
                        ? training_lms_build_guided_sequence($course)
                        : [];
                    $continueLid = $continueLesson ? (int) ($continueLesson['id'] ?? 0) : 0;
                    $focusIndex = 0;
                    if ($continueLid > 0) {
                        foreach ($guidedSteps as $gi => $gs) {
                            if (($gs['kind'] ?? '') === 'lesson' && (int) (($gs['lesson']['id'] ?? 0)) === $continueLid) {
                                $focusIndex = (int) $gi;
                                break;
                            }
                        }
                    } elseif ($hasCompletedAnyLesson) {
                        foreach ($guidedSteps as $gi => $gs) {
                            if (($gs['kind'] ?? '') === 'echanges') {
                                $focusIndex = (int) $gi;
                                break;
                            }
                        }
                    }
                    $lastPhaseShown = null;
                    foreach ($guidedSteps as $gi => $gs):
                        if (!is_array($gs)) {
                            continue;
                        }
                        $gKind = (string) ($gs['kind'] ?? '');
                        $gPhase = (string) ($gs['phase'] ?? '');
                        $gLabel = (string) ($gs['label'] ?? '');
                        $isFocus = $gi === $focusIndex;
                        $isPast = $gi < $focusIndex;
                        if ($gPhase !== '' && $gPhase !== $lastPhaseShown):
                            $lastPhaseShown = $gPhase;
                            ?>
                    <p class="pt-2 text-[10px] font-black uppercase tracking-[0.22em] text-slate-400"><?= htmlspecialchars($gPhase) ?></p>
                        <?php endif; ?>

                        <?php if ($gKind === 'preamble'): ?>
                    <article class="rounded-xl border px-4 py-3 <?= $isFocus ? 'border-emerald-400 bg-emerald-50/70 shadow-sm' : ($isPast ? 'border-slate-100 bg-slate-50/80 opacity-80' : 'border-slate-200 bg-white') ?>">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider <?= $isFocus ? 'text-emerald-700' : 'text-slate-400' ?>">Étape <?= $gi + 1 ?> · Préambule</p>
                                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($gLabel) ?></h3>
                                <p class="text-xs text-slate-600 mt-0.5">Cadre du parcours, puis démarrage du premier module.</p>
                            </div>
                            <?php if ($enrollment && $canAccessLearning && $firstLesson && $isFocus): ?>
                            <a href="<?= url('formations/lesson/' . (int) ($continueLesson['id'] ?? $firstLesson['id']) . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-[11px] font-black uppercase tracking-wide text-white hover:bg-emerald-700">Continuer</a>
                            <?php elseif ($isPast): ?>
                            <span class="text-[11px] font-semibold text-emerald-700">Fait</span>
                            <?php endif; ?>
                        </div>
                    </article>
                        <?php elseif ($gKind === 'lesson'):
                            $lid = (int) ($gs['lesson']['id'] ?? 0);
                            $isDone = !empty($lessonDone[$lid]);
                            $sum = trim((string) ($gs['lesson']['summary'] ?? ''));
                            ?>
                    <article class="rounded-xl border px-4 py-3 <?= $isFocus ? 'border-emerald-400 bg-emerald-50/70 shadow-sm' : ($isPast || $isDone ? 'border-slate-100 bg-slate-50/80' : 'border-slate-200 bg-white') ?>">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-wider <?= $isFocus ? 'text-emerald-700' : 'text-slate-400' ?>">Étape <?= $gi + 1 ?> · Leçon</p>
                                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($gLabel) ?></h3>
                                <?php if ($sum !== ''): ?>
                                <p class="text-xs text-slate-600 mt-0.5 line-clamp-2"><?= htmlspecialchars($sum) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($enrollment && $canAccessLearning && $lid > 0): ?>
                                <?php if ($isFocus && !$isDone): ?>
                            <a href="<?= url('formations/lesson/' . $lid . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-[11px] font-black uppercase tracking-wide text-white hover:bg-emerald-700"><?= $hasCompletedAnyLesson ? 'Continuer' : 'Commencer' ?></a>
                                <?php elseif ($isDone): ?>
                            <a href="<?= url('formations/lesson/' . $lid . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="text-xs font-bold text-slate-600 hover:underline">Revoir</a>
                                <?php else: ?>
                            <a href="<?= url('formations/lesson/' . $lid . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="text-xs font-bold text-emerald-700 hover:underline">Ouvrir</a>
                                <?php endif; ?>
                            <?php elseif ($enrollment && !$canAccessLearning): ?>
                            <span class="text-[10px] font-bold uppercase text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg">Après validation</span>
                            <?php elseif ($viewerLoggedIn): ?>
                            <a href="#lms-inscription" class="text-[10px] font-bold uppercase text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg">Inscription</a>
                            <?php else: ?>
                            <a href="<?= url('login') ?>" class="text-xs font-semibold text-emerald-700 hover:underline">Connexion</a>
                            <?php endif; ?>
                        </div>
                    </article>
                        <?php elseif ($gKind === 'quiz'):
                            $qid = (int) ($gs['quiz']['id'] ?? 0);
                            $isFinalQ = !empty($gs['quiz']['is_final']);
                            ?>
                    <article class="rounded-xl border px-4 py-3 <?= $isFocus ? 'border-violet-300 bg-violet-50/60 shadow-sm' : ($isPast ? 'border-slate-100 bg-slate-50/80' : 'border-violet-100 bg-violet-50/30') ?>">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-wider <?= $isFocus ? 'text-violet-800' : 'text-violet-500/80' ?>">Étape <?= $gi + 1 ?> · <?= $isFinalQ ? 'Évaluation finale' : 'Évaluation' ?></p>
                                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($gLabel) ?></h3>
                                <p class="text-xs text-slate-600 mt-0.5"><?= $isFinalQ ? 'Après l’ensemble des modules.' : 'Après les leçons de ce module — avant le module suivant.' ?></p>
                            </div>
                            <?php if ($enrollment && $canAccessLearning && $qid > 0): ?>
                            <form method="post" action="<?= url('formations/quiz/start') ?>" class="inline">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="quiz_id" value="<?= $qid ?>">
                                <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
                                <button type="submit" class="<?= $isFocus ? 'inline-flex items-center rounded-lg bg-violet-700 px-3 py-2 text-[11px] font-black uppercase tracking-wide text-white hover:bg-violet-800' : 'text-xs font-bold text-violet-700 hover:underline' ?>"><?= $isFocus ? 'Passer l’évaluation' : 'Démarrer' ?></button>
                            </form>
                            <?php elseif ($enrollment && !$canAccessLearning): ?>
                            <span class="text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg">Après validation</span>
                            <?php elseif ($viewerLoggedIn): ?>
                            <a href="#lms-inscription" class="text-[10px] font-bold uppercase text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg">Inscription</a>
                            <?php else: ?>
                            <a href="<?= url('login') ?>" class="text-xs font-semibold text-emerald-700 hover:underline">Connexion</a>
                            <?php endif; ?>
                        </div>
                    </article>
                        <?php elseif ($gKind === 'echanges'): ?>
                    <article class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white px-4 py-3 <?= $isFocus ? 'ring-2 ring-slate-300' : '' ?>">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Étape <?= $gi + 1 ?> · Fin de parcours</p>
                                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($gLabel) ?></h3>
                                <p class="text-xs text-slate-600 mt-0.5">Note, questions et commentaires — après les modules et évaluations.</p>
                            </div>
                            <a href="<?= url('formations/' . rawurlencode($slugForForms) . '/echanges') ?>" class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-black uppercase tracking-wide text-white hover:bg-slate-800">Ouvrir</a>
                        </div>
                    </article>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>

                <div class="pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="<?= url('formations') ?>" class="text-sm font-bold text-slate-600 hover:text-slate-900">← Retour au catalogue</a>
                    <?php if (!empty($viewerLoggedIn)): ?>
                    <button type="button" id="lms-signalement-parcours" data-community-report data-cr-type="training_course" data-cr-id="<?= (int) $courseId ?>" data-cr-summary="Signalement concernant ce parcours de formation." data-cr-reported-url="<?= htmlspecialchars(url('formations/' . rawurlencode($slugForForms)), ENT_QUOTES, 'UTF-8') ?>" data-cr-page-url="<?= htmlspecialchars(url('formations/' . rawurlencode($slugForForms)), ENT_QUOTES, 'UTF-8') ?>" class="text-left sm:text-right text-xs font-bold text-rose-700 hover:text-rose-900 border border-rose-200 rounded-xl px-4 py-2 bg-rose-50/80 transition-colors hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-2">Signaler un problème sur ce parcours</button>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <?php if (!empty($viewerLoggedIn)) { require base_path('views/partials/community_report_modal.php'); } ?>
    <?php require base_path('views/partials/analytics_beacon.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
