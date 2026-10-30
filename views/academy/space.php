<?php
require __DIR__ . '/init.php';
require __DIR__ . '/header.php';
$legacyCourses = $legacyCourses ?? [];
$legacyEnrollments = $legacyEnrollments ?? [];
$academyStorageReady = $academyStorageReady ?? true;
$states = $states ?? [];
$courses = $courses ?? [];
$learning = array_filter($states, static fn ($key): bool => $key !== '__notes', ARRAY_FILTER_USE_KEY);
$validCount = array_sum(array_map(static fn (array $item): int => count($item['validations'] ?? []), $learning));
$attemptCount = array_sum(array_map(static fn (array $item): int => count($item['attempts'] ?? []), $learning));
$percent = static function (array $item): int {
    if (!empty($item['completed'])) return 100;
    $total = max(1, count($item['snapshot']['chapters'] ?? [])) * 7;
    return (int) floor(((int) ($item['chapter'] ?? 0) * 7 + (int) ($item['stage'] ?? 0)) / $total * 100);
};
$courseUrl = static fn (string $area, string $key): string => $link($area) . '?course=' . rawurlencode($key);
$formStart = static function (string $action, string $key) use ($h, $link, $state): void { ?>
<form method="post" action="<?= $h($link('action/' . $action)) ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="course" value="<?= $h($key) ?>"><?php if ($state !== null && in_array($action,['advance','practice','exam_start','exam_submit','debrief'],true)): ?><input type="hidden" name="expected_stage" value="<?= (int)$state['stage'] ?>"><input type="hidden" name="expected_chapter" value="<?= (int)$state['chapter'] ?>"><?php endif ?>
<?php };
?>
<div class="app-shell">
<?php
$academyIcons = ['dashboard'=>'overview','catalogue'=>'catalogue','courses'=>'mine','classroom'=>'docs','laboratory'=>'studio','simulators'=>'star','exams'=>'calendar','library'=>'docs','notebook'=>'feedback','record'=>'cert','instructor'=>'groups'];
$academyNavItems = [];
foreach ($allSpaces as $item) {
    if ($item === 'instructor' && !$staff) continue;
    $academyNavItems[] = ['href'=>$link($item), 'label'=>$t($item), 'icon'=>$academyIcons[$item], 'active'=>$space===$item];
}
$lmsNav = ['id'=>'academy','tone'=>'emerald','kicker'=>'ATHENA · Academy','title'=>$t('brand'),'lead'=>$t('tagline'),'aria'=>$t('brand'),'sections'=>[['label'=>$t('welcome'),'items'=>$academyNavItems]],'foot'=>['href'=>url('dashboard'),'label'=>$t('back_athena')]];
(static function (array $lmsNav): void { require base_path('views/partials/lms_nav_aside.php'); })($lmsNav);
?>
<main id="main" class="app-main"><div class="breadcrumb"><a href="<?= $h($link()) ?>">ATHENA ACADEMY</a><span>/</span><?= $h($t($space)) ?></div>
<?php if ($notice): ?><div class="notice" role="status"><?= $h($t($notice)) ?></div><?php \App\Core\Session::set('academy_notice', ''); endif ?>
<?php if (!$academyStorageReady): ?><p class="notice"><?= $h($t('lms_storage_notice')) ?></p><?php endif ?>
<?php if ($space === 'dashboard'): ?>
<section class="academy-workspace-heading"><p class="eyebrow"><?= $h($t('welcome')) ?></p><h1><?= $h($t('dashboard_heading')) ?></h1><p><?= $h($t('academy_learning')) ?></p>
<?php $resumeCourse=null; foreach($learning as $key=>$item){if(empty($item['completed'])){$resumeCourse=$key;break;}} ?>
<a class="button" href="<?= $h($resumeCourse!==null?$courseUrl('classroom',$resumeCourse):$link('catalogue')) ?>"><?= $h($t($resumeCourse!==null?'continue':'catalogue')) ?> ↗</a></section>
<div class="academy-live-stats"><span><strong><?= count($legacyCourses)+count($courses) ?></strong><?= $h($t('published_courses')) ?></span><span><strong><?= count($legacyEnrollments)+count($learning) ?></strong><?= $h($t('enrolled')) ?></span><span><strong><?= count(array_filter($legacyEnrollments,static fn(array $entry):bool=>($entry['status']??'')==='completed'))+count(array_filter($learning,static fn(array $item):bool=>!empty($item['completed']))) ?></strong><?= $h($t('completed')) ?></span></div>
<h2><?= $h($t('academy_curricula')) ?></h2><?php $displayCourses=$courses; require __DIR__.'/course_list.php'; ?>
<?php if($legacyCourses!==[]): ?><h2><?= $h($t('your_community_courses')) ?></h2><?php require __DIR__.'/native_course_list.php'; endif ?>
<?php require __DIR__.'/lms_tools.php'; ?>
<?php elseif (in_array($space,['catalogue','courses'],true)): ?>
<div class="page-heading"><p class="eyebrow">ATHENA / <?= $h($t($space)) ?></p><h1><?= $h($t($space)) ?></h1><p><?= $h($t('academy_learning')) ?></p></div>
<label class="search"><span>⌕</span><input type="search" data-course-search placeholder="<?= $h($t('search')) ?>" aria-label="<?= $h($t('search')) ?>"></label>
<?php $displayCourses=$space==='catalogue'?$courses:array_map(static fn(array $item):array=>$item['snapshot'],array_values($learning)); ?>
<?php if($displayCourses!==[]): ?><h2><?= $h($t('academy_curricula')) ?></h2><?php require __DIR__.'/course_list.php'; endif ?>
<?php if(($space==='courses'?$legacyEnrollments:$legacyCourses)!==[]): ?><h2><?= $h($t('your_community_courses')) ?></h2><?php require __DIR__.'/native_course_list.php'; endif ?>
<?php if($displayCourses===[]&&($space==='courses'?$legacyEnrollments:$legacyCourses)===[]): ?><p><?= $h($t($space==='courses'?'empty_text':'no_published_courses')) ?></p><?php endif ?>
<p data-search-empty hidden role="status"><?= $h($t('search_empty')) ?></p>
<?php elseif(in_array($space,['classroom','laboratory','exams','simulators'],true)): ?>
<div class="page-heading"><p class="eyebrow">ATHENA / <?= $h($t($space)) ?></p><h1><?= $h($t($space)) ?></h1><p><?= $h($t($space.'_desc')) ?></p></div>
<?php require __DIR__.'/course_player.php'; ?>
<?php elseif ($space==='notebook'): ?>
<div class="page-heading"><p class="eyebrow">ATHENA / <?= $h($t('notebook')) ?></p><h1><?= $h($t('notebook')) ?></h1><p><?= $h($t('notes_help')) ?></p></div><section class="panel notebook"><?php $formStart('notes','academy'); ?><label for="notes"><?= $h($t('notes_label')) ?></label><textarea id="notes" name="notes" rows="16" maxlength="30000"><?= $h($states['__notes']['text']??'') ?></textarea><div class="buttons"><button class="button"><?= $h($t('save_notes')) ?> ✓</button><button class="button outline" type="button" data-print><?= $h($t('print')) ?> ↗</button></div></form><div class="print-notes" data-print-notes><?= $h($states['__notes']['text']??'') ?></div><div class="qr-card"><img src="<?= $h($link('qr')) ?>" width="140" height="140" alt="QR · <?= $h($t('classroom')) ?>"><div><h3><?= $h($t('classroom')) ?></h3><a href="<?= $h($link('classroom')) ?>"><?= $h($link('classroom')) ?></a></div></div></section>
<?php elseif ($space==='library'): ?>
<div class="page-heading"><p class="eyebrow">ATHENA / <?= $h($t('library')) ?></p><h1><?= $h($t('library')) ?></h1><p><?= $h($t('library_desc')) ?></p></div>
<div class="two-columns"><section class="lms-panel panel"><h2><?= $h($t('glossary')) ?></h2><?php foreach(['observation','competence','debrief'] as $word): ?><details class="glossary"><summary><?= $h($t($word)) ?></summary><p><?= $h($t($word.'_def')) ?></p></details><?php endforeach ?></section>
<section class="lms-panel panel"><h2><?= $h($t('reference')) ?></h2><p><?= $h($t('resources_help')) ?></p>
<?php $referenceCourses=[]; foreach($courses as $course)$referenceCourses[$course['key']]=$course; foreach($learning as $key=>$item)$referenceCourses[$key]=$item['snapshot']; $resourceCount=0; ?>
<?php foreach($referenceCourses as $course): $hasResources=false; foreach($course['chapters'] as $chapter)if(!empty($chapter['resources']))$hasResources=true; if(!$hasResources)continue; ?><h3><?= $h($local($course['title'])) ?></h3><?php foreach($course['chapters'] as $chapter): $resources=$chapter['resources']??[]; $resourceCount+=count($resources); require __DIR__.'/course_resources.php'; endforeach; endforeach ?>
<?php if(!$resourceCount): ?><p class="muted"><?= $h($t('no_resources')) ?></p><?php endif ?></section></div>
<?php require __DIR__.'/lms_tools.php'; ?>
<?php elseif ($space==='record'): ?>
<?php if($legacyEnrollments!==[])require __DIR__.'/native_course_list.php'; require __DIR__.'/lms_tools.php'; ?>
<div class="page-heading"><p class="eyebrow">ATHENA / <?= $h($t('record')) ?></p><h1><?= $h($t('record')) ?></h1><p><?= $h($t('not_official')) ?></p></div><div class="stat-grid"><div><span><?= $h($t('enrolled')) ?></span><strong><?= count($learning) ?></strong></div><div><span><?= $h($t('validated')) ?></span><strong><?= $validCount ?></strong></div><div><span><?= $h($t('attempts')) ?></span><strong><?= $attemptCount ?></strong></div></div>
<?php foreach ($learning as $key=>$item): ?><section class="lms-panel panel"><?php $licenseState=$item; require __DIR__.'/course_license.php'; ?><h2><?= $h($local($item['snapshot']['title'])) ?></h2><p><?= $h($t(!empty($item['completed'])?'completed':'in_progress')) ?> · <?= $percent($item) ?> %</p><?php foreach ($item['validations']??[] as $i=>$validation): ?><div class="validation"><b>✓ <?= $h($local($item['snapshot']['chapters'][$i]['title'])) ?></b><p><?= $h($t('feedback')) ?> : <?= nl2br($h($validation['comment'])) ?></p><small><?= $h((new DateTimeImmutable('@'.$validation['at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i')) ?></small></div><?php endforeach ?><h3><?= $h($t('history')) ?></h3><?php if (empty($item['attempts'])): ?><p class="muted"><?= $h($t('no_attempt')) ?></p><?php else: ?><div class="table-scroll"><table><thead><tr><th><?= $h($t('chapter')) ?></th><th><?= $h($t('score')) ?></th><th><?= $h($t('date')) ?></th></tr></thead><tbody><?php foreach (array_reverse($item['attempts']) as $attempt): ?><tr><td><?= $attempt['chapter']+1 ?></td><td><?= $attempt['score'] ?> %</td><td><?= $h((new DateTimeImmutable('@'.$attempt['at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i')) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section><?php endforeach; if (!$learning): ?><div class="empty-state"><h2><?= $h($t('empty')) ?></h2><p><?= $h($t('empty_text')) ?></p></div><?php endif ?>
<?php elseif ($space==='instructor'): ?>
<?php require __DIR__.'/lms_tools.php'; ?>
<div class="page-heading"><p class="eyebrow">ATHENA / <?= $h($t('instructor')) ?></p><h1><?= $h($t('instructor')) ?></h1><p><?= $h($t('instructor_desc')) ?></p></div>
<section class="lms-panel panel"><h2><?= $h($t('review')) ?></h2><div class="table-scroll"><table><thead><tr><th><?= $h($t('learner')) ?></th><th><?= $h($t('program')) ?></th><th><?= $h($t('progress')) ?></th></tr></thead><tbody><?php foreach($learners as $learner): $item=$learner['state']; ?><tr><td>#<?= (int)$learner['user_id'] ?></td><td><?= $h($local($item['snapshot']['title'])) ?></td><td><?= $percent($item) ?> % · <?= $h($t(!empty($item['completed'])?'completed':((int)$item['stage']===6?'pending':'in_progress'))) ?></td></tr><?php endforeach ?></tbody></table></div></section>
<?php $pendingCount=0; foreach($learners as $learner): $item=$learner['state']; if((int)$item['stage']!==6||!empty($item['completed']))continue; $pendingCount++; $reviewState=$item; $reviewChapter=$item['snapshot']['chapters'][$item['chapter']]; ?>
<section class="lms-panel panel academy-instructor-review"><span class="pill accent"><?= $h($t('pending')) ?></span>
<h2><?= $h($t('learner')) ?> #<?= (int)$learner['user_id'] ?> · <?= $h($local($item['snapshot']['title'])) ?></h2><h3><?= $h($local($reviewChapter['title'])) ?></h3>
<?php require __DIR__.'/course_review.php'; ?>
<h3><?= $h($t('submitted_debrief')) ?></h3><p class="lesson-text"><?= nl2br($h($item['debriefs'][$item['chapter']]??'')) ?></p>
<?php if(!empty($reviewChapter['validation']['brief'])): ?><p><?= nl2br($h($local($reviewChapter['validation']['brief']))) ?></p><?php endif ?>
<?php if(!empty($reviewChapter['validation']['criteria'])): ?><h3><?= $h($t('validation_criteria')) ?></h3><ul class="academy-rubric"><?php foreach($reviewChapter['validation']['criteria'] as $criterion): ?><li><?= $h($local($criterion)) ?></li><?php endforeach ?></ul><?php endif ?>
<?php if((int)$learner['user_id']===(int)\App\Core\Session::get('user_id')): ?><p class="hint"><?= $h($t('self_validation')) ?></p>
<?php else: $formStart('validate',$learner['course_key']); ?><input type="hidden" name="learner" value="<?= (int)$learner['user_id'] ?>"><input type="hidden" name="expected_chapter" value="<?= (int)$item['chapter'] ?>"><label for="comment-<?= $pendingCount ?>"><?= $h($t('comment')) ?></label><textarea id="comment-<?= $pendingCount ?>" name="comment" rows="5" minlength="10" maxlength="5000" required></textarea><button class="button"><?= $h($t('validate')) ?> ✓</button></form><?php endif ?></section>
<?php endforeach; if(!$pendingCount): ?><p class="muted"><?= $h($t('review_empty')) ?></p><?php endif ?>
<?php if($academyStorageReady): ?><details class="advanced"><summary><?= $h($t('additional_authoring')) ?></summary><?php require __DIR__.'/editor.php'; ?></details><?php endif ?>
<section class="lms-panel panel"><h2><?= $h($t('catalogue')) ?></h2><?php foreach($courses as $course): ?><div class="activity"><h3><?= $h($local($course['title'])) ?></h3><span class="pill"><?= $h($t(isset($course['published'])&&!$course['published']?'draft':'published_course')) ?></span><?php if(isset($course['published'])&&!$course['published']): $formStart('publish',$course['key']); ?><button class="button small"><?= $h($t('publish')) ?></button></form><?php endif ?></div><?php endforeach ?></section>
<?php endif ?>
</main></div>
<?php require __DIR__ . '/footer.php'; ?>
