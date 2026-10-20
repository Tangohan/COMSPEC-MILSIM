<?php
declare(strict_types=1);

/**
 * Champs du compte rendu (création / édition). Interactions : back-office-aar.js.
 *
 * @var array<string, mixed> $report
 * @var bool $isEdit
 * @var list<array<string, mixed>> $missions
 * @var list<array<string, mixed>> $templates  (création uniquement)
 */
$report = is_array($report ?? null) ? $report : [];
$missions = is_array($missions ?? null) ? $missions : [];
$templates = is_array($templates ?? null) ? $templates : [];
$isEdit = !empty($isEdit);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$val = static function (string $key, string $default = '') use ($report): string {
    $v = $report[$key] ?? $default;

    return is_scalar($v) ? trim((string) $v) : $default;
};
$status = $val('status', 'pending');
$missionId = (int) ($report['mission_cycle_id'] ?? 0);
$isCustomReport = $isEdit && !empty($report['is_custom']);
?>
<fieldset class="ath-panel bo-aar-fs">
  <legend class="bo-aar-fs__legend">Identification</legend>
  <div class="bo-aar-grid">
    <label class="bo-aar-field bo-aar-field--wide">
      <span>Titre <em>obligatoire</em></span>
      <input name="title" required maxlength="200" value="<?= $h($val('title')) ?>" placeholder="Ex. Compte rendu — Opération Aigle noir">
    </label>
    <label class="bo-aar-field">
      <span>Opération</span>
      <input name="operation_label" maxlength="200" value="<?= $h($val('operation_label')) ?>" placeholder="Nom de l’opération" data-aar-operation>
    </label>
    <label class="bo-aar-field">
      <span>Mission liée</span>
      <select name="mission_cycle_id" data-aar-mission>
        <option value="">Aucune</option>
        <?php foreach ($missions as $m): $mid = (int) ($m['id'] ?? 0); ?>
          <option value="<?= $mid ?>" data-title="<?= $h($m['title'] ?? '') ?>"<?= $missionId === $mid ? ' selected' : '' ?>><?= $h(($m['title'] ?? '') ?: 'Mission #' . $mid) ?> · <?= $h($m['status_label'] ?? '') ?></option>
        <?php endforeach; ?>
      </select>
      <small>Lier la mission permet de mesurer le délai de dépôt et d’exporter la synthèse de la mission.</small>
    </label>
    <?php if ($isEdit): ?>
    <label class="bo-aar-field">
      <span>Statut</span>
      <select name="status">
        <?php foreach (\App\Services\Aar\AarReportAdminService::STATUSES as $code => $label): ?>
          <option value="<?= $h($code) ?>"<?= $status === $code ? ' selected' : '' ?>><?= $h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
  </div>
</fieldset>

<?php if (!$isEdit && $templates !== []): ?>
<fieldset class="ath-panel bo-aar-fs">
  <legend class="bo-aar-fs__legend">Formulaire</legend>
  <div class="bo-aar-choice" role="radiogroup" aria-label="Formulaire">
    <label class="bo-aar-choice__opt"><input type="radio" name="template_id" value="0" checked data-aar-template> <b>Formulaire standard</b><small>Synthèse, points forts et faibles, enseignements, actions.</small></label>
    <?php foreach ($templates as $tpl): $n = count(is_array($tpl['fields'] ?? null) ? $tpl['fields'] : []); ?>
      <label class="bo-aar-choice__opt"><input type="radio" name="template_id" value="<?= (int) $tpl['id'] ?>" data-aar-template> <b><?= $h($tpl['title'] ?? 'Modèle') ?></b><small><?= $n ?> question<?= $n > 1 ? 's' : '' ?><?= trim((string) ($tpl['description'] ?? '')) !== '' ? ' · ' . $h($tpl['description']) : '' ?></small></label>
    <?php endforeach; ?>
  </div>
</fieldset>
<?php endif; ?>

<?php if ($isCustomReport): ?>
  <fieldset class="ath-panel bo-aar-fs">
    <legend class="bo-aar-fs__legend">Questionnaire</legend>
    <?php
    $customFields = is_array($report['custom_fields'] ?? null) ? $report['custom_fields'] : [];
    $customAnswers = is_array($report['custom_answers'] ?? null) ? $report['custom_answers'] : [];
    require base_path('views/admin/aar_reports/partials/custom_fields.php');
    ?>
  </fieldset>
<?php else: ?>
  <fieldset class="bo-aar-plain" data-aar-block="0">
    <?php require base_path('views/admin/aar_reports/partials/standard_fields.php'); ?>
  </fieldset>
  <?php if (!$isEdit): foreach ($templates as $tpl): ?>
    <fieldset class="ath-panel bo-aar-fs" data-aar-block="<?= (int) $tpl['id'] ?>" hidden disabled>
      <legend class="bo-aar-fs__legend"><?= $h($tpl['title'] ?? 'Questionnaire') ?></legend>
      <?php
      $customFields = is_array($tpl['fields'] ?? null) ? $tpl['fields'] : [];
      $customAnswers = [];
      $customPrefix = 't' . (int) $tpl['id'] . '-';
      require base_path('views/admin/aar_reports/partials/custom_fields.php');
      ?>
    </fieldset>
  <?php endforeach; endif; ?>
<?php endif; ?>

<?php $actionsLegend = $isCustomReport || (!$isEdit && $templates !== []) ? 'Actions décidées' : '4 · Actions décidées'; ?>
<?php require base_path('views/admin/aar_reports/partials/actions_fields.php'); ?>
