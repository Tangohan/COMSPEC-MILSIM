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
$ico = $ico ?? require base_path('views/admin/aar_reports/partials/icons.php');
$stepHead = $stepHead ?? static fn (string $i, string $t, string $l): string => '<header class="bo-aar-step__head"><span class="bo-aar-step__n" aria-hidden="true"></span><span class="bo-aar-step__txt"><b class="bo-aar-step__t">' . $h($t) . '</b><span class="bo-aar-step__lead">' . $h($l) . '</span></span></header>';
$actionRow = static function (int|string $i, array $a) use ($h): string {
    $closed = ($a['status'] ?? 'open') === 'closed';

    return '<li class="bo-aar-act' . ($closed ? ' is-closed' : '') . '">'
        . '<label class="bo-aar-act__label"><span class="bo-aar-vh">Action</span><input name="actions[' . $i . '][label]" value="' . $h($a['label'] ?? '') . '" placeholder="Ex. Mettre à jour la carte du secteur nord" maxlength="500" data-aar-act-label></label>'
        . '<label class="bo-aar-act__owner"><span>Responsable</span><input name="actions[' . $i . '][owner]" value="' . $h($a['owner'] ?? '') . '" placeholder="Indicatif ou nom" maxlength="160"></label>'
        . '<label class="bo-aar-act__due"><span>Échéance</span><input type="date" name="actions[' . $i . '][due_at]" value="' . $h($a['due_at'] ?? '') . '"></label>'
        . '<label class="bo-aar-act__state"><span>État</span><select name="actions[' . $i . '][status]" data-aar-act-state><option value="open"' . ($closed ? '' : ' selected') . '>En cours</option><option value="closed"' . ($closed ? ' selected' : '') . '>Close</option></select>'
        . '<input type="hidden" name="actions[' . $i . '][closed_at]" value="' . $h($a['closed_at'] ?? '') . '"></label>'
        . '<button type="button" class="bo-aar-x" data-aar-del aria-label="Retirer l’action" title="Retirer">×</button>'
        . '</li>';
};
?>
<section class="ath-panel bo-aar-step" data-aar-actions id="etape-actions">
  <?= $stepHead('actions', 'Actions décidées', 'Ce qu’on change avant la prochaine fois. Chaque action a un responsable et une échéance : elle reste dans le suivi tant qu’elle n’est pas close.') ?>
  <ol class="bo-aar-acts" data-aar-rows>
    <?php foreach ($actionRows as $i => $a) { echo $actionRow($i, $a); } ?>
  </ol>
  <template data-aar-row-tpl><?= $actionRow('__i__', []) ?></template>
  <div class="bo-aar-step__foot">
    <button type="button" class="ath-btn" data-aar-add>+ Ajouter une action</button>
    <details class="bo-aar-example">
      <summary>Exemples d’actions bien formulées</summary>
      <ul>
        <li><b>Mettre à jour la carte du secteur nord</b> — Kodiak — avant la prochaine opération</li>
        <li><b>Répéter la procédure EVASAN de nuit</b> — Doc — entraînement du 2 novembre</li>
        <li><b>Ajouter un point radio à H-15 dans le briefing type</b> — chef du PC</li>
      </ul>
      <p>Une action vague (« mieux communiquer ») ne se clôt jamais : préférez un geste concret et vérifiable.</p>
    </details>
  </div>
  <input type="hidden" name="actions[__present]" value="1">
</section>
