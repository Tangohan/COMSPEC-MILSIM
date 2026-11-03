<?php
/**
 * Fenêtre de validation du chapitre en attente depuis le tableau de suivi : même action « validate » que la file
 * « À traiter » (le serveur revérifie le droit, l'étape et le chapitre attendu), retour au tableau avec ses filtres.
 *
 * @var int $rUid @var string $rKey @var array $rCell @var string $rName
 */
$rCourse = $tk['courses'][$rKey] ?? [];
$rChapter = $rCourse['chapters'][$rCell['chapter']] ?? [];
$rDebrief = $tk['rows'][$rUid][$rKey]['debriefs'][$rCell['chapter']] ?? '';
$rId = 'trk-rv-' . $rUid . '-' . $rKey;
?>
<dialog class="q-dialog" id="<?= $h($rId) ?>" aria-labelledby="<?= $h($rId) ?>-t">
<div class="q-dialog__head"><div><p class="eyebrow"><?= $h($t(!empty($rCell['final']) ? 'ins_task_final' : 'ins_task_debrief')) ?></p>
<h2 id="<?= $h($rId) ?>-t"><?= $h($rName) ?> · <?= $h($courseTitle($rCourse)) ?></h2>
<p class="muted"><?= $h($t('chapter')) ?> <?= (int) $rCell['chapter'] + 1 ?><?php $rTitle = $local($rChapter['title'] ?? ['fr' => '']); if ($rTitle !== ''): ?> · <?= $h($rTitle) ?><?php endif ?></p></div>
<button type="button" class="q-dialog__close" data-q-close aria-label="<?= $h($t('q_close')) ?>">×</button></div>
<div class="q-dialog__body ins-review">
<h4><?= $h($t('submitted_debrief')) ?></h4><p class="lesson-text"><?= nl2br($h(is_string($rDebrief) ? $rDebrief : '')) ?></p>
<?php if (!empty($rChapter['validation']['criteria'])): ?><h4><?= $h($t('validation_criteria')) ?></h4><ul class="academy-rubric"><?php foreach ($rChapter['validation']['criteria'] as $criterion): ?><li><?= $h($local($criterion)) ?></li><?php endforeach ?></ul><?php endif ?>
<p><a class="text-link" href="<?= $h($link('instructor')) ?>?type=chapter#chapter-<?= $rUid ?>-<?= $h($rKey) ?>"><?= $h($t('trk_full_review')) ?> ↗</a></p>
<form method="post" action="<?= $h($link('action/validate')) ?>"><?= \App\Core\Csrf::field() ?>
<input type="hidden" name="course" value="<?= $h($rKey) ?>"><input type="hidden" name="learner" value="<?= $rUid ?>"><input type="hidden" name="expected_chapter" value="<?= (int) $rCell['chapter'] ?>"><input type="hidden" name="return" value="tracker"><?= $hiddenBack ?>
<label for="<?= $h($rId) ?>-c"><?= $h($t('comment')) ?></label><textarea id="<?= $h($rId) ?>-c" name="comment" rows="5" minlength="10" maxlength="5000" required></textarea>
<p class="hint"><?= $h($t(!empty($rCell['final']) ? 'ins_final_note' : 'q_validate_note')) ?></p>
<div class="q-dialog__foot"><button type="button" class="button outline" data-q-close><?= $h($t('q_later')) ?></button><button class="button"><?= $h($t(!empty($rCell['final']) ? 'ins_validate_final' : 'validate')) ?> ✓</button></div>
</form>
</div>
</dialog>
