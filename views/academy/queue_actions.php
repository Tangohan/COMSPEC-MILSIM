<?php
/**
 * Actions en ligne d'un élément de la file (queue_init.php) : validation rapide d'un chapitre (fenêtre), réponse à une
 * demande d'inscription, décision sur une qualification proposée. Les formulaires visent les actions existantes.
 *
 * @var array $qi élément de la file
 * @var string $qReturn retour après validation d'un chapitre : dashboard | queue
 */
$qCan = (string) ($qi['can'] ?? 'ok');
$qBulkData = $qBulk($qi, $qReturn);
?>
<span class="q-actions">
<?php if ($qi['type'] === 'auto'): ?>
<?php if ($qCan === 'ok' && !empty($qi['revocable'])): ?>
<form method="post" action="<?= $h($link('action/revoke_auto')) ?>" onsubmit="return confirm(<?= $h(json_encode($t('auto_revoke_confirm'), JSON_UNESCAPED_UNICODE)) ?>)"><?= $qHidden(['course' => $qi['course_key'], 'learner' => (int) $qi['learner_id'], 'chapter' => (int) $qi['chapter']]) ?><button class="button small outline"><?= $h($t('auto_revoke')) ?></button></form>
<?php elseif ($qCan === 'ok'): ?><small class="muted"><?= $h($t('auto_not_revocable')) ?></small>
<?php endif ?>
<a class="text-link" href="<?= $h($link('instructor') . '#eleve-' . (int) $qi['learner_id'] . '-' . rawurlencode($qi['course_key'])) ?>"><?= $h($t('q_open_learner')) ?></a>
<?php elseif ($qi['type'] === 'chapter'): ?>
<button type="button" class="button small<?= $qCan === 'ok' ? '' : ' outline' ?>" data-q-open="dlg-<?= $h($qi['id']) ?>"><?= $h($t($qCan === 'ok' ? 'ins_open_review' : 'q_read')) ?></button>
<?php elseif (in_array($qi['type'], ['enrollment', 'proposal'], true) && $qBulkData !== null): ?>
<form method="post" action="<?= $h($qBulkData['approve']['url']) ?>"><?= $qHidden($qBulkData['approve']['fields']) ?><button class="button small"><?= $h($t($qi['type'] === 'enrollment' ? 'q_accept' : 'q_approve')) ?> ✓</button></form>
<form method="post" action="<?= $h($qBulkData['refuse']['url']) ?>"><?= $qHidden($qBulkData['refuse']['fields']) ?><button class="button small outline"><?= $h($t('q_refuse')) ?></button></form>
<a class="text-link" href="<?= $h($qUrl($qi)) ?>"><?= $h($t($qi['type'] === 'enrollment' ? 'q_open_request' : 'q_open_sheet')) ?> ↗</a>
<?php elseif (in_array($qi['type'], ['enrollment', 'proposal'], true)): ?>
<small class="muted"><?= $h($t('q_own')) ?></small>
<?php else: ?>
<a class="text-link" href="<?= $h($qUrl($qi)) ?>"><?= $h($t('q_open_learner')) ?></a>
<?php endif ?>
</span>
