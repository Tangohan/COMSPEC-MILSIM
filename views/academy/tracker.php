<?php
/**
 * Academy › Tableau de suivi (AcademyController::trackerData). Même langage visuel que le tableau de suivi CQB de
 * l'aperçu public (views/home/partials/demo_showcase.php, onglet Formation) : en-tête de formation, indicateurs,
 * bandes de chapitres, barre de progression et pastille d'état — ici alimentés par les inscriptions réelles.
 *
 * Vue « cursus » : stagiaires × formations. Vue « formation » : stagiaires × chapitres d'une formation.
 * Toutes les actions visent des formulaires du serveur, qui revérifie chaque droit (inscrire, relancer, valider,
 * dispenser). Un stagiaire ne voit que sa ligne et aucune action.
 *
 * @var array $tracker
 */
use App\Services\Academy\AcademyTracker;

$tk = $tracker;
$m = $tk['matrix'];
$can = $tk['can'];
$isCourse = $tk['view'] === 'course';
$columns = $tk['columns'];
$focusColumn = $isCourse ? ($columns[0] ?? null) : null;
$query = $tk['query'];
$back = $query !== [] ? '?' . http_build_query($query) : '';
$trkUrl = static function (array $change = []) use ($query, $link): string {
    $q = array_filter(array_merge($query, $change), static fn ($v): bool => $v !== '' && $v !== null && $v !== 0);
    return $link('suivi') . ($q !== [] ? '?' . http_build_query($q) : '');
};
$courseTitle = static fn (array $course): string => $local($course['title'] ?? ['fr' => '']) ?: $t('untitled_course');
$badge = static function (array $course) use ($courseTitle): string {
    $code = (string) ($course['license_code'] ?? '');
    if (preg_match('/\A[A-Z0-9]{2,8}\z/', $code) === 1) return $code;
    $words = preg_split('/[\s\-—·]+/u', $courseTitle($course)) ?: [];
    $letters = '';
    foreach ($words as $word) { if (mb_strlen($word) > 2) $letters .= mb_strtoupper(mb_substr($word, 0, 1)); }
    return mb_substr($letters !== '' ? $letters : 'AC', 0, 4);
};
$initials = static fn (string $name): string => mb_strtoupper(mb_substr(trim(preg_replace('/\A[^\p{L}\p{N}]+/u', '', $name) ?? $name), 0, 2)) ?: '?';
$date = static fn (int $at): string => $at > 0 ? (new DateTimeImmutable('@' . $at))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y') : '—';
$icons = [
    'none' => '<circle cx="8" cy="8" r="5.5" stroke-dasharray="2 2"/>',
    'enrolled' => '<circle cx="8" cy="8" r="5.5"/>',
    'progress' => '<circle cx="8" cy="8" r="5.5"/><path d="M8 2.5a5.5 5.5 0 0 1 0 11z" fill="currentColor"/>',
    'review' => '<path d="M4.5 2.5h7M4.5 13.5h7M5 2.5c0 3 6 3 6 5.5S5 10.5 5 13.5M11 2.5c0 3-6 3-6 5.5s6 2.5 6 5.5"/>',
    'failed' => '<path d="M4 4l8 8M12 4l-8 8"/>',
    'validated' => '<path d="M3 8.5l3.2 3L13 4.5"/>',
    'expired' => '<path d="M13 8a5 5 0 1 1-1.6-3.7M13 2.5v3h-3"/>',
    'exempt' => '<circle cx="8" cy="8" r="5.5"/><path d="M4.2 11.8l7.6-7.6"/>',
];
$icon = static fn (string $state): string => '<svg class="trk-ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$state] ?? '') . '</svg>';
$k = $m['kpis'];
$staffView = !empty($can['detail']);
$cellActions = static fn (array $cell, int $uid, string $key): array => $staffView
    ? AcademyTracker::actions($cell, $uid, ['assign' => !empty($can['assign']), 'teach' => !empty($can['teach'][$key]), 'certify' => !empty($can['certify']), 'me' => (int) $can['me']])
    : [];
$hiddenBack = '<input type="hidden" name="back" value="' . $h($back) . '">';
$reviews = [];
$exemptible = false;
?>
<div class="page-heading"><p class="eyebrow">ATHENA / <?= $h($t('suivi')) ?></p><h1><?= $h($t('suivi')) ?></h1><p><?= $h($t($staffView ? 'trk_lead' : 'trk_lead_learner')) ?></p></div>
<section class="trk" data-trk aria-label="<?= $h($t('suivi')) ?>">
<div class="trk-bar trk-noprint">
<nav class="trk-views" aria-label="<?= $h($t('trk_views')) ?>">
<a class="trk-chip<?= !$isCourse ? ' is-on' : '' ?>" href="<?= $h($trkUrl(['vue' => '', 'course' => '', 'tous' => ''])) ?>"<?= !$isCourse ? ' aria-current="page"' : '' ?>><?= $h($t('trk_view_path')) ?></a>
<a class="trk-chip<?= $isCourse ? ' is-on' : '' ?>" href="<?= $h($trkUrl(['vue' => 'formation', 'course' => $tk['focus'], 'cols' => ''])) ?>"<?= $isCourse ? ' aria-current="page"' : '' ?>><?= $h($t('trk_view_course')) ?></a>
</nav>
<span class="trk-bar__end">
<a class="button small outline" href="<?= $h($trkUrl(['export' => 'csv', 'page' => ''])) ?>" download><?= $h($t('trk_export')) ?></a>
<button type="button" class="button small outline" data-trk-print><?= $h($t('trk_print')) ?></button>
</span>
</div>

<?php if ($columns === []): ?>
<div class="empty-state"><h2><?= $h($t('trk_empty')) ?></h2><p><?= $h($t($staffView ? 'trk_empty_help' : 'empty_text')) ?></p><a class="button" href="<?= $h($link('catalogue')) ?>"><?= $h($t('catalogue')) ?></a></div>
</section>
<?php return; endif ?>

<?php if ($focusColumn !== null): $fc = $focusColumn['course']; ?>
<div class="trk-course">
<span class="trk-course__badge" translate="no"><?= $h($badge($fc)) ?></span>
<span class="trk-course__t"><b><?= $h($courseTitle($fc)) ?></b><small><?= (int) $focusColumn['chapters'] ?> <?= $h($t('trk_chapters')) ?><?php if (!empty($fc['summary']) && is_array($fc['summary'])): ?> · <?= $h(mb_strimwidth($local($fc['summary']), 0, 140, '…')) ?><?php endif ?></small></span>
<span class="trk-course__stats"><span><b><?= (int) $k['tracked'] ?></b><?= $h($t('trk_enrolled_n')) ?></span><span><b><?= (int) $k['complete_pct'] ?> %</b><?= $h($t('trk_kpi_done')) ?></span><span><b><?= (int) $k['review'] ?></b><?= $h($t('trk_kpi_review')) ?></span></span>
</div>
<?php endif ?>

<div class="trk-kpis">
<div class="trk-kpi"><small><?= $h($t('trk_kpi_done')) ?></small><b><?= (int) $k['complete_pct'] ?><em> %</em></b><span class="trk-delta"><?= (int) ($k['validated'] + $k['expired'] + $k['exempt']) ?> / <?= (int) $k['tracked'] ?> <?= $h($t('trk_kpi_done_d')) ?></span></div>
<a class="trk-kpi<?= $k['review'] > 0 ? ' is-warn' : '' ?>" href="<?= $h($trkUrl(['etat' => 'review', 'page' => ''])) ?>"><small><?= $h($t('trk_kpi_review')) ?></small><b><?= (int) $k['review'] ?></b><span class="trk-delta"><?= $h($t('trk_kpi_review_d')) ?></span></a>
<a class="trk-kpi<?= $k['late'] > 0 ? ' is-late' : '' ?>" href="<?= $h($trkUrl(['etat' => 'late', 'page' => ''])) ?>"><small><?= $h($t('trk_kpi_late')) ?></small><b><?= (int) $k['late'] ?></b><span class="trk-delta"><?= $h(sprintf($t('trk_kpi_late_d'), AcademyTracker::LATE_DAYS)) ?></span></a>
<a class="trk-kpi<?= $k['expired'] > 0 ? ' is-warn' : '' ?>" href="<?= $h($trkUrl(['etat' => 'expired', 'page' => ''])) ?>"><small><?= $h($t('trk_kpi_expired')) ?></small><b><?= (int) $k['expired'] ?></b><span class="trk-delta"><?= $h($t('trk_kpi_expired_d')) ?></span></a>
</div>

<?php if ($staffView): $o = $tk['options']; $f = $tk['filters']; ?>
<form class="trk-filters trk-noprint" method="get" action="<?= $h($link('suivi')) ?>" role="search">
<?php if ($isCourse): ?><input type="hidden" name="vue" value="formation">
<label><span><?= $h($t('ins_course')) ?></span><select name="course"><?php foreach ($tk['courses'] as $ck => $cc): ?><option value="<?= $h($ck) ?>"<?= $ck === $tk['focus'] ? ' selected' : '' ?>><?= $h($courseTitle($cc)) ?> (<?= (int) ($tk['counts'][$ck] ?? 0) ?>)</option><?php endforeach ?></select></label>
<?php elseif (!empty($query['cols'])): ?><input type="hidden" name="cols" value="<?= $h($query['cols']) ?>"><?php endif ?>
<?php foreach (['unit' => 'unite', 'job' => 'fonction', 'group' => 'cohorte'] as $facet => $param): if (empty($o[$facet])) continue; ?>
<label><span><?= $h($t('trk_f_' . $facet)) ?></span><select name="<?= $h($param) ?>"><option value=""><?= $h($t('trk_all')) ?></option><?php foreach ($o[$facet] as $id => $label): ?><option value="<?= (int) $id ?>"<?= (int) $f[$facet] === (int) $id ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select></label>
<?php endforeach ?>
<?php if (!empty($o['grade'])): ?><label><span><?= $h($t('trk_f_grade')) ?></span><select name="grade"><option value=""><?= $h($t('trk_all')) ?></option><?php foreach ($o['grade'] as $g): ?><option value="<?= $h($g) ?>"<?= $f['grade'] === $g ? ' selected' : '' ?>><?= $h($g) ?></option><?php endforeach ?></select></label><?php endif ?>
<label><span><?= $h($t('trk_f_state')) ?></span><select name="etat"><option value=""><?= $h($t('trk_all')) ?></option><?php foreach (array_merge(AcademyTracker::STATES, ['late']) as $st): ?><option value="<?= $h($st) ?>"<?= $tk['state'] === $st ? ' selected' : '' ?>><?= $h($t($st === 'late' ? 'trk_late' : 'trk_state_' . $st)) ?></option><?php endforeach ?></select></label>
<label class="trk-filters__q"><span><?= $h($t('trk_f_search')) ?></span><input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="<?= $h($t('trk_f_search_ph')) ?>"></label>
<?php if ($isCourse): ?><label class="trk-check"><input type="checkbox" name="tous" value="1"<?= !$tk['onlyEnrolled'] ? ' checked' : '' ?>> <?= $h($t('trk_f_everyone')) ?></label><?php endif ?>
<button class="button small"><?= $h($t('trk_apply')) ?></button>
<?php if (array_diff_key($query, ['vue' => 1, 'course' => 1, 'cols' => 1]) !== []): ?><a class="text-link" href="<?= $h($link('suivi') . ($isCourse ? '?vue=formation&course=' . rawurlencode($tk['focus']) : (!empty($query['cols']) ? '?cols=' . rawurlencode($query['cols']) : ''))) ?>"><?= $h($t('trk_reset')) ?></a><?php endif ?>
</form>

<?php if (!$isCourse): $chosen = array_column($columns, 'key'); ?>
<details class="trk-cols trk-noprint"><summary><?= $h(sprintf($t('trk_cols'), count($columns))) ?></summary>
<form method="get" action="<?= $h($link('suivi')) ?>" data-trk-cols>
<?php foreach (array_diff_key($query, ['cols' => 1, 'page' => 1]) as $qk => $qv): ?><input type="hidden" name="<?= $h($qk) ?>" value="<?= $h($qv) ?>"><?php endforeach ?>
<input type="hidden" name="cols" value="<?= $h(implode(',', $chosen)) ?>" data-trk-cols-value>
<ul><?php foreach ($tk['courses'] as $ck => $cc): ?><li><label><input type="checkbox" value="<?= $h($ck) ?>"<?= in_array($ck, $chosen, true) ? ' checked' : '' ?> data-trk-col> <?= $h($courseTitle($cc)) ?> <small>(<?= (int) ($tk['counts'][$ck] ?? 0) ?>)</small></label></li><?php endforeach ?></ul>
<button class="button small"><?= $h($t('trk_apply')) ?></button>
</form></details>
<?php endif ?>

<div class="trk-bulk trk-noprint">
<form method="post" id="trk-sel" action="<?= $h($link('action/tracker_enroll')) ?>" class="trk-bulk__sel"><?= \App\Core\Csrf::field() ?><?= $hiddenBack ?>
<span><b data-trk-count>0</b> <?= $h($t('trk_selected')) ?></span>
<select name="course" aria-label="<?= $h($t('ins_course')) ?>"><?php foreach ($columns as $col): ?><option value="<?= $h($col['key']) ?>"><?= $h($courseTitle($col['course'])) ?></option><?php endforeach ?></select>
<?php if (!empty($can['assign'])): ?><button class="button small" formaction="<?= $h($link('action/tracker_enroll')) ?>" data-trk-needs-pick disabled><?= $h($t('trk_enroll_sel')) ?></button><?php endif ?>
<button class="button small outline" formaction="<?= $h($link('action/tracker_remind')) ?>" data-trk-needs-pick disabled><?= $h($t('trk_remind_sel')) ?></button>
</form>
<?php if (!empty($can['assign']) && !empty($o['unit'])): ?>
<form method="post" action="<?= $h($link('action/tracker_enroll')) ?>" class="trk-bulk__unit"><?= \App\Core\Csrf::field() ?><?= $hiddenBack ?>
<select name="unit" aria-label="<?= $h($t('trk_f_unit')) ?>" required><option value=""><?= $h($t('trk_pick_unit')) ?></option><?php foreach ($o['unit'] as $id => $label): ?><option value="<?= (int) $id ?>"<?= (int) $f['unit'] === (int) $id ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach ?></select>
<select name="course" aria-label="<?= $h($t('ins_course')) ?>"><?php foreach ($tk['courses'] as $ck => $cc): ?><option value="<?= $h($ck) ?>"<?= $ck === ($columns[0]['key'] ?? '') ? ' selected' : '' ?>><?= $h($courseTitle($cc)) ?></option><?php endforeach ?></select>
<button class="button small"><?= $h($t('trk_enroll_unit')) ?></button>
</form>
<?php endif ?>
<form method="post" action="<?= $h($link('action/tracker_remind')) ?>" class="trk-bulk__late"><?= \App\Core\Csrf::field() ?><?= $hiddenBack ?><input type="hidden" name="scope" value="late">
<select name="course" aria-label="<?= $h($t('ins_course')) ?>"><?php foreach ($columns as $col): ?><option value="<?= $h($col['key']) ?>"><?= $h($courseTitle($col['course'])) ?></option><?php endforeach ?></select>
<button class="button small outline"<?= $k['late'] === 0 ? ' disabled' : '' ?>><?= $h($t('trk_remind_late')) ?></button>
</form>
</div>
<form method="post" id="trk-act" action="<?= $h($link('action/tracker_enroll')) ?>" hidden><?= \App\Core\Csrf::field() ?><?= $hiddenBack ?></form>
<?php endif ?>

<?php if ($m['rows'] === []): ?>
<p class="trk-empty"><b><?= $h($t('trk_no_rows')) ?></b> <?= $h($t('trk_no_rows_help')) ?></p>
<?php elseif ($isCourse): $fk = $focusColumn['key']; $chapters = $focusColumn['course']['chapters'] ?? []; $n = (int) $focusColumn['chapters']; ?>
<div class="trk-mods" aria-hidden="true"><span></span><span class="trk-mods__list" style="--n:<?= $n ?>"><?php foreach ($chapters as $ci => $ch): ?><span title="<?= $h($local($ch['title'] ?? ['fr' => ''])) ?>"><?= $ci + 1 ?>. <?= $h($local($ch['title'] ?? ['fr' => ''])) ?></span><?php endforeach ?></span><span><?= $h($t('trk_progress')) ?></span><span><?= $h($t('trk_status')) ?></span><span></span></div>
<ul class="trk-trainees">
<?php foreach ($m['rows'] as $r): $mem = $r['member']; $uid = (int) $mem['id']; $cell = $r['cells'][$fk]; $acts = $cellActions($cell, $uid, $fk); ?>
<li data-state="<?= $h($cell['state']) ?>">
<span class="trk-op"><?php if ($staffView): ?><input type="checkbox" name="members[]" value="<?= $uid ?>" form="trk-sel" data-trk-pick aria-label="<?= $h($mem['name']) ?>"><?php endif ?><i><?= $h($initials($mem['name'])) ?></i><span><b><?= $h($mem['name']) ?></b><small><?= $h(implode(' · ', array_filter([$mem['grade'], $mem['unit']]))) ?: '—' ?></small></span></span>
<span class="trk-steps" style="--n:<?= $n ?>"><?php foreach (AcademyTracker::steps($cell, $n) as $si => $step): ?><i class="is-<?= $h($step) ?>" title="<?= $h(($si + 1) . '. ' . $local($chapters[$si]['title'] ?? ['fr' => '']) . ' · ' . $t('trk_step_' . $step)) ?>"></i><?php endforeach ?></span>
<span class="trk-prog"><span class="trk-meter<?= $cell['late'] ? ' is-low' : '' ?>"><i style="width:<?= (int) $cell['pct'] ?>%"></i></span><span class="trk-mono"><?= (int) $cell['pct'] ?> %</span></span>
<span class="trk-state">
<span class="trk-st trk-st--<?= $h($cell['state']) ?>"><?= $icon($cell['state']) ?><?= $h($t('trk_state_' . $cell['state'])) ?></span>
<?php if ($cell['late']): ?><span class="trk-st trk-st--late" title="<?= $h(sprintf($t('trk_last_activity'), $date((int) $cell['since']))) ?>"><?= $h($t('trk_late')) ?></span><?php endif ?>
</span>
<span class="trk-end"><?php require __DIR__ . '/tracker_actions.php'; ?></span>
</li>
<?php endforeach ?>
</ul>
<?php else: ?>
<div class="trk-tablewrap">
<table class="trk-table">
<thead><tr><th scope="col" class="trk-table__who"><?php if ($staffView): ?><input type="checkbox" data-trk-all aria-label="<?= $h($t('trk_select_all')) ?>"> <?php endif ?><?= $h($t('trk_learner')) ?></th>
<?php foreach ($columns as $col): ?><th scope="col"><a href="<?= $h($trkUrl(['vue' => 'formation', 'course' => $col['key'], 'cols' => '', 'etat' => '', 'page' => ''])) ?>" title="<?= $h($courseTitle($col['course'])) ?>"><span class="trk-badge" translate="no"><?= $h($badge($col['course'])) ?></span><span class="trk-th__t"><?= $h($courseTitle($col['course'])) ?></span></a></th><?php endforeach ?>
</tr></thead>
<tbody>
<?php foreach ($m['rows'] as $r): $mem = $r['member']; $uid = (int) $mem['id']; ?>
<tr>
<th scope="row"><span class="trk-op"><?php if ($staffView): ?><input type="checkbox" name="members[]" value="<?= $uid ?>" form="trk-sel" data-trk-pick aria-label="<?= $h($mem['name']) ?>"><?php endif ?><i><?= $h($initials($mem['name'])) ?></i><span><b><?= $h($mem['name']) ?></b><small><?= $h(implode(' · ', array_filter([$mem['grade'], $mem['unit']]))) ?: '—' ?></small></span></span></th>
<?php foreach ($columns as $col): $fk = $col['key']; $cell = $r['cells'][$fk]; $acts = $cellActions($cell, $uid, $fk); $label = $t('trk_state_' . $cell['state']); ?>
<td class="trk-cell trk-cell--<?= $h($cell['state']) ?><?= $cell['late'] ? ' is-late' : '' ?>">
<?php if ($cell['state'] === 'none' && $acts === []): ?><span class="trk-dash" title="<?= $h($label) ?>"><?= $icon('none') ?><span class="sr-only"><?= $h($label) ?></span></span>
<?php else: ?>
<details class="trk-pop"><summary title="<?= $h($courseTitle($col['course']) . ' · ' . $label) ?>"><?= $icon($cell['state']) ?><span class="trk-cell__l"><?= $h($label) ?></span><?php if (in_array($cell['state'], ['progress', 'review', 'failed'], true)): ?><span class="trk-mono"><?= (int) $cell['pct'] ?>%</span><?php endif ?></summary>
<div class="trk-pop__body">
<p><b><?= $h($courseTitle($col['course'])) ?></b></p>
<?php require __DIR__ . '/tracker_detail.php'; ?>
<?php require __DIR__ . '/tracker_actions.php'; ?>
</div></details>
<?php endif ?>
</td>
<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<?php endif ?>

<?php if ($m['pages'] > 1): ?>
<nav class="trk-pages trk-noprint" aria-label="<?= $h($t('trk_pages')) ?>">
<?php if ($m['page'] > 1): ?><a class="trk-chip" href="<?= $h($trkUrl(['page' => $m['page'] - 1])) ?>">← <?= $h($t('trk_prev')) ?></a><?php endif ?>
<span><?= $h(sprintf($t('trk_page_of'), $m['page'], $m['pages'], $m['total'])) ?></span>
<?php if ($m['page'] < $m['pages']): ?><a class="trk-chip" href="<?= $h($trkUrl(['page' => $m['page'] + 1])) ?>"><?= $h($t('trk_next')) ?> →</a><?php endif ?>
</nav>
<?php elseif ($m['total'] > 0): ?><p class="trk-foot"><?= $h(sprintf($t('trk_total'), $m['total'])) ?></p><?php endif ?>

<ul class="trk-legend" aria-label="<?= $h($t('trk_legend')) ?>">
<?php foreach (AcademyTracker::STATES as $st): ?><li><span class="trk-st trk-st--<?= $h($st) ?>"><?= $icon($st) ?><?= $h($t('trk_state_' . $st)) ?></span><small><?= $h($t('trk_state_' . $st . '_d')) ?></small></li><?php endforeach ?>
</ul>

<?php foreach ($reviews as [$rUid, $rKey, $rCell, $rName]): require __DIR__ . '/tracker_review.php'; endforeach ?>
<?php if ($exemptible): ?>
<dialog class="q-dialog" id="trk-exempt" aria-labelledby="trk-exempt-t">
<div class="q-dialog__head"><div><p class="eyebrow"><?= $h($t('trk_exempt')) ?></p><h2 id="trk-exempt-t" data-trk-exempt-title><?= $h($t('trk_exempt')) ?></h2></div><button type="button" class="q-dialog__close" data-q-close aria-label="<?= $h($t('q_close')) ?>">×</button></div>
<form method="post" action="<?= $h($link('action/tracker_exempt')) ?>" class="q-dialog__body"><?= \App\Core\Csrf::field() ?><?= $hiddenBack ?><input type="hidden" name="cell" value="" data-trk-exempt-cell>
<p class="hint"><?= $h($t('trk_exempt_help')) ?></p>
<label for="trk-exempt-c"><?= $h($t('trk_exempt_reason')) ?></label><textarea id="trk-exempt-c" name="comment" rows="3" maxlength="1000"></textarea>
<div class="q-dialog__foot"><button type="button" class="button outline" data-q-close><?= $h($t('q_later')) ?></button><button class="button"><?= $h($t('trk_exempt')) ?></button></div>
</form>
</dialog>
<?php endif ?>
</section>
