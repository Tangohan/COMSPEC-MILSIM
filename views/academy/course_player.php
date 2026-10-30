<?php
use App\Services\Academy\AcademyCurriculum;
?>
<?php if (!$selected): ?>
<p><?= $h($t('empty_text')) ?></p><a class="button" href="<?= $h($link('catalogue')) ?>"><?= $h($t('catalogue')) ?> →</a>
<?php if ($legacyCourses!==[]): require __DIR__.'/native_course_list.php'; endif ?>
<?php elseif (!$state): ?>
<section class="lms-panel lesson panel">
<p class="eyebrow"><?= $h($t(!empty($selected['demo'])?'demo':'published_course')) ?></p>
<h2><?= $h($local($selected['title'])) ?></h2><p><?= $h($local($selected['summary'])) ?></p>
<?php if (!empty($selected['license'])): ?><p class="academy-license-note"><?= $h($local($selected['license'])) ?></p><?php endif ?>
<p><?= $h($t('not_official')) ?></p>
<?php if (!empty($selected['duration_minutes'])): ?><p><?= $h($t('estimated_duration')) ?> : <?= (int)$selected['duration_minutes'] ?> min</p><?php endif ?>
<h3><?= $h($t('curriculum')) ?></h3><ol class="syllabus"><?php foreach ($selected['chapters'] as $chapter): ?><li><?= $h($local($chapter['title'])) ?></li><?php endforeach ?></ol>
<ol class="lesson-steps"><?php foreach (AcademyCurriculum::STAGES as $index=>$step): ?><li><span><?= $index+1 ?></span><?= $h($t($step)) ?></li><?php endforeach ?></ol>
<?php $formStart('enroll',$selected['key']); ?><button class="button" <?= !$academyStorageReady?'disabled':'' ?>><?= $h($t('enroll')) ?> ↗</button></form>
</section>
<?php else: $chapter=$selected['chapters'][$state['chapter']]; $stage=(int)$state['stage']; ?>
<div class="lesson-header">
<span class="pill"><?= $h($local($selected['title'])) ?></span>
<h2><?= $h($t('chapter')) ?> <?= (int)$state['chapter']+1 ?> — <?= $h($local($chapter['title'])) ?></h2>
<?php if (!empty($selected['license'])): ?><p class="academy-license-caption"><?= $h($local($selected['license'])) ?></p><?php endif ?>
<div class="progress-label"><span><?= $h($t('progress')) ?></span><b><?= $percent($state) ?> %</b></div><progress max="100" value="<?= $percent($state) ?>"><?= $percent($state) ?>%</progress>
</div>
<ol class="lesson-steps"><?php foreach (AcademyCurriculum::STAGES as $index=>$step): $stepDone=!empty($state['completed'])||$index<$stage; ?><li class="<?= $stepDone?'done':($index===$stage?'current':'') ?>" <?= !$stepDone&&$index===$stage?'aria-current="step"':'' ?>><span><?= $stepDone?'✓':$index+1 ?></span><?= $h($t($step)) ?></li><?php endforeach ?></ol>
<?php if (!empty($state['feedback'])): ?><div class="notice" role="status"><?= $h($t($state['feedback'])) ?><?php if (!empty($state['last_practice_feedback']) && $state['feedback']==='correct'): ?><p><?= $h($local($state['last_practice_feedback'])) ?></p><?php endif ?></div><?php endif ?>
<article class="lms-panel lesson panel">
<?php if (!empty($state['completed'])): ?>
<span class="completion-icon">✓</span><h2><?= $h($t('completed')) ?></h2><p><?= $h($t('all_done')) ?></p>
<?php $licenseState=$state; require __DIR__.'/course_license.php'; ?>
<p><?= $h($t('not_official')) ?></p><a class="button" href="<?= $h($link('record')) ?>"><?= $h($t('record')) ?> ↗</a>
<?php elseif (($space==='laboratory'&&!in_array($stage,[2,3],true))||($space==='exams'&&$stage!==4)||($space==='simulators'&&$stage!==3)): ?>
<h2><?= $h($t($space==='exams'?'no_exam':($space==='simulators'?'simulation_locked':'start_first'))) ?></h2>
<a class="button" href="<?= $h($courseUrl('classroom',$selected['key'])) ?>"><?= $h($t('go_class')) ?> →</a>
<?php elseif ($stage<2): ?>
<p class="eyebrow">0<?= $stage+1 ?> / <?= $h($t(AcademyCurriculum::STAGES[$stage])) ?></p><h2><?= $h($local($chapter['title'])) ?></h2>
<div class="lesson-text"><?= nl2br($h($local($chapter[$stage===0?'theory':'demonstration']))) ?></div>
<?php if ($stage===0&&!empty($chapter['resources'])): ?><section class="academy-lesson-resources"><h3><?= $h($t('reference')) ?></h3><?php $resources=$chapter['resources']; require __DIR__.'/course_resources.php'; ?></section><?php endif ?>
<?php $formStart('advance',$selected['key']); ?><button class="button"><?= $h($t('next')) ?> →</button></form>
<?php elseif (in_array($stage,[2,3],true)): $question=AcademyCurriculum::questionForStage($chapter,$stage); ?>
<p class="eyebrow"><?= $h($t($stage===2?'guided':'simulation')) ?></p>
<?php if (!empty($question['brief'])): ?><div class="lesson-text"><?= nl2br($h($local($question['brief']))) ?></div><?php endif ?>
<h2><?= $h($local($question['question'])) ?></h2>
<?php if ($stage===2): ?><div class="hint"><strong><?= $h($t('guided_hint')) ?></strong><p><?= nl2br($h($local($question['hint']??$chapter['demonstration']))) ?></p></div><?php else: ?><p class="muted"><?= $h($t('independent_case')) ?></p><?php endif ?>
<?php $formStart('practice',$selected['key']); ?><fieldset><legend><?= $h($t('choose')) ?></legend><?php foreach ($question['options'] as $index=>$option): ?><label class="answer-option"><input type="radio" name="answer" value="<?= $index ?>" required><span><?= $h($local($option)) ?></span></label><?php endforeach ?></fieldset><button class="button"><?= $h($t('submit')) ?> →</button></form>
<?php if (($state['feedback']??'')==='retry'&&$stage===2): ?><p class="hint"><?= $h($local($question['feedback'])) ?></p><?php endif ?>
<?php elseif ($stage===4): $examQuestions=AcademyCurriculum::examQuestions($chapter); $examSeconds=AcademyCurriculum::examSeconds($chapter); $examThreshold=AcademyCurriculum::examPassPercent($chapter); $richExam=isset($chapter['assessment']['questions']); ?>
<p class="eyebrow"><?= $h($t('assessment')) ?></p><h2><?= $h($t('ready_exam')) ?></h2>
<p class="academy-exam-summary"><?= count($examQuestions) ?> <?= $h($t('questions_label')) ?> · <?= (int)ceil($examSeconds/60) ?> min · <?= $h($t('passing_score')) ?> <?= $examThreshold ?> %</p>
<p><?= $h($t('exam_server_rules')) ?></p>
<?php if (!empty($chapter['assessment']['instructions'])): ?><div class="lesson-text"><?= nl2br($h($local($chapter['assessment']['instructions']))) ?></div><?php endif ?>
<?php if (empty($state['exam'])): $formStart('exam_start',$selected['key']); ?><button class="button"><?= $h($t('start_exam')) ?> ◷</button></form>
<?php else: ?><div class="exam-clock" role="timer" aria-label="<?= $h($t('remaining')) ?>"><span><?= $h($t('remaining')) ?></span><strong data-countdown="<?= (int)$state['exam']['deadline'] ?>" data-server-now="<?= time() ?>"><?= $h(sprintf('%02d:%02d',intdiv(max(0,(int)$state['exam']['deadline']-time()),60),max(0,(int)$state['exam']['deadline']-time())%60)) ?></strong></div>
<?php $formStart('exam_submit',$selected['key']); ?><input type="hidden" name="token" value="<?= $h($state['exam']['token']) ?>">
<?php foreach ($examQuestions as $questionIndex=>$question): ?><fieldset class="academy-exam-question"><legend><?= $questionIndex+1 ?>. <?= $h($local($question['question'])) ?></legend><?php foreach ($question['options'] as $index=>$option): ?><label class="answer-option"><input type="radio" name="<?= $richExam?'answers['.$questionIndex.']':'answer' ?>" value="<?= $index ?>" required><span><?= $h($local($option)) ?></span></label><?php endforeach ?></fieldset><?php endforeach ?>
<button class="button"><?= $h($t('send_exam')) ?> →</button></form><?php endif ?>
<?php elseif ($stage===5): ?>
<p class="eyebrow"><?= $h($t('debrief')) ?></p><h2><?= $h($t('reflection')) ?></h2>
<?php $reviewState=$state; $reviewChapter=$chapter; require __DIR__.'/course_review.php'; ?>
<div class="lesson-text"><?= nl2br($h($local($chapter['debrief']['prompt']??$chapter['feedback']))) ?></div>
<?php if (!empty($chapter['debrief']['criteria'])): ?><ul class="academy-rubric"><?php foreach ($chapter['debrief']['criteria'] as $criterion): ?><li><?= $h($local($criterion)) ?></li><?php endforeach ?></ul><?php endif ?>
<?php $formStart('debrief',$selected['key']); ?><label for="reflection"><?= $h($t('reflection_help')) ?></label><textarea id="reflection" name="reflection" minlength="20" maxlength="5000" rows="10" required></textarea><button class="button"><?= $h($t('send_debrief')) ?> →</button></form>
<?php else: ?>
<span class="completion-icon">◷</span><h2><?= $h($t('pending')) ?></h2><p><?= $h($t('pending_text')) ?></p>
<?php if (!empty($chapter['validation']['brief'])): ?><div class="lesson-text"><?= nl2br($h($local($chapter['validation']['brief']))) ?></div><?php endif ?>
<?php if (!empty($chapter['validation']['criteria'])): ?><h3><?= $h($t('validation_criteria')) ?></h3><ul class="academy-rubric"><?php foreach ($chapter['validation']['criteria'] as $criterion): ?><li><?= $h($local($criterion)) ?></li><?php endforeach ?></ul><?php endif ?>
<h3><?= $h($t('submitted_debrief')) ?></h3><p class="lesson-text"><?= nl2br($h($state['debriefs'][$state['chapter']]??'')) ?></p>
<?php endif ?>
</article>
<?php if ($stage>=2 && empty($state['exam'])): ?>
<details class="academy-completed-materials" data-completed-materials>
<summary><?= $h($t('review_completed_materials')) ?></summary>
<p class="muted"><?= $h($t('review_materials_help')) ?></p>
<section><h3><?= $h($t('theory')) ?></h3><div class="lesson-text"><?= nl2br($h($local($chapter['theory']))) ?></div></section>
<section><h3><?= $h($t('demonstration')) ?></h3><div class="lesson-text"><?= nl2br($h($local($chapter['demonstration']))) ?></div></section>
<?php if (!empty($chapter['resources'])): ?><section class="academy-lesson-resources"><h3><?= $h($t('reference')) ?></h3><?php $resources=$chapter['resources']; require __DIR__.'/course_resources.php'; ?></section><?php endif ?>
</details>
<?php endif ?>
<?php endif ?>
