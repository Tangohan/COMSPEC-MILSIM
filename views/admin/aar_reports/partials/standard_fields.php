<?php
declare(strict_types=1);

/**
 * Formulaire standard (synthèse, points, actions).
 *
 * @var callable(string, string=):string $val
 * @var callable(string):string $h
 * @var string $strengthsText
 * @var string $weaknessesText
 * @var string $openActionsText
 * @var string $closedActionsText
 */
?>
<label for="aar-summary-heading">Titre de synthèse</label>
<input id="aar-summary-heading" name="summary_heading" value="<?= $h($val('summary_heading')) ?>" placeholder="Ex. Mission remplie sans perte">

<label for="aar-summary">Synthèse</label>
<textarea id="aar-summary" name="summary_text" rows="4" placeholder="Résumé opérationnel"><?= $h($val('summary_text')) ?></textarea>

<label for="aar-strengths">Points forts (une ligne par point)</label>
<textarea id="aar-strengths" name="strengths" rows="3"><?= $h($strengthsText) ?></textarea>

<label for="aar-weaknesses">Points faibles (une ligne par point)</label>
<textarea id="aar-weaknesses" name="weaknesses" rows="3"><?= $h($weaknessesText) ?></textarea>

<label for="aar-lessons">Enseignements</label>
<textarea id="aar-lessons" name="lessons_learned" rows="2"><?= $h($val('lessons_learned')) ?></textarea>

<label for="aar-lessons-ctx">Contexte enseignements</label>
<input id="aar-lessons-ctx" name="lessons_context" value="<?= $h($val('lessons_context')) ?>" placeholder="Ex. prochaine opération">

<label for="aar-conclusion">Conclusion</label>
<textarea id="aar-conclusion" name="conclusion_text" rows="2"><?= $h($val('conclusion_text')) ?></textarea>

<label for="aar-open-actions">Actions en cours (une ligne par action)</label>
<textarea id="aar-open-actions" name="open_actions" rows="2" placeholder="Libellé — responsable"><?= $h($openActionsText) ?></textarea>

<label for="aar-closed-actions">Actions closes (une ligne par action)</label>
<textarea id="aar-closed-actions" name="closed_actions" rows="2"><?= $h($closedActionsText) ?></textarea>
