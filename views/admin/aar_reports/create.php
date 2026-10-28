<?php
declare(strict_types=1);

/**
 * Nouveau compte rendu (/back-office/atak/comptes-rendus/nouveau[?mission=ID]).
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus');
$report = is_array($aarReport ?? null) ? $aarReport : [];
$missions = is_array($aarMissions ?? null) ? $aarMissions : [];
$missing = is_array($aarMissing ?? null) ? $aarMissing : [];
$templates = is_array($aarTemplates ?? null) ? $aarTemplates : [];
$isEdit = false;
$cancelUrl = $base;
$submitLabel = 'Déposer le compte rendu';
?>
<form method="post" action="<?= $h($base) ?>" class="bo-aar bo-aar-form bo-aar-compose" id="bo-aar-form">
  <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken ?? '') ?>">
  <div class="bo-aar-compose__main">
    <?php require base_path('views/admin/aar_reports/partials/form_fields.php'); ?>
  </div>
  <?php require base_path('views/admin/aar_reports/partials/compose_aside.php'); ?>
</form>
<script src="<?= $h(asset_url('assets/js/back-office-aar.js')) ?>" defer></script>
