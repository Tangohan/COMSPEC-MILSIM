<?php
/**
 * Studio : ce que l'on crée (formations, qualifications, brevets) — onglets communs à l'accueil et aux fiches,
 * et fenêtre « Nouveau » (formation : fenêtre du cours ; qualification et brevet : leur fiche).
 *
 * @var string $kindActive course|qualification|brevet
 * @var array<string,int> $kindCounts
 * @var bool $kindCanCreate
 */
$kindCounts = $kindCounts ?? [];
?>
<nav class="sth-tabs sth-kinds" aria-label="<?= $h($t('studio_new_what')) ?>">
<?php foreach (['course' => '', 'qualification' => '?vue=qualifications', 'brevet' => '?vue=brevets'] as $kindKey => $kindQuery): ?>
<a href="<?= $h($link('studio') . $kindQuery) ?>"<?= $kindActive === $kindKey ? ' class="on" aria-current="page"' : '' ?>><?= $h($t('kind_' . $kindKey . 's')) ?><?php if (isset($kindCounts[$kindKey])): ?><em><?= (int) $kindCounts[$kindKey] ?></em><?php endif ?></a>
<?php endforeach ?>
</nav>
<?php if (!empty($kindCanCreate)): ?>
<dialog class="ast-dialog sth-pick" id="sth-pick" aria-labelledby="sth-pick-title">
<div class="mh"><div><p class="k"><?= $h($t('studio_new')) ?></p><h2 id="sth-pick-title"><?= $h($t('studio_new_what')) ?></h2></div><button class="mx" type="button" data-close-dialog aria-label="<?= $h($t('close')) ?>">×</button></div>
<div class="mb"><div class="sth-pick__list">
<?php if (!empty($kindCourseDialog)): ?><button type="button" class="sth-pick__opt" data-open-dialog="sth-new" data-close-dialog><span class="ic t-theory"><svg class="i"><use href="#ai-theory"/></svg></span><span><b><?= $h($t('kind_course')) ?></b><small><?= $h($t('studio_new_course_help')) ?></small></span></button>
<?php else: ?><a class="sth-pick__opt" href="<?= $h($link('studio') . '?nouveau-cours=1') ?>"><span class="ic t-theory"><svg class="i"><use href="#ai-theory"/></svg></span><span><b><?= $h($t('kind_course')) ?></b><small><?= $h($t('studio_new_course_help')) ?></small></span></a><?php endif ?>
<a class="sth-pick__opt" href="<?= $h($link('studio') . '?nouveau=qualification') ?>"><span class="ic t-validation"><svg class="i"><use href="#ai-check"/></svg></span><span><b><?= $h($t('kind_qualification')) ?></b><small><?= $h($t('studio_new_qual_help')) ?></small></span></a>
<a class="sth-pick__opt" href="<?= $h($link('studio') . '?nouveau=brevet') ?>"><span class="ic t-assessment"><svg class="i"><use href="#ai-validation"/></svg></span><span><b><?= $h($t('kind_brevet')) ?></b><small><?= $h($t('studio_new_brevet_help')) ?></small></span></a>
</div></div>
</dialog>
<?php endif ?>
