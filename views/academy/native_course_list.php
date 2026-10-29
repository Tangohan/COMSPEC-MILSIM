<?php
$nativeEntries = in_array($space ?? '', ['courses','record'], true) ? ($legacyEnrollments ?? []) : ($legacyCourses ?? []);
?>
<?php if (!$nativeEntries): ?><p class="academy-empty-line"><?= $h($t('no_published_courses')) ?></p><?php else: ?>
<ol class="academy-course-list">
<?php foreach ($nativeEntries as $index=>$entry):
$slug = (string)($entry['course_slug']??$entry['slug']??'');
$title = (string)($entry['course_title']??$entry['title']??'');
$status = (string)($entry['enrollment']['status']??$entry['status']??'not_enrolled_label');
$statusKey = in_array($status,['completed','in_progress','assigned','pending_approval'],true)?$status:'not_enrolled_label';
?>
<li data-course-row><span class="academy-course-number"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span>
<div class="academy-course-copy"><a href="<?= $h(url('formations/' . rawurlencode($slug))) ?>"><h3><?= $h($title) ?></h3></a><p><?= $h(strip_tags((string)($entry['short_description']??''))) ?></p><span class="academy-course-meta"><?= $h($t($statusKey)) ?> · <?= (int)($entry['estimated_minutes']??0) ?> min<?php if (!empty($entry['progress_percent'])): ?> · <?= (int)$entry['progress_percent'] ?> %<?php endif ?></span>
<?php if (!empty($entry['certificate_id'])): ?><a class="text-link" href="<?= $h(url('formations/certificate/' . (int)$entry['certificate_id'])) ?>"><?= $h($t('certificates_tool')) ?> ↗</a><?php endif ?></div>
<a class="text-link" href="<?= $h(url('formations/' . rawurlencode($slug))) ?>"><?= $h($t('open_course')) ?> ↗</a></li>
<?php endforeach ?></ol><?php endif ?>
