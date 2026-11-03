<?php
/**
 * Fenêtre de validation rapide d'un chapitre : évaluation, débriefing remis, critères, puis commentaire et validation
 * (action « validate » existante, revérifiée côté serveur). Pas de refus : le stagiaire reste en attente tant que le
 * chapitre n'est pas validé ; le commentaire sert de correction.
 *
 * @var array $qi élément « chapter » de la file
 * @var string $qReturn dashboard | queue
 */
$reviewState = $qi['row']['state'] ?? [];
$reviewChapter = $reviewState['snapshot']['chapters'][$qi['chapter']] ?? null;
if ($reviewChapter === null) return;
$qCan = (string) ($qi['can'] ?? 'ok');
$qDialogId = 'dlg-' . $qi['id'];
?>
<dialog class="q-dialog" id="<?= $h($qDialogId) ?>" aria-labelledby="<?= $h($qDialogId) ?>-t">
<div class="q-dialog__head"><div><p class="eyebrow"><?= $h($t(!empty($qi['final']) ? 'ins_task_final' : 'ins_task_debrief')) ?></p>
<h2 id="<?= $h($qDialogId) ?>-t"><?= $h($qName($qi)) ?> · <?= $h($qTitle($qi['course_title'])) ?></h2>
<p class="muted"><?= $h($qDetail($qi)) ?> · <?= $h(sprintf($t('ins_sent_on'), $qDate((int) $qi['since']))) ?></p></div>
<button type="button" class="q-dialog__close" data-q-close aria-label="<?= $h($t('q_close')) ?>">×</button></div>
<div class="q-dialog__body ins-review">
<?php require __DIR__ . '/course_review.php'; ?>
<h4><?= $h($t('submitted_debrief')) ?></h4><p class="lesson-text"><?= nl2br($h($debriefText($reviewState['debriefs'][$qi['chapter']] ?? ''))) ?></p>
<?php if (!empty($reviewChapter['validation']['brief'])): ?><div class="rt"><?= $rich($reviewChapter['validation']['brief']) ?></div><?php endif ?>
<?php if (!empty($reviewChapter['validation']['criteria'])): ?><h4><?= $h($t('validation_criteria')) ?></h4><ul class="academy-rubric"><?php foreach ($reviewChapter['validation']['criteria'] as $criterion): ?><li><?= $h($local($criterion)) ?></li><?php endforeach ?></ul><?php endif ?>
<?php if ($qCan === 'own'): ?><p class="hint"><?= $h($t('self_validation')) ?></p>
<?php elseif ($qCan === 'not_allowed'): ?><p class="hint"><?= $h($t('ins_cannot_validate')) ?></p>
<?php elseif ($qCan === 'needs_certify'): ?><p class="hint"><?= $h($t('ins_final_needs_certify')) ?></p>
<?php else: ?>
<form method="post" action="<?= $h($link('action/validate')) ?>"><?= $qHidden(['course' => $qi['course_key'], 'learner' => (int) $qi['learner_id'], 'expected_chapter' => (int) $qi['chapter'], 'return' => $qReturn]) ?>
<label for="<?= $h($qDialogId) ?>-c"><?= $h($t('comment')) ?></label><textarea id="<?= $h($qDialogId) ?>-c" name="comment" rows="5" minlength="10" maxlength="5000" required></textarea>
<p class="hint"><?= $h($t(!empty($qi['final']) ? 'ins_final_note' : 'q_validate_note')) ?></p>
<div class="q-dialog__foot"><button type="button" class="button outline" data-q-close><?= $h($t('q_later')) ?></button><button class="button"><?= $h($t(!empty($qi['final']) ? 'ins_validate_final' : 'validate')) ?> ✓</button></div>
</form>
<?php endif ?>
</div>
</dialog>
