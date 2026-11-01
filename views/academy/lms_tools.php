<?php
// Outils de l'ancien LMS « Formations », désormais servis sous /academy/formations (mêmes données et droits).
$memberTools = ['academy/formations/sessions'=>'sessions_tool','academy/formations/competences'=>'competencies_tool','academy/formations/code-acces'=>'access_code_tool'];
$instructorTools = ['academy/studio'=>'academy_studio','formation/studio'=>'legacy_studio','formation/enrollments'=>'enrollments_tool','formation/reports'=>'reports_tool','formation/certificates'=>'certificates_tool','formation/publications'=>'publications_tool'];
?>
<nav class="academy-tool-list" aria-label="<?= $h($t('lms_tools')) ?>"><h3><?= $h($t('lms_tools')) ?></h3>
<?php foreach ($memberTools as $path=>$label): ?><a href="<?= $h(url($path)) ?>"><span><?= $h($t($label)) ?></span><span aria-hidden="true">↗</span></a><?php endforeach ?>
<?php if ($staff??false): ?><h3><?= $h($t('instructor')) ?></h3><?php foreach ($instructorTools as $path=>$label): ?><a href="<?= $h(url($path)) ?>"><span><?= $h($t($label)) ?></span><span aria-hidden="true">↗</span></a><?php endforeach; endif ?>
</nav>
