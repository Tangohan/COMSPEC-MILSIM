<?php
/**
 * Tableau de bord Academy, encadrement : bloc « À traiter » en tête, avec un compteur par type qui ouvre la file
 * filtrée de l'espace instructeur, et les premiers éléments avec leur action rapide.
 */
require __DIR__ . '/queue_init.php';
$qCounts = $queue['counts'] ?? [];
$qSessions = $queue['sessions'] ?? [];
$qTiles = [
    'chapter' => ['n' => (int) ($qCounts['chapter'] ?? 0), 'href' => $link('instructor') . '?type=chapter#file', 'hot' => true],
    'proposal' => ['n' => (int) ($qCounts['proposal'] ?? 0), 'href' => $link('instructor') . '?type=proposal#file', 'hot' => true, 'show' => !empty($queue['can']['proposal'])],
    'enrollment' => ['n' => (int) ($qCounts['enrollment'] ?? 0), 'href' => $link('instructor') . '?type=enrollment#file', 'hot' => true, 'show' => !empty($queue['can']['enrollment'])],
    'exam' => ['n' => (int) ($qCounts['exam'] ?? 0), 'href' => $link('instructor') . '?type=exam#file', 'hot' => false],
    'stalled' => ['n' => (int) ($qCounts['stalled'] ?? 0), 'href' => $link('instructor') . '?type=stalled#file', 'hot' => false],
    'session' => ['n' => count($qSessions), 'href' => $link('instructor') . '#seances', 'hot' => false],
];
$qFirst = array_slice(array_values(array_filter($queue['items'], static fn (array $i): bool => in_array($i['type'], \App\Services\Academy\AcademyInstructorQueue::ACTIONABLE, true) && !in_array($i['can'], ['own', 'not_allowed'], true))), 0, 5);
$qReturn = 'dashboard';
?>
<section class="q-band" id="a-traiter" aria-labelledby="q-band-title" data-q-root>
<header class="q-band__head">
<div><p class="eyebrow"><?= $h($t('q_band_kicker')) ?></p>
<h2 id="q-band-title"><?= $h($t('ins_todo')) ?> <b class="pill<?= (int) $queue['total'] > 0 ? ' accent' : '' ?>"><?= (int) $queue['total'] ?></b></h2></div>
<span class="q-band__links"><a class="button small outline" href="<?= $h($link('suivi')) ?>" title="<?= $h($t('ins_tracker_help')) ?>"><?= $h($t('ins_tracker_link')) ?></a> <a class="button small" href="<?= $h($link('instructor')) ?>#file"><?= $h($t('q_open_space')) ?></a></span>
</header>
<ul class="q-tiles">
<?php foreach ($qTiles as $type => $tile): if (array_key_exists('show', $tile) && !$tile['show'] && $tile['n'] === 0) continue; ?>
<li><a class="q-tile<?= $tile['n'] > 0 && $tile['hot'] ? ' is-hot' : '' ?><?= $tile['n'] === 0 ? ' is-zero' : '' ?>" href="<?= $h($tile['href']) ?>"><b><?= $tile['n'] ?></b><span><?= $h($t('q_type_' . $type)) ?></span></a></li>
<?php endforeach ?>
</ul>
<?php if ($qFirst === []): ?>
<p class="q-empty"><b><?= $h($t('q_empty')) ?></b> <?= $h($t('q_empty_help')) ?></p>
<?php else: ?>
<ul class="q-list">
<?php foreach ($qFirst as $qi): ?>
<li class="q-row"><span class="tag q-t-<?= $h($qi['type']) ?>"><?= $h($t('q_type_' . $qi['type'] . '_one')) ?></span>
<span class="q-row__main"><b><?= $h($qName($qi)) ?></b> <span translate="no"><?= $h($qTitle($qi['course_title'])) ?></span><small><?= $h($qDetail($qi)) ?> · <span title="<?= $h($qDate((int) $qi['since'])) ?>"><?= $h($qAge((int) $qi['since'])) ?></span></small></span>
<?php require __DIR__ . '/queue_actions.php'; ?></li>
<?php endforeach ?>
</ul>
<?php if ((int) $queue['total'] > count($qFirst)): ?><p><a class="text-link" href="<?= $h($link('instructor')) ?>#file"><?= $h(sprintf($t('q_see_all'), (int) $queue['total'])) ?> →</a></p><?php endif ?>
<?php foreach ($qFirst as $qi) { if ($qi['type'] === 'chapter') require __DIR__ . '/queue_dialog.php'; } ?>
<?php endif ?>
</section>
