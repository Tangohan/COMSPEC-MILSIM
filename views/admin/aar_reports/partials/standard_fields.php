<?php
declare(strict_types=1);

/**
 * Formulaire standard : synthèse, points forts / faibles, enseignements, conclusion (actions : actions_fields.php).
 *
 * @var array<string, mixed> $report
 * @var callable $val
 * @var callable $h
 * @var callable $stepHead
 */
$items = static fn (mixed $list): array => array_values(array_filter(
    array_map(static fn ($i) => trim(is_array($i) ? (string) ($i['label'] ?? '') : (string) $i), is_array($list) ? $list : []),
    static fn (string $s): bool => $s !== ''
));
$pointList = static function (string $name, array $values, string $tone, string $label, string $placeholder) use ($h): string {
    $row = static fn (string $v): string => '<li><input name="' . $name . '[]" value="' . $h($v) . '" placeholder="' . $h($placeholder) . '" maxlength="500" aria-label="' . $h($label) . '"><button type="button" class="bo-aar-x" data-aar-list-del aria-label="Retirer" title="Retirer">×</button></li>';
    $out = '<div class="bo-aar-list bo-aar-list--' . $tone . '" data-aar-list>'
        . '<p class="bo-aar-list__t">' . $h($label) . ' <span data-aar-list-count></span></p>'
        . '<ol class="bo-aar-list__items" data-aar-list-items>';
    foreach ($values !== [] ? $values : [''] as $v) {
        $out .= $row($v);
    }

    return $out . '</ol><template data-aar-list-tpl>' . $row('') . '</template>'
        . '<button type="button" class="bo-aar-list__add" data-aar-list-add>+ Ajouter</button></div>';
};
$frame = "Prévu : \nRéalisé : \nÉcarts et causes : \nRésultat : ";
?>
<section class="ath-panel bo-aar-step" id="etape-synthese">
  <?= $stepHead('summary', 'Synthèse', 'En quelques lignes : ce qui était prévu, ce qui s’est réellement passé, pourquoi il y a eu des écarts, et le résultat.') ?>
  <label class="bo-aar-field">
    <span>Phrase de synthèse</span>
    <input name="summary_heading" maxlength="200" value="<?= $h($val('summary_heading')) ?>" placeholder="Ex. Objectif tenu à H+40, sans perte">
    <small>Elle sert de titre à la section : la phrase qu’on retient si on ne lit que ça.</small>
  </label>
  <label class="bo-aar-field">
    <span>Déroulé et résultat</span>
    <textarea name="summary_text" rows="6" placeholder="Prévu : prise du village de Pyrgos avant l’aube.&#10;Réalisé : départ avec 12 min de retard, assaut à 05:10, objectif tenu à 05:50.&#10;Écarts et causes : retard au point de départ (convoi mal regroupé).&#10;Résultat : mission remplie, deux blessés légers évacués." data-aar-check-src="summary" data-aar-frame-target><?= $h($val('summary_text')) ?></textarea>
  </label>
  <div class="bo-aar-step__foot">
    <button type="button" class="ath-btn" data-aar-frame="<?= $h($frame) ?>">Insérer la trame Prévu / Réalisé / Écarts / Résultat</button>
    <span class="bo-aar-muted" data-aar-counter></span>
  </div>
</section>

<section class="ath-panel bo-aar-step" id="etape-points">
  <?= $stepHead('points', 'Points relevés', 'Un point par ligne, factuel et court. Appuyez sur Entrée pour passer au point suivant.') ?>
  <div class="bo-aar-grid bo-aar-grid--2">
    <?= $pointList('strengths', $items($report['strengths'] ?? []), 'ok', 'Ce qui a fonctionné', 'Ex. Discipline radio exemplaire') ?>
    <?= $pointList('weaknesses', $items($report['weaknesses'] ?? []), 'warn', 'Ce qui doit progresser', 'Ex. Carte du secteur nord périmée') ?>
  </div>
  <details class="bo-aar-example">
    <summary>Comment bien formuler un point ?</summary>
    <ul>
      <li><b>Factuel :</b> « Appui mortier déclenché en 40 s après la demande » plutôt que « bon appui ».</li>
      <li><b>Sans nom :</b> on parle d’un groupe ou d’une fonction, pas d’une personne.</li>
      <li><b>Un point faible appelle une action</b> : reprenez-le dans l’étape « Actions décidées ».</li>
    </ul>
  </details>
</section>

<section class="ath-panel bo-aar-step" id="etape-enseignements">
  <?= $stepHead('lessons', 'Enseignements et conclusion', 'Ce que l’unité doit retenir pour la suite, et l’avis du commandement sur l’opération.') ?>
  <div class="bo-aar-grid bo-aar-grid--2">
    <label class="bo-aar-field">
      <span>Enseignements</span>
      <textarea name="lessons_learned" rows="4" placeholder="Ex. Reconnaître les itinéraires de repli avant H, pas pendant l’action." data-aar-check-src="lessons"><?= $h($val('lessons_learned')) ?></textarea>
    </label>
    <label class="bo-aar-field">
      <span>Conclusion du commandement</span>
      <textarea name="conclusion_text" rows="4" placeholder="Ex. Mission remplie. Bonne cohésion des groupes, effort à porter sur la préparation."><?= $h($val('conclusion_text')) ?></textarea>
    </label>
  </div>
  <label class="bo-aar-field">
    <span>À retenir pour…</span>
    <input name="lessons_context" maxlength="200" value="<?= $h($val('lessons_context')) ?>" placeholder="Ex. la prochaine opération de nuit">
    <small>Complète le titre de la section : « À retenir pour la prochaine opération de nuit ».</small>
  </label>
</section>
