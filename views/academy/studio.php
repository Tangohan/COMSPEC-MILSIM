<?php
/**
 * Academy Studio (maquette « Academy Studio ») : structure chapitres/étapes à gauche, édition de l'étape au centre,
 * réglages de l'étape et du cours à droite. Un seul formulaire : chaque action (enregistrer, ajouter un chapitre,
 * importer des diapositives, publier) enregistre d'abord le brouillon, rien n'est perdu entre deux actions.
 */
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyStudio;

require __DIR__ . '/init.php';
$customTopbar = true;
require __DIR__ . '/header.php';
require __DIR__ . '/icons.php';
$stages = AcademyCurriculum::STAGES;
$csrf = \App\Core\Csrf::field();
$noticeClass = static fn (string $notice): string => str_starts_with($notice, 'slides_') && !in_array($notice, ['slides_imported', 'slides_updated'], true) || $notice === 'studio_not_ready' ? 'warn' : 'ok';
?>
<?php if ($course === null): /* Liste des cours de la communauté. */ ?>
<header class="ast-top">
<a class="tb icon" href="<?= $h($link('instructor')) ?>" title="<?= $h($t('instructor')) ?>"><svg class="i"><use href="#ai-back"/></svg><span class="sr-only"><?= $h($t('instructor')) ?></span></a>
<span class="wm">ACADEMY <span>STUDIO</span></span>
<span class="sp"></span>
<nav class="lang" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr', 'en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav>
</header>
<main id="main" class="ast-list">
<?php if ($notice): ?><p class="ast-notice ast-notice--<?= $noticeClass($notice) ?>" role="status"><?= $h($t($notice)) ?></p><?php endif ?>
<div class="ast-list__head"><div><h1><?= $h($t('studio_heading')) ?></h1><p><?= $h($t('studio_intro')) ?></p></div>
<form method="post" action="<?= $h($link('action/studio_new')) ?>"><?= $csrf ?><button class="btn pri"><svg class="i"><use href="#ai-plus"/></svg><?= $h($t('studio_new_course')) ?></button></form></div>
<ul class="ast-cards">
<?php foreach ($studioCourses as $item): $builtIn = !empty($item['built_in']); $published = $builtIn || !empty($item['published']); $title = $local($item['title']) ?: $t('untitled_course'); ?>
<li class="ast-card"><div><span class="pill <?= $published ? 'pb' : 'dr' ?>"><i></i><?= $h($t($builtIn ? 'built_in_module' : ($published ? 'published_course' : 'draft'))) ?></span>
<h2><?= $h($title) ?></h2><p><?= $h($local($item['summary'])) ?></p><small><?= count($item['chapters']) ?> <?= $h($t(count($item['chapters']) === 1 ? 'chapter' : 'chapters')) ?> · <?= count($item['chapters']) * count($stages) ?> <?= $h($t('steps_word')) ?></small></div>
<?php if ($builtIn): ?><form method="post" action="<?= $h($link('action/studio_revise')) ?>"><?= $csrf ?><input type="hidden" name="course" value="<?= $h($item['key']) ?>"><button class="btn"><?= $h($t('studio_copy_builtin')) ?></button></form>
<?php else: ?><a class="btn dark" href="<?= $h($link('studio') . '?course=' . rawurlencode($item['key'])) ?>"><?= $h($t($published ? 'studio_open' : 'studio_edit')) ?></a><?php endif ?></li>
<?php endforeach ?>
</ul>
</main>
<?php else: /* Édition d'un cours. */
$key = (string) $course['key'];
$builtIn = !empty($course['built_in']);
$readOnly = $builtIn || !empty($course['published']);
$chapters = $course['chapters'];
$selC = 0;
$selS = 0;
if (preg_match('/\A(\d{1,2})\.(\d)\z/', (string) ($viewStep ?? ''), $m) && isset($chapters[(int) $m[1]]) && (int) $m[2] < count($stages)) {
    [$selC, $selS] = [(int) $m[1], (int) $m[2]];
}
$issuesByStep = [];
foreach ($issues as $issue) {
    $issuesByStep[($issue['chapter'] ?? 0) . '.' . $issue['stage']][] = $issue;
}
$v = static fn (?array $text, string $language): string => (string) ($text[$language] ?? '');
$stepUrl = static fn (int $c, int $s): string => $link('studio') . '?course=' . rawurlencode($key) . '&step=' . $c . '.' . $s;
$title = $local($course['title']) ?: $t('untitled_course');
$words = preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: ['A'];
$crest = mb_strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : mb_substr($words[0], 1, 1)));
/** Champ bilingue FR / EN côte à côte. */
$bi = static function (string $name, ?array $value, string $label, int $rows = 0, string $help = '') use ($h, $v): void { ?>
<div class="fl full"><span class="lab"><?= $h($label) ?></span><?php if ($help !== ''): ?><span class="h"><?= $h($help) ?></span><?php endif ?><div class="bi">
<?php foreach (['fr', 'en'] as $language): ?><label><span lang="<?= $language ?>"><?= strtoupper($language) ?></span><?php if ($rows > 0): ?><textarea class="in" name="<?= $h($name) ?>[<?= $language ?>]" rows="<?= $rows ?>" maxlength="20000" lang="<?= $language ?>"><?= $h($v($value, $language)) ?></textarea><?php else: ?><input class="in" name="<?= $h($name) ?>[<?= $language ?>]" value="<?= $h($v($value, $language)) ?>" maxlength="2000" lang="<?= $language ?>"><?php endif ?></label><?php endforeach ?>
</div></div>
<?php };
/** Question à trois choix : énoncé, choix (la bonne réponse se coche), explication. */
$questionFields = static function (string $name, ?array $question, string $label) use ($bi, $h, $v, $t): void {
    $question ??= [];
    $answer = (int) ($question['answer'] ?? 0); ?>
<?php $bi($name . '[question]', $question['question'] ?? null, $label, 2); ?>
<fieldset class="fl full ast-options"><legend class="lab"><?= $h($t('studio_options')) ?></legend><span class="h"><?= $h($t('studio_options_help')) ?></span>
<?php for ($i = 0; $i < 3; $i++): ?><div class="opt<?= $answer === $i ? ' ok' : '' ?>"><label class="lt" title="<?= $h($t('answer_label')) ?>"><input type="radio" name="<?= $h($name) ?>[answer]" value="<?= $i ?>"<?= $answer === $i ? ' checked' : '' ?>><span><?= ['A', 'B', 'C'][$i] ?></span><span class="sr-only"><?= $h($t('answer_label')) ?></span></label>
<div class="bi"><?php foreach (['fr', 'en'] as $language): ?><label><span><?= strtoupper($language) ?></span><input class="in" name="<?= $h($name) ?>[options][<?= $i ?>][<?= $language ?>]" value="<?= $h($v($question['options'][$i] ?? null, $language)) ?>" maxlength="2000" lang="<?= $language ?>"></label><?php endforeach ?></div></div><?php endfor ?>
</fieldset>
<?php $bi($name . '[feedback]', $question['feedback'] ?? null, $t('studio_feedback'), 2); ?>
<?php };
$criteriaFields = static function (string $name, array $criteria) use ($h, $v, $t): void { ?>
<div class="fl full"><span class="lab"><?= $h($t('validation_criteria')) ?></span><span class="h"><?= $h($t('studio_criteria_help')) ?></span>
<?php foreach (array_merge($criteria, [null]) as $index => $criterion): ?><div class="bi ast-row"><?php foreach (['fr', 'en'] as $language): ?><label><span><?= strtoupper($language) ?></span><input class="in" name="<?= $h($name) ?>[<?= $index ?>][<?= $language ?>]" value="<?= $h($v($criterion, $language)) ?>" maxlength="2000" placeholder="<?= $criterion === null ? $h($t('studio_add_criterion')) : '' ?>"></label><?php endforeach ?></div><?php endforeach ?>
</div>
<?php };
$stepSummary = static function (array $chapter, int $s) use ($t): string {
    return match ($s) {
        0 => ($pages = array_sum(array_map(static fn (array $r): int => (int) ($r['pages'] ?? 0), $chapter['resources'] ?? []))) > 0 ? $pages . ' ' . $t('slides_count') : $t('sub_theory'),
        2 => $t(isset($chapter['guided']) ? 'studio_custom' : 'studio_reference'),
        3 => $t(isset($chapter['simulation']) ? 'studio_custom' : 'studio_reference'),
        4 => count(AcademyCurriculum::examQuestions($chapter)) . ' ' . $t('questions_label') . ' · ' . (int) ceil(AcademyCurriculum::examSeconds($chapter) / 60) . ' min',
        5 => $t(isset($chapter['debrief']) ? 'studio_custom' : 'studio_default'),
        6 => $t(isset($chapter['validation']) ? 'studio_custom' : 'studio_default'),
        default => $t('sub_demonstration'),
    };
};
$stepCount = count($chapters) * count($stages);
?>
<header class="ast-top">
<a class="tb icon" href="<?= $h($link('studio')) ?>" title="<?= $h($t('studio_all_courses')) ?>"><svg class="i"><use href="#ai-back"/></svg><span class="sr-only"><?= $h($t('studio_all_courses')) ?></span></a>
<span class="wm">ACADEMY <span>STUDIO</span></span><span class="sep" aria-hidden="true"></span>
<div class="ctitle"><span class="cr" aria-hidden="true"><?= $h($crest) ?></span><span><b><?= $h($title) ?></b><small><?= count($chapters) ?> <?= $h($t(count($chapters) === 1 ? 'chapter' : 'chapters')) ?> · <?= $stepCount ?> <?= $h($t('steps_word')) ?></small></span></div>
<span class="pill <?= $readOnly ? 'pb' : 'dr' ?>"><i></i><?= $h($t($builtIn ? 'built_in_module' : ($readOnly ? 'published_course' : (isset($course['replaces']) ? 'studio_new_version' : 'draft')))) ?></span>
<span class="sp"></span>
<?php if (!$readOnly): ?><span class="sv-st" data-save-state data-dirty="<?= $h($t('studio_unsaved')) ?>" aria-live="polite"><?= $h($t('studio_saved')) ?></span><?php endif ?>
<?php if (!$readOnly): ?><details class="ast-issues"><summary class="tb <?= $issues ? 'warn' : 'okc' ?>"><?php if ($issues): ?><svg class="i"><use href="#ai-alert"/></svg><?= count($issues) ?> <?= $h($t(count($issues) === 1 ? 'studio_issue' : 'studio_issues')) ?><?php else: ?>✓ <?= $h($t('studio_ready')) ?><?php endif ?></summary>
<div class="pop"><?php if ($issues): ?><p class="k"><?= $h($t('studio_before_publish')) ?></p><ul><?php foreach ($issues as $issue): $c = (int) ($issue['chapter'] ?? 0); ?><li><a href="<?= $h($stepUrl($c, $issue['stage'])) ?>" data-step-link="<?= $c ?>.<?= $issue['stage'] ?>"><b><?= $issue['chapter'] === null ? $h($t('studio_course_settings')) : $h($t('chapter')) . ' ' . ($c + 1) . ' · ' . $h($t($stages[$issue['stage']])) ?></b><?= $h($t($issue['issue'])) ?></a></li><?php endforeach ?></ul><?php else: ?><p class="okp"><?= $h($t('studio_ready_text')) ?></p><?php endif ?></div></details><?php endif ?>
<?php if (!$builtIn): ?><a class="tb" href="<?= $h($link('classroom') . '?course=' . rawurlencode($key) . '&preview=1') ?>"><svg class="i"><use href="#ai-eye"/></svg><?= $h($t('studio_preview')) ?></a><?php endif ?>
<?php if ($readOnly): ?><button class="tb pri" form="ast-revise"><?= $h($t($builtIn ? 'studio_copy_builtin' : 'studio_revise')) ?></button>
<?php else: ?><button class="tb" form="ast-form"><?= $h($t('studio_save')) ?></button><button class="tb pri" form="ast-form" formaction="<?= $h($link('action/studio_publish')) ?>"<?= $issues ? ' disabled title="' . $h($t('studio_not_ready')) . '"' : '' ?>><?= $h($t('studio_publish')) ?></button><?php endif ?>
</header>
<form id="ast-revise" method="post" action="<?= $h($link('action/studio_revise')) ?>" hidden><?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"></form>
<form id="ast-form" class="ast-form" method="post" action="<?= $h($link('action/studio_save')) ?>" data-studio-form>
<?= $csrf ?><input type="hidden" name="course" value="<?= $h($key) ?>"><input type="hidden" name="step" value="<?= $selC ?>.<?= $selS ?>" data-step-input>
<fieldset class="ast-app" <?= $readOnly ? 'disabled' : '' ?>>
<nav class="col left" aria-label="<?= $h($t('studio_structure')) ?>">
<div class="lh"><span class="k"><?= $h($t('studio_structure')) ?></span><span class="ast-hint"><?= $h($t('studio_reorder_help')) ?></span></div>
<?php foreach ($chapters as $ci => $chapter): ?>
<div class="ch"><div class="chh"><span class="n"><?= $ci + 1 ?></span><b><?= $h($local($chapter['title']) ?: $t('untitled_chapter')) ?></b>
<button class="mini" name="op" value="up:<?= $ci ?>" title="<?= $h($t('studio_move_up')) ?>"<?= $ci === 0 ? ' disabled' : '' ?>><svg class="i"><use href="#ai-up"/></svg><span class="sr-only"><?= $h($t('studio_move_up')) ?></span></button>
<button class="mini" name="op" value="down:<?= $ci ?>" title="<?= $h($t('studio_move_down')) ?>"<?= $ci === count($chapters) - 1 ? ' disabled' : '' ?>><svg class="i"><use href="#ai-down"/></svg><span class="sr-only"><?= $h($t('studio_move_down')) ?></span></button>
<button class="mini dg" name="op" value="remove_chapter:<?= $ci ?>" title="<?= $h($t('remove_chapter')) ?>" data-confirm="<?= $h($t('studio_confirm_remove')) ?>"<?= count($chapters) === 1 ? ' disabled' : '' ?>><svg class="i"><use href="#ai-trash"/></svg><span class="sr-only"><?= $h($t('remove_chapter')) ?></span></button></div>
<ol class="ls"><?php foreach ($stages as $si => $stageName): $hasIssue = isset($issuesByStep[$ci . '.' . $si]) || ($ci === 0 && $si === 0 && isset($issuesByStep['0.0'])); ?>
<li><a class="l<?= $ci === $selC && $si === $selS ? ' on' : '' ?>" href="<?= $h($stepUrl($ci, $si)) ?>" data-step-link="<?= $ci ?>.<?= $si ?>"<?= $ci === $selC && $si === $selS ? ' aria-current="true"' : '' ?>><span class="ic t-<?= $stageName ?>"><svg class="i"><use href="#ai-<?= $stageName ?>"/></svg></span><span><b><?= $h($t($stageName)) ?></b><small><?= $h($stepSummary($chapter, $si)) ?></small></span><?php if ($hasIssue): ?><span class="iss" title="<?= $h($t('studio_issue')) ?>"></span><?php endif ?></a></li>
<?php endforeach ?></ol></div>
<?php endforeach ?>
<button class="addc" name="op" value="add_chapter"<?= count($chapters) >= AcademyStudio::MAX_CHAPTERS ? ' disabled' : '' ?>>+ <?= $h($t('add_chapter')) ?></button>
<div class="lf"><span><?= $stepCount ?> <?= $h($t('steps_word')) ?></span><span><?= $h($t('studio_shortcut')) ?></span></div>
</nav>
<main id="main" class="col center">
<?php if ($notice): ?><p class="ast-notice ast-notice--<?= $noticeClass($notice) ?>" role="status"><?= $h($t($notice)) ?></p><?php endif ?>
<?php if ($readOnly): ?><p class="ast-notice ast-notice--info"><?= $h($t($builtIn ? 'studio_builtin_readonly' : 'studio_published_readonly')) ?></p><?php endif ?>
<?php foreach ($chapters as $ci => $chapter): $base = 'draft[chapters][' . $ci . ']'; ?>
<?php foreach ($stages as $si => $stageName): ?>
<section class="ast-panel" data-step-panel="<?= $ci ?>.<?= $si ?>"<?= $ci === $selC && $si === $selS ? '' : ' hidden' ?> aria-labelledby="ast-h-<?= $ci ?>-<?= $si ?>">
<div class="chd"><span class="ic t-<?= $stageName ?>"><svg class="i"><use href="#ai-<?= $stageName ?>"/></svg></span><div><small><?= $h($t('chapter')) ?> <?= $ci + 1 ?> · <?= $h(mb_strtolower($t('step_label'))) ?> <?= $si + 1 ?> · <?= $h($t($stageName)) ?></small><h1 id="ast-h-<?= $ci ?>-<?= $si ?>"><?= $h($t($stageName)) ?> — <?= $h($local($chapter['title']) ?: $t('untitled_chapter')) ?></h1></div>
<?php if (!$builtIn): ?><a class="btn" href="<?= $h($link('classroom') . '?course=' . rawurlencode($key) . '&preview=1') ?>"><svg class="i"><use href="#ai-eye"/></svg><?= $h($t('studio_preview')) ?></a><?php endif ?></div>
<?php foreach ($issuesByStep[$ci . '.' . $si] ?? [] as $issue): ?><?php if ($issue['chapter'] === null): ?><button type="button" class="ast-notice ast-notice--warn" data-open-course-tab><?= $h($t($issue['issue'])) ?> · <u><?= $h($t('studio_course_settings')) ?></u></button><?php else: ?><p class="ast-notice ast-notice--warn"><?= $h($t($issue['issue'])) ?></p><?php endif ?><?php endforeach ?>
<div class="form">
<?php if ($si === 0): ?>
<?php foreach ($chapter['resources'] ?? [] as $ri => $resource): if (!isset($resource['file'])) continue; $mediaUrl = $link('media/' . rawurlencode((string) $resource['file'])); ?>
<div class="fc ast-deck"><h3><span><svg class="i"><use href="#ai-theory"/></svg> <?= $h($t('studio_slides')) ?></span><button class="ib dg" name="op" value="remove_resource:<?= $ci ?>:<?= $ri ?>" data-confirm="<?= $h($t('studio_confirm_remove_slides')) ?>"><svg class="i"><use href="#ai-trash"/></svg><?= $h($t('remove_resource')) ?></button></h3>
<p><?= (int) ($resource['pages'] ?? 0) ?> <?= $h($t('slides_count')) ?><?php if (!empty($resource['imported_at'])): ?> · <?= $h($t('studio_imported_on')) ?> <?= $h((new DateTimeImmutable('@' . (int) $resource['imported_at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i')) ?><?php endif ?> · <a href="<?= $h($mediaUrl) ?>" target="_blank" rel="noopener"><?= $h($t('open_slides')) ?></a></p>
<input type="hidden" name="<?= $h($base) ?>[resources][<?= $ri ?>][file]" value="<?= $h($resource['file']) ?>">
<?php $bi($base . '[resources][' . $ri . '][title]', $resource['title'], $t('resource_title')); ?>
<?php if (!empty($resource['source'])): ?><div class="irow"><button class="ib" name="reimport_url" value="<?= $h($resource['source']) ?>" formaction="<?= $h($link('action/studio_import')) ?>"><svg class="i"><use href="#ai-refresh"/></svg><?= $h($t('studio_reimport')) ?></button><a class="ib" href="<?= $h($resource['source']) ?>" target="_blank" rel="noopener noreferrer"><svg class="i"><use href="#ai-link"/></svg><?= $h($t('studio_open_google')) ?></a></div><?php endif ?>
</div>
<?php endforeach ?>
<div class="fc"><h3><?= $h($t('studio_lesson')) ?></h3><p><?= $h($t('studio_theory_help')) ?></p><div class="fg">
<?php $bi($base . '[title]', $chapter['title'], $t('studio_chapter_title')); ?>
<?php $bi($base . '[theory]', $chapter['theory'], $t('theory'), 12); ?>
</div></div>
<div class="fc"><h3><?= $h($t('resources_label')) ?></h3><p><?= $h($t('studio_resources_help')) ?></p>
<?php $links = array_filter($chapter['resources'] ?? [], static fn (array $r): bool => !isset($r['file'])); $nextIndex = count($chapter['resources'] ?? []);
foreach ($links + [$nextIndex => null] as $ri => $resource): ?>
<div class="ast-resource"><div class="fg">
<label class="fl"><span class="lab"><?= $h($t('resource_type')) ?></span><select class="in" name="<?= $h($base) ?>[resources][<?= $ri ?>][type]"><?php foreach (['document', 'slides', 'diagram', 'audio'] as $type): ?><option value="<?= $type ?>"<?= ($resource['type'] ?? 'document') === $type ? ' selected' : '' ?>><?= $h($t($type)) ?></option><?php endforeach ?></select></label>
<?php if (isset($resource['body'])): ?><p class="fl h"><?= $h($t('studio_inline_resource')) ?></p><?php foreach (['fr', 'en'] as $language): ?><input type="hidden" name="<?= $h($base) ?>[resources][<?= $ri ?>][body][<?= $language ?>]" value="<?= $h($v($resource['body'], $language)) ?>"><?php endforeach ?><?php endif ?>
<?php $bi($base . '[resources][' . $ri . '][title]', $resource['title'] ?? null, $t('resource_title')); ?>
<?php if (!isset($resource['body'])) { $bi($base . '[resources][' . $ri . '][url]', $resource['url'] ?? null, $t('resource_url')); } ?>
</div><?php if ($resource !== null): ?><button class="ib dg" name="op" value="remove_resource:<?= $ci ?>:<?= $ri ?>"><svg class="i"><use href="#ai-trash"/></svg><?= $h($t('remove_resource')) ?></button><?php endif ?></div>
<?php endforeach ?>
</div>
<?php elseif ($si === 1): ?>
<div class="fc"><h3><?= $h($t('demonstration')) ?></h3><p><?= $h($t('studio_demo_help')) ?></p><div class="fg"><?php $bi($base . '[demonstration]', $chapter['demonstration'], $t('demonstration'), 12); ?></div></div>
<?php elseif ($si === 2): ?>
<div class="fc"><h3><?= $h($t('studio_reference_question')) ?></h3><p><?= $h($t('studio_reference_help')) ?></p><div class="fg"><?php $questionFields($base, $chapter, $t('question_label')); ?></div></div>
<details class="fc ast-optional"<?= isset($chapter['guided']) ? ' open' : '' ?>><summary><h3><?= $h($t('studio_custom_guided')) ?></h3><span><?= $h($t('studio_optional')) ?></span></summary><p><?= $h($t('studio_guided_help')) ?></p><div class="fg">
<?php $bi($base . '[guided][brief]', $chapter['guided']['brief'] ?? null, $t('studio_case_brief'), 4); ?>
<?php $bi($base . '[guided][hint]', $chapter['guided']['hint'] ?? null, $t('hint_title'), 2); ?>
<?php $questionFields($base . '[guided]', $chapter['guided'] ?? null, $t('question_label')); ?>
</div></details>
<?php elseif ($si === 3): ?>
<div class="fc"><h3><?= $h($t('simulation')) ?></h3><p><?= $h($t('studio_simulation_help')) ?></p><div class="fg">
<?php $bi($base . '[simulation][brief]', $chapter['simulation']['brief'] ?? null, $t('studio_case_brief'), 4); ?>
<?php $questionFields($base . '[simulation]', $chapter['simulation'] ?? null, $t('question_label')); ?>
</div></div>
<?php elseif ($si === 4): $assessment = $chapter['assessment'] ?? null; ?>
<div class="fc"><h3><?= $h($t('assessment')) ?></h3><p><?= $h($t('studio_assessment_help')) ?></p><div class="fg">
<?php $bi($base . '[assessment][instructions]', $assessment['instructions'] ?? null, $t('studio_instructions'), 3); ?>
<label class="fl"><span class="lab"><?= $h($t('studio_minutes')) ?></span><input class="in" type="number" min="1" max="120" name="<?= $h($base) ?>[assessment][minutes]" value="<?= (int) ceil(($assessment['seconds'] ?? 300) / 60) ?>"></label>
<label class="fl"><span class="lab"><?= $h($t('passing_score')) ?> (%)</span><input class="in" type="number" min="1" max="100" name="<?= $h($base) ?>[assessment][pass_percent]" value="<?= (int) ($assessment['pass_percent'] ?? 80) ?>"></label>
</div></div>
<?php foreach (array_merge($assessment['questions'] ?? [], [null]) as $qi => $question): ?>
<div class="fc qc"><h3><span><?= $h($t('questions_label')) ?> <?= $qi + 1 ?><?= $question === null ? ' · ' . $h($t('studio_new_question')) : '' ?></span><?php if ($question !== null): ?><button class="ib dg" name="op" value="remove_question:<?= $ci ?>:<?= $qi ?>"><svg class="i"><use href="#ai-trash"/></svg><?= $h($t('studio_remove_question')) ?></button><?php endif ?></h3><div class="fg"><?php $questionFields($base . '[assessment][questions][' . $qi . ']', $question, $t('question_label')); ?></div></div>
<?php endforeach ?>
<?php elseif ($si === 5): ?>
<div class="fc"><h3><?= $h($t('debrief')) ?></h3><p><?= $h($t('studio_debrief_help')) ?></p><div class="fg">
<?php $bi($base . '[debrief][prompt]', $chapter['debrief']['prompt'] ?? null, $t('studio_debrief_prompt'), 4); ?>
<?php $criteriaFields($base . '[debrief][criteria]', $chapter['debrief']['criteria'] ?? []); ?>
</div></div>
<?php else: ?>
<div class="fc"><h3><?= $h($t('validation')) ?></h3><p><?= $h($t('studio_validation_help')) ?></p><div class="fg">
<?php $bi($base . '[validation][brief]', $chapter['validation']['brief'] ?? null, $t('studio_validation_brief'), 4); ?>
<?php $criteriaFields($base . '[validation][criteria]', $chapter['validation']['criteria'] ?? []); ?>
</div></div>
<?php endif ?>
</div>
</section>
<?php endforeach; endforeach ?>
</main>
<aside class="col right" aria-label="<?= $h($t('studio_inspector')) ?>">
<div class="itabs" role="tablist"><button type="button" role="tab" aria-selected="true" data-itab="step"><?= $h($t('studio_tab_step')) ?></button><button type="button" role="tab" aria-selected="false" data-itab="course"><?= $h($t('studio_tab_course')) ?></button></div>
<div data-ipanel="step" role="tabpanel">
<div class="isec ast-import"><p class="k"><?= $h($t('studio_import_title')) ?></p>
<p class="h"><?= $h($t('studio_import_help')) ?></p>
<label class="fl"><span class="lab"><?= $h($t('studio_import_link')) ?></span><input class="in" type="url" name="slides_url" inputmode="url" maxlength="512" placeholder="https://docs.google.com/presentation/d/…" autocomplete="off"></label>
<label class="fl"><span class="lab"><?= $h($t('studio_import_target')) ?></span><select class="in" name="slides_target"><option value="new"><?= $h($t('studio_import_new_chapter')) ?></option><?php foreach ($chapters as $ci => $chapter): ?><option value="<?= $ci ?>"<?= $ci === $selC ? ' data-selected-chapter' : '' ?>><?= $h($t('chapter')) ?> <?= $ci + 1 ?> · <?= $h($t('theory')) ?></option><?php endforeach ?></select></label>
<button class="btn dark" formaction="<?= $h($link('action/studio_import')) ?>"><?= $h($t('studio_import_button')) ?></button>
<p class="h"><?= $h($t('studio_import_limits')) ?></p>
</div>
<div class="isec"><p class="k"><?= $h($t('studio_learner_view')) ?></p><?php foreach ($stages as $si => $stageName): ?><p class="h" data-step-tip="<?= $si ?>"<?= $si === $selS ? '' : ' hidden' ?>><?= $h($t('studio_tip_' . $stageName)) ?></p><?php endforeach ?></div>
</div>
<div data-ipanel="course" role="tabpanel" hidden>
<div class="isec"><p class="k"><?= $h($t('studio_course_settings')) ?></p>
<?php $bi('draft[title]', $course['title'], $t('title_label')); ?>
<?php $bi('draft[summary]', $course['summary'], $t('summary_label'), 3); ?>
<?php $bi('draft[license]', $course['license'] ?? null, $t('studio_license'), 2, $t('studio_license_help')); ?>
<div class="fg"><label class="fl"><span class="lab"><?= $h($t('course_version')) ?></span><input class="in" name="draft[version]" value="<?= $h($course['version'] ?? '') ?>" maxlength="32" pattern="[A-Za-z0-9._-]{1,32}"></label>
<label class="fl"><span class="lab"><?= $h($t('estimated_duration')) ?> (min)</span><input class="in" type="number" min="5" max="1440" name="draft[duration_minutes]" value="<?= isset($course['duration_minutes']) ? (int) $course['duration_minutes'] : '' ?>"></label></div>
</div>
<div class="isec"><p class="k"><?= $h($t('studio_cover')) ?></p><p class="h"><?= $h($t('studio_cover_help')) ?></p><div class="imgs"><?php foreach (AcademyStudio::COVERS as $cover): ?><label style="--img:url('<?= $h(asset_url('assets/images/academy/cover-' . $cover . '.webp')) ?>')"><input type="radio" name="draft[cover]" value="<?= $cover ?>"<?= ($course['cover'] ?? 'night') === $cover ? ' checked' : '' ?>><span class="sr-only"><?= $h($t('studio_cover_' . $cover)) ?></span></label><?php endforeach ?></div></div>
</div>
</aside>
</fieldset>
</form>
<?php endif ?>
<?php require __DIR__ . '/disclaimer.php'; ?>
</body></html>
