<?php
/** Détail d'une cellule du tableau de suivi (vue « cursus ») : où en est le stagiaire. @var array $cell @var array $col */
$detailChapters = $col['course']['chapters'] ?? [];
?>
<ul class="trk-detail">
<li><?= $icon($cell['state']) ?> <?= $h($t('trk_state_' . $cell['state'])) ?><?php if (!in_array($cell['state'], ['none', 'validated', 'expired', 'exempt'], true)): ?> · <?= (int) $cell['pct'] ?> %<?php endif ?></li>
<?php if (in_array($cell['state'], ['enrolled', 'progress', 'review', 'failed'], true)): ?><li><?= $h(sprintf($t('trk_at_chapter'), (int) $cell['chapter'] + 1, (int) $col['chapters'])) ?><?php $ct = $local($detailChapters[$cell['chapter']]['title'] ?? ['fr' => '']); if ($ct !== ''): ?> · <?= $h($ct) ?><?php endif ?></li><?php endif ?>
<?php if ((int) $cell['since'] > 0): ?><li><?= $h(sprintf($t('trk_last_activity'), $date((int) $cell['since']))) ?><?php if ($cell['late']): ?> · <b><?= $h($t('trk_late')) ?></b><?php endif ?></li><?php endif ?>
<?php if ((int) $cell['reminded'] > 0): ?><li><?= $h(sprintf($t('trk_reminded_on'), $date((int) $cell['reminded']))) ?></li><?php endif ?>
<?php if ($cell['expires'] !== null): ?><li><?= $h(sprintf($t((int) $cell['expires'] < (int) $tk['now'] ? 'trk_expired_on' : 'trk_expires_on'), $date((int) $cell['expires']))) ?></li><?php endif ?>
</ul>
