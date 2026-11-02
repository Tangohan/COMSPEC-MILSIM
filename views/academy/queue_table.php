<?php
/**
 * Espace instructeur : file de travail unique (chapitres à valider, qualifications proposées, demandes d'inscription,
 * évaluations échouées, stagiaires sans activité), triable par type, cours, stagiaire et ancienneté, filtrable, avec
 * actions en ligne et groupées. Les actions groupées envoient, ligne par ligne, les mêmes formulaires que les boutons
 * (public/assets/js/academy-queue.js).
 */
if (!is_array($queue ?? null)) {
    // Sans file préparée par le contrôleur : les débriefings des élèves déjà chargés par l'espace instructeur.
    $queue = \App\Services\Academy\AcademyInstructorQueue::build($learners ?? [], static fn (string $key): bool => $teach === null ? (bool) $can['validate'] : (isset($teach[$key]) || $can['validate']),
        (bool) $can['certify'], [], [], $me, time()) + ['sessions' => $sessions ?? [], 'can' => []];
}
require __DIR__ . '/queue_init.php';
$qCounts = $queue['counts'] ?? [];
$qType = in_array($queueType ?? '', \App\Services\Academy\AcademyInstructorQueue::TYPES, true) ? (string) $queueType : '';
$qReturn = 'queue';
$qItems = $queue['items'];
$qBulkable = count(array_filter($qItems, static fn (array $i): bool => $qBulk($i, 'queue') !== null));
?>
<section class="ins-sec" id="file" aria-labelledby="q-file-title" data-q-root data-q-filter-initial="<?= $h($qType) ?>"
 data-q-csrf="<?= $h(\App\Core\Csrf::token()) ?>" data-q-msg-none="<?= $h($t('q_bulk_none')) ?>" data-q-msg-comment="<?= $h($t('q_bulk_comment_required')) ?>"
 data-q-msg-refuse="<?= $h($t('q_bulk_refuse_confirm')) ?>" data-q-msg-working="<?= $h($t('q_bulk_working')) ?>">
<div class="ins-sec__head"><h2 id="q-file-title"><?= $h($t('ins_todo')) ?> <span class="pill<?= (int) $queue['total'] ? ' accent' : '' ?>"><?= (int) $queue['total'] ?></span></h2></div>
<?php if (!empty($queueBulk)): ?><p class="notice" role="status"><?= $h(sprintf($t($queueBulk['failed'] > 0 ? 'q_bulk_done_failed' : 'q_bulk_done'), $queueBulk['done'], $queueBulk['failed'])) ?></p><?php endif ?>
<details class="q-help"><summary><?= $h($t('q_help_title')) ?></summary><ol>
<li><?= $h($t('q_help_1')) ?></li><li><?= $h($t('q_help_2')) ?></li><li><?= $h($t('q_help_3')) ?></li><li><?= $h($t('q_help_4')) ?></li>
</ol></details>
<?php if ($qItems === []): ?>
<div class="ins-empty"><b><?= $h($t('q_empty')) ?></b><p><?= $h($t('ins_todo_empty_help')) ?></p></div>
<?php else: ?>
<div class="q-filters" role="group" aria-label="<?= $h($t('q_filter')) ?>">
<button type="button" class="q-chip" data-q-filter="" aria-pressed="<?= $qType === '' ? 'true' : 'false' ?>"><?= $h($t('q_all')) ?> <b><?= count($qItems) ?></b></button>
<?php foreach (\App\Services\Academy\AcademyInstructorQueue::TYPES as $type): if (empty($qCounts[$type])) continue; ?>
<button type="button" class="q-chip" data-q-filter="<?= $h($type) ?>" aria-pressed="<?= $qType === $type ? 'true' : 'false' ?>"><?= $h($t('q_type_' . $type)) ?> <b><?= (int) $qCounts[$type] ?></b></button>
<?php endforeach ?>
</div>
<?php if ($qBulkable > 0): ?>
<div class="q-bulk" data-q-bulk>
<label class="q-bulk__all"><input type="checkbox" data-q-all> <?= $h($t('q_select_all')) ?></label>
<label class="q-bulk__comment"><span><?= $h($t('q_bulk_comment')) ?></span><textarea rows="2" maxlength="5000" data-q-comment placeholder="<?= $h($t('q_bulk_comment_ph')) ?>"></textarea></label>
<div class="q-bulk__buttons"><span class="muted" data-q-selected>0</span>
<button type="button" class="button small" data-q-run="approve"><?= $h($t('q_bulk_approve')) ?> ✓</button>
<button type="button" class="button small outline" data-q-run="refuse"><?= $h($t('q_bulk_refuse')) ?></button></div>
<p class="hint"><?= $h($t('q_bulk_help')) ?></p>
<p class="q-bulk__status" role="status" data-q-status></p>
</div>
<?php endif ?>
<div class="table-scroll"><table class="ins-table q-table">
<thead><tr><th scope="col"><span class="sr-only"><?= $h($t('q_select')) ?></span></th>
<?php foreach (['type' => 'q_col_type', 'course' => 'ins_course', 'learner' => 'learner', 'since' => 'q_col_since'] as $sortKey => $label): ?>
<th scope="col" aria-sort="<?= $sortKey === 'type' ? 'ascending' : 'none' ?>"><button type="button" class="q-sort" data-q-sort="<?= $h($sortKey) ?>"><?= $h($t($label)) ?> <span aria-hidden="true">↕</span></button></th>
<?php endforeach ?>
<th scope="col"><?= $h($t('q_col_action')) ?></th></tr></thead>
<tbody>
<?php foreach ($qItems as $index => $qi): $qBulkData = $qBulk($qi, 'queue'); ?>
<tr id="<?= $h($qi['id']) ?>" data-q-row data-type="<?= $h($qi['type']) ?>" data-type-order="<?= (int) array_search($qi['type'], \App\Services\Academy\AcademyInstructorQueue::TYPES, true) ?>" data-course="<?= $h(mb_strtolower($qTitle($qi['course_title']))) ?>" data-learner="<?= $h(mb_strtolower($qName($qi))) ?>" data-since="<?= (int) $qi['since'] ?>" data-index="<?= (int) $index ?>">
<td><?php if ($qBulkData !== null): ?><input type="checkbox" data-q-pick data-kind="<?= $h($qi['type']) ?>" data-approve="<?= $h(json_encode($qBulkData['approve'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>" data-refuse="<?= $h(json_encode($qBulkData['refuse'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>" aria-label="<?= $h($t('q_select') . ' · ' . $qName($qi)) ?>"><?php endif ?></td>
<td data-label="<?= $h($t('q_col_type')) ?>"><span class="tag q-t-<?= $h($qi['type']) ?>"><?= $h($t('q_type_' . $qi['type'] . '_one')) ?></span></td>
<td data-label="<?= $h($t('ins_course')) ?>"><b translate="no"><?= $h($qTitle($qi['course_title'])) ?></b><small><?= $h($qDetail($qi)) ?></small></td>
<td data-label="<?= $h($t('learner')) ?>"><?= $h($qName($qi)) ?></td>
<td data-label="<?= $h($t('q_col_since')) ?>"><span title="<?= $h($qDate((int) $qi['since'])) ?>"><?= $h($qAge((int) $qi['since'])) ?></span></td>
<td data-label="<?= $h($t('q_col_action')) ?>"><?php require __DIR__ . '/queue_actions.php'; ?></td></tr>
<?php endforeach ?>
<tr data-q-empty hidden><td colspan="6"><div class="ins-empty"><b><?= $h($t('q_empty_filter')) ?></b></div></td></tr>
</tbody></table></div>
<?php foreach ($qItems as $qi) { if ($qi['type'] === 'chapter') require __DIR__ . '/queue_dialog.php'; } ?>
<?php endif ?>
</section>
