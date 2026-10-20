<?php
declare(strict_types=1);

/**
 * Formulaire standard : synthèse, points forts / faibles, enseignements, conclusion (actions : actions_fields.php).
 *
 * @var array<string, mixed> $report
 * @var callable $val
 * @var callable $h
 */
$lines = static fn (mixed $items): string => implode("\n", array_values(array_filter(
    array_map(static fn ($i) => trim(is_array($i) ? (string) ($i['label'] ?? '') : (string) $i), is_array($items) ? $items : []),
    static fn (string $s): bool => $s !== ''
)));
?>
<fieldset class="ath-panel bo-aar-fs">
  <legend class="bo-aar-fs__legend">1 · Synthèse</legend>
  <label class="bo-aar-field">
    <span>Titre de synthèse</span>
    <input name="summary_heading" maxlength="200" value="<?= $h($val('summary_heading')) ?>" placeholder="Ex. Mission remplie sans perte">
  </label>
  <label class="bo-aar-field">
    <span>Déroulé et résultat</span>
    <textarea name="summary_text" rows="5" placeholder="Ce qui était prévu, ce qui s’est passé, le résultat obtenu."><?= $h($val('summary_text')) ?></textarea>
  </label>
</fieldset>

<fieldset class="ath-panel bo-aar-fs">
  <legend class="bo-aar-fs__legend">2 · Points relevés</legend>
  <div class="bo-aar-grid bo-aar-grid--2">
    <label class="bo-aar-field">
      <span class="bo-aar-ok">Points forts</span>
      <textarea name="strengths" rows="5" placeholder="Un point par ligne"><?= $h($lines($report['strengths'] ?? [])) ?></textarea>
    </label>
    <label class="bo-aar-field">
      <span class="bo-aar-warn">Points faibles</span>
      <textarea name="weaknesses" rows="5" placeholder="Un point par ligne"><?= $h($lines($report['weaknesses'] ?? [])) ?></textarea>
    </label>
  </div>
</fieldset>

<fieldset class="ath-panel bo-aar-fs">
  <legend class="bo-aar-fs__legend">3 · Enseignements et conclusion</legend>
  <div class="bo-aar-grid bo-aar-grid--2">
    <label class="bo-aar-field">
      <span>Enseignements</span>
      <textarea name="lessons_learned" rows="4" placeholder="Ce qu’il faut retenir"><?= $h($val('lessons_learned')) ?></textarea>
    </label>
    <label class="bo-aar-field">
      <span>Conclusion du commandement</span>
      <textarea name="conclusion_text" rows="4" placeholder="Avis général"><?= $h($val('conclusion_text')) ?></textarea>
    </label>
  </div>
  <label class="bo-aar-field">
    <span>Enseignements à retenir pour…</span>
    <input name="lessons_context" maxlength="200" value="<?= $h($val('lessons_context')) ?>" placeholder="Ex. la prochaine opération de nuit">
  </label>
</fieldset>
