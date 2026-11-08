<?php
require __DIR__ . '/init.php';
/* Salle de cours avec un cursus ouvert : maquette « Classroom » pleine largeur, avec sa propre barre d'en-tête. */
$classroomFocus = $space === 'classroom' && !empty($selected);
$customTopbar = $classroomFocus;
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
<?php if ($classroomFocus): require __DIR__ . '/classroom.php'; else: ?>
<div class="app-shell">
<?php
$academyIcons = ['dashboard'=>'overview','catalogue'=>'catalogue','courses'=>'mine','classroom'=>'docs','laboratory'=>'studio','simulators'=>'star','exams'=>'calendar','library'=>'docs','notebook'=>'feedback','record'=>'cert','instructor'=>'groups','suivi'=>'reports','studio'=>'studio'];
$academyNavItems = [];
foreach ($allSpaces as $item) {
    if (in_array($item, ['instructor','studio'], true) && !$staff) continue;
    // Espace instructeur : nombre d'éléments à traiter (chapitres à valider, demandes, propositions).
    $academyNavItems[] = ['href'=>$link($item), 'label'=>$t($item), 'icon'=>$academyIcons[$item], 'active'=>$space===$item||($space==='droits'&&$item==='instructor'), 'badge'=>$item==='instructor' ? (int) ($instructorBadge ?? 0) : null, 'hint'=>$item==='instructor' && (int) ($instructorBadge ?? 0) > 0 ? sprintf($t('q_badge_hint'), (int) $instructorBadge) : null];
}
$lmsNav = ['id'=>'academy','tone'=>'emerald','kicker'=>'Academy','title'=>$t('brand'),'lead'=>$t('tagline'),'aria'=>$t('brand'),'sections'=>[['label'=>$t('welcome'),'items'=>$academyNavItems]],'foot'=>['href'=>url('dashboard'),'label'=>$t('back_athena')]];
(static function (array $lmsNav): void { require base_path('views/partials/lms_nav_aside.php'); })($lmsNav);
?>
<main id="main" class="app-main"><div class="breadcrumb"><a href="<?= $h($link()) ?>">Academy</a><span>/</span><?= $h($t($space)) ?></div>
<?php if ($notice): ?><div class="notice" role="status"><?= $h(str_starts_with((string) $notice, 'trk_') ? sprintf($t($notice), (int) ($noticeCount ?? 0)) : $t($notice)) ?></div><?php \App\Core\Session::set('academy_notice', ''); endif ?>
<?php /* Réponse à une demande ou décision sur une proposition prise depuis la file : message de l'écran d'origine. */
foreach (['success' => 'notice', 'error' => 'notice notice--error'] as $flashKey => $flashClass): $flashText = \App\Core\Session::getFlash($flashKey); if (is_string($flashText) && $flashText !== '' && empty($queueBulk)): ?><div class="<?= $flashClass ?>" role="status"><?= $h($flashText) ?></div><?php endif; endforeach ?>
<?php if (!$academyStorageReady): ?><p class="notice"><?= $h($t('lms_storage_notice')) ?></p><?php endif ?>
<?php if ($space === 'dashboard'): ?>
<?php if (!empty($queue)) require __DIR__.'/queue_dashboard.php'; ?>
<?php require __DIR__.'/offer_dashboard.php'; ?>
<?php elseif ($space === 'catalogue'): ?>
<?php require __DIR__.'/offer_catalogue.php'; ?>
<?php elseif ($space === 'courses'): ?>
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
<?php foreach ($learning as $key=>$item): ?><section class="lms-panel panel"><?php $licenseState=$item; require __DIR__.'/course_license.php'; ?><h2><?= $h($local($item['snapshot']['title'])) ?></h2><p><?= $h($t(!empty($item['completed'])?'completed':'in_progress')) ?> · <?= $percent($item) ?> %</p><?php foreach ($item['validations']??[] as $i=>$validation): ?><div class="validation"><b>✓ <?= $h($local($item['snapshot']['chapters'][$i]['title'])) ?></b><?php if (!empty($validation['auto'])): ?><p><b>🤖 <?= $h($t('auto_badge')) ?></b> — <?= $h(sprintf($t('auto_trace'), (int) ($validation['hours'] ?? 72))) ?></p><?php else: ?><p><?= $h($t('feedback')) ?> : <?= nl2br($h($validation['comment'])) ?></p><?php endif ?><small><?= $h((new DateTimeImmutable('@'.$validation['at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i')) ?></small></div><?php endforeach ?><h3><?= $h($t('history')) ?></h3><?php if (empty($item['attempts'])): ?><p class="muted"><?= $h($t('no_attempt')) ?></p><?php else: ?><div class="table-scroll"><table><thead><tr><th><?= $h($t('chapter')) ?></th><th><?= $h($t('score')) ?></th><th><?= $h($t('date')) ?></th></tr></thead><tbody><?php foreach (array_reverse($item['attempts']) as $attempt): ?><tr><td><?= $attempt['chapter']+1 ?></td><td><?= $attempt['score'] ?> %</td><td><?= $h((new DateTimeImmutable('@'.$attempt['at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i')) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></section><?php endforeach; if (!$learning): ?><div class="empty-state"><h2><?= $h($t('empty')) ?></h2><p><?= $h($t('empty_text')) ?></p></div><?php endif ?>
<?php elseif ($space==='instructor'): ?>
<?php require __DIR__.'/instructor.php'; ?>
<?php elseif ($space==='suivi'): ?>
<?php require __DIR__.'/tracker.php'; ?>
<?php elseif ($space==='droits'): ?>
<?php require __DIR__.'/rights.php'; ?>
<?php endif ?>
</main></div>
<?php endif ?>
<?php require __DIR__ . '/footer.php'; ?>
