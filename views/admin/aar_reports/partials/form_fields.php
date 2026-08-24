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
$isCustomReport = $isEdit && !empty($report['is_custom']);
$templates = is_array($aarTemplates ?? null) ? $aarTemplates : [];
$canManageTemplates = !empty($aarCanManageTemplates);
?>

<label for="aar-title">Titre</label>
<input id="aar-title" name="title" required value="<?= $h($val('title')) ?>" placeholder="Titre du compte rendu">

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

<?php if (!$isEdit): ?>
<label for="aar-template">Formulaire</label>
<select id="aar-template" name="template_id" x-model.number="templateId">
    <option value="0">Formulaire standard</option>
    <?php foreach ($templates as $tpl): ?>
    <option value="<?= (int) ($tpl['id'] ?? 0) ?>"><?= $h((string) ($tpl['title'] ?? 'Modèle')) ?></option>
    <?php endforeach; ?>
</select>
<p class="ath-aar-custom-q__help">
    Le formulaire standard reprend les points forts, points faibles et actions.
    <?php if ($canManageTemplates): ?>
        <a href="<?= $h(url('back-office/atak/comptes-rendus/modeles')) ?>">Gérer les modèles</a>
    <?php endif; ?>
</p>
<?php endif; ?>

<?php if ($isCustomReport): ?>
    <?php
    $customFields = is_array($report['custom_fields'] ?? null) ? $report['custom_fields'] : [];
    $customAnswers = is_array($report['custom_answers'] ?? null) ? $report['custom_answers'] : [];
    require base_path('views/admin/aar_reports/partials/custom_fields.php');
    ?>
<?php elseif ($isEdit): ?>
    <?php require base_path('views/admin/aar_reports/partials/standard_fields.php'); ?>
<?php else: ?>

<template x-if="isCustom">
<div class="ath-aar-custom-block">
    <p class="ath-aar-custom-block__title">Questions du debriefing</p>
    <template x-for="field in currentFields" :key="field.id">
        <div class="ath-aar-custom-q">
            <label>
                <span x-text="field.label"></span>
                <span class="ath-aar-req" x-show="field.required">obligatoire</span>
            </label>
            <p class="ath-aar-custom-q__help" x-show="field.help" x-text="field.help"></p>

            <template x-if="field.type === 'text'">
                <input type="text" :name="'answers[' + field.id + ']'" :required="field.required">
            </template>
            <template x-if="field.type === 'textarea'">
                <textarea :name="'answers[' + field.id + ']'" rows="4" :required="field.required"></textarea>
            </template>
            <template x-if="field.type === 'select'">
                <select :name="'answers[' + field.id + ']'" :required="field.required">
                    <option value="">Choisir</option>
                    <template x-for="opt in (field.options || [])" :key="opt">
                        <option :value="opt" x-text="opt"></option>
                    </template>
                </select>
            </template>
            <template x-if="field.type === 'checkbox' && !(field.options || []).length">
                <div class="ath-aar-yesno">
                    <label class="ath-aar-yesno__opt">
                        <input type="radio" :name="'answers[' + field.id + ']'" value="1" :required="field.required">
                        Oui
                    </label>
                    <label class="ath-aar-yesno__opt">
                        <input type="radio" :name="'answers[' + field.id + ']'" value="0" :required="field.required">
                        Non
                    </label>
                </div>
            </template>
            <template x-if="field.type === 'checkbox' && (field.options || []).length">
                <div class="ath-aar-checks">
                    <template x-for="opt in (field.options || [])" :key="opt">
                        <label class="ath-aar-checks__opt">
                            <input type="checkbox" :name="'answers[' + field.id + '][]'" :value="opt">
                            <span x-text="opt"></span>
                        </label>
                    </template>
                </div>
            </template>
        </div>
    </template>
    <p class="ath-aar-custom-q__help" x-show="currentFields.length === 0">Ce modèle n’a pas encore de question.</p>
</div>
</template>

<template x-if="!isCustom">
<div>
    <?php require base_path('views/admin/aar_reports/partials/standard_fields.php'); ?>
</div>
</template>
<?php endif; ?>
