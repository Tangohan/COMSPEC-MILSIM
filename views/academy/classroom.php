<?php
/**
 * Salle de cours (maquette « Academy Classroom ») : sommaire chapitres/étapes, en-tête d'étape, contenu,
 * notes, ressources et navigation précédente/suivante. Tout est branché sur l'état serveur du stagiaire :
 * la progression reste séquentielle, seules les étapes déjà atteintes se relisent.
 */
use App\Services\Academy\AcademyCurriculum;

$stages = AcademyCurriculum::STAGES;
$chapters = $selected['chapters'];
$courseKey = (string) $selected['key'];
$stageCount = count($stages);
$courseDone = $state && !empty($state['completed']);
$curC = $state ? (int) $state['chapter'] : 0;
$curS = $state ? (int) $state['stage'] : 0;
$examRunning = $state && !empty($state['exam']);
$reached = static fn (int $c, int $s): bool => $state !== null && ($courseDone || $c < $curC || ($c === $curC && $s <= $curS));
$isDone = static fn (int $c, int $s): bool => $state !== null && ($courseDone || $c < $curC || ($c === $curC && $s < $curS));
$viewC = $curC;
$viewS = $curS;
$requested = false;
// Relecture d'une étape atteinte ; jamais pendant une évaluation chronométrée.
if ($state && !$examRunning && preg_match('/\A(\d{1,2})\.(\d)\z/', (string) ($viewStep ?? ''), $m)
    && isset($chapters[(int) $m[1]]) && (int) $m[2] < $stageCount && $reached((int) $m[1], (int) $m[2])) {
    [$viewC, $viewS, $requested] = [(int) $m[1], (int) $m[2], true];
}
$showCompletion = $courseDone && !$requested;
$isCurrent = $state && !$courseDone && $viewC === $curC && $viewS === $curS;
// Retour d'exercice ou d'évaluation : affiché sur l'étape validée (redirection du contrôleur), jamais en tête de l'étape suivante.
$feedbackFlag = $state ? (string) ($state['feedback'] ?? '') : '';
$latestPractice = $state && !empty($state['practice']) ? end($state['practice']) : null;
$freshResult = $state && !$isCurrent && !$courseDone && (
    ($feedbackFlag === 'correct' && is_array($latestPractice) && (int) ($latestPractice['chapter'] ?? -1) === $viewC && (int) ($latestPractice['stage'] ?? -1) === $viewS)
    || ($feedbackFlag === 'passed' && $viewS === 4 && $viewC === $curC));
$chapter = $chapters[min($viewC, count($chapters) - 1)];
$stepUrl = static fn (int $c, int $s): string => $courseUrl('classroom', $courseKey) . '&step=' . $c . '.' . $s;
$totalSteps = count($chapters) * $stageCount;
$doneSteps = $courseDone ? $totalSteps : ($state ? $curC * $stageCount + $curS : 0);
$progress = $state ? $percent($state) : 0;
$images = ['night', 'fog', 'hero'];
$cover = in_array($selected['cover'] ?? '', $images, true) ? $selected['cover'] : 'night';
$imageFor = static function (int $index) use ($images, $cover): string {
    $start = (int) array_search($cover, $images, true);
    return asset_url('assets/images/academy/cover-' . $images[($start + $index) % 3] . '.webp');
};
$initials = static function (string $title): string {
    $words = preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: ['A'];
    $second = isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1);
    return mb_strtoupper(mb_substr($words[0], 0, 1) . $second);
};
$stepSub = static function (array $chapter, int $s) use ($t): string {
    if ($s === 0) {
        $pages = array_sum(array_map(static fn (array $r): int => isset($r['file']) ? (int) ($r['pages'] ?? 0) : 0, $chapter['resources'] ?? []));
        return $pages > 0 ? $pages . ' ' . $t('slides_count') : $t('sub_theory');
    }
    if ($s === 4) {
        $count = count(AcademyCurriculum::examQuestions($chapter));
        return $count . ' ' . ($count === 1 ? mb_strtolower($t('question_number')) : $t('questions_label')) . ' · ' . (int) ceil(AcademyCurriculum::examSeconds($chapter) / 60) . ' min';
    }
    return $t('sub_' . AcademyCurriculum::STAGES[$s]);
};
$decks = static fn (array $chapter): array => array_values(array_filter($chapter['resources'] ?? [], static fn (array $r): bool => isset($r['file'])));
$links = static fn (array $chapter): array => array_values(array_filter($chapter['resources'] ?? [], static fn (array $r): bool => !isset($r['file'])));
$criteria = $chapter['validation']['criteria'] ?? ($chapter['debrief']['criteria'] ?? []);
$sequence = [];
foreach ($chapters as $ci => $unused) {
    for ($si = 0; $si < $stageCount; $si++) {
        $sequence[] = [$ci, $si];
    }
}
$position = $viewC * $stageCount + $viewS;
$previous = !$showCompletion && $position > 0 ? $sequence[$position - 1] : ($showCompletion ? end($sequence) : null);
$following = !$showCompletion && isset($sequence[$position + 1]) ? $sequence[$position + 1] : null;
$followingOpen = $following !== null && !$examRunning && $reached($following[0], $following[1]);
$letters = ['A', 'B', 'C'];
?>
<?php require __DIR__ . '/icons.php'; ?>
<header class="acr-top">
<button class="tb icon" type="button" data-outline-toggle aria-controls="academy-outline" aria-expanded="true" title="<?= $h($t('outline_toggle')) ?>"><svg class="i"><use href="#ai-menu"/></svg><span class="sr-only"><?= $h($t('outline_toggle')) ?></span></button>
<a class="wm" href="<?= $h($link('dashboard')) ?>">ATHENA <span>ACADEMY</span></a>
<button class="beta" type="button" data-open-disclaimer title="<?= $h($t('disclaimer')) ?>"><?= $h($t('prerelease')) ?></button>
<div class="ct"><b><?= $h($local($selected['title'])) ?></b><?php if ($state): ?><div class="tp" role="img" aria-label="<?= $h($t('progress') . ' ' . $progress . ' %') ?>"><div class="tr"><i style="width:<?= $progress ?>%"></i></div><b><?= $progress ?> %</b></div><?php endif ?></div>
<button class="tb icon" type="button" data-academy-theme data-to-dark="<?= $h($t('theme_to_dark')) ?>" data-to-light="<?= $h($t('theme_to_light')) ?>" aria-pressed="false" title="<?= $h($t('theme_to_dark')) ?>"><svg class="i th-moon" aria-hidden="true"><use href="#ai-moon"/></svg><svg class="i th-sun" aria-hidden="true"><use href="#ai-sun"/></svg><span class="sr-only" data-theme-label><?= $h($t('theme_to_dark')) ?></span></button>
<nav class="lang" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr', 'en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav>
<button class="tb" type="button" data-focus-toggle aria-pressed="false" title="<?= $h($t('focus_help')) ?>"><svg class="i"><use href="#ai-full"/></svg><?= $h($t('focus_mode')) ?></button>
<?php if ($staff): ?><a class="tb tb-studio" href="<?= $h($link('studio') . (empty($selected['built_in']) ? '?course=' . rawurlencode($courseKey) : '')) ?>"><?= $h($t('academy_studio')) ?></a><?php endif ?>
<a class="tb" href="<?= $h($link('dashboard')) ?>"><svg class="i"><use href="#ai-back"/></svg><span class="tb-label"><?= $h($t('back_dashboard')) ?></span></a>
</header>
<div class="acr-wrap" data-classroom>
<aside class="acr-out" id="academy-outline" aria-label="<?= $h($t('course_outline')) ?>">
<div class="acr-oh">
<div class="acr-crest"><span aria-hidden="true"><?= $h($initials($local($selected['title']))) ?></span><div><h2><?= $h($local($selected['title'])) ?></h2><p><?= $h(!empty($selected['license']) ? $local($selected['license']) : $t('not_official')) ?></p></div></div>
<?php if ($state && ($vi = ($versionInfo ?? [])[$courseKey] ?? null) !== null): $vKey = $courseKey; $vLearner = null; require __DIR__ . '/version_badge.php'; endif ?>
<?php if ($state): $nextLabel = $courseDone ? $t('course_finished') : $t('next_up') . ' : ' . $t($stages[$curS]); ?>
<div class="acr-ring"><div class="acr-ring__r" style="--p:<?= $progress ?>"><b><?= $progress ?> %</b></div><div><b><?= $doneSteps ?> / <?= $totalSteps ?></b> <?= $h($t('steps_done')) ?><br><?= $h($nextLabel) ?></div></div>
<?php endif ?>
</div>
<?php foreach ($chapters as $ci => $tocChapter): $chapterDone = $state && ($courseDone || $ci < $curC || isset($state['validations'][$ci])); $chapterSteps = $state ? ($courseDone || $ci < $curC ? $stageCount : ($ci === $curC ? $curS : 0)) : 0; ?>
<details class="acr-ch"<?= $ci === $viewC || !$state ? ' open' : '' ?>>
<summary class="acr-chh<?= $chapterDone ? ' is-done' : '' ?>"><span class="n"><?php if ($chapterDone): ?><svg class="i sm"><use href="#ai-check"/></svg><?php else: ?><?= $ci + 1 ?><?php endif ?></span><span><b><?= $h($local($tocChapter['title'])) ?></b><small><?= $chapterSteps ?> / <?= $stageCount ?> · <?= $h(mb_strtolower($t('chapter'))) ?> <?= $ci + 1 ?></small></span><svg class="i car" aria-hidden="true"><use href="#ai-down"/></svg></summary>
<ol class="acr-ls">
<?php foreach ($stages as $si => $stageName):
    $done = $isDone($ci, $si);
    $open = $reached($ci, $si) && !$examRunning;
    $on = $state && $ci === $viewC && $si === $viewS && !$showCompletion;
    $classes = trim(($on ? 'on ' : '') . ($done ? 'done ' : '') . (!$reached($ci, $si) ? 'lock' : ''));
    $current = $state && !$courseDone && $ci === $curC && $si === $curS;
    $inner = '<span class="st">' . ($done ? '<svg class="i xs"><use href="#ai-check"/></svg>' : (!$reached($ci, $si) ? '<svg class="i xs"><use href="#ai-lock"/></svg>' : '')) . '</span><span><b>' . $h($t($stageName)) . '</b><small><svg class="i xs"><use href="#ai-' . $stageName . '"/></svg>' . $h($stepSub($tocChapter, $si)) . '</small></span>';
?>
<li><?php if ($open): ?><a class="acr-l <?= $classes ?>" href="<?= $h($stepUrl($ci, $si)) ?>"<?= $current ? ' aria-current="step"' : '' ?>><?= $inner ?></a><?php else: ?><span class="acr-l <?= $classes ?>"<?= $current ? ' aria-current="step"' : '' ?> title="<?= $h($t($examRunning ? 'exam_locked_nav' : 'locked_step')) ?>"><?= $inner ?></span><?php endif ?></li>
<?php endforeach ?>
</ol>
</details>
<?php endforeach ?>
</aside>
<main id="main" class="acr-main"><div class="acr-inner">
<?php if ($notice): ?><p class="acr-toast" role="status"><svg class="i"><use href="#ai-check"/></svg><?= $h($t($notice)) ?></p><?php \App\Core\Session::set('academy_notice', ''); endif ?>
<?php if (!empty($preview)): ?><p class="acr-banner"><?= $h($t('preview_banner')) ?></p><?php endif ?>
<?php if (!$academyStorageReady && empty($preview)): ?><p class="acr-banner"><?= $h($t('lms_storage_notice')) ?></p><?php endif ?>

<?php if (!$state): /* Présentation du cursus avant inscription. */ ?>
<header class="acr-lh" style="--img:url('<?= $h($imageFor(0)) ?>')"><div class="num"><small><?= $h($t('chapter_short')) ?></small><b><?= count($chapters) ?></b></div>
<div><p class="crumb"><span class="ty"><?= $h($t(!empty($selected['demo']) ? 'demo' : 'published_course')) ?></span></p><h1><?= $h($local($selected['title'])) ?></h1>
<p class="meta"><?php if (!empty($selected['duration_minutes'])): ?><span><?= (int) $selected['duration_minutes'] ?> min</span><?php endif ?><span><?= count($chapters) ?> <?= $h($t(count($chapters) === 1 ? 'chapter' : 'chapters')) ?> · <?= $stageCount ?> <?= $h($t('learning_steps')) ?></span></p></div></header>
<section class="acr-box acr-intro">
<p class="acr-lead"><?= $h($local($selected['summary'])) ?></p>
<?php if (!empty($selected['license'])): ?><p class="academy-license-note"><?= $h($local($selected['license'])) ?></p><?php endif ?>
<p class="acr-muted"><?= $h($t('not_official')) ?></p>
<?php if (!empty($selected['duration_minutes'])): ?><p><?= $h($t('estimated_duration')) ?> : <?= (int) $selected['duration_minutes'] ?> min</p><?php endif ?>
<h2><?= $h($t('curriculum')) ?></h2>
<ol class="acr-syllabus"><?php foreach ($chapters as $syllabusChapter): ?><li><?= $h($local($syllabusChapter['title'])) ?></li><?php endforeach ?></ol>
<h3><?= $h($t('method_heading')) ?></h3>
<ol class="acr-stagelist"><?php foreach ($stages as $index => $stageName): ?><li><svg class="i"><use href="#ai-<?= $stageName ?>"/></svg><?= $h($t($stageName)) ?></li><?php endforeach ?></ol>
<?php if (empty($preview)): $formStart('enroll', $courseKey); ?><div class="act"><button class="btn pri"<?= !$academyStorageReady ? ' disabled' : '' ?>><?= $h($t('enroll')) ?></button></div></form><?php endif ?>
</section>

<?php elseif ($showCompletion): /* Parcours terminé : licence éventuelle et dossier. */ ?>
<section class="acr-cert">
<div class="l1"><p class="k"><?= $h($t('validation')) ?></p><h1><?= $h($t('completed')) ?></h1><p><?= $h($t('all_done')) ?></p>
<ul class="acr-reqs"><li class="y"><i>✓</i><?= $h($t('req_exam')) ?></li><li class="y"><i>✓</i><?= $h($t('req_debrief')) ?></li><li class="y"><i>✓</i><?= $h($t('req_instructor')) ?></li></ul>
<a class="hb1" href="<?= $h($link('record')) ?>"><?= $h($t('record')) ?></a></div>
<div class="r1" style="--img:url('<?= $h($imageFor(2)) ?>')"></div>
</section>
<?php $licenseState = $state; require __DIR__ . '/course_license.php'; ?>
<p class="acr-muted"><?= $h($t('not_official')) ?></p>

<?php else: /* Une étape : en cours ou relue. */ ?>
<header class="acr-lh" style="--img:url('<?= $h($imageFor($viewC)) ?>')">
<div class="num"><small><?= $h($t('chapter_short')) ?></small><b><?= $viewC + 1 ?>.<?= $viewS + 1 ?></b></div>
<div><p class="crumb"><span class="ty"><svg class="i sm"><use href="#ai-<?= $stages[$viewS] ?>"/></svg><?= $h($t($stages[$viewS])) ?></span><span aria-hidden="true">·</span><span><?= $h($t('chapter')) ?> <?= $viewC + 1 ?> <?= $h($t('chapter_of')) ?> <?= count($chapters) ?></span></p>
<h1><?= $h($local($chapter['title'])) ?></h1>
<p class="meta"><?php if ($viewS === 4): ?><span><?= (int) ceil(AcademyCurriculum::examSeconds($chapter) / 60) ?> min</span><?php endif ?><span><?= $h($t('fictional_short')) ?></span><?php if ($isDone($viewC, $viewS)): ?><span class="ok">✓ <?= $h($t('completed')) ?></span><?php endif ?></p></div>
<div class="lsteps"><div class="dots"><?php foreach ($stages as $si => $stageName): $dotClass = $si === $viewS ? 'c' : ($isDone($viewC, $si) ? 'd' : ''); ?><?php if ($reached($viewC, $si) && !$examRunning): ?><a class="<?= $dotClass ?>" href="<?= $h($stepUrl($viewC, $si)) ?>" title="<?= $h($t($stageName)) ?>"><span class="sr-only"><?= $h($t($stageName)) ?></span></a><?php else: ?><span class="<?= $dotClass ?>" title="<?= $h($t($stageName)) ?>"></span><?php endif ?><?php endforeach ?></div><small><?= $h($t('step_label')) ?> <?= $viewS + 1 ?> <?= $h($t('chapter_of')) ?> <?= $stageCount ?></small></div>
</header>
<?php if (!$isCurrent && !$freshResult): ?><p class="acr-banner"><?= $h($t('review_banner')) ?><?php if (!$courseDone): ?> <a href="<?= $h($stepUrl($curC, $curS)) ?>"><?= $h($t('back_to_current')) ?></a><?php endif ?></p><?php endif ?>

<?php if ($viewS <= 1): /* Cours théorique et démonstration. */ ?>
<?php if ($viewS === 0): foreach ($decks($chapter) as $deck): $deckUrl = $link('media/' . rawurlencode((string) $deck['file'])); ?>
<figure class="acr-deck" data-academy-deck data-src="<?= $h($deckUrl) ?>" data-lib="<?= $h(asset_url('assets/vendor/pdfjs/pdf.js')) ?>" data-worker="<?= $h(asset_url('assets/vendor/pdfjs/pdf.worker.min.js')) ?>" data-label-of="<?= $h($t('chapter_of')) ?>">
<div class="sv"><canvas data-deck-canvas aria-label="<?= $h($local($deck['title'])) ?>"></canvas><p class="acr-deck__status" data-deck-status><?= $h($t('slides_loading')) ?> <a href="<?= $h($deckUrl) ?>" target="_blank" rel="noopener"><?= $h($t('open_slides')) ?></a></p><button class="nav p" type="button" data-deck-prev aria-label="<?= $h($t('slide_prev')) ?>"></button><button class="nav n" type="button" data-deck-next aria-label="<?= $h($t('slide_next')) ?>"></button></div>
<div class="dc"><button class="ib" type="button" data-deck-prev aria-label="<?= $h($t('slide_prev')) ?>"><svg class="i"><use href="#ai-left"/></svg></button><span class="cnt" data-deck-count>1 / <?= (int) ($deck['pages'] ?? 1) ?></span><button class="ib" type="button" data-deck-next aria-label="<?= $h($t('slide_next')) ?>"><svg class="i"><use href="#ai-right"/></svg></button><div class="pb" data-deck-bar></div><button class="ib" type="button" data-deck-full aria-label="<?= $h($t('fullscreen')) ?>"><svg class="i"><use href="#ai-full"/></svg></button></div>
<div class="thumbs" data-deck-thumbs></div>
<figcaption><?= $h($local($deck['title'])) ?> · <?= (int) ($deck['pages'] ?? 0) ?> <?= $h($t('slides_count')) ?> · <a href="<?= $h($deckUrl) ?>" target="_blank" rel="noopener"><?= $h($t('open_slides')) ?></a></figcaption>
</figure>
<?php endforeach; endif ?>
<article class="acr-box acr-text"><div class="lesson-text rt"><?= $rich($chapter[$viewS === 0 ? 'theory' : 'demonstration']) ?></div>
<?php if ($isCurrent): $formStart('advance', $courseKey); ?><div class="act"><button class="btn dark"><?= $h($t('next')) ?><svg class="i"><use href="#ai-right"/></svg></button><span class="msg"><?= $h($t('advance_help')) ?></span></div></form><?php endif ?>
</article>

<?php elseif ($viewS <= 3): /* Cas guidé et simulation autonome : le retour s'affiche sur la question elle-même. */
$question = AcademyCurriculum::questionForStage($chapter, $viewS);
$tries = array_values(array_filter($state['practice'] ?? [], static fn (array $try): bool => (int) ($try['chapter'] ?? -1) === $viewC && (int) ($try['stage'] ?? -1) === $viewS));
$lastTry = $tries !== [] ? end($tries) : null;
$validTries = array_values(array_filter($tries, static fn (array $try): bool => !empty($try['correct']) && is_int($try['answer'] ?? null)));
$validTry = $validTries !== [] ? end($validTries) : null;
$justValidated = !$isCurrent && $feedbackFlag === 'correct' && $validTry !== null && $latestPractice === $validTry;
$missed = $isCurrent && $feedbackFlag === 'retry' && $lastTry !== null && empty($lastTry['correct']) && is_int($lastTry['answer'] ?? null) ? (int) $lastTry['answer'] : null;
$why = static fn (array $q, int $index): string => isset($q['option_feedback'][$index]) && is_array($q['option_feedback'][$index]) ? $local($q['option_feedback'][$index]) : '';
?>
<div class="acr-ex"><div>
<?php if (!empty($question['brief'])): ?><section class="acr-case" style="--img:url('<?= $h($imageFor($viewC + 1)) ?>')"><p class="kk"><?= $h($t($viewS === 2 ? 'case_guided' : 'case_autonomous')) ?></p><h2><?= $h($local($chapter['title'])) ?></h2><div class="rt"><?= $rich($question['brief']) ?></div></section><?php endif ?>
<?php if ($isCurrent): $formStart('practice', $courseKey); ?>
<fieldset class="acr-opts" data-choice<?= $missed !== null ? ' data-missed' : '' ?>><legend><span class="q"><?= $h($local($question['question'])) ?></span><span class="qh"><?= $h($t($viewS === 2 ? 'choose_then_validate' : 'independent_case')) ?></span></legend>
<?php foreach ($question['options'] as $index => $option): $wrong = $missed === $index; ?><label class="op<?= $wrong ? ' is-wrong' : '' ?>"><input type="radio" name="answer" value="<?= $index ?>" required><span class="lt"><?= $letters[$index] ?></span><span class="tx"><?= $h($local($option)) ?><?php if ($wrong): ?><small class="tag ko"><span aria-hidden="true">✗</span> <?= $h($t('fb_previous_try')) ?></small><?php endif ?></span></label><?php endforeach ?>
</fieldset>
<?php if ($missed !== null): ?>
<section class="acr-result acr-result--ko" id="acr-result" tabindex="-1" data-result-announce="<?= $h(sprintf($t('fb_result_wrong'), $letters[$missed])) ?>" aria-labelledby="acr-result-title">
<header><span class="ri" aria-hidden="true">✗</span><div><h2 id="acr-result-title"><?= $h($t('fb_wrong_title')) ?></h2><p><?= $h($t($viewS === 2 ? 'fb_wrong_guided' : 'fb_wrong_simulation')) ?></p></div></header>
<?php if ($viewS === 2): ?><div class="rb"><p class="k"><?= $h($t('fb_explanation')) ?></p><div class="rt"><?= $rich($question['feedback']) ?></div>
<?php if ($why($question, $missed) !== ''): ?><p class="k"><?= $h($t('fb_why_others')) ?></p><div class="rt"><?= \App\Services\Academy\AcademyRichText::render($why($question, $missed), $copy) ?></div><?php endif ?></div><?php endif ?>
<div class="ra"><button class="btn" type="button" data-retry><svg class="i"><use href="#ai-refresh"/></svg><?= $h($t('fb_retry')) ?></button></div>
</section>
<?php endif ?>
<div class="act"><button class="btn dark"><?= $h($t('validate_answer')) ?></button><span class="msg" data-choice-msg data-empty="<?= $h($t('no_choice')) ?>" data-chosen="<?= $h($t('choice_made')) ?>"><?= $h($t('no_choice')) ?></span></div></form>
<?php else: $chosen = $validTry !== null ? (int) $validTry['answer'] : null; ?>
<h2 class="q"><?= $h($local($question['question'])) ?></h2>
<ul class="acr-opts acr-opts--read<?= $chosen !== null ? ' is-graded' : '' ?>"><?php foreach ($question['options'] as $index => $option): $ok = $chosen === $index; ?><li class="op<?= $ok ? ' is-correct' : ($chosen !== null ? ' is-off' : '') ?>"<?= $chosen !== null && !$ok ? ' aria-disabled="true"' : '' ?>><span class="lt"><?= $ok ? '<span aria-hidden="true">✓</span><span class="sr-only">' . $letters[$index] . '</span>' : $letters[$index] ?></span><span class="tx"><?= $h($local($option)) ?><?php if ($ok): ?><small class="tag ok"><?= $h($t('fb_your_choice')) ?> · <?= $h($t('fb_correct_title')) ?></small><?php endif ?></span></li><?php endforeach ?></ul>
<?php if ($chosen !== null): ?>
<section class="acr-result acr-result--ok<?= $justValidated ? ' is-new' : '' ?>" id="acr-result" tabindex="-1"<?= $justValidated ? ' data-result-announce="' . $h(sprintf($t('fb_result_correct'), $letters[$chosen])) . '"' : '' ?> aria-labelledby="acr-result-title">
<header><span class="ri" aria-hidden="true">✓</span><div><h2 id="acr-result-title"><?= $h($t($justValidated ? 'fb_correct_title' : 'fb_validated_review')) ?></h2><?php if ($justValidated): ?><p><?= $h($t('fb_correct_lead')) ?></p><?php endif ?></div></header>
<div class="rb"><p class="k"><?= $h($t('fb_explanation')) ?></p><div class="rt"><?= $rich($question['feedback']) ?></div>
<?php $others = array_filter(array_keys($question['options']), static fn (int $i): bool => $i !== $chosen && $why($question, $i) !== ''); if ($others !== []): ?>
<p class="k"><?= $h($t('fb_why_others')) ?></p><ul class="acr-why"><?php foreach ($others as $other): ?><li><span class="lt"><?= $letters[$other] ?></span><div class="rt"><?= \App\Services\Academy\AcademyRichText::render($why($question, $other), $copy) ?></div></li><?php endforeach ?></ul><?php endif ?></div>
<?php if ($justValidated && !$courseDone): ?><div class="ra"><a class="btn dark" href="<?= $h($stepUrl($curC, $curS)) ?>"><?= $h($t('fb_continue')) ?> <?= $h(mb_strtolower($t($stages[$curS]))) ?><svg class="i"><use href="#ai-right"/></svg></a></div><?php endif ?>
</section>
<?php else: ?><p class="acr-ok"><svg class="i"><use href="#ai-check"/></svg><?= $h($t('step_validated')) ?></p><?php endif ?>
<?php endif ?>
</div>
<aside class="acr-side">
<?php if ($viewS === 2): ?><details class="acr-hint"<?= $missed !== null ? ' open' : '' ?>><summary><?= $h($t('hint_title')) ?><svg class="i"><use href="#ai-down"/></svg></summary><div class="rt"><?= $rich($question['hint'] ?? $chapter['demonstration']) ?></div></details><?php endif ?>
<?php require __DIR__ . '/classroom_notes.php'; ?>
</aside></div>

<?php elseif ($viewS === 4): /* Évaluation corrigée par le serveur ; correction détaillée seulement après réussite. */ $examQuestions = AcademyCurriculum::examQuestions($chapter); $examSeconds = AcademyCurriculum::examSeconds($chapter); $examThreshold = AcademyCurriculum::examPassPercent($chapter); $richExam = isset($chapter['assessment']['questions']);
$chapterAttempts = array_values(array_filter($state['attempts'] ?? [], static fn (array $attempt): bool => (int) ($attempt['chapter'] ?? -1) === $viewC)); $lastAttempt = $chapterAttempts !== [] ? end($chapterAttempts) : null;
$examJust = in_array($feedbackFlag, ['passed', 'failed'], true) && $lastAttempt !== null && !$examRunning; ?>
<section class="acr-qz">
<div class="qp" aria-hidden="true"><?php foreach ($examQuestions as $index => $unused): $result = $lastAttempt['results'][$index] ?? null; ?><i class="<?= $examRunning ? 'cur' : ($result === null || empty($lastAttempt['passed']) ? '' : (!empty($result['correct']) ? 'ok' : 'ko')) ?>"></i><?php endforeach ?></div>
<?php if ($lastAttempt !== null && !$examRunning): $passedAttempt = !empty($lastAttempt['passed']); $questionCount = (int) ($lastAttempt['question_count'] ?? count($examQuestions)); ?>
<section class="acr-result acr-result--<?= $passedAttempt ? 'ok' : 'ko' ?> acr-result--exam<?= $examJust ? ' is-new' : '' ?>" id="acr-result" tabindex="-1"<?= $examJust ? ' data-result-announce="' . $h($t($passedAttempt ? 'exam_passed' : 'exam_failed') . ' · ' . (int) $lastAttempt['score'] . ' %') . '"' : '' ?> aria-labelledby="acr-result-title">
<header><span class="ri" aria-hidden="true"><?= $passedAttempt ? '✓' : '✗' ?></span><div><p class="k"><?= $h($t('exam_result_title')) ?></p><h2 id="acr-result-title"><?= $h($t($passedAttempt ? 'exam_passed' : 'exam_failed')) ?></h2></div>
<div class="sc"><b><?= (int) $lastAttempt['score'] ?> %</b><small><?= $h($t('passing_score')) ?> <?= $examThreshold ?> %</small></div></header>
<div class="rb">
<div class="meter" role="img" aria-label="<?= $h((int) $lastAttempt['score'] . ' %') ?>"><i style="width:<?= max(0, min(100, (int) $lastAttempt['score'])) ?>%"></i><span style="left:<?= $examThreshold ?>%"></span></div>
<?php if (isset($lastAttempt['correct_count'])): ?><p><?= $h(sprintf($t('exam_count'), (int) $lastAttempt['correct_count'], $questionCount)) ?></p><?php endif ?>
<?php if (!empty($lastAttempt['expired'])): ?><p class="acr-muted"><?= $h($t('exam_expired_note')) ?></p><?php endif ?>
<?php if ($passedAttempt && isset($lastAttempt['results'])): ?>
<details class="acr-byq"<?= $examJust ? ' open' : '' ?>><summary><?= $h($t('exam_by_question')) ?><svg class="i"><use href="#ai-down"/></svg></summary><ol>
<?php foreach ($examQuestions as $qi => $examQuestion): $res = $lastAttempt['results'][$qi] ?? []; $sel = $res['selected'] ?? null; $good = !empty($res['correct']); ?>
<li class="<?= $good ? 'ok' : 'ko' ?>"><span class="ri" aria-hidden="true"><?= $good ? '✓' : '✗' ?></span><div><p class="q"><?= $h($local($examQuestion['question'])) ?> <span class="sr-only"><?= $h($t($good ? 'response_correct' : 'response_incorrect')) ?></span></p>
<p><span class="lab"><?= $h($t('submitted_response')) ?><?= $lang === 'fr' ? ' :' : ':' ?></span> <?= is_int($sel) && isset($examQuestion['options'][$sel]) ? '<b>' . $letters[$sel] . '</b> ' . $h($local($examQuestion['options'][$sel])) : $h($t('no_response')) ?></p>
<?php if (!$good): ?><p><span class="lab"><?= $h($t('expected_response')) ?><?= $lang === 'fr' ? ' :' : ':' ?></span> <b><?= $letters[$examQuestion['answer']] ?></b> <?= $h($local($examQuestion['options'][$examQuestion['answer']])) ?></p><?php endif ?>
<div class="rt"><?= $rich($examQuestion['feedback']) ?></div></div></li>
<?php endforeach ?></ol></details>
<?php elseif (!$passedAttempt): ?><p class="acr-muted"><?= $h($t('exam_details_after_pass')) ?></p><?php endif ?>
</div>
<?php if ($passedAttempt && $examJust && !$courseDone && !$isCurrent): ?><div class="ra"><a class="btn dark" href="<?= $h($stepUrl($curC, $curS)) ?>"><?= $h($t('fb_continue')) ?> <?= $h(mb_strtolower($t($stages[$curS]))) ?><svg class="i"><use href="#ai-right"/></svg></a></div><?php endif ?>
</section>
<?php endif ?>
<div class="acr-box">
<p class="k"><?= $h($t('assessment')) ?> · <?= count($examQuestions) ?> <?= $h($t('questions_label')) ?> · <?= (int) ceil($examSeconds / 60) ?> min · <?= $h($t('passing_score')) ?> <?= $examThreshold ?> %</p>
<?php if ($isCurrent && !$examRunning): ?>
<h2 class="q"><?= $h($t($lastAttempt !== null ? 'exam_retry' : 'ready_exam')) ?></h2><p><?= $h($t($lastAttempt !== null ? 'exam_retry_help' : 'exam_server_rules')) ?></p>
<?php if (!empty($chapter['assessment']['instructions'])): ?><div class="lesson-text rt"><?= $rich($chapter['assessment']['instructions']) ?></div><?php endif ?>
<?php $formStart('exam_start', $courseKey); ?><div class="act"><button class="btn dark"><?= $h($t('start_exam')) ?></button></div></form>
<?php elseif ($isCurrent): ?>
<div class="acr-clock" role="timer" aria-label="<?= $h($t('remaining')) ?>"><span><?= $h($t('remaining')) ?></span><strong data-countdown="<?= (int) $state['exam']['deadline'] ?>" data-server-now="<?= time() ?>"><?= $h(sprintf('%02d:%02d', intdiv(max(0, (int) $state['exam']['deadline'] - time()), 60), max(0, (int) $state['exam']['deadline'] - time()) % 60)) ?></strong></div>
<?php $formStart('exam_submit', $courseKey); ?><input type="hidden" name="token" value="<?= $h($state['exam']['token']) ?>">
<?php foreach ($examQuestions as $questionIndex => $examQuestion): ?><fieldset class="acr-opts"><legend><span class="q"><?= $questionIndex + 1 ?>. <?= $h($local($examQuestion['question'])) ?></span></legend><?php foreach ($examQuestion['options'] as $index => $option): ?><label class="op"><input type="radio" name="<?= $richExam ? 'answers[' . $questionIndex . ']' : 'answer' ?>" value="<?= $index ?>" required><span class="lt"><?= $letters[$index] ?></span><span><?= $h($local($option)) ?></span></label><?php endforeach ?></fieldset><?php endforeach ?>
<div class="act"><button class="btn dark"><?= $h($t('send_exam')) ?></button></div></form>
<?php else: ?>
<h2 class="q"><?= $h($t('history')) ?></h2>
<?php if ($chapterAttempts === []): ?><p class="acr-muted"><?= $h($t('no_attempt')) ?></p><?php else: ?><div class="table-scroll"><table><thead><tr><th><?= $h($t('score')) ?></th><th><?= $h($t('date')) ?></th></tr></thead><tbody><?php foreach (array_reverse($chapterAttempts) as $attempt): ?><tr><td><?= (int) $attempt['score'] ?> %</td><td><?= $h((new DateTimeImmutable('@' . (int) $attempt['at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i')) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
<?php endif ?>
</div></section>

<?php elseif ($viewS === 5): /* Débriefing écrit. */ ?>
<section class="acr-box">
<p class="k"><?= $h($t('debrief')) ?></p><h2 class="q"><?= $h($t('reflection')) ?></h2>
<?php $reviewState = $state; $reviewChapter = $chapter; if ($isCurrent) { require __DIR__ . '/course_review.php'; } ?>
<div class="lesson-text rt"><?= $rich($chapter['debrief']['prompt'] ?? $chapter['feedback']) ?></div>
<?php if (!empty($chapter['debrief']['criteria'])): ?><ul class="academy-rubric"><?php foreach ($chapter['debrief']['criteria'] as $criterion): ?><li><?= $h($local($criterion)) ?></li><?php endforeach ?></ul><?php endif ?>
<?php if ($isCurrent): $formStart('debrief', $courseKey); ?><label for="reflection"><?= $h($t('reflection_help')) ?></label><textarea id="reflection" name="reflection" minlength="20" maxlength="5000" rows="10" required></textarea><label class="academy-check"><input type="checkbox" name="nothing_to_improve" value="1" data-nothing-toggle aria-controls="reflection"> <?= $h($t('nothing_to_improve')) ?></label><p class="hint"><?= $h($t('nothing_to_improve_help')) ?></p><div class="act"><button class="btn dark"><?= $h($t('send_debrief')) ?></button></div></form>
<?php else: ?><h3><?= $h($t('submitted_debrief')) ?></h3><p class="lesson-text"><?= nl2br($h($debriefText($state['debriefs'][$viewC] ?? ''))) ?></p><?php endif ?>
</section>

<?php else: /* Validation par un instructeur. */ $validationEntry = $state['validations'][$viewC] ?? null; ?>
<section class="acr-cert">
<div class="l1"><p class="k"><?= $h($t('validation')) ?></p><h2><?= $h($t($validationEntry ? 'validated' : 'pending')) ?></h2><p><?= $h($validationEntry ? $t('step_validated') : $t('pending_text')) ?></p>
<ul class="acr-reqs"><li class="y"><i>✓</i><?= $h($t('req_exam')) ?></li><li class="y"><i>✓</i><?= $h($t('req_debrief')) ?></li><li class="<?= $validationEntry ? 'y' : 'n' ?>"><i><?= $validationEntry ? '✓' : '' ?></i><?= $h($t('req_instructor')) ?></li></ul>
<?php if ($validationEntry && !empty($validationEntry['auto'])): ?><p class="acr-comment"><b><?= $h($t(!empty($validationEntry['final']) ? 'auto_badge_final' : 'auto_badge')) ?></b><?= $h(sprintf($t(!empty($validationEntry['final']) ? 'auto_trace_final' : 'auto_trace'), (int) ($validationEntry['hours'] ?? 72))) ?></p>
<?php elseif ($validationEntry): ?><p class="acr-comment"><b><?= $h($t('instructor_comment')) ?></b><?= nl2br($h($validationEntry['comment'] ?? '')) ?></p><?php endif ?></div>
<div class="r1" style="--img:url('<?= $h($imageFor($viewC + 2)) ?>')"></div>
</section>
<?php if (!empty($chapter['validation']['brief'])): ?><div class="acr-box"><div class="lesson-text rt"><?= $rich($chapter['validation']['brief']) ?></div></div><?php endif ?>
<?php if (!$validationEntry): ?><section class="acr-box"><h3><?= $h($t('submitted_debrief')) ?></h3><p class="lesson-text"><?= nl2br($h($debriefText($state['debriefs'][$viewC] ?? ''))) ?></p></section><?php endif ?>
<?php endif ?>

<?php if ($viewS <= 1 || $viewS >= 4): /* Sous le contenu : notes, ressources, critères (masqués pendant l'évaluation). */ ?>
<?php if (!$examRunning): ?>
<div class="acr-pane">
<div data-tabs>
<div class="acr-tabs" role="tablist"><button type="button" role="tab" aria-selected="true" data-tab="notes"><?= $h($t('my_notes')) ?></button><button type="button" role="tab" aria-selected="false" data-tab="resources"><?= $h($t('resources_tab')) ?></button></div>
<div data-panel="notes" role="tabpanel"><?php require __DIR__ . '/classroom_notes.php'; ?></div>
<div data-panel="resources" role="tabpanel" class="acr-box acr-res">
<?php $resources = $links($chapter); foreach ($decks($chapter) as $deck): ?><a class="acr-res__file" href="<?= $h($link('media/' . rawurlencode((string) $deck['file']))) ?>" target="_blank" rel="noopener"><i>PDF</i><span><b><?= $h($local($deck['title'])) ?></b><small><?= (int) ($deck['pages'] ?? 0) ?> <?= $h($t('slides_count')) ?></small></span><svg class="i"><use href="#ai-right"/></svg></a><?php endforeach ?>
<?php require __DIR__ . '/course_resources.php'; ?>
<?php if ($resources === [] && $decks($chapter) === []): ?><p class="acr-muted"><?= $h($t('no_resources')) ?></p><?php endif ?>
</div>
</div>
<aside class="acr-box"><h3><?= $h($t($criteria !== [] ? 'checked_criteria' : 'curriculum')) ?></h3>
<?php if ($criteria !== []): ?><ul class="acr-obj"><?php foreach ($criteria as $criterion): ?><li><svg class="i"><use href="#ai-check"/></svg><?= $h($local($criterion)) ?></li><?php endforeach ?></ul>
<?php else: ?><ol class="acr-obj acr-obj--plain"><?php foreach ($chapters as $ci => $syllabusChapter): ?><li<?= $ci === $viewC ? ' class="on"' : '' ?>><?= $h($local($syllabusChapter['title'])) ?></li><?php endforeach ?></ol><?php endif ?>
</aside>
</div>
<?php endif ?>
<?php endif ?>
<?php endif ?>
</div></main>
</div>
<?php if ($state): ?>
<nav class="acr-fn" aria-label="<?= $h($t('step_navigation')) ?>"><div class="inner">
<?php if ($previous !== null && !$examRunning): ?><a class="btn" href="<?= $h($stepUrl($previous[0], $previous[1])) ?>"><svg class="i"><use href="#ai-left"/></svg><?= $h($t('previous')) ?></a><span class="pv"><?= $h($t($stages[$previous[1]])) ?> · <?= $h($t('chapter')) ?> <?= $previous[0] + 1 ?></span>
<?php else: ?><span class="btn" aria-disabled="true"><svg class="i"><use href="#ai-left"/></svg><?= $h($t('previous')) ?></span><span class="pv"></span><?php endif ?>
<span class="nx"><?php if ($following !== null): ?><?= $h($t('up_next')) ?> <b><?= $h($t($stages[$following[1]])) ?><?= $following[0] !== $viewC ? ' · ' . $h($t('chapter')) . ' ' . ($following[0] + 1) : '' ?></b><?php elseif (!$showCompletion): ?><b><?= $h($t('end_of_course')) ?></b><?php endif ?></span>
<?php if ($followingOpen): ?><a class="btn dark" href="<?= $h($stepUrl($following[0], $following[1])) ?>"><?= $h($t('next_step')) ?><svg class="i"><use href="#ai-right"/></svg></a>
<?php elseif ($courseDone && !$showCompletion && $following === null): ?><a class="btn dark" href="<?= $h($courseUrl('classroom', $courseKey)) ?>"><?= $h($t('completed')) ?><svg class="i"><use href="#ai-right"/></svg></a>
<?php elseif (!$showCompletion): ?><span class="btn dark" aria-disabled="true" title="<?= $h($t('finish_step_first')) ?>"><?= $h($t('next_step')) ?><svg class="i"><use href="#ai-right"/></svg></span><?php endif ?>
</div></nav>
<?php endif ?>
