<?php
/**
 * Espace instructeur : rôle expliqué, file de travail unique (queue_table.php : chapitres, qualifications proposées,
 * demandes d'inscription, examens à reprendre, stagiaires sans activité),
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
<a class="button outline" href="<?= $h($link('suivi')) ?>" title="<?= $h($t('ins_tracker_help')) ?>"><?= $h($t('ins_tracker_link')) ?></a>
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

<?php require __DIR__ . '/queue_table.php'; ?>

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
<tr id="eleve-<?= (int) $learner['user_id'] ?>-<?= $h($learner['course_key']) ?>"><td data-label="<?= $h($t('learner')) ?>"><b><?= $h($memberName((int) $learner['user_id'])) ?></b></td><td data-label="<?= $h($t('ins_course')) ?>"><?= $h($titles[$learner['course_key']] ?? '') ?></td>
<td data-label="<?= $h($t('progress')) ?>"><span class="ins-bar" role="img" aria-label="<?= $value ?> %"><i style="width:<?= $value ?>%"></i></span><small><?= $value ?> %</small></td>
<td data-label="<?= $h($t('ins_state')) ?>"><span class="tag s-<?= $state ?>"><?= $h($t($state === 'license' ? 'ins_state_license' : $state)) ?></span><?php if (($vi = $learner['version'] ?? null) !== null): $vKey = (string) $learner['course_key']; $vLearner = (int) $learner['user_id']; require __DIR__ . '/version_badge.php'; endif ?></td>
<td data-label="<?= $h($t('ins_last_activity')) ?>"><?= $h($when($learner['updated_at'] ?? ($item['updated'] ?? null))) ?></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?>
</section>

<section class="ins-sec ins-tools" aria-labelledby="ins-tools"><div class="ins-sec__head"><h2 id="ins-tools"><?= $h($t('ins_shortcuts')) ?></h2></div>
<?php require __DIR__ . '/lms_tools.php'; ?>
</section>
</div>
