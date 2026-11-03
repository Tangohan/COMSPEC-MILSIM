<?php
/** Academy curricula use their own persisted enrolments, alongside the native LMS catalogue. */
$displayCourses = $displayCourses ?? $courses;
?>
<?php if ($displayCourses !== []): ?>
<ol class="academy-course-list" aria-label="<?= $h($t('academy_curricula')) ?>">
<?php foreach ($displayCourses as $course): $key=(string)$course['key']; $item=$learning[$key]??null; ?>
<li data-course-row>
    <div class="academy-course-copy">
        <a href="<?= $h($courseUrl('classroom',$key)) ?>"><h3><?= $h($local($course['title'])) ?></h3></a>
        <p><?= $h($local($course['summary'])) ?></p>
        <span class="academy-course-meta"><?= count($course['chapters']) ?> <?= $h($t(count($course['chapters'])===1?'chapter':'chapters')) ?> · 7 <?= $h($t('learning_steps')) ?><?php if (!empty($course['duration_minutes'])): ?> · <?= (int)$course['duration_minutes'] ?> min<?php endif ?><?php if ($item): ?> · <?= $percent($item) ?> %<?php endif ?></span>
        <?php if ($item && empty($item['completed']) && (int) ($item['stage'] ?? 0) === 6): /* Débriefing envoyé : le stagiaire sait qu'il attend un instructeur. */ ?><p><span class="pill accent"><?= $h($t('q_learner_waiting')) ?></span></p><?php endif ?>
        <?php if ($item && ($vi = ($versionInfo ?? [])[$key] ?? null) !== null): $vKey = $key; $vLearner = null; require __DIR__ . '/version_badge.php'; endif ?>
        <?php if (!empty($course['license'])): ?><p class="academy-license-caption"><?= $h($local($course['license'])) ?></p><?php endif ?>
    </div>
    <?php if ($item): ?><a class="text-link" href="<?= $h($courseUrl('classroom',$key)) ?>"><?= $h($t(!empty($item['completed'])?'view_learning':'continue')) ?> ↗</a>
    <?php else: $formStart('enroll',$key); ?><button class="button small" <?= !$academyStorageReady?'disabled':'' ?>><?= $h($t('enroll')) ?> ↗</button></form><?php endif ?>
</li>
<?php endforeach ?>
</ol>
<?php endif ?>
