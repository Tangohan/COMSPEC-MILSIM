<?php
/**
 * Espace instructeur : rôle expliqué, « À traiter » (débriefings à valider, examens à reprendre, demandes d'inscription),
 * cours encadrés et séances, élèves et leur progression, raccourcis Studio / Salle de cours / Droits d'accès.
 * Données préparées par AcademyController::instructorData() ; chaque formulaire est revérifié côté serveur.
 */
$instructor = $instructor ?? [];
$capabilities = \App\Services\Academy\AcademyRights::CAPABILITIES;
$can = ($instructor['can'] ?? []) + array_fill_keys($capabilities, (bool) ($staff ?? false));
$members = $instructor['members'] ?? [];
$managed = $instructor['managed'] ?? $courses;
$courseSettings = $instructor['settings'] ?? [];
$sessions = $instructor['sessions'] ?? [];
$pendingRequests = (int) ($instructor['pendingRequests'] ?? 0);
$assignable = $instructor['assignable'] ?? [];
$teach = $instructor['teach'] ?? null;
$me = (int) \App\Core\Session::get('user_id');
$memberName = static fn (int $id): string => $members[$id] ?? sprintf($t('ins_member_number'), $id);
$when = static fn (int|string|null $at): string => $at ? (new DateTimeImmutable(is_int($at) ? '@' . $at : (string) $at))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i') : '—';
$titles = [];
foreach (array_merge($managed, $courses) as $course) {
    $titles[$course['key']] = $local($course['title']) ?: $t('untitled_course');
}

$toValidate = $examFollowUp = $stalled = [];
$perCourse = [];
foreach ($learners as $learner) {
    $item = $learner['state'];
    $key = (string) $learner['course_key'];
    $perCourse[$key] ??= ['learners' => 0, 'completed' => 0, 'pending' => 0];
    $perCourse[$key]['learners']++;
    $titles[$key] ??= $local($item['snapshot']['title'] ?? ['fr' => '', 'en' => '']) ?: $t('untitled_course');
    if (!empty($item['completed'])) {
        $perCourse[$key]['completed']++;
        continue;
    }
    if ((int) ($item['stage'] ?? 0) === 6) {
        $perCourse[$key]['pending']++;
        $toValidate[] = $learner;
        continue;
    }
    $attempts = $item['attempts'] ?? [];
    $last = $attempts !== [] ? end($attempts) : null;
    if ((int) ($item['stage'] ?? 0) === 4 && empty($item['exam']) && is_array($last) && (int) ($last['chapter'] ?? -1) === (int) ($item['chapter'] ?? 0) && empty($last['passed'])) {
        $examFollowUp[] = $learner + ['attempt' => $last];
    }
    $updated = strtotime((string) ($learner['updated_at'] ?? '')) ?: (int) ($item['updated'] ?? 0);
    if ($updated > 0 && $updated < time() - 14 * 86400) {
        $stalled[] = $learner;
    }
}
$todoCount = count($toValidate) + count($examFollowUp) + ($pendingRequests > 0 ? 1 : 0);
$statusOf = static function (array $course): string {
    if (!empty($course['built_in'])) return 'builtin';
    if (!empty($course['archived'])) return 'archived';
    return !empty($course['published']) ? 'pub' : 'draft';
};
$audienceOf = static function (array $course) use ($courseSettings): string {
    $settings = $courseSettings[$course['key']] ?? ['audience' => 'community', 'private_draft' => false];
    if (empty($course['published']) && empty($course['built_in']) && !empty($settings['private_draft'])) return 'private';
    return ($settings['audience'] ?? 'community') === 'restricted' ? 'restricted' : 'community';
};
?>
<div class="ins">
<section class="ins-hero" aria-labelledby="ins-title">
<div class="ins-hero__text"><p class="eyebrow"><?= $h($t('instructor')) ?></p>
<h1 id="ins-title"><?= $h($t('ins_heading')) ?></h1>
<p><?= $h($t('ins_role_text')) ?></p>
<ul class="ins-chips" aria-label="<?= $h($t('ins_my_rights')) ?>"><?php foreach ($capabilities as $capability): ?><li class="<?= $can[$capability] ? 'on' : 'off' ?>"><span aria-hidden="true"><?= $can[$capability] ? '✓' : '–' ?></span><?= $h($t('cap_' . $capability)) ?><span class="sr-only"> · <?= $h($t($can[$capability] ? 'ins_allowed' : 'ins_not_allowed')) ?></span></li><?php endforeach ?></ul>
</div>
<nav class="ins-hero__actions" aria-label="<?= $h($t('ins_shortcuts')) ?>">
<?php if ($instructor['entersStudio'] ?? $staff): ?><a class="button" href="<?= $h($link('studio')) ?>"><?= $h($t('open_studio')) ?> ↗</a><?php endif ?>
<a class="button outline" href="<?= $h($link('classroom')) ?>"><?= $h($t('classroom')) ?></a>
<?php if (!empty($instructor['manageRights'])): ?><a class="button outline" href="<?= $h($link('droits')) ?>"><?= $h($t('rights_open')) ?></a><?php endif ?>
</nav>
</section>

<dl class="ins-stats">
<div><dt><?= $h($t('ins_stat_validate')) ?></dt><dd class="<?= $toValidate ? 'hot' : '' ?>"><?= count($toValidate) ?></dd></div>
<div><dt><?= $h($t('ins_stat_exams')) ?></dt><dd><?= count($examFollowUp) ?></dd></div>
<div><dt><?= $h($t('ins_stat_learners')) ?></dt><dd><?= count($learners) ?></dd></div>
<div><dt><?= $h($t('ins_stat_courses')) ?></dt><dd><?= count($managed) ?></dd></div>
</dl>

<section class="ins-sec" id="a-traiter" aria-labelledby="ins-todo"><div class="ins-sec__head"><h2 id="ins-todo"><?= $h($t('ins_todo')) ?></h2><span class="pill<?= $todoCount ? ' accent' : '' ?>"><?= $todoCount ?></span></div>
<?php if ($todoCount === 0): ?>
<div class="ins-empty"><b><?= $h($t('ins_todo_empty')) ?></b><p><?= $h($t('ins_todo_empty_help')) ?></p></div>
<?php endif ?>
<?php $reviewIndex = 0; foreach ($toValidate as $learner): $item = $learner['state']; $reviewIndex++;
    $reviewState = $item; $reviewChapter = $item['snapshot']['chapters'][$item['chapter']] ?? null; if ($reviewChapter === null) continue;
    $final = (int) $item['chapter'] + 1 >= count($item['snapshot']['chapters']);
    $own = (int) $learner['user_id'] === $me;
    $canHere = $teach === null ? $can['validate'] : (isset($teach[$learner['course_key']]) || $can['validate']); ?>
<article class="ins-task" aria-labelledby="task-<?= $reviewIndex ?>">
<header><span class="pill accent"><?= $h($t($final ? 'ins_task_final' : 'ins_task_debrief')) ?></span>
<h3 id="task-<?= $reviewIndex ?>"><?= $h($memberName((int) $learner['user_id'])) ?> · <?= $h($titles[$learner['course_key']] ?? '') ?></h3>
<p><?= $h($t('chapter')) ?> <?= (int) $item['chapter'] + 1 ?> · <?= $h($local($reviewChapter['title'])) ?> · <?= $h(sprintf($t('ins_sent_on'), $when($learner['updated_at'] ?? ($item['updated'] ?? null)))) ?></p></header>
<details class="ins-review"<?= $reviewIndex === 1 ? ' open' : '' ?>><summary><?= $h($t('ins_open_review')) ?></summary>
<?php require __DIR__ . '/course_review.php'; ?>
<h4><?= $h($t('submitted_debrief')) ?></h4><p class="lesson-text"><?= nl2br($h($item['debriefs'][$item['chapter']] ?? '')) ?></p>
<?php if (!empty($reviewChapter['validation']['brief'])): ?><p><?= nl2br($h($local($reviewChapter['validation']['brief']))) ?></p><?php endif ?>
<?php if (!empty($reviewChapter['validation']['criteria'])): ?><h4><?= $h($t('validation_criteria')) ?></h4><ul class="academy-rubric"><?php foreach ($reviewChapter['validation']['criteria'] as $criterion): ?><li><?= $h($local($criterion)) ?></li><?php endforeach ?></ul><?php endif ?>
<?php if ($own): ?><p class="hint"><?= $h($t('self_validation')) ?></p>
<?php elseif (!$canHere): ?><p class="hint"><?= $h($t('ins_cannot_validate')) ?></p>
<?php elseif ($final && !$can['certify']): ?><p class="hint"><?= $h($t('ins_final_needs_certify')) ?></p>
<?php else: $formStart('validate', $learner['course_key']); ?><input type="hidden" name="learner" value="<?= (int) $learner['user_id'] ?>"><input type="hidden" name="expected_chapter" value="<?= (int) $item['chapter'] ?>">
<label for="comment-<?= $reviewIndex ?>"><?= $h($t('comment')) ?></label><textarea id="comment-<?= $reviewIndex ?>" name="comment" rows="5" minlength="10" maxlength="5000" required></textarea>
<?php if ($final): ?><p class="hint"><?= $h($t('ins_final_note')) ?></p><?php endif ?>
<button class="button"><?= $h($t($final ? 'ins_validate_final' : 'validate')) ?> ✓</button></form><?php endif ?>
</details></article>
<?php endforeach ?>
<?php if ($examFollowUp): ?><div class="ins-list"><h3><?= $h($t('ins_exam_followup')) ?></h3><p class="muted"><?= $h($t('ins_exam_followup_help')) ?></p><ul>
<?php foreach ($examFollowUp as $learner): $attempt = $learner['attempt']; ?><li><b><?= $h($memberName((int) $learner['user_id'])) ?></b><span><?= $h($titles[$learner['course_key']] ?? '') ?> · <?= $h($t('chapter')) ?> <?= (int) $attempt['chapter'] + 1 ?></span><span class="pill"><?= $h(!empty($attempt['expired']) ? $t('ins_exam_expired') : sprintf($t('ins_exam_score'), (int) ($attempt['score'] ?? 0))) ?></span><small><?= $h($when($attempt['at'] ?? null)) ?></small></li><?php endforeach ?>
</ul></div><?php endif ?>
<?php if ($pendingRequests > 0): ?><div class="ins-list"><h3><?= $h($t('ins_requests')) ?></h3><p class="muted"><?= $h(sprintf($t($pendingRequests === 1 ? 'ins_requests_one' : 'ins_requests_many'), $pendingRequests)) ?></p><a class="text-link" href="<?= $h(url('formation/enrollments')) ?>"><?= $h($t('ins_requests_open')) ?> ↗</a></div><?php endif ?>
</section>

<div class="ins-cols">
<section class="ins-sec" id="mes-cours" aria-labelledby="ins-courses"><div class="ins-sec__head"><h2 id="ins-courses"><?= $h($t('ins_my_courses')) ?></h2><?php if ($can['create']): ?><a class="text-link" href="<?= $h($link('studio')) ?>">+ <?= $h($t('studio_new_course')) ?></a><?php endif ?></div>
<?php if ($managed === []): ?><div class="ins-empty"><b><?= $h($t('ins_courses_empty')) ?></b><p><?= $h($t($can['create'] ? 'ins_courses_empty_create' : 'ins_courses_empty_help')) ?></p></div>
<?php else: ?><ul class="ins-courses">
<?php foreach ($managed as $course): $status = $statusOf($course); $audience = $audienceOf($course); $stats = $perCourse[$course['key']] ?? ['learners' => (int) ($course['learners'] ?? 0), 'completed' => 0, 'pending' => 0]; ?>
<li><div class="ins-course__main"><b><?= $h($titles[$course['key']] ?? '') ?></b>
<span class="ins-tags"><span class="tag t-<?= $status ?>"><?= $h($t('ins_status_' . $status)) ?></span><span class="tag a-<?= $audience ?>"><?= $h($t('audience_' . $audience)) ?></span></span></div>
<span class="ins-course__nums"><?= (int) $stats['learners'] ?> <?= $h($t((int) $stats['learners'] === 1 ? 'learner_one' : 'learner_many')) ?><?php if ($stats['pending']): ?> · <em><?= (int) $stats['pending'] ?> <?= $h($t('ins_to_validate_short')) ?></em><?php endif ?></span>
<span class="ins-course__actions"><?php if (empty($course['built_in'])): ?><a class="text-link" href="<?= $h($link('studio') . '?course=' . rawurlencode($course['key'])) ?>"><?= $h($t('edit_in_studio')) ?></a><?php endif ?><a class="text-link" href="<?= $h($link('classroom') . '?course=' . rawurlencode($course['key']) . '&preview=1') ?>"><?= $h($t('learner_preview')) ?></a></span></li>
<?php endforeach ?></ul><?php endif ?>
</section>

<section class="ins-sec" id="seances" aria-labelledby="ins-sessions"><div class="ins-sec__head"><h2 id="ins-sessions"><?= $h($t('ins_sessions')) ?></h2></div>
<?php if ($sessions === []): ?><div class="ins-empty"><b><?= $h($t('ins_sessions_empty')) ?></b><p><?= $h($t('ins_sessions_empty_help')) ?></p><a class="text-link" href="<?= $h(url('academy/formations/sessions')) ?>"><?= $h($t('sessions_tool')) ?> ↗</a></div>
<?php else: ?><ul class="ins-sessions"><?php foreach ($sessions as $session): ?><li><time datetime="<?= $h($session['starts_at']) ?>"><?= $h($when($session['starts_at'])) ?></time><b><?= $h($session['label'] !== '' ? $session['label'] : $session['title']) ?></b><span><?= $h($session['title']) ?><?= $session['location'] !== '' ? ' · ' . $h($session['location']) : '' ?></span><?php if ($session['slug'] !== ''): ?><a class="text-link" href="<?= $h(url('formations/' . rawurlencode($session['slug']))) ?>"><?= $h($t('open')) ?></a><?php endif ?></li><?php endforeach ?></ul><?php endif ?>
</section>
</div>

<section class="ins-sec" id="eleves" aria-labelledby="ins-learners"><div class="ins-sec__head"><h2 id="ins-learners"><?= $h($t('ins_learners')) ?></h2><?php if ($stalled): ?><span class="pill"><?= $h(sprintf($t('ins_stalled'), count($stalled))) ?></span><?php endif ?></div>
<p class="muted"><?= $h($t(($can['stats'] || $can['validate']) ? 'ins_learners_all' : 'ins_learners_mine')) ?></p>
<?php if ($can['assign'] && $assignable !== [] && $members !== []): ?>
<details class="ins-assign"><summary><?= $h($t('ins_assign')) ?></summary>
<form method="post" action="<?= $h($link('action/assign')) ?>"><?= \App\Core\Csrf::field() ?>
<label><span><?= $h($t('ins_assign_member')) ?></span><select name="member" required><option value=""><?= $h($t('share_choose_member')) ?></option><?php foreach ($members as $id => $name): ?><option value="<?= (int) $id ?>"><?= $h($name) ?></option><?php endforeach ?></select></label>
<label><span><?= $h($t('ins_assign_course')) ?></span><select name="course" required><?php foreach ($assignable as $course): ?><option value="<?= $h($course['key']) ?>"><?= $h($local($course['title'])) ?></option><?php endforeach ?></select></label>
<button class="button small"><?= $h($t('ins_assign_submit')) ?></button></form>
<p class="hint"><?= $h($t('ins_assign_help')) ?></p></details>
<?php endif ?>
<?php if ($learners === []): ?><div class="ins-empty"><b><?= $h($t('ins_learners_empty')) ?></b><p><?= $h($t('ins_learners_empty_help')) ?></p></div>
<?php else: ?><div class="table-scroll"><table class="ins-table"><thead><tr><th scope="col"><?= $h($t('learner')) ?></th><th scope="col"><?= $h($t('ins_course')) ?></th><th scope="col"><?= $h($t('progress')) ?></th><th scope="col"><?= $h($t('ins_state')) ?></th><th scope="col"><?= $h($t('ins_last_activity')) ?></th></tr></thead><tbody>
<?php foreach ($learners as $learner): $item = $learner['state']; $value = $percent($item);
    $state = !empty($item['license']) ? 'license' : (!empty($item['completed']) ? 'completed' : ((int) ($item['stage'] ?? 0) === 6 ? 'pending' : 'in_progress')); ?>
<tr><td data-label="<?= $h($t('learner')) ?>"><b><?= $h($memberName((int) $learner['user_id'])) ?></b></td><td data-label="<?= $h($t('ins_course')) ?>"><?= $h($titles[$learner['course_key']] ?? '') ?></td>
<td data-label="<?= $h($t('progress')) ?>"><span class="ins-bar" role="img" aria-label="<?= $value ?> %"><i style="width:<?= $value ?>%"></i></span><small><?= $value ?> %</small></td>
<td data-label="<?= $h($t('ins_state')) ?>"><span class="tag s-<?= $state ?>"><?= $h($t($state === 'license' ? 'ins_state_license' : $state)) ?></span></td>
<td data-label="<?= $h($t('ins_last_activity')) ?>"><?= $h($when($learner['updated_at'] ?? ($item['updated'] ?? null))) ?></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?>
</section>

<section class="ins-sec ins-tools" aria-labelledby="ins-tools"><div class="ins-sec__head"><h2 id="ins-tools"><?= $h($t('ins_shortcuts')) ?></h2></div>
<?php require __DIR__ . '/lms_tools.php'; ?>
</section>
</div>
