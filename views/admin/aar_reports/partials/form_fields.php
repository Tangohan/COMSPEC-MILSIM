<?php
declare(strict_types=1);

/**
 * Rédacteur du compte rendu (création / édition), en étapes numérotées. Interactions : back-office-aar.js.
 *
 * @var array<string, mixed> $report
 * @var bool $isEdit
 * @var list<array<string, mixed>> $missions
 * @var list<array<string, mixed>> $missing    missions clôturées sans compte rendu (création)
 * @var list<array<string, mixed>> $templates  questionnaires actifs (création)
 */
use App\Services\Aar\AarReportAdminService as Aar;

$report = is_array($report ?? null) ? $report : [];
$missions = is_array($missions ?? null) ? $missions : [];
$missing = is_array($missing ?? null) ? $missing : [];
$templates = is_array($templates ?? null) ? $templates : [];
$isEdit = !empty($isEdit);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ico = require base_path('views/admin/aar_reports/partials/icons.php');
$val = static function (string $key, string $default = '') use ($report): string {
    $v = $report[$key] ?? $default;

    return is_scalar($v) ? trim((string) $v) : $default;
};
$fmt = static function (mixed $raw): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date('d/m/Y H:i', $t) : '';
};
$stepHead = static function (string $icon, string $title, string $lead) use ($ico, $h): string {
    return '<header class="bo-aar-step__head"><span class="bo-aar-step__n" aria-hidden="true"></span>'
        . '<span class="bo-aar-step__ico">' . $ico($icon) . '</span>'
        . '<span class="bo-aar-step__txt"><b class="bo-aar-step__t">' . $h($title) . '</b><span class="bo-aar-step__lead">' . $h($lead) . '</span></span></header>';
};
$status = $val('status', 'pending');
$missionId = (int) ($report['mission_cycle_id'] ?? 0);
$isCustomReport = $isEdit && !empty($report['is_custom']);
$missingIds = array_map(static fn ($m) => (int) $m['id'], $missing);
?>
<section class="ath-panel bo-aar-step" id="etape-mission">
  <?= $stepHead('mission', 'Mission et identification', 'Rattacher le retour à sa mission permet de mesurer le délai de dépôt et de retrouver la synthèse de la mission.') ?>

  <?php if (!$isEdit && $missing !== []): ?>
  <p class="bo-aar-sublabel">Missions clôturées qui attendent leur compte rendu</p>
  <div class="bo-aar-cards bo-aar-cards--missions" role="radiogroup" aria-label="Mission">
    <?php foreach (array_slice($missing, 0, 6) as $m): $mid = (int) $m['id']; $hrs = $m['hours_since_end'] ?? null; ?>
      <label class="bo-aar-card">
        <input type="radio" name="_mission_pick" value="<?= $mid ?>"<?= $missionId === $mid ? ' checked' : '' ?> data-aar-mission-pick>
        <span class="bo-aar-card__ico"><?= $ico('mission') ?></span>
        <span class="bo-aar-card__body">
          <b><?= $h(($m['title'] ?? '') ?: 'Mission #' . $mid) ?></b>
          <small><?= $fmt($m['ended_at'] ?? null) !== '' ? 'Terminée le ' . $h($fmt($m['ended_at'])) : 'Clôturée' ?></small>
        </span>
        <?php if ($hrs !== null): ?><span class="ath-tag ath-tag--<?= !empty($m['late']) ? 'bad' : 'warn' ?> bo-aar-card__tag"><?= $ico('clock') ?> <?= $h(Aar::formatDelay((float) $hrs)) ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>
    <label class="bo-aar-card bo-aar-card--ghost">
      <input type="radio" name="_mission_pick" value=""<?= $missionId === 0 || !in_array($missionId, $missingIds, true) ? ' checked' : '' ?> data-aar-mission-pick>
      <span class="bo-aar-card__ico"><?= $ico('free') ?></span>
      <span class="bo-aar-card__body"><b>Autre mission ou retour libre</b><small>Entraînement, événement, mission plus ancienne…</small></span>
    </label>
  </div>
  <?php endif; ?>

  <div class="bo-aar-grid">
    <label class="bo-aar-field" data-aar-mission-field<?= !$isEdit && $missing !== [] && in_array($missionId, $missingIds, true) ? ' hidden' : '' ?>>
      <span>Mission liée</span>
      <select name="mission_cycle_id" data-aar-mission>
        <option value="" data-start="" data-end="">Aucune mission</option>
        <?php foreach ($missions as $m): $mid = (int) ($m['id'] ?? 0); ?>
          <option value="<?= $mid ?>" data-title="<?= $h($m['title'] ?? '') ?>" data-status="<?= $h($m['status_label'] ?? '') ?>" data-start="<?= $h($fmt($m['started_at'] ?? null)) ?>" data-end="<?= $h($fmt($m['ended_at'] ?? null)) ?>"<?= $missionId === $mid ? ' selected' : '' ?>><?= $h(($m['title'] ?? '') ?: 'Mission #' . $mid) ?> · <?= $h($m['status_label'] ?? '') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="bo-aar-field">
      <span>Opération</span>
      <input name="operation_label" maxlength="200" value="<?= $h($val('operation_label')) ?>" placeholder="Ex. Aigle noir" data-aar-operation>
      <small>Le nom affiché en tête du compte rendu et dans le registre des opérations.</small>
    </label>
    <label class="bo-aar-field<?= $isEdit ? '' : ' bo-aar-field--wide' ?>">
      <span>Titre du compte rendu <em>obligatoire</em></span>
      <input name="title" required maxlength="200" value="<?= $h($val('title')) ?>" placeholder="Ex. Compte rendu — Aigle noir" data-aar-check-src="title">
    </label>
    <?php if ($isEdit): ?>
    <label class="bo-aar-field">
      <span>Statut</span>
      <select name="status">
        <?php foreach (Aar::STATUSES as $code => $label): ?>
          <option value="<?= $h($code) ?>"<?= $status === $code ? ' selected' : '' ?>><?= $h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small>Le circuit habituel se pilote depuis la fiche (relecture, validation).</small>
    </label>
    <?php endif; ?>
  </div>
</section>

<?php if (!$isEdit && $templates !== []): ?>
<section class="ath-panel bo-aar-step" id="etape-format">
  <?= $stepHead('format', 'Format du retour', 'Le formulaire standard convient à la plupart des opérations ; un questionnaire pose les questions préparées par l’état-major.') ?>
  <div class="bo-aar-cards" role="radiogroup" aria-label="Format">
    <label class="bo-aar-card">
      <input type="radio" name="template_id" value="0" checked data-aar-template>
      <span class="bo-aar-card__ico"><?= $ico('standard') ?></span>
      <span class="bo-aar-card__body"><b>Formulaire standard</b><small>Synthèse, points forts et faibles, enseignements, conclusion.</small></span>
    </label>
    <?php foreach ($templates as $tpl): $n = count(is_array($tpl['fields'] ?? null) ? $tpl['fields'] : []); ?>
      <label class="bo-aar-card">
        <input type="radio" name="template_id" value="<?= (int) $tpl['id'] ?>" data-aar-template>
        <span class="bo-aar-card__ico"><?= $ico('quiz') ?></span>
        <span class="bo-aar-card__body"><b><?= $h($tpl['title'] ?? 'Modèle') ?></b><small><?= $n ?> question<?= $n > 1 ? 's' : '' ?><?= trim((string) ($tpl['description'] ?? '')) !== '' ? ' · ' . $h($tpl['description']) : '' ?></small></span>
      </label>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($isCustomReport): ?>
  <section class="ath-panel bo-aar-step">
    <?= $stepHead('quiz', 'Questionnaire', 'Les questions en vigueur au moment du dépôt.') ?>
    <?php
    $customFields = is_array($report['custom_fields'] ?? null) ? $report['custom_fields'] : [];
    $customAnswers = is_array($report['custom_answers'] ?? null) ? $report['custom_answers'] : [];
    require base_path('views/admin/aar_reports/partials/custom_fields.php');
    ?>
  </section>
<?php else: ?>
  <fieldset class="bo-aar-plain" data-aar-block="0">
    <?php require base_path('views/admin/aar_reports/partials/standard_fields.php'); ?>
  </fieldset>
  <?php if (!$isEdit): foreach ($templates as $tpl): ?>
    <fieldset class="ath-panel bo-aar-step" data-aar-block="<?= (int) $tpl['id'] ?>" hidden disabled>
      <?= $stepHead('quiz', (string) ($tpl['title'] ?? 'Questionnaire'), trim((string) ($tpl['description'] ?? '')) !== '' ? (string) $tpl['description'] : 'Répondez aux questions préparées pour ce type d’opération.') ?>
      <?php
      $customFields = is_array($tpl['fields'] ?? null) ? $tpl['fields'] : [];
      $customAnswers = [];
      $customPrefix = 't' . (int) $tpl['id'] . '-';
      require base_path('views/admin/aar_reports/partials/custom_fields.php');
      ?>
    </fieldset>
  <?php endforeach; endif; ?>
<?php endif; ?>

<?php require base_path('views/admin/aar_reports/partials/actions_fields.php'); ?>
