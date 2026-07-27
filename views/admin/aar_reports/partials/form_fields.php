<?php
declare(strict_types=1);

/**
 * Champs de formulaire compte rendu post-op (création / édition).
 *
 * @var array<string, mixed> $report
 * @var bool $isEdit
 */

$report = is_array($report ?? null) ? $report : [];
$missions = is_array($missions ?? null) ? $missions : (is_array($aarMissions ?? null) ? $aarMissions : []);
$isEdit = !empty($isEdit);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$val = static function (string $key, string $default = '') use ($report): string {
    $v = $report[$key] ?? $default;

    return is_scalar($v) ? trim((string) $v) : $default;
};

$linesFromList = static function (array $items): string {
    $lines = [];
    foreach ($items as $item) {
        if (is_string($item)) {
            $t = trim($item);
            if ($t !== '') {
                $lines[] = $t;
            }
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $owner = trim((string) ($item['owner'] ?? ''));
        $lines[] = $owner !== '' ? $label . ' — ' . $owner : $label;
    }

    return implode("\n", $lines);
};

$strengthsText = $linesFromList(is_array($report['strengths'] ?? null) ? $report['strengths'] : []);
$weaknessesText = $linesFromList(is_array($report['weaknesses'] ?? null) ? $report['weaknesses'] : []);
$openActionsText = $linesFromList(is_array($report['open_actions'] ?? null) ? $report['open_actions'] : []);
$closedActionsText = $linesFromList(is_array($report['closed_actions'] ?? null) ? $report['closed_actions'] : []);

$status = $val('status', 'pending');
$missionId = (int) ($report['mission_cycle_id'] ?? 0);
?>

<label for="aar-title">Titre</label>
<input id="aar-title" name="title" value="<?= $h($val('title')) ?>" placeholder="Titre du compte rendu">

<label for="aar-operation">Opération</label>
<input id="aar-operation" name="operation_label" value="<?= $h($val('operation_label')) ?>" placeholder="Nom de l’opération">

<label for="aar-mission">Mission liée</label>
<select id="aar-mission" name="mission_cycle_id">
    <option value="">Facultatif</option>
    <?php foreach ($missions as $mission): ?>
    <?php $mid = (int) ($mission['id'] ?? 0); ?>
    <option value="<?= $mid ?>" <?= $missionId === $mid ? 'selected' : '' ?>><?= $h((string) ($mission['title'] ?? 'Mission')) ?></option>
    <?php endforeach; ?>
</select>

<?php if ($isEdit): ?>
<label for="aar-status">Statut</label>
<select id="aar-status" name="status" class="bo-select">
    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>En attente</option>
    <option value="in_review" <?= $status === 'in_review' ? 'selected' : '' ?>>En relecture</option>
    <option value="validated" <?= $status === 'validated' ? 'selected' : '' ?>>Validé</option>
    <option value="missing" <?= $status === 'missing' ? 'selected' : '' ?>>Manquant</option>
</select>
<?php endif; ?>

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
