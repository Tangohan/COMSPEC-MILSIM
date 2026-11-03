<?php
/**
 * Parcours sur une ancienne version du cours (AcademyVersionUpgrade::outdated) : badge, ce qui a changé et passage
 * à la version courante après confirmation (progression gardée, licence jamais retirée).
 *
 * @var array{from: string, to: string, key: string, new: int, removed: int, note: string, completed: bool} $vi
 * @var string $vKey clé du parcours suivi
 * @var int|null $vLearner stagiaire (encadrement) ; null : le membre connecté
 */
$vChanges = $vi['note'] !== '' ? $vi['note'] : sprintf($t('version_changes'), (int) $vi['new'], (int) $vi['removed']);
?>
<div class="academy-version">
<span class="pill accent" title="<?= $h($vChanges) ?>"><?= $h(sprintf($t('version_old'), $vi['from'], $vi['to'])) ?></span>
<details class="academy-version__up"><summary><?= $h($t('version_upgrade')) ?></summary>
<p><?= $h($vChanges) ?></p>
<p><?= $h($t($vi['completed'] ? 'version_upgrade_done_help' : 'version_upgrade_help')) ?></p>
<form method="post" action="<?= $h($link('action/version_upgrade')) ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="course" value="<?= $h($vKey) ?>"><?php if ($vLearner !== null): ?><input type="hidden" name="learner" value="<?= (int) $vLearner ?>"><?php endif ?>
<button class="button small" name="confirm" value="1"><?= $h($t('version_upgrade_confirm')) ?></button></form>
</details>
</div>
