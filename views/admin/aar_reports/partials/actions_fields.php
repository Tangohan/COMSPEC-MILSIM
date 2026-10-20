<?php
declare(strict_types=1);

/**
 * Tableau éditable des actions décidées (libellé, responsable, échéance, état).
 *
 * @var array<string, mixed> $report
 * @var callable $h
 */
$actionRows = [];
foreach (['open_actions' => 'open', 'closed_actions' => 'closed'] as $key => $state) {
    foreach (is_array($report[$key] ?? null) ? $report[$key] : [] as $a) {
        if (is_array($a) && trim((string) ($a['label'] ?? '')) !== '') {
            $actionRows[] = [
                'label' => (string) $a['label'],
                'owner' => (string) ($a['owner'] ?? ''),
                'due_at' => !empty($a['due_at']) ? date('Y-m-d', strtotime((string) $a['due_at']) ?: time()) : '',
                'closed_at' => !empty($a['closed_at']) ? date('Y-m-d', strtotime((string) $a['closed_at']) ?: time()) : '',
                'status' => $state,
            ];
        }
    }
}
$actionRow = static function (int|string $i, array $a) use ($h): string {
    $closed = ($a['status'] ?? 'open') === 'closed';

    return '<tr class="bo-aar-act">'
        . '<td><input name="actions[' . $i . '][label]" value="' . $h($a['label'] ?? '') . '" placeholder="Action à mener" aria-label="Action" maxlength="500"></td>'
        . '<td><input name="actions[' . $i . '][owner]" value="' . $h($a['owner'] ?? '') . '" placeholder="Responsable" aria-label="Responsable" maxlength="160"></td>'
        . '<td><input type="date" name="actions[' . $i . '][due_at]" value="' . $h($a['due_at'] ?? '') . '" aria-label="Échéance"></td>'
        . '<td><select name="actions[' . $i . '][status]" aria-label="État"><option value="open"' . ($closed ? '' : ' selected') . '>En cours</option><option value="closed"' . ($closed ? ' selected' : '') . '>Close</option></select>'
        . '<input type="hidden" name="actions[' . $i . '][closed_at]" value="' . $h($a['closed_at'] ?? '') . '"></td>'
        . '<td><button type="button" class="ath-btn bo-aar-icon" data-aar-del aria-label="Retirer l’action">×</button></td>'
        . '</tr>';
};
?>
<fieldset class="ath-panel bo-aar-fs" data-aar-actions>
  <legend class="bo-aar-fs__legend"><?= $h($actionsLegend ?? '4 · Actions décidées') ?></legend>
  <p class="bo-aar-muted">Chaque action a un responsable et une échéance : elle apparaît dans le suivi des actions jusqu’à sa clôture.</p>
  <div class="ath-table-wrap">
    <table class="ath-table bo-aar-acts">
      <thead><tr><th>Action</th><th>Responsable</th><th>Échéance</th><th>État</th><th></th></tr></thead>
      <tbody data-aar-rows>
        <?php foreach ($actionRows as $i => $a) { echo $actionRow($i, $a); } ?>
      </tbody>
    </table>
  </div>
  <template data-aar-row-tpl><?= $actionRow('__i__', []) ?></template>
  <p><button type="button" class="ath-btn" data-aar-add>+ Ajouter une action</button></p>
  <input type="hidden" name="actions[__present]" value="1">
</fieldset>
