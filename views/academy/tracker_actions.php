<?php
/**
 * Actions en ligne d'une cellule du tableau de suivi (tracker.php). Inscrire, relancer et lever une dispense passent par
 * le formulaire partagé #trk-act (valeur « membre|cours ») ; valider ouvre la fenêtre de validation du chapitre (action
 * « validate » de la file « À traiter ») ; dispenser ouvre la fenêtre #trk-exempt.
 *
 * @var list<string> $acts @var array $cell @var int $uid @var string $fk @var array $mem
 */
if ($acts === [] && !($staffView && $cell['state'] !== 'none')) return;
$cellRef = $uid . '|' . $fk;
?>
<span class="trk-acts trk-noprint">
<?php foreach ($acts as $act): ?>
<?php if ($act === 'enroll'): ?><button type="submit" class="button small" form="trk-act" formaction="<?= $h($link('action/tracker_enroll')) ?>" name="cell" value="<?= $h($cellRef) ?>"><?= $h($t('trk_enroll')) ?></button>
<?php elseif ($act === 'remind'): $remindable = \App\Services\Academy\AcademyTracker::canRemindNow($cell, (int) $tk['now']); ?><button type="submit" class="button small outline" form="trk-act" formaction="<?= $h($link('action/tracker_remind')) ?>" name="cell" value="<?= $h($cellRef) ?>"<?= $remindable ? '' : ' disabled title="' . $h(sprintf($t('trk_reminded_on'), $date((int) $cell['reminded']))) . '"' ?>><?= $h($t('trk_remind')) ?></button>
<?php elseif ($act === 'validate'): $reviews[] = [$uid, $fk, $cell, $mem['name']]; ?><button type="button" class="button small" data-q-open="trk-rv-<?= $uid ?>-<?= $h($fk) ?>"><?= $h($t('trk_validate')) ?></button>
<?php elseif ($act === 'exempt'): $exemptible = true; ?><button type="button" class="button small outline" data-trk-exempt="<?= $h($cellRef) ?>" data-trk-exempt-name="<?= $h($mem['name'] . ' · ' . $courseTitle($tk['courses'][$fk] ?? [])) ?>"><?= $h($t('trk_exempt')) ?></button>
<?php elseif ($act === 'unexempt'): ?><button type="submit" class="button small outline" form="trk-act" formaction="<?= $h($link('action/tracker_unexempt')) ?>" name="cell" value="<?= $h($cellRef) ?>"><?= $h($t('trk_unexempt')) ?></button>
<?php endif ?>
<?php endforeach ?>
<?php if ($staffView && isset($tk['rows'][$uid][$fk])): ?><a class="text-link" href="<?= $h($link('instructor')) ?>#eleve-<?= $uid ?>-<?= $h($fk) ?>"><?= $h($t('trk_detail')) ?></a><?php endif ?>
</span>
