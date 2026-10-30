<?php
/** The caller supplies a persisted state and its chapter; no private notebook is read. */
$chapterAttempts = array_values(array_filter($reviewState['attempts']??[], static fn(array $attempt):bool=>(int)($attempt['chapter']??-1)===(int)$reviewState['chapter']));
$lastAttempt = $chapterAttempts !== [] ? $chapterAttempts[array_key_last($chapterAttempts)] : null;
?>
<?php if ($lastAttempt): ?>
<section class="academy-assessment-review" aria-label="<?= $h($t('assessment_review')) ?>">
<h3><?= $h($t('assessment_review')) ?></h3>
<p><strong><?= (int)$lastAttempt['score'] ?> %</strong><?php if (isset($lastAttempt['correct_count'],$lastAttempt['question_count'])): ?> · <?= (int)$lastAttempt['correct_count'] ?> / <?= (int)$lastAttempt['question_count'] ?> <?= $h($t('correct_responses')) ?><?php endif ?><?php if (!empty($lastAttempt['expired'])): ?> · <?= $h($t('time_expired')) ?><?php endif ?></p>
<?php if (isset($lastAttempt['results'])): ?>
<details><summary><?= $h($t('review_answers')) ?></summary>
<ol class="academy-answer-review">
<?php foreach (\App\Services\Academy\AcademyCurriculum::examQuestions($reviewChapter) as $index=>$question): $result=$lastAttempt['results'][$index]??[]; $selectedAnswer=$result['selected']??null; ?>
<li><h4><?= $h($local($question['question'])) ?></h4><p><strong><?= $h($t(!empty($result['correct'])?'response_correct':'response_incorrect')) ?></strong></p>
<p><?= $h($t('submitted_response')) ?> : <?= $h(is_int($selectedAnswer)&&isset($question['options'][$selectedAnswer])?$local($question['options'][$selectedAnswer]):$t('no_response')) ?></p>
<p><?= $h($t('expected_response')) ?> : <?= $h($local($question['options'][$question['answer']])) ?></p>
<p><?= $h($local($question['feedback'])) ?></p></li>
<?php endforeach ?>
</ol></details>
<?php endif ?>
</section>
<?php endif ?>
