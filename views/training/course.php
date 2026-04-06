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
$courseSessions = $courseSessions ?? [];
$viewerLoggedIn = $viewerLoggedIn ?? false;
$continueLesson = $continueLesson ?? null;
$firstLesson = $firstLesson ?? null;
$lessonDone = $lessonDone ?? [];
$hasCompletedAnyLesson = $lessonDone !== [];
$canAccessLearning = $canAccessLearning ?? false;
$canWithdrawEnrollment = $canWithdrawEnrollment ?? false;
$lmsCommentsEnabled = $lmsCommentsEnabled ?? true;
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
        <div class="grid lg:grid-cols-[300px_1fr] min-h-screen">
            <?php
            $lmsBase = $base;
            $currentLessonId = null;
            require base_path('views/training/partials/lms_course_sidebar.php');
            ?>

            <main class="p-5 md:p-8 lg:p-10 space-y-8">
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

                <header class="lms-panel rounded-[2rem] p-6 md:p-8 relative">
                    <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-emerald-500/80 via-emerald-500/20 to-transparent rounded-t-[2rem]"></div>
                    <div class="flex flex-wrap items-start justify-between gap-6">
                        <div class="min-w-0 flex-1">
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Fiche formation</p>
                            <h1 id="lms-course-page-title" class="text-2xl md:text-4xl font-black tracking-tight uppercase text-slate-900"><?= htmlspecialchars((string) $course['title']) ?></h1>
                            <p class="text-sm font-mono text-emerald-600 mt-2"><?= htmlspecialchars($code) ?></p>
                            <?php if (!empty($course['short_description'])): ?>
                            <p class="text-slate-600 mt-3 max-w-3xl"><?= htmlspecialchars((string) $course['short_description']) ?></p>
                            <?php endif; ?>
                            <p class="text-sm text-slate-500 mt-2"><?= (int)($course['estimated_minutes'] ?? 0) ?> min — <?= htmlspecialchars((string)($course['category'] ?? '')) ?> — <?= htmlspecialchars((string)($course['level'] ?? '')) ?></p>
                        </div>
                        <div class="flex flex-col items-stretch sm:items-end gap-3 shrink-0">
                            <?php if ($viewerLoggedIn): ?>
                            <form method="post" action="<?= url('formations/favorite') ?>" class="inline">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                                <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                                <input type="hidden" name="favorite" value="<?= $isFavorite ? '0' : '1' ?>">
                                <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-xl border text-xs font-black uppercase tracking-wider <?= $isFavorite ? 'border-amber-400 bg-amber-50 text-amber-800' : 'border-slate-200 bg-white text-slate-600' ?>"><?= $isFavorite ? '★ Favori' : '☆ Favori' ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($enrollment && $canAccessLearning): ?>
                            <div class="text-left sm:text-right rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                                <p class="text-3xl font-black text-slate-900 leading-none"><?= (int) $progressPercent ?> %</p>
                                <p class="text-[10px] text-slate-500 uppercase tracking-wider mt-1">Progression</p>
                            </div>
                            <?php if ($certificate): ?>
                            <a href="<?= url('formations/certificate/' . (int) $certificate['id']) ?>" class="inline-flex justify-center px-6 py-3 bg-emerald-600 text-white text-xs font-bold uppercase rounded-xl hover:bg-emerald-700">Attestation</a>
                            <?php elseif (($enrollment['status'] ?? '') === 'completed'): ?>
                            <span class="text-sm font-bold text-emerald-600 text-center sm:text-right">Formation validée</span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($enrollment && $canAccessLearning && (int) $progressPercent < 100): ?>
                    <div class="mt-8">
                        <div class="lms-progress-bar h-2 bg-slate-200 rounded-full overflow-hidden">
                            <span style="width: <?= (int) $progressPercent ?>%"></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </header>

                <?php if ($enrollment && $canAccessLearning && $firstLesson): ?>
                <section class="lms-panel rounded-[2rem] p-6 md:p-8 border border-emerald-200/80 bg-gradient-to-br from-white to-emerald-50/40">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center sm:justify-between gap-4">
                        <div>
                            <p class="text-[9px] font-black tracking-[0.35em] uppercase text-emerald-700/80 mb-2">Parcours</p>
                            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Votre progression</h2>
                            <p class="text-sm text-slate-600 mt-1 max-w-xl">Chaque leçon s’ouvre sur une page dédiée. Utilisez le bouton principal pour enchaîner dans l’ordre du parcours.</p>
                        </div>
                        <div class="flex flex-col sm:items-end gap-2 shrink-0">
                            <?php if ($continueLesson): ?>
                            <?php
                            $ctaPrimary = $hasCompletedAnyLesson ? 'Continuer la formation' : 'Commencer le parcours';
                            ?>
                            <a href="<?= url('formations/lesson/' . (int) $continueLesson['id'] . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="inline-flex items-center justify-center px-8 py-3.5 bg-emerald-600 text-white text-xs font-black uppercase tracking-wider rounded-xl hover:bg-emerald-700 shadow-sm text-center"><?= htmlspecialchars($ctaPrimary) ?></a>
                            <p class="text-[11px] text-slate-500 text-center sm:text-right">Prochaine étape : <strong class="text-slate-800"><?= htmlspecialchars((string) ($continueLesson['title'] ?? '')) ?></strong></p>
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
                <section class="lms-panel rounded-[2rem] p-6 md:p-8">
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-4">Créneaux</p>
                    <ul class="space-y-3 text-sm">
                        <?php foreach ($courseSessions as $cs): ?>
                        <li class="border border-slate-100 rounded-xl p-4 bg-white">
                            <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($cs['label'] ?? 'Session')) ?></p>
                            <p class="text-slate-600"><?= htmlspecialchars((string) ($cs['starts_at'] ?? '')) ?> → <?= htmlspecialchars((string) ($cs['ends_at'] ?? '')) ?></p>
                            <?php if (!empty($cs['location'])): ?><p class="text-slate-500"><?= htmlspecialchars((string) $cs['location']) ?></p><?php endif; ?>
                            <?php if (!empty($cs['audio_briefing_url'])): ?>
                            <audio controls class="w-full max-w-md mt-2" src="<?= htmlspecialchars((string) $cs['audio_briefing_url']) ?>"></audio>
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

                <section class="grid grid-cols-1 gap-6">
                    <?php foreach ($modules as $mod):
                        $mLessons = $mod['lessons'] ?? [];
                        $mQuizzes = $mod['quizzes'] ?? [];
                    ?>
                    <div class="lms-panel rounded-[2rem] p-6 border border-slate-200/80">
                        <h2 class="text-lg font-black uppercase text-slate-900 mb-1"><?= htmlspecialchars((string) ($mod['title'] ?? '')) ?></h2>
                        <?php if (!empty($mod['subtitle'])): ?>
                        <p class="text-sm font-semibold text-slate-700 mb-2"><?= htmlspecialchars((string) $mod['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($mod['description'])): ?>
                        <p class="text-sm text-slate-600 mb-3"><?= htmlspecialchars((string) $mod['description']) ?></p>
                        <?php endif; ?>
                        <?php
                        $modObjs = function_exists('training_lms_learning_objectives')
                            ? training_lms_learning_objectives(['learning_objectives' => $mod['learning_objectives'] ?? ''])
                            : [];
                        ?>
                        <?php if ($modObjs !== []): ?>
                        <ul class="text-xs text-slate-600 mb-4 space-y-1 list-disc list-inside">
                            <?php foreach (array_slice($modObjs, 0, 4) as $mo): ?>
                            <li><?= htmlspecialchars($mo) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($modObjs) > 4): ?>
                            <li class="list-none text-slate-400">…</li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                        <?php if ((int) ($mod['estimated_minutes'] ?? 0) > 0): ?>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-3">≈ <?= (int) $mod['estimated_minutes'] ?> min (module)</p>
                        <?php endif; ?>
                        <ul class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
                            <?php foreach ($mLessons as $lesson): ?>
                            <li class="flex items-center justify-between gap-2 px-4 py-3 bg-white">
                                <span class="min-w-0">
                                    <span class="block text-sm text-slate-800 font-medium"><?= htmlspecialchars((string) ($lesson['title'] ?? '')) ?></span>
                                    <?php if (!empty($lesson['summary'])): ?>
                                    <span class="block text-xs text-slate-500 mt-0.5 line-clamp-2"><?= htmlspecialchars((string) $lesson['summary']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($enrollment && $canAccessLearning):
                                    $lid = (int) ($lesson['id'] ?? 0);
                                    $isDone = !empty($lessonDone[$lid]);
                                    $isNext = $continueLesson && (int) ($continueLesson['id'] ?? 0) === $lid;
                                    if ($isNext && !$isDone) {
                                        $lessonLinkLabel = $hasCompletedAnyLesson ? 'Continuer' : 'Commencer';
                                        $lessonLinkClass = 'text-xs font-black uppercase tracking-wide text-white bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 rounded-lg shrink-0';
                                    } elseif ($isDone) {
                                        $lessonLinkLabel = 'Revoir';
                                        $lessonLinkClass = 'text-xs font-bold text-slate-600 hover:underline shrink-0';
                                    } else {
                                        $lessonLinkLabel = 'Ouvrir';
                                        $lessonLinkClass = 'text-xs font-bold text-emerald-600 hover:underline shrink-0';
                                    }
                                ?>
                                <a href="<?= url('formations/lesson/' . $lid . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="<?= htmlspecialchars($lessonLinkClass) ?>"><?= htmlspecialchars($lessonLinkLabel) ?></a>
                                <?php elseif ($enrollment && !$canAccessLearning): ?>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg shrink-0">Accès après validation</span>
                                <?php elseif ($viewerLoggedIn): ?>
                                <a href="#lms-inscription" class="text-[10px] font-bold uppercase tracking-wide text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg shrink-0 hover:bg-amber-50">Inscription requise</a>
                                <?php else: ?>
                                <a href="<?= url('login') ?>" class="text-xs font-semibold text-emerald-700 hover:underline shrink-0">Connexion</a>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                            <?php foreach ($mQuizzes as $qz): ?>
                            <li class="flex items-center justify-between gap-2 px-4 py-3 bg-slate-50">
                                <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($qz['title'] ?? 'Quiz')) ?></span>
                                <?php if ($enrollment && $canAccessLearning && (int) ($qz['id'] ?? 0) > 0): ?>
                                <form method="post" action="<?= url('formations/quiz/start') ?>" class="inline">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="quiz_id" value="<?= (int) $qz['id'] ?>">
                                    <input type="hidden" name="enrollment_id" value="<?= (int) $enrollment['id'] ?>">
                                    <button type="submit" class="text-xs font-bold text-violet-700 hover:underline">Démarrer</button>
                                </form>
                                <?php elseif ($enrollment && !$canAccessLearning): ?>
                                <span class="text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg shrink-0">Accès après validation</span>
                                <?php elseif ($viewerLoggedIn): ?>
                                <a href="#lms-inscription" class="text-[10px] font-bold uppercase tracking-wide text-amber-800 bg-amber-100 border border-amber-200 px-2 py-1 rounded-lg shrink-0 hover:bg-amber-50">Inscription requise</a>
                                <?php else: ?>
                                <a href="<?= url('login') ?>" class="text-xs font-semibold text-emerald-700 hover:underline shrink-0">Connexion</a>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </section>

                <section class="lms-panel rounded-[2rem] p-6 md:p-8 border border-slate-200/80 bg-gradient-to-br from-slate-50 to-white">
                    <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Après le parcours</p>
                    <h2 class="text-lg font-black uppercase tracking-tight text-slate-900 mb-2">Avis, questions et commentaires</h2>
                    <p class="text-sm text-slate-600 max-w-2xl mb-4"><?= $lmsCommentsEnabled
                        ? 'La note, les questions au staff et les commentaires sont regroupés sur une page dédiée, à la fin du parcours — pas sur chaque leçon ni en bas de cette fiche.'
                        : 'Les avis et les questions au staff restent disponibles sur la page dédiée ; les commentaires libres entre participants sont désactivés pour cette formation.' ?></p>
                    <a href="<?= url('formations/' . rawurlencode($slugForForms) . '/echanges') ?>" class="inline-flex items-center justify-center px-6 py-3 bg-slate-900 text-white text-xs font-black uppercase tracking-wider rounded-xl hover:bg-slate-800">Ouvrir la page « Avis &amp; échanges »</a>
                </section>

                <div class="pt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="<?= url('formations') ?>" class="text-sm font-bold text-slate-600 hover:text-slate-900">← Retour au catalogue</a>
                    <?php if (!empty($viewerLoggedIn)): ?>
                    <button type="button" data-community-report data-cr-type="training_course" data-cr-id="<?= (int) $courseId ?>" data-cr-summary="Signalement concernant ce parcours de formation." class="text-left sm:text-right text-xs font-bold text-rose-700 hover:text-rose-900 border border-rose-200 rounded-xl px-4 py-2 bg-rose-50/80">Signaler un problème sur ce parcours</button>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <?php if (!empty($viewerLoggedIn)) { require base_path('views/partials/community_report_modal.php'); } ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
