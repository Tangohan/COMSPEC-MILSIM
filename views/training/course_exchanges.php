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
$fmtExDate = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }

    return date('d/m/Y à H:i', $ts);
};
$exInitial = static function (string $who): string {
    $who = trim($who);
    if ($who === '') {
        return '?';
    }
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($who, 0, 1));
    }

    return strtoupper(substr($who, 0, 1));
};
$questionsCount = count($courseQuestions);
$answeredCount = 0;
foreach ($courseQuestions as $cqRow) {
    if (!empty($cqRow['answer_text']) || (($cqRow['status'] ?? '') === 'answered')) {
        $answeredCount++;
    }
}
$reviewsCount = count($courseReviews);
$commentsCount = count($courseComments);
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
            $lmsSequenceContext = 'echanges';
            $lmsCompletedLessonIds = is_array($lmsCompletedLessonIds ?? null) ? $lmsCompletedLessonIds : [];
            $lmsPassedQuizIds = is_array($lmsPassedQuizIds ?? null) ? $lmsPassedQuizIds : [];
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
                        <nav class="lms-ex-tabs" aria-label="Sections de la page">
                            <a href="#section-avis">Avis<?php if ($reviewsCount > 0): ?> <span class="lms-ex-tabs__count"><?= $reviewsCount ?></span><?php endif; ?></a>
                            <a href="#section-questions">Questions<?php if ($questionsCount > 0): ?> <span class="lms-ex-tabs__count"><?= $questionsCount ?></span><?php endif; ?></a>
                            <?php if ($lmsCommentsEnabled): ?>
                            <a href="#section-commentaires">Commentaires<?php if ($commentsCount > 0): ?> <span class="lms-ex-tabs__count"><?= $commentsCount ?></span><?php endif; ?></a>
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
                    <div class="lms-ex-section-head">
                        <div class="lms-ex-section-head__title">
                            <span class="lms-ex-section-head__icon lms-ex-section-head__icon--amber" aria-hidden="true">★</span>
                            <div>
                                <h2>Note et avis</h2>
                                <p>Une note courte suffit ; le commentaire est optionnel.</p>
                            </div>
                        </div>
                        <?php if ($reviewsCount > 0): ?>
                        <span class="lms-ex-section-head__meta"><?= $reviewsCount ?> avis</span>
                        <?php endif; ?>
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
                    <p class="lms-ex-login-hint border-b border-slate-100 pb-8 mb-8"><a href="<?= url('login') ?>">Connectez-vous</a> pour laisser une note.</p>
                    <?php endif; ?>
                    <?php if ($courseReviews !== []): ?>
                    <ul class="space-y-4">
                        <?php foreach ($courseReviews as $rv): ?>
                        <?php
                        $who = (string) ($rv['display_name'] ?? $rv['callsign'] ?? 'Membre');
                        $initial = $exInitial($who);
                        ?>
                        <li class="flex gap-4 rounded-2xl border border-slate-100 bg-white/80 p-5 shadow-sm">
                            <div class="shrink-0 w-11 h-11 rounded-xl bg-slate-200 text-slate-700 font-black flex items-center justify-center text-sm"><?= htmlspecialchars($initial) ?></div>
                            <div class="min-w-0 flex-1">
                                <?php $rvStars = max(0, min(5, (int) ($rv['rating'] ?? 0))); ?>
                                <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($who) ?> <span class="text-amber-600 font-black"><?= str_repeat('★', $rvStars) ?><span class="text-slate-300"><?= str_repeat('★', 5 - $rvStars) ?></span></span></p>
                                <?php if (!empty($rv['body'])): ?><p class="text-sm text-slate-700 mt-2 leading-relaxed"><?= nl2br(htmlspecialchars((string) $rv['body'])) ?></p><?php endif; ?>
                                <p class="text-[11px] text-slate-400 mt-2"><?= htmlspecialchars($fmtExDate((string) ($rv['created_at'] ?? ''))) ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="lms-ex-empty">
                        <span class="lms-ex-empty__icon" aria-hidden="true">★</span>
                        <strong>Pas encore d’avis</strong>
                        <p>Soyez le premier à partager votre retour sur ce parcours.</p>
                    </div>
                    <?php endif; ?>
                </section>

                <section id="section-questions" class="lms-panel rounded-[2rem] p-6 md:p-10 scroll-mt-24">
                    <div class="lms-ex-section-head">
                        <div class="lms-ex-section-head__title">
                            <span class="lms-ex-section-head__icon lms-ex-section-head__icon--sky" aria-hidden="true">?</span>
                            <div>
                                <h2>Questions au staff</h2>
                                <p>Posez une question claire sur le parcours. Les réponses publiées apparaissent dans le fil ci-dessous.</p>
                            </div>
                        </div>
                        <?php if ($questionsCount > 0): ?>
                        <span class="lms-ex-section-head__meta"><?= $answeredCount ?> / <?= $questionsCount ?> répondue<?= $answeredCount > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($viewerLoggedIn): ?>
                    <form method="post" action="<?= url('formations/question') ?>" class="lms-ex-compose">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                        <input type="hidden" name="social_return" value="echanges">
                        <label for="question_text" class="lms-ex-compose__label">Votre question</label>
                        <textarea id="question_text" name="question_text" rows="4" placeholder="Ex. : À quel moment dois-je préparer mon dossier ? Qui contacter après le parcours ?" required></textarea>
                        <div class="lms-ex-compose__actions">
                            <button type="submit" class="lms-ex-compose__btn">Envoyer au staff</button>
                            <p class="lms-ex-compose__hint">Une fois envoyée, votre question reste visible ici. La réponse du staff s’affiche dès qu’elle est publiée.</p>
                        </div>
                    </form>
                    <?php else: ?>
                    <p class="lms-ex-login-hint mb-8"><a href="<?= url('login') ?>">Connectez-vous</a> pour poser une question au staff.</p>
                    <?php endif; ?>

                    <?php if ($courseQuestions !== []): ?>
                    <div class="lms-ex-thread" role="list">
                        <?php foreach ($courseQuestions as $cq): ?>
                        <?php
                        $qAuthor = trim((string) ($cq['author_name'] ?? 'Membre'));
                        $qAnswered = !empty($cq['answer_text']) || (($cq['status'] ?? '') === 'answered');
                        $qStaff = trim((string) ($cq['staff_name'] ?? ''));
                        $qCreated = $fmtExDate((string) ($cq['created_at'] ?? ''));
                        $qAnsweredAt = $fmtExDate((string) ($cq['answered_at'] ?? ''));
                        ?>
                        <article class="lms-ex-qa" role="listitem">
                            <div class="lms-ex-qa__q">
                                <div class="lms-ex-qa__meta">
                                    <div class="lms-ex-qa__who">
                                        <span class="lms-ex-qa__avatar" aria-hidden="true"><?= htmlspecialchars($exInitial($qAuthor)) ?></span>
                                        <div>
                                            <p class="lms-ex-qa__name"><?= htmlspecialchars($qAuthor !== '' ? $qAuthor : 'Membre') ?></p>
                                            <?php if ($qCreated !== ''): ?>
                                            <p class="lms-ex-qa__date"><?= htmlspecialchars($qCreated) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($qAnswered): ?>
                                    <span class="lms-ex-qa__badge lms-ex-qa__badge--answered">Répondue</span>
                                    <?php else: ?>
                                    <span class="lms-ex-qa__badge lms-ex-qa__badge--open">En attente</span>
                                    <?php endif; ?>
                                </div>
                                <p class="lms-ex-qa__body"><?= nl2br(htmlspecialchars((string) ($cq['question_text'] ?? ''))) ?></p>
                            </div>
                            <?php if (!empty($cq['answer_text'])): ?>
                            <div class="lms-ex-qa__a">
                                <p class="lms-ex-qa__a-label">
                                    <span>Réponse du staff<?= $qStaff !== '' ? ' · ' . htmlspecialchars($qStaff) : '' ?></span>
                                    <?php if ($qAnsweredAt !== ''): ?>
                                    <span><?= htmlspecialchars($qAnsweredAt) ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="lms-ex-qa__a-body"><?= nl2br(htmlspecialchars((string) $cq['answer_text'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="lms-ex-empty">
                        <span class="lms-ex-empty__icon" aria-hidden="true">?</span>
                        <strong>Aucune question pour l’instant</strong>
                        <p>Une interrogation sur le déroulé, les prérequis ou la suite ? Posez-la ici : le staff y répondra.</p>
                    </div>
                    <?php endif; ?>
                </section>

                <?php if ($lmsCommentsEnabled): ?>
                <section id="section-commentaires" class="lms-panel rounded-[2rem] p-6 md:p-10 scroll-mt-24">
                    <div class="lms-ex-section-head">
                        <div class="lms-ex-section-head__title">
                            <span class="lms-ex-section-head__icon lms-ex-section-head__icon--violet" aria-hidden="true">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </span>
                            <div>
                                <h2>Commentaires</h2>
                                <p>Échanges libres entre participants.</p>
                            </div>
                        </div>
                        <?php if ($commentsCount > 0): ?>
                        <span class="lms-ex-section-head__meta"><?= $commentsCount ?> commentaire<?= $commentsCount > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($viewerLoggedIn): ?>
                    <form method="post" action="<?= url('formations/comment') ?>" class="lms-ex-compose lms-ex-compose--neutral">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="course_slug" value="<?= htmlspecialchars($slugForForms) ?>">
                        <input type="hidden" name="social_return" value="echanges">
                        <label for="comment_body" class="lms-ex-compose__label">Votre commentaire</label>
                        <textarea id="comment_body" name="comment_body" rows="3" placeholder="Partagez une remarque utile…" required></textarea>
                        <div class="lms-ex-compose__actions">
                            <button type="submit" class="lms-ex-compose__btn">Publier</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <p class="lms-ex-login-hint mb-8"><a href="<?= url('login') ?>">Connectez-vous</a> pour commenter.</p>
                    <?php endif; ?>
                    <div class="space-y-5">
                    <?php foreach ($courseComments as $cc): ?>
                        <div class="rounded-xl border-l-4 border-violet-200 bg-violet-50/30 pl-4 py-3 pr-3">
                            <p class="text-xs font-bold text-slate-800"><?= htmlspecialchars((string) ($cc['display_name'] ?? '')) ?></p>
                            <p class="text-sm text-slate-800 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars((string) ($cc['body'] ?? ''))) ?></p>
                            <p class="text-[11px] text-slate-400 mt-2"><?= htmlspecialchars($fmtExDate((string) ($cc['created_at'] ?? ''))) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($courseComments === []): ?>
                        <div class="lms-ex-empty">
                            <span class="lms-ex-empty__icon" aria-hidden="true" style="background:#ede9fe;color:#5b21b6">…</span>
                            <strong>Aucun commentaire</strong>
                            <p>Lancez la discussion avec une remarque utile pour les autres participants.</p>
                        </div>
                    <?php endif; ?>
                    </div>
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
    <script>
    (function () {
      var tabs = document.querySelectorAll('.lms-ex-tabs a');
      if (!tabs.length) return;
      function setActive() {
        var hash = (location.hash || '#section-avis').toLowerCase();
        tabs.forEach(function (a) {
          var href = (a.getAttribute('href') || '').toLowerCase();
          a.classList.toggle('is-active', href === hash);
        });
      }
      setActive();
      window.addEventListener('hashchange', setActive);
    })();
    </script>
</body>
</html>
