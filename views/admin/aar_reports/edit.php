<?php
declare(strict_types=1);

/**
 * Modification d'un compte rendu (/back-office/atak/comptes-rendus/{id}/edit).
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus');
$report = is_array($aarReport ?? null) ? $aarReport : [];
$missions = is_array($aarMissions ?? null) ? $aarMissions : [];
$missing = [];
$templates = [];
$isEdit = true;
$id = (int) ($report['id'] ?? 0);
$cancelUrl = $base . '/' . $id;
$submitLabel = 'Enregistrer';
?>
<form method="post" action="<?= $h($base . '/' . $id) ?>" class="bo-aar bo-aar-form bo-aar-compose" id="bo-aar-form">
  <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken ?? '') ?>">
  <div class="bo-aar-compose__main">
    <?php require base_path('views/admin/aar_reports/partials/form_fields.php'); ?>
  </div>
  <?php require base_path('views/admin/aar_reports/partials/compose_aside.php'); ?>
</form>
<script src="<?= $h(asset_url('assets/js/back-office-aar.js')) ?>" defer></script>
