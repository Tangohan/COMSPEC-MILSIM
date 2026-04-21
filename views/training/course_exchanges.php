<?php
declare(strict_types=1);
$base = url('');
$course = $course ?? null;
$enrollment = $enrollment ?? null;
$progressPercent = $progressPercent ?? 0;
if (!$course) {
    echo '<p>Formation non trouvée.</p>';
    return;
}
$courseId = (int) $course['id'];
$slugForForms = (string) ($course['slug'] ?? '');
$courseReviews = $courseReviews ?? [];
$courseAvgRating = $courseAvgRating ?? null;
$courseQuestions = $courseQuestions ?? [];
$courseComments = $courseComments ?? [];
$userReview = $userReview ?? null;
$viewerLoggedIn = $viewerLoggedIn ?? false;
$firstLesson = $firstLesson ?? null;
$canAccessLearning = $canAccessLearning ?? false;
$canWithdrawEnrollment = $canWithdrawEnrollment ?? false;
$lmsCommentsEnabled = $lmsCommentsEnabled ?? true;
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$theme = function_exists('training_lms_parse_theme') ? training_lms_parse_theme((string) ($course['theme_json'] ?? '')) : [];
$code = (string) ($course['course_code'] ?? '');
if ($code === '') {
    $code = 'F-' . $courseId;
}
$lmsTitle = 'Avis et échanges — ' . (string) $course['title'];
$lmsBase = $base;
$lmsThemeVars = function_exists('training_lms_theme_css_vars') ? training_lms_theme_css_vars($theme) : '';
$lmsExtraHead = '';
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
$ficheUrl = url('formations/' . rawurlencode($slugForForms));
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 overflow-x-hidden">
    <div class="lms-grain"></div>
    <div class="min-h-screen relative z-10">
        <div class="lms-course-shell flex min-h-screen min-w-0 flex-col lg:flex-row">
            <?php
            $lmsBase = $base;
            $currentLessonId = null;
            $lmsHideEchangesSidebarLink = true;
            require base_path('views/training/partials/lms_course_sidebar.php');
            ?>

            <main class="min-w-0 flex-1 p-5 md:p-8 lg:p-10">
                <div class="max-w-6xl mx-auto w-full space-y-8 lg:space-y-10">
                <?php if ($flashOk): ?>
                <div class="lms-panel rounded-2xl p-4 bg-emerald-50 border border-emerald-200 text-emerald-950 text-sm font-medium"><?= htmlspecialchars((string) $flashOk) ?></div>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                <div class="lms-panel rounded-2xl p-4 bg-rose-50 border border-rose-200 text-rose-950 text-sm font-medium"><?= htmlspecialchars((string) $flashErr) ?></div>
                <?php endif; ?>

                <header class="lms-panel rounded-[2rem] p-6 md:p-10 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-emerald-500 via-teal-400/60 to-transparent rounded-t-[2rem]"></div>
                    <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-emerald-500/[0.07] blur-2xl pointer-events-none" aria-hidden="true"></div>
                    <div class="relative">
                        <p class="text-[9px] font-black tracking-[0.35em] uppercase text-slate-400 mb-2">Après le parcours</p>
                        <h1 class="text-2xl md:text-4xl font-black tracking-tight text-slate-900 max-w-3xl">Avis, questions et retours</h1>
                        <p class="text-slate-600 mt-4 max-w-2xl text-sm md:text-base leading-relaxed">
                            Échangez sur <strong class="text-slate-800"><?= htmlspecialchars((string) $course['title']) ?></strong> : votre note aide les autres membres ; les questions au staff <?= $lmsCommentsEnabled ? 'et les commentaires ' : '' ?>restent liés à cette formation<?= $lmsCommentsEnabled ? '.' : ' (commentaires désactivés sur cette fiche).' ?>
                        </p>
                        <div class="flex flex-wrap items-center gap-4 mt-5">
                            <span class="text-xs font-mono text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1 rounded-lg"><?= htmlspecialchars($code) ?></span>
                            <?php if ($courseAvgRating !== null): ?>
                            <span class="text-sm text-amber-800 font-bold">Moyenne des notes : <?= htmlspecialchars(number_format((float) $courseAvgRating, 1, ',', ' ')) ?> / 5</span>
                            <?php endif; ?>
                        </div>
                        <nav class="mt-8 flex flex-wrap gap-2 text-[11px] font-black uppercase tracking-wider" aria-label="Sections de la page">
                            <a href="#section-avis" class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-emerald-700 transition-colors">Avis</a>
                            <a href="#section-questions" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50">Questions</a>
                            <?php if ($lmsCommentsEnabled): ?>
                            <a href="#section-commentaires" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50">Commentaires</a>
                            <?php endif; ?>
                        </nav>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="<?= htmlspecialchars($ficheUrl) ?>" class="inline-flex items-center px-5 py-2.5 border border-slate-200 text-slate-800 text-xs font-black uppercase rounded-xl hover:bg-slate-50">← Fiche formation</a>
                            <?php if ($enrollment && $canAccessLearning && $firstLesson && (int) ($firstLesson['id'] ?? 0) > 0): ?>
                            <a href="<?= url('formations/lesson/' . (int) $firstLesson['id'] . '?enrollment_id=' . (int) $enrollment['id']) ?>" class="inline-flex items-center px-5 py-2.5 bg-emerald-600 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700 shadow-md shadow-emerald-600/20">Reprendre le parcours</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <section id="section-avis" class="lms-panel rounded-[2rem] p-6 md:p-10 scroll-mt-24">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-800 text-lg" aria-hidden="true">★</span>
                        <div>
                            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Note et avis</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Une note courte suffit ; le commentaire est optionnel.</p>
                        </div>
                    </div>
                    <?php if ($viewerLoggedIn): ?>
                    <form method="post" action="<?= url('formations/review') ?>" class="space-y-5 border-b border-slate-100 pb-8 mb-8">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                        <input type="hidden" name="social_return" value="echanges">
                        <fieldset>
                            <legend class="text-xs font-bold text-slate-600 mb-3">Votre note</legend>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Note sur 5">
                                <?php for ($s = 5; $s >= 1; $s--): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="rating" value="<?= $s ?>" class="peer sr-only" <?= (!empty($userReview) && (int)($userReview['rating'] ?? 0) === $s) ? 'checked' : '' ?><?= (empty($userReview) && $s === 5) ? ' checked' : '' ?>>
                                    <span class="inline-flex min-w-[2.75rem] justify-center px-3 py-2 rounded-xl border-2 border-slate-200 text-sm font-black text-slate-500 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-900 hover:border-slate-300"><?= $s ?></span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </fieldset>
                        <div>
                            <label for="review_body" class="block text-xs font-bold text-slate-600 mb-2">Commentaire (facultatif)</label>
                            <textarea id="review_body" name="review_body" rows="4" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400 outline-none transition-shadow" placeholder="Ce qui vous a aidé, ce qui manquait, le rythme…"><?= htmlspecialchars((string) ($userReview['body'] ?? '')) ?></textarea>
                        </div>
                        <input type="hidden" name="review_kind" value="rating">
                        <button type="submit" class="px-6 py-3 bg-slate-900 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700">Publier mon avis</button>
                    </form>
                    <?php else: ?>
                    <p class="text-sm text-slate-600 border-b border-slate-100 pb-8 mb-8"><a href="<?= url('login') ?>" class="font-bold text-emerald-700 hover:underline">Connectez-vous</a> pour laisser une note.</p>
                    <?php endif; ?>
                    <?php if ($courseReviews !== []): ?>
                    <ul class="space-y-4">
                        <?php foreach ($courseReviews as $rv): ?>
                        <?php
                        $who = (string) ($rv['display_name'] ?? $rv['callsign'] ?? 'Membre');
                        $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($who, 0, 1)) : strtoupper(substr($who, 0, 1));
                        ?>
                        <li class="flex gap-4 rounded-2xl border border-slate-100 bg-white/80 p-5 shadow-sm">
                            <div class="shrink-0 w-11 h-11 rounded-xl bg-slate-200 text-slate-700 font-black flex items-center justify-center text-sm"><?= htmlspecialchars($initial) ?></div>
                            <div class="min-w-0 flex-1">
                                <?php $rvStars = max(0, min(5, (int) ($rv['rating'] ?? 0))); ?>
                                <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($who) ?> <span class="text-amber-600 font-black"><?= str_repeat('★', $rvStars) ?><span class="text-slate-300"><?= str_repeat('★', 5 - $rvStars) ?></span></span></p>
                                <?php if (!empty($rv['body'])): ?><p class="text-sm text-slate-700 mt-2 leading-relaxed"><?= nl2br(htmlspecialchars((string) $rv['body'])) ?></p><?php endif; ?>
                                <p class="text-[11px] text-slate-400 mt-2"><?= htmlspecialchars((string) ($rv['created_at'] ?? '')) ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-10 text-center">
                        <p class="text-slate-600 text-sm">Pas encore d’avis publié. Soyez le premier à partager votre retour.</p>
                    </div>
                    <?php endif; ?>
                </section>

                <section id="section-questions" class="lms-panel rounded-[2rem] p-6 md:p-10 scroll-mt-24">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-800 text-lg" aria-hidden="true">?</span>
                        <div>
                            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Questions au staff</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Les réponses apparaissent ici lorsqu’elles sont publiées.</p>
                        </div>
                    </div>
                    <div class="space-y-4 mb-8">
                    <?php foreach ($courseQuestions as $cq): ?>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-5">
                            <p class="text-sm text-slate-800 leading-relaxed"><?= nl2br(htmlspecialchars((string) ($cq['question_text'] ?? ''))) ?></p>
                            <?php if (!empty($cq['answer_text'])): ?>
                            <div class="mt-4 pt-4 border-t border-slate-200/80">
                                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700 mb-1">Réponse</p>
                                <p class="text-sm text-emerald-950 leading-relaxed"><?= nl2br(htmlspecialchars((string) $cq['answer_text'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($courseQuestions === []): ?>
                        <p class="text-sm text-slate-500 text-center py-6">Aucune question publique pour l’instant.</p>
                    <?php endif; ?>
                    </div>
                    <?php if ($viewerLoggedIn): ?>
                    <form method="post" action="<?= url('formations/question') ?>" class="space-y-3 rounded-2xl bg-white border border-slate-100 p-5">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                        <input type="hidden" name="social_return" value="echanges">
                        <label for="question_text" class="block text-xs font-bold text-slate-600">Nouvelle question</label>
                        <textarea id="question_text" name="question_text" rows="3" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Formulez votre demande clairement…" required></textarea>
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700">Envoyer au staff</button>
                    </form>
                    <?php else: ?>
                    <p class="text-sm text-slate-600"><a href="<?= url('login') ?>" class="font-bold text-emerald-700 hover:underline">Connectez-vous</a> pour poser une question.</p>
                    <?php endif; ?>
                </section>

                <?php if ($lmsCommentsEnabled): ?>
                <section id="section-commentaires" class="lms-panel rounded-[2rem] p-6 md:p-10 scroll-mt-24">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-800" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-black uppercase tracking-tight text-slate-900">Commentaires</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Échanges libres entre participants.</p>
                        </div>
                    </div>
                    <div class="space-y-5 mb-8">
                    <?php foreach ($courseComments as $cc): ?>
                        <div class="rounded-xl border-l-4 border-violet-200 bg-violet-50/30 pl-4 py-3 pr-3">
                            <p class="text-xs font-bold text-slate-800"><?= htmlspecialchars((string) ($cc['display_name'] ?? '')) ?></p>
                            <p class="text-sm text-slate-800 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars((string) ($cc['body'] ?? ''))) ?></p>
                            <p class="text-[11px] text-slate-400 mt-2"><?= htmlspecialchars((string) ($cc['created_at'] ?? '')) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($courseComments === []): ?>
                        <p class="text-sm text-slate-500 text-center py-6">Aucun commentaire pour le moment.</p>
                    <?php endif; ?>
                    </div>
                    <?php if ($viewerLoggedIn): ?>
                    <form method="post" action="<?= url('formations/comment') ?>" class="space-y-3 rounded-2xl bg-white border border-slate-100 p-5">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                        <input type="hidden" name="social_return" value="echanges">
                        <label for="comment_body" class="block text-xs font-bold text-slate-600">Votre commentaire</label>
                        <textarea id="comment_body" name="comment_body" rows="3" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm" placeholder="Partagez une remarque utile…" required></textarea>
                        <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white text-xs font-black uppercase rounded-xl hover:bg-emerald-700">Publier</button>
                    </form>
                    <?php else: ?>
                    <p class="text-sm text-slate-600"><a href="<?= url('login') ?>" class="font-bold text-emerald-700 hover:underline">Connectez-vous</a> pour commenter.</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <footer class="flex flex-wrap gap-6 pt-2 pb-8 text-sm font-bold text-slate-500">
                    <a href="<?= htmlspecialchars($ficheUrl) ?>" class="hover:text-slate-900">← Fiche formation</a>
                    <a href="<?= url('formations') ?>" class="hover:text-slate-900">Catalogue</a>
                    <a href="<?= url('formations/mes-formations') ?>" class="hover:text-slate-900">Mes parcours</a>
                </footer>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
