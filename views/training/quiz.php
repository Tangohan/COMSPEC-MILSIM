<?php
declare(strict_types=1);
$base = url('');
$attemptId = $attemptId ?? 0;
$course = $course ?? [];
$enrollment = $enrollment ?? null;
$progressPercent = $progressPercent ?? 0;
$quizTitle = trim((string) ($quizTitle ?? 'Quiz'));
$timeLimitMinutes = $timeLimitMinutes ?? null;
$passingScore = $passingScore ?? null;
$title = $title ?? $quizTitle;
$csrfToken = \App\Core\Csrf::token();
$lmsTitle = (string) $title;
$lmsBase = $base;
$theme = function_exists('training_lms_parse_theme') ? training_lms_parse_theme((string) ($course['theme_json'] ?? '')) : [];
$lmsThemeVars = function_exists('training_lms_theme_css_vars') ? training_lms_theme_css_vars($theme) : '';
$lmsExtraHead = '';
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
$currentLessonId = null;
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth module-shell">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen overflow-x-hidden">
    <div class="lms-grain"></div>
    <div class="relative z-10 lms-course-shell flex min-h-screen min-w-0 flex-col lg:flex-row">
        <?php
        $lmsBase = $base;
        require base_path('views/training/partials/lms_course_sidebar.php');
        ?>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="topbar sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 py-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-600 mb-1">Évaluation</p>
                        <h1 class="text-lg sm:text-xl font-black uppercase tracking-tight text-slate-900"><?= htmlspecialchars($quizTitle) ?></h1>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[11px] text-slate-500">
                            <?php if ($timeLimitMinutes !== null && (int) $timeLimitMinutes > 0): ?>
                            <span>Temps indicatif : <strong class="text-slate-700"><?= (int) $timeLimitMinutes ?> min</strong></span>
                            <?php endif; ?>
                            <?php if ($passingScore !== null && $passingScore !== ''): ?>
                            <span>Seuil de réussite : <strong class="text-slate-700"><?= htmlspecialchars((string) $passingScore) ?> %</strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars(url('formations/' . rawurlencode((string) ($course['slug'] ?? '')))) ?>" class="shrink-0 text-[11px] font-black uppercase tracking-wider text-slate-600 hover:text-emerald-700 border border-slate-200 rounded-xl px-4 py-2 hover:bg-slate-50">← Fiche formation</a>
                </div>
            </header>

            <main class="flex-1 px-4 sm:px-8 py-8 lg:py-10">
                <div
                    id="lms-quiz-app"
                    class="max-w-3xl mx-auto w-full"
                    data-attempt-id="<?= (int) $attemptId ?>"
                    data-base="<?= htmlspecialchars($base) ?>"
                    data-csrf="<?= htmlspecialchars($csrfToken) ?>"
                    data-formations-url="<?= htmlspecialchars(url('formations')) ?>"
                    data-course-url="<?= htmlspecialchars(url('formations/' . rawurlencode((string) ($course['slug'] ?? '')))) ?>"
                >
                    <div class="lms-panel rounded-2xl p-8 md:p-10 text-center">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 mb-4" aria-hidden="true">
                            <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p class="text-slate-600 text-sm font-medium">Préparation du questionnaire…</p>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= htmlspecialchars($base) ?>/assets/js/training_quiz_player.js" defer></script>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
